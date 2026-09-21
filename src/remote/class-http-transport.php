<?php
/**
 * HTTP transport contract.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

/**
 * Makes the fixed remote client testable without network access.
 */
interface HttpTransport {

	/**
	 * Fetches one trusted URL.
	 *
	 * @param string               $url  Trusted URL.
	 * @param array<string, mixed> $args WordPress-style request arguments.
	 * @return HttpResponse|TransportFailure
	 */
	public function get( string $url, array $args ): HttpResponse|TransportFailure;
}
