<?php
/**
 * WordPress site-origin provider.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Render;

/**
 * Reduces the configured home URL to one exact HTTPS origin.
 */
final class WordPressParentOrigin implements ParentOriginProvider {

	/** Returns the site's exact HTTPS origin. */
	public function get_origin(): ?string {
		$parts = wp_parse_url( home_url( '/' ) );

		if (
			! is_array( $parts )
			|| 'https' !== ( $parts['scheme'] ?? null )
			|| ! isset( $parts['host'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
		) {
			return null;
		}

		$origin = 'https://' . strtolower( (string) $parts['host'] );

		if ( isset( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}

		return $origin;
	}
}
