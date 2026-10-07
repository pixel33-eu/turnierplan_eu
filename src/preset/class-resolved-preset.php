<?php
/**
 * Validated reusable embed preset.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Preset;

use TurnierplanEU\WordPress\Config\EmbedConfig;

/** Carries only the values needed by shared rendering and editors. */
final class ResolvedPreset {

	/** Creates a resolved preset value. */
	public function __construct(
		private readonly int $id,
		private readonly string $title,
		private readonly EmbedConfig $config
	) {
	}

	/** Returns the WordPress post ID. */
	public function get_id(): int {
		return $this->id;
	}

	/** Returns the plain preset title. */
	public function get_title(): string {
		return $this->title;
	}

	/** Returns the validated embed configuration. */
	public function get_config(): EmbedConfig {
		return $this->config;
	}
}
