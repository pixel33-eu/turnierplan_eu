<?php
/**
 * Normalized HTTP response value.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

/**
 * Keeps WordPress HTTP details outside the metadata client.
 */
final class HttpResponse {

	/**
	 * Creates a normalized response.
	 *
	 * @param int                  $status  HTTP status.
	 * @param array<string,string> $headers Lowercase response headers.
	 * @param string               $body    Response body.
	 */
	public function __construct(
		private readonly int $status,
		private readonly array $headers,
		private readonly string $body
	) {
	}

	/** Returns the HTTP status. */
	public function get_status(): int {
		return $this->status;
	}

	/** Returns one lowercase-normalized header. */
	public function get_header( string $name ): ?string {
		return $this->headers[ strtolower( $name ) ] ?? null;
	}

	/** Returns the raw response body. */
	public function get_body(): string {
		return $this->body;
	}
}
