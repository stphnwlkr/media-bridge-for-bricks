<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

/** One-way, resumable copy. Import itself never modifies source records or media bytes. Cleanup is a separate action. */
class Folder_Import {
	private const JOB = 'uplink_mbb_happyfiles_import';
	private const LOCK = 'uplink_mbb_happyfiles_import_lock';
	private const SOURCE_META = '_uplink_mbb_happyfiles_source';
	public function __construct() {
		add_action( 'wp_ajax_uplink_mbb_folder_import', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_uplink_mbb_happyfiles_import', array( $this, 'ajax' ) );
	}
	public static function can_import(): bool {
		$tax = get_taxonomy( 'mbb_folder' );
		return current_user_can( 'manage_options' ) && current_user_can( 'upload_files' ) && $tax
			&& current_user_can( $tax->cap->manage_terms ) && current_user_can( $tax->cap->assign_terms );
	}
	public static function preview( string $source = 'happyfiles' ) {
		if ( ! self::can_import() ) return self::forbidden();
		return Folder_Import_Sources::preview( $source );
	}
	private static function forbidden(): \WP_Error { return new \WP_Error( 'import_forbidden', __( 'Only administrators with media and folder permissions can import folders.', 'media-bridge-for-bricks' ) ); }
	private static function summary( array $job ): array {
		$job['source'] = $job['source'] ?? 'happyfiles';
		return array_intersect_key( $job, array_flip( array( 'id', 'status', 'phase', 'total', 'cursor', 'created', 'reused', 'assigned', 'existing', 'denied', 'message', 'source', 'media', 'select_provider', 'retire_source', 'cleanup_complete' ) ) );
	}
	public static function status() {
		if ( ! self::can_import() ) return self::forbidden();
		$job = get_option( self::JOB, array() );
		if ( ! $job ) return array();
		if ( (int) $job['owner'] !== get_current_user_id() ) return array( 'status' => 'other', 'message' => __( 'Another administrator owns the last import. A paused import expires after 30 minutes.', 'media-bridge-for-bricks' ) );
		return self::summary( $job );
	}
	/** Atomic, expiring mutex for start/step requests, including two browser tabs. */
	public static function locked( callable $work ) {
		global $wpdb;
		$old = get_option( self::LOCK, '' );
		if ( $old && (int) $old < time() - 120 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-delete prevents removing a newer process's mutex.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $old ) );
			wp_cache_delete( self::LOCK, 'options' );
		}
		$token = time() . ':' . wp_generate_uuid4();
		if ( ! add_option( self::LOCK, $token, '', false ) ) return new \WP_Error( 'import_busy', __( 'An import request is already running. Wait a moment and resume.', 'media-bridge-for-bricks' ) );
		try { return $work(); }
		finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release only our own mutex; a timed-out request cannot delete a successor's lock.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $token ) );
			wp_cache_delete( self::LOCK, 'options' );
		}
	}
	public static function start( bool $include_media, bool $select_provider, string $source_key = 'happyfiles', bool $retire_source = false ) {
		if ( ! self::can_import() ) return self::forbidden();
		if ( ! Folder_Import_Sources::valid( $source_key ) ) return new \WP_Error( 'import_source', __( 'Choose a supported folder source.', 'media-bridge-for-bricks' ) );
		if ( $retire_source && ( ! $include_media || ( Folder_Import_Sources::site_active_files( $source_key ) && ! current_user_can( 'activate_plugins' ) ) ) ) return new \WP_Error( 'retire_unavailable', __( 'Automatic cleanup requires media assignments and permission to deactivate any site-active source plugin.', 'media-bridge-for-bricks' ) );
		return self::locked( static function () use ( $include_media, $select_provider, $source_key, $retire_source ) {
			$cleanup = get_option( 'uplink_mbb_folder_cleanup', array() );
			if ( ( $cleanup['status'] ?? '' ) === 'running' ) return new \WP_Error( 'cleanup_running', __( 'Finish the legacy folder cleanup before starting another import.', 'media-bridge-for-bricks' ) );
			$old = get_option( self::JOB, array() );
			if ( $old && 'running' === $old['status'] && $old['updated'] > time() - 1800 ) {
				if ( (int) $old['owner'] === get_current_user_id() && ( $old['source'] ?? 'happyfiles' ) !== $source_key ) return new \WP_Error( 'import_source_busy', __( 'Resume the current source import before starting another source, or wait 30 minutes for it to expire.', 'media-bridge-for-bricks' ) );
				return (int) $old['owner'] === get_current_user_id() ? self::summary( $old ) : new \WP_Error( 'import_owned', __( 'Another administrator is importing folders. Try again after that import finishes.', 'media-bridge-for-bricks' ) );
			}
			$terms = Folder_Import_Sources::terms( $source_key );
			if ( is_wp_error( $terms ) ) return $terms;
			if ( ! $terms ) return new \WP_Error( 'import_empty', __( 'No media folders were found for this source.', 'media-bridge-for-bricks' ) );
			$children = array(); $known = array( 0 => true );
			foreach ( $terms as $term ) $known[ $term->term_id ] = true;
			foreach ( $terms as $term ) {
				if ( ! isset( $known[ $term->parent ] ) ) return new \WP_Error( 'source_hierarchy', __( 'A source folder has a missing parent. Repair the source hierarchy before importing.', 'media-bridge-for-bricks' ) );
				$children[ $term->parent ][] = $term;
			}
			foreach ( $children as &$siblings ) usort( $siblings, static function ( $a, $b ) use ( $source_key ) {
				return ( Folder_Import_Sources::position( $source_key, $a->term_id ) <=> Folder_Import_Sources::position( $source_key, $b->term_id ) ) ?: ( $a->term_id <=> $b->term_id );
			} );
			unset( $siblings );
			$queue = $children[0] ?? array(); $plan = array();
			for ( $i = 0; $i < count( $queue ); ++$i ) {
				$term = $queue[ $i ];
				$plan[] = array( 'id' => (int) $term->term_id, 'parent' => (int) $term->parent, 'signature' => Folder_Import_Sources::signature( $term ) );
				foreach ( $children[ $term->term_id ] ?? array() as $child ) $queue[] = $child;
			}
			if ( count( $plan ) !== count( $terms ) ) return new \WP_Error( 'source_cycle', __( 'The source contains a folder cycle. Repair the source hierarchy before importing.', 'media-bridge-for-bricks' ) );
			$job = array( 'id' => wp_generate_uuid4(), 'blog_id' => get_current_blog_id(), 'source' => $source_key, 'owner' => get_current_user_id(), 'updated' => time(), 'status' => 'running', 'phase' => 'folders', 'total' => count( $plan ), 'cursor' => 0, 'created' => 0, 'reused' => 0, 'assigned' => 0, 'existing' => 0, 'denied' => 0, 'message' => '', 'plan' => $plan, 'map' => array(), 'media' => $include_media, 'retire_source' => $retire_source, 'select_provider' => $select_provider, 'folder_cursor' => 0, 'media_cursor' => 0 );
			update_option( self::JOB, $job, false );
			return self::summary( $job );
		} );
	}
	private static function destination( \WP_Term $source, int $parent, Folder_Provider $folders, string $source_key ) {
		// Keep beta.7 HappyFiles mappings and give every other provider its own namespace.
		$meta_key = 'happyfiles' === $source_key ? self::SOURCE_META : '_uplink_mbb_import_source';
		$meta_value = 'happyfiles' === $source_key ? (string) $source->term_id : $source_key . ':' . $source->term_id;
		$mapped = get_terms( array( 'taxonomy' => $folders->taxonomy(), 'hide_empty' => false, 'meta_key' => $meta_key, 'meta_value' => $meta_value, 'number' => 1 ) );
		if ( is_wp_error( $mapped ) ) return $mapped;
		if ( $mapped ) return array( (int) $mapped[0]->term_id, false );
		$name = sanitize_text_field( wp_specialchars_decode( $source->name ) );
		$matches = get_terms( array( 'taxonomy' => $folders->taxonomy(), 'hide_empty' => false, 'name' => $name, 'parent' => $parent, 'number' => 1 ) );
		if ( is_wp_error( $matches ) ) return $matches;
		$created = ! $matches;
		if ( $matches ) $id = (int) $matches[0]->term_id;
		else {
			$result = $folders->create_term( array( 'name' => $name, 'parent' => $parent ) );
			if ( is_wp_error( $result ) ) return $result;
			$id = (int) $result['term_id'];
			$folders->append_position( $id, $parent );
		}
		if ( ! in_array( $meta_value, get_term_meta( $id, $meta_key ), true ) ) add_term_meta( $id, $meta_key, $meta_value );
		return array( $id, $created );
	}
	public static function step( string $id ) {
		if ( ! self::can_import() ) return self::forbidden();
		return self::locked( static function () use ( $id ) {
			global $wpdb;
			$job = get_option( self::JOB, array() );
			if ( ! $job || ! hash_equals( $job['id'], $id ) || (int) $job['owner'] !== get_current_user_id() ) return new \WP_Error( 'import_invalid', __( 'This import session is not available to your account. Reload the import page.', 'media-bridge-for-bricks' ) );
			if ( 'running' !== $job['status'] ) return self::summary( $job );
			$source_key = $job['source'] ?? 'happyfiles';
			$sources = Folder_Import_Sources::terms( $source_key );
			if ( is_wp_error( $sources ) ) return $sources;
			$folders = new Folder_Provider(); $error = null;
			if ( 'folders' === $job['phase'] ) {
				$end = min( $job['total'], $job['cursor'] + 30 );
				while ( $job['cursor'] < $end ) {
					$row = $job['plan'][ $job['cursor'] ]; $source = $sources[ $row['id'] ] ?? null;
					$parent = $row['parent'] ? ( $job['map'][ $row['parent'] ] ?? 0 ) : 0;
					if ( ! $source instanceof \WP_Term || (int) $source->parent !== (int) $row['parent'] || ( $row['parent'] && ! $folders->get_term( $parent ) ) ) { $error = __( 'A source or destination folder changed during import. Start a new import to resume safely.', 'media-bridge-for-bricks' ); break; }
					$result = self::destination( $source, $parent, $folders, $source_key );
					if ( is_wp_error( $result ) ) { $error = $result->get_error_message(); break; }
					$job['map'][ $row['id'] ] = $result[0]; ++$job[ $result[1] ? 'created' : 'reused' ]; ++$job['cursor'];
				}
				if ( $job['cursor'] === $job['total'] ) $job['phase'] = $job['media'] ? 'assignments' : 'complete';
			} elseif ( 'assignments' === $job['phase'] ) {
				$budget = 100; $scanned = 0;
				while ( $budget > 0 && $scanned++ < 10 && $job['folder_cursor'] < $job['total'] ) {
					$row = $job['plan'][ $job['folder_cursor'] ]; $source = $sources[ $row['id'] ] ?? null; $target = $job['map'][ $row['id'] ];
					if ( ! $source instanceof \WP_Term || (int) $source->parent !== (int) $row['parent'] || ! $folders->get_term( $target ) ) { $error = __( 'A source or destination folder changed during import. Start a new import to resume safely.', 'media-bridge-for-bricks' ); break; }
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh, bounded keyset reads prevent loading an entire media library into memory.
					$ids = $wpdb->get_col( $wpdb->prepare( "SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = %d AND tr.object_id > %d AND p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft') ORDER BY tr.object_id ASC LIMIT %d", $source->term_taxonomy_id, $job['media_cursor'], 'attachment', $budget ) );
					if ( $wpdb->last_error ) { $error = __( 'The media assignments could not be read. Start a new import to retry safely.', 'media-bridge-for-bricks' ); break; }
					$count = count( $ids ); $limit = $budget;
					foreach ( $ids as $attachment_id ) {
						$attachment_id = (int) $attachment_id;
						if ( ! current_user_can( 'edit_post', $attachment_id ) ) ++$job['denied'];
						elseif ( has_term( $target, $folders->taxonomy(), $attachment_id ) ) ++$job['existing'];
						else {
							$result = wp_set_object_terms( $attachment_id, array( $target ), $folders->taxonomy(), true );
							if ( is_wp_error( $result ) ) { $error = $result->get_error_message(); break; }
							++$job['assigned'];
						}
						$job['media_cursor'] = $attachment_id; --$budget;
					}
					if ( $error ) break;
					if ( $count < $limit ) { ++$job['folder_cursor']; $job['media_cursor'] = 0; }
				}
				if ( $job['folder_cursor'] === $job['total'] ) $job['phase'] = 'complete';
			}
			if ( $error ) { $job['status'] = 'error'; $job['message'] = $error; }
			elseif ( 'complete' === $job['phase'] ) {
				$job['status'] = 'complete';
				$receipts = get_option( 'uplink_mbb_import_receipts', array() );
				$receipts[ $source_key ] = $job;
				update_option( 'uplink_mbb_import_receipts', $receipts, false );
				if ( $job['select_provider'] ) Plugin::update_settings( array_merge( Plugin::settings(), array( 'prefer_media_bridge_folders' => 1 ) ) );
				delete_transient( 'uplink_mbb_media_health_v3' );
			}
			$job['updated'] = time(); update_option( self::JOB, $job, false );
			return self::summary( $job );
		} );
	}
	public function ajax(): void {
		check_ajax_referer( 'uplink_mbb_happyfiles_import', 'nonce' );
		if ( ! self::can_import() ) wp_send_json_error( array( 'message' => self::forbidden()->get_error_message() ), 403 );
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$source = isset( $_POST['source'] ) ? ( is_string( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '' ) : 'happyfiles';
		if ( 'start' === $mode ) $result = self::start( isset( $_POST['include_media'] ) && is_string( $_POST['include_media'] ) && '1' === sanitize_key( wp_unslash( $_POST['include_media'] ) ), isset( $_POST['select_provider'] ) && is_string( $_POST['select_provider'] ) && '1' === sanitize_key( wp_unslash( $_POST['select_provider'] ) ), $source, isset( $_POST['retire_source'] ) && is_string( $_POST['retire_source'] ) && '1' === sanitize_key( wp_unslash( $_POST['retire_source'] ) ) );
		elseif ( 'step' === $mode ) $result = self::step( isset( $_POST['job'] ) && is_string( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '' );
		elseif ( 'status' === $mode ) $result = self::status();
		else $result = new \WP_Error( 'import_mode', __( 'Choose a valid import action.', 'media-bridge-for-bricks' ) );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( $result );
	}
}
