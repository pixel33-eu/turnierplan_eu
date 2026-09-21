<?php
/**
 * Metadata cache storage contract.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Cache;

/**
 * Supports isolated cache policy tests and a WordPress transient adapter.
 */
interface CacheStore {

	/** Returns a stored value or false after expiry. */
	public function get( string $key ): mixed;

	/** Stores a value for a bounded number of seconds. */
	public function set( string $key, mixed $value, int $ttl ): void;

	/** Deletes one cache entry. */
	public function delete( string $key ): void;

	/** Deletes every tracked metadata entry. */
	public function clear(): void;
}
