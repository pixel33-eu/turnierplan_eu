<?php
/**
 * Reusable embed post type and strict configuration meta.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Preset;

use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;
use WP_Error;
use WP_REST_Request;

/** Registers local preset storage without a public query surface. */
final class PresetPostType {

	/** Registers post type, meta, and REST validation hooks. */
	public function register(): void {
		add_action( 'init', array( $this, 'register_storage' ) );
		add_action( 'add_meta_boxes_' . PresetRepository::POST_TYPE, array( $this, 'remove_raw_meta_box' ) );
		add_filter( 'rest_pre_insert_' . PresetRepository::POST_TYPE, array( $this, 'validate_rest_insert' ), 10, 2 );
		add_filter( 'rest_pre_dispatch', array( $this, 'protect_core_rest_routes' ), 10, 3 );
	}

	/** Protects the core collection and item routes including published records. */
	public function protect_core_rest_routes( mixed $result, mixed $server, WP_REST_Request $request ): mixed {
		unset( $server );
		$route = $request->get_route();

		if ( 1 !== preg_match( '#^/wp/v2/turnierplan-embeds(?:/[0-9]+)?$#', $route ) ) {
			return $result;
		}

		if ( ! current_user_can( Capabilities::USE_PRESETS ) ) {
			return new WP_Error(
				'tpeu_presets_forbidden',
				esc_html__( 'Du darfst keine Turnierplan-Presets verwenden.', 'turnierplan-eu' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( null === $nonce || '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'tpeu_invalid_nonce',
				esc_html__( 'Die Sicherheitsprüfung ist abgelaufen. Bitte lade den Editor neu.', 'turnierplan-eu' ),
				array( 'status' => 403 )
			);
		}

		return $result;
	}

	/** Registers the private UI post type and its versioned object meta. */
	public function register_storage(): void {
		register_post_type(
			PresetRepository::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => esc_html__( 'Turnierplan-Einbettungen', 'turnierplan-eu' ),
					'singular_name'      => esc_html__( 'Einbettung', 'turnierplan-eu' ),
					'menu_name'          => esc_html__( 'Turnierplan', 'turnierplan-eu' ),
					'all_items'          => esc_html__( 'Einbettungen', 'turnierplan-eu' ),
					'add_new_item'       => esc_html__( 'Einbettung hinzufügen', 'turnierplan-eu' ),
					'edit_item'          => esc_html__( 'Einbettung bearbeiten', 'turnierplan-eu' ),
					'new_item'           => esc_html__( 'Neue Einbettung', 'turnierplan-eu' ),
					'not_found'          => esc_html__( 'Keine Einbettungen gefunden.', 'turnierplan-eu' ),
					'not_found_in_trash' => esc_html__( 'Keine Einbettungen im Papierkorb gefunden.', 'turnierplan-eu' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'rest_base'           => 'turnierplan-embeds',
				'menu_icon'           => 'dashicons-chart-bar',
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'show_in_nav_menus'   => false,
				'delete_with_user'    => false,
				'supports'            => array( 'title', 'custom-fields' ),
				'capability_type'     => array( 'tpeu_embed', 'tpeu_embeds' ),
				'capabilities'        => Capabilities::post_type_capabilities(),
				'map_meta_cap'        => true,
			)
		);

		register_post_meta(
			PresetRepository::POST_TYPE,
			PresetRepository::META_KEY,
			array(
				'label'             => esc_html__( 'Einbettungskonfiguration', 'turnierplan-eu' ),
				'description'       => esc_html__( 'Versionierte und validierte Turnierplan.eu-Konfiguration.', 'turnierplan-eu' ),
				'type'              => 'object',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_config' ),
				'auth_callback'     => array( $this, 'authorize_meta' ),
				'show_in_rest'      => array( 'schema' => self::configuration_schema() ),
			)
		);
	}

	/** Removes the raw custom-fields metabox while preserving REST support. */
	public function remove_raw_meta_box(): void {
		remove_meta_box( 'postcustom', PresetRepository::POST_TYPE, 'normal' );
	}

	/**
	 * Normalizes config or returns an empty draft value.
	 *
	 * @return array<string,mixed>
	 */
	public function sanitize_config( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		try {
			return EmbedConfig::from_array( $value )->to_array();
		} catch ( ConfigException ) {
			return array();
		}
	}

	/** Restricts meta changes to users who may edit the exact preset. */
	public function authorize_meta( bool $allowed, string $meta_key, int $object_id ): bool {
		unset( $allowed, $meta_key );

		return $object_id > 0
			? current_user_can( 'edit_post', $object_id )
			: current_user_can( 'create_tpeu_embeds' );
	}

	/** Rejects relationally invalid REST meta before WordPress writes it. */
	public function validate_rest_insert( mixed $prepared_post, WP_REST_Request $request ): mixed {
		$meta   = $request->get_param( 'meta' );
		$status = $request->get_param( 'status' );
		$value  = is_array( $meta ) && array_key_exists( PresetRepository::META_KEY, $meta )
			? $meta[ PresetRepository::META_KEY ]
			: null;

		if ( null !== $value ) {
			try {
				if ( ! is_array( $value ) ) {
					throw ConfigException::for_field( 'config', 'Preset configuration must be an object.' );
				}

				EmbedConfig::from_array( $value );
			} catch ( ConfigException ) {
				return new WP_Error(
					'tpeu_invalid_preset_config',
					esc_html__( 'Die Einbettungskonfiguration ist ungültig oder unvollständig.', 'turnierplan-eu' ),
					array( 'status' => 400 )
				);
			}
		}

		if ( 'publish' === $status && null === $value ) {
			$post_id = (int) $request->get_param( 'id' );
			$stored  = $post_id > 0 ? get_post_meta( $post_id, PresetRepository::META_KEY, true ) : null;

			if ( array() === $this->sanitize_config( $stored ) ) {
				return new WP_Error(
					'tpeu_missing_preset_config',
					esc_html__( 'Vor dem Veröffentlichen muss ein gültiges Turnier verbunden werden.', 'turnierplan-eu' ),
					array( 'status' => 400 )
				);
			}
		}

		return $prepared_post;
	}

	/**
	 * Returns the complete REST schema for the V1 configuration object.
	 *
	 * @return array<string,mixed>
	 */
	public static function configuration_schema(): array {
		$nullable_string = static fn ( array $schema ): array => array_merge(
			$schema,
			array( 'type' => array( 'string', 'null' ) )
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'schemaVersion'         => array(
					'type' => 'integer',
					'enum' => array( 1 ),
				),
				'tournamentRef'         => array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 100,
				),
				'view'                  => array(
					'type' => 'string',
					'enum' => array( 'standings', 'matches' ),
				),
				'language'              => array(
					'type'    => 'string',
					'pattern' => '^(?:auto|[a-z]{2,3}(?:-[A-Z]{2})?)$',
				),
				'group'                 => $nullable_string( array( 'pattern' => '^grp_[0-9a-hjkmnp-tv-z]{26}$' ) ),
				'participant'           => $nullable_string( array( 'pattern' => '^ptc_[0-9a-hjkmnp-tv-z]{26}$' ) ),
				'matchFrom'             => array(
					'type'    => array( 'integer', 'null' ),
					'minimum' => 1,
					'maximum' => 999999,
				),
				'matchTo'               => array(
					'type'    => array( 'integer', 'null' ),
					'minimum' => 1,
					'maximum' => 999999,
				),
				'dateFrom'              => $nullable_string( array( 'pattern' => '^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])$' ) ),
				'dateTo'                => $nullable_string( array( 'pattern' => '^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])$' ) ),
				'theme'                 => array(
					'type' => 'string',
					'enum' => array( 'auto', 'light', 'dark' ),
				),
				'density'               => array(
					'type' => 'string',
					'enum' => array( 'comfortable', 'compact' ),
				),
				'accentColor'           => $nullable_string( array( 'pattern' => '^#[0-9A-F]{6}$' ) ),
				'showBranding'          => array( 'type' => 'boolean' ),
				'openLinksInNewTab'     => array( 'type' => 'boolean' ),
				'minHeight'             => array(
					'type'    => 'integer',
					'minimum' => 160,
					'maximum' => 2000,
				),
				'maxHeight'             => array(
					'type'    => 'integer',
					'minimum' => 300,
					'maximum' => 8000,
				),
				'showTeamLogos'         => array( 'type' => 'boolean' ),
				'showPlayed'            => array( 'type' => 'boolean' ),
				'showWinsDrawsLosses'   => array( 'type' => 'boolean' ),
				'showScoreBalance'      => array( 'type' => 'boolean' ),
				'showPoints'            => array( 'type' => 'boolean' ),
				'enableGroupNavigation' => array( 'type' => 'boolean' ),
				'showMatchNumber'       => array( 'type' => 'boolean' ),
				'showDate'              => array(
					'type' => 'string',
					'enum' => array( 'auto', 'show', 'hide' ),
				),
				'showTime'              => array( 'type' => 'boolean' ),
				'showField'             => array( 'type' => 'boolean' ),
				'showGroup'             => array( 'type' => 'boolean' ),
				'showRound'             => array( 'type' => 'boolean' ),
				'showReferee'           => array( 'type' => 'boolean' ),
				'showLiveState'         => array( 'type' => 'boolean' ),
				'showExtraTime'         => array( 'type' => 'boolean' ),
				'showPenaltyResult'     => array( 'type' => 'boolean' ),
			),
		);
	}
}
