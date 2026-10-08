<?php
/**
 * Site privacy policy suggestion.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Settings;

/**
 * Explains server-side metadata and browser-side frame requests separately.
 */
final class PrivacyPolicy {

	/** Registers the WordPress privacy policy suggestion. */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_suggested_text' ) );
	}

	/** Adds separate server-side and browser-side request explanations. */
	public function add_suggested_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>'
			. esc_html__( 'This website embeds public tournament information from Turnierplan.eu when the feature has been approved by an administrator and used in content.', 'turnierplan-eu' )
			. '</p><p>'
			. esc_html__( 'In the WordPress dashboard, the server retrieves small metadata responses for configuration and preview. Turnierplan.eu processes the WordPress server\'s IP address, the time, the requested tournament reference, and standard technical HTTP data. The plugin does not send WordPress login details or cookies.', 'turnierplan-eu' )
			. '</p><p>'
			. esc_html__( 'On published pages, the visitor\'s browser loads the tournament frame directly from Turnierplan.eu. This may process the visitor\'s IP address, referrer and browser data, and the requested tournament view. More information is available in the Turnierplan.eu privacy policy.', 'turnierplan-eu' )
			. '</p>';

		wp_add_privacy_policy_content(
			esc_html__( 'Turnierplan.eu – Embed tournaments', 'turnierplan-eu' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}
}
