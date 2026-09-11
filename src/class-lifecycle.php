<?php
/**
 * Activation and deactivation behavior.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress;

/**
 * Owns the plugin lifecycle hooks.
 */
final class Lifecycle {

	/**
	 * Validates requirements and records the installed plugin version.
	 *
	 * This method creates no sample data and performs no remote request.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$wordpress_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '0';
		$failures          = Requirements::unmet_requirements( PHP_VERSION, $wordpress_version );

		if ( array() !== $failures ) {
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( plugin_basename( TPEU_PLUGIN_FILE ) );
			}

			wp_die(
				esc_html( Requirements::get_error_message( $failures ) ),
				esc_html__( 'Plugin-Aktivierung fehlgeschlagen', 'turnierplan-eu' ),
				array(
					'back_link' => true,
					'response'  => 500,
				)
			);
		}

		update_option( 'tpeu_plugin_version', TPEU_VERSION, false );
	}

	/**
	 * Leaves stored user configuration untouched on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Reserved for reversible shutdown tasks such as clearing scheduled hooks.
	}
}
