<?php
/**
 * WordPress Playground assertions for protected Block 9 routes.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

$tpeu_wordpress_loader = '/wordpress/wp-load.php';

if ( ! is_readable( $tpeu_wordpress_loader ) ) {
	throw new RuntimeException( 'WordPress bootstrap is unavailable in Playground.' );
}

require_once $tpeu_wordpress_loader;

$tpeu_server = rest_get_server();
$tpeu_routes = $tpeu_server->get_routes();

if (
	! isset( $tpeu_routes['/turnierplan-eu/v1/metadata/(?P<reference>[A-Za-z0-9_-]{1,104})'] )
	|| ! isset( $tpeu_routes['/turnierplan-eu/v1/cache/refresh'] )
) {
	throw new RuntimeException( 'Turnierplan.eu REST routes were not registered.' );
}

wp_set_current_user( 0 );
$tpeu_guest_request  = new WP_REST_Request( 'GET', '/turnierplan-eu/v1/metadata/123' );
$tpeu_guest_response = $tpeu_server->dispatch( $tpeu_guest_request );

if ( 403 !== $tpeu_guest_response->get_status() ) {
	throw new RuntimeException( 'Guest metadata proxy request was not denied.' );
}

$tpeu_admin = get_user_by( 'login', 'admin' );

if ( false === $tpeu_admin ) {
	throw new RuntimeException( 'Playground administrator is unavailable.' );
}

wp_set_current_user( $tpeu_admin->ID );
$tpeu_admin_request  = new WP_REST_Request( 'GET', '/turnierplan-eu/v1/metadata/123' );
$tpeu_admin_response = $tpeu_server->dispatch( $tpeu_admin_request );

if ( 403 !== $tpeu_admin_response->get_status() ) {
	throw new RuntimeException( 'Nonce-less administrator metadata request was not denied.' );
}

if ( ( new TurnierplanEU\WordPress\Settings\SettingsRepository() )->is_service_enabled() ) {
	throw new RuntimeException( 'The external service was enabled without administrator approval.' );
}
