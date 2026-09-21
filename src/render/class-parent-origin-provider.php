<?php
/**
 * WordPress parent-origin contract.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Render;

/**
 * Provides the exact HTTPS origin allowed to receive frame messages.
 */
interface ParentOriginProvider {

	/** Returns the exact HTTPS origin, or null on an unsafe site URL. */
	public function get_origin(): ?string;
}
