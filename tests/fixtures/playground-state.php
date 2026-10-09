<?php
/**
 * Mutable WordPress state readers for Playground integration assertions.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

/**
 * Reads a mutable option during an integration test.
 *
 * @phpstan-impure
 */
function tpeu_test_option( string $name, mixed $default_value = false ): mixed {
	return get_option( $name, $default_value );
}

/**
 * Reads a mutable post during an integration test.
 *
 * @phpstan-impure
 */
function tpeu_test_post( int $post_id ): ?WP_Post {
	return get_post( $post_id );
}

/**
 * Reads a mutable cron schedule during an integration test.
 *
 * @phpstan-impure
 */
function tpeu_test_scheduled( string $hook ): int|false {
	return wp_next_scheduled( $hook );
}

/**
 * Reads a mutable transient during an integration test.
 *
 * @phpstan-impure
 */
function tpeu_test_transient( string $name ): mixed {
	return get_transient( $name );
}

/**
 * Reads a mutable role capability during an integration test.
 *
 * @phpstan-impure
 */
function tpeu_test_role_has_cap( string $role_name, string $capability ): bool {
	$role = get_role( $role_name );

	return null !== $role && $role->has_cap( $capability );
}

/**
 * Reads a mutable plugin cache entry during an integration test.
 *
 * @phpstan-impure
 */
function tpeu_test_cache_value( string $key ): mixed {
	return ( new TurnierplanEU\WordPress\Cache\WordPressCacheStore() )->get( $key );
}

/**
 * Reads the mutable current blog context during an integration test.
 *
 * @phpstan-impure
 */
function tpeu_test_current_blog_id(): int {
	return get_current_blog_id();
}

/**
 * Checks the mutable database schema version during an integration test.
 *
 * @phpstan-impure
 */
function tpeu_test_database_version_is_current(): bool {
	return TurnierplanEU\WordPress\Lifecycle::DATABASE_VERSION === (int) get_option(
		TurnierplanEU\WordPress\Lifecycle::DATABASE_VERSION_OPTION,
		0
	);
}
