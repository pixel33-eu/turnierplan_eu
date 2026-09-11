<?php
/**
 * Plugin runtime root.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress;

/**
 * Starts feature registration after all active plugins are loaded.
 */
final class Plugin {

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Creates the runtime root.
	 *
	 * @param string $version Current plugin version.
	 */
	public function __construct( string $version ) {
		$this->version = $version;
	}

	/**
	 * Registers the plugin runtime.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'announce_loaded' ) );
	}

	/**
	 * Announces that the plugin is ready for feature registration.
	 *
	 * @return void
	 */
	public function announce_loaded(): void {
		/**
		 * Fires after the Turnierplan.eu runtime has passed its requirement checks.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin $plugin Runtime root instance.
		 */
		do_action( 'tpeu_loaded', $this );
	}

	/**
	 * Returns the current plugin version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}
}
