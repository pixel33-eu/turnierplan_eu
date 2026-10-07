<?php
/**
 * Dynamic Gutenberg embed block.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Block;

use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;
use TurnierplanEU\WordPress\Render\EmbedRenderer;
use TurnierplanEU\WordPress\Settings\SettingsRepository;
use TurnierplanEU\WordPress\Preset\PresetResolver;

/**
 * Registers the metadata-defined block and delegates all public markup.
 */
final class EmbedBlock {

	/** Creates the dynamic block adapter. */
	public function __construct(
		private readonly EmbedRenderer $renderer,
		private readonly PresetResolver $presets,
		private readonly SettingsRepository $settings,
		private readonly string $plugin_file,
		private readonly string $plugin_version
	) {
	}

	/** Registers block assets and metadata during WordPress initialization. */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/** Registers the editor-only assets and dynamic block type. */
	public function register_block(): void {
		$this->register_editor_assets();

		register_block_type(
			plugin_dir_path( $this->plugin_file ) . 'blocks/embed',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Validates saved block attributes and returns shared renderer markup.
	 *
	 * @param array<string,mixed> $attributes Parsed block attributes.
	 * @return string Rendered block markup or an empty string for an unconfigured block.
	 */
	public function render( array $attributes ): string {
		$preset_id = $attributes['presetId'] ?? 0;

		if ( is_int( $preset_id ) && $preset_id > 0 ) {
			$preset = $this->presets->resolve_published( $preset_id );

			return null === $preset
				? $this->renderer->render_preset_unavailable()
				: $this->renderer->render( $preset->get_config(), $preset->get_title() );
		}

		$input = $attributes['config'] ?? null;

		if (
			! is_array( $input )
			|| ! isset( $input['tournamentRef'] )
			|| ! is_string( $input['tournamentRef'] )
			|| '' === trim( $input['tournamentRef'] )
		) {
			return '';
		}

		try {
			$config = EmbedConfig::from_array( $input );
		} catch ( ConfigException ) {
			return $this->renderer->render_invalid();
		}

		return $this->renderer->render( $config );
	}

	/** Registers local assets that WordPress loads only inside the block editor. */
	private function register_editor_assets(): void {
		$plugin_url = plugin_dir_url( $this->plugin_file );
		$asset      = $this->editor_asset();

		wp_register_script(
			'tpeu-block-editor',
			$plugin_url . 'build/block-editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'tpeu-block-editor', 'turnierplan-eu' );
		wp_add_inline_script(
			'tpeu-block-editor',
			'window.TurnierplanEUBlockSettings = ' . wp_json_encode(
				array(
					'serviceEnabled' => $this->settings->is_service_enabled(),
					'setupDefaults'  => $this->settings->get_setup_defaults(),
				)
			) . ';',
			'before'
		);

		wp_register_style(
			'tpeu-block-editor',
			$plugin_url . 'assets/css/block-editor.css',
			array( 'wp-edit-blocks' ),
			$this->plugin_version
		);
	}

	/**
	 * Loads the generated editor dependency manifest with fixed safe fallbacks.
	 *
	 * @return array{dependencies:list<non-empty-string>,version:string}
	 */
	private function editor_asset(): array {
		$fallback = array(
			'dependencies' => array(
				'wp-api-fetch',
				'wp-block-editor',
				'wp-blocks',
				'wp-components',
				'wp-element',
				'wp-i18n',
				'wp-server-side-render',
			),
			'version'      => $this->plugin_version,
		);
		$file     = plugin_dir_path( $this->plugin_file ) . 'build/block-editor.asset.php';

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
