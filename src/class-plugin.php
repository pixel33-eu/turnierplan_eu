<?php
/**
 * Plugin runtime root.
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

namespace TurnierplanEU\WordPress;

use TurnierplanEU\WordPress\Cache\MetadataCache;
use TurnierplanEU\WordPress\Cache\WordPressCacheStore;
use TurnierplanEU\WordPress\Config\EmbedUrlBuilder;
use TurnierplanEU\WordPress\Config\ServiceConfiguration;
use TurnierplanEU\WordPress\Remote\MetadataClient;
use TurnierplanEU\WordPress\Remote\MetadataGateway;
use TurnierplanEU\WordPress\Remote\MetadataResponseValidator;
use TurnierplanEU\WordPress\Remote\TrustedMetadataUrl;
use TurnierplanEU\WordPress\Remote\WordPressHttpTransport;
use TurnierplanEU\WordPress\Rest\MetadataController;
use TurnierplanEU\WordPress\Rest\UserRateLimiter;
use TurnierplanEU\WordPress\Render\EmbedRenderer;
use TurnierplanEU\WordPress\Render\WordPressFrontendAssets;
use TurnierplanEU\WordPress\Render\WordPressInstanceIdGenerator;
use TurnierplanEU\WordPress\Render\WordPressParentOrigin;
use TurnierplanEU\WordPress\Settings\PrivacyPolicy;
use TurnierplanEU\WordPress\Settings\SettingsPage;
use TurnierplanEU\WordPress\Settings\SettingsRepository;
use TurnierplanEU\WordPress\Shortcode\ShortcodeHandler;

/**
 * Starts feature registration after all active plugins are loaded.
 */
final class Plugin {

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Creates the runtime root.
	 *
	 * @param string $version Current plugin version.
	 */
	public function __construct( string $version ) {
		$this->version = $version;
	}

	/**
	 * Registers the plugin runtime.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'announce_loaded' ) );
	}

	/**
	 * Announces that the plugin is ready for feature registration.
	 *
	 * @return void
	 */
	public function announce_loaded(): void {
		$service   = ServiceConfiguration::production();
		$builder   = new EmbedUrlBuilder( $service );
		$validator = new MetadataResponseValidator();
		$cache     = new MetadataCache( new WordPressCacheStore(), $validator );
		$settings  = new SettingsRepository();
		$client    = new MetadataClient(
			$builder,
			new TrustedMetadataUrl( $service ),
			new WordPressHttpTransport(),
			$validator,
			$this->version
		);
		$gateway   = new MetadataGateway( $settings, $cache, $client );

		( new SettingsPage( $settings, $cache, $gateway, $service ) )->register();
		( new PrivacyPolicy() )->register();
		( new MetadataController( $gateway, new UserRateLimiter() ) )->register();

		$renderer = new EmbedRenderer(
			$settings,
			$builder,
			$service,
			new WordPressParentOrigin(),
			new WordPressInstanceIdGenerator(),
			new WordPressFrontendAssets( TPEU_PLUGIN_FILE, $this->version )
		);

		( new ShortcodeHandler( $renderer ) )->register();

		/**
		 * Fires after the Turnierplan.eu runtime has passed its requirement checks.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin $plugin Runtime root instance.
		 */
		do_action( 'tpeu_loaded', $this );
	}

	/**
	 * Returns the current plugin version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}
}
