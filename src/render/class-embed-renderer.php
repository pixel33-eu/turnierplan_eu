<?php
/**
 * Shared Turnierplan.eu embed renderer.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Render;

use TurnierplanEU\WordPress\Config\ConfigException;
use TurnierplanEU\WordPress\Config\EmbedConfig;
use TurnierplanEU\WordPress\Config\EmbedUrlBuilder;
use TurnierplanEU\WordPress\Config\ServiceConfiguration;
use TurnierplanEU\WordPress\Localization\FrontendTranslator;
use TurnierplanEU\WordPress\Settings\ServiceApproval;
use TurnierplanEU\WordPress\Localization\SiteLanguageResolver;

/**
 * Is the only runtime component allowed to create iframe markup.
 */
final class EmbedRenderer {

	private const SANDBOX = 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox';
	private const ALLOW   = "camera 'none'; microphone 'none'; geolocation 'none'; clipboard-read 'none'; clipboard-write 'none'";

	/** Creates the shared renderer. */
	public function __construct(
		private readonly ServiceApproval $approval,
		private readonly EmbedUrlBuilder $url_builder,
		private readonly ServiceConfiguration $service,
		private readonly ParentOriginProvider $parent_origin,
		private readonly InstanceIdGenerator $instance_ids,
		private readonly FrontendAssetLoader $assets,
		private readonly SiteLanguageResolver $languages,
		private readonly FrontendTranslator $translations
	) {
	}

	/**
	 * Renders one validated inline embed without making a server request.
	 *
	 * @param EmbedConfig $config           Validated embed configuration.
	 * @param string|null $tournament_title Optional validated cached title.
	 * @return string Escaped frontend markup.
	 */
	public function render( EmbedConfig $config, ?string $tournament_title = null ): string {
		$resolved_config = $this->languages->apply( $config );

		return $this->render_localized( $resolved_config, $tournament_title );
	}

