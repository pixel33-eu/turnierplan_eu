<?php
/**
 * Configuration validation exception.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

use InvalidArgumentException;

/**
 * Reports one invalid configuration field without retaining its raw value.
 */
final class ConfigException extends InvalidArgumentException {

	/**
	 * Invalid field name.
	 *
	 * @var string
	 */
	private string $field;

	/**
	 * Creates a field-specific validation error.
	 *
	 * @param string $field   Invalid field name.
	 * @param string $message Safe diagnostic message.
	 */
	private function __construct( string $field, string $message ) {
		parent::__construct( $message );

		$this->field = $field;
	}

	/**
	 * Creates a validation exception for an internal schema field name.
	 *
	 * Neither argument is rendered directly. Presentation layers translate a
	 * stable field name or error category and escape it for their own context.
	 *
	 * @param string $field   Internal field identifier.
	 * @param string $message Developer-facing diagnostic.
	 * @return self
	 */
	public static function for_field( string $field, string $message ): self {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The exception is internal and never rendered directly.
		return new self( $field, $message );
	}

	/**
	 * Returns the invalid field name.
	 *
	 * @return string
	 */
	public function get_field(): string {
		return $this->field;
	}
}
