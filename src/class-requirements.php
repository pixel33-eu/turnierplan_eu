<?php
/**
 * Runtime version requirements.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress;

/**
 * Checks the WordPress and PHP versions before runtime code starts.
 */
final class Requirements {

	public const MINIMUM_PHP       = '8.3';
	public const MINIMUM_WORDPRESS = '6.5';

	/**
	 * Finds all unmet runtime requirements.
	 *
	 * @param string $php_version       Active PHP version.
	 * @param string $wordpress_version Active WordPress version.
	 * @return array<string, array{current: string, required: string}>
	 */
	public static function unmet_requirements( string $php_version, string $wordpress_version ): array {
		$failures = array();

		if ( version_compare( $php_version, self::MINIMUM_PHP, '<' ) ) {
			$failures['php'] = array(
				'current'  => $php_version,
				'required' => self::MINIMUM_PHP,
			);
		}

		if ( version_compare( $wordpress_version, self::MINIMUM_WORDPRESS, '<' ) ) {
			$failures['wordpress'] = array(
				'current'  => $wordpress_version,
				'required' => self::MINIMUM_WORDPRESS,
			);
		}

		return $failures;
	}

	/**
	 * Builds a translated message for unmet requirements.
	 *
	 * @param array<string, array{current: string, required: string}> $failures Unmet requirements.
	 * @return string
	 */
	public static function get_error_message( array $failures ): string {
		$messages = array();

		if ( isset( $failures['php'] ) ) {
			$messages[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'Turnierplan.eu benötigt PHP %1$s oder neuer. Installiert ist PHP %2$s.', 'turnierplan-eu' ),
				$failures['php']['required'],
				$failures['php']['current']
			);
		}

		if ( isset( $failures['wordpress'] ) ) {
			$messages[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'Turnierplan.eu benötigt WordPress %1$s oder neuer. Installiert ist WordPress %2$s.', 'turnierplan-eu' ),
				$failures['wordpress']['required'],
				$failures['wordpress']['current']
			);
		}

		return implode( ' ', $messages );
	}
}
