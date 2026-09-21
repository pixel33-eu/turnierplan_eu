<?php
/**
 * Turnierplan.eu settings page.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress\Settings;

use TurnierplanEU\WordPress\Cache\MetadataCache;
use TurnierplanEU\WordPress\Config\ServiceConfiguration;
use TurnierplanEU\WordPress\Remote\MetadataGateway;

/**
 * Provides service approval, setup defaults, revocation, and a deliberate test.
 */
final class SettingsPage {

	private const PAGE_SLUG = 'turnierplan-eu';

	/** Creates the settings UI and deliberate connection test. */
	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly MetadataCache $cache,
		private readonly MetadataGateway $gateway,
		private readonly ServiceConfiguration $service
	) {
	}

	/** Registers admin hooks. */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_tpeu_connection_test', array( $this, 'handle_connection_test' ) );
	}

	/** Adds the page below the WordPress Settings menu. */
	public function add_page(): void {
		add_options_page(
			esc_html__( 'Turnierplan.eu', 'turnierplan-eu' ),
			esc_html__( 'Turnierplan.eu', 'turnierplan-eu' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/** Registers the option, sections, and fields with the Settings API. */
	public function register_settings(): void {
		register_setting(
			'tpeu_settings_group',
			SettingsRepository::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => SettingsRepository::defaults(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'tpeu_service',
			esc_html__( 'Externer Dienst', 'turnierplan-eu' ),
			array( $this, 'render_service_section' ),
			self::PAGE_SLUG
		);
		add_settings_field( 'tpeu_service_enabled', esc_html__( 'Verbindung', 'turnierplan-eu' ), array( $this, 'render_service_field' ), self::PAGE_SLUG, 'tpeu_service' );
		add_settings_field( 'tpeu_service_url', esc_html__( 'Dienst-URL', 'turnierplan-eu' ), array( $this, 'render_service_url_field' ), self::PAGE_SLUG, 'tpeu_service' );

		add_settings_section( 'tpeu_defaults', esc_html__( 'Standardwerte für neue Einbettungen', 'turnierplan-eu' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'tpeu_language', esc_html__( 'Sprache', 'turnierplan-eu' ), array( $this, 'render_language_field' ), self::PAGE_SLUG, 'tpeu_defaults' );
		add_settings_field( 'tpeu_theme', esc_html__( 'Farbschema', 'turnierplan-eu' ), array( $this, 'render_theme_field' ), self::PAGE_SLUG, 'tpeu_defaults' );
		add_settings_field( 'tpeu_density', esc_html__( 'Dichte', 'turnierplan-eu' ), array( $this, 'render_density_field' ), self::PAGE_SLUG, 'tpeu_defaults' );

		add_settings_section( 'tpeu_data', esc_html__( 'Gespeicherte Daten', 'turnierplan-eu' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'tpeu_delete_data', esc_html__( 'Deinstallation', 'turnierplan-eu' ), array( $this, 'render_delete_field' ), self::PAGE_SLUG, 'tpeu_data' );
	}

	/**
	 * Sanitizes settings and clears metadata after revocation.
	 *
	 * @param mixed $value Untrusted Settings API value.
	 * @return array{service_enabled:bool,language:string,theme:string,density:string,delete_data:bool}
	 */
	public function sanitize_settings( mixed $value ): array {
		$was_enabled = $this->settings->is_service_enabled();
		$sanitized   = $this->settings->sanitize( $value );

		if ( $was_enabled && ! $sanitized['service_enabled'] ) {
			$this->cache->clear();
		}

		return $sanitized;
	}

	/** Renders the complete settings and connection-test screen. */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Turnierplan.eu', 'turnierplan-eu' ); ?></h1>
			<?php $this->render_test_notice(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'tpeu_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( esc_html__( 'Einstellungen speichern', 'turnierplan-eu' ) );
				?>
			</form>

			<hr>
			<h2><?php echo esc_html__( 'Verbindung testen', 'turnierplan-eu' ); ?></h2>
			<p><?php echo esc_html__( 'Der Test startet erst nach dem Absenden eine Serveranfrage und benötigt eine gespeicherte Dienstfreigabe.', 'turnierplan-eu' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="tpeu_connection_test">
				<?php wp_nonce_field( 'tpeu_connection_test' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tpeu-test-reference"><?php echo esc_html__( 'Turnierreferenz', 'turnierplan-eu' ); ?></label></th>
						<td><input id="tpeu-test-reference" name="reference" type="text" class="regular-text" maxlength="300" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="tpeu-test-language"><?php echo esc_html__( 'Sprache', 'turnierplan-eu' ); ?></label></th>
						<td>
							<select id="tpeu-test-language" name="language">
								<option value="auto"><?php echo esc_html__( 'Automatisch', 'turnierplan-eu' ); ?></option>
								<option value="de"><?php echo esc_html__( 'Deutsch', 'turnierplan-eu' ); ?></option>
								<option value="en"><?php echo esc_html__( 'Englisch', 'turnierplan-eu' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( esc_html__( 'Verbindung jetzt testen', 'turnierplan-eu' ), 'secondary' ); ?>
			</form>

			<h2><?php echo esc_html__( 'Datenübertragung und Seiten-Caches', 'turnierplan-eu' ); ?></h2>
			<p><?php echo esc_html__( 'Metadaten für Editor und Verbindungstest werden vom WordPress-Server abgerufen. Veröffentlichte Frames werden dagegen direkt im Browser des Besuchers geladen. Dabei gelten jeweils die Datenschutzinformationen von Turnierplan.eu.', 'turnierplan-eu' ); ?></p>
			<p><?php echo esc_html__( 'Beim Widerruf löscht das Plugin seinen Metadaten-Cache und erzeugt keine neuen Frames oder Abrufe. Bereits von einem Seiten- oder CDN-Cache gespeichertes HTML muss zusätzlich in der jeweiligen Cache-Lösung geleert werden.', 'turnierplan-eu' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $this->service->get_origin() . '/privacy.php' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Datenschutz bei Turnierplan.eu', 'turnierplan-eu' ); ?></a>
				<span aria-hidden="true"> · </span>
				<a href="<?php echo esc_url( $this->service->get_origin() . '/terms.php' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Nutzungsbedingungen', 'turnierplan-eu' ); ?></a>
			</p>
		</div>
		<?php
	}

	/** Renders approval status and the no-request-before-approval rule. */
	public function render_service_section(): void {
		$enabled = $this->settings->is_service_enabled();
		$status  = $enabled
			? esc_html__( 'Freigegeben. Editorabfragen und neue Einbettungen dürfen Turnierplan.eu verwenden.', 'turnierplan-eu' )
			: esc_html__( 'Nicht freigegeben. Es erfolgen keine Remote-Abfragen und neue Frames bleiben gesperrt.', 'turnierplan-eu' );

		printf( '<p><strong>%s</strong> %s</p>', esc_html__( 'Status:', 'turnierplan-eu' ), esc_html( $status ) );
		printf( '<p>%s</p>', esc_html__( 'Die Aktivierung des Plugins allein baut keine Verbindung auf. Die Freigabe gilt für diese WordPress-Installation und kann jederzeit widerrufen werden.', 'turnierplan-eu' ) );
	}

	/** Renders the external service approval checkbox. */
	public function render_service_field(): void {
		$settings = $this->settings->get();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[service_enabled]" value="1" <?php checked( $settings['service_enabled'] ); ?>>
			<?php echo esc_html__( 'Ich erlaube die Nutzung des externen Dienstes Turnierplan.eu für öffentliche Turnierinhalte.', 'turnierplan-eu' ); ?>
		</label>
		<?php
	}

	/** Renders the immutable service URL. */
	public function render_service_url_field(): void {
		printf( '<code>%s</code><p class="description">%s</p>', esc_html( $this->service->get_origin() ), esc_html__( 'Fest im Plugin hinterlegt und nicht durch Benutzer oder REST-Anfragen änderbar.', 'turnierplan-eu' ) );
	}

	/** Renders the default language field. */
	public function render_language_field(): void {
		$this->render_select(
			'language',
			array(
				'auto' => esc_html__( 'Automatisch', 'turnierplan-eu' ),
				'de'   => esc_html__( 'Deutsch', 'turnierplan-eu' ),
				'en'   => esc_html__( 'Englisch', 'turnierplan-eu' ),
			)
		);
	}

	/** Renders the default color scheme field. */
	public function render_theme_field(): void {
		$this->render_select(
			'theme',
			array(
				'auto'  => esc_html__( 'Automatisch', 'turnierplan-eu' ),
				'light' => esc_html__( 'Hell', 'turnierplan-eu' ),
				'dark'  => esc_html__( 'Dunkel', 'turnierplan-eu' ),
			)
		);
	}

	/** Renders the default density field. */
	public function render_density_field(): void {
		$this->render_select(
			'density',
			array(
				'comfortable' => esc_html__( 'Komfortabel', 'turnierplan-eu' ),
				'compact'     => esc_html__( 'Kompakt', 'turnierplan-eu' ),
			)
		);
	}

	/** Renders the future uninstall cleanup choice. */
	public function render_delete_field(): void {
		$settings = $this->settings->get();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[delete_data]" value="1" <?php checked( $settings['delete_data'] ); ?>>
			<?php echo esc_html__( 'Plugin-Daten beim Löschen entfernen', 'turnierplan-eu' ); ?>
		</label>
		<p class="description"><?php echo esc_html__( 'Die eigentliche Deinstallationsbereinigung wird mit dem Lebenszyklus in Block 14 aktiviert. Bis dahin wird diese Entscheidung nur gespeichert.', 'turnierplan-eu' ); ?></p>
		<?php
	}

	/** Handles the nonce-protected manual connection test. */
	public function handle_connection_test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Du darfst diese Verbindung nicht testen.', 'turnierplan-eu' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'tpeu_connection_test' );

		$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$language  = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'auto';
		$result    = $this->gateway->get( $reference, $language, true, 10 );
		$code      = $result->is_success() ? ( 'stale' === $result->get_source() ? 'stale' : 'success' ) : $result->get_code();
		$allowed   = array(
			'success',
			'stale',
			'service_not_enabled',
			'invalid_reference',
			'tournament_not_found',
			'rate_limited',
			'temporarily_unavailable',
			'timeout',
			'transport_error',
			'invalid_content_type',
			'invalid_json',
			'invalid_schema',
			'response_too_large',
			'remote_error',
		);

		if ( ! in_array( $code, $allowed, true ) ) {
			$code = 'remote_error';
		}

		$url = add_query_arg(
			array(
				'page'      => self::PAGE_SLUG,
				'tpeu_test' => $code,
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Renders one positive-list select field.
	 *
	 * @param string               $field   Setting field.
	 * @param array<string,string> $options Allowed labels by value.
	 * @return void
	 */
	private function render_select( string $field, array $options ): void {
		$settings = $this->settings->get();
		$name     = SettingsRepository::OPTION_NAME . '[' . $field . ']';

		printf( '<select name="%s">', esc_attr( $name ) );

		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $settings[ $field ], $value, false ), esc_html( $label ) );
		}

		echo '</select>';
	}

	/** Renders a whitelisted test result notice. */
	private function render_test_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A whitelisted query value only selects an admin notice after a nonce-protected action.
		$code = isset( $_GET['tpeu_test'] ) ? sanitize_key( wp_unslash( $_GET['tpeu_test'] ) ) : '';

		$messages = array(
			'success'                 => array( 'success', esc_html__( 'Verbindung erfolgreich. Die Metadaten wurden geprüft und gespeichert.', 'turnierplan-eu' ) ),
			'stale'                   => array( 'warning', esc_html__( 'Der Dienst war nicht erreichbar. Eine ältere, geprüfte Antwort ist noch vorhanden.', 'turnierplan-eu' ) ),
			'service_not_enabled'     => array( 'error', esc_html__( 'Speichere zuerst die Dienstfreigabe.', 'turnierplan-eu' ) ),
			'invalid_reference'       => array( 'error', esc_html__( 'Die Turnierreferenz oder Sprache ist ungültig.', 'turnierplan-eu' ) ),
			'tournament_not_found'    => array( 'error', esc_html__( 'Das Turnier wurde nicht gefunden oder ist nicht öffentlich freigegeben.', 'turnierplan-eu' ) ),
			'rate_limited'            => array( 'warning', esc_html__( 'Zu viele Anfragen. Bitte später erneut versuchen.', 'turnierplan-eu' ) ),
			'temporarily_unavailable' => array( 'warning', esc_html__( 'Turnierplan.eu ist vorübergehend nicht verfügbar.', 'turnierplan-eu' ) ),
			'timeout'                 => array( 'warning', esc_html__( 'Der Verbindungstest hat das Zeitlimit erreicht.', 'turnierplan-eu' ) ),
			'transport_error'         => array( 'error', esc_html__( 'Die sichere HTTPS-Verbindung konnte nicht aufgebaut werden.', 'turnierplan-eu' ) ),
			'invalid_content_type'    => array( 'error', esc_html__( 'Der Dienst hat keine JSON-Antwort geliefert.', 'turnierplan-eu' ) ),
			'invalid_json'            => array( 'error', esc_html__( 'Die JSON-Antwort des Dienstes war ungültig.', 'turnierplan-eu' ) ),
			'invalid_schema'          => array( 'error', esc_html__( 'Die Antwort entspricht nicht dem unterstützten Metadatenschema.', 'turnierplan-eu' ) ),
			'response_too_large'      => array( 'error', esc_html__( 'Die Antwort war größer als das erlaubte Limit.', 'turnierplan-eu' ) ),
			'remote_error'            => array( 'error', esc_html__( 'Der Verbindungstest ist mit einem unerwarteten Dienstfehler fehlgeschlagen.', 'turnierplan-eu' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}
}
