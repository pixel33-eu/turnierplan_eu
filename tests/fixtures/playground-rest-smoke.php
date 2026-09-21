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

if ( ! shortcode_exists( 'turnierplan' ) ) {
	throw new RuntimeException( 'The Turnierplan.eu shortcode was not registered.' );
}

$tpeu_https_home = static function ( string $url ): string {
	unset( $url );

	return 'https://verein.example/';
};

add_filter( 'home_url', $tpeu_https_home );
update_option(
	TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME,
	array(
		'service_enabled' => true,
		'language'        => 'auto',
		'theme'           => 'auto',
		'density'         => 'comfortable',
		'delete_data'     => false,
	),
	false
);

$tpeu_shortcode     = '[turnierplan tournament="123" view="matches" min_height="300" unknown="https://evil.example"]';
$tpeu_http_requests = 0;
$tpeu_http_guard    = static function ( mixed $preempt ) use ( &$tpeu_http_requests ): WP_Error {
	unset( $preempt );
	++$tpeu_http_requests;

	return new WP_Error( 'unexpected_frontend_http_request', 'Frontend rendering must not use WordPress HTTP.' );
};
add_filter( 'pre_http_request', $tpeu_http_guard );
$tpeu_first_html  = do_shortcode( $tpeu_shortcode );
$tpeu_second_html = do_shortcode( $tpeu_shortcode );
remove_filter( 'pre_http_request', $tpeu_http_guard );

if (
	! str_contains( $tpeu_first_html, '<iframe ' )
	|| ! str_contains( $tpeu_first_html, 'width="100%"' )
	|| ! str_contains( $tpeu_first_html, 'title="Spielplan: Turnier 123"' )
	|| ! str_contains( $tpeu_first_html, 'loading="lazy"' )
	|| ! str_contains( $tpeu_first_html, 'referrerpolicy="strict-origin-when-cross-origin"' )
	|| ! str_contains( $tpeu_first_html, 'sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"' )
	|| ! str_contains( $tpeu_first_html, 'https://www.turnierplan.eu/embed/v1/tournaments/123' )
	|| ! str_contains( $tpeu_first_html, 'parent_origin=https%3A%2F%2Fverein.example' )
	|| ! str_contains( $tpeu_first_html, 'Turnier vollständig auf Turnierplan.eu öffnen' )
	|| str_contains( $tpeu_first_html, 'evil.example' )
) {
	throw new RuntimeException( 'The shortcode did not render the secured shared iframe markup.' );
}

if ( 0 !== $tpeu_http_requests ) {
	throw new RuntimeException( 'Normal frontend rendering triggered a WordPress HTTP request.' );
}

preg_match( '/data-tpeu-instance="([^"]+)"/', $tpeu_first_html, $tpeu_first_instance );
preg_match( '/data-tpeu-instance="([^"]+)"/', $tpeu_second_html, $tpeu_second_instance );

if (
	! isset( $tpeu_first_instance[1], $tpeu_second_instance[1] )
	|| $tpeu_first_instance[1] === $tpeu_second_instance[1]
) {
	throw new RuntimeException( 'Multiple embeds did not receive unique instance IDs.' );
}

if ( ! wp_script_is( 'tpeu-embed', 'enqueued' ) || ! wp_style_is( 'tpeu-embed', 'enqueued' ) ) {
	throw new RuntimeException( 'Frontend assets were not enqueued for an actual embed.' );
}

if ( str_contains( do_shortcode( '[turnierplan view="standings"]' ), '<iframe ' ) ) {
	throw new RuntimeException( 'An invalid shortcode rendered an iframe.' );
}

wp_dequeue_script( 'tpeu-embed' );
wp_dequeue_style( 'tpeu-embed' );
update_option(
	TurnierplanEU\WordPress\Settings\SettingsRepository::OPTION_NAME,
	TurnierplanEU\WordPress\Settings\SettingsRepository::defaults(),
	false
);

$tpeu_disabled_html = do_shortcode( '[turnierplan tournament="123" view="standings"]' );

if (
	str_contains( $tpeu_disabled_html, '<iframe ' )
	|| in_array( 'tpeu-embed', wp_scripts()->queue, true )
	|| in_array( 'tpeu-embed', wp_styles()->queue, true )
) {
	throw new RuntimeException( 'Revoked service approval still rendered or enqueued an embed.' );
}

remove_filter( 'home_url', $tpeu_https_home );
