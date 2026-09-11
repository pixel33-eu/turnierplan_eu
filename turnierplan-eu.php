<?php
/**
 * Plugin Name:       Turnierplan.eu – Turniere einbetten
 * Plugin URI:        https://www.turnierplan.eu/
 * Description:       Bindet öffentliche Turnierpläne, Tabellen und Ergebnisse von Turnierplan.eu in WordPress ein.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Author:            Pixel33 Software
 * Author URI:        https://pixel33.eu/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       turnierplan-eu
 * Domain Path:       /languages
 *
 * @package TurnierplanEU
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TPEU_VERSION', '0.1.0' );
define( 'TPEU_PLUGIN_FILE', __FILE__ );
define( 'TPEU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once TPEU_PLUGIN_DIR . 'src/class-autoloader.php';

TurnierplanEU\WordPress\Autoloader::register();
TurnierplanEU\WordPress\Bootstrap::boot( TPEU_PLUGIN_FILE, TPEU_VERSION );
