<?php
/**
 * Assertions executed against the extracted release ZIP.
 *
 * @package TurnierplanEU
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

$tpeu_wordpress_loader = '/wordpress/wp-load.php';

if ( ! is_readable( $tpeu_wordpress_loader ) ) {
	throw new RuntimeException( 'WordPress bootstrap is unavailable in Playground.' );
}

require_once $tpeu_wordpress_loader;

if ( ! defined( 'TPEU_VERSION' ) ) {
	throw new RuntimeException( 'The release ZIP did not load its version constant.' );
}

$tpeu_release_headers = get_file_data(
	WP_PLUGIN_DIR . '/turnierplan-eu/turnierplan-eu.php',
	array( 'version' => 'Version' )
);

if ( '1.0.0' !== $tpeu_release_headers['version'] ) {
	throw new RuntimeException( 'The release ZIP does not expose version 1.0.0.' );
}

if ( ! shortcode_exists( 'turnierplan' ) ) {
	throw new RuntimeException( 'The release ZIP did not register the shortcode.' );
}

if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'turnierplan-eu/embed' ) ) {
	throw new RuntimeException( 'The release ZIP did not register the block.' );
}

if ( ( new TurnierplanEU\WordPress\Settings\SettingsRepository() )->is_service_enabled() ) {
	throw new RuntimeException( 'The external service was enabled during fresh installation.' );
}

$tpeu_disabled_output = do_shortcode( '[turnierplan tournament="400" view="standings"]' );

if ( str_contains( $tpeu_disabled_output, '<iframe' ) ) {
	throw new RuntimeException( 'A fresh installation rendered a frame before service approval.' );
}

if ( ! is_readable( WP_PLUGIN_DIR . '/turnierplan-eu/build/block-editor.js' ) ) {
	throw new RuntimeException( 'The release ZIP is missing compiled editor assets.' );
}
