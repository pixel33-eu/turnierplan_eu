<?php
/**
 * Protected editor metadata REST routes.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Rest;

use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;
use TurnierplanEU\WordPress\Config\TournamentReference;
use TurnierplanEU\WordPress\Remote\MetadataGateway;
use TurnierplanEU\WordPress\Remote\MetadataResult;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes metadata only to nonce-authenticated users who can edit posts.
 */
final class MetadataController {

	private const NAMESPACE = 'turnierplan-eu/v1';

	/** Creates the protected metadata controller. */
	public function __construct(
		private readonly MetadataGateway $gateway,
		private readonly UserRateLimiter $limiter
	) {
	}

	/** Registers the REST initialization hook. */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Registers metadata and refresh routes. */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/metadata/(?P<reference>[A-Za-z0-9_-]{1,104})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_metadata' ),
				'permission_callback' => array( $this, 'can_read_metadata' ),
				'args'                => array(
					'reference' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_reference' ),
					),
					'language'  => array(
						'default'           => 'auto',
						'validate_callback' => array( $this, 'validate_language' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/cache/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'refresh_metadata' ),
				'permission_callback' => array( $this, 'can_refresh_metadata' ),
				'args'                => array(
					'reference' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_reference' ),
					),
					'language'  => array(
						'default'           => 'auto',
						'validate_callback' => array( $this, 'validate_language' ),
					),
					'preset_id' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/** Checks permission, nonce, and rate limit for metadata reads. */
	public function can_read_metadata( WP_REST_Request $request ): bool|WP_Error {
		return $this->authorize( $request, 'metadata', 30 );
	}

	/** Checks general and optional preset-specific refresh permission. */
	public function can_refresh_metadata( WP_REST_Request $request ): bool|WP_Error {
		$authorized = $this->authorize( $request, 'refresh', 10 );

		if ( true !== $authorized ) {
			return $authorized;
		}

		$preset_id = absint( $request->get_param( 'preset_id' ) );

		if ( $preset_id > 0 && ! current_user_can( 'edit_post', $preset_id ) ) {
			return new WP_Error(
				'tpeu_preset_forbidden',
				esc_html__( 'Du darfst dieses Preset nicht aktualisieren.', 'turnierplan-eu' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/** Returns cached or newly fetched metadata. */
	public function get_metadata( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->gateway->get(
			$request->get_param( 'reference' ),
			(string) $request->get_param( 'language' )
		);

		return $this->prepare_response( $result );
	}

	/** Forces a conditional metadata refresh. */
	public function refresh_metadata( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->gateway->get(
			$request->get_param( 'reference' ),
			(string) $request->get_param( 'language' ),
			true
		);

		return $this->prepare_response( $result );
	}

	/** Validates a route reference with the central normalizer. */
	public function validate_reference( mixed $value ): bool {
		try {
			TournamentReference::normalize( $value );
			return true;
		} catch ( ConfigException ) {
			return false;
		}
	}

	/** Validates a language with the central configuration model. */
	public function validate_language( mixed $value ): bool {
		try {
			EmbedConfig::from_array(
				array(
					'tournamentRef' => '1',
					'language'      => $value,
				)
			);
			return true;
		} catch ( ConfigException ) {
			return false;
		}
	}

	/** Applies shared route authorization controls. */
	private function authorize( WP_REST_Request $request, string $bucket, int $limit ): bool|WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'tpeu_rest_forbidden',
				esc_html__( 'Du darfst keine Turniermetadaten abrufen.', 'turnierplan-eu' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( null === $nonce || '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'tpeu_invalid_nonce',
				esc_html__( 'Die Sicherheitsprüfung ist abgelaufen. Bitte lade den Editor neu.', 'turnierplan-eu' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->limiter->consume( get_current_user_id(), $bucket, $limit ) ) {
			return new WP_Error(
				'tpeu_user_rate_limited',
				esc_html__( 'Zu viele Metadatenanfragen. Bitte warte kurz.', 'turnierplan-eu' ),
				array(
					'status'      => 429,
					'retry_after' => 60,
				)
			);
		}

		return true;
	}

	/** Converts a stable internal result to a WordPress REST response. */
	private function prepare_response( MetadataResult $result ): WP_REST_Response|WP_Error {
		if ( $result->is_success() ) {
			return new WP_REST_Response(
				array(
					'metadata' => $result->get_metadata(),
					'cache'    => array(
						'source' => $result->get_source(),
						'stale'  => 'stale' === $result->get_source(),
					),
				),
				200
			);
		}

		$code  = $result->get_code();
		$map   = array(
			'service_not_enabled'     => array( 403, esc_html__( 'Der externe Dienst wurde für diese Website nicht freigegeben.', 'turnierplan-eu' ) ),
			'invalid_reference'       => array( 400, esc_html__( 'Die Turnierreferenz oder Sprache ist ungültig.', 'turnierplan-eu' ) ),
			'tournament_not_found'    => array( 404, esc_html__( 'Das Turnier wurde nicht gefunden oder ist nicht öffentlich.', 'turnierplan-eu' ) ),
			'rate_limited'            => array( 429, esc_html__( 'Turnierplan.eu begrenzt die Anfragen vorübergehend.', 'turnierplan-eu' ) ),
			'temporarily_unavailable' => array( 503, esc_html__( 'Turnierplan.eu ist vorübergehend nicht verfügbar.', 'turnierplan-eu' ) ),
			'timeout'                 => array( 504, esc_html__( 'Der Metadatenabruf hat das Zeitlimit erreicht.', 'turnierplan-eu' ) ),
			'transport_error'         => array( 502, esc_html__( 'Die sichere Verbindung zu Turnierplan.eu ist fehlgeschlagen.', 'turnierplan-eu' ) ),
			'invalid_content_type'    => array( 502, esc_html__( 'Turnierplan.eu hat keine JSON-Antwort geliefert.', 'turnierplan-eu' ) ),
			'invalid_json'            => array( 502, esc_html__( 'Turnierplan.eu hat ungültiges JSON geliefert.', 'turnierplan-eu' ) ),
			'invalid_schema'          => array( 502, esc_html__( 'Die Metadatenantwort hat ein nicht unterstütztes Format.', 'turnierplan-eu' ) ),
			'response_too_large'      => array( 502, esc_html__( 'Die Metadatenantwort überschreitet das Größenlimit.', 'turnierplan-eu' ) ),
		);
		$error = $map[ $code ] ?? array( 502, esc_html__( 'Die Metadaten konnten nicht geladen werden.', 'turnierplan-eu' ) );
		$data  = array( 'status' => $error[0] );

		if ( null !== $result->get_retry_after() ) {
			$data['retry_after'] = $result->get_retry_after();
		}

		return new WP_Error( 'tpeu_' . sanitize_key( $code ), $error[1], $data );
	}
}
