<?php
/**
 * Loads plugin classes without a runtime package dependency.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress;

/**
 * Minimal namespace autoloader using WordPress class filenames.
 */
final class Autoloader {

	private const PREFIX = __NAMESPACE__ . '\\';

	/**
	 * Registers the class loader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Loads one class from the src directory.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load( string $class_name ): void {
		if ( 0 !== strncmp( $class_name, self::PREFIX, strlen( self::PREFIX ) ) ) {
			return;
		}

		$relative_name = substr( $class_name, strlen( self::PREFIX ) );

		if ( ! preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative_name ) ) {
			return;
		}

		$name_parts = explode( '\\', $relative_name );
		$class_name = array_pop( $name_parts );
		$class_file = preg_replace( '/(?<!^)[A-Z]/', '-$0', (string) $class_name );
		$directory  = array_map( 'strtolower', $name_parts );
		$file       = __DIR__ . '/';

		if ( array() !== $directory ) {
			$file .= implode( '/', $directory ) . '/';
		}

		$file .= 'class-' . strtolower( (string) $class_file ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
