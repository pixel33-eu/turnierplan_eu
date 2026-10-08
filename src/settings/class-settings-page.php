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
use TurnierplanEU\WordPress\Preset\Capabilities;
use TurnierplanEU\WordPress\Localization\SiteLanguageResolver;

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
		private readonly ServiceConfiguration $service,
		private readonly SiteLanguageResolver $languages
	) {
	}

	/** Registers admin hooks. */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_tpeu_connection_test', array( $this, 'handle_connection_test' ) );
		add_filter( 'option_page_capability_tpeu_settings_group', array( $this, 'settings_capability' ) );
	}

	/** Returns the dedicated capability used by the Settings API. */
	public function settings_capability(): string {
		return Capabilities::MANAGE_SETTINGS;
	}

	/** Adds the page below the WordPress Settings menu. */
	public function add_page(): void {
		add_options_page(
			esc_html__( 'Turnierplan.eu', 'turnierplan-eu' ),
			esc_html__( 'Turnierplan.eu', 'turnierplan-eu' ),
			Capabilities::MANAGE_SETTINGS,
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
			esc_html__( 'External service', 'turnierplan-eu' ),
			array( $this, 'render_service_section' ),
			self::PAGE_SLUG
		);
		add_settings_field( 'tpeu_service_enabled', esc_html__( 'Connection', 'turnierplan-eu' ), array( $this, 'render_service_field' ), self::PAGE_SLUG, 'tpeu_service' );
		add_settings_field( 'tpeu_service_url', esc_html__( 'Service URL', 'turnierplan-eu' ), array( $this, 'render_service_url_field' ), self::PAGE_SLUG, 'tpeu_service' );

		add_settings_section( 'tpeu_defaults', esc_html__( 'Defaults for new embeds', 'turnierplan-eu' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'tpeu_language', esc_html__( 'Language', 'turnierplan-eu' ), array( $this, 'render_language_field' ), self::PAGE_SLUG, 'tpeu_defaults' );
		add_settings_field( 'tpeu_theme', esc_html__( 'Color scheme', 'turnierplan-eu' ), array( $this, 'render_theme_field' ), self::PAGE_SLUG, 'tpeu_defaults' );
		add_settings_field( 'tpeu_density', esc_html__( 'Density', 'turnierplan-eu' ), array( $this, 'render_density_field' ), self::PAGE_SLUG, 'tpeu_defaults' );

		add_settings_section( 'tpeu_data', esc_html__( 'Stored data', 'turnierplan-eu' ), '__return_false', self::PAGE_SLUG );
		add_settings_field( 'tpeu_delete_data', esc_html__( 'Uninstallation', 'turnierplan-eu' ), array( $this, 'render_delete_field' ), self::PAGE_SLUG, 'tpeu_data' );
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
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
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
				submit_button( esc_html__( 'Save settings', 'turnierplan-eu' ) );
				?>
			</form>

			<hr>
			<h2><?php echo esc_html__( 'Test connection', 'turnierplan-eu' ); ?></h2>
			<p><?php echo esc_html__( 'The test starts a server request only after submission and requires saved service approval.', 'turnierplan-eu' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="tpeu_connection_test">
				<?php wp_nonce_field( 'tpeu_connection_test' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tpeu-test-reference"><?php echo esc_html__( 'Tournament reference', 'turnierplan-eu' ); ?></label></th>
						<td><input id="tpeu-test-reference" name="reference" type="text" class="regular-text" maxlength="300" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="tpeu-test-language"><?php echo esc_html__( 'Language', 'turnierplan-eu' ); ?></label></th>
						<td>
							<select id="tpeu-test-language" name="language">
								<option value="auto"><?php echo esc_html__( 'Automatic', 'turnierplan-eu' ); ?></option>
								<option value="de"><?php echo esc_html__( 'German', 'turnierplan-eu' ); ?></option>
								<option value="en"><?php echo esc_html__( 'English', 'turnierplan-eu' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( esc_html__( 'Test connection now', 'turnierplan-eu' ), 'secondary' ); ?>
			</form>

			<h2><?php echo esc_html__( 'Data transfer and page caches', 'turnierplan-eu' ); ?></h2>
			<p><?php echo esc_html__( 'Metadata for the editor and connection test is retrieved by the WordPress server. Published frames are loaded directly in the visitor\'s browser. The Turnierplan.eu privacy information applies in each case.', 'turnierplan-eu' ); ?></p>
			<p><?php echo esc_html__( 'When access is revoked, the plugin clears its metadata cache and creates no new frames or requests. HTML already stored by a page or CDN cache must also be cleared in that cache solution.', 'turnierplan-eu' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $this->service->get_origin() . '/privacy.php' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Privacy at Turnierplan.eu', 'turnierplan-eu' ); ?></a>
				<span aria-hidden="true"> · </span>
				<a href="<?php echo esc_url( $this->service->get_origin() . '/terms.php' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Terms of use', 'turnierplan-eu' ); ?></a>
			</p>
		</div>
		<?php
	}

	/** Renders approval status and the no-request-before-approval rule. */
	public function render_service_section(): void {
		$enabled = $this->settings->is_service_enabled();
		$status  = $enabled
			? esc_html__( 'Enabled. Editor requests and new embeds may use Turnierplan.eu.', 'turnierplan-eu' )
			: esc_html__( 'Disabled. No remote requests are made and new frames remain blocked.', 'turnierplan-eu' );

		printf( '<p><strong>%s</strong> %s</p>', esc_html__( 'Status:', 'turnierplan-eu' ), esc_html( $status ) );
		printf( '<p>%s</p>', esc_html__( 'Activating the plugin alone does not establish a connection. Approval applies to this WordPress installation and can be revoked at any time.', 'turnierplan-eu' ) );
	}

	/** Renders the external service approval checkbox. */
	public function render_service_field(): void {
		$settings = $this->settings->get();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[service_enabled]" value="1" <?php checked( $settings['service_enabled'] ); ?>>
			<?php echo esc_html__( 'I allow use of the external Turnierplan.eu service for public tournament content.', 'turnierplan-eu' ); ?>
		</label>
		<?php
	}

	/** Renders the immutable service URL. */
	public function render_service_url_field(): void {
		printf( '<code>%s</code><p class="description">%s</p>', esc_html( $this->service->get_origin() ), esc_html__( 'Built into the plugin and not editable by users or REST requests.', 'turnierplan-eu' ) );
	}

	/** Renders the default language field. */
	public function render_language_field(): void {
		$this->render_select(
			'language',
			array(
				'auto' => esc_html__( 'Automatic', 'turnierplan-eu' ),
				'de'   => esc_html__( 'German', 'turnierplan-eu' ),
				'en'   => esc_html__( 'English', 'turnierplan-eu' ),
			)
		);
	}

	/** Renders the default color scheme field. */
	public function render_theme_field(): void {
		$this->render_select(
			'theme',
			array(
				'auto'  => esc_html__( 'Automatic', 'turnierplan-eu' ),
				'light' => esc_html__( 'Light', 'turnierplan-eu' ),
				'dark'  => esc_html__( 'Dark', 'turnierplan-eu' ),
			)
		);
	}

	/** Renders the default density field. */
	public function render_density_field(): void {
		$this->render_select(
			'density',
			array(
				'comfortable' => esc_html__( 'Comfortable', 'turnierplan-eu' ),
				'compact'     => esc_html__( 'Compact', 'turnierplan-eu' ),
			)
		);
	}

	/** Renders the future uninstall cleanup choice. */
	public function render_delete_field(): void {
		$settings = $this->settings->get();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[delete_data]" value="1" <?php checked( $settings['delete_data'] ); ?>>
			<?php echo esc_html__( 'Remove plugin data on deletion', 'turnierplan-eu' ); ?>
		</label>
		<p class="description"><?php echo esc_html__( 'The actual uninstall cleanup will be enabled with the lifecycle work in Block 14. Until then, this choice is only stored.', 'turnierplan-eu' ); ?></p>
		<?php
	}

	/** Handles the nonce-protected manual connection test. */
	public function handle_connection_test(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You are not allowed to test this connection.', 'turnierplan-eu' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'tpeu_connection_test' );

		$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$language  = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'auto';
		$result    = $this->gateway->get( $reference, $this->languages->resolve( $language ), true, 10 );
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
			'success'                 => array( 'success', esc_html__( 'Connection successful. The metadata was validated and stored.', 'turnierplan-eu' ) ),
			'stale'                   => array( 'warning', esc_html__( 'The service was unavailable. An older validated response is still available.', 'turnierplan-eu' ) ),
			'service_not_enabled'     => array( 'error', esc_html__( 'Save the service approval first.', 'turnierplan-eu' ) ),
			'invalid_reference'       => array( 'error', esc_html__( 'The tournament reference or language is invalid.', 'turnierplan-eu' ) ),
			'tournament_not_found'    => array( 'error', esc_html__( 'The tournament was not found or is not publicly available.', 'turnierplan-eu' ) ),
			'rate_limited'            => array( 'warning', esc_html__( 'Too many requests. Try again later.', 'turnierplan-eu' ) ),
			'temporarily_unavailable' => array( 'warning', esc_html__( 'Turnierplan.eu is temporarily unavailable.', 'turnierplan-eu' ) ),
			'timeout'                 => array( 'warning', esc_html__( 'The connection test timed out.', 'turnierplan-eu' ) ),
			'transport_error'         => array( 'error', esc_html__( 'The secure HTTPS connection could not be established.', 'turnierplan-eu' ) ),
			'invalid_content_type'    => array( 'error', esc_html__( 'The service did not return a JSON response.', 'turnierplan-eu' ) ),
			'invalid_json'            => array( 'error', esc_html__( 'The service returned invalid JSON.', 'turnierplan-eu' ) ),
			'invalid_schema'          => array( 'error', esc_html__( 'The response does not match the supported metadata schema.', 'turnierplan-eu' ) ),
			'response_too_large'      => array( 'error', esc_html__( 'The response exceeded the permitted size.', 'turnierplan-eu' ) ),
			'remote_error'            => array( 'error', esc_html__( 'The connection test failed with an unexpected service error.', 'turnierplan-eu' ) ),
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
