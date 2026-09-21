<?php
/**
 * Plugin settings repository.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Settings;

/**
 * Owns exact option names, defaults, and positive-list sanitization.
 */
final class SettingsRepository implements ServiceApproval {

	public const OPTION_NAME = 'tpeu_settings';

	/**
	 * Returns settings used before an option exists.
	 *
	 * @return array{service_enabled:bool,language:string,theme:string,density:string,delete_data:bool} Defaults.
	 */
	public static function defaults(): array {
		return array(
			'service_enabled' => false,
			'language'        => 'auto',
			'theme'           => 'auto',
			'density'         => 'comfortable',
			'delete_data'     => false,
		);
	}

	/**
	 * Returns normalized saved settings.
	 *
	 * @return array{service_enabled:bool,language:string,theme:string,density:string,delete_data:bool}
	 */
	public function get(): array {
		$value = get_option( self::OPTION_NAME, self::defaults() );

		if ( ! is_array( $value ) ) {
			return self::defaults();
		}

		$defaults = self::defaults();

		return array(
			'service_enabled' => true === ( $value['service_enabled'] ?? false ),
			'language'        => in_array( $value['language'] ?? null, array( 'auto', 'de', 'en' ), true ) ? (string) $value['language'] : $defaults['language'],
			'theme'           => in_array( $value['theme'] ?? null, array( 'auto', 'light', 'dark' ), true ) ? (string) $value['theme'] : $defaults['theme'],
			'density'         => in_array( $value['density'] ?? null, array( 'comfortable', 'compact' ), true ) ? (string) $value['density'] : $defaults['density'],
			'delete_data'     => true === ( $value['delete_data'] ?? false ),
		);
	}

	/**
	 * Sanitizes a Settings API submission.
	 *
	 * @param mixed $value Untrusted Settings API value.
	 * @return array{service_enabled:bool,language:string,theme:string,density:string,delete_data:bool}
	 */
	public function sanitize( mixed $value ): array {
		$value = is_array( $value ) ? $value : array();

		return array(
			'service_enabled' => true === ( $value['service_enabled'] ?? false ) || '1' === ( $value['service_enabled'] ?? null ),
			'language'        => in_array( $value['language'] ?? null, array( 'auto', 'de', 'en' ), true ) ? (string) $value['language'] : 'auto',
			'theme'           => in_array( $value['theme'] ?? null, array( 'auto', 'light', 'dark' ), true ) ? (string) $value['theme'] : 'auto',
			'density'         => in_array( $value['density'] ?? null, array( 'comfortable', 'compact' ), true ) ? (string) $value['density'] : 'comfortable',
			'delete_data'     => true === ( $value['delete_data'] ?? false ) || '1' === ( $value['delete_data'] ?? null ),
		);
	}

	/** Reports whether an administrator approved the service. */
	public function is_service_enabled(): bool {
		return $this->get()['service_enabled'];
	}

	/**
	 * Returns defaults applied only to newly created embeds.
	 *
	 * @return array{language:string,theme:string,density:string} Setup defaults.
	 */
	public function get_setup_defaults(): array {
		$settings = $this->get();

		return array(
			'language' => $settings['language'],
			'theme'    => $settings['theme'],
			'density'  => $settings['density'],
		);
	}
}
