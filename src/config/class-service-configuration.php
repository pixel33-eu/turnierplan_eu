<?php
/**
 * Fixed Turnierplan.eu service configuration.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

/**
 * Owns the trusted service origin used by every URL builder.
 */
final class ServiceConfiguration {

	public const PRODUCTION_ORIGIN = 'https://www.turnierplan.eu';

	/**
	 * Trusted service origin.
	 *
	 * @var string
	 */
	private string $origin;

	/**
	 * Creates a trusted service configuration.
	 *
	 * @param string $origin Trusted origin.
	 */
	private function __construct( string $origin ) {
		$this->origin = $origin;
	}

	/**
	 * Returns the immutable production configuration.
	 *
	 * @return self
	 */
	public static function production(): self {
		return new self( self::PRODUCTION_ORIGIN );
	}

	/**
	 * Returns the trusted origin without a trailing slash.
	 *
	 * @return string
	 */
	public function get_origin(): string {
		return $this->origin;
	}
}
