<?php
/**
 * Plugin bootstrap orchestration.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress;

/**
 * Connects the plugin lifecycle to WordPress.
 */
final class Bootstrap {

	/**
	 * Registers lifecycle hooks and starts the runtime.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @param string $version     Current plugin version.
	 * @return void
	 */
	public static function boot( string $plugin_file, string $version ): void {
		register_activation_hook( $plugin_file, array( Lifecycle::class, 'activate' ) );
		register_deactivation_hook( $plugin_file, array( Lifecycle::class, 'deactivate' ) );

		$wordpress_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '0';
		$failures          = Requirements::unmet_requirements( PHP_VERSION, $wordpress_version );

		if ( array() !== $failures ) {
			add_action(
				'admin_notices',
				static function () use ( $failures ): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html( Requirements::get_error_message( $failures ) )
					);
				}
			);

			return;
		}

		$plugin = new Plugin( $version );
		$plugin->boot();
	}
}
