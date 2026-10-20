<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Explicit, verified removal of imported media-folder records only. */
final class Folder_Cleanup {
	private const JOB = 'uplink_mbb_folder_cleanup';
	public function __construct() { add_action( 'wp_ajax_uplink_mbb_folder_cleanup', array( $this, 'ajax' ) ); }
	private static function error( string $message ): \WP_Error { return new \WP_Error( 'cleanup_unavailable', $message ); }
	private static function summary( array $job ): array { return array_intersect_key( $job, array_flip( array( 'id', 'source', 'status', 'total', 'removed', 'assignments' ) ) ); }
	private static function proof( string $source ) {
		if ( ! Folder_Import::can_import() || ! Folder_Import_Sources::valid( $source ) ) return self::error( __( 'Administrator import permissions and a supported source are required.', 'media-bridge-for-bricks' ) );
		$receipts = get_option( 'uplink_mbb_import_receipts', array() );
		$receipt = $receipts[ $source ] ?? array();
		if ( ! $receipt || (int) ( $receipt['blog_id'] ?? 0 ) !== get_current_blog_id() || empty( $receipt['media'] ) || ! empty( $receipt['denied'] ) || 'complete' !== $receipt['status'] ) return self::error( __( 'Complete an import with media assignments and no skipped permissions before clearing legacy data.', 'media-bridge-for-bricks' ) );
		return $receipt;
	}
	/** Recheck the source, destinations and every remaining source relationship. */
	private static function verify( array $receipt, array $remaining, bool $before_deactivation = false ) {
		global $wpdb;
		if ( (int) ( $receipt['blog_id'] ?? 0 ) !== get_current_blog_id() ) return self::error( __( 'This import belongs to a different site.', 'media-bridge-for-bricks' ) );
		$source = $receipt['source'];
		$definition = Folder_Import_Sources::definitions()[ $source ];
		$network_cleanup = ! empty( $receipt['network_cleanup'] ) && ! empty( $receipt['retirement_verified'] ) && ! empty( $receipt['retire_source'] ) && Folder_Import_Sources::network_active( $source );
		if ( ! $before_deactivation && ! $network_cleanup && ( 'active' === Folder_Import_Sources::plugin_status( $source ) || taxonomy_exists( $definition['taxonomy'] ) ) ) return self::error( Folder_Import_Sources::network_active( $source ) ? __( 'Use the post-import cleanup option to clear this site’s folder data while preserving network activation.', 'media-bridge-for-bricks' ) : __( 'Deactivate the source plugin before clearing legacy folder data.', 'media-bridge-for-bricks' ) );
		$terms = Folder_Import_Sources::terms( $source );
		if ( is_wp_error( $terms ) ) return $terms;
		$actual = array_map( 'intval', array_keys( $terms ) ); $expected = array_map( 'intval', $remaining ); sort( $actual ); sort( $expected );
		if ( $actual !== $expected ) return self::error( __( 'Source folders changed since import. Import the current data before clearing it.', 'media-bridge-for-bricks' ) );
		$plan = array_column( $receipt['plan'], null, 'id' );
		foreach ( $remaining as $id ) {
			$term = $terms[ $id ]; $target = (int) ( $receipt['map'][ $id ] ?? 0 );
			if ( empty( $plan[ $id ]['signature'] ) || ! hash_equals( $plan[ $id ]['signature'], Folder_Import_Sources::signature( $term ) ) || ! get_term( $target, 'mbb_folder' ) instanceof \WP_Term ) return self::error( __( 'Source or imported folders changed. Complete a fresh import before cleanup.', 'media-bridge-for-bricks' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Never remove term metadata shared with another taxonomy.
			$uses = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $id ) );
			if ( $wpdb->last_error || (int) $uses !== 1 ) return self::error( __( 'A legacy folder shares a term with another taxonomy. Cleanup is unavailable.', 'media-bridge-for-bricks' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Refuse to discard any source relationship that has not reached the mapped destination, including trash and non-media records.
			$missing = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id LEFT JOIN {$wpdb->term_taxonomy} dest ON dest.term_id = %d AND dest.taxonomy = %s LEFT JOIN {$wpdb->term_relationships} copied ON copied.term_taxonomy_id = dest.term_taxonomy_id AND copied.object_id = tr.object_id WHERE tr.term_taxonomy_id = %d AND (p.ID IS NULL OR p.post_type <> %s OR copied.object_id IS NULL)", $target, 'mbb_folder', $term->term_taxonomy_id, 'attachment' ) );
			if ( $wpdb->last_error || (int) $missing > 0 ) return self::error( __( 'Some legacy assignments are not present in Media Bridge. Cleanup is unavailable until every assignment has been preserved.', 'media-bridge-for-bricks' ) );
		}
		return $terms;
	}
	public static function retire( string $source, string $import_id ) {
		$proof = self::proof( $source ); if ( is_wp_error( $proof ) ) return $proof;
		if ( empty( $proof['retire_source'] ) || ! hash_equals( $proof['id'], $import_id ) || (int) $proof['owner'] !== get_current_user_id() ) return self::error( __( 'This import did not authorize automatic deactivation and cleanup.', 'media-bridge-for-bricks' ) );
		if ( Folder_Import_Sources::site_active_files( $source ) && ! current_user_can( 'activate_plugins' ) ) return self::error( __( 'You do not have permission to deactivate the site-active source plugin.', 'media-bridge-for-bricks' ) );
		return Folder_Import::locked( static function () use ( $source, $proof ) {
			$terms = self::verify( $proof, array_column( $proof['plan'], 'id' ), true ); if ( is_wp_error( $terms ) ) return $terms;
			deactivate_plugins( Folder_Import_Sources::site_active_files( $source ), false, false );
			if ( Folder_Import_Sources::site_active_files( $source ) ) return self::error( __( 'The source plugin could not be deactivated. Legacy data has not been cleared.', 'media-bridge-for-bricks' ) );
			$proof['retirement_verified'] = true;
			$proof['network_cleanup'] = Folder_Import_Sources::network_active( $source );
			$receipts = get_option( 'uplink_mbb_import_receipts', array() ); $receipts[ $source ] = $proof; update_option( 'uplink_mbb_import_receipts', $receipts, false );
			return array( 'deactivated' => ! $proof['network_cleanup'], 'network_preserved' => $proof['network_cleanup'] );
		} );
	}
	public static function preview( string $source ) {
		if ( ! Folder_Import::can_import() || ! Folder_Import_Sources::valid( $source ) ) return self::error( __( 'You cannot clear legacy data for this source.', 'media-bridge-for-bricks' ) );
		$job = get_option( self::JOB, array() );
		if ( ( $job['status'] ?? '' ) === 'running' ) {
			if ( (int) $job['owner'] !== get_current_user_id() ) return array( 'available' => false, 'reason' => __( 'Another administrator is clearing legacy folders.', 'media-bridge-for-bricks' ) );
			return array( 'available' => true, 'job' => self::summary( $job ), 'reason' => __( 'Resume the remaining legacy folder cleanup.', 'media-bridge-for-bricks' ) );
		}
		$terms = Folder_Import_Sources::terms( $source );
		if ( ! is_wp_error( $terms ) && ! $terms ) return array( 'available' => false, 'reason' => __( 'No legacy media folders remain.', 'media-bridge-for-bricks' ) );
		$proof = self::proof( $source );
		if ( is_wp_error( $proof ) ) return array( 'available' => false, 'reason' => $proof->get_error_message() );
		$terms = self::verify( $proof, array_column( $proof['plan'], 'id' ) );
		if ( is_wp_error( $terms ) ) return array( 'available' => false, 'reason' => $terms->get_error_message() );
		return array( 'available' => (bool) $terms, 'reason' => $terms ? __( 'Imported folders and assignments verified. Legacy folder data can be cleared.', 'media-bridge-for-bricks' ) : __( 'No legacy folders remain.', 'media-bridge-for-bricks' ) );
	}
	public static function start( string $source, bool $confirmed ) {
		if ( ! $confirmed ) return self::error( __( 'Confirm that legacy folder data can be permanently removed.', 'media-bridge-for-bricks' ) );
		$proof = self::proof( $source ); if ( is_wp_error( $proof ) ) return $proof;
		return Folder_Import::locked( static function () use ( $source, $proof ) {
			$old = get_option( self::JOB, array() );
			if ( ( $old['status'] ?? '' ) === 'running' ) return (int) $old['owner'] === get_current_user_id() && $old['source'] === $source ? self::summary( $old ) : self::error( __( 'Another cleanup is already running.', 'media-bridge-for-bricks' ) );
			$import = get_option( 'uplink_mbb_happyfiles_import', array() );
			if ( ( $import['status'] ?? '' ) === 'running' ) return self::error( __( 'Finish the current import before clearing legacy folders.', 'media-bridge-for-bricks' ) );
			$remaining = array_reverse( array_column( $proof['plan'], 'id' ) );
			$terms = self::verify( $proof, $remaining ); if ( is_wp_error( $terms ) ) return $terms;
			$job = array( 'id' => wp_generate_uuid4(), 'source' => $source, 'owner' => get_current_user_id(), 'status' => 'running', 'receipt' => $proof, 'remaining' => $remaining, 'total' => count( $remaining ), 'removed' => 0, 'assignments' => 0 );
			update_option( self::JOB, $job, false ); return self::summary( $job );
		} );
	}
	public static function step( string $id ) {
		if ( ! Folder_Import::can_import() ) return self::error( __( 'You cannot clear legacy folders.', 'media-bridge-for-bricks' ) );
		return Folder_Import::locked( static function () use ( $id ) {
			global $wpdb;
			$job = get_option( self::JOB, array() );
			if ( ! $job || ! hash_equals( $job['id'], $id ) || (int) $job['owner'] !== get_current_user_id() ) return self::error( __( 'This cleanup does not belong to your account.', 'media-bridge-for-bricks' ) );
			if ( 'running' !== $job['status'] ) return self::summary( $job );
			$terms = self::verify( $job['receipt'], $job['remaining'] ); if ( is_wp_error( $terms ) ) return $terms;
			$taxonomy = Folder_Import_Sources::definitions()[ $job['source'] ]['taxonomy'];
			$temporary_taxonomy = ! taxonomy_exists( $taxonomy );
			if ( $temporary_taxonomy ) register_taxonomy( $taxonomy, 'attachment', array( 'hierarchical' => true, 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'query_var' => false ) );
			$budget = 100; $folders = 0; $error = null;
			try {
				while ( $job['remaining'] && $budget > 0 && $folders < 30 ) {
					$source_id = $job['remaining'][0]; $term = $terms[ $source_id ];
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read only a bounded batch of remaining source relationships.
					$ids = $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d ORDER BY object_id ASC LIMIT %d", $term->term_taxonomy_id, $budget ) );
					if ( $wpdb->last_error ) { $error = self::error( __( 'Legacy assignments could not be read. Resume cleanup to retry.', 'media-bridge-for-bricks' ) ); break; }
					foreach ( $ids as $object ) {
						if ( ! current_user_can( 'edit_post', (int) $object ) || ! has_term( (int) $job['receipt']['map'][ $source_id ], 'mbb_folder', (int) $object ) ) { $error = self::error( __( 'Attachment edit permission changed. Restore permission before resuming cleanup.', 'media-bridge-for-bricks' ) ); break; }
						$result = wp_remove_object_terms( (int) $object, (int) $source_id, $taxonomy );
						if ( is_wp_error( $result ) || ! $result ) { $error = self::error( __( 'A legacy assignment could not be removed. Resume cleanup to retry.', 'media-bridge-for-bricks' ) ); break; }
						++$job['assignments']; --$budget;
					}
					if ( $error ) break;
					if ( $budget <= 0 ) break;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Avoid deleting relationships added during the current batch.
					$still_assigned = $wpdb->get_var( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d LIMIT 1", $term->term_taxonomy_id ) );
					if ( $wpdb->last_error ) { $error = self::error( __( 'Legacy folder verification failed. Resume cleanup to retry.', 'media-bridge-for-bricks' ) ); break; }
					if ( $still_assigned ) continue;
					$result = wp_delete_term( $source_id, $taxonomy );
					if ( is_wp_error( $result ) || ! $result ) { $error = self::error( __( 'A legacy folder could not be removed. Resume cleanup to retry.', 'media-bridge-for-bricks' ) ); break; }
					if ( 'wicked-folders' === $job['source'] ) delete_metadata( 'post', 0, '_wicked_folder_order__wf_attachment_folders__' . $source_id, '', true );
					array_shift( $job['remaining'] ); ++$job['removed']; ++$folders;
				}
			} finally { if ( $temporary_taxonomy ) unregister_taxonomy( $taxonomy ); }
			if ( ! $job['remaining'] ) {
				$job['status'] = 'complete';
				$import = get_option( 'uplink_mbb_happyfiles_import', array() );
				if ( ( $import['id'] ?? '' ) === $job['receipt']['id'] ) { $import['cleanup_complete'] = true; update_option( 'uplink_mbb_happyfiles_import', $import, false ); }
			}
			update_option( self::JOB, $job, false );
			return $error ?? self::summary( $job );
		} );
	}
	public function ajax(): void {
		check_ajax_referer( 'uplink_mbb_folder_cleanup', 'nonce' );
		if ( ! Folder_Import::can_import() ) wp_send_json_error( array( 'message' => __( 'You cannot clear legacy folder data.', 'media-bridge-for-bricks' ) ), 403 );
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$source = isset( $_POST['source'] ) && is_string( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		if ( 'retire' === $mode ) $result = self::retire( $source, isset( $_POST['job'] ) && is_string( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '' );
		elseif ( 'preview' === $mode ) $result = self::preview( $source );
		elseif ( 'start' === $mode ) $result = self::start( $source, isset( $_POST['confirmed'] ) && is_string( $_POST['confirmed'] ) && '1' === sanitize_key( wp_unslash( $_POST['confirmed'] ) ) );
		elseif ( 'step' === $mode ) $result = self::step( isset( $_POST['job'] ) && is_string( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '' );
		else $result = self::error( __( 'Choose a valid cleanup action.', 'media-bridge-for-bricks' ) );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( $result );
	}
}
