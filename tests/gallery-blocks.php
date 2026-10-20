<?php
use UplinkPress\MediaBridgeForBricks\Collection_Gallery;
use UplinkPress\MediaBridgeForBricks\Folder_Provider;
function mbb_gallery_check( $ok, string $label ): void { if ( ! $ok ) throw new RuntimeException( $label ); echo "PASS: $label\n"; }
wp_set_current_user( 1 );
$ids = array(); $terms = array(); $post = 0;
$keep = '1' === getenv( 'MBB_KEEP_GALLERY_FIXTURE' );
try {
	$type = WP_Block_Type_Registry::get_instance()->get_registered( 'uplinkpress/bricks-collection-gallery' );
	mbb_gallery_check( $type && $type->is_dynamic(), 'Bricks gallery is registered as a dynamic Gutenberg block' );
	mbb_gallery_check( shortcode_exists( 'uplink_mbb_gallery' ), 'Namespaced Media Bridge shortcode is registered' );
	mbb_gallery_check( shortcode_exists( 'bricks_collection_gallery' ), 'Earlier beta shortcode remains available' );
	mbb_gallery_check( ! shortcode_exists( 'etch_collection_gallery' ), 'No Etch shortcode is registered by this plugin' );
	$tag = wp_generate_uuid4();
	$parent = wp_insert_term( 'Gallery test ' . $tag, 'mbb_folder' )['term_id']; $terms[] = $parent;
	$child = wp_insert_term( 'Gallery child ' . $tag, 'mbb_folder', array( 'parent' => $parent ) )['term_id']; $terms[] = $child;
	require_once ABSPATH . 'wp-admin/includes/image.php';
	for ( $i = 0; $i < 2; ++$i ) {
		$source = get_attached_file( 753 );
		$upload = wp_upload_bits( 'mbb-gallery-' . $tag . '-' . $i . '.jpg', null, file_get_contents( $source ) );
		$id = wp_insert_attachment( array( 'post_title' => 'Gallery bird ' . ( $i + 1 ), 'post_excerpt' => 'Gallery test caption', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ), $upload['file'] );
		$ids[] = $id;
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		wp_set_object_terms( $id, array( 0 === $i ? $parent : $child ), 'mbb_folder' );
		if ( $keep ) file_put_contents( '/tmp/mbb-cimo/gallery-fixture.json', wp_json_encode( array( 'post' => 0, 'terms' => $terms, 'ids' => $ids ) ) );
	}
	$attrs = array( 'collectionId' => $parent, 'includeChildren' => true, 'sortOrder' => 'custom', 'showTitle' => true, 'showCaptions' => true, 'lightboxMode' => 'custom', 'lightboxShowExif' => true, 'lightboxExifFields' => array( 'camera_model' ), 'columns' => 2 );
	update_term_meta( $parent, '_uplink_mbb_media_order', array_reverse( $ids ) );
	$render = static fn( $a ) => render_block( array( 'blockName' => 'uplinkpress/bricks-collection-gallery', 'attrs' => $a, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	$html = $render( $attrs );
	mbb_gallery_check( substr_count( $html, 'class="uplink-mbb-custom-lightbox-trigger"' ) === 2, 'Gallery reads parent and child folders' );
	mbb_gallery_check( strpos( $html, 'wp-image-' . $ids[1] ) < strpos( $html, 'wp-image-' . $ids[0] ), 'Gallery follows the enhanced manager custom order' );
	mbb_gallery_check( str_contains( $html, 'Gallery test caption' ), 'Gallery renders attachment captions' );
	mbb_gallery_check( ! str_contains( $html, 'uplink-mbe' ) && ! str_contains( $html, 'etch' ), 'Gallery output has independent Bricks markup' );
	$without_children = $render( array_merge( $attrs, array( 'includeChildren' => false ) ) );
	mbb_gallery_check( ! str_contains( $without_children, 'wp-image-' . $ids[1] ), 'Disabling child folders excludes their images' );
	$slider_attrs = array_merge( $attrs, array( 'layout' => 'slider', 'slidesPerView' => 1, 'columns' => 1, 'sliderShowSlideshow' => true, 'aspectRatio' => '16/9' ) );
	$slider = $render( $slider_attrs );
	mbb_gallery_check( str_contains( $slider, 'is-layout-slider' ) && str_contains( $slider, 'data-slider' ), 'Slideshow renders slider controls and settings' );
	mbb_gallery_check( wp_script_is( 'uplink-mbb-gallery-slider', 'enqueued' ), 'Slideshow loads its own slider script' );
	$shortcode = do_shortcode( '[bricks_collection_gallery collection="' . get_term( $parent )->slug . '" include_children="true"]' );
	$canonical_shortcode = do_shortcode( '[uplink_mbb_gallery collection="' . get_term( $parent )->slug . '" include_children="true"]' );
	mbb_gallery_check( substr_count( $canonical_shortcode, 'wp-block-image' ) === substr_count( $shortcode, 'wp-block-image' ), 'Canonical and legacy shortcodes render the same images' );
	mbb_gallery_check( str_contains( $shortcode, 'wp-image-' . $ids[0] ), 'Shortcode resolves the shared folder slug' );
	wp_set_current_user( 0 );
	mbb_gallery_check( str_contains( $render( $attrs ), 'wp-image-' . $ids[0] ), 'Public frontend renders the gallery' );
	wp_set_current_user( 1 );
	if ( $keep ) {
		$content = '<!-- wp:heading --><h2 class="wp-block-heading">Media Bridge Gallery</h2><!-- /wp:heading -->' . serialize_block( array( 'blockName' => 'uplinkpress/bricks-collection-gallery', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerContent' => array() ) ) . '<!-- wp:heading --><h2 class="wp-block-heading">Media Bridge Slideshow</h2><!-- /wp:heading -->' . serialize_block( array( 'blockName' => 'uplinkpress/bricks-collection-gallery', 'attrs' => $slider_attrs, 'innerBlocks' => array(), 'innerContent' => array() ) );
		$post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Media Bridge block verification', 'post_content' => $content ) );
		file_put_contents( '/tmp/mbb-cimo/gallery-fixture.json', wp_json_encode( array( 'post' => $post, 'terms' => $terms, 'ids' => $ids ) ) );
		echo 'GALLERY_PREVIEW_POST=' . $post . "\n";
	}
} finally {
	if ( ! $keep ) {
		foreach ( $ids as $id ) wp_delete_attachment( $id, true );
		foreach ( array_reverse( $terms ) as $term ) wp_delete_term( $term, 'mbb_folder' );
		if ( $post ) wp_delete_post( $post, true );
	}
}
echo "Gallery and slideshow integration tests passed.\n";
