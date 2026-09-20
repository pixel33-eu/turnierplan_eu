<?php
/**
 * Versioned embed configuration.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

use DateTimeImmutable;

/**
 * Immutable, normalized V1 configuration shared by every input path.
 */
final class EmbedConfig {

	public const SCHEMA_VERSION = 1;

	/**
	 * Contract defaults stay fixed for the complete V1 lifetime.
	 *
	 * @var array<string, bool|int|string|null>
	 */
	public const CONTRACT_DEFAULTS = array(
		'schemaVersion'         => self::SCHEMA_VERSION,
		'view'                  => 'standings',
		'language'              => 'auto',
		'group'                 => null,
		'participant'           => null,
		'matchFrom'             => null,
		'matchTo'               => null,
		'dateFrom'              => null,
		'dateTo'                => null,
		'theme'                 => 'auto',
		'density'               => 'comfortable',
		'accentColor'           => null,
		'showBranding'          => true,
		'openLinksInNewTab'     => true,
		'minHeight'             => 240,
		'maxHeight'             => 4000,
		'showTeamLogos'         => true,
		'showPlayed'            => true,
		'showWinsDrawsLosses'   => true,
		'showScoreBalance'      => true,
		'showPoints'            => true,
		'enableGroupNavigation' => true,
		'showMatchNumber'       => true,
		'showDate'              => 'auto',
		'showTime'              => true,
		'showField'             => true,
		'showGroup'             => true,
		'showRound'             => true,
		'showReferee'           => true,
		'showLiveState'         => true,
		'showExtraTime'         => true,
		'showPenaltyResult'     => true,
	);

	private const GROUP_PATTERN       = '/^grp_[0-9a-hjkmnp-tv-z]{26}$/';
	private const PARTICIPANT_PATTERN = '/^ptc_[0-9a-hjkmnp-tv-z]{26}$/';
	private const LANGUAGE_PATTERN    = '/^[a-z]{2,3}(?:-[A-Z]{2})?$/';
	private const DATE_PATTERN        = '/^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])$/';

	/**
	 * Fields that administrators may use to prefill a new configuration.
	 *
	 * @var list<string>
	 */
	private const SETUP_DEFAULT_FIELDS = array(
		'view',
		'language',
		'theme',
		'density',
		'accentColor',
		'showBranding',
		'openLinksInNewTab',
		'minHeight',
		'maxHeight',
		'showTeamLogos',
		'showPlayed',
		'showWinsDrawsLosses',
		'showScoreBalance',
		'showPoints',
		'enableGroupNavigation',
		'showMatchNumber',
		'showDate',
		'showTime',
		'showField',
		'showGroup',
		'showRound',
		'showReferee',
		'showLiveState',
		'showExtraTime',
		'showPenaltyResult',
	);

	/**
	 * Normalized values.
	 *
	 * @var array<string, bool|int|string|null>
	 */
	private array $values;

	/**
	 * Stores already validated values.
	 *
	 * @param array<string, bool|int|string|null> $values Normalized values.
	 */
	private function __construct( array $values ) {
		$this->values = $values;
	}

	/**
	 * Migrates and validates stored or block configuration values.
	 *
	 * Missing V1 fields receive contract defaults, never current setup defaults.
	 * Unversioned values are the only legacy shape and migrate directly to V1.
	 *
	 * @param array<string, mixed> $input Configuration input.
	 * @return self
	 * @throws ConfigException When a field or relation is invalid.
	 */
	public static function from_array( array $input ): self {
		$input = self::migrate( $input );
		self::reject_unknown_fields( $input );

		if ( ! array_key_exists( 'tournamentRef', $input ) ) {
			throw ConfigException::for_field( 'tournamentRef', 'Tournament reference is required.' );
		}

		$values                  = array_merge( self::CONTRACT_DEFAULTS, $input );
		$values['tournamentRef'] = TournamentReference::normalize( $values['tournamentRef'] );
		$values['view']          = self::enum( 'view', $values['view'], array( 'standings', 'matches' ) );
		$values['language']      = self::language( $values['language'] );
		$values['group']         = self::nullable_reference( 'group', $values['group'], self::GROUP_PATTERN );
		$values['participant']   = self::nullable_reference( 'participant', $values['participant'], self::PARTICIPANT_PATTERN );
		$values['matchFrom']     = self::nullable_integer( 'matchFrom', $values['matchFrom'], 1, 999999 );
		$values['matchTo']       = self::nullable_integer( 'matchTo', $values['matchTo'], 1, 999999 );
		$values['dateFrom']      = self::nullable_date( 'dateFrom', $values['dateFrom'] );
		$values['dateTo']        = self::nullable_date( 'dateTo', $values['dateTo'] );
		$values['theme']         = self::enum( 'theme', $values['theme'], array( 'auto', 'light', 'dark' ) );
		$values['density']       = self::enum( 'density', $values['density'], array( 'comfortable', 'compact' ) );
		$values['accentColor']   = self::accent_color( $values['accentColor'] );
		$values['showDate']      = self::enum( 'showDate', $values['showDate'], array( 'auto', 'show', 'hide' ) );
		$values['minHeight']     = self::integer( 'minHeight', $values['minHeight'], 160, 2000 );
		$values['maxHeight']     = self::integer( 'maxHeight', $values['maxHeight'], 300, 8000 );

		foreach ( self::boolean_fields() as $field ) {
			if ( ! is_bool( $values[ $field ] ) ) {
				throw ConfigException::for_field( $field, 'Configuration flag must be boolean.' );
			}
		}

		self::validate_relations( $values );

		$ordered = array(
			'schemaVersion' => self::SCHEMA_VERSION,
			'tournamentRef' => $values['tournamentRef'],
		);

		foreach ( array_keys( self::CONTRACT_DEFAULTS ) as $field ) {
			if ( 'schemaVersion' !== $field ) {
				$ordered[ $field ] = $values[ $field ];
			}
		}

		return new self( $ordered );
	}

