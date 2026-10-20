<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Public Bricks hooks for scalar image metadata in the picker and rendered content. */
final class Bricks_Dynamic_Data {
	public static function fields(): array {
		return array(
			'camera' => array( 'Camera', 'camera_model' ), 'lens' => array( 'Lens', 'lens' ),
			'aperture' => array( 'Aperture', 'aperture_display' ), 'shutter_speed' => array( 'Shutter speed', 'exposure_display' ),
			'focal_length' => array( 'Focal length', 'focal_display' ), 'iso' => array( 'ISO', 'iso' ),
			'date_taken' => array( 'Date taken', 'date_taken' ), 'date_taken_iso' => array( 'Date taken, ISO 8601', 'date_taken_iso' ),
			'credit' => array( 'Credit', 'credit' ), 'copyright' => array( 'Copyright', 'copyright' ),
			'width' => array( 'Width', 'width' ), 'height' => array( 'Height', 'height' ),
			'dimensions' => array( 'Dimensions', 'dimensions' ), 'format' => array( 'File format', 'file_format' ),
			'file_size' => array( 'File size', 'file_size' ), 'file_size_bytes' => array( 'File size in bytes', 'file_size_bytes' ),
			'alt_text' => array( 'Alternative text', 'alt_text' ), 'caption' => array( 'Caption', 'caption' ),
			'latitude' => array( 'GPS latitude', 'latitude' ), 'longitude' => array( 'GPS longitude', 'longitude' ),
			'altitude' => array( 'GPS altitude', 'altitude_ft' ),
		);
	}
	public function __construct() {
		add_filter( 'bricks/dynamic_tags_list', array( $this, 'tags' ), 20 );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 20, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 20, 3 );
		add_filter( 'bricks/frontend/render_data', array( $this, 'render_content' ), 20, 2 );
		foreach ( array( 'add_attachment', 'edit_attachment', 'delete_attachment' ) as $hook ) add_action( $hook, array( Image_Metadata::class, 'invalidate' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( $this, 'invalidate_meta' ), 10, 4 );
	}
	public function invalidate_meta( $meta_id, $object_id, $meta_key, $value ): void {
		if ( in_array( $meta_key, array( '_wp_attachment_metadata', '_wp_attached_file', '_wp_attachment_image_alt' ), true ) ) Image_Metadata::invalidate( (int) $object_id );
	}
	public function tags( $tags ): array {
		$tags = is_array( $tags ) ? $tags : array();
		if ( empty( Plugin::settings()['exif_dynamic_data'] ) ) return $tags;
		foreach ( self::fields() as $name => $field ) {
			if ( in_array( $name, array( 'latitude', 'longitude', 'altitude' ), true ) && empty( Plugin::settings()['exif_gps'] ) ) continue;
			$tags[] = array( 'name' => '{mbb_exif_' . $name . '}', 'label' => $field[0], 'group' => 'Media Bridge · EXIF', 'provider' => 'media_bridge' );
		}
		return $tags;
	}
	public function render_tag( $value, $post, $context = 'text' ) {
		if ( ! is_string( $value ) ) return $value;
		$raw = trim( $value, "{} \t\n\r\0\x0B" );
		if ( ! preg_match( '/^mbb_exif_([a-z_]+)(?::([1-9][0-9]*))?$/D', $raw, $match ) ) return $value;
		$fields = self::fields();
		if ( ! isset( $fields[ $match[1] ] ) ) return $value;
		if ( empty( Plugin::settings()['exif_dynamic_data'] ) ) return '';
		$is_gps = in_array( $match[1], array( 'latitude', 'longitude', 'altitude' ), true );
		if ( $is_gps && empty( Plugin::settings()['exif_gps'] ) ) return '';
		$id = isset( $match[2] ) ? absint( $match[2] ) : 0;
		if ( ! $id && class_exists( '\\Bricks\\Query' ) && \Bricks\Query::is_looping() ) {
			$loop = \Bricks\Query::get_loop_object();
			if ( $loop instanceof \WP_Post && 'attachment' === $loop->post_type ) $id = $loop->ID;
			elseif ( is_array( $loop ) && ! empty( $loop['url'] ) && ! empty( $loop['id'] ) && is_numeric( $loop['id'] ) ) $id = absint( $loop['id'] );
		}
		if ( ! $id && $post instanceof \WP_Post ) $id = 'attachment' === $post->post_type ? $post->ID : get_post_thumbnail_id( $post->ID );
		if ( ! $id || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) return '';
		$attachment = get_post( $id );
		if ( ! $attachment || ! Image_Metadata::can_read( $id ) ) return '';
		$data = Image_Metadata::attachment_exif( $id );
		$result = $is_gps ? ( $data['gps'][ $fields[ $match[1] ][1] ] ?? '' ) : ( $data[ $fields[ $match[1] ][1] ] ?? '' );
		return is_scalar( $result ) ? esc_html( (string) $result ) : '';
	}
	public function render_content( $content, $post, $context = 'text' ) {
		if ( ! is_string( $content ) || ! str_contains( $content, '{mbb_exif_' ) ) return $content;
		return preg_replace_callback( '/\{mbb_exif_[a-z_]+(?::[1-9][0-9]*)?\}/', fn( $match ) => $this->render_tag( $match[0], $post, $context ), $content );
	}
}
