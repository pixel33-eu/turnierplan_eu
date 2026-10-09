<?php
/**
 * Activation and deactivation behavior.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress;

use TurnierplanEU\WordPress\Preset\Capabilities;
use WP_Site;

/**
 * Owns the plugin lifecycle hooks.
 */
final class Lifecycle {
	public const DATABASE_VERSION        = 1;
	public const DATABASE_VERSION_OPTION = 'tpeu_db_version';
	public const PLUGIN_VERSION_OPTION   = 'tpeu_plugin_version';

	/**
	 * Plugin-owned scheduled hook names.
	 *
	 * @var list<string>
	 */
	private const CRON_HOOKS = array( 'tpeu_metadata_cache_cleanup' );

	/**
	 * Validates requirements and installs the plugin for the selected sites.
	 *
	 * This method creates no sample data and performs no remote request.
	 *
	 * @param bool $network_wide Whether WordPress is activating the plugin for the network.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		$wordpress_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '0';
		$failures          = Requirements::unmet_requirements( PHP_VERSION, $wordpress_version );

		if ( array() !== $failures ) {
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( plugin_basename( TPEU_PLUGIN_FILE ) );
			}

			wp_die(
				esc_html( Requirements::get_error_message( $failures ) ),
				esc_html__( 'Plugin activation failed', 'turnierplan-eu' ),
				array(
					'back_link' => true,
					'response'  => 500,
				)
			);
		}

		if ( is_multisite() && $network_wide ) {
			self::for_each_site( array( self::class, 'install_site' ) );
			return;
		}

		self::install_site();
	}

	/** Installs or upgrades the current site's exact plugin state. */
	public static function install_site(): void {
		update_option( self::PLUGIN_VERSION_OPTION, TPEU_VERSION, false );
		update_option( self::DATABASE_VERSION_OPTION, self::DATABASE_VERSION, false );
		Capabilities::install();
	}

	/** Applies idempotent per-site upgrades after an ordinary plugin update. */
	public static function maybe_upgrade(): void {
		if (
			TPEU_VERSION !== get_option( self::PLUGIN_VERSION_OPTION )
			|| self::DATABASE_VERSION !== (int) get_option( self::DATABASE_VERSION_OPTION, 0 )
			|| Capabilities::VERSION !== (int) get_option( Capabilities::VERSION_OPTION, 0 )
		) {
			self::install_site();
		}
	}

	/**
	 * Initializes a site created after network activation.
	 *
	 * @param WP_Site $site Newly initialized site.
	 */
	public static function initialize_site( WP_Site $site ): void {
		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		if (
			! is_multisite()
			|| ! is_array( $network_plugins )
			|| ! isset( $network_plugins[ plugin_basename( TPEU_PLUGIN_FILE ) ] )
		) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );

		try {
			self::install_site();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Leaves stored user configuration untouched on deactivation.
	 *
	 * @param bool $network_wide Whether WordPress is deactivating the plugin for the network.
	 * @return void
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			self::for_each_site( array( self::class, 'clear_scheduled_hooks' ) );
			return;
		}

		self::clear_scheduled_hooks();
	}

	/** Removes only plugin-owned scheduled events for the current site. */
	public static function clear_scheduled_hooks(): void {
		foreach ( self::CRON_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Runs one callback in every existing site without leaking blog context.
	 *
	 * @param callable():void $callback Site-local operation.
	 */
	private static function for_each_site( callable $callback ): void {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );

			try {
				$callback();
			} finally {
				restore_current_blog();
			}
		}
	}
}
