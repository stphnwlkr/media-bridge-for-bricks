<?php
namespace UplinkPress\MediaBridgeForBricks;

if ( ! defined( 'ABSPATH' ) ) exit;

final class Plugin {
	private const SETTINGS_OPTION = 'uplink_mbb_settings';
	private static ?self $instance = null;
	public static function bricks_is_active(): bool {
		return defined( 'BRICKS_VERSION' ) && version_compare( BRICKS_VERSION, '2.4', '>=' );
	}
	public static function bootstrap(): void {
		add_action( 'bricks/media_folder_providers/register', static function ( $registry ) {
			require_once UPLINK_MBB_PATH . 'includes/class-bricks-folder-provider.php';
			$registry::register( new Bricks_Folder_Provider() );
		} );
		add_filter( 'bricks/media_folder_provider', static function ( $selected, $available, $post_type ) {
			return 'attachment' === $post_type && ! empty( self::settings()['prefer_media_bridge_folders'] ) && isset( $available['media-bridge'] ) ? 'media-bridge' : $selected;
		}, 10, 3 );
		Upload_Workspace::register_cimo_integration();
		// Themes load after plugins_loaded. Register Bricks integration after its interfaces exist.
		add_action( 'after_setup_theme', array( self::class, 'boot' ), 30 );
	}
	public static function activate(): void {
		$theme = wp_get_theme();
		if ( 'bricks' !== $theme->get_template() ) {
			wp_die( esc_html__( 'Media Bridge for Bricks requires the Bricks theme.', 'media-bridge-for-bricks' ), '', array( 'back_link' => true ) );
		}
		add_option( self::SETTINGS_OPTION, self::defaults(), '', false );
	}
	public static function boot(): void {
		if ( ! self::bricks_is_active() || ! interface_exists( '\Bricks\Media_Folder_Provider' ) ) {
			add_action( 'admin_notices', static function () {
				if ( current_user_can( 'activate_plugins' ) ) echo '<div class="notice notice-error"><p>' . esc_html__( 'Media Bridge for Bricks requires Bricks 2.4 or later.', 'media-bridge-for-bricks' ) . '</p></div>';
			} );
			return;
		}
		if ( self::$instance ) return;
		self::$instance = new self();
	}
	private function __construct() {
		require_once UPLINK_MBB_PATH . 'includes/class-bricks-folder-provider.php';
		$folders = new Folder_Provider();
		add_action( 'init', array( $folders, 'register' ) );
		add_action( 'admin_init', array( $folders, 'maybe_recount' ), 20 );
		new Native_Media_Library( $folders, is_admin() );
		new File_Replacement();
		new Image_Conversion();
		new Folder_Import();
		new Folder_Cleanup();
		add_action( 'wp_ajax_uplink_mbb_cimo_plan', array( Cimo_Optimization::class, 'ajax_plan' ) );
		new Bricks_Dynamic_Data();
		if ( ! empty( self::settings()['collection_gallery'] ) ) new Collection_Gallery( $folders );
		add_action( 'wp_ajax_bricks_verify_media_upload_folder', array( Bricks_Folder_Provider::class, 'verify_upload' ), 1 );
		if ( is_admin() ) new Admin();
		add_action( 'admin_enqueue_scripts', array( self::class, 'toast_assets' ), 1 );
	}
	public static function toast_assets(): void {
		if ( ! current_user_can( 'upload_files' ) ) return;
		wp_enqueue_style( 'uplink-mbb-toasts', UPLINK_MBB_URL . 'assets/toasts.css', array(), UPLINK_MBB_ASSET_VERSION );
		wp_enqueue_script( 'uplink-mbb-toasts', UPLINK_MBB_URL . 'assets/toasts.js', array(), UPLINK_MBB_ASSET_VERSION, false );
		wp_localize_script( 'uplink-mbb-toasts', 'uplinkMbbToastStrings', array( 'dismiss' => __( 'Dismiss notification', 'media-bridge-for-bricks' ) ) );
	}
	public static function defaults(): array {
		$defaults = array(
			'default_media_screen' => 1,
			'manager_label' => 'Media Bridge', 'native_media_collections' => 1,
			'native_pagination' => 0, 'parent_count_display' => 'direct', 'prefer_media_bridge_folders' => 0,
			'appearance' => 'auto', 'file_replacement' => 1, 'collection_gallery' => 1,
			'image_conversion' => 1, 'compression_webp' => 1, 'compression_avif' => 1, 'compression_jxl' => 0, 'compression_jpg' => 0,
			'compression_roles' => array( 'administrator' ), 'restoration_roles' => array( 'administrator' ),
			'cimo_integration' => 1, 'exif_dynamic_data' => 1, 'exif_gps' => 0, 'exif_map' => 0,
		);
		foreach ( self::appearance_defaults() as $mode => $colors ) {
			foreach ( $colors as $name => $value ) $defaults[ $mode . '_' . $name ] = $value;
		}
		return $defaults;
	}
	public static function appearance_defaults(): array {
		return array(
			'light' => array(
				'background'       => '#f6f7f7',
				'surface'          => '#ffffff',
				'card'             => '#ffffff',
				'surface_muted'    => '#f6f7f7',
				'text'             => '#1d2327',
				'muted'            => '#646970',
				'border'           => '#dcdcde',
				'soft_border'      => '#edf0f2',
				'control'          => '#ffffff',
				'control_border'   => '#c3c4c7',
				'preview'          => '#f0f0f1',
				'preview_text'     => '#646970',
				'badge'            => '#f0f0f1',
				'badge_text'       => '#646970',
				'accent'           => '#2271b1',
				'accent_text'      => '#0a4b78',
				'accent_icon'      => '#ffffff',
				'accent_soft'      => '#eaf3fb',
				'accent_hover'     => '#135e96',
				'alt'              => '#e8f7ee',
				'alt_text'         => '#176b3a',
				'guide'            => '#f6fafe',
				'guide_border'     => '#c7d7e6',
				'guide_text'       => '#3c434a',
				'drop_bg'          => '#d7f0df',
				'drop_border'      => '#007a1f',
				'drop_text'        => '#1d2327',
				'danger'           => '#b32d2e',
				'danger_solid'     => '#b32d2e',
				'danger_icon'      => '#ffffff',
				'scrollbar_track'  => '#f0f0f1',
				'scrollbar_thumb'  => '#787c82',
				'scrollbar_hover'  => '#50575e',
			),
			'dark' => array(
				'background'       => '#171a1d',
				'surface'          => '#23272b',
				'card'             => '#23272b',
				'surface_muted'    => '#1b1f23',
				'text'             => '#f0f0f1',
				'muted'            => '#b2b8bf',
				'border'           => '#444c56',
				'soft_border'      => '#383f47',
				'control'          => '#2c3338',
				'control_border'   => '#59636e',
				'preview'          => '#111417',
				'preview_text'     => '#b2b8bf',
				'badge'            => '#343a40',
				'badge_text'       => '#b2b8bf',
				'accent'           => '#72aee6',
				'accent_text'      => '#b9dcfa',
				'accent_icon'      => '#1d2327',
				'accent_soft'      => '#20384c',
				'accent_hover'     => '#b9dcfa',
				'alt'              => '#173c28',
				'alt_text'         => '#b7f7ca',
				'guide'            => '#202a33',
				'guide_border'     => '#40566a',
				'guide_text'       => '#d7dde3',
				'drop_bg'          => '#173c28',
				'drop_border'      => '#62c77d',
				'drop_text'        => '#dff7e6',
				'danger'           => '#ff8085',
				'danger_solid'     => '#b32d2e',
				'danger_icon'      => '#ffffff',
				'scrollbar_track'  => '#171a1d',
				'scrollbar_thumb'  => '#6f7a86',
				'scrollbar_hover'  => '#8c98a4',
			),
		);
	}

