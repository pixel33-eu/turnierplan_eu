<?php
/**
 * WordPress preset storage adapter.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Preset;

use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;
use WP_Post;

/** Reads exact post and meta records without fallback queries. */
final class PresetRepository implements PresetResolver {

	public const POST_TYPE = 'tpeu_embed';
	public const META_KEY  = '_tpeu_config';

	/** Returns a published valid preset or null without revealing why it failed. */
	public function resolve_published( int $preset_id ): ?ResolvedPreset {
		$post = get_post( $preset_id );

		if (
			! $post instanceof WP_Post
			|| self::POST_TYPE !== $post->post_type
			|| 'publish' !== $post->post_status
		) {
			return null;
		}

		return $this->resolve_post( $post );
	}

	/** Returns a valid preset the current user may edit. */
	public function resolve_editable( int $preset_id ): ?ResolvedPreset {
		$post = get_post( $preset_id );

		if (
			! $post instanceof WP_Post
			|| self::POST_TYPE !== $post->post_type
			|| ! current_user_can( 'edit_post', $preset_id )
		) {
			return null;
		}

		return $this->resolve_post( $post );
	}

	/** Converts one exact post record into a safe value object. */
	private function resolve_post( WP_Post $post ): ?ResolvedPreset {
		$value = get_post_meta( $post->ID, self::META_KEY, true );

		if ( ! is_array( $value ) ) {
			return null;
		}

		try {
			$config = EmbedConfig::from_array( $value );
		} catch ( ConfigException ) {
			return null;
		}

		return new ResolvedPreset(
			$post->ID,
			get_the_title( $post ),
			$config
		);
	}
}
