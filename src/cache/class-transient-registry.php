<?php
/**
 * Exact plugin transient registry.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Cache;

/** Tracks plugin transients so uninstall works with database and object caches. */
final class TransientRegistry {

	public const INDEX_OPTION = 'tpeu_transient_keys';

	/** Stores and tracks an exact plugin transient name. */
	public static function set( string $name, mixed $value, int $expiration ): void {
		if ( ! self::is_plugin_name( $name ) ) {
			return;
		}

		set_transient( $name, $value, $expiration );
		$names = self::names();

		if ( ! in_array( $name, $names, true ) ) {
			$names[] = $name;
			update_option( self::INDEX_OPTION, $names, false );
		}
	}

	/** Deletes and untracks one exact plugin transient name. */
	public static function delete( string $name ): void {
		if ( ! self::is_plugin_name( $name ) ) {
			return;
		}

		delete_transient( $name );
		$names = array_values( array_diff( self::names(), array( $name ) ) );

		if ( array() === $names ) {
			delete_option( self::INDEX_OPTION );
			return;
		}

		update_option( self::INDEX_OPTION, $names, false );
	}

	/** Deletes all exactly tracked plugin transients and their index. */
	public static function clear(): void {
		foreach ( self::names() as $name ) {
			delete_transient( $name );
		}

		delete_option( self::INDEX_OPTION );
	}

	/**
	 * Returns sanitized, plugin-owned transient names.
	 *
	 * @return list<string>
	 */
	private static function names(): array {
		$names = get_option( self::INDEX_OPTION, array() );

		if ( ! is_array( $names ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$names,
				static fn ( mixed $name ): bool => is_string( $name ) && self::is_plugin_name( $name )
			)
		);
	}

	/** Returns whether a name belongs to the plugin's reserved namespace. */
	private static function is_plugin_name( string $name ): bool {
		return str_starts_with( $name, 'tpeu_' );
	}
}
