<?php
/**
 * Opt-in plugin data removal.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress;

use TurnierplanEU\WordPress\Cache\WordPressCacheStore;
use TurnierplanEU\WordPress\Cache\TransientRegistry;
use TurnierplanEU\WordPress\Preset\Capabilities;
use TurnierplanEU\WordPress\Preset\PresetRepository;
use TurnierplanEU\WordPress\Settings\SettingsRepository;

/** Removes only known plugin data from sites that explicitly opted in. */
final class Uninstaller {

	/** Executes the site-aware uninstall policy. */
	public static function run(): void {
		if ( is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );

				try {
					self::cleanup_current_site();
				} finally {
					restore_current_blog();
				}
			}

			return;
		}

		self::cleanup_current_site();
	}

	/** Applies the saved opt-in decision to the current site. */
	public static function cleanup_current_site(): void {
		$settings = ( new SettingsRepository() )->get();

		if ( ! $settings['delete_data'] ) {
			return;
		}

		self::delete_presets();
		( new WordPressCacheStore() )->clear();
		TransientRegistry::clear();
		Lifecycle::clear_scheduled_hooks();
		Capabilities::remove();

		foreach ( self::option_names() as $option_name ) {
			delete_option( $option_name );
		}
	}

	/** Deletes every post with the exact private preset post type. */
	private static function delete_presets(): void {
		$preset_ids = get_posts(
			array(
				'fields'           => 'ids',
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit' ),
				'post_type'        => PresetRepository::POST_TYPE,
				'suppress_filters' => true,
			)
		);

		foreach ( $preset_ids as $preset_id ) {
			wp_delete_post( (int) $preset_id, true );
		}
	}

	/**
	 * Returns exact site option names owned by this plugin.
	 *
	 * @return list<string>
	 */
	private static function option_names(): array {
		return array(
			SettingsRepository::OPTION_NAME,
			Lifecycle::PLUGIN_VERSION_OPTION,
			Lifecycle::DATABASE_VERSION_OPTION,
			Capabilities::VERSION_OPTION,
			'tpeu_metadata_cache_keys',
			TransientRegistry::INDEX_OPTION,
		);
	}
}
