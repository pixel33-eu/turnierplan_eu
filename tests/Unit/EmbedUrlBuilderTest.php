<?php
/**
 * Tests for deterministic trusted service URLs.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;
use TurnierplanEU\WordPress\Config\EmbedUrlBuilder;
use TurnierplanEU\WordPress\Config\ServiceConfiguration;

/**
 * Verifies mapping, ordering, encoding, and URL trust boundaries.
 */
final class EmbedUrlBuilderTest extends TestCase {

	private const INSTANCE = '550e8400-e29b-41d4-a716-446655440000';

	/**
	 * The full matches mapping produces one deterministic URL.
	 *
	 * @return void
	 */
	public function test_builds_deterministic_matches_url(): void {
		$config  = EmbedConfig::from_array(
			array(
				'tournamentRef'     => 'sommer-cup-2026',
				'view'              => 'matches',
				'language'          => 'de',
				'group'             => 'grp_01k4f70bcde2fgh3jkm4npq5rs',
				'participant'       => 'ptc_01k4f71bcde2fgh3jkm4npq5rs',
				'matchFrom'         => 4,
				'matchTo'           => 12,
				'dateFrom'          => '2026-09-12',
				'dateTo'            => '2026-09-13',
				'theme'             => 'dark',
				'density'           => 'compact',
				'accentColor'       => '#16A34A',
				'showField'         => false,
				'showReferee'       => false,
				'showPenaltyResult' => false,
				'showDate'          => 'show',
				'showBranding'      => false,
				'openLinksInNewTab' => false,
			)
		);
		$builder = new EmbedUrlBuilder( ServiceConfiguration::production() );

		$this->assertSame(
			'https://www.turnierplan.eu/embed/v1/tournaments/sommer-cup-2026'
			. '?view=matches&lang=de'
			. '&group=grp_01k4f70bcde2fgh3jkm4npq5rs'
			. '&participant=ptc_01k4f71bcde2fgh3jkm4npq5rs'
			. '&match_from=4&match_to=12&date_from=2026-09-12&date_to=2026-09-13'
			. '&theme=dark&density=compact&accent=16a34a'
			. '&show=match_number%2Ctime%2Cgroup%2Cround%2Clive_state%2Cextra_time'
			. '&date=show&branding=hide&links=same-tab'
			. '&instance=550e8400-e29b-41d4-a716-446655440000'
			. '&parent_origin=https%3A%2F%2Fverein.example',
			$builder->frame_url( $config, self::INSTANCE, 'https://verein.example' )
		);
	}

	/**
	 * An explicit all-hidden list is emitted as show=.
	 *
	 * @return void
	 */
	public function test_keeps_empty_show_distinct_from_default_show(): void {
		$config  = EmbedConfig::from_array(
			array(
				'tournamentRef'         => '12345',
				'showTeamLogos'         => false,
				'showPlayed'            => false,
				'showWinsDrawsLosses'   => false,
				'showScoreBalance'      => false,
				'showPoints'            => false,
				'enableGroupNavigation' => false,
			)
		);
		$builder = new EmbedUrlBuilder( ServiceConfiguration::production() );
		$url     = $builder->frame_url( $config, self::INSTANCE, 'https://verein.example' );

		$this->assertStringContainsString( '&show=&branding=show', $url );
		$this->assertStringNotContainsString( 'minHeight', $url );
		$this->assertStringNotContainsString( 'maxHeight', $url );
	}

	/**
	 * Metadata and fallback URLs share the fixed production origin.
	 *
	 * @return void
	 */
	public function test_builds_metadata_and_public_urls_from_trusted_origin(): void {
		$builder = new EmbedUrlBuilder( ServiceConfiguration::production() );

		$this->assertSame(
			'https://www.turnierplan.eu/api/embed/v1/tournaments/12345/metadata?lang=de-DE',
			$builder->metadata_url( 'https://www.turnierplan.eu/live.php?id=12345', 'de-de' )
		);
		$this->assertSame(
			'https://www.turnierplan.eu/t/sommer-cup',
			$builder->public_url( 'Sommer-Cup' )
		);
	}

	/**
	 * Supplies invalid browser transport values.
	 *
	 * @return iterable<string, array{string, string}>
	 */
	public static function invalid_transport_values(): iterable {
		yield 'wrong UUID version' => array( '550e8400-e29b-11d4-a716-446655440000', 'https://verein.example' );
		yield 'uppercase UUID' => array( strtoupper( self::INSTANCE ), 'https://verein.example' );
		yield 'HTTP parent' => array( self::INSTANCE, 'http://verein.example' );
		yield 'parent path' => array( self::INSTANCE, 'https://verein.example/path' );
		yield 'parent credentials' => array( self::INSTANCE, 'https://user@verein.example' );
		yield 'parent query' => array( self::INSTANCE, 'https://verein.example?x=1' );
	}

	/**
	 * Invalid transport values never enter a generated URL.
	 *
	 * @param string $instance Instance value.
	 * @param string $origin   Parent origin.
	 * @return void
	 */
	#[DataProvider( 'invalid_transport_values' )]
	public function test_rejects_invalid_transport_values( string $instance, string $origin ): void {
		$config  = EmbedConfig::from_array( array( 'tournamentRef' => '12345' ) );
		$builder = new EmbedUrlBuilder( ServiceConfiguration::production() );

		$this->expectException( ConfigException::class );
		$builder->frame_url( $config, $instance, $origin );
	}
}
