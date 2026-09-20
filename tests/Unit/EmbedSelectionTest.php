<?php
/**
 * Tests for inline, preset, and shortcode configuration mapping.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedSelection;
use TurnierplanEU\WordPress\Config\ShortcodeConfigMapper;

/**
 * Ensures every input path reaches the same normalized model.
 */
final class EmbedSelectionTest extends TestCase {

	private const REFERENCE = 'trn_01k4f6y7m8n9p0q1r2s3t4v5wx';

	/**
	 * Preset references cannot be combined with inline values.
	 *
	 * @return void
	 */
	public function test_rejects_mixed_preset_and_inline_configuration(): void {
		$this->expectException( ConfigException::class );

		EmbedSelection::from_array(
			array(
				'presetId' => 87,
				'config'   => array( 'tournamentRef' => self::REFERENCE ),
			)
		);
	}

	/**
	 * A preset-only shortcode produces a preset-only selection.
	 *
	 * @return void
	 */
	public function test_maps_preset_shortcode_without_inline_defaults(): void {
		$selection = ShortcodeConfigMapper::from_attributes(
			array(
				'preset'  => '87',
				'ignored' => 'value',
			),
			false
		);

		$this->assertTrue( $selection->is_preset() );
		$this->assertSame( 87, $selection->get_preset_id() );
		$this->assertNull( $selection->get_config() );
		$this->assertSame(
			array( 'preset' => '87' ),
			ShortcodeConfigMapper::to_attributes( $selection )
		);
	}

	/**
	 * Equivalent shortcode and block inputs produce the same configuration.
	 *
	 * @return void
	 */
	public function test_shortcode_and_block_inputs_normalize_equally(): void {
		$shortcode = ShortcodeConfigMapper::from_attributes(
			array(
				'tournament'  => self::REFERENCE,
				'view'        => 'matches',
				'lang'        => 'de',
				'group'       => 'grp_01k4f70bcde2fgh3jkm4npq5rs',
				'participant' => 'ptc_01k4f71bcde2fgh3jkm4npq5rs',
				'match_from'  => '4',
				'match_to'    => '12',
				'date_from'   => '2026-09-12',
				'date_to'     => '2026-09-13',
				'theme'       => 'dark',
				'density'     => 'compact',
				'accent'      => '16a34a',
				'branding'    => 'hide',
				'links'       => 'same-tab',
				'min_height'  => '320',
				'max_height'  => '5000',
				'date'        => 'show',
				'show'        => 'match_number,time,group,round,live_state',
			),
			false
		);
		$block     = EmbedSelection::from_array(
			array(
				'presetId' => 0,
				'config'   => array(
					'tournamentRef'     => self::REFERENCE,
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
					'showBranding'      => false,
					'openLinksInNewTab' => false,
					'minHeight'         => 320,
					'maxHeight'         => 5000,
					'showMatchNumber'   => true,
					'showTime'          => true,
					'showField'         => false,
					'showGroup'         => true,
					'showRound'         => true,
					'showReferee'       => false,
					'showLiveState'     => true,
					'showExtraTime'     => false,
					'showPenaltyResult' => false,
					'showDate'          => 'show',
				),
			)
		);

		$this->assertSame(
			$block->get_config()?->to_array(),
			$shortcode->get_config()?->to_array()
		);
	}

	/**
	 * A fully default shortcode remains compact and stable.
	 *
	 * @return void
	 */
	public function test_canonical_default_shortcode_omits_display_defaults(): void {
		$selection = ShortcodeConfigMapper::from_attributes(
			array( 'tournament' => '12345' ),
			false
		);

		$this->assertSame(
			array(
				'tournament' => '12345',
				'view'       => 'standings',
			),
			ShortcodeConfigMapper::to_attributes( $selection )
		);
	}

	/**
	 * An explicit empty show list stays different from an omitted list.
	 *
	 * @return void
	 */
	public function test_explicit_empty_show_list_survives_round_trip(): void {
		$selection = ShortcodeConfigMapper::from_attributes(
			array(
				'tournament' => '12345',
				'show'       => '',
			),
			false
		);

		$this->assertSame( '', ShortcodeConfigMapper::to_attributes( $selection )['show'] );
	}
}
