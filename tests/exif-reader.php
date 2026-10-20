<?php
/** Camera EXIF regression using a generated JPEG, without bundling a personal photo. */
use UplinkPress\MediaBridgeForBricks\Image_Metadata;
use UplinkPress\MediaBridgeForBricks\Native_Media_Library;
use UplinkPress\MediaBridgeForBricks\Folder_Provider;
use Bricks\Integrations\Dynamic_Data\Providers;
wp_set_current_user( 1 );
function camera_check( $condition, $label ): void {
	if ( ! $condition ) throw new RuntimeException( $label );
	echo "PASS: $label\n";
}
// Little-endian TIFF with real standard camera tags, including the Nikon lens tag.
$data = '';
$offset = 140;
$entry = static function ( int $tag, int $type, int $count, string $value ) use ( &$data, $offset ): string {
	if ( strlen( $value ) > 4 ) { $pointer = pack( 'V', $offset + strlen( $data ) ); $data .= $value; }
	else $pointer = str_pad( $value, 4, "\0" );
	return pack( 'vvV', $tag, $type, $count ) . $pointer;
};
$text = static function ( int $tag, string $value ) use ( $entry ): string { return $entry( $tag, 2, strlen( $value ) + 1, $value . "\0" ); };
$primary = $text( 0x0110, 'NIKON Z 8' ) . $text( 0x013b, 'Camera test author' ) . $text( 0x8298, 'Camera test copyright' ) . $entry( 0x8769, 4, 1, pack( 'V', 62 ) );
$exif = $entry( 0x829a, 5, 1, pack( 'VV', 1, 800 ) ) . $entry( 0x829d, 5, 1, pack( 'VV', 71, 10 ) ) . $entry( 0x8827, 3, 1, pack( 'v', 1600 ) ) . $text( 0x9003, '2026:09:25 15:09:52' ) . $entry( 0x920a, 5, 1, pack( 'VV', 800, 1 ) ) . $text( 0xa434, 'NIKKOR Z 800mm f/6.3 VR S' );
$tiff = 'II' . pack( 'vV', 42, 8 ) . pack( 'v', 4 ) . $primary . pack( 'V', 0 ) . pack( 'v', 6 ) . $exif . pack( 'V', 0 ) . $data;
$payload = "Exif\0\0" . $tiff;
$im = imagecreatetruecolor( 32, 32 );
ob_start(); imagejpeg( $im ); $jpeg = ob_get_clean(); imagedestroy( $im );
$dir = wp_upload_dir()['path']; wp_mkdir_p( $dir );
$file = $dir . '/mbb-camera-test-' . wp_generate_uuid4() . '.jpg';
file_put_contents( $file, substr( $jpeg, 0, 2 ) . "\xff\xe1" . pack( 'n', strlen( $payload ) + 2 ) . $payload . substr( $jpeg, 2 ) );
$id = 0;
try {
	$raw = exif_read_data( $file, 'ANY_TAG', true );
	camera_check( 'NIKKOR Z 800mm f/6.3 VR S' === ( $raw['EXIF']['UndefinedTag:0xA434'] ?? $raw['EXIF']['LensModel'] ?? '' ), 'Fixture contains a real encoded lens tag' );
	$id = wp_insert_attachment( array( 'post_title' => 'MBB camera EXIF test', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ), $file );
	wp_update_attachment_metadata( $id, array( 'width' => 32, 'height' => 32, 'file' => _wp_relative_upload_path( $file ), 'sizes' => array(), 'image_meta' => array( 'camera' => '', 'lens' => '', 'aperture' => '0', 'iso' => '0', 'focal_length' => '0', 'shutter_speed' => '0', 'credit' => '', 'copyright' => '' ) ) );
	$read = Image_Metadata::attachment_exif( $id );
	camera_check( 'NIKON Z 8' === $read['camera_model'], 'Read camera from original JPEG when WordPress camera field is empty' );
	camera_check( 'NIKKOR Z 800mm f/6.3 VR S' === $read['lens'], 'Recognize Nikon lens metadata' );
	camera_check( 1600 === $read['iso'] && 'f/7.1' === $read['aperture_display'] && '1/800 s' === $read['exposure_display'] && '800 mm' === $read['focal_display'], 'Empty WordPress defaults do not mask embedded exposure data' );
	camera_check( 'Camera test author' === $read['credit'] && 'Camera test copyright' === $read['copyright'], 'Empty WordPress defaults do not mask author metadata' );
	$library = new Native_Media_Library( new Folder_Provider(), false );
	$method = new ReflectionMethod( Native_Media_Library::class, 'attachment_data' );
	$labels = array_column( $method->invoke( $library, $id )['exif'], 'value', 'label' );
	camera_check( 'NIKON Z 8' === $labels['Camera'] && 'NIKKOR Z 800mm f/6.3 VR S' === $labels['Lens'], 'Inspector response includes camera and lens after upload' );
	camera_check( 'NIKKOR Z 800mm f/6.3 VR S' === Providers::render_tag( '{mbb_exif_lens}', $id ), 'Bricks dynamic data reads the same original EXIF' );
} finally {
	if ( $id ) wp_delete_attachment( $id, true );
	if ( is_file( $file ) ) unlink( $file );
}
echo "Original JPEG EXIF tests passed.\n";
