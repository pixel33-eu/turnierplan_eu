<?php
/**
 * Tests for settings sanitization.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Settings\SettingsRepository;

/**
 * Verifies fixed settings enums and opt-in behavior without WordPress state.
 */
final class SettingsRepositoryTest extends TestCase {

	/** Missing input keeps the external service disabled. */
	public function test_empty_submission_uses_safe_defaults(): void {
		$settings = ( new SettingsRepository() )->sanitize( array() );

		$this->assertFalse( $settings['service_enabled'] );
		$this->assertFalse( $settings['delete_data'] );
		$this->assertSame( 'auto', $settings['language'] );
		$this->assertSame( 'auto', $settings['theme'] );
		$this->assertSame( 'comfortable', $settings['density'] );
	}

	/** Allowed values and explicit checkboxes survive sanitization. */
	public function test_accepts_only_documented_settings(): void {
		$repository = new SettingsRepository();
		$settings   = $repository->sanitize(
			array(
				'service_enabled' => '1',
				'language'        => 'en',
				'theme'           => 'dark',
				'density'         => 'compact',
				'delete_data'     => '1',
				'service_url'     => 'http://127.0.0.1/private',
			)
		);

		$this->assertSame(
			array(
				'service_enabled' => true,
				'language'        => 'en',
				'theme'           => 'dark',
				'density'         => 'compact',
				'delete_data'     => true,
			),
			$settings
		);
		$this->assertArrayNotHasKey( 'service_url', $settings );
	}

	/** Unknown enum values fall back rather than entering URL configuration. */
	public function test_rejects_unknown_default_values(): void {
		$settings = ( new SettingsRepository() )->sanitize(
			array(
				'language' => 'javascript:alert(1)',
				'theme'    => 'custom',
				'density'  => 'tiny',
			)
		);

		$this->assertSame( 'auto', $settings['language'] );
		$this->assertSame( 'auto', $settings['theme'] );
		$this->assertSame( 'comfortable', $settings['density'] );
	}
}
