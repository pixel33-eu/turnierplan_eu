<?php
/**
 * Shared configuration field mappings.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

/**
 * Keeps token names and their canonical order in one place.
 */
final class EmbedConfigMap {

	/**
	 * Standings visibility tokens in canonical order.
	 *
	 * @var array<string, string>
	 */
	public const STANDINGS_SHOW_FIELDS = array(
		'team_logos'        => 'showTeamLogos',
		'played'            => 'showPlayed',
		'wins_draws_losses' => 'showWinsDrawsLosses',
		'score_balance'     => 'showScoreBalance',
		'points'            => 'showPoints',
		'group_navigation'  => 'enableGroupNavigation',
	);

	/**
	 * Match visibility tokens in canonical order.
	 *
	 * @var array<string, string>
	 */
	public const MATCH_SHOW_FIELDS = array(
		'match_number'   => 'showMatchNumber',
		'time'           => 'showTime',
		'field'          => 'showField',
		'group'          => 'showGroup',
		'round'          => 'showRound',
		'referee'        => 'showReferee',
		'live_state'     => 'showLiveState',
		'extra_time'     => 'showExtraTime',
		'penalty_result' => 'showPenaltyResult',
	);

	/**
	 * Returns the visibility mapping for one view.
	 *
	 * @param string $view Validated view identifier.
	 * @return array<string, string>
	 */
	public static function show_fields( string $view ): array {
		return 'matches' === $view
			? self::MATCH_SHOW_FIELDS
			: self::STANDINGS_SHOW_FIELDS;
	}
}
