<?php
/**
 * WordPress frontend asset adapter.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Render;

/**
 * Enqueues only local files and only after a renderer emits an embed.
 */
final class WordPressFrontendAssets implements FrontendAssetLoader {

	/** Creates the versioned asset adapter. */
	public function __construct(
		private readonly string $plugin_file,
		private readonly string $plugin_version
	) {
	}

	/** Enqueues the shared frontend stylesheet and deferred script. */
	public function enqueue(): void {
		$asset        = $this->script_asset();
		$dependencies = $asset['dependencies'];
		$version      = $asset['version'];

		wp_enqueue_style(
			'tpeu-embed',
			plugin_dir_url( $this->plugin_file ) . 'assets/css/embed.css',
			array(),
			$this->plugin_version
		);
		wp_enqueue_script(
			'tpeu-embed',
			plugin_dir_url( $this->plugin_file ) . 'build/index.js',
			$dependencies,
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Reads the generated dependency manifest when it is available.
	 *
	 * @return array{dependencies:list<non-empty-string>,version:string} Asset data.
	 */
	private function script_asset(): array {
		$file = plugin_dir_path( $this->plugin_file ) . 'build/index.asset.php';

		if ( ! is_readable( $file ) ) {
			return array(
				'dependencies' => array(),
				'version'      => $this->plugin_version,
			);
		}

		$asset = require $file;

		if ( ! is_array( $asset ) ) {
			return array(
				'dependencies' => array(),
				'version'      => $this->plugin_version,
			);
		}

		$dependencies = array();

		if ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) {
			foreach ( $asset['dependencies'] as $dependency ) {
				if ( is_string( $dependency ) && '' !== $dependency ) {
					$dependencies[] = $dependency;
				}
			}
		}

		$version = isset( $asset['version'] ) && is_string( $asset['version'] )
			? $asset['version']
			: $this->plugin_version;

		return array(
			'dependencies' => $dependencies,
			'version'      => $version,
		);
	}
}
