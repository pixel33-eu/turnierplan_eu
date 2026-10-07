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
	|| ! isset( $tpeu_routes['/turnierplan-eu/v1/metadata/resolve'] )
	|| ! isset( $tpeu_routes['/turnierplan-eu/v1/cache/refresh'] )
	|| ! isset( $tpeu_routes['/turnierplan-eu/v1/presets'] )
	|| ! isset( $tpeu_routes['/turnierplan-eu/v1/presets/(?P<id>[1-9][0-9]*)'] )
) {
	throw new RuntimeException( 'Turnierplan.eu REST routes were not registered.' );
}

wp_set_current_user( 0 );
$tpeu_guest_request  = new WP_REST_Request( 'GET', '/turnierplan-eu/v1/metadata/123' );
$tpeu_guest_response = $tpeu_server->dispatch( $tpeu_guest_request );

if ( 403 !== $tpeu_guest_response->get_status() ) {
	throw new RuntimeException( 'Guest metadata proxy request was not denied.' );
}

$tpeu_guest_presets = $tpeu_server->dispatch( new WP_REST_Request( 'GET', '/turnierplan-eu/v1/presets' ) );

if ( 403 !== $tpeu_guest_presets->get_status() ) {
	throw new RuntimeException( 'Guest preset listing was not denied.' );
}

$tpeu_guest_core_presets = $tpeu_server->dispatch( new WP_REST_Request( 'GET', '/wp/v2/turnierplan-embeds' ) );

if ( $tpeu_guest_core_presets->get_status() < 400 ) {
	throw new RuntimeException( 'Guest core REST preset listing was exposed.' );
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

$tpeu_admin_presets_without_nonce = $tpeu_server->dispatch( new WP_REST_Request( 'GET', '/turnierplan-eu/v1/presets' ) );

if ( 403 !== $tpeu_admin_presets_without_nonce->get_status() ) {
	throw new RuntimeException( 'Nonce-less administrator preset listing was not denied.' );
}

foreach (
	array(
		'administrator' => array( true, true, true, true ),
		'editor'        => array( true, true, false, true ),
		'author'        => array( true, true, false, false ),
	) as $tpeu_role_name => $tpeu_expected_caps
) {
	$tpeu_role = get_role( $tpeu_role_name );

	if (
		null === $tpeu_role
		|| $tpeu_role->has_cap( TurnierplanEU\WordPress\Preset\Capabilities::USE_PRESETS ) !== $tpeu_expected_caps[0]
		|| $tpeu_role->has_cap( 'create_tpeu_embeds' ) !== $tpeu_expected_caps[1]
		|| $tpeu_role->has_cap( TurnierplanEU\WordPress\Preset\Capabilities::MANAGE_SETTINGS ) !== $tpeu_expected_caps[2]
		|| $tpeu_role->has_cap( 'edit_others_tpeu_embeds' ) !== $tpeu_expected_caps[3]
	) {
		throw new RuntimeException( 'Preset role capability matrix is incorrect for ' . esc_html( $tpeu_role_name ) . '.' );
	}
}

if ( ( new TurnierplanEU\WordPress\Settings\SettingsRepository() )->is_service_enabled() ) {
	throw new RuntimeException( 'The external service was enabled without administrator approval.' );
}

$tpeu_resolve_request = new WP_REST_Request( 'POST', '/turnierplan-eu/v1/metadata/resolve' );
$tpeu_resolve_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$tpeu_resolve_request->set_body_params(
	array(
		'reference' => 'https://www.turnierplan.eu/t/sommer-cup-2026',
		'language'  => 'de',
	)
);
$tpeu_resolve_response = $tpeu_server->dispatch( $tpeu_resolve_request );

if ( 403 !== $tpeu_resolve_response->get_status() ) {
	throw new RuntimeException( 'The protected resolver did not accept a service URL before applying service approval.' );
}

$tpeu_foreign_request = new WP_REST_Request( 'POST', '/turnierplan-eu/v1/metadata/resolve' );
$tpeu_foreign_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$tpeu_foreign_request->set_body_params(
	array(
		'reference' => 'https://evil.example/t/123',
		'language'  => 'de',
	)
);
$tpeu_foreign_response = $tpeu_server->dispatch( $tpeu_foreign_request );

if ( 400 !== $tpeu_foreign_response->get_status() ) {
	throw new RuntimeException( 'The protected resolver accepted a foreign tournament URL.' );
}

if ( ! shortcode_exists( 'turnierplan' ) ) {
	throw new RuntimeException( 'The Turnierplan.eu shortcode was not registered.' );
}

$tpeu_block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'turnierplan-eu/embed' );

