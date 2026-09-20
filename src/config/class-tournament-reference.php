<?php
/**
 * Tournament reference normalization.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

/**
 * Reduces supported user input to one bounded public tournament reference.
 */
final class TournamentReference {

	private const CANONICAL_PATTERN    = '/^trn_[0-9a-hjkmnp-tv-z]{26}$/';
	private const NUMERIC_PATTERN      = '/^[1-9][0-9]{0,19}$/';
	private const SLUG_PATTERN         = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
	private const MAX_INPUT_LENGTH     = 2048;
	private const MAX_REFERENCE_LENGTH = 100;

	/**
	 * Normalizes an ID, slug, canonical reference, or supported service URL.
	 *
	 * @param mixed $input User-provided reference.
	 * @return string
	 * @throws ConfigException When the input is not a supported reference.
	 */
	public static function normalize( mixed $input ): string {
		if ( ! is_string( $input ) ) {
			throw ConfigException::for_field( 'tournamentRef', 'Tournament reference must be a string.' );
		}

		$input = trim( $input );

		if (
			'' === $input
			|| strlen( $input ) > self::MAX_INPUT_LENGTH
			|| 1 === preg_match( '/[\x00-\x1F\x7F]/', $input )
		) {
			throw ConfigException::for_field( 'tournamentRef', 'Tournament reference is empty or malformed.' );
		}

		if ( str_contains( $input, '://' ) ) {
			return self::from_url( $input );
		}

		return self::normalize_reference( $input );
	}

	/**
	 * Extracts a reference from one documented production URL shape.
	 *
	 * @param string $input Complete URL.
	 * @return string
	 * @throws ConfigException When the URL shape is not supported.
	 */
	private static function from_url( string $input ): string {
		$parts = self::parse_url( $input );

		if (
			! is_array( $parts )
			|| 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) )
			|| 'www.turnierplan.eu' !== strtolower( (string) ( $parts['host'] ?? '' ) )
			|| isset( $parts['port'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| isset( $parts['fragment'] )
		) {
			throw ConfigException::for_field( 'tournamentRef', 'Tournament URL is not an allowed production URL.' );
		}

		$path  = (string) ( $parts['path'] ?? '' );
		$query = (string) ( $parts['query'] ?? '' );

		if ( 1 === preg_match( '#^/t/([^/]+)$#', $path, $matches ) ) {
			if ( '' !== $query || str_contains( $matches[1], '%' ) ) {
				throw ConfigException::for_field( 'tournamentRef', 'Tournament URL contains unsupported components.' );
			}

			return self::normalize_reference( $matches[1] );
		}

		if (
			'/live.php' === $path
			&& 1 === preg_match( '/^id=([1-9][0-9]{0,19})$/', $query, $matches )
		) {
			return $matches[1];
		}

		throw ConfigException::for_field( 'tournamentRef', 'Tournament URL path or query is not supported.' );
	}

	/**
	 * Normalizes a direct reference value.
	 *
	 * @param string $input Direct input value.
	 * @return string
	 * @throws ConfigException When the reference format is invalid.
	 */
	private static function normalize_reference( string $input ): string {
		$reference = strtolower( $input );
		$numeric   = 1 === preg_match( self::NUMERIC_PATTERN, $reference );

		if ( ctype_digit( $reference ) && ! $numeric ) {
			throw ConfigException::for_field( 'tournamentRef', 'Numeric tournament reference is malformed.' );
		}

		if (
			strlen( $reference ) > self::MAX_REFERENCE_LENGTH
			|| (
				1 !== preg_match( self::CANONICAL_PATTERN, $reference )
				&& ! $numeric
				&& 1 !== preg_match( self::SLUG_PATTERN, $reference )
			)
		) {
			throw ConfigException::for_field( 'tournamentRef', 'Tournament reference has an unsupported format.' );
		}

		return $reference;
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
