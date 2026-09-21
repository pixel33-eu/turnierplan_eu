<?php
/**
 * WordPress transient metadata store.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Cache;

/**
 * Tracks exact transient names so revocation can invalidate plugin caches.
 */
final class WordPressCacheStore implements CacheStore {

	private const PREFIX       = 'tpeu_meta_';
	private const INDEX_OPTION = 'tpeu_metadata_cache_keys';

	/** Returns one transient value. */
	public function get( string $key ): mixed {
		return get_transient( self::PREFIX . $key );
	}

	/** Stores and indexes one transient value. */
	public function set( string $key, mixed $value, int $ttl ): void {
		$name = self::PREFIX . $key;
		set_transient( $name, $value, $ttl );

		$keys = get_option( self::INDEX_OPTION, array() );
		$keys = is_array( $keys ) ? array_values( array_filter( $keys, 'is_string' ) ) : array();

		if ( ! in_array( $name, $keys, true ) ) {
			$keys[] = $name;
			update_option( self::INDEX_OPTION, $keys, false );
		}
	}

	/** Deletes and untracks one transient value. */
	public function delete( string $key ): void {
		$name = self::PREFIX . $key;
		delete_transient( $name );

		$keys = get_option( self::INDEX_OPTION, array() );

		if ( is_array( $keys ) ) {
			$keys = array_values( array_diff( $keys, array( $name ) ) );
			update_option( self::INDEX_OPTION, $keys, false );
		}
	}

	/** Deletes all exactly tracked plugin metadata transients. */
	public function clear(): void {
		$keys = get_option( self::INDEX_OPTION, array() );

		if ( is_array( $keys ) ) {
			foreach ( $keys as $name ) {
				if ( is_string( $name ) && str_starts_with( $name, self::PREFIX ) ) {
					delete_transient( $name );
				}
			}
		}

		delete_option( self::INDEX_OPTION );
	}
}
