<?php
/**
 * WordPress uninstall entry point.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/src/class-autoloader.php';

TurnierplanEU\WordPress\Autoloader::register();
TurnierplanEU\WordPress\Uninstaller::run();