	/**
	 * Renders one embed with wrapper strings for the resolved output language.
	 *
	 * @param EmbedConfig $config           Configuration with resolved language.
	 * @param string|null $tournament_title Optional validated cached title.
	 * @return string Escaped frontend markup.
	 */
	private function render_localized( EmbedConfig $config, ?string $tournament_title ): string {
		$reference  = (string) $config->get( 'tournamentRef' );
		$public_url = $this->safe_public_url( $reference );
		$language   = (string) $config->get( 'language' );

		if ( null === $public_url ) {
			return $this->render_invalid();
		}

		if ( ! $this->approval->is_service_enabled() ) {
			return $this->render_state(
				$this->translate( 'The tournament view is currently disabled on this website.', $language ),
				$public_url,
				$this->translate( 'Open tournament on Turnierplan.eu', $language )
			);
		}

		$origin = $this->parent_origin->get_origin();

		if ( null === $origin ) {
			return $this->render_state(
				$this->translate( 'The tournament view requires a secure HTTPS address for the WordPress website.', $language ),
				$public_url,
				$this->translate( 'Open tournament on Turnierplan.eu', $language )
			);
		}

		try {
			$instance  = $this->instance_ids->generate();
			$frame_url = $this->url_builder->frame_url( $config, $instance, $origin );
		} catch ( ConfigException ) {
			return $this->render_state(
				$this->translate( 'The tournament view could not be loaded because its settings are invalid.', $language ),
				$public_url,
				$this->translate( 'Open tournament on Turnierplan.eu', $language )
			);
		}

		$this->assets->enqueue();

		$values       = $config->to_array();
		$view         = (string) $values['view'];
		$view_label   = 'matches' === $view
			? $this->translate( 'Schedule', $language )
			: $this->translate( 'Standings', $language );
		$display_name = null !== $tournament_title && '' !== trim( $tournament_title )
			? trim( $tournament_title )
			/* translators: %s: public tournament reference. */
			: sprintf( $this->translate( 'Tournament %s', $language ), $reference );
		/* translators: 1: embed view name, 2: tournament title or reference. */
		$title      = sprintf( $this->translate( '%1$s: %2$s', $language ), $view_label, $display_name );
		$target     = true === $values['openLinksInNewTab'] ? ' target="_blank" rel="noopener noreferrer"' : '';
		$min_height = (int) $values['minHeight'];
		$max_height = (int) $values['maxHeight'];

		return sprintf(
			'<div class="tpeu-embed" data-tpeu-embed data-tpeu-instance="%1$s" data-tpeu-origin="%2$s" data-tpeu-min-height="%3$d" data-tpeu-max-height="%4$d">'
			. '<p class="tpeu-embed__status" role="status" aria-live="polite" aria-atomic="true">'
			. '<span data-tpeu-state="loading">%5$s</span>'
			. '<span data-tpeu-state="ready" hidden>%6$s</span>'
			. '<span data-tpeu-state="empty" hidden>%7$s</span>'
			. '<span data-tpeu-state="stale" hidden>%8$s</span>'
			. '<span data-tpeu-state="error" hidden>%9$s</span>'
			. '<span data-tpeu-state="timeout" hidden>%10$s</span>'
			. '</p>'
			. '<iframe class="tpeu-embed__frame" data-tpeu-frame src="%11$s" title="%12$s" width="100%%" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" sandbox="%13$s" allow="%14$s" style="height:%3$dpx;--tpeu-max-height:%4$dpx"></iframe>'
			. '<p class="tpeu-embed__fallback"><a href="%15$s"%16$s>%17$s</a></p>'
			. '</div>',
			esc_attr( $instance ),
			esc_attr( $this->service->get_origin() ),
			$min_height,
			$max_height,
			esc_html( $this->translate( 'Loading tournament view…', $language ) ),
			esc_html( $this->translate( 'Tournament view loaded.', $language ) ),
			esc_html( $this->translate( 'No content is currently available for this selection.', $language ) ),
			esc_html( $this->translate( 'The displayed data could not be updated completely.', $language ) ),
			esc_html( $this->translate( 'The tournament view is currently unavailable.', $language ) ),
			esc_html( $this->translate( 'Loading is taking longer than expected. The tournament link remains available.', $language ) ),
			esc_url( $frame_url ),
			esc_attr( $title ),
			esc_attr( self::SANDBOX ),
			esc_attr( self::ALLOW ),
			esc_url( $public_url ),
			$target,
			esc_html( $this->translate( 'Open full tournament on Turnierplan.eu', $language ) )
		);
	}

	/** Returns a safe invalid-shortcode state without loading assets. */
	public function render_invalid(): string {
		return $this->render_state(
			__( 'The tournament view contains invalid or incomplete settings.', 'turnierplan-eu' ),
			null
		);
	}

	/** Returns the safe state for a missing, invalid, or unpublished preset. */
	public function render_preset_unavailable(): string {
		return $this->render_state(
			__( 'This saved tournament embed is currently unavailable.', 'turnierplan-eu' ),
			null
		);
	}

	/** Builds a fixed public URL or returns null for an impossible invalid config. */
	private function safe_public_url( string $reference ): ?string {
		try {
			return $this->url_builder->public_url( $reference );
		} catch ( ConfigException ) {
			return null;
		}
	}

	/** Renders a non-iframe state and an optional deliberate fallback link. */
	private function render_state( string $message, ?string $public_url, ?string $link_label = null ): string {
		$link = '';

		if ( null !== $public_url ) {
			$link = sprintf(
				' <a href="%1$s">%2$s</a>',
				esc_url( $public_url ),
				esc_html( $link_label ?? __( 'Open tournament on Turnierplan.eu', 'turnierplan-eu' ) )
			);
		}

		return sprintf(
			'<div class="tpeu-embed tpeu-embed--unavailable"><p>%1$s%2$s</p></div>',
			esc_html( $message ),
			$link
		);
	}

	/** Translates one public string without changing WordPress's global locale. */
	private function translate( string $message, string $language ): string {
		return $this->translations->translate( $message, $language );
	}
}
