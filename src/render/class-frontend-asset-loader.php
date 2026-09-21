<?php
/**
 * Frontend asset loading contract.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Render;

/**
 * Loads the local assets needed by an actual embed.
 */
interface FrontendAssetLoader {

	/** Enqueues the shared frontend stylesheet and script. */
	public function enqueue(): void;
}
