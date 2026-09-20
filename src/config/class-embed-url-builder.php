<?php
/**
 * Canonical service URL builder.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

/**
 * Builds URLs exclusively from validated values and a trusted service origin.
 */
final class EmbedUrlBuilder {

	private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	/**
	 * Trusted service configuration.
	 *
	 * @var ServiceConfiguration
	 */
	private ServiceConfiguration $service;

	/**
	 * Creates a URL builder for one trusted service configuration.
	 *
	 * @param ServiceConfiguration $service Trusted service configuration.
	 */
	public function __construct( ServiceConfiguration $service ) {
		$this->service = $service;
	}

	/**
	 * Builds a complete V1 frame URL in deterministic query order.
	 *
	 * @param EmbedConfig $config        Validated embed configuration.
	 * @param string      $instance      Lowercase UUID v4.
	 * @param string      $parent_origin Exact HTTPS parent origin.
	 * @return string
	 * @throws ConfigException When browser transport values are invalid.
	 */
	public function frame_url( EmbedConfig $config, string $instance, string $parent_origin ): string {
		if ( 1 !== preg_match( self::UUID_V4_PATTERN, $instance ) ) {
			throw ConfigException::for_field( 'instance', 'Embed instance must be a lowercase UUID v4.' );
		}

		$parent_origin = self::parent_origin( $parent_origin );
		$values        = $config->to_array();
		$view          = (string) $values['view'];
		$query         = array(
			'view' => $view,
			'lang' => (string) $values['language'],
		);

		foreach (
			array(
				'group'       => 'group',
				'participant' => 'participant',
				'matchFrom'   => 'match_from',
				'matchTo'     => 'match_to',
				'dateFrom'    => 'date_from',
				'dateTo'      => 'date_to',
			) as $field => $parameter
		) {
			if ( null !== $values[ $field ] ) {
				$query[ $parameter ] = (string) $values[ $field ];
			}
		}

		$query['theme']   = (string) $values['theme'];
		$query['density'] = (string) $values['density'];

		if ( null !== $values['accentColor'] ) {
			$query['accent'] = strtolower( substr( (string) $values['accentColor'], 1 ) );
		}

		$show = self::show_tokens( $config );

		if ( null !== $show ) {
			$query['show'] = implode( ',', $show );
		}

		if ( 'matches' === $view ) {
			$query['date'] = (string) $values['showDate'];
		}

		$query['branding']      = true === $values['showBranding'] ? 'show' : 'hide';
		$query['links']         = true === $values['openLinksInNewTab'] ? 'new-tab' : 'same-tab';
		$query['instance']      = $instance;
		$query['parent_origin'] = $parent_origin;

		return $this->service->get_origin()
			. '/embed/v1/tournaments/'
			. rawurlencode( (string) $values['tournamentRef'] )
			. '?'
			. http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Builds the metadata endpoint for a normalized reference and language.
	 *
	 * @param mixed  $reference Tournament reference input.
	 * @param string $language  Validated language or auto.
	 * @return string
	 * @throws ConfigException When the reference or language is invalid.
	 */
	public function metadata_url( mixed $reference, string $language = 'auto' ): string {
		$config = EmbedConfig::from_array(
			array(
				'tournamentRef' => $reference,
				'language'      => $language,
			)
		);

		return $this->service->get_origin()
			. '/api/embed/v1/tournaments/'
			. rawurlencode( (string) $config->get( 'tournamentRef' ) )
			. '/metadata?lang='
			. rawurlencode( (string) $config->get( 'language' ) );
	}

	/**
	 * Builds a safe public fallback URL from the normalized reference.
	 *
	 * @param mixed $reference Tournament reference input.
	 * @return string
	 * @throws ConfigException When the reference is invalid.
	 */
	public function public_url( mixed $reference ): string {
		return $this->service->get_origin()
			. '/t/'
			. rawurlencode( TournamentReference::normalize( $reference ) );
	}

	/**
	 * Returns a visibility list only when at least one V1 default changed.
	 *
	 * @param EmbedConfig $config Validated configuration.
	 * @return list<string>|null
	 */
	private static function show_tokens( EmbedConfig $config ): ?array {
		$fields  = EmbedConfigMap::show_fields( (string) $config->get( 'view' ) );
		$changed = false;
		$tokens  = array();

		foreach ( $fields as $token => $field ) {
			if ( true === $config->get( $field ) ) {
				$tokens[] = $token;
			} else {
				$changed = true;
			}
		}

		return $changed ? $tokens : null;
	}

	/**
	 * Validates and normalizes one exact HTTPS parent origin.
	 *
	 * @param string $origin Candidate origin.
	 * @return string
	 * @throws ConfigException When the origin is not exact and secure.
	 */
	private static function parent_origin( string $origin ): string {
		if (
			'' === $origin
			|| strlen( $origin ) > 255
			|| 1 === preg_match( '/[\x00-\x20\x7F]/', $origin )
		) {
			throw ConfigException::for_field( 'parentOrigin', 'Parent origin is malformed.' );
		}

		$parts = self::parse_url( $origin );

		if (
			! is_array( $parts )
			|| 'https' !== ( $parts['scheme'] ?? null )
			|| ! isset( $parts['host'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| isset( $parts['path'] )
			|| isset( $parts['query'] )
			|| isset( $parts['fragment'] )
		) {
			throw ConfigException::for_field( 'parentOrigin', 'Parent origin must be an exact HTTPS origin.' );
		}

		$normalized = 'https://' . strtolower( (string) $parts['host'] );

		if ( isset( $parts['port'] ) ) {
			$port        = (int) $parts['port'];
			$normalized .= ':' . $port;
		}

		if ( ! hash_equals( $normalized, $origin ) ) {
			throw ConfigException::for_field( 'parentOrigin', 'Parent origin is not normalized.' );
		}

		return $normalized;
	}

	/**
	 * Uses the WordPress URL parser while retaining isolated unit test support.
	 *
	 * @param string $url URL to parse.
	 * @return array<string, int|string>|false
	 */
	private static function parse_url( string $url ): array|false {
		if ( function_exists( 'wp_parse_url' ) ) {
			return wp_parse_url( $url );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress is intentionally absent from isolated unit tests.
		return parse_url( $url );
	}
}
