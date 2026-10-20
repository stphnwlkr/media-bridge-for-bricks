<?php
/** Run with studio wp eval-file tests/integration.php on a test site. Removes its own fixtures. */
use UplinkPress\MediaBridgeForBricks\Bricks_Folder_Provider;
use UplinkPress\MediaBridgeForBricks\Folder_Provider;
use UplinkPress\MediaBridgeForBricks\Native_Media_Library;
use UplinkPress\MediaBridgeForBricks\Plugin;

wp_set_current_user( 1 );
if ( ! defined( 'DOING_AJAX' ) ) define( 'DOING_AJAX', true );
class MBB_Test_Response extends RuntimeException {}
$die = static fn() => static function () { throw new MBB_Test_Response(); };
add_filter( 'wp_die_ajax_handler', $die );
function mbb_assert( $condition, string $message ): void {
	if ( ! $condition ) throw new RuntimeException( $message );
	echo "PASS: $message\n";
}
function mbb_ajax( callable $callback, array $data ): array {
	$_POST = $data;
	$_REQUEST = array( 'nonce' => wp_create_nonce( 'uplink_mbb_native_media' ) );
	ob_start();
	try { $callback(); } catch ( MBB_Test_Response $e ) {}
	return json_decode( ob_get_clean(), true ) ?? array();
}
$p = new Bricks_Folder_Provider();
$library = new Native_Media_Library( new Folder_Provider(), false );
$folders = array();
$attachments = array();
$fixture_post = 0;
$saved_settings = get_option( 'uplink_mbb_settings' );
$tag = 'MBB test ' . wp_generate_password( 8, false );
try {
	mbb_assert( Plugin::bricks_is_active(), 'Bricks 2.4+ dependency detected' );
	mbb_assert( \Bricks\Media_Folder_Providers::get_active() instanceof Bricks_Folder_Provider, 'Bricks selects the registered provider without HappyFiles' );
	$parent = 0;
	for ( $i = 0; $i < 8; $i++ ) {
		$r = $p->manage_folder( array( 'operation' => 'create', 'name' => "$tag level $i", 'parent' => $parent ) );
		mbb_assert( ! is_wp_error( $r ), "Bricks creates folder at level " . ( $i + 1 ) );
		$term = get_term_by( 'name', "$tag level $i", 'mbb_folder' );
		$folders[] = $parent = (int) $term->term_id;
	}
	$r = mbb_ajax( array( $library, 'ajax_save_collection' ), array( 'name' => "$tag native", 'parent' => $parent ) );
	mbb_assert( ! empty( $r['success'] ), 'Enhanced view creates a ninth folder level' );
	$folders[] = (int) $r['data']['term_id'];
	$deep = end( $folders );
	mbb_assert( in_array( $deep, array_column( \Bricks\Media_Folder_Providers::get_context()['folders'], 'id' ), true ), 'Folder from enhanced view is immediately visible to Bricks' );
	mbb_assert( is_wp_error( $p->manage_folder( array( 'operation' => 'move', 'folderId' => $folders[0], 'parent' => $deep ) ) ), 'Reject cyclic folder move' );
	mbb_assert( is_wp_error( $p->manage_folder( array( 'operation' => 'create', 'name' => "$tag native", 'parent' => $parent ) ) ), 'Reject duplicate sibling name' );
	mbb_assert( is_wp_error( $p->manage_folder( array( 'operation' => array() ) ) ), 'Reject malformed folder operation' );
	mbb_assert( ! is_wp_error( $p->manage_folder( array( 'operation' => 'rename', 'folderId' => $deep, 'name' => "$tag renamed" ) ) ), 'Bricks renames shared folder' );
	mbb_assert( get_term( $deep, 'mbb_folder' )->name === "$tag renamed", 'WordPress reads the Bricks rename' );
	foreach ( array( 'Assigned', 'Unfiled' ) as $title ) $attachments[] = wp_insert_attachment( array( 'post_title' => "$tag $title", 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
	mbb_assert( true === $p->assign_attachment( $attachments[0], $deep ), 'Bricks assigns an attachment' );
	mbb_assert( array( $deep ) === array_map( 'intval', wp_get_object_terms( $attachments[0], 'mbb_folder', array( 'fields' => 'ids' ) ) ), 'WordPress reads the same relationship' );
	$fixture_post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "$tag reference" ) );
	update_post_meta( $fixture_post, '_bricks_page_content_2', array( array( 'id' => 'mbbtest', 'name' => 'image', 'settings' => array( 'image' => array( 'id' => $attachments[0] ) ) ) ) );
	$usage_method = new ReflectionMethod( Native_Media_Library::class, 'attachment_is_used' );
	mbb_assert( true === $usage_method->invoke( $library, $attachments[0] ), 'Usage check reads Bricks image metadata' );
	mbb_assert( false === $usage_method->invoke( $library, $attachments[1] ), 'Usage check does not match unrelated media' );
	$base = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids', 'post__in' => $attachments );
	mbb_assert( array( $attachments[0] ) === array_map( 'intval', get_posts( $p->apply_query( $base, $folders[0] ) ) ), 'Bricks parent query includes descendants' );
	mbb_assert( array( $attachments[1] ) === array_map( 'intval', get_posts( $p->apply_query( $base, 'unfiled' ) ) ), 'Unfiled query excludes assigned files' );
	$or = $base;
	$or['tax_query'] = array( 'relation' => 'OR', array( 'taxonomy' => 'mbb_folder', 'terms' => $folders[0] ) );
	$q = $p->apply_query( $or, $deep );
	mbb_assert( 'AND' === $q['tax_query']['relation'], 'Folder constraint preserves existing query restrictions' );
	mbb_assert( array() === get_posts( $p->apply_query( $base, 999999999 ) ), 'Missing folder never broadens media query' );
	$r = mbb_ajax( array( $library, 'ajax_assign_media' ), array( 'media_ids' => wp_json_encode( array( $attachments[1] ) ), 'collection' => $deep, 'mode' => 'add' ) );
	mbb_assert( ! empty( $r['success'] ) && in_array( $deep, array_map( 'intval', $p->get_item_folder_ids( $attachments[1] ) ), true ), 'Enhanced assignment is visible to Bricks' );
	$_REQUEST = array( 'bricks-is-builder' => '1', 'bricksMediaFolderProvider' => 'media-bridge', 'bricksMediaFolderId' => $deep );
	mbb_assert( true === \Bricks\Media_Browser_Upload::assign_requested_folder( $attachments[0] ), 'Bricks upload queue uses the shared provider' );
	$r = mbb_ajax( static function () {
		$_REQUEST['nonce'] = wp_create_nonce( 'bricks-nonce-builder' );
		Bricks_Folder_Provider::verify_upload();
	}, array( 'attachmentId' => $attachments[0], 'folderId' => $deep ) );
	mbb_assert( ! empty( $r['success'] ), 'Bricks upload verification accepts Media Bridge folders' );
	$r = mbb_ajax( static function () {
		$_REQUEST['nonce'] = wp_create_nonce( 'bricks-nonce-builder' );
		Bricks_Folder_Provider::verify_upload();
	}, array( 'attachmentId' => $attachments[0], 'folderId' => 0, 'retry' => '1' ) );
	mbb_assert( ! empty( $r['success'] ) && array() === $p->get_item_folder_ids( $attachments[0] ), 'Upload retry repairs and reads back folder assignment' );
	update_option( 'uplink_mbb_settings', array_merge( Plugin::defaults(), array( 'default_media_screen' => 0 ) ) );
	mbb_assert( admin_url( 'upload.php' ) === uplink_mbb_get_library_url(), 'WordPress mode opens the native library' );
	update_option( 'uplink_mbb_settings', array_merge( Plugin::defaults(), array( 'default_media_screen' => 1 ) ) );
	mbb_assert( str_contains( uplink_mbb_get_library_url(), 'page=bricks-media-folders' ), 'Enhanced mode opens Media Bridge' );
	$deny = static function ( $caps, $cap, $user_id, $args ) use ( $attachments ) { return 'edit_post' === $cap && $attachments[0] === (int) ( $args[0] ?? 0 ) ? array( 'do_not_allow' ) : $caps; };
	add_filter( 'map_meta_cap', $deny, 10, 4 );
	mbb_assert( is_wp_error( $p->assign_attachment( $attachments[0], $deep ) ), 'Reject assignment without attachment edit permission' );
	remove_filter( 'map_meta_cap', $deny );
	wp_set_current_user( 0 );
	mbb_assert( ! $p->is_available() && is_wp_error( $p->assign_attachment( $attachments[0], $deep ) ) && is_wp_error( $p->manage_folder( array( 'operation' => 'delete', 'folderId' => $deep ) ) ), 'Anonymous users cannot access or mutate folders' );
	wp_set_current_user( 1 );
	mbb_assert( ! is_wp_error( $p->manage_folder( array( 'operation' => 'delete', 'folderId' => $deep ) ) ) && get_post( $attachments[1] ), 'Deleting folder retains attachment' );
	mbb_assert( null === $p->max_depth(), 'Folder provider has no imposed depth cap' );
} finally {
	wp_set_current_user( 1 );
	update_option( 'uplink_mbb_settings', $saved_settings );
	if ( $fixture_post ) wp_delete_post( $fixture_post, true );
	foreach ( $attachments as $id ) wp_delete_attachment( $id, true );
	foreach ( array_reverse( $folders ) as $id ) wp_delete_term( $id, 'mbb_folder' );
	remove_filter( 'wp_die_ajax_handler', $die );
	$_REQUEST = $_POST = array();
}
echo "Media Bridge for Bricks integration tests passed.\n";