	/**
	 * Creates a new configuration using current administrator setup defaults.
	 *
	 * Existing configurations must use from_array() so later setting changes do
	 * not alter their meaning.
	 *
	 * @param mixed                $tournament_reference Tournament input.
	 * @param array<string, mixed> $setup_defaults       Defaults for new items.
	 * @return self
	 * @throws ConfigException When a setup default is unknown or invalid.
	 */
	public static function for_new( mixed $tournament_reference, array $setup_defaults = array() ): self {
		$unknown = array_diff( array_keys( $setup_defaults ), self::SETUP_DEFAULT_FIELDS );

		if ( array() !== $unknown ) {
			throw ConfigException::for_field( (string) reset( $unknown ), 'Unknown setup default.' );
		}

		return self::from_array(
			array_merge(
				$setup_defaults,
				array(
					'schemaVersion' => self::SCHEMA_VERSION,
					'tournamentRef' => $tournament_reference,
				)
			)
		);
	}

	/**
	 * Returns one normalized field.
	 *
	 * @param string $field Field name.
	 * @return bool|int|string|null
	 * @throws ConfigException When the requested field does not exist.
	 */
	public function get( string $field ): bool|int|string|null {
		if ( ! array_key_exists( $field, $this->values ) ) {
			throw ConfigException::for_field( $field, 'Unknown configuration field.' );
		}

		return $this->values[ $field ];
	}

	/**
	 * Exports the complete schema in stable field order.
	 *
	 * @return array<string, bool|int|string|null>
	 */
	public function to_array(): array {
		return $this->values;
	}

	/**
	 * Adds the V1 marker to the unversioned pre-release shape.
	 *
	 * @param array<string, mixed> $input Raw configuration.
	 * @return array<string, mixed>
	 * @throws ConfigException When the schema version is unsupported.
	 */
	private static function migrate( array $input ): array {
		if ( ! array_key_exists( 'schemaVersion', $input ) ) {
			$input['schemaVersion'] = self::SCHEMA_VERSION;
		}

		if ( self::SCHEMA_VERSION !== $input['schemaVersion'] ) {
			throw ConfigException::for_field( 'schemaVersion', 'Unsupported configuration schema version.' );
		}

		return $input;
	}

	/**
	 * Rejects fields outside the versioned schema.
	 *
	 * @param array<string, mixed> $input Configuration input.
	 * @return void
	 * @throws ConfigException When an unknown field is present.
	 */
	private static function reject_unknown_fields( array $input ): void {
		$allowed = array_merge( array_keys( self::CONTRACT_DEFAULTS ), array( 'tournamentRef' ) );
		$unknown = array_diff( array_keys( $input ), $allowed );

		if ( array() !== $unknown ) {
			throw ConfigException::for_field( (string) reset( $unknown ), 'Unknown configuration field.' );
		}
	}

	/**
	 * Returns all boolean schema fields.
	 *
	 * @return list<string>
	 */
	private static function boolean_fields(): array {
		return array(
			'showBranding',
			'openLinksInNewTab',
			'showTeamLogos',
			'showPlayed',
			'showWinsDrawsLosses',
			'showScoreBalance',
			'showPoints',
			'enableGroupNavigation',
			'showMatchNumber',
			'showTime',
			'showField',
			'showGroup',
			'showRound',
			'showReferee',
			'showLiveState',
			'showExtraTime',
			'showPenaltyResult',
		);
	}

