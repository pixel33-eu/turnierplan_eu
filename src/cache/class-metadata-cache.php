<?php
/**
 * Metadata cache policy.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Cache;

use TurnierplanEU\WordPress\Remote\MetadataResponseValidator;
use TurnierplanEU\WordPress\Remote\MetadataResult;

/**
 * Separates fresh, stale-success, and authoritative-not-found entries.
 */
final class MetadataCache {

	private const SUCCESS_TTL   = 300;
	private const NOT_FOUND_TTL = 60;
	private const STALE_TTL     = 86400;

	/** Creates the metadata cache. */
	public function __construct(
		private readonly CacheStore $store,
		private readonly MetadataResponseValidator $validator
	) {
	}

	/** Returns a current success entry. */
	public function get_fresh( string $reference, string $language ): ?MetadataResult {
		return $this->read_success( 'fresh_' . $this->key( $reference, $language ), 'cache' );
	}

	/** Returns the last valid success entry. */
	public function get_stale( string $reference, string $language ): ?MetadataResult {
		return $this->read_success( 'stale_' . $this->key( $reference, $language ), 'stale' );
	}

	/** Reports whether an authoritative short-lived 404 is cached. */
	public function has_not_found( string $reference, string $language ): bool {
		return true === $this->store->get( 'missing_' . $this->key( $reference, $language ) );
	}

	/** Stores one validated response as fresh and stale success. */
	public function save_success( string $reference, string $language, MetadataResult $result ): void {
		$metadata = $result->get_metadata();

		if ( null === $metadata || ! $this->validator->is_valid( $metadata ) ) {
			return;
		}

		$value = array(
			'metadata'  => $metadata,
			'etag'      => $result->get_etag(),
			'stored_at' => time(),
		);
		$key   = $this->key( $reference, $language );

		$this->store->set( 'fresh_' . $key, $value, self::SUCCESS_TTL );
		$this->store->set( 'stale_' . $key, $value, self::STALE_TTL );
		$this->store->delete( 'missing_' . $key );
	}

	/** Renews a success entry after an HTTP 304 response. */
	public function revalidate( string $reference, string $language, MetadataResult $stale ): void {
		$this->save_success( $reference, $language, $stale );
	}

	/** Stores an authoritative 404 and removes every old success. */
	public function save_not_found( string $reference, string $language ): void {
		$key = $this->key( $reference, $language );

		$this->store->delete( 'fresh_' . $key );
		$this->store->delete( 'stale_' . $key );
		$this->store->set( 'missing_' . $key, true, self::NOT_FOUND_TTL );
	}

	/** Clears every tracked metadata cache entry. */
	public function clear(): void {
		$this->store->clear();
	}

	/**
	 * Reads and validates one cached success.
	 *
	 * @return MetadataResult|null Valid cached result.
	 */
	private function read_success( string $key, string $source ): ?MetadataResult {
		$value = $this->store->get( $key );

		if (
			! is_array( $value )
			|| ! isset( $value['metadata'], $value['stored_at'] )
			|| ! is_array( $value['metadata'] )
			|| ! is_int( $value['stored_at'] )
			|| time() - $value['stored_at'] > self::STALE_TTL
			|| ! $this->validator->is_valid( $value['metadata'] )
		) {
			return null;
		}

		$etag = isset( $value['etag'] ) && is_string( $value['etag'] ) ? $value['etag'] : null;

		/**
		 * Metadata passed the complete V1 validator.
		 *
		 * @var array<string,mixed> $metadata
		 */
		$metadata = $value['metadata'];

		return MetadataResult::success( $metadata, $etag, $source );
	}

	/** Creates a short opaque V1 cache key. */
	private function key( string $reference, string $language ): string {
		return substr( hash( 'sha256', 'v1|' . $reference . '|' . $language ), 0, 32 );
	}
}
