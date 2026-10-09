<?php
/**
 * WordPress Playground assertions for the site-aware lifecycle.
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
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';

if ( ! is_multisite() ) {
	throw new RuntimeException( 'The lifecycle fixture is not running in multisite mode.' );
}

$tpeu_activation = activate_plugin( 'turnierplan-eu/turnierplan-eu.php', '', true );

if ( is_wp_error( $tpeu_activation ) ) {
	throw new RuntimeException( 'Network activation failed: ' . esc_html( $tpeu_activation->get_error_message() ) );
}

if ( ! is_plugin_active_for_network( 'turnierplan-eu/turnierplan-eu.php' ) ) {
	throw new RuntimeException( 'The plugin was not marked active for the network.' );
}

$tpeu_main_site_id = get_current_blog_id();

if (
	TPEU_VERSION !== tpeu_test_option( TurnierplanEU\WordPress\Lifecycle::PLUGIN_VERSION_OPTION )
	|| ! tpeu_test_database_version_is_current()
	|| ! tpeu_test_role_has_cap( 'administrator', TurnierplanEU\WordPress\Preset\Capabilities::MANAGE_SETTINGS )
) {
	throw new RuntimeException( 'Network activation did not initialize the existing main site.' );
}

$tpeu_main_settings                    = TurnierplanEU\WordPress\Settings\SettingsRepository::defaults();
$tpeu_main_settings['language']        = 'de';
$tpeu_main_settings['service_enabled'] = true;
update_option( TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME, $tpeu_main_settings, false );
( new TurnierplanEU\WordPress\Cache\WordPressCacheStore() )->set( 'shared_key', 'main-site', HOUR_IN_SECONDS );

$tpeu_main_preset = wp_insert_post(
	array(
		'post_type'   => TurnierplanEU\WordPress\Preset\PresetRepository::POST_TYPE,
		'post_status' => 'draft',
		'post_title'  => 'Main site preset',
	),
	true
);

if ( is_wp_error( $tpeu_main_preset ) ) {
	throw new RuntimeException( 'The main-site preset fixture could not be created.' );
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$tpeu_second_site_id    = wpmu_create_blog(
	(string) wp_parse_url( network_home_url(), PHP_URL_HOST ),
	'/second/',
	'Second lifecycle site',
	1
);

if ( is_wp_error( $tpeu_second_site_id ) ) {
	throw new RuntimeException( 'The second site could not be created: ' . esc_html( $tpeu_second_site_id->get_error_message() ) );
}

switch_to_blog( (int) $tpeu_second_site_id );

if (
	TPEU_VERSION !== tpeu_test_option( TurnierplanEU\WordPress\Lifecycle::PLUGIN_VERSION_OPTION )
	|| ! tpeu_test_database_version_is_current()
	|| false !== tpeu_test_option( TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME, false )
	|| ! tpeu_test_role_has_cap( 'administrator', TurnierplanEU\WordPress\Preset\Capabilities::MANAGE_SETTINGS )
) {
	throw new RuntimeException( 'A site created after network activation was not initialized independently.' );
}

$tpeu_second_cache = new TurnierplanEU\WordPress\Cache\WordPressCacheStore();
$tpeu_second_cache->set( 'shared_key', 'second-site', HOUR_IN_SECONDS );

if ( 'second-site' !== tpeu_test_cache_value( 'shared_key' ) ) {
	throw new RuntimeException( 'The second site did not retain its own cache value.' );
}

$tpeu_second_settings                = TurnierplanEU\WordPress\Settings\SettingsRepository::defaults();
$tpeu_second_settings['delete_data'] = true;
update_option( TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME, $tpeu_second_settings, false );
update_option( 'neighbor_multisite_setting', 'keep', false );

$tpeu_second_preset = wp_insert_post(
	array(
		'post_type'   => TurnierplanEU\WordPress\Preset\PresetRepository::POST_TYPE,
		'post_status' => 'draft',
		'post_title'  => 'Second site preset',
	),
	true
);

if ( is_wp_error( $tpeu_second_preset ) ) {
	throw new RuntimeException( 'The second-site preset fixture could not be created.' );
}

restore_current_blog();

if ( tpeu_test_current_blog_id() !== $tpeu_main_site_id ) {
	throw new RuntimeException( 'Site switching did not restore the main blog context.' );
}

$tpeu_main_cache_value = tpeu_test_cache_value( 'shared_key' );

if ( 'second-site' === $tpeu_main_cache_value ) {
	throw new RuntimeException( 'Site switching leaked the second site cache value into the main site.' );
}

( new TurnierplanEU\WordPress\Cache\WordPressCacheStore() )->set( 'shared_key', 'main-site', HOUR_IN_SECONDS );

if ( 'main-site' !== tpeu_test_cache_value( 'shared_key' ) ) {
	throw new RuntimeException( 'The main site cache could not be restored after site switching.' );
}

TurnierplanEU\WordPress\Uninstaller::run();

if (
	'de' !== ( new TurnierplanEU\WordPress\Settings\SettingsRepository() )->get()['language']
	|| null === tpeu_test_post( $tpeu_main_preset )
	|| ! tpeu_test_role_has_cap( 'administrator', TurnierplanEU\WordPress\Preset\Capabilities::MANAGE_SETTINGS )
) {
	throw new RuntimeException( 'Multisite uninstall ignored the main site retention decision.' );
}

switch_to_blog( (int) $tpeu_second_site_id );

if (
	null !== tpeu_test_post( $tpeu_second_preset )
	|| false !== tpeu_test_option( TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME, false )
	|| false !== tpeu_test_option( TurnierplanEU\WordPress\Lifecycle::PLUGIN_VERSION_OPTION, false )
	|| false !== tpeu_test_cache_value( 'shared_key' )
	|| tpeu_test_role_has_cap( 'administrator', TurnierplanEU\WordPress\Preset\Capabilities::MANAGE_SETTINGS )
	|| 'keep' !== tpeu_test_option( 'neighbor_multisite_setting' )
) {
	throw new RuntimeException( 'Multisite uninstall did not apply the second site opt-in in isolation.' );
}

restore_current_blog();

if ( tpeu_test_current_blog_id() !== $tpeu_main_site_id ) {
	throw new RuntimeException( 'Multisite uninstall leaked the switched blog context.' );
}
