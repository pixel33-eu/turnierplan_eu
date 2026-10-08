<?php
/**
 * Tournament output language resolution.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Localization;

use TurnierplanEU\WordPress\Config\EmbedConfig;

/** Keeps the site output language independent from the current dashboard user. */
final class SiteLanguageResolver {

	/**
	 * Resolves auto to the WordPress site locale's primary language code.
	 *
	 * @param string $language Configured output language.
	 * @return string Explicit output language or normalized site language.
	 */
	public function resolve( string $language ): string {
		if ( 'auto' !== $language ) {
			return $language;
		}

		return self::primary_from_locale( get_locale() );
	}

	/**
	 * Returns a frame-ready copy while preserving the stored auto setting.
	 *
	 * @param EmbedConfig $config Validated stored configuration.
	 * @return EmbedConfig Configuration with a resolved output language.
	 */
	public function apply( EmbedConfig $config ): EmbedConfig {
		$values             = $config->to_array();
		$values['language'] = $this->resolve( (string) $values['language'] );

		return EmbedConfig::from_array( $values );
	}

	/**
	 * Normalizes a WordPress locale or leaves service fallback enabled.
	 *
	 * @param mixed $locale WordPress site locale.
	 * @return string Primary language code or auto for malformed values.
	 */
	public static function primary_from_locale( mixed $locale ): string {
		if ( ! is_string( $locale ) ) {
			return 'auto';
		}

		$primary = strtolower( explode( '_', str_replace( '-', '_', trim( $locale ) ), 2 )[0] );

		return 1 === preg_match( '/^[a-z]{2,3}$/', $primary ) ? $primary : 'auto';
	}
}
