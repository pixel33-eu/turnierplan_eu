<?php
/**
 * Shortcode configuration mapping.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

/**
 * Converts documented shortcode attributes without rendering a shortcode.
 */
final class ShortcodeConfigMapper {

	/**
	 * Recognized shortcode attributes.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_ATTRIBUTES = array(
		'preset',
		'tournament',
		'view',
		'lang',
		'group',
		'participant',
		'match_from',
		'match_to',
		'date_from',
		'date_to',
		'theme',
		'density',
		'accent',
		'branding',
		'links',
		'min_height',
		'max_height',
		'show',
		'date',
	);

	/**
	 * Parses raw shortcode attributes into the common selection model.
	 *
	 * Unknown attributes are intentionally ignored. Slashes are removed once at
	 * this input boundary and never by the normalized configuration object.
	 *
	 * @param array<string, mixed> $attributes Raw attributes.
	 * @param bool                 $slashed    Whether WordPress slashed the input.
	 * @return EmbedSelection
	 * @throws ConfigException When recognized attributes are invalid.
	 */
	public static function from_attributes( array $attributes, bool $slashed = true ): EmbedSelection {
		$recognized = array_intersect_key( $attributes, array_flip( self::ALLOWED_ATTRIBUTES ) );

		if ( $slashed ) {
			$recognized = self::unslash( $recognized );
		}

		if ( array_key_exists( 'preset', $recognized ) ) {
			if ( 1 !== count( $recognized ) ) {
				throw ConfigException::for_field( 'preset', 'Preset cannot be combined with inline shortcode attributes.' );
			}

			$preset_id = self::positive_integer_string( 'preset', $recognized['preset'] );

			return EmbedSelection::from_array( array( 'presetId' => $preset_id ) );
		}

		if ( ! array_key_exists( 'tournament', $recognized ) ) {
			throw ConfigException::for_field( 'tournament', 'Inline shortcode requires a tournament reference.' );
		}

		$input         = array(
			'tournamentRef' => $recognized['tournament'],
		);
		$direct_fields = array(
			'view'        => 'view',
			'lang'        => 'language',
			'group'       => 'group',
			'participant' => 'participant',
			'date_from'   => 'dateFrom',
			'date_to'     => 'dateTo',
			'theme'       => 'theme',
			'density'     => 'density',
			'date'        => 'showDate',
		);

		foreach ( $direct_fields as $attribute => $field ) {
			if ( array_key_exists( $attribute, $recognized ) && '' !== $recognized[ $attribute ] ) {
				$input[ $field ] = $recognized[ $attribute ];
			}
		}

		foreach ( array(
			'match_from' => 'matchFrom',
			'match_to'   => 'matchTo',
		) as $attribute => $field ) {
			if ( array_key_exists( $attribute, $recognized ) && '' !== $recognized[ $attribute ] ) {
				$input[ $field ] = self::positive_integer_string( $attribute, $recognized[ $attribute ], 999999 );
			}
		}

		foreach ( array(
			'min_height' => 'minHeight',
			'max_height' => 'maxHeight',
		) as $attribute => $field ) {
			if ( array_key_exists( $attribute, $recognized ) && '' !== $recognized[ $attribute ] ) {
				$input[ $field ] = self::positive_integer_string( $attribute, $recognized[ $attribute ], 8000 );
			}
		}

		if ( array_key_exists( 'accent', $recognized ) && '' !== $recognized['accent'] ) {
			$accent               = (string) $recognized['accent'];
			$input['accentColor'] = str_starts_with( $accent, '#' ) ? $accent : '#' . $accent;
		}

		if ( array_key_exists( 'branding', $recognized ) ) {
			$input['showBranding'] = self::choice_boolean(
				'branding',
				$recognized['branding'],
				'show',
				'hide'
			);
		}

		if ( array_key_exists( 'links', $recognized ) ) {
			$input['openLinksInNewTab'] = self::choice_boolean(
				'links',
				$recognized['links'],
				'new-tab',
				'same-tab'
			);
		}

		if ( array_key_exists( 'show', $recognized ) ) {
			$view   = isset( $input['view'] ) ? (string) $input['view'] : 'standings';
			$fields = EmbedConfigMap::show_fields( $view );

			foreach ( $fields as $field ) {
				$input[ $field ] = false;
			}

			$tokens = '' === $recognized['show']
				? array()
				: array_values( array_unique( explode( ',', (string) $recognized['show'] ) ) );

			foreach ( $tokens as $token ) {
				if ( ! isset( $fields[ $token ] ) ) {
					throw ConfigException::for_field( 'show', 'Shortcode visibility token is not allowed for this view.' );
				}

				$input[ $fields[ $token ] ] = true;
			}
		}

		return EmbedSelection::from_array(
			array(
				'presetId' => 0,
				'config'   => $input,
			)
		);
	}

