<?php
/**
 * Preset role and capability policy.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Preset;

use WP_Role;

/** Installs the documented role matrix idempotently. */
final class Capabilities {

	public const VERSION         = 1;
	public const VERSION_OPTION  = 'tpeu_capability_version';
	public const MANAGE_SETTINGS = 'tpeu_manage_settings';
	public const USE_PRESETS     = 'tpeu_use_embeds';
	public const REFRESH_PRESETS = 'tpeu_refresh_embeds';

	/** Registers the lightweight upgrade check. */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_install' ), 5 );
	}

	/** Installs capabilities only when their version changes. */
	public function maybe_install(): void {
		if ( self::VERSION !== (int) get_option( self::VERSION_OPTION, 0 ) ) {
			self::install();
		}
	}

	/** Grants each built-in editorial role only its intended capabilities. */
	public static function install(): void {
		$administrator = get_role( 'administrator' );
		$editor        = get_role( 'editor' );
		$author        = get_role( 'author' );

		self::grant( $administrator, self::administrator_capabilities() );
		self::grant( $editor, self::editor_capabilities() );
		self::grant( $author, self::author_capabilities() );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/** Removes only capabilities granted by this plugin from built-in roles. */
	public static function remove(): void {
		self::revoke( get_role( 'administrator' ), self::administrator_capabilities() );
		self::revoke( get_role( 'editor' ), self::editor_capabilities() );
		self::revoke( get_role( 'author' ), self::author_capabilities() );
	}

	/**
	 * Returns explicit post type capability names.
	 *
	 * @return array<string,string>
	 */
	public static function post_type_capabilities(): array {
		return array(
			'edit_post'              => 'edit_tpeu_embed',
			'read_post'              => 'read_tpeu_embed',
			'delete_post'            => 'delete_tpeu_embed',
			'edit_posts'             => 'edit_tpeu_embeds',
			'edit_others_posts'      => 'edit_others_tpeu_embeds',
			'publish_posts'          => 'publish_tpeu_embeds',
			'read_private_posts'     => 'read_private_tpeu_embeds',
			'delete_posts'           => 'delete_tpeu_embeds',
			'delete_private_posts'   => 'delete_private_tpeu_embeds',
			'delete_published_posts' => 'delete_published_tpeu_embeds',
			'delete_others_posts'    => 'delete_others_tpeu_embeds',
			'edit_private_posts'     => 'edit_private_tpeu_embeds',
			'edit_published_posts'   => 'edit_published_tpeu_embeds',
			'create_posts'           => 'create_tpeu_embeds',
		);
	}

	/**
	 * Grants a set to a role when that role exists.
	 *
	 * @param WP_Role|null $role         Target role.
	 * @param list<string> $capabilities Capabilities to grant.
	 */
	private static function grant( ?WP_Role $role, array $capabilities ): void {
		if ( null === $role ) {
			return;
		}

		foreach ( $capabilities as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Revokes a set from a role when that role exists.
	 *
	 * @param WP_Role|null $role         Target role.
	 * @param list<string> $capabilities Capabilities to revoke.
	 */
	private static function revoke( ?WP_Role $role, array $capabilities ): void {
		if ( null === $role ) {
			return;
		}

		foreach ( $capabilities as $capability ) {
			$role->remove_cap( $capability );
		}
	}

	/**
	 * Returns capabilities for site administrators.
	 *
	 * @return list<string>
	 */
	private static function administrator_capabilities(): array {
		return array_values(
			array_unique(
				array_merge(
					array_values( self::post_type_capabilities() ),
					array( self::MANAGE_SETTINGS, self::USE_PRESETS, self::REFRESH_PRESETS )
				)
			)
		);
	}

	/**
	 * Returns capabilities for editors, including other users' presets.
	 *
	 * @return list<string>
	 */
	private static function editor_capabilities(): array {
		return array(
			'edit_tpeu_embeds',
			'edit_others_tpeu_embeds',
			'publish_tpeu_embeds',
			'read_private_tpeu_embeds',
			'delete_tpeu_embeds',
			'delete_private_tpeu_embeds',
			'delete_published_tpeu_embeds',
			'delete_others_tpeu_embeds',
			'edit_private_tpeu_embeds',
			'edit_published_tpeu_embeds',
			'create_tpeu_embeds',
			self::USE_PRESETS,
			self::REFRESH_PRESETS,
		);
	}

	/**
	 * Returns capabilities for authors, limited to their own presets.
	 *
	 * @return list<string>
	 */
	private static function author_capabilities(): array {
		return array(
			'edit_tpeu_embeds',
			'publish_tpeu_embeds',
			'delete_tpeu_embeds',
			'delete_published_tpeu_embeds',
			'edit_published_tpeu_embeds',
			'create_tpeu_embeds',
			self::USE_PRESETS,
			self::REFRESH_PRESETS,
		);
	}
}
