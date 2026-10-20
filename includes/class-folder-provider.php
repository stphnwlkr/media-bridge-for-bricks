<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Shared taxonomy storage for the WordPress views and Bricks provider. */
class Folder_Provider extends Taxonomy_Provider {
	public function id(): string { return 'media-bridge'; }
	public function label(): string { return __( 'Media Bridge folders', 'media-bridge-for-bricks' ); }
	public function taxonomy(): string { return 'mbb_folder'; }
	public function max_depth(): ?int { return null; }
	public function order_meta_key(): string { return '_uplink_mbb_position'; }
	public function register(): void {
		register_taxonomy( $this->taxonomy(), 'attachment', array(
			'label' => __( 'Media folders', 'media-bridge-for-bricks' ),
			'hierarchical' => true, 'public' => false, 'show_ui' => false,
			'show_in_rest' => false, 'query_var' => false, 'rewrite' => false,
			// Media folders count assignments, including unattached media and unpublished parents.
			'update_count_callback' => '_update_generic_term_count',
			'capabilities' => array( 'manage_terms' => 'manage_categories', 'edit_terms' => 'manage_categories', 'delete_terms' => 'manage_categories', 'assign_terms' => 'upload_files' ),
		) );
	}
	/** Refresh counters created with WordPress's publication-based post counter. */
	public function maybe_recount(): void {
		if ( ! current_user_can( 'manage_options' ) || ! taxonomy_exists( $this->taxonomy() ) || '1' === get_option( 'uplink_mbb_folder_count_version' ) ) return;
		$ids = get_terms( array( 'taxonomy' => $this->taxonomy(), 'hide_empty' => false, 'fields' => 'tt_ids', 'update_term_meta_cache' => false ) );
		if ( is_wp_error( $ids ) ) return;
		foreach ( array_chunk( $ids, 100 ) as $batch ) wp_update_term_count_now( $batch, $this->taxonomy() );
		update_option( 'uplink_mbb_folder_count_version', '1', false );
	}
}
