<?php
/**
 * Fixed-origin metadata client.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

use JsonException;
use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedUrlBuilder;

/**
 * Fetches and validates metadata within one bounded total time budget.
 */
final class MetadataClient {

	private const MAX_RESPONSE_BYTES = 1048576;

	/** Creates the fixed-origin client. */
	public function __construct(
		private readonly EmbedUrlBuilder $url_builder,
		private readonly TrustedMetadataUrl $url_policy,
		private readonly HttpTransport $transport,
		private readonly MetadataResponseValidator $validator,
		private readonly string $plugin_version
	) {
	}

	/** Fetches metadata within one total time budget. */
	public function fetch( mixed $reference, string $language, ?string $etag = null, int $time_budget = 5 ): MetadataResult {
		try {
			$url = $this->url_builder->metadata_url( $reference, $language );
		} catch ( ConfigException ) {
			return MetadataResult::status( 'invalid_reference' );
		}

		if ( ! $this->url_policy->is_allowed( $url ) ) {
			return MetadataResult::status( 'unsafe_target' );
		}

		$deadline  = microtime( true ) + max( 1, min( 10, $time_budget ) );
		$redirects = 0;

		while ( true ) {
			$remaining = $deadline - microtime( true );

			if ( $remaining <= 0 ) {
				return MetadataResult::status( 'timeout' );
			}

			$headers = array(
				'Accept'     => 'application/json',
				'User-Agent' => 'Turnierplan.eu-WordPress/' . $this->plugin_version,
			);

			if ( null !== $etag && '' !== $etag ) {
				$headers['If-None-Match'] = $etag;
			}

			$response = $this->transport->get(
				$url,
				array(
					'timeout'             => $remaining,
					'redirection'         => 0,
					'sslverify'           => true,
					'reject_unsafe_urls'  => true,
					'limit_response_size' => self::MAX_RESPONSE_BYTES + 1,
					'headers'             => $headers,
					'cookies'             => array(),
				)
			);

			if ( $response instanceof TransportFailure ) {
				return MetadataResult::status( $response->get_code() );
			}

			$status = $response->get_status();

			if ( in_array( $status, array( 301, 302, 303, 307, 308 ), true ) ) {
				if ( $redirects >= 2 ) {
					return MetadataResult::status( 'too_many_redirects' );
				}

				$next_url = $this->url_policy->resolve_redirect( $url, (string) $response->get_header( 'location' ) );

				if ( null === $next_url ) {
					return MetadataResult::status( 'unsafe_redirect' );
				}

				++$redirects;
				$url = $next_url;
				continue;
			}

			return $this->interpret( $response );
		}
	}

	/** Converts one final HTTP response into a stable result. */
	private function interpret( HttpResponse $response ): MetadataResult {
		$status = $response->get_status();

		if ( 304 === $status ) {
			return MetadataResult::status( 'not_modified' );
		}

		if ( 404 === $status ) {
			return MetadataResult::status( 'tournament_not_found' );
		}

		if ( 429 === $status ) {
			return MetadataResult::status( 'rate_limited', $this->retry_after( $response ) );
		}

		if ( 503 === $status ) {
			return MetadataResult::status( 'temporarily_unavailable', $this->retry_after( $response ) );
		}

		if ( 200 !== $status ) {
			return MetadataResult::status( 'remote_error' );
		}

		$content_type = strtolower( (string) $response->get_header( 'content-type' ) );
		$body         = $response->get_body();

		if ( ! str_starts_with( $content_type, 'application/json' ) ) {
			return MetadataResult::status( 'invalid_content_type' );
		}

		if ( strlen( $body ) > self::MAX_RESPONSE_BYTES ) {
			return MetadataResult::status( 'response_too_large' );
		}

		try {
			$metadata = json_decode( $body, true, 64, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			return MetadataResult::status( 'invalid_json' );
		}

		if ( ! $this->validator->is_valid( $metadata ) ) {
			return MetadataResult::status( 'invalid_schema' );
		}

		$etag = $response->get_header( 'etag' );

		if ( null !== $etag && ( strlen( $etag ) > 200 || str_contains( $etag, "\r" ) || str_contains( $etag, "\n" ) ) ) {
			$etag = null;
		}

		/**
		 * Metadata passed the complete V1 validator.
		 *
		 * @var array<string,mixed> $metadata
		 */
		return MetadataResult::success( $metadata, $etag );
	}

	/** Returns a bounded numeric Retry-After delay. */
	private function retry_after( HttpResponse $response ): ?int {
		$value = $response->get_header( 'retry-after' );

		if ( null === $value || 1 !== preg_match( '/^[0-9]{1,4}$/', $value ) ) {
			return null;
		}

		return max( 1, min( 3600, (int) $value ) );
	}
}
