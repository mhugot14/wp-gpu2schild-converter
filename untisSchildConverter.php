<?php
/**
 * Plugin Name: Untis2Schild Converter
 * Plugin URI: https://www.lebk-muenster.de
 * Description: Wandelt die GPU002 aus Untis in eine Schild-Importdatei für die klassenweise Fachzuordnung um – mit Klassen- und Fachregeln, Lehrkraft-Konflikten und nachvollziehbarem Protokoll.
 * Version: 1.0.0-dev
 * Author: Michael Hugot
 * Author URI: https://berufsschulwissen.de
 * License: GPLv2
 * Text Domain: untis-schild
 * Requires PHP: 8.1
 */

declare(strict_types=1);

// Abbrechen, wenn direkt aufgerufen.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'admin_notices', function (): void {
		echo '<div class="notice notice-error"><p><strong>Untis2Schild Converter:</strong> '
			. 'vendor/autoload.php fehlt – bitte im Plugin-Ordner <code>composer install</code> ausführen.</p></div>';
	} );
	return;
}
require_once __DIR__ . '/vendor/autoload.php';

use Mh\UntisSchild\Setup\Activator;
use Mh\UntisSchild\Setup\Plugin_Bootstrap;

define( 'MH_USC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MH_USC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MH_USC_VERSION', '1.0.0-dev' );

register_activation_hook( __FILE__, function () {
	Activator::activate();
} );

register_deactivation_hook( __FILE__, function () {
	Activator::deactivate();
} );

/**
 * Schema-Migrationen auch ohne Reaktivieren ausführen (z. B. nach einem Update per ZIP).
 */
add_action( 'plugins_loaded', function () {
	if ( get_option( Activator::OPTION_DB_VERSION ) !== Activator::DB_VERSION ) {
		Activator::activate();
	}
}, 5 );

add_action( 'plugins_loaded', function () {
	( new Plugin_Bootstrap() )->init();
} );
