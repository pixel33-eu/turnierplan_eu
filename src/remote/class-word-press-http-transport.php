<?php
/**
 * WordPress HTTP API adapter.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

/**
 * Performs safe remote requests without cookies or credentials.
 */
final class WordPressHttpTransport implements HttpTransport {

	/** Performs one safe WordPress HTTP API request. */
	public function get( string $url, array $args ): HttpResponse|TransportFailure {
		$response = wp_safe_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$code = 'transport_error';

			if ( str_contains( strtolower( $response->get_error_message() ), 'timed out' ) ) {
				$code = 'timeout';
			}

			return new TransportFailure( $code );
		}

		$headers = array();

		foreach ( wp_remote_retrieve_headers( $response ) as $name => $value ) {
			$headers[ strtolower( (string) $name ) ] = is_array( $value )
				? implode( ', ', array_map( 'strval', $value ) )
				: (string) $value;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$status = is_int( $status ) ? $status : 0;

		return new HttpResponse(
			$status,
			$headers,
			wp_remote_retrieve_body( $response )
		);
	}
}
