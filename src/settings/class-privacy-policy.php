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
			. esc_html__( 'Diese Website bindet öffentliche Turnierinformationen von Turnierplan.eu ein, wenn die Funktion administrativ freigegeben und in einem Inhalt verwendet wurde.', 'turnierplan-eu' )
			. '</p><p>'
			. esc_html__( 'Im WordPress-Backend ruft der Server kleine Metadatenantworten zur Konfiguration und Vorschau ab. Dabei verarbeitet Turnierplan.eu insbesondere die IP-Adresse des WordPress-Servers, Zeitpunkt, angeforderte Turnierreferenz und übliche technische HTTP-Daten. Das Plugin sendet dabei keine WordPress-Anmeldedaten oder Cookies.', 'turnierplan-eu' )
			. '</p><p>'
			. esc_html__( 'Auf veröffentlichten Seiten lädt der Browser des Besuchers den Turnier-Frame direkt von Turnierplan.eu. Dabei können insbesondere die IP-Adresse des Besuchers, Referrer- und Browserdaten sowie die angeforderte Turnieransicht verarbeitet werden. Weitere Informationen stehen in der Datenschutzerklärung von Turnierplan.eu.', 'turnierplan-eu' )
			. '</p>';

		wp_add_privacy_policy_content(
			esc_html__( 'Turnierplan.eu – Turniere einbetten', 'turnierplan-eu' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}
}
