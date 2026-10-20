<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

/** GPS map data is omitted entirely unless the administrator enables GPS metadata. */
final class Exif_Map {
	public static function coordinates( mixed $latitude, mixed $longitude ): ?array {
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) return null;
		$latitude = (float) $latitude;
		$longitude = (float) $longitude;
		if ( ! is_finite( $latitude ) || ! is_finite( $longitude ) || abs( $latitude ) > 90 || abs( $longitude ) > 180 ) return null;
		return array( 'latitude' => $latitude, 'longitude' => $longitude );
	}
	public static function location( int $attachment_id ): ?array {
		if ( empty( Plugin::settings()['exif_gps'] ) || empty( Plugin::settings()['exif_map'] ) ) return null;
		$gps = Image_Metadata::attachment_exif( $attachment_id )['gps'] ?? array();
		return self::coordinates( $gps['latitude'] ?? null, $gps['longitude'] ?? null );
	}
	public static function assets(): void {
		if ( empty( Plugin::settings()['exif_gps'] ) || empty( Plugin::settings()['exif_map'] ) || ! current_user_can( 'upload_files' ) ) return;
		$handle = 'uplink-mbb-leaflet';
		wp_enqueue_script( $handle, UPLINK_MBB_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		wp_enqueue_style( $handle, UPLINK_MBB_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_enqueue_style( 'uplink-mbb-exif-map', UPLINK_MBB_URL . 'assets/exif-map.css', array( 'uplink-mbb-leaflet' ), UPLINK_MBB_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbb-exif-map', UPLINK_MBB_URL . 'assets/exif-map.js', array( $handle ), UPLINK_MBB_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbb-exif-map', 'uplinkMbbExifMapStrings', array(
			'location' => __( 'Photo location', 'media-bridge-for-bricks' ),
			'satellite' => __( 'Satellite', 'media-bridge-for-bricks' ),
			'streets' => __( 'OpenStreetMap', 'media-bridge-for-bricks' ),
			'open' => __( 'Open in OpenStreetMap', 'media-bridge-for-bricks' ),
			'error' => __( 'Map imagery could not be loaded. You can still open the location in OpenStreetMap.', 'media-bridge-for-bricks' ),
		) );
	}
}