if (
	null === $tpeu_block_type
	|| 2 !== count( $tpeu_block_type->get_variations() )
	|| ! wp_script_is( 'tpeu-block-editor', 'registered' )
	|| ! wp_style_is( 'tpeu-block-editor', 'registered' )
) {
	throw new RuntimeException( 'The metadata-defined dynamic block was not registered completely.' );
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

$tpeu_preset_id = wp_insert_post(
	array(
		'post_type'   => TurnierplanEU\WordPress\Preset\PresetRepository::POST_TYPE,
		'post_status' => 'publish',
		'post_title'  => 'Vereinsmeisterschaft',
		'post_author' => $tpeu_admin->ID,
	),
	true
);

if ( is_wp_error( $tpeu_preset_id ) ) {
	throw new RuntimeException( 'Published preset fixture could not be created.' );
}

$tpeu_preset_config = TurnierplanEU\WordPress\Config\EmbedConfig::from_array(
	array(
		'tournamentRef' => '123',
		'view'          => 'standings',
	)
)->to_array();
update_post_meta( $tpeu_preset_id, TurnierplanEU\WordPress\Preset\PresetRepository::META_KEY, $tpeu_preset_config );

$tpeu_preset_list_request = new WP_REST_Request( 'GET', '/turnierplan-eu/v1/presets' );
$tpeu_preset_list_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$tpeu_preset_list_response = $tpeu_server->dispatch( $tpeu_preset_list_request );
$tpeu_preset_list_data     = $tpeu_preset_list_response->get_data();

if (
	200 !== $tpeu_preset_list_response->get_status()
	|| ! is_array( $tpeu_preset_list_data )
	|| ! isset( $tpeu_preset_list_data['presets'][0]['id'] )
	|| $tpeu_preset_id !== $tpeu_preset_list_data['presets'][0]['id']
	|| isset( $tpeu_preset_list_data['presets'][0]['author'] )
) {
	throw new RuntimeException( 'Protected preset selector response is incomplete or exposes author data.' );
}

$tpeu_preset_shortcode = sprintf( '[turnierplan preset="%d"]', $tpeu_preset_id );
$tpeu_preset_html      = do_shortcode( $tpeu_preset_shortcode );
$tpeu_preset_block     = render_block(
	array(
		'blockName' => 'turnierplan-eu/embed',
		'attrs'     => array( 'presetId' => $tpeu_preset_id ),
	)
);

if (
	! str_contains( $tpeu_preset_html, '/embed/v1/tournaments/123' )
	|| ! str_contains( $tpeu_preset_block, '/embed/v1/tournaments/123' )
) {
	throw new RuntimeException( 'Published preset shortcode or block did not resolve its exact configuration.' );
}

$tpeu_preset_config['tournamentRef'] = '456';
update_post_meta( $tpeu_preset_id, TurnierplanEU\WordPress\Preset\PresetRepository::META_KEY, $tpeu_preset_config );

if ( ! str_contains( do_shortcode( $tpeu_preset_shortcode ), '/embed/v1/tournaments/456' ) ) {
	throw new RuntimeException( 'Preset changes did not reach existing shortcode use.' );
}

wp_update_post(
	array(
		'ID'          => $tpeu_preset_id,
		'post_status' => 'draft',
	)
);
$tpeu_unpublished_html = do_shortcode( $tpeu_preset_shortcode );
$tpeu_missing_html     = do_shortcode( '[turnierplan preset="999999999"]' );
$tpeu_draft_list       = $tpeu_server->dispatch( $tpeu_preset_list_request )->get_data();

if (
	str_contains( $tpeu_unpublished_html, '<iframe ' )
	|| str_contains( $tpeu_missing_html, '<iframe ' )
	|| ! str_contains( $tpeu_unpublished_html, 'tpeu-embed--unavailable' )
	|| ! str_contains( $tpeu_missing_html, 'tpeu-embed--unavailable' )
	|| ! is_array( $tpeu_draft_list )
	|| array() !== $tpeu_draft_list['presets']
) {
	throw new RuntimeException( 'Missing or unpublished preset did not use the safe exact-ID fallback.' );
}

wp_delete_post( $tpeu_preset_id, true );

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
$tpeu_block_html  = render_block(
	array(
		'blockName' => 'turnierplan-eu/embed',
		'attrs'     => array(
			'config' => array(
				'tournamentRef' => '123',
				'view'          => 'standings',
			),
		),
	)
);
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

if (
	! str_contains( $tpeu_block_html, '<iframe ' )
	|| ! str_contains( $tpeu_block_html, '/embed/v1/tournaments/123' )
	|| '' !== render_block(
		array(
			'blockName' => 'turnierplan-eu/embed',
			'attrs'     => array(),
		)
	)
) {
	throw new RuntimeException( 'The dynamic block did not delegate configured and empty states correctly.' );
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
