<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Bricks 2.4 provider. No copies or synchronization of folder relationships. */
final class Bricks_Folder_Provider extends Folder_Provider implements \Bricks\Media_Folder_Provider, \Bricks\Media_Folder_Item_Provider, \Bricks\Media_Folder_Item_Assignment_Provider {
	public function get_id() { return $this->id(); }
	public function get_label() { return $this->label(); }
	public function is_available(): bool { return parent::is_available() && current_user_can( 'upload_files' ); }
	public function can_manage() {
		$taxonomy = get_taxonomy( $this->taxonomy() );
		return $this->is_available() && $taxonomy && current_user_can( $taxonomy->cap->manage_terms );
	}
	public function can_assign() {
		$taxonomy = get_taxonomy( $this->taxonomy() );
		return $this->is_available() && $taxonomy && current_user_can( $taxonomy->cap->assign_terms );
	}
	public function get_folders() {
		if ( ! $this->is_available() ) return array();
		$terms = $this->get_terms();
		if ( is_wp_error( $terms ) ) return array();
		return array_map( static fn( $term ) => array(
			'id' => (int) $term->term_id, 'name' => wp_specialchars_decode( $term->name ),
			'parent' => (int) $term->parent, 'count' => (int) $term->count,
		), $this->order_terms_hierarchically( $terms ) );
	}
	public function apply_query( $query_args, $folder_id ) {
		if ( '' === $folder_id || null === $folder_id ) return $query_args;
		$clause = array( 'taxonomy' => $this->taxonomy(), 'field' => 'term_id', 'include_children' => true );
		if ( 'unfiled' === $folder_id ) {
			$clause['operator'] = 'NOT EXISTS';
		} elseif ( is_scalar( $folder_id ) && absint( $folder_id ) && $this->get_term( absint( $folder_id ) ) ) {
			$clause['terms'] = array( absint( $folder_id ) );
		} else {
			$query_args['post__in'] = array( 0 );
			return $query_args;
		}
		$existing = $query_args['tax_query'] ?? array();
		$query_args['tax_query'] = $existing ? array( 'relation' => 'AND', $existing, $clause ) : array( $clause );
		return $query_args;
	}
	public function get_item_folder_ids( $item_id ) { return $this->get_object_terms( absint( $item_id ) ); }
	public function assign_item( $item_id, $folder_id ) { return $this->assign_attachment( $item_id, $folder_id ); }
	public function assign_attachment( $attachment_id, $folder_id ) {
		$attachment_id = absint( $attachment_id );
		$folder_id = absint( $folder_id );
		if ( ! $this->can_assign() || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new \WP_Error( 'media_folder_not_allowed', __( 'You cannot move this attachment.', 'media-bridge-for-bricks' ) );
		}
		if ( $folder_id && ! $this->get_term( $folder_id ) ) return new \WP_Error( 'media_folder_not_found', __( 'The folder no longer exists.', 'media-bridge-for-bricks' ) );
		$result = $this->set_object_terms( $attachment_id, $folder_id ? array( $folder_id ) : array() );
		return is_wp_error( $result ) ? $result : true;
	}
	public function manage_folder( $input ) {
		if ( ! $this->can_manage() ) return new \WP_Error( 'media_folder_not_allowed', __( 'You cannot manage media folders.', 'media-bridge-for-bricks' ) );
		if ( ! is_array( $input ) ) return new \WP_Error( 'media_folder_invalid', 'Invalid folder request.' );
		foreach ( array( 'operation', 'folderId', 'name', 'parent', 'position' ) as $key ) {
			if ( isset( $input[ $key ] ) && ! is_scalar( $input[ $key ] ) ) return new \WP_Error( 'media_folder_invalid', 'Invalid folder request.' );
		}
		$operation = sanitize_key( $input['operation'] ?? '' );
		$id = absint( $input['folderId'] ?? 0 );
		$name = sanitize_text_field( $input['name'] ?? '' );
		$term = $id ? $this->get_term( $id ) : null;
		$parent = array_key_exists( 'parent', $input ) ? absint( $input['parent'] ) : ( $term ? (int) $term->parent : 0 );
		if ( ! in_array( $operation, array( 'create', 'rename', 'move', 'delete' ), true ) || ( 'create' !== $operation && ! $term ) ) return new \WP_Error( 'media_folder_invalid', 'Choose a valid folder and operation.' );
		if ( $parent && ! $this->get_term( $parent ) ) return new \WP_Error( 'media_folder_parent_not_found', 'The parent folder no longer exists.' );
		if ( $id && ( $parent === $id || in_array( $id, array_map( 'intval', get_ancestors( $parent, $this->taxonomy(), 'taxonomy' ) ), true ) ) ) return new \WP_Error( 'media_folder_parent_invalid', 'A folder cannot be moved into itself or a descendant.' );
		if ( in_array( $operation, array( 'create', 'rename' ), true ) && '' === $name ) return new \WP_Error( 'media_folder_name_required', 'Enter a folder name.' );
		if ( in_array( $operation, array( 'create', 'rename', 'move' ), true ) ) {
			$terms = $this->get_terms();
			if ( is_wp_error( $terms ) ) return $terms;
			foreach ( $terms as $sibling ) {
				if ( (int) $sibling->term_id !== $id && (int) $sibling->parent === $parent && 0 === strcasecmp( $sibling->name, 'move' === $operation ? $term->name : $name ) ) return new \WP_Error( 'media_folder_name_exists', 'A folder with this name already exists in this location.' );
			}
		}
		if ( 'create' === $operation ) {
			$result = $this->create_term( array( 'name' => $name, 'parent' => $parent ) );
			if ( ! is_wp_error( $result ) ) { $id = (int) $result['term_id']; $this->append_position( $id, $parent ); }
		} elseif ( 'delete' === $operation ) {
			$result = $this->delete_term( $id );
		} else {
			$result = $this->update_term( $id, 'rename' === $operation ? array( 'name' => $name ) : array( 'parent' => $parent ) );
			if ( ! is_wp_error( $result ) && 'move' === $operation ) {
				$siblings = get_terms( array( 'taxonomy' => $this->taxonomy(), 'hide_empty' => false, 'parent' => $parent, 'exclude' => array( $id ) ) );
				if ( is_wp_error( $siblings ) ) return $siblings;
				$ids = array_map( static fn( $sibling ) => (int) $sibling->term_id, $this->order_terms_hierarchically( $siblings ) );
				$position = isset( $input['position'] ) ? min( count( $ids ), max( 0, (int) $input['position'] ) ) : count( $ids );
				array_splice( $ids, $position, 0, array( $id ) );
				foreach ( $ids as $index => $sibling_id ) $this->set_position( $sibling_id, $index );
			}
		}
		if ( is_wp_error( $result ) ) return $result;
		if ( false === $result || null === $result ) return new \WP_Error( 'media_folder_update_failed', 'The folder could not be saved.' );
		return array( 'folders' => $this->get_folders() );
	}

