<?php
/**
 * Canonical Turnierplan.eu shortcode generator.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Shortcode;

use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedSelection;
use TurnierplanEU\WordPress\Config\ShortcodeConfigMapper;

/**
 * Creates compact copyable shortcode text from validated selections.
 */
final class ShortcodeGenerator {

	/**
	 * Generates a canonical self-closing shortcode.
	 *
	 * @param EmbedSelection $selection Validated inline or preset selection.
	 * @return string Canonical shortcode text.
	 * @throws ConfigException When a mapped value cannot be represented safely.
	 */
	public function generate( EmbedSelection $selection ): string {
		$parts = array();

		foreach ( ShortcodeConfigMapper::to_attributes( $selection ) as $name => $value ) {
			if (
				1 !== preg_match( '/^[a-z_]+$/', $name )
				|| 1 !== preg_match( '/^[A-Za-z0-9_#.,:-]+$/', $value )
			) {
				throw ConfigException::for_field( 'shortcode', 'Shortcode value cannot be represented safely.' );
			}

			// Values are constrained to the safe character sets immediately above.
			$parts[] = sprintf( '%s="%s"', $name, $value ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( array() === $parts ) {
			throw ConfigException::for_field( 'selection', 'A usable shortcode selection is required.' );
		}

		return '[turnierplan ' . implode( ' ', $parts ) . ']';
	}
}
