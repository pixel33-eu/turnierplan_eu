<?php
/**
 * Normalized transport failure.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

/**
 * Prevents low-level HTTP messages from reaching REST consumers.
 */
final class TransportFailure {

	/** Creates a failure with a stable internal code. */
	public function __construct( private readonly string $code ) {
	}

	/** Returns the stable failure code. */
	public function get_code(): string {
		return $this->code;
	}
}
