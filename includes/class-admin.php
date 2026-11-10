<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_uplink_mbb_reset_settings', array( $this, 'reset_settings' ) );
	}
	public function menu(): void {
		add_media_page( 'Uplink Media Manager', 'Media Manager Settings', 'manage_options', 'media-bridge-for-bricks', array( $this, 'render' ) );
	}
	public function assets( string $hook ): void {
		if ( 'media_page_media-bridge-for-bricks' !== $hook || ! current_user_can( 'manage_options' ) ) return;
		wp_enqueue_script( 'uplink-mbb-happyfiles-import', UPLINK_MBB_URL . 'assets/happyfiles-import.js', array(), UPLINK_MBB_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbb-happyfiles-import', 'uplinkMbbImport', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'uplink_mbb_happyfiles_import' ), 'cleanupNonce' => wp_create_nonce( 'uplink_mbb_folder_cleanup' ),
			'strings' => array(
				'cleanupStart' => __( 'Clear legacy folder data', 'uplink-media-manager' ), 'cleanupResume' => __( 'Resume cleanup', 'uplink-media-manager' ), 'finish' => __( 'Finish post-import cleanup', 'uplink-media-manager' ),
				/* translators: %1$d: Removed legacy folders. %2$d: Total legacy folders. */
				'cleanupProgress' => __( 'Legacy folders cleared: %1$d of %2$d.', 'uplink-media-manager' ),
				'cleanupDone' => __( 'Legacy folder data cleared. Media files and Uplink Media Manager folders are preserved.', 'uplink-media-manager' ),
				'resumeHint' => __( 'Progress is saved. Choose Resume import to continue.', 'uplink-media-manager' ),
				'start' => __( 'Import folders', 'uplink-media-manager' ), 'resume' => __( 'Resume import', 'uplink-media-manager' ),
				'failed' => __( 'The import could not continue. Reload this page and resume.', 'uplink-media-manager' ),
				'paused' => __( 'Paused. Resume to continue.', 'uplink-media-manager' ), 'pausing' => __( 'Pausing after the current batch…', 'uplink-media-manager' ),
				'running' => __( 'Importing. Keep this page open, or pause and resume later.', 'uplink-media-manager' ),
				/* translators: %1$d: Processed folders. %2$d: Total folders. %3$d: Checked media assignments. */
				'progress' => __( 'Folders %1$d of %2$d. Media assignments checked: %3$d.', 'uplink-media-manager' ),
				/* translators: %1$d: Created folders. %2$d: Reused folders. %3$d: Added media assignments. %4$d: Skipped media without edit permission. %5$d: Assignments already present. */
				'complete' => __( 'Import complete. Folders created: %1$d. Reused: %2$d. Media assignments added: %3$d. Already present: %5$d. Skipped for permissions: %4$d.', 'uplink-media-manager' ),
			),
		) );
		wp_enqueue_style( 'uplink-mbb-admin', UPLINK_MBB_URL . 'assets/admin.css', array(), UPLINK_MBB_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbb-settings', UPLINK_MBB_URL . 'assets/settings.js', array(), UPLINK_MBB_ASSET_VERSION, true );
		wp_localize_script( 'uplink-mbb-settings', 'uplinkMbbSettings', array( 'unsaved' => __( 'Unsaved changes', 'uplink-media-manager' ), 'resetConfirm' => __( 'Reset all Uplink Media Manager settings to their defaults?', 'uplink-media-manager' ) ) );
	}
	public function register_settings(): void {
		register_setting( 'uplink_mbb_settings_group', 'uplink_mbb_settings', array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize' ), 'default' => Plugin::defaults() ) );
	}
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$current = Plugin::settings();
		$result = array_merge( $current, Plugin::sanitize_appearance( $input, $current ) );
		foreach ( array( 'default_media_screen', 'native_media_collections', 'native_pagination', 'file_replacement', 'image_conversion', 'compression_webp', 'compression_avif', 'compression_jxl', 'compression_jpg', 'show_exif', 'exif_dynamic_data', 'exif_gps', 'exif_map', 'cimo_integration', 'collection_gallery', 'prefer_media_bridge_folders' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) $result[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}
		if ( array_key_exists( 'show_exif', $input ) && (bool) $result['show_exif'] !== (bool) $current['show_exif'] ) $result['show_exif_revision'] = wp_generate_uuid4();
		foreach ( array( 'compression_roles', 'restoration_roles' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) $result[ $key ] = array_values( array_intersect( array_keys( wp_roles()->roles ), array_filter( is_array( $input[ $key ] ) ? $input[ $key ] : array(), 'is_string' ) ) );
		}
		$result['compression_excluded_sizes'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', array_filter( (array) ( $input['compression_excluded_sizes'] ?? $current['compression_excluded_sizes'] ), 'is_string' ) ) ) ) );
		if ( isset( $input['manager_experience'] ) && in_array( $input['manager_experience'], array( 'wordpress', 'manager_default' ), true ) ) $result['default_media_screen'] = 'manager_default' === $input['manager_experience'] ? 1 : 0;
		if ( isset( $input['manager_label'] ) ) $result['manager_label'] = sanitize_text_field( $input['manager_label'] ) ?: 'Uplink Media Manager';
		if ( isset( $input['parent_count_display'] ) ) {
			$count = sanitize_key( $input['parent_count_display'] );
			$result['parent_count_display'] = in_array( $count, array( 'direct', 'cumulative', 'direct_total' ), true ) ? $count : 'direct';
		}
		return $result;
	}
	public function reset_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You cannot reset these settings.', 'uplink-media-manager' ) );
		check_admin_referer( 'uplink_mbb_reset_settings' );
		Plugin::update_settings( Plugin::defaults() );
		wp_safe_redirect( add_query_arg( 'reset', '1', $this->page_url() ) );
		exit;
	}
	private function page_url(): string { return admin_url( 'upload.php?page=media-bridge-for-bricks' ); }
	private function toggle( string $key, string $label, array $settings, string $description = '' ): void {
		?>
		<input type="hidden" name="uplink_mbb_settings[<?php echo esc_attr( $key ); ?>]" value="0">
		<label class="uplink-mbb-toggle"><input type="checkbox" name="uplink_mbb_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $settings[ $key ] ); ?>><span><?php echo esc_html( $label ); ?></span></label>
		<?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
		<?php
	}
	private function render_import(): void {
		if ( ! Folder_Import::can_import() ) return;
		$previews = array(); $selected = '';
		foreach ( Folder_Import_Sources::definitions() as $key => $definition ) {
			$previews[ $key ] = Folder_Import::preview( $key );
			if ( ! $selected && ! is_wp_error( $previews[ $key ] ) && $previews[ $key ]['folders'] ) $selected = $key;
		}
		if ( ! $selected ) {
			$errors = array_filter( $previews, 'is_wp_error' );
			?>
			<section class="uplink-mbb-card"><h2><?php esc_html_e( 'Import media folders', 'uplink-media-manager' ); ?></h2>
			<?php if ( $errors ) : foreach ( $errors as $error ) : ?>
				<p><?php echo esc_html( $error->get_error_message() ); ?></p>
			<?php endforeach; else : ?>
				<p><?php esc_html_e( 'No folders available to import', 'uplink-media-manager' ); ?></p>
			<?php endif; ?></section>
			<?php
			return;
		}
		?>
		<section class="uplink-mbb-card" id="uplink-mbb-happyfiles-import"><h2><?php esc_html_e( 'Import media folders', 'uplink-media-manager' ); ?></h2>
		<p><?php esc_html_e( 'Choose a source to copy its media folders into Uplink Media Manager, including empty folders and their hierarchy. Existing Uplink Media Manager folders and assignments are preserved. Repeating an import reuses previously imported folders.', 'uplink-media-manager' ); ?></p>
		<fieldset class="uplink-mbb-import-sources"><legend><?php esc_html_e( 'Folder source', 'uplink-media-manager' ); ?></legend>
		<?php foreach ( Folder_Import_Sources::definitions() as $key => $definition ) : $preview = $previews[ $key ]; $available = ! is_wp_error( $preview ) && $preview['folders'] > 0; ?>
		<label class="uplink-mbb-import-source">
			<input type="radio" name="mbb_import_source" value="<?php echo esc_attr( $key ); ?>" data-import-source <?php checked( $selected, $key ); ?> <?php disabled( ! $available ); ?> data-source-available="<?php echo $available ? '1' : '0'; ?>">
			<span><strong><?php echo esc_html( $definition['label'] ); ?></strong><small><?php
			if ( is_wp_error( $preview ) ) echo esc_html( $preview->get_error_message() );
			elseif ( ! $available ) esc_html_e( 'No media folders found to import.', 'uplink-media-manager' );
			else {
				/* translators: %1$d: Media folders. %2$d: Media-to-folder relationships. */
				echo esc_html( sprintf( __( 'Folders: %1$d. Media assignments: %2$d.', 'uplink-media-manager' ), $preview['folders'], $preview['assignments'] ) );
			}
			?></small><small class="uplink-mbb-source-status"><?php if ( ! is_wp_error( $preview ) ) { echo esc_html( Folder_Import_Sources::status_label( $preview['plugin_status'], $available ) ); if ( Folder_Import_Sources::network_active( $key ) ) esc_html_e( ' · Network-wide activation', 'uplink-media-manager' ); } ?></small></span>
		</label>
		<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Only media-library folders are imported. Stored folders can be detected even when the source plugin is inactive. Wicked Folders dynamic folders are not stored folders and are excluded.', 'uplink-media-manager' ); ?></p>
		<label class="uplink-mbb-toggle"><input type="checkbox" data-import-media checked><span><?php esc_html_e( 'Copy media assignments', 'uplink-media-manager' ); ?></span></label><p class="description"><?php esc_html_e( 'Adds imported folders to existing WordPress attachments. Files are not uploaded again or moved, and current Uplink Media Manager assignments remain intact.', 'uplink-media-manager' ); ?></p>
		<label class="uplink-mbb-toggle"><input type="checkbox" data-import-provider checked><span><?php esc_html_e( 'Use Uplink Media Manager folders in Bricks after import', 'uplink-media-manager' ); ?></span></label><p class="description"><?php esc_html_e( 'The source plugin can stay active for its existing content. This switches only Bricks’ media-folder provider. Change it later under Folder behavior.', 'uplink-media-manager' ); ?></p>
		<label class="uplink-mbb-toggle"><input type="checkbox" data-import-retire><span><?php esc_html_e( 'After import, deactivate the source plugin and clear its legacy folder data', 'uplink-media-manager' ); ?></span></label><p class="description"><?php esc_html_e( 'Optional. Requires media assignments. Permanently removes verified source media folders, their folder metadata and source assignments after import. Your files and Uplink Media Manager folders stay intact. On multisite, cleanup affects this site only; network-active plugins stay active. Site-active plugins are deactivated when you have permission. Plugin files, settings, licenses and folders for other content types stay in place.', 'uplink-media-manager' ); ?></p>
		<p><button type="button" class="button button-primary" data-import-start disabled><?php esc_html_e( 'Import folders', 'uplink-media-manager' ); ?></button> <button type="button" class="button" data-import-pause hidden><?php esc_html_e( 'Pause', 'uplink-media-manager' ); ?></button></p>
		<p role="status" aria-live="polite" data-import-status></p>
		<div class="uplink-mbb-settings-subsection uplink-mbb-cleanup"><h3><?php esc_html_e( 'Clear leftover folder data', 'uplink-media-manager' ); ?></h3><p><?php esc_html_e( 'For an earlier import, run a fresh import with media assignments to verify the current data. Cleanup removes only the selected source’s imported media folders, their metadata and source assignments. It can break source-plugin galleries or shortcodes that still use those folders. Review your migrated library and keep a database backup before clearing source data.', 'uplink-media-manager' ); ?></p><p data-cleanup-reason role="status" aria-live="polite"></p><label class="uplink-mbb-toggle"><input type="checkbox" data-cleanup-confirm><span><?php esc_html_e( 'I understand that the selected source’s legacy folder data will be permanently deleted.', 'uplink-media-manager' ); ?></span></label><p><button type="button" class="button" data-cleanup-start disabled><?php esc_html_e( 'Clear legacy folder data', 'uplink-media-manager' ); ?></button></p><p data-cleanup-status role="status" aria-live="polite"></p></div>

		<div class="uplink-mbb-docs-callout"><h3><?php esc_html_e( 'What the import keeps', 'uplink-media-manager' ); ?></h3><p><?php esc_html_e( 'Import preserves source folders, assignments and original files. The optional cleanup removes verified source folder data after import. Imported folders use Uplink Media Manager’s shared folder permissions. Source ownership rules, access restrictions, colors, custom media ordering, galleries and shortcodes are not copied. Existing matching Uplink Media Manager folders are reused without moving or reordering them.', 'uplink-media-manager' ); ?></p><p><?php esc_html_e( 'This is a one-time copy, with no ongoing synchronization. Imports run in batches. Pause or reload and resume with the same administrator account. Finish or resume the current source before choosing another. Review content that uses the source plugin before removing it.', 'uplink-media-manager' ); ?></p></div></section>
		<?php
	}
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = Plugin::settings();
		// Display-only tab selection.
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, array( 'settings', 'compression', 'exif', 'cimo', 'import', 'about' ), true ) ) $tab = 'settings';
		$experience = $s['default_media_screen'] ? 'manager_default' : 'wordpress';
		?>
		<div class="wrap uplink-mbb-wrap">
			<h1 class="screen-reader-text">Uplink Media Manager</h1>
			<header class="uplink-mbb-hero"><div class="uplink-mbb-hero-brand">
				<span class="uplink-mbb-hero-mark" aria-hidden="true"><img src="<?php echo esc_url( UPLINK_MBB_URL . 'assets/media-bridge-for-bricks-icon.svg' ); ?>" alt="" width="88" height="88"></span>
				<div><div class="uplink-mbb-hero-meta"><span class="uplink-mbb-hero-eyebrow">By Stephen Walker</span><span class="uplink-mbb-hero-version"><?php echo esc_html( 'Version ' . UPLINK_MBB_VERSION ); ?></span></div>
				<div class="uplink-mbb-hero-title" aria-hidden="true">Uplink Media Manager</div><p>Organize WordPress media with folders, image compression, galleries and optional Bricks integration.</p></div>
			</div></header>
			<?php settings_errors(); ?>
			<nav class="nav-tab-wrapper uplink-mbb-tabs" aria-label="Uplink Media Manager sections">
			<?php foreach ( array( 'settings' => 'Settings', 'compression' => 'Compression', 'exif' => 'EXIF', 'cimo' => 'CIMO', 'import' => 'Import', 'about' => 'About' ) as $key => $label ) : ?>
				<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $key, $this->page_url() ) ); ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
			</nav>
			<?php if ( 'about' === $tab ) : ?>
			<section class="uplink-mbb-card uplink-mbb-docs"><h2>Uplink Media Manager</h2><p>Manage media in the enhanced manager or standard WordPress library with any theme. Both views share the same folders, with no plugin-imposed nesting limit. Bricks 2.4 or later can use these folders through the optional integration.</p><p>HappyFiles keeps its own storage and Bricks integration. Use Import to copy its media folders into Uplink Media Manager. When Bricks is active, select Uplink Media Manager under Folder behavior to use them there too.</p><p>Folders, assignments, and settings remain in the database after deactivation or deletion.</p><a class="button button-primary" href="<?php echo esc_url( uplink_mbb_get_library_url() ); ?>">Open media library</a></section>
			<?php elseif ( 'import' === $tab ) : ?>
				<?php $this->render_import(); ?>
			<?php else : ?>
			<form action="options.php" method="post" id="uplink-mbb-main-settings" class="uplink-mbb-settings-form uplink-mbb-settings-form-main">
			<?php settings_fields( 'uplink_mbb_settings_group' ); ?>
			<div class="uplink-mbb-grid uplink-mbb-settings-groups">
			<?php if ( 'settings' === $tab ) : ?>

				<section class="uplink-mbb-card"><h2>Media Library experience</h2><p>Choose your default media workflow. Both views use the same folders and work with any WordPress theme.</p>
					<fieldset class="uplink-mbb-choice-group"><legend>Default experience</legend>
					<label class="uplink-mbb-choice"><input type="radio" name="uplink_mbb_settings[manager_experience]" value="wordpress" <?php checked( $experience, 'wordpress' ); ?>><span><strong>WordPress Media Library</strong><small>Use the familiar WordPress screens with folder controls.</small></span></label>
					<label class="uplink-mbb-choice"><input type="radio" name="uplink_mbb_settings[manager_experience]" value="manager_default" <?php checked( $experience, 'manager_default' ); ?>><span><strong>Enhanced Manager as the default</strong><small>Use the complete Uplink Media Manager library and media-selection dialogs.</small></span></label></fieldset>
					<div class="uplink-mbb-settings-subsection" data-uplink-mbb-wordpress-settings <?php echo 'wordpress' === $experience ? '' : 'hidden'; ?>><?php $this->toggle( 'native_media_collections', 'Show folders in the WordPress Media Library', $s, 'Adds folder filtering, upload destinations, assignments, and drag-and-drop to the native media screens.' ); ?></div>
					<div class="uplink-mbb-settings-subsection" data-uplink-mbb-manager-settings <?php echo 'manager_default' === $experience ? '' : 'hidden'; ?>>
								<p><?php esc_html_e( 'The Enhanced Manager includes Media Health, richer filters, bulk organization, expanded metadata editing, alternate layouts, and customizable colors.', 'uplink-media-manager' ); ?></p>
								<label for="uplink-mbb-manager-label"><?php esc_html_e( 'Manager label', 'uplink-media-manager' ); ?></label>
								<input type="text" class="regular-text" id="uplink-mbb-manager-label" name="uplink_mbb_settings[manager_label]" value="<?php echo esc_attr( $s['manager_label'] ); ?>" maxlength="80">
								<p class="description"><?php esc_html_e( 'Changes the manager menu label, page title, information panel, and media-selection tab. The default is Uplink Media Manager.', 'uplink-media-manager' ); ?></p>
								<input type="hidden" name="uplink_mbb_settings[native_pagination]" value="0">
								<label class="uplink-mbb-toggle"><input type="checkbox" name="uplink_mbb_settings[native_pagination]" value="1" <?php checked( $s['native_pagination'] ); ?>><span><?php esc_html_e( 'Use numbered pagination in the Enhanced Media Manager', 'uplink-media-manager' ); ?></span></label>
								<p class="description"><?php esc_html_e( 'Leave this off to load more media automatically while scrolling.', 'uplink-media-manager' ); ?></p>
								<p class="description"><?php esc_html_e( 'Use the palette button in the Enhanced Media Manager to preview and save its colors.', 'uplink-media-manager' ); ?></p>
							</div>
				</section>
				<section class="uplink-mbb-card"><h2>Folder behavior</h2><?php if ( Plugin::bricks_is_active() && Plugin::bricks_interfaces_available() ) : ?><?php $this->toggle( 'prefer_media_bridge_folders', 'Use Uplink Media Manager folders in Bricks', $s, 'Selects Uplink Media Manager in the Bricks media manager even while HappyFiles is active. Turn this off to use Bricks’ automatic provider selection.' ); ?><p>Organize folders in WordPress or Bricks. Changes apply to the same records immediately.</p><?php endif; ?>
				<label for="mbb-counts">Parent folder counts</label><select id="mbb-counts" name="uplink_mbb_settings[parent_count_display]"><?php foreach ( array( 'direct' => 'Direct assignments only', 'cumulative' => 'Including subfolders', 'direct_total' => 'Direct / including subfolders' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['parent_count_display'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><p class="description">For example, 17/23 means 17 items are assigned directly and 23 appear with the subfolders included.</p>
				<div class="uplink-mbb-settings-subsection"><h3>Appearance</h3><label for="mbb-appearance">Color mode</label><select id="mbb-appearance" name="uplink_mbb_settings[appearance]"><?php foreach ( array( 'auto' => 'System', 'light' => 'Light', 'dark' => 'Dark' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['appearance'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
				<div class="uplink-mbb-settings-subsection"><h3>File replacement</h3><?php $this->toggle( 'file_replacement', 'Enable file replacement', $s, 'Preserves the attachment ID, metadata, folder assignments, and custom order. Keeping the existing URL requires the same final file type.' ); ?></div></section>
			<section class="uplink-mbb-card"><h2>Gutenberg galleries and slideshows</h2><?php $this->toggle( 'collection_gallery', 'Enable gallery and slideshow blocks', $s, 'Adds Uplink Media Manager Gallery and Uplink Media Manager Slideshow to the WordPress block editor. Images come from the shared Uplink Media Manager folders.' ); ?><p>Choose a gallery layout or slideshow, with lightbox, caption and EXIF controls. New images added to the selected folder appear automatically.</p><p>Shortcode: <code>[uplink_mbb_gallery collection="your-folder-slug"]</code></p></section>
			<?php elseif ( 'compression' === $tab ) : ?>
<section class="uplink-mbb-card" id="uplink-mbb-compression-settings">
						<h2><?php esc_html_e( 'Compression', 'uplink-media-manager' ); ?></h2>
						<input type="hidden" name="uplink_mbb_settings[image_conversion]" value="0">
						<label class="uplink-mbb-toggle"><input id="uplink-mbb-use-compression" type="checkbox" name="uplink_mbb_settings[image_conversion]" value="1" <?php checked( $s['image_conversion'] ); ?>><span><?php esc_html_e( 'Use compression', 'uplink-media-manager' ); ?></span></label>
						<p class="description"><?php esc_html_e( 'On by default. Lets permitted users preview and compress images in the Enhanced Manager. Turn it off to disable new compression; existing originals can still be restored.', 'uplink-media-manager' ); ?></p>
						<fieldset class="uplink-mbb-compression-formats" aria-describedby="uplink-mbb-compression-formats-help">
							<legend><?php esc_html_e( 'Output formats', 'uplink-media-manager' ); ?></legend>
							<?php foreach ( array( 'webp' => 'WebP', 'avif' => 'AVIF', 'jxl' => 'JPEG XL', 'jpg' => 'JPG' ) as $format => $format_label ) : ?>
								<input type="hidden" name="uplink_mbb_settings[compression_<?php echo esc_attr( $format ); ?>]" value="0">
								<label class="uplink-mbb-toggle"><input type="checkbox" name="uplink_mbb_settings[compression_<?php echo esc_attr( $format ); ?>]" value="1" <?php checked( $s[ 'compression_' . $format ] ); ?>><span><?php echo esc_html( $format_label ); ?></span></label>
							<?php endforeach; ?>
							<p id="uplink-mbb-compression-formats-help" class="description"><?php esc_html_e( 'WebP and AVIF are on by default. JPEG XL and JPG are off by default. Only selected formats appear in the preview workspace. JPG does not support transparency.', 'uplink-media-manager' ); ?></p>
						</fieldset>
						<fieldset class="uplink-mbb-compression-exclusions">
							<legend><strong><?php esc_html_e( 'Exclude image sizes from compression', 'uplink-media-manager' ); ?></strong></legend>
							<p class="description"><?php esc_html_e( 'All sizes skips these sizes. To override for one image, choose Selected sizes in its optimization panel and check an excluded size. Sizes sharing the same file are excluded and overridden together.', 'uplink-media-manager' ); ?></p>
							<input type="hidden" name="uplink_mbb_settings[compression_excluded_sizes][]" value="">
							<?php
							$registered_sizes = wp_get_registered_image_subsizes();
							foreach ( array_unique( array_merge( array_keys( $registered_sizes ), $s['compression_excluded_sizes'] ) ) as $size_name ) :
								$dimensions = $registered_sizes[ $size_name ] ?? null;
								?>
								<label class="uplink-mbb-toggle"><input type="checkbox" name="uplink_mbb_settings[compression_excluded_sizes][]" value="<?php echo esc_attr( $size_name ); ?>" <?php checked( in_array( $size_name, $s['compression_excluded_sizes'], true ) ); ?>><span><?php echo esc_html( $size_name . ( $dimensions ? ' · ' . ( $dimensions['width'] ?: __( 'auto', 'uplink-media-manager' ) ) . ' × ' . ( $dimensions['height'] ?: __( 'auto', 'uplink-media-manager' ) ) : '' ) ); ?></span></label>
							<?php endforeach; ?>
						</fieldset>
						<h3><?php esc_html_e( 'Compression and restoration permissions', 'uplink-media-manager' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Choose roles separately for each action. Users must also have permission to upload files and edit the specific image. Both actions default to administrators only. Restoration remains available to permitted users when compression is turned off.', 'uplink-media-manager' ); ?></p>
						<table class="widefat striped">
							<thead><tr><th scope="col"><?php esc_html_e( 'Role', 'uplink-media-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Compress images', 'uplink-media-manager' ); ?></th><th scope="col"><?php esc_html_e( 'Restore originals', 'uplink-media-manager' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( wp_roles()->roles as $role_slug => $role_data ) : ?>
								<tr><th scope="row"><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></th>
								<?php foreach ( array( 'compression_roles' => __( 'Compress images', 'uplink-media-manager' ), 'restoration_roles' => __( 'Restore originals', 'uplink-media-manager' ) ) as $permission_key => $permission_label ) : ?>
									<td><label><input type="checkbox" name="uplink_mbb_settings[<?php echo esc_attr( $permission_key ); ?>][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $s[ $permission_key ], true ) ); ?>><span class="screen-reader-text"><?php echo esc_html( translate_user_role( $role_data['name'] ) . ': ' . $permission_label ); ?></span></label></td>
								<?php endforeach; ?></tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<input type="hidden" name="uplink_mbb_settings[compression_roles][]" value="">
						<input type="hidden" name="uplink_mbb_settings[restoration_roles][]" value="">

					</section>
			<?php elseif ( 'exif' === $tab ) : ?>
				<section class="uplink-mbb-card"><h2>Image metadata</h2><p>Inspect camera, lens, exposure and other available details from your WordPress media. These features work with any theme.</p>
				<?php $this->toggle( 'show_exif', 'Show EXIF in the media inspector', $s, 'Enables the EXIF tab by default when an image contains metadata. Users can override this in the Enhanced Manager display options. GPS coordinates remain separately controlled below.' ); ?>
				<?php if ( Plugin::bricks_is_active() && Plugin::bricks_interfaces_available() ) : ?>
				<div class="uplink-mbb-settings-subsection"><h3>Bricks dynamic data</h3><p>Add image metadata to text fields, templates and attachment query loops through the Bricks dynamic data picker.</p><?php $this->toggle( 'exif_dynamic_data', 'Enable EXIF in Bricks dynamic data', $s, 'Look for the Uplink Media Manager · EXIF group in the picker.' ); ?><p>Tags use the current attachment in an image loop or the featured image on a post. Append an attachment ID to choose an image, for example <code>{mbb_exif_camera:123}</code>.</p></div>
				<?php endif; ?>
				<div class="uplink-mbb-settings-subsection"><h3>GPS metadata</h3><?php $this->toggle( 'exif_gps', 'Include GPS coordinates and altitude', $s, 'Disabled by default. Enables GPS fields in the inspector and, when available, Bricks dynamic data.' ); ?><?php $this->toggle( 'exif_map', 'Enable external location maps', $s, 'Optional and disabled by default. Requires GPS above. When an EXIF map is visible, your browser contacts Esri for satellite tiles, or OpenStreetMap when you select the street layer. Requests share your IP address, browser details, site origin and the viewed map area. Photo files are not uploaded to these services.' ); ?><p class="description"><a href="https://www.esri.com/en-us/legal/terms/web-site-service" target="_blank" rel="noopener noreferrer">Esri terms</a> · <a href="https://www.esri.com/en-us/privacy/privacy-statements/privacy-statement" target="_blank" rel="noopener noreferrer">Esri privacy</a> · <a href="https://operations.osmfoundation.org/policies/tiles/" target="_blank" rel="noopener noreferrer">OpenStreetMap tile policy</a> · <a href="https://osmfoundation.org/wiki/Privacy_Policy" target="_blank" rel="noopener noreferrer">OpenStreetMap privacy</a></p></div></section>
				<?php if ( Plugin::bricks_is_active() && Plugin::bricks_interfaces_available() ) : ?>
				<section class="uplink-mbb-card uplink-mbb-docs"><h2>Available EXIF tags</h2><p>Fields return an empty value when the image has no matching metadata. GPS fields require the setting above.</p><div class="uplink-mbb-docs-table-wrap"><table class="widefat striped uplink-mbb-docs-table"><thead><tr><th>Field</th><th>Dynamic tag</th></tr></thead><tbody><?php foreach ( Bricks_Dynamic_Data::fields() as $name => $field ) : ?><tr><td><?php echo esc_html( $field[0] ); ?></td><td><code><?php echo esc_html( '{mbb_exif_' . $name . '}' ); ?></code></td></tr><?php endforeach; ?></tbody></table></div></section>
				<?php endif; ?>
			<?php elseif ( 'cimo' === $tab ) : ?>
				<section class="uplink-mbb-card"><div class="uplink-mbb-section-heading"><h2>CIMO image optimization</h2><span class="uplink-mbb-badge <?php echo defined( 'CIMO_FILE' ) ? '' : 'is-optional'; ?>"><?php echo defined( 'CIMO_FILE' ) ? 'Detected' : 'Optional'; ?></span></div><p>Use CIMO's optional pre-upload optimization inside the upload and replacement workspaces. Post-upload compression and format conversion are available in the Optimization tab without Cimo Pro.</p><?php $this->toggle( 'cimo_integration', 'Enable CIMO integration', $s, 'Requires the CIMO Image Optimizer plugin. Existing CIMO metadata can still be reviewed when the optimizer is inactive.' ); ?><div class="uplink-mbb-docs-callout"><h3>Keep originals</h3><p>The upload workspace includes a Keep originals switch when CIMO is available. Use it to bypass pre-upload optimization for that upload, including when you need to preserve embedded EXIF data. Closing the dialog restores the previous CIMO setting.</p></div></section>
				<section class="uplink-mbb-card"><h2>Optimization details</h2><p>Review CIMO status, original and optimized sizes, saved space, converted format, processing time, and generated WordPress image sizes in the attachment inspector.</p><p>The manager also includes optimization indicators and filters. Replacing a file clears stale optimization metadata.</p><h3>EXIF metadata</h3><p>Optimization can remove embedded camera and location details. Uplink Media Manager reads WordPress image metadata and available original-image EXIF. Metadata absent from the stored image cannot be reconstructed.</p></section>
			<?php endif; ?>
			</div></form>
			<div class="uplink-mbb-floating-save" data-uplink-mbb-floating-save><span class="uplink-mbb-save-state" data-uplink-mbb-save-state role="status" aria-live="polite"></span><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="uplink-mbb-reset-form" data-uplink-mbb-reset-form><input type="hidden" name="action" value="uplink_mbb_reset_settings"><?php wp_nonce_field( 'uplink_mbb_reset_settings' ); ?><button type="submit" class="button button-secondary">Reset to defaults</button></form><button type="submit" form="uplink-mbb-main-settings" class="button button-primary button-large">Save settings</button></div>
			<?php endif; ?>
		</div>
		<?php
	}
}
