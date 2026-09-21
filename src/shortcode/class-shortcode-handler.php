<?php
/**
 * Turnierplan.eu shortcode registration and rendering.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Shortcode;

use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\ShortcodeConfigMapper;
use TurnierplanEU\WordPress\Render\EmbedRenderer;

/**
 * Maps the documented allowlist into the shared renderer and always returns.
 */
final class ShortcodeHandler {

	/** Creates the shortcode handler. */
	public function __construct( private readonly EmbedRenderer $renderer ) {
	}

	/** Registers the shortcode during WordPress initialization. */
	public function register(): void {
		add_action( 'init', array( $this, 'register_shortcode' ) );
	}

	/** Registers the public shortcode name. */
	public function register_shortcode(): void {
		add_shortcode( 'turnierplan', array( $this, 'render' ) );
	}

	/**
	 * Returns one rendered embed or a safe local error state.
	 *
	 * Enclosing content and the tag name do not alter the configuration.
	 *
	 * @param array<string,mixed>|string $attributes Shortcode attributes.
	 * @param string|null                $content    Ignored enclosing content.
	 * @param string                     $tag        Registered shortcode tag.
	 * @return string Rendered shortcode output.
	 */
	public function render( array|string $attributes = array(), ?string $content = null, string $tag = '' ): string {
		unset( $content, $tag );

		if ( ! is_array( $attributes ) ) {
			return $this->renderer->render_invalid();
		}

		$attributes = array_change_key_case( $attributes, CASE_LOWER );

		try {
			$selection = ShortcodeConfigMapper::from_attributes( $attributes );
		} catch ( ConfigException ) {
			return $this->renderer->render_invalid();
		}

		if ( $selection->is_preset() ) {
			return $this->renderer->render_preset_unavailable();
		}

		$config = $selection->get_config();

		return null === $config
			? $this->renderer->render_invalid()
			: $this->renderer->render( $config );
	}
}
