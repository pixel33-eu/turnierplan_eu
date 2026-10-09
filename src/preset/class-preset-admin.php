<?php
/**
 * Preset editor integration.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Preset;

use TurnierplanEU\WordPress\Cache\TransientRegistry;
use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;
use TurnierplanEU\WordPress\Settings\SettingsRepository;
use WP_Post;

/** Supplies a focused editor, validated saves, preview data, and duplication. */
final class PresetAdmin {

	/** Creates the admin integration. */
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly string $plugin_file,
		private readonly string $plugin_version
	) {
	}

	/** Registers editor hooks. */
	public function register(): void {
		add_action( 'add_meta_boxes_' . PresetRepository::POST_TYPE, array( $this, 'add_editor' ) );
		add_action( 'save_post_' . PresetRepository::POST_TYPE, array( $this, 'save' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_tpeu_duplicate_preset', array( $this, 'duplicate' ) );
		add_filter( 'redirect_post_location', array( $this, 'add_notice' ), 10, 2 );
	}

	/** Adds the full-width editor metabox. */
	public function add_editor(): void {
		add_meta_box(
			'tpeu-preset-editor',
			esc_html__( 'Configure embed', 'turnierplan-eu' ),
			array( $this, 'render_editor' ),
			PresetRepository::POST_TYPE,
			'normal',
			'high'
		);
	}

	/** Renders the React mount point and tamper-resistant save fields. */
	public function render_editor( WP_Post $post ): void {
		$config  = get_post_meta( $post->ID, PresetRepository::META_KEY, true );
		$encoded = wp_json_encode( is_array( $config ) ? $config : array() );
		$encoded = is_string( $encoded ) ? $encoded : '{}';

		wp_nonce_field( 'tpeu_save_preset_' . $post->ID, 'tpeu_preset_nonce' );
		?>
		<div id="tpeu-preset-editor-root"></div>
		<input id="tpeu-preset-config" name="tpeu_preset_config" type="hidden" value="<?php echo esc_attr( $encoded ); ?>">
		<?php if ( $post->ID > 0 && 'auto-draft' !== $post->post_status ) : ?>
			<p><label for="tpeu-preset-shortcode"><strong><?php echo esc_html__( 'Shortcode', 'turnierplan-eu' ); ?></strong></label></p>
			<p><input id="tpeu-preset-shortcode" class="large-text code" type="text" readonly value="<?php echo esc_attr( sprintf( '[turnierplan preset="%d"]', $post->ID ) ); ?>"></p>
			<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpeu_duplicate_preset&preset_id=' . $post->ID ), 'tpeu_duplicate_preset_' . $post->ID ) ); ?>"><?php echo esc_html__( 'Duplicate as new draft', 'turnierplan-eu' ); ?></a></p>
		<?php endif; ?>
		<?php
	}

	/** Creates an explicit draft copy and opens it for editing. */
	public function duplicate(): void {
		$preset_id = isset( $_GET['preset_id'] ) ? absint( $_GET['preset_id'] ) : 0;

		if (
			$preset_id < 1
			|| ! current_user_can( 'edit_post', $preset_id )
			|| ! current_user_can( 'create_tpeu_embeds' )
		) {
			wp_die( esc_html__( 'You are not allowed to duplicate this preset.', 'turnierplan-eu' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'tpeu_duplicate_preset_' . $preset_id );
		$source = get_post( $preset_id );
		$config = get_post_meta( $preset_id, PresetRepository::META_KEY, true );

		if ( ! $source instanceof WP_Post || PresetRepository::POST_TYPE !== $source->post_type || ! is_array( $config ) ) {
			wp_die( esc_html__( 'The preset is unavailable.', 'turnierplan-eu' ), '', array( 'response' => 404 ) );
		}

		try {
			$config = EmbedConfig::from_array( $config )->to_array();
		} catch ( ConfigException ) {
			wp_die( esc_html__( 'The preset does not contain a valid configuration.', 'turnierplan-eu' ), '', array( 'response' => 400 ) );
		}

		$copy_id = wp_insert_post(
			array(
				'post_type'   => PresetRepository::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => sprintf(
					/* translators: %s: source preset title. */
					esc_html__( '%s – Copy', 'turnierplan-eu' ),
					$source->post_title
				),
			),
			true
		);

		if ( is_wp_error( $copy_id ) ) {
			wp_die( esc_html__( 'The preset could not be duplicated.', 'turnierplan-eu' ), '', array( 'response' => 500 ) );
		}

		update_post_meta( $copy_id, PresetRepository::META_KEY, $config );
		$edit_link = get_edit_post_link( $copy_id, 'raw' );

		if ( ! is_string( $edit_link ) ) {
			wp_die( esc_html__( 'The new draft could not be opened.', 'turnierplan-eu' ), '', array( 'response' => 500 ) );
		}

		wp_safe_redirect( $edit_link );
		exit;
	}

	/** Loads editor assets only on the preset edit screens. */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || PresetRepository::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$asset = $this->asset();
		wp_enqueue_script(
			'tpeu-preset-editor',
			plugin_dir_url( $this->plugin_file ) . 'build/preset-editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations(
			'tpeu-preset-editor',
			'turnierplan-eu',
			plugin_dir_path( $this->plugin_file ) . 'languages'
		);
		wp_add_inline_script(
			'tpeu-preset-editor',
			'window.TurnierplanEUPresetSettings = ' . wp_json_encode(
				array(
					'setupDefaults'  => $this->settings->get_setup_defaults(),
					'serviceEnabled' => $this->settings->is_service_enabled(),
				)
			) . ';',
			'before'
		);
		wp_enqueue_style(
			'tpeu-preset-editor',
			plugin_dir_url( $this->plugin_file ) . 'assets/css/preset-editor.css',
			array( 'wp-components' ),
			$this->plugin_version
		);
	}

	/** Validates and persists the complete configuration object. */
	public function save( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if (
			wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| ! current_user_can( 'edit_post', $post_id )
			|| ! isset( $_POST['tpeu_preset_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tpeu_preset_nonce'] ) ), 'tpeu_save_preset_' . $post_id )
		) {
			return;
		}

		$raw  = isset( $_POST['tpeu_preset_config'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tpeu_preset_config'] ) ) : '';
		$data = json_decode( $raw, true );

		try {
			if ( ! is_array( $data ) ) {
				throw ConfigException::for_field( 'config', 'Preset configuration must be an object.' );
			}

			$config = EmbedConfig::from_array( $data );
			update_post_meta( $post_id, PresetRepository::META_KEY, $config->to_array() );
		} catch ( ConfigException ) {
			delete_post_meta( $post_id, PresetRepository::META_KEY );
			TransientRegistry::set( 'tpeu_preset_error_' . get_current_user_id(), 1, 60 );

			if ( 'publish' === $post->post_status ) {
				remove_action( 'save_post_' . PresetRepository::POST_TYPE, array( $this, 'save' ), 10 );
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
				add_action( 'save_post_' . PresetRepository::POST_TYPE, array( $this, 'save' ), 10, 3 );
			}
		}
	}

	/** Adds a one-time query notice after an invalid save. */
	public function add_notice( string $location, int $post_id ): string {
		if (
			PresetRepository::POST_TYPE === get_post_type( $post_id )
			&& get_transient( 'tpeu_preset_error_' . get_current_user_id() )
		) {
			TransientRegistry::delete( 'tpeu_preset_error_' . get_current_user_id() );
			return add_query_arg( 'tpeu_preset_error', '1', $location );
		}

		return $location;
	}

	/**
	 * Loads the generated dependency manifest with safe fallbacks.
	 *
	 * @return array{dependencies:list<non-empty-string>,version:string}
	 */
	private function asset(): array {
		$fallback = array(
			'dependencies' => array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			'version'      => $this->plugin_version,
		);
		$file     = plugin_dir_path( $this->plugin_file ) . 'build/preset-editor.asset.php';

		if ( ! is_readable( $file ) ) {
			return $fallback;
		}

		$asset = require $file;

		if ( ! is_array( $asset ) ) {
			return $fallback;
		}

		$dependencies = array();

		if ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) {
			foreach ( $asset['dependencies'] as $dependency ) {
				if ( is_string( $dependency ) && '' !== $dependency ) {
					$dependencies[] = $dependency;
				}
			}
		}

		return array(
			'dependencies' => array() === $dependencies ? $fallback['dependencies'] : $dependencies,
			'version'      => isset( $asset['version'] ) && is_string( $asset['version'] )
				? $asset['version']
				: $this->plugin_version,
		);
	}
}
