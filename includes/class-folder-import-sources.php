<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Read-only adapters for ordinary WordPress media-folder taxonomies. */
final class Folder_Import_Sources {
	public static function definitions(): array {
		return array(
			'happyfiles' => array( 'label' => 'HappyFiles', 'taxonomy' => defined( 'HAPPYFILES_TAXONOMY' ) ? HAPPYFILES_TAXONOMY : 'happyfiles_category', 'order' => defined( 'HAPPYFILES_POSITION' ) ? HAPPYFILES_POSITION : 'happyfiles_position' ),
			'wicked-folders' => array( 'label' => 'Wicked Folders', 'taxonomy' => 'wf_attachment_folders', 'order' => 'wf_order' ),
			'folders' => array( 'label' => 'Folders by Premio', 'taxonomy' => 'media_folder', 'order' => '' ),
			'enhanced-media-library' => array( 'label' => 'Enhanced Media Library', 'taxonomy' => 'media_category', 'order' => '' ),
			'wp-media-folder' => array( 'label' => 'WP Media Folder by JoomUnited', 'taxonomy' => 'wpmf-category', 'order' => '' ),
		);
	}
	/** Plugin inventory is separate from retained taxonomy data. */
	public static function plugin_files( string $key ): array {
		if ( ! self::valid( $key ) ) return array();
		if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$directories = array( 'happyfiles' => array( 'happyfiles', 'happyfiles-pro' ), 'wicked-folders' => array( 'wicked-folders', 'wicked-folders-pro' ), 'folders' => array( 'folders', 'folders-pro' ), 'enhanced-media-library' => array( 'enhanced-media-library', 'enhanced-media-library-pro' ), 'wp-media-folder' => array( 'wp-media-folder', 'wp-media-folder-pro' ) );
		$names = array( 'happyfiles' => '/^HappyFiles(?: Pro)?$/i', 'wicked-folders' => '/^Wicked Folders(?: Pro)?$/i', 'folders' => '/^Folders(?: Pro)?$/i', 'enhanced-media-library' => '/^Enhanced Media Library(?: Pro)?$/i', 'wp-media-folder' => '/^WP Media Folder(?: Pro)?$/i' );
		$files = array();
		foreach ( get_plugins() as $file => $plugin ) if ( in_array( dirname( $file ), $directories[ $key ], true ) || preg_match( $names[ $key ], $plugin['Name'] ?? '' ) ) $files[] = $file;
		return $files;
	}
	public static function network_active( string $key ): bool {
		foreach ( self::plugin_files( $key ) as $file ) if ( is_plugin_active_for_network( $file ) ) return true;
		return false;
	}
	public static function site_active_files( string $key ): array {
		return array_values( array_filter( self::plugin_files( $key ), static fn( $file ) => is_plugin_active( $file ) && ! is_plugin_active_for_network( $file ) ) );
	}
	public static function plugin_status( string $key ): string {
		$files = self::plugin_files( $key );
		foreach ( $files as $file ) if ( is_plugin_active( $file ) || is_plugin_active_for_network( $file ) ) return 'active';
		return $files ? 'inactive' : 'missing';
	}
	public static function status_label( string $status, bool $has_data ): string {
		if ( 'active' === $status ) return __( 'Plugin active', 'media-bridge-for-bricks' );
		if ( 'inactive' === $status ) return $has_data ? __( 'Plugin installed but inactive · Leftover folder data detected', 'media-bridge-for-bricks' ) : __( 'Plugin installed but inactive', 'media-bridge-for-bricks' );
		return $has_data ? __( 'Plugin not installed · Leftover folder data detected', 'media-bridge-for-bricks' ) : __( 'Plugin not installed', 'media-bridge-for-bricks' );
	}
	public static function signature( \WP_Term $term ): string {
		return hash( 'sha256', wp_json_encode( array( (int) $term->term_id, (int) $term->term_taxonomy_id, (int) $term->parent, $term->name, $term->slug ) ) );
	}
	public static function valid( string $key ): bool { return isset( self::definitions()[ $key ] ); }
	public static function label( string $key ): string { return self::definitions()[ $key ]['label'] ?? ''; }
	public static function terms( string $key ) {
		global $wpdb;
		if ( ! self::valid( $key ) ) return new \WP_Error( 'import_source', __( 'Choose a supported folder source.', 'media-bridge-for-bricks' ) );
		$definition = self::definitions()[ $key ];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read only known source taxonomies, including records retained by an inactive plugin.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY t.term_id ASC", $definition['taxonomy'] ) );
		if ( $wpdb->last_error ) return new \WP_Error( 'import_read', __( 'The source folders could not be read. No source data has been changed.', 'media-bridge-for-bricks' ) );
		$roots = array();
		if ( 'wp-media-folder' === $key ) {
			foreach ( $rows as $row ) if ( 'wp-media-folder-root' === $row->slug ) $roots[] = (int) $row->term_id;
		}
		$terms = array();
		foreach ( $rows as $row ) {
			if ( in_array( (int) $row->term_id, $roots, true ) ) continue;
			if ( in_array( (int) $row->parent, $roots, true ) ) $row->parent = 0;
			$terms[ (int) $row->term_id ] = new \WP_Term( $row );
		}
		return $terms;
	}
	public static function position( string $key, int $id ): int {
		$meta = self::definitions()[ $key ]['order'];
		return $meta ? (int) get_term_meta( $id, $meta, true ) : 0;
	}
	public static function preview( string $key ) {
		global $wpdb;
		$terms = self::terms( $key );
		if ( is_wp_error( $terms ) ) return $terms;
		$taxonomy = self::definitions()[ $key ]['taxonomy'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Accurate media-only count; term counts may include stale or non-media relationships.
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft') AND (%s <> 'wp-media-folder' OR t.slug <> 'wp-media-folder-root')", $taxonomy, 'attachment', $key ) );
		if ( $wpdb->last_error ) return new \WP_Error( 'import_read', __( 'The source assignments could not be counted.', 'media-bridge-for-bricks' ) );
		return array( 'plugin_status' => self::plugin_status( $key ), 'label' => self::label( $key ), 'folders' => count( $terms ), 'assignments' => (int) $count );
	}
}
