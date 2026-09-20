<?php
/**
 * Tests for tournament reference normalization.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\TournamentReference;

/**
 * Verifies all documented reference input shapes and trust boundaries.
 */
final class TournamentReferenceTest extends TestCase {

	/**
	 * Supplies supported reference inputs.
	 *
	 * @return iterable<string, array{string, string}>
	 */
	public static function supported_references(): iterable {
		yield 'numeric ID' => array( '12345', '12345' );
		yield 'slug normalized to lowercase' => array( 'Sommer-Cup-2026', 'sommer-cup-2026' );
		yield 'canonical reference' => array(
			'TRN_01K4F6Y7M8N9P0Q1R2S3T4V5WX',
			'trn_01k4f6y7m8n9p0q1r2s3t4v5wx',
		);
		yield 'public route URL' => array(
			'https://WWW.TURNIERPLAN.EU/t/Sommer-Cup-2026',
			'sommer-cup-2026',
		);
		yield 'legacy live URL' => array(
			'https://www.turnierplan.eu/live.php?id=987',
			'987',
		);
	}

	/**
	 * Supported inputs reduce to a bounded reference.
	 *
	 * @param string $input    Input value.
	 * @param string $expected Normalized reference.
	 * @return void
	 */
	#[DataProvider( 'supported_references' )]
	public function test_normalizes_supported_references( string $input, string $expected ): void {
		$this->assertSame( $expected, TournamentReference::normalize( $input ) );
	}

	/**
	 * Supplies values that must never reach a service URL.
	 *
	 * @return iterable<string, array{string}>
	 */
	public static function rejected_references(): iterable {
		yield 'HTTP URL' => array( 'http://www.turnierplan.eu/t/cup' );
		yield 'foreign host' => array( 'https://example.org/t/cup' );
		yield 'host suffix attack' => array( 'https://www.turnierplan.eu.example/t/cup' );
		yield 'trailing host dot' => array( 'https://www.turnierplan.eu./t/cup' );
		yield 'credentials' => array( 'https://user@www.turnierplan.eu/t/cup' );
		yield 'explicit default port' => array( 'https://www.turnierplan.eu:443/t/cup' );
		yield 'foreign port' => array( 'https://www.turnierplan.eu:444/t/cup' );
		yield 'fragment' => array( 'https://www.turnierplan.eu/t/cup#details' );
		yield 'route query' => array( 'https://www.turnierplan.eu/t/cup?preview=1' );
		yield 'unknown live query' => array( 'https://www.turnierplan.eu/live.php?id=7&x=1' );
		yield 'duplicate live query' => array( 'https://www.turnierplan.eu/live.php?id=7&id=8' );
		yield 'encoded segment' => array( 'https://www.turnierplan.eu/t/cup%252fsecret' );
		yield 'leading zero' => array( '0123' );
		yield 'repeated slug separator' => array( 'sommer--cup' );
		yield 'control character' => array( "cup\nnext" );
		yield 'empty' => array( '   ' );
	}

	/**
	 * Unsupported inputs fail before URL construction.
	 *
	 * @param string $input Input value.
	 * @return void
	 */
	#[DataProvider( 'rejected_references' )]
	public function test_rejects_unsupported_references( string $input ): void {
		$this->expectException( ConfigException::class );
		TournamentReference::normalize( $input );
	}
}
