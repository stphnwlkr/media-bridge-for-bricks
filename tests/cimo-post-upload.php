<?php
use UplinkPress\MediaBridgeForBricks\Cimo_Optimization;
use UplinkPress\MediaBridgeForBricks\Plugin;
function mbb_cimo_check( $ok, string $message ): void { if ( ! $ok ) throw new RuntimeException( $message ); echo "PASS: $message\n"; }
wp_set_current_user( 1 );
$saved = get_option( 'uplink_mbb_settings' );
$id = 0;
$keep = '1' === getenv( 'MBB_KEEP_CIMO_FIXTURE' );
try {
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'cimo_integration' => 1 ) ) );
	mbb_cimo_check( Cimo_Optimization::available(), 'Licensed Cimo Pro is available' );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$im = imagecreatetruecolor( 720, 480 );
	for ( $y = 0; $y < 480; ++$y ) for ( $x = 0; $x < 720; ++$x ) {
		$color = imagecolorallocate( $im, ( $x + $y ) % 256, ( $x * 3 + $y ) % 256, ( $x + $y * 2 ) % 256 );
		imagesetpixel( $im, $x, $y, $color );
	}
	ob_start(); imagejpeg( $im, null, 100 ); $bytes = ob_get_clean(); imagedestroy( $im );
	$upload = wp_upload_bits( 'mbb-cimo-post-' . wp_generate_uuid4() . '.jpg', null, $bytes );
	$id = wp_insert_attachment( array( 'post_title' => 'Media Bridge Cimo optimization test', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ), $upload['file'] );
	$metadata = wp_generate_attachment_metadata( $id, $upload['file'] );
	unset( $metadata['cimo'] );
	update_post_meta( $id, '_wp_attachment_metadata', $metadata );
	$plan = Cimo_Optimization::plan( $id );
	mbb_cimo_check( ! is_wp_error( $plan ) && count( $plan ) > 1 && 'full' === $plan[0]['size'], 'Plan includes full image and generated image sizes' );
	mbb_cimo_check( count( array_unique( array_column( $plan, 'url' ) ) ) === count( $plan ), 'Duplicate physical files are not processed twice' );
	$metadata['cimo']['bulk_optimization']['full'] = array( 'status' => 'bulk' );
	update_post_meta( $id, '_wp_attachment_metadata', $metadata );
	mbb_cimo_check( array() === Cimo_Optimization::plan( $id ), 'Existing bulk optimization data disables Optimize' );
	unset( $metadata['cimo'] );
	$metadata['cimo']['optimized_during_upload'] = true;
	update_post_meta( $id, '_wp_attachment_metadata', $metadata );
	mbb_cimo_check( array() === Cimo_Optimization::plan( $id ), 'Existing pre-upload optimization data disables Optimize' );
	unset( $metadata['cimo'] );
	update_post_meta( $id, '_wp_attachment_metadata', $metadata );
	wp_set_current_user( 0 );
	mbb_cimo_check( is_wp_error( Cimo_Optimization::plan( $id ) ), 'Anonymous request cannot obtain an optimization plan' );
	wp_set_current_user( 1 );
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'cimo_integration' => 0 ) ) );
	mbb_cimo_check( ! Cimo_Optimization::available() && is_wp_error( Cimo_Optimization::plan( $id ) ), 'Disabling CIMO integration disables post-upload actions' );
	Plugin::update_settings( array_merge( Plugin::settings(), array( 'cimo_integration' => 1 ) ) );
	mbb_cimo_check( is_wp_error( Cimo_Optimization::plan( 0 ) ), 'Invalid attachment is rejected' );
	if ( $keep ) {
		file_put_contents( '/tmp/mbb-cimo/fixture.json', wp_json_encode( array( 'id' => $id, 'file' => $upload['file'], 'plan' => $plan, 'metadata' => $metadata ) ) );
		echo 'CIMO_FIXTURE_ID=' . $id . "\n";
	}
} finally {
	update_option( 'uplink_mbb_settings', $saved );
	if ( $id && ! $keep ) wp_delete_attachment( $id, true );
}
echo "Post-upload Cimo plan tests passed.\n";
