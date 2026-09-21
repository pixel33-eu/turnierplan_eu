<?php
/**
 * Tests for canonical shortcode generation.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Config\EmbedSelection;
use TurnierplanEU\WordPress\Shortcode\ShortcodeGenerator;

/**
 * Verifies compact output, stable order, and explicit view attributes.
 */
final class ShortcodeGeneratorTest extends TestCase {

	/** A standard inline selection stays compact and keeps its explicit view. */
	public function test_generates_compact_inline_shortcode(): void {
		$selection = EmbedSelection::from_array(
			array(
				'config' => array(
					'tournamentRef' => 'Sommer-Cup',
					'view'          => 'standings',
				),
			)
		);

		$this->assertSame(
			'[turnierplan tournament="sommer-cup" view="standings"]',
			( new ShortcodeGenerator() )->generate( $selection )
		);
	}

	/** Non-default presentation and filter values appear in canonical order. */
	public function test_generates_complete_changed_matches_shortcode(): void {
		$selection = EmbedSelection::from_array(
			array(
				'config' => array(
					'tournamentRef' => '123',
					'view'          => 'matches',
					'language'      => 'de',
					'group'         => 'grp_01k4f70bcde2fgh3jkm4npq5rs',
					'theme'         => 'dark',
					'density'       => 'compact',
					'accentColor'   => '#16A34A',
					'showField'     => false,
					'showDate'      => 'show',
					'showBranding'  => false,
					'minHeight'     => 320,
				),
			)
		);

		$this->assertSame(
			'[turnierplan tournament="123" view="matches" lang="de" group="grp_01k4f70bcde2fgh3jkm4npq5rs" theme="dark" density="compact" accent="#16A34A" min_height="320" date="show" branding="hide" show="match_number,time,group,round,referee,live_state,extra_time,penalty_result"]',
			( new ShortcodeGenerator() )->generate( $selection )
		);
	}

	/** A validated preset selection has one unambiguous attribute. */
	public function test_generates_preset_shortcode(): void {
		$selection = EmbedSelection::from_array( array( 'presetId' => 42 ) );

		$this->assertSame(
			'[turnierplan preset="42"]',
			( new ShortcodeGenerator() )->generate( $selection )
		);
	}
}
