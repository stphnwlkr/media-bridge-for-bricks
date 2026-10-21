<?php
/**
 * Plugin Name: Media Bridge for Bricks
 * Plugin URI: https://plugins.uplink.press/articles/uplink-media-bridge-for-bricks/
 * Description: Enhanced and WordPress media views with shared folders in the Bricks media manager.
 * Version: 1.1.0
 * Author: Stephen Walker
 * License: GPL-3.0-or-later
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Text Domain: media-bridge-for-bricks
 */
if ( ! defined( 'ABSPATH' ) ) exit;
define( 'UPLINK_MBB_VERSION', '1.1.0' );
define( 'UPLINK_MBB_ASSET_VERSION', '1.1.0-sizes.3' );
define( 'UPLINK_MBB_FILE', __FILE__ );
define( 'UPLINK_MBB_PATH', plugin_dir_path( __FILE__ ) );
define( 'UPLINK_MBB_URL', plugin_dir_url( __FILE__ ) );
function uplink_mbb_get_library_url( int $attachment_id = 0, string $action = '' ): string {
	$attachment_id = absint( $attachment_id );
	$action        = 'upload' === sanitize_key( $action ) ? 'upload' : '';
	$settings      = wp_parse_args(
		get_option( 'uplink_mbb_settings', array() ),
		array(
			'default_media_screen' => 1,
		)
	);
	$enhanced      = ! empty( $settings['default_media_screen'] );

	if ( $enhanced ) {
		$args = array( 'page' => 'bricks-media-folders' );
		if ( $attachment_id ) {
			$args['uplink_mbb_attachment'] = $attachment_id;
		}
		if ( $action ) {
			$args['uplink_mbb_action'] = $action;
		}
		$url = add_query_arg( $args, admin_url( 'upload.php' ) );
	} elseif ( $attachment_id ) {
		$url = add_query_arg(
			array(
				'post'   => $attachment_id,
				'action' => 'edit',
			),
			admin_url( 'post.php' )
		);
	} else {
		$url = admin_url( $action ? 'media-new.php' : 'upload.php' );
	}

	/**
	 * Filter the Media Bridge library URL used by integrations.
	 *
	 * @param string $url           Generated library URL.
	 * @param int    $attachment_id Attachment requested by the caller, or zero.
	 * @param string $action        Normalized interface action, or an empty string.
	 */
	return (string) apply_filters( 'uplink_mbb_media_library_url', $url, $attachment_id, $action );
}


foreach ( array( 'provider-interface', 'taxonomy-provider', 'folder-provider', 'image-metadata', 'exif-map', 'bricks-dynamic-data', 'upload-workspace', 'cimo-optimization', 'folder-import-sources', 'folder-import', 'folder-cleanup', 'happyfiles-import', 'collection-gallery', 'file-replacement', 'image-references', 'image-conversion', 'native-media-library', 'admin', 'plugin' ) as $uplink_mbb_file ) {
	require_once UPLINK_MBB_PATH . 'includes/class-' . $uplink_mbb_file . '.php';
}
register_activation_hook( __FILE__, array( 'UplinkPress\\MediaBridgeForBricks\\Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'UplinkPress\\MediaBridgeForBricks\\Plugin', 'bootstrap' ) );
