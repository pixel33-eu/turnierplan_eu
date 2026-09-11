<?php
/**
 * Tests for runtime version requirements.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Requirements;

/**
 * Verifies the supported WordPress and PHP version boundary.
 */
final class RequirementsTest extends TestCase {

	/**
	 * Exact minimum versions are accepted.
	 *
	 * @return void
	 */
	public function test_accepts_exact_minimum_versions(): void {
		$this->assertSame( array(), Requirements::unmet_requirements( '8.3.0', '6.5' ) );
	}

	/**
	 * Newer runtime versions are accepted.
	 *
	 * @return void
	 */
	public function test_accepts_newer_versions(): void {
		$this->assertSame( array(), Requirements::unmet_requirements( '8.5.1', '7.0-alpha-1' ) );
	}

	/**
	 * Both outdated runtime components are reported together.
	 *
	 * @return void
	 */
	public function test_reports_all_outdated_components(): void {
		$this->assertSame(
			array(
				'php'       => array(
					'current'  => '8.2.29',
					'required' => '8.3',
				),
				'wordpress' => array(
					'current'  => '6.4.7',
					'required' => '6.5',
				),
			),
			Requirements::unmet_requirements( '8.2.29', '6.4.7' )
		);
	}

	/**
	 * A development suffix does not make an old WordPress release valid.
	 *
	 * @return void
	 */
	public function test_rejects_prerelease_below_minimum(): void {
		$failures = Requirements::unmet_requirements( '8.3.0', '6.5-beta1' );

		$this->assertArrayHasKey( 'wordpress', $failures );
	}
}
