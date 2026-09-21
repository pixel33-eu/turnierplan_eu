<?php
/**
 * External service approval contract.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Settings;

/**
 * Allows the metadata gateway to enforce site-level administrator approval.
 */
interface ServiceApproval {

	/** Reports whether an administrator approved the external service. */
	public function is_service_enabled(): bool;
}
