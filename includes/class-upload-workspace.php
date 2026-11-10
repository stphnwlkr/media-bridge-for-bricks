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

	/** Keep shared overrides after every workspace feature loaded on this page. */
	public static function ui_assets( array $dependencies ): void {
		$handle = 'uplink-mbb-workspace-ui';
		wp_enqueue_style( $handle, UPLINK_MBB_URL . 'assets/workspace-ui.css', $dependencies, UPLINK_MBB_ASSET_VERSION );
		$registered = wp_styles()->registered[ $handle ] ?? null;
		if ( $registered ) {
			$registered->deps = array_values( array_unique( array_merge( $registered->deps, $dependencies ) ) );
		}
	}

	/** Native WordPress uploads can retain original bytes without requiring folder controls. */
	public static function native_assets(): void {
		if ( ! current_user_can( 'upload_files' ) || ! empty( Plugin::settings()['default_media_screen'] ) || ! self::cimo_available() ) return;
		wp_enqueue_style( 'uplink-mbb-native-upload', UPLINK_MBB_URL . 'assets/native-upload.css', array(), UPLINK_MBB_ASSET_VERSION );
		global $pagenow;
		$dependencies = 'media-new.php' === $pagenow ? array( 'jquery', 'plupload-handlers' ) : array( 'media-views', 'jquery' );
		wp_enqueue_script( 'uplink-mbb-native-upload', UPLINK_MBB_URL . 'assets/native-upload.js', $dependencies, UPLINK_MBB_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbb-native-upload', 'uplinkMbbNativeUpload', array(
			'keepOriginals' => __( 'Keep originals', 'uplink-media-manager' ),
			'help' => __( 'Skip Cimo compression for this upload to preserve the original file and embedded EXIF. WordPress may still generate image sizes. Resets after the upload finishes or the dialog closes.', 'uplink-media-manager' ),
		) );
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
		self::ui_assets( array( 'uplink-mbb-upload-workspace' ) );
		wp_enqueue_script( 'uplink-mbb-upload-workspace', UPLINK_MBB_URL . 'assets/upload-workspace.js', array( 'jquery' ), UPLINK_MBB_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbb-upload-workspace', 'uplinkMbbUploadConfig', array(
			'cimoAvailable' => self::cimo_available(),
			'maxUploadBytes' => wp_max_upload_size(),
			'maxUploadSize' => size_format( wp_max_upload_size() ),
			'strings' => array(
				'acceptedFiles' => __( 'Files accepted by WordPress', 'uplink-media-manager' ),
				'cancelUpload' => __( 'Cancel', 'uplink-media-manager' ),
				'chooseFiles' => __( 'Choose files', 'uplink-media-manager' ),
				'clearFiles' => __( 'Clear', 'uplink-media-manager' ),
				'collectionCreated' => __( 'Folder created and selected.', 'uplink-media-manager' ),
				'collectionName' => __( 'Folder name', 'uplink-media-manager' ),
				'createCollection' => __( 'Create folder', 'uplink-media-manager' ),
				'destination' => __( 'Destination', 'uplink-media-manager' ),
				'dropFiles' => __( 'Drop files here', 'uplink-media-manager' ),
				'fileKeptOriginal' => __( 'Original will be kept', 'uplink-media-manager' ),
				'fileOverLimit' => __( 'Over the upload limit', 'uplink-media-manager' ),
				'fileReady' => __( 'Ready', 'uplink-media-manager' ),
				'fileWillOptimize' => __( 'Will optimize', 'uplink-media-manager' ),
				/* translators: %d: Number of files exceeding the upload limit. */
				'filesOverLimit' => __( '%d over limit', 'uplink-media-manager' ),
				/* translators: %1$d: Ready file count. %2$s: Formatted total file size. */
				'filesReady' => __( '%1$d files · %2$s ready', 'uplink-media-manager' ),
				'keepOriginals' => __( 'Keep originals', 'uplink-media-manager' ),
				'keepOriginalsHelp' => __( 'Upload the original file without Cimo pre-upload optimization. This can preserve embedded EXIF data.', 'uplink-media-manager' ),
				'newCollection' => __( 'New folder', 'uplink-media-manager' ),
				'parentCollection' => __( 'Parent folder', 'uplink-media-manager' ),
				'perFile' => __( 'per file', 'uplink-media-manager' ),
				/* translators: %s: File name. */
				'removeFile' => __( 'Remove %s', 'uplink-media-manager' ),
				'renameUploadHelp' => __( 'You can rename optimized files too. The extension matches the file format.', 'uplink-media-manager' ),
				/* translators: %d: Number of files to retry. */
				'retryFiles' => __( 'Retry %d', 'uplink-media-manager' ),
				'topLevel' => __( 'Top level', 'uplink-media-manager' ),
				'uncategorized'  => __( 'Uncategorized', 'uplink-media-manager' ),
				/* translators: %d: Number of successfully uploaded files. */
				'uploadComplete' => __( '%d uploaded', 'uplink-media-manager' ),
				'uploadFailed' => __( 'WordPress could not upload this file.', 'uplink-media-manager' ),
				'uploadFileName' => __( 'File name', 'uplink-media-manager' ),
				/* translators: %d: Number of files to upload. */
				'uploadFiles' => __( 'Upload %d', 'uplink-media-manager' ),
				'uploadHeading' => __( 'Upload', 'uplink-media-manager' ),
				'uploadInstructions' => self::cimo_available() ? __( 'Choose a folder, then add files. Optimization stays on unless you keep originals.', 'uplink-media-manager' ) : __( 'Choose a folder, then add files.', 'uplink-media-manager' ),
				/* translators: %1$d: Successful uploads. %2$d: Failed uploads. */
				'uploadResult' => __( '%1$d uploaded · %2$d failed', 'uplink-media-manager' ),
				'uploading' => __( 'Uploading…', 'uplink-media-manager' ),
				/* translators: %1$d: Completed uploads. %2$d: Total uploads. */
				'uploadingFiles' => __( '%1$d of %2$d finished', 'uplink-media-manager' ),
				'validFileName' => __( 'Enter a file name without slashes.', 'uplink-media-manager' ),
				'replacementHeading' => __( 'Choose a replacement', 'uplink-media-manager' ),
				'replacementInstructions' => __( 'Choose one file. Review its final format and filename after optimization, then replace the attachment.', 'uplink-media-manager' ),
				'chooseOneFile' => __( 'Choose only one replacement file.', 'uplink-media-manager' ),
				'chooseFile' => __( 'Choose file', 'uplink-media-manager' ),
				'dropFile' => __( 'Drop one file here', 'uplink-media-manager' ),
				/* translators: %s: Formatted file size. */
				'fileSelected' => __( '1 file · %s ready', 'uplink-media-manager' ),
				'replaceFile' => __( 'Replace file', 'uplink-media-manager' ),
			),
		) );
	}
}
