<?php
/**
 * Per-user REST request limiter.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Rest;

/**
 * Bounds authenticated users without exposing a public proxy.
 */
final class UserRateLimiter {

	/** Consumes one request from a short per-user bucket. */
	public function consume( int $user_id, string $bucket, int $limit ): bool {
		if ( $user_id <= 0 || 1 !== preg_match( '/^[a-z_]{1,20}$/', $bucket ) ) {
			return false;
		}

		$key   = 'tpeu_limit_' . substr( hash( 'sha256', $user_id . '|' . $bucket ), 0, 24 );
		$count = get_transient( $key );
		$count = is_int( $count ) ? $count : 0;

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}
}
