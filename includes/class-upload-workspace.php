<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Upload_Workspace {
	private const SELECTOR = '.uplink-mbb-upload-workspace';

	/**
	 * Register Cimo's public selector filters before it localizes its settings.
	 *
	 * Cimo 1.5.0 introduced these PHP filters. Keeping the browser filters in
	 * assets() preserves compatibility with earlier Cimo releases.
	 */
	public static function register_cimo_integration(): void {
		if ( empty( Plugin::settings()['cimo_integration'] ) ) return;
		add_filter( 'cimo/select_files/allowed_locations', array( self::class, 'allow_cimo_location' ) );
		add_filter( 'cimo/drop_zone/allowed_locations', array( self::class, 'allow_cimo_location' ) );
	}

	/** @param mixed $locations */
	public static function allow_cimo_location( $locations ): array {
		$locations = is_array( $locations ) ? $locations : array();
		if ( ! in_array( self::SELECTOR, $locations, true ) ) {
			$locations[] = self::SELECTOR;
		}
		return $locations;
	}

	public static function cimo_available(): bool {
		return defined( 'CIMO_FILE' ) && ! empty( Plugin::settings()['cimo_integration'] );
	}

	public static function assets(): void {
		static $enqueued = false;
		if ( $enqueued ) return;
		$enqueued = true;
		// Cimo 1.5.0 can be loaded explicitly on frontend upload screens.
		if ( self::cimo_available() && ! is_admin() && function_exists( 'cimo_enqueue_assets' ) ) {
			cimo_enqueue_assets();
		}
		// Cimo releases before 1.5.0 expose only the browser-side filters.
		wp_enqueue_script( 'wp-hooks' );
		if ( self::cimo_available() ) wp_add_inline_script( 'wp-hooks', "['cimo.selectFiles.allowedLocations', 'cimo.dropZone.allowedLocations'].forEach(function (hook) { wp.hooks.addFilter(hook, 'uplink-media-bridge/upload-workspace', function (locations) { locations = Array.isArray(locations) ? locations : []; return locations.indexOf('" . self::SELECTOR . "') === -1 ? locations.concat(['" . self::SELECTOR . "']) : locations; }); });" );
		wp_enqueue_style( 'uplink-mbb-upload-workspace', UPLINK_MBB_URL . 'assets/media-modal.css', array(), UPLINK_MBB_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbb-upload-workspace', UPLINK_MBB_URL . 'assets/upload-workspace.js', array( 'jquery' ), UPLINK_MBB_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbb-upload-workspace', 'uplinkMbbUploadConfig', array(
			'cimoAvailable' => self::cimo_available(),
			'maxUploadBytes' => wp_max_upload_size(),
			'maxUploadSize' => size_format( wp_max_upload_size() ),
			'strings' => array(
				'acceptedFiles' => __( 'Files accepted by WordPress', 'media-bridge-for-bricks' ),
				'cancelUpload' => __( 'Cancel', 'media-bridge-for-bricks' ),
				'chooseFiles' => __( 'Choose files', 'media-bridge-for-bricks' ),
				'clearFiles' => __( 'Clear', 'media-bridge-for-bricks' ),
				'collectionCreated' => __( 'Folder created and selected.', 'media-bridge-for-bricks' ),
				'collectionName' => __( 'Folder name', 'media-bridge-for-bricks' ),
				'createCollection' => __( 'Create folder', 'media-bridge-for-bricks' ),
				'destination' => __( 'Destination', 'media-bridge-for-bricks' ),
				'dropFiles' => __( 'Drop files here', 'media-bridge-for-bricks' ),
				'fileKeptOriginal' => __( 'Original will be kept', 'media-bridge-for-bricks' ),
				'fileOverLimit' => __( 'Over the upload limit', 'media-bridge-for-bricks' ),
				'fileReady' => __( 'Ready', 'media-bridge-for-bricks' ),
				'fileWillOptimize' => __( 'Will optimize', 'media-bridge-for-bricks' ),
				/* translators: %d: Number of files exceeding the upload limit. */
				'filesOverLimit' => __( '%d over limit', 'media-bridge-for-bricks' ),
				/* translators: %1$d: Ready file count. %2$s: Formatted total file size. */
				'filesReady' => __( '%1$d files · %2$s ready', 'media-bridge-for-bricks' ),
				'keepOriginals' => __( 'Keep originals', 'media-bridge-for-bricks' ),
				'keepOriginalsHelp' => __( 'Upload the original file without Cimo pre-upload optimization. This can preserve embedded EXIF data.', 'media-bridge-for-bricks' ),
				'newCollection' => __( 'New folder', 'media-bridge-for-bricks' ),
				'parentCollection' => __( 'Parent folder', 'media-bridge-for-bricks' ),
				'perFile' => __( 'per file', 'media-bridge-for-bricks' ),
				/* translators: %s: File name. */
				'removeFile' => __( 'Remove %s', 'media-bridge-for-bricks' ),
				'renameUploadHelp' => __( 'You can rename optimized files too. The extension matches the file format.', 'media-bridge-for-bricks' ),
				/* translators: %d: Number of files to retry. */
				'retryFiles' => __( 'Retry %d', 'media-bridge-for-bricks' ),
				'topLevel' => __( 'Top level', 'media-bridge-for-bricks' ),
				'uncategorized'  => __( 'Uncategorized', 'media-bridge-for-bricks' ),
				/* translators: %d: Number of successfully uploaded files. */
				'uploadComplete' => __( '%d uploaded', 'media-bridge-for-bricks' ),
				'uploadFailed' => __( 'WordPress could not upload this file.', 'media-bridge-for-bricks' ),
				'uploadFileName' => __( 'File name', 'media-bridge-for-bricks' ),
				/* translators: %d: Number of files to upload. */
				'uploadFiles' => __( 'Upload %d', 'media-bridge-for-bricks' ),
				'uploadHeading' => __( 'Upload', 'media-bridge-for-bricks' ),
				'uploadInstructions' => self::cimo_available() ? __( 'Choose a folder, then add files. Optimization stays on unless you keep originals.', 'media-bridge-for-bricks' ) : __( 'Choose a folder, then add files.', 'media-bridge-for-bricks' ),
				/* translators: %1$d: Successful uploads. %2$d: Failed uploads. */
				'uploadResult' => __( '%1$d uploaded · %2$d failed', 'media-bridge-for-bricks' ),
				'uploading' => __( 'Uploading…', 'media-bridge-for-bricks' ),
				/* translators: %1$d: Completed uploads. %2$d: Total uploads. */
				'uploadingFiles' => __( '%1$d of %2$d finished', 'media-bridge-for-bricks' ),
				'validFileName' => __( 'Enter a file name without slashes.', 'media-bridge-for-bricks' ),
				'replacementHeading' => __( 'Choose a replacement', 'media-bridge-for-bricks' ),
				'replacementInstructions' => __( 'Choose one file. Review its final format and filename after optimization, then replace the attachment.', 'media-bridge-for-bricks' ),
				'chooseOneFile' => __( 'Choose only one replacement file.', 'media-bridge-for-bricks' ),
				'chooseFile' => __( 'Choose file', 'media-bridge-for-bricks' ),
				'dropFile' => __( 'Drop one file here', 'media-bridge-for-bricks' ),
				/* translators: %s: Formatted file size. */
				'fileSelected' => __( '1 file · %s ready', 'media-bridge-for-bricks' ),
				'replaceFile' => __( 'Replace file', 'media-bridge-for-bricks' ),
			),
		) );
	}
}
