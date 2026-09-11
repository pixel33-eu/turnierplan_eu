<?php
/**
 * PHPUnit bootstrap.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

$tpeu_autoload_file = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $tpeu_autoload_file ) ) {
	throw new RuntimeException( 'Run composer install before executing the PHP test suite.' );
}

require_once $tpeu_autoload_file;
