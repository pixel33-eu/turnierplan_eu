<?php
/**
 * Trusted metadata URL policy.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

use TurnierplanEU\WordPress\Config\ServiceConfiguration;

/**
 * Restricts initial and redirected requests to the fixed V1 metadata endpoint.
 */
final class TrustedMetadataUrl {

	/** Creates a policy for the fixed service. */
	public function __construct( private readonly ServiceConfiguration $service ) {
	}

	/** Resolves and checks one absolute or root-relative redirect target. */
	public function resolve_redirect( string $current_url, string $location ): ?string {
		unset( $current_url );
		if ( '' === $location || strlen( $location ) > 500 || str_contains( $location, "\r" ) || str_contains( $location, "\n" ) ) {
			return null;
		}

		if ( str_starts_with( $location, '/' ) && ! str_starts_with( $location, '//' ) ) {
			$location = $this->service->get_origin() . $location;
		} elseif ( ! str_starts_with( $location, 'https://' ) ) {
			return null;
		}

		return $this->is_allowed( $location ) ? $location : null;
	}

	/** Reports whether a URL is the fixed V1 HTTPS metadata endpoint. */
	public function is_allowed( string $url ): bool {
		if ( function_exists( 'wp_parse_url' ) ) {
			$parts = wp_parse_url( $url );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress is intentionally absent from isolated unit tests.
			$parts = parse_url( $url );
		}

		if (
			! is_array( $parts )
			|| 'https' !== ( $parts['scheme'] ?? null )
			|| 'www.turnierplan.eu' !== strtolower( (string) ( $parts['host'] ?? '' ) )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| isset( $parts['port'] )
			|| isset( $parts['fragment'] )
			|| ! isset( $parts['path'], $parts['query'] )
		) {
			return false;
		}

		if ( 1 !== preg_match( '#^/api/embed/v1/tournaments/[A-Za-z0-9-]{1,104}/metadata$#', (string) $parts['path'] ) ) {
			return false;
		}

		return 1 === preg_match( '/^lang=(?:auto|[a-z]{2,3}(?:-[A-Z]{2})?)$/', (string) $parts['query'] );
	}
}
