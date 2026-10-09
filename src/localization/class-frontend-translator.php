<?php
/**
 * Isolated translations for public embed wrapper strings.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Localization;

/** Keeps an embed's explicit language independent from the WordPress locale. */
final class FrontendTranslator {

	/**
	 * Whether loading the bundled German catalog has already been attempted.
	 *
	 * @var bool
	 */
	private bool $german_catalog_loaded = false;

	/**
	 * Bundled German message catalog, when available.
	 *
	 * @var \WP_Translation_File|null
	 */
	private ?\WP_Translation_File $german_catalog = null;

	/**
	 * Creates the translator for the bundled plugin catalogs.
	 *
	 * @param string $plugin_file Main plugin file path.
	 */
	public function __construct( private readonly string $plugin_file ) {
	}

	/**
	 * Translates one public wrapper string for the resolved embed language.
	 *
	 * @param string $message  English source string.
	 * @param string $language Resolved embed language.
	 * @return string Localized string or the English source fallback.
	 */
	public function translate( string $message, string $language ): string {
		$primary = strtolower( explode( '-', $language, 2 )[0] );

		if ( 'de' !== $primary ) {
			return $message;
		}

		$catalog = $this->german_catalog();

		if ( null === $catalog ) {
			return $message;
		}

		$translation = $catalog->translate( $message );

		return false === $translation ? $message : $translation;
	}

	/** Loads the German MO catalog once without modifying WordPress's global locale. */
	private function german_catalog(): ?\WP_Translation_File {
		if ( $this->german_catalog_loaded ) {
			return $this->german_catalog;
		}

		$this->german_catalog_loaded = true;
		$catalog_file                = plugin_dir_path( $this->plugin_file ) . 'languages/turnierplan-eu-de_DE.mo';

		if ( ! is_readable( $catalog_file ) ) {
			return null;
		}

		$catalog = \WP_Translation_File::create( $catalog_file );

		if ( false === $catalog ) {
			return null;
		}

		$this->german_catalog = $catalog;

		return $this->german_catalog;
	}
}
