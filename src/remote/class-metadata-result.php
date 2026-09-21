<?php
/**
 * Metadata operation result.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

/**
 * Carries only stable states between transport, cache, REST, and settings UI.
 */
final class MetadataResult {

	/**
	 * Creates an internal result value.
	 *
	 * @param string                   $code        Stable result code.
	 * @param array<string,mixed>|null $metadata    Validated metadata.
	 * @param string|null              $etag        Response validator.
	 * @param int|null                 $retry_after Retry delay in seconds.
	 * @param string                   $source      remote, cache, or stale.
	 */
	private function __construct(
		private readonly string $code,
		private readonly ?array $metadata = null,
		private readonly ?string $etag = null,
		private readonly ?int $retry_after = null,
		private readonly string $source = 'remote'
	) {
	}

	/**
	 * Creates a successful result.
	 *
	 * @param array<string,mixed> $metadata Validated metadata.
	 * @param string|null         $etag     Remote response validator.
	 * @param string              $source   Result source.
	 */
	public static function success( array $metadata, ?string $etag, string $source = 'remote' ): self {
		return new self( 'success', $metadata, $etag, null, $source );
	}

	/** Creates a result without metadata. */
	public static function status( string $code, ?int $retry_after = null ): self {
		return new self( $code, null, null, $retry_after );
	}

	/** Reports whether validated metadata is present. */
	public function is_success(): bool {
		return null !== $this->metadata;
	}

	/** Returns the stable result code. */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Returns validated metadata.
	 *
	 * @return array<string,mixed>|null Metadata or null.
	 */
	public function get_metadata(): ?array {
		return $this->metadata;
	}

	/** Returns the optional remote ETag. */
	public function get_etag(): ?string {
		return $this->etag;
	}

	/** Returns an optional retry delay. */
	public function get_retry_after(): ?int {
		return $this->retry_after;
	}

	/** Returns remote, cache, or stale. */
	public function get_source(): string {
		return $this->source;
	}
}
