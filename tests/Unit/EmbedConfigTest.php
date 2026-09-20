<?php
/**
 * Tests for the central embed configuration.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;

/**
 * Verifies normalization, contract defaults, and cross-field constraints.
 */
final class EmbedConfigTest extends TestCase {

	private const REFERENCE = 'trn_01k4f6y7m8n9p0q1r2s3t4v5wx';

	/**
	 * Compact unversioned data migrates to the complete V1 representation.
	 *
	 * @return void
	 */
	public function test_migrates_unversioned_compact_configuration_to_v1(): void {
		$config = EmbedConfig::from_array(
			array(
				'tournamentRef' => self::REFERENCE,
				'language'      => 'DE-de',
				'accentColor'   => '#16a34a',
			)
		);
		$values = $config->to_array();

		$this->assertSame( 1, $values['schemaVersion'] );
		$this->assertSame( self::REFERENCE, $values['tournamentRef'] );
		$this->assertSame( 'standings', $values['view'] );
		$this->assertSame( 'de-DE', $values['language'] );
		$this->assertSame( '#16A34A', $values['accentColor'] );
		$this->assertTrue( $values['showTeamLogos'] );
		$this->assertSame( 240, $values['minHeight'] );
		$this->assertSame( 4000, $values['maxHeight'] );
		$this->assertSame( 'schemaVersion', array_key_first( $values ) );
		$this->assertSame( 'tournamentRef', array_keys( $values )[1] );
	}

	/**
	 * Setup defaults affect only a newly created configuration.
	 *
	 * @return void
	 */
	public function test_setup_defaults_do_not_change_existing_compact_configuration(): void {
		$new      = EmbedConfig::for_new(
			self::REFERENCE,
			array(
				'theme'     => 'dark',
				'minHeight' => 360,
			)
		);
		$existing = EmbedConfig::from_array(
			array( 'tournamentRef' => self::REFERENCE )
		);

		$this->assertSame( 'dark', $new->get( 'theme' ) );
		$this->assertSame( 360, $new->get( 'minHeight' ) );
		$this->assertSame( 'auto', $existing->get( 'theme' ) );
		$this->assertSame( 240, $existing->get( 'minHeight' ) );
	}

	/**
	 * Supplies invalid configurations and the field that must be reported.
	 *
	 * @return iterable<string, array{array<string, mixed>, string}>
	 */
	public static function invalid_configurations(): iterable {
		$matches = array(
			'tournamentRef' => self::REFERENCE,
			'view'          => 'matches',
		);

		yield 'unknown schema' => array( array_merge( $matches, array( 'schemaVersion' => 2 ) ), 'schemaVersion' );
		yield 'unknown field' => array( array_merge( $matches, array( 'iframeUrl' => 'https://evil.example' ) ), 'iframeUrl' );
		yield 'string boolean' => array( array_merge( $matches, array( 'showBranding' => 'false' ) ), 'showBranding' );
		yield 'invalid enum' => array( array_merge( $matches, array( 'theme' => 'neon' ) ), 'theme' );
		yield 'invalid color' => array( array_merge( $matches, array( 'accentColor' => '#abcd' ) ), 'accentColor' );
		yield 'impossible date' => array( array_merge( $matches, array( 'dateFrom' => '2026-02-30' ) ), 'dateFrom' );
		yield 'reversed dates' => array(
			array_merge(
				$matches,
				array(
					'dateFrom' => '2026-09-20',
					'dateTo'   => '2026-09-19',
				)
			),
			'dateTo',
		);
		yield 'reversed matches' => array(
			array_merge(
				$matches,
				array(
					'matchFrom' => 12,
					'matchTo'   => 4,
				)
			),
			'matchTo',
		);
		yield 'height relation' => array(
			array_merge(
				$matches,
				array(
					'minHeight' => 900,
					'maxHeight' => 800,
				)
			),
			'maxHeight',
		);
		yield 'standings participant' => array(
			array(
				'tournamentRef' => self::REFERENCE,
				'participant'   => 'ptc_01k4f71bcde2fgh3jkm4npq5rs',
			),
			'participant',
		);
		yield 'injected timezone' => array(
			array_merge( $matches, array( 'timezone' => 'Europe/Berlin' ) ),
			'timezone',
		);
	}

	/**
	 * Invalid data reports a stable local field without storing raw input.
	 *
	 * @param array<string, mixed> $input Configuration input.
	 * @param string               $field Expected invalid field.
	 * @return void
	 */
	#[DataProvider( 'invalid_configurations' )]
	public function test_rejects_invalid_configuration( array $input, string $field ): void {
		try {
			EmbedConfig::from_array( $input );
			$this->fail( 'Expected configuration validation to fail.' );
		} catch ( ConfigException $exception ) {
			$this->assertSame( $field, $exception->get_field() );
		}
	}
}
