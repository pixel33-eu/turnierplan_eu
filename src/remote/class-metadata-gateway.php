<?php
/**
 * Metadata cache and service orchestration.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Remote;

use TurnierplanEU\WordPress\Cache\MetadataCache;
use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;
use TurnierplanEU\WordPress\Settings\ServiceApproval;

/**
 * Applies approval, input normalization, cache, ETag, and stale fallback policy.
 */
final class MetadataGateway {

	/** Creates the approved cached service gateway. */
	public function __construct(
		private readonly ServiceApproval $settings,
		private readonly MetadataCache $cache,
		private readonly MetadataClient $client
	) {
	}

	/** Resolves one normalized metadata request. */
	public function get( mixed $reference, string $language = 'auto', bool $force = false, int $time_budget = 5 ): MetadataResult {
		if ( ! $this->settings->is_service_enabled() ) {
			return MetadataResult::status( 'service_not_enabled' );
		}

		try {
			$config = EmbedConfig::from_array(
				array(
					'tournamentRef' => $reference,
					'language'      => $language,
				)
			);
		} catch ( ConfigException ) {
			return MetadataResult::status( 'invalid_reference' );
		}

		$normalized_reference = (string) $config->get( 'tournamentRef' );
		$normalized_language  = (string) $config->get( 'language' );

		if ( ! $force ) {
			$fresh = $this->cache->get_fresh( $normalized_reference, $normalized_language );

			if ( null !== $fresh ) {
				return $fresh;
			}

			if ( $this->cache->has_not_found( $normalized_reference, $normalized_language ) ) {
				return MetadataResult::status( 'tournament_not_found' );
			}
		}

		$stale  = $this->cache->get_stale( $normalized_reference, $normalized_language );
		$result = $this->client->fetch(
			$normalized_reference,
			$normalized_language,
			null !== $stale ? $stale->get_etag() : null,
			$time_budget
		);

		if ( $result->is_success() ) {
			$this->cache->save_success( $normalized_reference, $normalized_language, $result );
			return $result;
		}

		if ( 'not_modified' === $result->get_code() && null !== $stale ) {
			$this->cache->revalidate( $normalized_reference, $normalized_language, $stale );
			return MetadataResult::success(
				(array) $stale->get_metadata(),
				$stale->get_etag(),
				'cache'
			);
		}

		if ( 'tournament_not_found' === $result->get_code() ) {
			$this->cache->save_not_found( $normalized_reference, $normalized_language );
			return $result;
		}

		if (
			null !== $stale
			&& in_array( $result->get_code(), array( 'timeout', 'transport_error', 'temporarily_unavailable' ), true )
		) {
			return $stale;
		}

		return $result;
	}
}
