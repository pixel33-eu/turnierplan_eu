<?php
/**
 * Tests for cached semantic filter validation.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Config\CachedMetadataValidator;
use TurnierplanEU\WordPress\Config\EmbedConfig;

/**
 * Verifies local warnings without any remote access.
 */
final class CachedMetadataValidatorTest extends TestCase {

	/**
	 * Stale cached choices are marked without changing the configuration.
	 *
	 * @return void
	 */
	public function test_marks_stale_cached_filters(): void {
		$config   = EmbedConfig::from_array(
			array(
				'tournamentRef' => '12345',
				'view'          => 'matches',
				'group'         => 'grp_01k4f70bcde2fgh3jkm4npq5rs',
				'participant'   => 'ptc_01k4f71bcde2fgh3jkm4npq5rs',
			)
		);
		$metadata = array(
			'views'        => array( array( 'id' => 'standings' ) ),
			'groups'       => array( array( 'id' => 'grp_01k4f70bcde2fgh3jkm4npq5rt' ) ),
			'participants' => array(),
			'tournament'   => array( 'timezone' => 'Europe/Berlin' ),
		);

		$this->assertSame(
			array(
				array(
					'code'  => 'view_unavailable',
					'field' => 'view',
				),
				array(
					'code'  => 'filter_ignored',
					'field' => 'group',
				),
				array(
					'code'  => 'filter_ignored',
					'field' => 'participant',
				),
			),
			CachedMetadataValidator::warnings( $config, $metadata )
		);
		$this->assertSame(
			'grp_01k4f70bcde2fgh3jkm4npq5rs',
			$config->get( 'group' ),
			'Cached warnings must not silently rewrite stored filters.'
		);
		$this->assertTrue( CachedMetadataValidator::has_valid_timezone( $metadata ) );
	}

	/**
	 * Missing cached lists do not pretend that a filter is invalid.
	 *
	 * @return void
	 */
	public function test_does_not_warn_without_cached_filter_lists(): void {
		$config = EmbedConfig::from_array(
			array(
				'tournamentRef' => '12345',
				'group'         => 'grp_01k4f70bcde2fgh3jkm4npq5rs',
			)
		);

		$this->assertSame( array(), CachedMetadataValidator::warnings( $config, array() ) );
		$this->assertFalse(
			CachedMetadataValidator::has_valid_timezone(
				array( 'tournament' => array( 'timezone' => 'Europe/Invalid' ) )
			)
		);
	}
}
