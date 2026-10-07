<?php
/**
 * Public preset resolution contract.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Preset;

/** Resolves only presets that are safe for a public page. */
interface PresetResolver {

	/** Returns a published valid preset or null without substitution. */
	public function resolve_published( int $preset_id ): ?ResolvedPreset;
}
