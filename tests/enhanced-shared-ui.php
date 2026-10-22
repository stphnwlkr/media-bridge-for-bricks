<?php
/** Disposable WordPress integration checks for shared Enhanced Manager assets and settings. */
use UplinkPress\MediaBridgeForBricks\Plugin;
use UplinkPress\MediaBridgeForBricks\Admin;
use UplinkPress\MediaBridgeForBricks\Native_Media_Library;
use UplinkPress\MediaBridgeForBricks\Folder_Provider;
wp_set_current_user( 1 );
$saved = get_option( 'uplink_mbb_settings' );
function shared_ui_check( bool $ok, string $label ): void { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS: $label\n"; }
try {
 Plugin::update_settings( array_merge( Plugin::settings(), array( 'default_media_screen' => 0, 'exif_gps' => 1, 'exif_map' => 1 ) ) );
 $library = new Native_Media_Library( new Folder_Provider(), false );
 $library->assets( 'media_page_bricks-media-folders' );
 shared_ui_check( wp_script_is( 'uplink-mbb-exif-map', 'enqueued' ), 'Enhanced loads GPS maps while WordPress remains the default library' );
 shared_ui_check( wp_style_is( 'uplink-mbb-enhanced-components', 'enqueued' ) && wp_style_is( 'uplink-mbb-workspace-ui', 'enqueued' ), 'Shared component and workspace styles are enqueued' );
 $admin = new Admin();
 $_GET['tab'] = 'settings'; ob_start(); $admin->render(); $general = ob_get_clean();
 shared_ui_check( ! str_contains( $general, 'id="uplink-mbb-compression-settings"' ), 'General settings does not repeat Compression controls' );
 $_GET['tab'] = 'compression'; ob_start(); $admin->render(); $compression = ob_get_clean();
 shared_ui_check( str_contains( $compression, 'compression_excluded_sizes' ) && str_contains( $compression, 'restoration_roles' ), 'Compression has size exclusions and independent restoration roles' );
 $before = Plugin::settings(); $after = $admin->sanitize( array( 'compression_excluded_sizes' => array( 'thumbnail' ) ) );
 unset( $before['compression_excluded_sizes'], $after['compression_excluded_sizes'] );
 shared_ui_check( $before === $after, 'Saving compression exclusions preserves every other setting' );
 ob_start(); $library->render(); $html = ob_get_clean();
 shared_ui_check( substr_count( $html, '<wa-button-group' ) === substr_count( $html, '</wa-button-group>' ), 'All rendered button groups are balanced' );
 shared_ui_check( ! str_contains( $html, 'open-loop-generator' ) && ! str_contains( $html, 'etch-collections' ), 'Bricks UI does not expose Etch-specific controls' );
 shared_ui_check( str_contains( $html, 'wa-popover' ) && str_contains( $html, file_get_contents( UPLINK_MBB_PATH . 'assets/vendor/lucide/trash.svg' ) ), 'Selection info popup and open-source icons are rendered' );
 echo "Enhanced shared UI integration tests passed.\n";
} finally { update_option( 'uplink_mbb_settings', $saved ); unset( $_GET['tab'] ); }
