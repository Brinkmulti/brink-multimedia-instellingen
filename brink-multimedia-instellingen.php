<?php
/**
 * Plugin Name:       Brink Multimedia Instellingen
 * Plugin URI:        https://github.com/Brinkmulti/brink-multimedia-instellingen
 * Description:       Centrale plugin voor het aanpassen van diverse WordPress-instellingen (login, dashboard layout, contentbeheer, rollen & rechten, e-mail) voor Brink Multimedia. Geïnspireerd op Admin and Site Enhancements (ASE).
 * Version:           1.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Brink Multimedia
 * Author URI:        https://github.com/Brinkmulti
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       brink-multimedia-instellingen
 * Domain Path:       /languages
 * Update URI:        false
 */

// Voorkom direct aanroepen van dit bestand.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --------------------------------------------------------------------
// Constanten
// --------------------------------------------------------------------
define( 'BMI_VERSION', '1.6.0' );
define( 'BMI_PLUGIN_FILE', __FILE__ );
define( 'BMI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BMI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Hoofdklasse van de plugin. Laadt alle onderdelen in en zorgt dat
 * ze alleen actief worden waar dat nodig is (admin vs frontend/login).
 */
final class Brink_Multimedia_Instellingen {

	/**
	 * Enige instantie van deze klasse (singleton).
	 *
	 * @var Brink_Multimedia_Instellingen|null
	 */
	private static $instance = null;

	/**
	 * Haal de enige instantie op, en maak hem aan indien nodig.
	 *
	 * @return Brink_Multimedia_Instellingen
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Privé-constructor: laadt bestanden en start de onderdelen.
	 */
	private function __construct() {
		$this->includes();
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Laad alle benodigde bestanden.
	 */
	private function includes() {
		require_once BMI_PLUGIN_DIR . 'includes/class-bmi-settings.php';
		require_once BMI_PLUGIN_DIR . 'includes/class-bmi-login.php';
		require_once BMI_PLUGIN_DIR . 'includes/class-bmi-dashboard.php';
		require_once BMI_PLUGIN_DIR . 'includes/class-bmi-roles.php';
		require_once BMI_PLUGIN_DIR . 'includes/class-bmi-content.php';
		require_once BMI_PLUGIN_DIR . 'includes/class-bmi-email.php';
		require_once BMI_PLUGIN_DIR . 'includes/class-bmi-updater.php';
	}

	/**
	 * Start alle onderdelen van de plugin.
	 */
	public function init() {
		load_plugin_textdomain( 'brink-multimedia-instellingen', false, dirname( BMI_PLUGIN_BASENAME ) . '/languages' );

		// Instellingenpagina (altijd nodig, ook in admin).
		new BMI_Settings();

		// Login-URL aanpassen werkt zowel op front-end (login/logout links)
		// als voor het onderscheppen van het verzoek zelf, dus altijd laden.
		new BMI_Login();

		// Rechten (zoals "Verwijderen uitsluiten") moeten overal gelden, ook bij de REST API.
		BMI_Roles::register_capability_filters();

		// Dashboard-aanpassingen zijn alleen relevant in wp-admin.
		if ( is_admin() ) {
			new BMI_Dashboard();
			new BMI_Roles();
		}

		// Contentbeheer (dupliceren, upload-mimetypes) werkt in de admin
		// en de upload-mimetype-filters gelden ook voor REST/uploads.
		new BMI_Content();

		// E-mailinstellingen gelden voor alle uitgaande WordPress-mail,
		// dus altijd laden (ook op de front-end).
		new BMI_Email();

		// De updatecheck moet ook tijdens wp-cron kunnen draaien (WordPress'
		// eigen achtergrond-updatecheck), dus niet beperken tot is_admin().
		new BMI_Updater();
	}
}

Brink_Multimedia_Instellingen::get_instance();