	/**
	 * Validates an enum value.
	 *
	 * @param string $field   Field name.
	 * @param mixed  $value   Candidate value.
	 * @param array  $allowed Allowed values.
	 * @phpstan-param list<string> $allowed
	 * @return string
	 * @throws ConfigException When the value is not allowed.
	 */
	private static function enum( string $field, mixed $value, array $allowed ): string {
		if ( ! is_string( $value ) || ! in_array( $value, $allowed, true ) ) {
			throw ConfigException::for_field( $field, 'Configuration value is not allowed.' );
		}

		return $value;
	}

	/**
	 * Normalizes one language code.
	 *
	 * @param mixed $value Candidate value.
	 * @return string
	 * @throws ConfigException When the language is malformed.
	 */
	private static function language( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			throw ConfigException::for_field( 'language', 'Language must be a string.' );
		}

		if ( 'auto' === strtolower( $value ) ) {
			return 'auto';
		}

		$parts    = explode( '-', $value, 2 );
		$language = strtolower( $parts[0] );

		if ( isset( $parts[1] ) ) {
			$language .= '-' . strtoupper( $parts[1] );
		}

		if ( 1 !== preg_match( self::LANGUAGE_PATTERN, $language ) ) {
			throw ConfigException::for_field( 'language', 'Language code is malformed.' );
		}

		return $language;
	}

	/**
	 * Validates an optional canonical reference.
	 *
	 * @param string $field   Field name.
	 * @param mixed  $value   Candidate value.
	 * @param string $pattern Required pattern.
	 * @return string|null
	 * @throws ConfigException When the reference is malformed.
	 */
	private static function nullable_reference( string $field, mixed $value, string $pattern ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( $pattern, strtolower( $value ) ) ) {
			throw ConfigException::for_field( $field, 'Filter reference is malformed.' );
		}

		return strtolower( $value );
	}

	/**
	 * Validates an optional bounded integer.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Candidate value.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @return int|null
	 * @throws ConfigException When the integer is outside the range.
	 */
	private static function nullable_integer( string $field, mixed $value, int $min, int $max ): ?int {
		return null === $value ? null : self::integer( $field, $value, $min, $max );
	}

	/**
	 * Validates a bounded integer without coercion.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Candidate value.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @return int
	 * @throws ConfigException When the integer is outside the range.
	 */
	private static function integer( string $field, mixed $value, int $min, int $max ): int {
		if ( ! is_int( $value ) || $value < $min || $value > $max ) {
			throw ConfigException::for_field( $field, 'Configuration integer is outside its allowed range.' );
		}

		return $value;
	}

	/**
	 * Validates a real ISO calendar date.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Candidate value.
	 * @return string|null
	 * @throws ConfigException When the date is malformed or impossible.
	 */
	private static function nullable_date( string $field, mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( self::DATE_PATTERN, $value ) ) {
			throw ConfigException::for_field( $field, 'Date must use YYYY-MM-DD.' );
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			throw ConfigException::for_field( $field, 'Date is not a real calendar day.' );
		}

		return $value;
	}

	/**
	 * Normalizes an optional six-digit color.
	 *
	 * @param mixed $value Candidate value.
	 * @return string|null
	 * @throws ConfigException When the color is malformed.
	 */
	private static function accent_color( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^#[0-9A-Fa-f]{6}$/', $value ) ) {
			throw ConfigException::for_field( 'accentColor', 'Accent color must contain six hexadecimal digits.' );
		}

		return strtoupper( $value );
	}

	/**
	 * Validates cross-field and view-specific rules.
	 *
	 * @param array<string, bool|int|string|null> $values Normalized values.
	 * @return void
	 * @throws ConfigException When field relations are invalid.
	 */
	private static function validate_relations( array $values ): void {
		if (
			null !== $values['matchFrom']
			&& null !== $values['matchTo']
			&& $values['matchFrom'] > $values['matchTo']
		) {
			throw ConfigException::for_field( 'matchTo', 'Match range is reversed.' );
		}

		if (
			null !== $values['dateFrom']
			&& null !== $values['dateTo']
			&& $values['dateFrom'] > $values['dateTo']
		) {
			throw ConfigException::for_field( 'dateTo', 'Date range is reversed.' );
		}

		if ( $values['minHeight'] > $values['maxHeight'] ) {
			throw ConfigException::for_field( 'maxHeight', 'Maximum height must not be below minimum height.' );
		}

		if ( 'standings' === $values['view'] ) {
			foreach ( array( 'participant', 'matchFrom', 'matchTo', 'dateFrom', 'dateTo' ) as $field ) {
				if ( null !== $values[ $field ] ) {
					throw ConfigException::for_field( $field, 'This filter is only available for matches.' );
				}
			}
		}
	}
}
