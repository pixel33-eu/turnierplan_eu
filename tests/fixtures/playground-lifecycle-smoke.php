<?php
/**
 * WordPress Playground assertions for the single-site lifecycle.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

$tpeu_wordpress_loader = '/wordpress/wp-load.php';

if ( ! is_readable( $tpeu_wordpress_loader ) ) {
	throw new RuntimeException( 'WordPress bootstrap is unavailable in Playground.' );
}

require_once $tpeu_wordpress_loader;
require_once __DIR__ . '/playground-state.php';

$tpeu_admin = get_user_by( 'login', 'admin' );

if ( false === $tpeu_admin ) {
	throw new RuntimeException( 'Playground administrator is unavailable.' );
}

if (
	TPEU_VERSION !== tpeu_test_option( TurnierplanEU\WordPress\Lifecycle::PLUGIN_VERSION_OPTION )
	|| ! tpeu_test_database_version_is_current()
) {
	throw new RuntimeException( 'Activation did not install the lifecycle versions.' );
}

$tpeu_lifecycle_preset = wp_insert_post(
	array(
		'post_type'   => TurnierplanEU\WordPress\Preset\PresetRepository::POST_TYPE,
		'post_status' => 'draft',
		'post_title'  => 'Lifecycle fixture',
		'post_author' => $tpeu_admin->ID,
	),
	true
);

if ( is_wp_error( $tpeu_lifecycle_preset ) ) {
	throw new RuntimeException( 'Lifecycle preset fixture could not be created.' );
}

update_option( TurnierplanEU\WordPress\Lifecycle::PLUGIN_VERSION_OPTION, '0.0.0', false );
update_option( TurnierplanEU\WordPress\Lifecycle::DATABASE_VERSION_OPTION, 0, false );
update_option( TurnierplanEU\WordPress\Preset\Capabilities::VERSION_OPTION, 0, false );
TurnierplanEU\WordPress\Lifecycle::maybe_upgrade();

if (
	TPEU_VERSION !== tpeu_test_option( TurnierplanEU\WordPress\Lifecycle::PLUGIN_VERSION_OPTION )
	|| ! tpeu_test_database_version_is_current()
	|| null === tpeu_test_post( $tpeu_lifecycle_preset )
) {
	throw new RuntimeException( 'The idempotent lifecycle migration changed user data or missed a version.' );
}

wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'tpeu_metadata_cache_cleanup' );
wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'neighbor_cleanup_event' );
TurnierplanEU\WordPress\Lifecycle::deactivate( false );

if (
	false !== tpeu_test_scheduled( 'tpeu_metadata_cache_cleanup' )
	|| false === tpeu_test_scheduled( 'neighbor_cleanup_event' )
	|| null === tpeu_test_post( $tpeu_lifecycle_preset )
) {
	throw new RuntimeException( 'Deactivation did not preserve data or isolate scheduled events.' );
}

update_option(
	TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME,
	TurnierplanEU\WordPress\Settings\SettingsRepository::defaults(),
	false
);
TurnierplanEU\WordPress\Uninstaller::cleanup_current_site();

if (
	null === tpeu_test_post( $tpeu_lifecycle_preset )
	|| false === tpeu_test_option( TurnierplanEU\WordPress\Lifecycle::PLUGIN_VERSION_OPTION, false )
	|| ! tpeu_test_role_has_cap( 'administrator', TurnierplanEU\WordPress\Preset\Capabilities::MANAGE_SETTINGS )
) {
	throw new RuntimeException( 'Default uninstall policy removed plugin data without opt-in.' );
}

$tpeu_delete_settings                = TurnierplanEU\WordPress\Settings\SettingsRepository::defaults();
$tpeu_delete_settings['delete_data'] = true;
update_option( TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME, $tpeu_delete_settings, false );
update_option( 'neighbor_setting', 'keep', false );
TurnierplanEU\WordPress\Cache\TransientRegistry::set( 'tpeu_limit_fixture', 'remove', HOUR_IN_SECONDS );
set_transient( 'neighbor_cache', 'keep', HOUR_IN_SECONDS );
( new TurnierplanEU\WordPress\Cache\WordPressCacheStore() )->set( 'lifecycle_fixture', array( 'remove' ), HOUR_IN_SECONDS );
wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'tpeu_metadata_cache_cleanup' );
TurnierplanEU\WordPress\Uninstaller::cleanup_current_site();

if (
	null !== tpeu_test_post( $tpeu_lifecycle_preset )
	|| false !== tpeu_test_option( TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME, false )
	|| false !== tpeu_test_option( TurnierplanEU\WordPress\Lifecycle::PLUGIN_VERSION_OPTION, false )
	|| false !== tpeu_test_transient( 'tpeu_limit_fixture' )
	|| false !== tpeu_test_cache_value( 'lifecycle_fixture' )
	|| false !== tpeu_test_scheduled( 'tpeu_metadata_cache_cleanup' )
	|| tpeu_test_role_has_cap( 'administrator', TurnierplanEU\WordPress\Preset\Capabilities::MANAGE_SETTINGS )
) {
	throw new RuntimeException( 'Opt-in uninstall did not remove all exact plugin-owned data.' );
}

if (
	'keep' !== tpeu_test_option( 'neighbor_setting' )
	|| 'keep' !== tpeu_test_transient( 'neighbor_cache' )
	|| false === tpeu_test_scheduled( 'neighbor_cleanup_event' )
) {
	throw new RuntimeException( 'Opt-in uninstall removed unrelated site data.' );
}
