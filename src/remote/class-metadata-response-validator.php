<?php
/**
 * Metadata response validation.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Validates the required V1 response shape before it can enter the cache.
 */
final class MetadataResponseValidator {

	private const LANGUAGE_PATTERN    = '/^[a-z]{2,3}(?:-[A-Z]{2})?$/';
	private const TOURNAMENT_PATTERN  = '/^trn_[0-9a-hjkmnp-tv-z]{26}$/';
	private const GROUP_PATTERN       = '/^grp_[0-9a-hjkmnp-tv-z]{26}$/';
	private const PARTICIPANT_PATTERN = '/^ptc_[0-9a-hjkmnp-tv-z]{26}$/';

	/**
	 * Checks a decoded response.
	 *
	 * @param mixed $value Decoded JSON value.
	 * @return bool Whether the value satisfies required V1 fields.
	 */
	public function is_valid( mixed $value ): bool {
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			return false;
		}

		foreach (
			array(
				'schema_version',
				'response_language',
				'tournament',
				'supported_languages',
				'views',
				'groups',
				'participants',
				'branding',
			) as $field
		) {
			if ( ! array_key_exists( $field, $value ) ) {
				return false;
			}
		}

		return 1 === $value['schema_version']
			&& $this->is_language( $value['response_language'] )
			&& $this->is_tournament( $value['tournament'] )
			&& $this->is_language_list( $value['supported_languages'] )
			&& $this->is_views( $value['views'] )
			&& $this->is_groups( $value['groups'] )
			&& $this->is_participants( $value['participants'] )
			&& $this->is_branding( $value['branding'] );
	}

	/** Checks the tournament object. */
	private function is_tournament( mixed $value ): bool {
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			return false;
		}

		foreach (
			array(
				'ref',
				'title',
				'state',
				'default_language',
				'timezone',
				'starts_on',
				'ends_on',
				'public_url',
				'updated_at',
				'score_unit',
				'refresh_interval_seconds',
			) as $field
		) {
			if ( ! array_key_exists( $field, $value ) ) {
				return false;
			}
		}

		$refresh = $value['refresh_interval_seconds'];

		return $this->matches( $value['ref'], self::TOURNAMENT_PATTERN )
			&& $this->is_label( $value['title'] )
			&& in_array( $value['state'], array( 'upcoming', 'live', 'completed', 'cancelled' ), true )
			&& $this->is_language( $value['default_language'] )
			&& is_string( $value['timezone'] )
			&& in_array( $value['timezone'], DateTimeZone::listIdentifiers(), true )
			&& $this->is_date_or_null( $value['starts_on'] )
			&& $this->is_date_or_null( $value['ends_on'] )
			&& is_string( $value['public_url'] )
			&& 1 === preg_match( '#^https://www\.turnierplan\.eu/t/(?:trn_[0-9a-hjkmnp-tv-z]{26}|[a-z0-9]+(?:-[a-z0-9]+)*)$#', $value['public_url'] )
			&& is_string( $value['updated_at'] )
			&& 1 === preg_match( '/^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])T(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]Z$/', $value['updated_at'] )
			&& in_array( $value['score_unit'], array( 'goals', 'sets', 'points', 'time', 'custom' ), true )
			&& ( null === $refresh || ( is_int( $refresh ) && $refresh >= 30 && $refresh <= 300 ) );
	}

	/** Checks the non-empty unique language list. */
	private function is_language_list( mixed $value ): bool {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || array() === $value ) {
			return false;
		}

		foreach ( $value as $language ) {
			if ( ! $this->is_language( $language ) ) {
				return false;
			}
		}

		return count( $value ) === count( array_unique( $value ) );
	}

	/** Checks available view capabilities. */
	private function is_views( mixed $value ): bool {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || array() === $value || count( $value ) > 2 ) {
			return false;
		}

		$view_ids = array();

		foreach ( $value as $view ) {
			if (
				! is_array( $view )
				|| ! isset( $view['id'], $view['filters'], $view['options'] )
				|| ! in_array( $view['id'], array( 'standings', 'matches' ), true )
				|| ! $this->is_enum_list( $view['filters'], array( 'group', 'participant', 'match_number_range', 'date_range' ) )
				|| ! $this->is_enum_list(
					$view['options'],
					array(
						'team_logos',
						'played',
						'wins_draws_losses',
						'score_balance',
						'points',
						'group_navigation',
						'match_number',
						'date',
						'time',
						'field',
						'group',
						'round',
						'referee',
						'live_state',
						'extra_time',
						'penalty_result',
					)
				)
			) {
				return false;
			}

			$view_ids[] = $view['id'];
		}

		return count( $view_ids ) === count( array_unique( $view_ids ) );
	}

	/** Checks public group entries. */
	private function is_groups( mixed $value ): bool {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) > 500 ) {
			return false;
		}

		foreach ( $value as $group ) {
			if (
				! is_array( $group )
				|| ! $this->matches( $group['id'] ?? null, self::GROUP_PATTERN )
				|| ! $this->is_label( $group['label'] ?? null )
			) {
				return false;
			}
		}

		return true;
	}

	/** Checks public participant entries. */
	private function is_participants( mixed $value ): bool {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) > 2000 ) {
			return false;
		}

		foreach ( $value as $participant ) {
			if (
				! is_array( $participant )
				|| ! $this->matches( $participant['id'] ?? null, self::PARTICIPANT_PATTERN )
				|| ! $this->is_label( $participant['label'] ?? null )
				|| ! isset( $participant['group_ids'] )
				|| ! is_array( $participant['group_ids'] )
				|| ! array_is_list( $participant['group_ids'] )
				|| count( $participant['group_ids'] ) > 50
			) {
				return false;
			}

			foreach ( $participant['group_ids'] as $group_id ) {
				if ( ! $this->matches( $group_id, self::GROUP_PATTERN ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/** Checks the server-controlled branding policy. */
	private function is_branding( mixed $value ): bool {
		if ( ! is_array( $value ) || ! isset( $value['policy'], $value['default_visible'] ) ) {
			return false;
		}

		if ( ! in_array( $value['policy'], array( 'required', 'optional', 'hidden' ), true ) || ! is_bool( $value['default_visible'] ) ) {
			return false;
		}

		return ! ( 'required' === $value['policy'] && false === $value['default_visible'] )
			&& ! ( 'hidden' === $value['policy'] && true === $value['default_visible'] );
	}

	/**
	 * Checks a unique list against an enum.
	 *
	 * @param mixed        $value   Candidate list.
	 * @param list<string> $allowed Allowed values.
	 * @return bool Whether every value is allowed and unique.
	 */
	private function is_enum_list( mixed $value, array $allowed ): bool {
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( ! is_string( $item ) || ! in_array( $item, $allowed, true ) ) {
				return false;
			}
		}

		return count( $value ) === count( array_unique( $value ) );
	}

	/** Checks a contract language code. */
	private function is_language( mixed $value ): bool {
		return $this->matches( $value, self::LANGUAGE_PATTERN );
	}

	/** Checks a non-empty bounded label. */
	private function is_label( mixed $value ): bool {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		$characters = preg_match_all( '/./us', $value );

		return false !== $characters && $characters <= 200;
	}

	/** Checks a nullable real ISO calendar date. */
	private function is_date_or_null( mixed $value ): bool {
		if ( null === $value ) {
			return true;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value ) ) {
			return false;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		return false !== $date && $date->format( 'Y-m-d' ) === $value;
	}

	/** Matches a string against one contract pattern. */
	private function matches( mixed $value, string $pattern ): bool {
		return is_string( $value ) && 1 === preg_match( $pattern, $value );
	}
}
