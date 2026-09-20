<?php
/**
 * Cached metadata checks for configured filters.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Config;

use DateTimeZone;

/**
 * Marks stale semantic choices without requesting remote data.
 */
final class CachedMetadataValidator {

	/**
	 * Finds choices that are absent from an already available metadata snapshot.
	 *
	 * The service frame remains authoritative because cached metadata can be old.
	 *
	 * @param EmbedConfig          $config   Validated configuration.
	 * @param array<string, mixed> $metadata Cached metadata response.
	 * @return list<array{code: string, field: string}>
	 */
	public static function warnings( EmbedConfig $config, array $metadata ): array {
		$warnings = array();
		$views    = self::ids( $metadata['views'] ?? null, 'id' );

		if (
			array_key_exists( 'views', $metadata )
			&& ! in_array( $config->get( 'view' ), $views, true )
		) {
			$warnings[] = array(
				'code'  => 'view_unavailable',
				'field' => 'view',
			);
		}

		foreach (
			array(
				'group'       => array(
					'source' => 'groups',
					'key'    => 'id',
				),
				'participant' => array(
					'source' => 'participants',
					'key'    => 'id',
				),
			) as $field => $mapping
		) {
			$value = $config->get( $field );

			if ( null === $value || ! array_key_exists( $mapping['source'], $metadata ) ) {
				continue;
			}

			$available = self::ids( $metadata[ $mapping['source'] ], $mapping['key'] );

			if ( ! in_array( $value, $available, true ) ) {
				$warnings[] = array(
					'code'  => 'filter_ignored',
					'field' => $field,
				);
			}
		}

		return $warnings;
	}

	/**
	 * Checks the IANA timezone supplied by cached service metadata.
	 *
	 * User configuration never accepts or forwards a timezone value.
	 *
	 * @param array<string, mixed> $metadata Cached metadata response.
	 * @return bool
	 */
	public static function has_valid_timezone( array $metadata ): bool {
		$timezone = $metadata['tournament']['timezone'] ?? null;

		return is_string( $timezone )
			&& in_array( $timezone, DateTimeZone::listIdentifiers(), true );
	}

	/**
	 * Extracts non-empty string IDs from a metadata list.
	 *
	 * @param mixed  $items Candidate list.
	 * @param string $key   ID field.
	 * @return list<string>
	 */
	private static function ids( mixed $items, string $key ): array {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$ids = array();

		foreach ( $items as $item ) {
			if ( is_array( $item ) && is_string( $item[ $key ] ?? null ) ) {
				$ids[] = $item[ $key ];
			}
		}

		return $ids;
	}
}