	/**
	 * Sanitize the appearance controls and preserve accessible foreground pairs.
	 */
	public static function sanitize_appearance( array $input, ?array $base = null ): array {
		$base       = wp_parse_args( $base ?? self::settings(), self::defaults() );
		$appearance = sanitize_key( $input['appearance'] ?? $base['appearance'] );
		if ( ! in_array( $appearance, array( 'auto', 'light', 'dark' ), true ) ) {
			$appearance = 'auto';
		}
		$result = array( 'appearance' => $appearance );
		foreach ( self::appearance_defaults() as $mode => $defaults ) {
			foreach ( $defaults as $name => $fallback ) {
				$key = $mode . '_' . $name;
				$result[ $key ] = sanitize_hex_color( (string) ( $input[ $key ] ?? $base[ $key ] ?? $fallback ) ) ?: $fallback;
			}
			$against = array(
				'text'            => array( 'background', 'surface', 'card', 'surface_muted', 'control' ),
				'muted'           => array( 'background', 'surface', 'card', 'surface_muted', 'control' ),
				'preview_text'    => array( 'preview', 'surface_muted' ),
				'badge_text'      => array( 'badge' ),
				'accent'          => array( 'background', 'surface', 'card', 'control' ),
				'accent_text'     => array( 'background', 'surface', 'card', 'control', 'accent_soft' ),
				'accent_icon'     => array( 'accent', 'accent_hover' ),
				'danger_icon'     => array( 'danger_solid' ),
				'accent_hover'    => array( 'background', 'surface', 'card', 'control' ),
				'alt_text'        => array( 'alt' ),
				'guide_text'      => array( 'guide' ),
				'drop_text'       => array( 'drop_bg' ),
				'danger'          => array( 'background', 'surface', 'card', 'control' ),
				'scrollbar_thumb' => array( 'scrollbar_track' ),
				'scrollbar_hover' => array( 'scrollbar_track' ),
			);
			foreach ( $against as $foreground => $backgrounds ) {
				$minimum = in_array( $foreground, array( 'scrollbar_thumb', 'scrollbar_hover' ), true ) ? 3.0 : 4.5;
				foreach ( $backgrounds as $background ) {
					if ( self::contrast_ratio( $result[ $mode . '_' . $foreground ], $result[ $mode . '_' . $background ] ) < $minimum ) {
						$result[ $mode . '_' . $foreground ] = $defaults[ $foreground ];
						break;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Find the smallest black or white mix that gives text sufficient contrast.
	 */
	private static function accessible_background( string $background, string $foreground, float $minimum ): string {
		$source = array_map( 'hexdec', str_split( ltrim( $background, '#' ), 2 ) );
		$best = '';
		$best_step = 257;

		foreach ( array( 0, 255 ) as $target ) {
			for ( $step = 1; $step <= 256; $step++ ) {
				$mix = $step / 256;
				$channels = array_map(
					static fn( int $channel ): int => (int) round( $channel + ( $target - $channel ) * $mix ),
					$source
				);
				$candidate = sprintf( '#%02x%02x%02x', $channels[0], $channels[1], $channels[2] );
				if ( self::contrast_ratio( $foreground, $candidate ) >= $minimum ) {
					if ( $step < $best_step ) {
						$best = $candidate;
						$best_step = $step;
					}
					break;
				}
			}
		}

		return $best ?: ( self::contrast_ratio( $foreground, '#000000' ) >= $minimum ? '#000000' : '#ffffff' );
	}

	public static function update_settings( array $settings ): bool {
		return update_option( self::SETTINGS_OPTION, wp_parse_args( $settings, self::defaults() ), false );
	}

	private static function contrast_ratio( string $first, string $second ): float {
		$values = array();
		foreach ( array( $first, $second ) as $color ) {
			$channels = array_map( static fn( string $hex ): float => hexdec( $hex ) / 255, str_split( ltrim( $color, '#' ), 2 ) );
			$channels = array_map( static fn( float $channel ): float => $channel <= 0.04045 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4, $channels );
			$values[] = ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
		}
		return ( max( $values ) + 0.05 ) / ( min( $values ) + 0.05 );
	}

	public static function settings(): array {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( array( 'light', 'dark' ) as $mode ) {
			if ( ! isset( $saved[ $mode . '_card' ] ) && isset( $saved[ $mode . '_surface' ] ) ) {
				$saved[ $mode . '_card' ] = $saved[ $mode . '_surface' ];
			}
		}
		return wp_parse_args( $saved, self::defaults() );
	}

}