	/**
	 * Creates compact canonical shortcode attributes from a selection.
	 *
	 * @param EmbedSelection $selection Validated selection.
	 * @return array<string, string>
	 * @throws ConfigException When the selection is internally inconsistent.
	 */
	public static function to_attributes( EmbedSelection $selection ): array {
		if ( $selection->is_preset() ) {
			return array( 'preset' => (string) $selection->get_preset_id() );
		}

		$config = $selection->get_config();

		if ( null === $config ) {
			throw ConfigException::for_field( 'config', 'Inline selection has no configuration.' );
		}

		$values     = $config->to_array();
		$attributes = array(
			'tournament' => (string) $values['tournamentRef'],
			'view'       => (string) $values['view'],
		);
		$optional   = array(
			'language'    => 'lang',
			'group'       => 'group',
			'participant' => 'participant',
			'matchFrom'   => 'match_from',
			'matchTo'     => 'match_to',
			'dateFrom'    => 'date_from',
			'dateTo'      => 'date_to',
			'theme'       => 'theme',
			'density'     => 'density',
			'accentColor' => 'accent',
			'minHeight'   => 'min_height',
			'maxHeight'   => 'max_height',
			'showDate'    => 'date',
		);

		foreach ( $optional as $field => $attribute ) {
			if ( self::is_non_default( $field, $values[ $field ] ) ) {
				$attributes[ $attribute ] = (string) $values[ $field ];
			}
		}

		if ( false === $values['showBranding'] ) {
			$attributes['branding'] = 'hide';
		}

		if ( false === $values['openLinksInNewTab'] ) {
			$attributes['links'] = 'same-tab';
		}

		$fields  = EmbedConfigMap::show_fields( (string) $values['view'] );
		$changed = false;
		$tokens  = array();

		foreach ( $fields as $token => $field ) {
			if ( true === $values[ $field ] ) {
				$tokens[] = $token;
			} else {
				$changed = true;
			}
		}

		if ( $changed ) {
			$attributes['show'] = implode( ',', $tokens );
		}

		return $attributes;
	}

	/**
	 * Removes WordPress slashes exactly once at the shortcode boundary.
	 *
	 * @param array<string, mixed> $values Raw values.
	 * @return array<string, mixed>
	 */
	private static function unslash( array $values ): array {
		if ( function_exists( 'wp_unslash' ) ) {
			/**
			 * Unslashed values retain the same associative shape.
			 *
			 * @var array<string, mixed> $unslashed
			 */
			$unslashed = wp_unslash( $values );

			return $unslashed;
		}

		foreach ( $values as $key => $value ) {
			if ( is_string( $value ) ) {
				$values[ $key ] = stripslashes( $value );
			}
		}

		return $values;
	}

	/**
	 * Parses a positive decimal integer string without coercing other types.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Candidate value.
	 * @param int    $max   Maximum accepted value.
	 * @return int
	 * @throws ConfigException When the shortcode integer is invalid.
	 */
	private static function positive_integer_string( string $field, mixed $value, int $max = PHP_INT_MAX ): int {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/', $value ) ) {
			throw ConfigException::for_field( $field, 'Shortcode integer is malformed.' );
		}

		$integer = filter_var( $value, FILTER_VALIDATE_INT );

		if ( false === $integer || $integer > $max ) {
			throw ConfigException::for_field( $field, 'Shortcode integer is outside its allowed range.' );
		}

		return $integer;
	}

	/**
	 * Converts a positive/negative enum pair to boolean.
	 *
	 * @param string $field    Field name.
	 * @param mixed  $value    Candidate value.
	 * @param string $positive Positive token.
	 * @param string $negative Negative token.
	 * @return bool
	 * @throws ConfigException When neither token matches.
	 */
	private static function choice_boolean(
		string $field,
		mixed $value,
		string $positive,
		string $negative
	): bool {
		if ( $positive === $value ) {
			return true;
		}

		if ( $negative === $value ) {
			return false;
		}

		throw ConfigException::for_field( $field, 'Shortcode choice is not allowed.' );
	}

	/**
	 * Checks whether one value differs from its fixed V1 contract default.
	 *
	 * @param string               $field Field name.
	 * @param bool|int|string|null $value Normalized value.
	 * @return bool
	 */
	private static function is_non_default( string $field, bool|int|string|null $value ): bool {
		return array_key_exists( $field, EmbedConfig::CONTRACT_DEFAULTS )
			&& EmbedConfig::CONTRACT_DEFAULTS[ $field ] !== $value;
	}
}
