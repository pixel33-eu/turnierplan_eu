<?php
/**
 * Protected preset selector REST API.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Preset;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/** Exposes published presets to authenticated editors without author data. */
final class PresetRestController {

	private const NAMESPACE = 'turnierplan-eu/v1';

	/** Creates the controller. */
	public function __construct( private readonly PresetRepository $presets ) {
	}

	/** Registers routes on REST initialization. */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/** Registers the protected list and exact-item endpoints. */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/presets',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'authorize' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/presets/(?P<id>[1-9][0-9]*)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'authorize' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/** Requires the dedicated selector capability and a REST nonce. */
	public function authorize( WP_REST_Request $request ): bool|WP_Error {
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

		return true;
	}

	/** Returns every published and valid preset in stable title order. */
	public function get_items(): WP_REST_Response {
		$query = new WP_Query(
			array(
				'post_type'              => PresetRepository::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => array(
					'title' => 'ASC',
					'ID'    => 'ASC',
				),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);
		$items = array();

		$post_ids = is_array( $query->posts ) ? $query->posts : array();

		foreach ( $post_ids as $post_id ) {
			if ( ! is_int( $post_id ) ) {
				continue;
			}

			$preset = $this->presets->resolve_published( $post_id );

			if ( null !== $preset ) {
				$items[] = $this->prepare_item( $preset );
			}
		}

		return new WP_REST_Response( array( 'presets' => $items ), 200 );
	}

	/** Returns one exact published preset without falling back to another item. */
	public function get_item( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$preset = $this->presets->resolve_published( absint( $request->get_param( 'id' ) ) );

		if ( null === $preset ) {
			return new WP_Error(
				'tpeu_preset_not_found',
				esc_html__( 'Das Preset ist nicht veröffentlicht oder nicht verfügbar.', 'turnierplan-eu' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->prepare_item( $preset ), 200 );
	}

	/**
	 * Returns only selector and inline-copy fields.
	 *
	 * @return array{id:int,title:string,config:array<string,mixed>,shortcode:string}
	 */
	private function prepare_item( ResolvedPreset $preset ): array {
		return array(
			'id'        => $preset->get_id(),
			'title'     => $preset->get_title(),
			'config'    => $preset->get_config()->to_array(),
			'shortcode' => sprintf( '[turnierplan preset="%d"]', $preset->get_id() ),
		);
	}
}