	/** Bricks 2.4.2 restricts its upload verification to HappyFiles. Handle our provider only. */
	public static function verify_upload(): void {
		$active = \Bricks\Media_Folder_Providers::get_active();
		if ( ! $active instanceof self ) return;
		\Bricks\Ajax::verify_request( 'bricks-nonce-builder' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Bricks\Ajax::verify_request above verifies the builder nonce and permissions before reading these values.
		$id = isset( $_POST['attachmentId'] ) && is_scalar( $_POST['attachmentId'] ) ? absint( $_POST['attachmentId'] ) : 0;
		$folder = isset( $_POST['folderId'] ) && is_scalar( $_POST['folderId'] ) ? absint( $_POST['folderId'] ) : 0;
		if ( ! $active->can_assign() || 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) wp_send_json_error( array( 'code' => 'media_folder_not_allowed', 'message' => 'You cannot move this attachment.' ), 403 );
		$ids = $active->get_item_folder_ids( $id );
		$matches = ! is_wp_error( $ids ) && ( $folder ? in_array( $folder, array_map( 'intval', $ids ), true ) : ! $ids );
		$retry = isset( $_POST['retry'] ) && is_string( $_POST['retry'] ) ? sanitize_text_field( wp_unslash( $_POST['retry'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $matches && '1' === $retry ) {
			$result = $active->assign_attachment( $id, $folder );
			$ids = $active->get_item_folder_ids( $id );
			$matches = ! is_wp_error( $result ) && ! is_wp_error( $ids ) && ( $folder ? in_array( $folder, array_map( 'intval', $ids ), true ) : ! $ids );
		}
		if ( ! $matches ) wp_send_json_error( array( 'code' => 'media_upload_folder_mismatch', 'message' => 'The uploaded file is not in its intended folder.' ), 400 );
		wp_send_json_success( array( 'attachmentId' => $id, 'folderId' => $folder ) );
	}
}
