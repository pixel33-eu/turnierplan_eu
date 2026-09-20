<?php
/**
 * Inline or preset embed selection.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

/**
 * Enforces the mutually exclusive block and shortcode storage modes.
 */
final class EmbedSelection {

	/**
	 * Selected preset ID, or null for inline configuration.
	 *
	 * @var int|null
	 */
	private ?int $preset_id;

	/**
	 * Inline configuration, or null for a preset reference.
	 *
	 * @var EmbedConfig|null
	 */
	private ?EmbedConfig $config;

	/**
	 * Creates one already validated selection.
	 *
	 * @param int|null         $preset_id Preset post ID.
	 * @param EmbedConfig|null $config    Inline configuration.
	 */
	private function __construct( ?int $preset_id, ?EmbedConfig $config ) {
		$this->preset_id = $preset_id;
		$this->config    = $config;
	}

	/**
	 * Validates the block-style selection envelope.
	 *
	 * A presetId of zero is the documented block default and means inline mode.
	 *
	 * @param array<string, mixed> $input Selection input.
	 * @return self
	 * @throws ConfigException When modes are mixed or values are malformed.
	 */
	public static function from_array( array $input ): self {
		$unknown = array_diff( array_keys( $input ), array( 'presetId', 'config' ) );

		if ( array() !== $unknown ) {
			throw ConfigException::for_field( (string) reset( $unknown ), 'Unknown selection field.' );
		}

		$preset_id = $input['presetId'] ?? 0;

		if ( ! is_int( $preset_id ) || $preset_id < 0 ) {
			throw ConfigException::for_field( 'presetId', 'Preset ID must be a non-negative integer.' );
		}

		if ( $preset_id > 0 ) {
			if ( array_key_exists( 'config', $input ) && null !== $input['config'] ) {
				throw ConfigException::for_field( 'config', 'Preset and inline configuration cannot be combined.' );
			}

			return new self( $preset_id, null );
		}

		if ( ! isset( $input['config'] ) || ! is_array( $input['config'] ) ) {
			throw ConfigException::for_field( 'config', 'Inline configuration is required.' );
		}

		return new self( null, EmbedConfig::from_array( $input['config'] ) );
	}

	/**
	 * Returns whether this selection references a preset.
	 *
	 * @return bool
	 */
	public function is_preset(): bool {
		return null !== $this->preset_id;
	}

	/**
	 * Returns the preset ID.
	 *
	 * @return int|null
	 */
	public function get_preset_id(): ?int {
		return $this->preset_id;
	}

	/**
	 * Returns the inline configuration.
	 *
	 * @return EmbedConfig|null
	 */
	public function get_config(): ?EmbedConfig {
		return $this->config;
	}
}
