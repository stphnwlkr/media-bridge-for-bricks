<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Adapter to the installed, licensed Cimo Premium optimizer and size-upload API. */
final class Cimo_Optimization {
	public static function available(): bool {
		return Upload_Workspace::cimo_available() && defined( 'CIMO_BUILD' ) && 'premium' === CIMO_BUILD
			&& class_exists( '\Cimo_Bulk_Optimization' ) && current_user_can( 'upload_files' )
			&& current_user_can( 'edit_posts' ) && current_user_can( 'edit_others_posts' );
	}
	public static function plan( int $id ) {
		if ( ! self::available() ) return new \WP_Error( 'cimo_unavailable', __( 'Post-upload optimization requires active Cimo Pro and permission to optimize media.', 'media-bridge-for-bricks' ) );
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) return new \WP_Error( 'cimo_forbidden', __( 'You cannot optimize this attachment.', 'media-bridge-for-bricks' ) );
		$mime = get_post_mime_type( $id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) return array();
		$meta = wp_get_attachment_metadata( $id );
		// Existing Cimo records, including upload optimization and skips, hide Optimize.
		if ( ! empty( $meta['cimo'] ) ) return array();
		$file = get_attached_file( $id );
		$base = realpath( wp_get_upload_dir()['basedir'] );
		if ( ! is_array( $meta ) || ! $file || ! $base ) return array();
		$generated = is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : array();
		$sizes = array( 'full' => array( 'file' => wp_basename( $file ), 'mime-type' => $mime ) ) + $generated;
		$done = array();
		$plan = array();
		foreach ( $sizes as $key => $size ) {
			if ( ! is_array( $size ) ) continue;
			$name = $size['file'] ?? '';
			$type = $size['mime-type'] ?? $mime;
			if ( ! $name || isset( $done[ $name ] ) || wp_basename( $name ) !== $name || ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) continue;
			$path = realpath( dirname( $file ) . '/' . $name );
			if ( ! $path || ! str_starts_with( $path, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) || wp_get_image_mime( $path ) !== $type ) continue;
			$url = 'full' === $key ? wp_get_attachment_url( $id ) : trailingslashit( dirname( wp_get_attachment_url( $id ) ) ) . rawurlencode( $name );
			$plan[] = array( 'size' => $key, 'filename' => $name, 'mime' => $type, 'url' => esc_url_raw( $url ) );
			$done[ $name ] = true;
		}
		return $plan;
	}
	public static function ajax_plan(): void {
		check_ajax_referer( 'uplink_mbb_cimo_optimize', 'nonce' );
		$id = absint( $_POST['attachment_id'] ?? 0 );
		$plan = self::plan( $id );
		if ( is_wp_error( $plan ) ) wp_send_json_error( array( 'message' => $plan->get_error_message() ), 403 );
		wp_send_json_success( $plan );
	}
	public static function assets(): void {
		if ( ! self::available() ) return;
		if ( class_exists( '\Cimo_Script_Loader' ) ) \Cimo_Script_Loader::enqueue_cimo_assets();
		wp_enqueue_script( 'uplink-mbb-png-compression', UPLINK_MBB_URL . 'assets/vendor/browser-image-compression/browser-image-compression.js', array(), '2.0.2', true );
		wp_enqueue_script( 'uplink-mbb-cimo-optimize', UPLINK_MBB_URL . 'assets/cimo-optimize.js', array( 'wp-hooks', 'cimo-script', 'uplink-mbb-png-compression' ), UPLINK_MBB_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbb-cimo-optimize', 'uplinkMbbCimoConfig', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'uplink_mbb_cimo_optimize' ),
			'restUrl' => rest_url( 'cimo/v1/' ), 'restNonce' => wp_create_nonce( 'wp_rest' ),
			'strings' => array(
				'unavailable' => __( 'Cimo Pro optimization is unavailable. Reload this page after activating Cimo Pro.', 'media-bridge-for-bricks' ),
				'failed' => __( 'Optimization failed. Review the image in Cimo\'s Bulk Optimization screen.', 'media-bridge-for-bricks' ),
				'optimize' => __( 'Optimize', 'media-bridge-for-bricks' ),
				/* translators: %1$d: Current file index. %2$d: Total files. */
				'progress' => __( 'Optimizing %1$d of %2$d…', 'media-bridge-for-bricks' ),
				/* translators: %1$d: Optimized file count. %2$d: Unchanged file count. %3$d: Failed file count. */
				'complete' => __( '%1$d sizes optimized, %2$d unchanged, %3$d failed.', 'media-bridge-for-bricks' ),
				'help' => __( 'Optimizes the full image and remaining generated sizes with Cimo Pro. File formats and dimensions stay the same. Cimo keeps backups for its Restore action. Keep this page open until it finishes.', 'media-bridge-for-bricks' ),
			),
		) );
	}
}
