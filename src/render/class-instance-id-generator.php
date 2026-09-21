<?php
/**
 * Embed instance ID contract.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Render;

/**
 * Creates one lowercase UUID v4 for browser-message correlation.
 */
interface InstanceIdGenerator {

	/** Returns a lowercase UUID v4. */
	public function generate(): string;
}
