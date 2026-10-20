<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;
final class Image_Metadata {
	private static array $cache = array();
	public static function invalidate( int $id ): void {
		unset( self::$cache[ $id . ':0' ], self::$cache[ $id . ':1' ] );
	}
	/** Check access before reading files or using metadata cached for another user. */
	public static function can_read( int $id ): bool {
		$attachment = get_post( $id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) return false;
		$parent = $attachment->post_parent ? get_post( $attachment->post_parent ) : null;
		if ( $parent ) {
			if ( post_password_required( $parent ) && ! current_user_can( 'edit_post', $parent->ID ) ) return false;
			if ( ! is_post_publicly_viewable( $parent ) && ! current_user_can( 'read_post', $parent->ID ) ) return false;
		}
		return is_post_publicly_viewable( $attachment ) || current_user_can( 'read_post', $id );
	}
	public static function attachment_exif( int $attachment_id ): array {
		if ( ! self::can_read( $attachment_id ) ) return array();
		$cache_key = $attachment_id . ':' . ( empty( Plugin::settings()['exif_gps'] ) ? '0' : '1' );
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}
		$metadata   = wp_get_attachment_metadata( $attachment_id );
		$image_meta = is_array( $metadata ) && isset( $metadata['image_meta'] ) && is_array( $metadata['image_meta'] ) ? $metadata['image_meta'] : array();
		$file        = get_attached_file( $attachment_id );
		$original_file = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $attachment_id ) : '';
		$exif_file   = $original_file && is_file( $original_file ) && is_readable( $original_file ) ? $original_file : $file;
		if ( is_array( $metadata ) && 'bulk' === ( $metadata['cimo']['bulk_optimization']['full']['status'] ?? '' ) && $exif_file === $file ) {
			$backup = dirname( $file ) . '/' . pathinfo( $file, PATHINFO_FILENAME ) . '-backup.' . pathinfo( $file, PATHINFO_EXTENSION );
			if ( is_file( $backup ) && is_readable( $backup ) ) $exif_file = $backup;
		}
		$conversion = get_post_meta( $attachment_id, '_uplink_mbb_conversion_backup', true );
		if ( is_array( $conversion ) ) {
			$sources = $conversion['sources'] ?? array();
			$candidate = $sources['original_image']['path'] ?? $sources['full']['path'] ?? '';
			if ( $candidate && ! isset( $sources['original_image'] ) && 'bulk' === ( $conversion['old']['metadata']['cimo']['bulk_optimization']['full']['status'] ?? '' ) ) {
				$cimo_backup = dirname( $candidate ) . '/' . pathinfo( $candidate, PATHINFO_FILENAME ) . '-backup.' . pathinfo( $candidate, PATHINFO_EXTENSION );
				if ( is_file( $cimo_backup ) ) $candidate = $cimo_backup;
			}
			$base = realpath( wp_get_upload_dir()['basedir'] ); $real = $candidate ? realpath( $candidate ) : false;
			if ( $base && $real && str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) && is_readable( $real ) ) $exif_file = $real;
		}
		$width       = is_array( $metadata ) ? absint( $metadata['width'] ?? 0 ) : 0;
		$height      = is_array( $metadata ) ? absint( $metadata['height'] ?? 0 ) : 0;
		$camera      = sanitize_text_field( (string) ( $image_meta['camera'] ?? '' ) );
		$captured    = ! empty( $image_meta['created_timestamp'] ) && is_numeric( $image_meta['created_timestamp'] ) ? (int) $image_meta['created_timestamp'] : 0;
		$raw_exif      = array();
		$exif_mime     = $exif_file && is_file( $exif_file ) && is_readable( $exif_file ) && function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $exif_file ) : false;
		$can_read_exif = in_array( $exif_mime, array( 'image/jpeg', 'image/tiff' ), true );
		if ( $can_read_exif && is_file( $exif_file ) && is_readable( $exif_file ) && function_exists( 'exif_read_data' ) ) {
			$read = @exif_read_data( $exif_file, 'ANY_TAG', true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- EXIF support varies by image format and host.
			$raw_exif = is_array( $read ) ? $read : array();
		}
		$ifd0 = is_array( $raw_exif['IFD0'] ?? null ) ? $raw_exif['IFD0'] : array();
		$exif = is_array( $raw_exif['EXIF'] ?? null ) ? $raw_exif['EXIF'] : array();
		$include_gps = ! empty( Plugin::settings()['exif_gps'] );
		$gps  = $include_gps && is_array( $raw_exif['GPS'] ?? null ) ? $raw_exif['GPS'] : array();
		if ( ! $camera ) {
			$camera = sanitize_text_field( (string) ( $ifd0['Model'] ?? '' ) );
		}
		if ( ! $captured && ! empty( $exif['DateTimeOriginal'] ) ) {
			$date = \DateTimeImmutable::createFromFormat( 'Y:m:d H:i:s', (string) $exif['DateTimeOriginal'], wp_timezone() );
			$captured = $date ? $date->getTimestamp() : 0;
		}
		$aperture = self::fraction_to_float( self::first_value( $image_meta['aperture'] ?? null, $exif['FNumber'] ?? null ) );
		$focal    = self::fraction_to_float( self::first_value( $image_meta['focal_length'] ?? null, $exif['FocalLength'] ?? null ) );
		$exposure = self::fraction_to_float( self::first_value( $image_meta['shutter_speed'] ?? null, $exif['ExposureTime'] ?? null ) );
		$latitude = self::gps_decimal( $gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? '' );
		$longitude = self::gps_decimal( $gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? '' );
		$altitude_m = self::fraction_to_float( $gps['GPSAltitude'] ?? null );
		$file_size = $file && is_file( $file ) ? (int) filesize( $file ) : 0;
		$result = array(
			'attachment_id'   => $attachment_id,
			'file_format'     => $file ? strtoupper( pathinfo( $file, PATHINFO_EXTENSION ) ) : null,
			'file_size'       => $file_size ? size_format( $file_size ) : null,
			'file_size_bytes' => $file_size ?: null,
			'width'           => $width ?: null,
			'height'          => $height ?: null,
			'dimensions'      => $width && $height ? $width . ' x ' . $height . ' px' : null,
			'camera_model'    => $camera ?: null,
			'lens'            => self::text_value( self::first_value( $exif['LensModel'] ?? null, $exif['UndefinedTag:0xA434'] ?? null, $image_meta['lens'] ?? null ) ),
			'iso'             => self::number_value( self::first_value( $image_meta['iso'] ?? null, $exif['ISOSpeedRatings'] ?? null, $exif['PhotographicSensitivity'] ?? null ) ),
			'exposure'        => $exposure,
			'exposure_display' => self::exposure_display( $exposure ),
			'date_taken'      => $captured ? wp_date( 'F j, Y', $captured ) : null,
			'date_taken_iso'  => $captured ? wp_date( DATE_ATOM, $captured ) : null,
			'aperture'        => $aperture,
			'aperture_display' => $aperture ? 'f/' . self::trim_decimal( $aperture ) : null,
			'focal_mm'        => $focal,
			'focal_display'   => $focal ? self::trim_decimal( $focal ) . ' mm' : null,
			'alt_text'        => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: null,
			'caption'         => get_the_excerpt( $attachment_id ) ?: null,
			'credit'          => self::text_value( self::first_value( $image_meta['credit'] ?? null, $ifd0['Artist'] ?? null ) ),
			'copyright'       => self::text_value( self::first_value( $image_meta['copyright'] ?? null, $ifd0['Copyright'] ?? null ) ),
			'gps'             => array(
				'latitude'    => $latitude,
				'longitude'   => $longitude,
				'altitude_ft' => null !== $altitude_m ? self::trim_decimal( $altitude_m * 3.28084 ) . ' ft' : null,
			),
		);
		self::$cache[ $cache_key ] = $result;
		return $result;
	}

	/** WordPress fills missing EXIF values with empty strings and zeroes. */
	private static function first_value( mixed ...$values ): mixed {
		foreach ( $values as $value ) {
			if ( null === $value || '' === $value || ( is_numeric( $value ) && 0.0 === (float) $value ) ) continue;
			return $value;
		}
		return null;
	}

	private static function fraction_to_float( $value ): ?float {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( null === $value || '' === $value ) {
			return null;
		}
		$parts = explode( '/', (string) $value, 2 );
		$number = (float) $parts[0];
		$divisor = isset( $parts[1] ) ? (float) $parts[1] : 1.0;
		return 0.0 === $divisor || ! is_finite( $number / $divisor ) ? null : $number / $divisor;
	}

	private static function text_value( $value ): ?string {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		$value = sanitize_text_field( (string) $value );
		return '' === $value ? null : $value;
	}

	private static function number_value( $value ): int|float|null {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$number = (float) $value;
		return floor( $number ) === $number ? (int) $number : $number;
	}

	private static function gps_decimal( $coordinates, string $hemisphere ): ?float {
		if ( ! is_array( $coordinates ) || 2 > count( $coordinates ) ) {
			return null;
		}
		$degrees = self::fraction_to_float( $coordinates[0] ) ?? 0.0;
		$minutes = self::fraction_to_float( $coordinates[1] ) ?? 0.0;
		$seconds = self::fraction_to_float( $coordinates[2] ?? null ) ?? 0.0;
		$decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );
		return in_array( strtoupper( $hemisphere ), array( 'S', 'W' ), true ) ? -$decimal : $decimal;
	}

	private static function exposure_display( ?float $seconds ): ?string {
		if ( null === $seconds || 0 >= $seconds ) {
			return null;
		}
		return 1 > $seconds ? '1/' . max( 1, (int) round( 1 / $seconds ) ) . ' s' : self::trim_decimal( $seconds ) . ' s';
	}

	private static function trim_decimal( float $number ): string {
		return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
	}
}
