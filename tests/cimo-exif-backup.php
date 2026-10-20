<?php
use UplinkPress\MediaBridgeForBricks\Image_Metadata;
wp_set_current_user( 1 );
$id = 0; $backup = '';
try {
	$original = wp_get_original_image_path( 753 );
	$upload = wp_upload_bits( 'mbb-cimo-exif-' . wp_generate_uuid4() . '.jpg', null, file_get_contents( $original ) );
	$file = $upload['file'];
	$backup = dirname( $file ) . '/' . pathinfo( $file, PATHINFO_FILENAME ) . '-backup.jpg';
	copy( $file, $backup );
	$im = imagecreatefromjpeg( $file ); imagejpeg( $im, $file, 75 ); imagedestroy( $im );
	$id = wp_insert_attachment( array( 'post_title' => 'Cimo EXIF backup test', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ), $file );
	$dims = wp_getimagesize( $file );
	wp_update_attachment_metadata( $id, array( 'file' => _wp_relative_upload_path( $file ), 'width' => $dims[0], 'height' => $dims[1], 'image_meta' => array( 'camera' => '' ), 'cimo' => array( 'bulk_optimization' => array( 'full' => array( 'status' => 'bulk' ) ) ) ) );
	$data = Image_Metadata::attachment_exif( $id );
	if ( 'NIKON Z 8' !== $data['camera_model'] || ! str_contains( $data['lens'] ?? '', 'NIKKOR' ) ) throw new RuntimeException( 'Original EXIF was lost after Cimo compression' );
	echo "PASS: Cimo backup preserves camera and lens in the inspector after compression strips embedded EXIF\n";
} finally {
	if ( $id ) wp_delete_attachment( $id, true );
	if ( $backup && is_file( $backup ) ) wp_delete_file( $backup );
}
