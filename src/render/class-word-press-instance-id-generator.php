<?php
/**
 * WordPress embed instance ID generator.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Render;

/**
 * Uses WordPress core's cryptographically random UUID v4 helper.
 */
final class WordPressInstanceIdGenerator implements InstanceIdGenerator {

	/** Returns a lowercase UUID v4. */
	public function generate(): string {
		return strtolower( wp_generate_uuid4() );
	}
}
