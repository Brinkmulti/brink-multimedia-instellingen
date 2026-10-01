<?php
// Voorkom direct aanroepen van dit bestand.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regelt het tabblad "Log in/uit | Registreer": eigen login-URL,
 * brute-force bescherming, eigen uiterlijk van het inlogscherm en
 * het uitschakelen van auteur-archiefpagina's.
 */
class BMI_Login {

	/**
	 * De ingestelde login-slug, zonder slashes. Leeg = functie uitgeschakeld.
	 *
	 * @var string
	 */
	private $login_slug = '';

	/**
	 * Opgeslagen instellingen voor dit tabblad.
	 *
	 * @var array
	 */
	private $options = array();

	public function __construct() {
		$this->options    = get_option( 'bmi_login_options', array() );
		$this->login_slug = ! empty( $this->options['login_slug'] ) ? trim( $this->options['login_slug'], '/' ) : '';

		// WordPress-kern geeft op sommige PHP-versies onschuldige
		// "Undefined variable"-waarschuwingen op de inlogpagina zelf.
		// 'login_init' vuurt altijd af, ongeacht welke route er naar de
		// inlogpagina leidt, dus hier onderdrukken we ze.
		add_action( 'login_init', array( $this, 'suppress_core_login_warnings' ), 1 );

		// Brute-force bescherming.
		if ( ! empty( $this->options['enable_lockout'] ) ) {
			add_filter( 'authenticate', array( $this, 'maybe_block_locked_ip' ), 1 );
			add_action( 'wp_login_failed', array( $this, 'register_failed_attempt' ) );
			add_action( 'wp_login', array( $this, 'clear_failed_attempts' ) );
		}

		// Eigen uiterlijk van het inlogscherm.
		if ( $this->has_custom_login_look() ) {
			add_action( 'login_enqueue_scripts', array( $this, 'output_login_screen_css' ) );
			add_filter( 'login_headerurl', array( $this, 'filter_login_header_url' ) );
			add_filter( 'login_headertext', array( $this, 'filter_login_header_text' ) );
		}

		// Auteur-archiefpagina's uitschakelen (voorkomt username-enumeratie).
		if ( ! empty( $this->options['disable_author_archives'] ) ) {
			add_action( 'template_redirect', array( $this, 'maybe_block_author_archive' ) );
			add_filter( 'redirect_canonical', array( $this, 'maybe_prevent_author_canonical_redirect' ), 10, 2 );
		}

		// Als er geen eigen login-slug is ingesteld, blijft /wp-login.php
		// verder gewoon normaal werken.
		if ( '' === $this->login_slug ) {
			return;
		}

		add_action( 'init', array( $this, 'maybe_serve_login_page' ) );
		add_action( 'wp_loaded', array( $this, 'maybe_block_default_login' ) );

		add_filter( 'site_url', array( $this, 'filter_login_url' ), 10, 1 );
		add_filter( 'network_site_url', array( $this, 'filter_login_url' ), 10, 1 );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 1 );
		add_filter( 'wp_redirect', array( $this, 'filter_login_url' ), 10, 1 );
	}

	/**
	 * Onderdruk onschuldige PHP-waarschuwingen (undefined variable e.d.)
	 * die WordPress-kern soms op de inlogpagina zelf veroorzaakt.
	 */
	public function suppress_core_login_warnings() {
		error_reporting( error_reporting() & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
	}

	// ------------------------------------------------------------
	// Brute-force bescherming
	// ------------------------------------------------------------

	/**
	 * Weiger het inloggen als het IP-adres momenteel geblokkeerd is
	 * wegens te veel mislukte pogingen.
	 *
	 * @param WP_User|WP_Error|null $user Huidige authenticatiestatus.
	 * @return WP_User|WP_Error|null
	 */
	public function maybe_block_locked_ip( $user ) {
		$ip = $this->get_client_ip();

		if ( get_transient( 'bmi_login_lockout_' . md5( $ip ) ) ) {
			return new WP_Error(
				'bmi_locked_out',
				__( '<strong>Fout</strong>: te veel mislukte inlogpogingen. Probeer het over enkele minuten opnieuw.', 'brink-multimedia-instellingen' )
			);
		}

		return $user;
	}

	/**
	 * Tel een mislukte inlogpoging voor het huidige IP-adres, en
	 * blokkeer het IP-adres tijdelijk zodra de limiet bereikt is.
	 */
	public function register_failed_attempt() {
		$ip      = $this->get_client_ip();
		$key     = 'bmi_login_attempts_' . md5( $ip );
		$count   = (int) get_transient( $key );
		++$count;
		$minutes = max( 1, intval( $this->options['lockout_minutes'] ) );

		set_transient( $key, $count, $minutes * MINUTE_IN_SECONDS );

		if ( $count >= max( 1, intval( $this->options['max_attempts'] ) ) ) {
			set_transient( 'bmi_login_lockout_' . md5( $ip ), 1, $minutes * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Reset de teller na een geslaagde login.
	 */
	public function clear_failed_attempts() {
		$ip = $this->get_client_ip();
		delete_transient( 'bmi_login_attempts_' . md5( $ip ) );
		delete_transient( 'bmi_login_lockout_' . md5( $ip ) );
	}

	/**
	 * Haal het IP-adres van de huidige bezoeker op.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
	}

	// ------------------------------------------------------------
	// Eigen uiterlijk van het inlogscherm
	// ------------------------------------------------------------

	/**
	 * @return bool
	 */
	private function has_custom_login_look() {
		return ! empty( $this->options['logo_url'] )
			|| ! empty( $this->options['background_color'] )
			|| ! empty( $this->options['accent_color'] )
			|| ! empty( $this->options['background_image_url'] );
	}

	/**
	 * Print de custom CSS voor het inlogscherm.
	 */
	public function output_login_screen_css() {
		$css = '';

		if ( ! empty( $this->options['logo_url'] ) ) {
			$width = ! empty( $this->options['logo_width'] ) ? intval( $this->options['logo_width'] ) : 84;
			$logo  = esc_url( $this->options['logo_url'] );
			$css  .= "
				#login h1 a, .login h1 a {
					background-image: url('{$logo}');
					background-size: contain;
					background-position: center;
					background-repeat: no-repeat;
					width: {$width}px;
					height: {$width}px;
				}
			";
		}

		if ( ! empty( $this->options['background_color'] ) ) {
			$bg   = sanitize_hex_color( $this->options['background_color'] );
			$css .= $bg ? "body.login { background-color: {$bg}; }" : '';
		}

		if ( ! empty( $this->options['background_image_url'] ) ) {
			$bg_image = esc_url( $this->options['background_image_url'] );
			$css     .= "body.login { background-image: url('{$bg_image}'); background-size: cover; background-position: center; background-repeat: no-repeat; }";
		}

		if ( ! empty( $this->options['accent_color'] ) ) {
			$accent = sanitize_hex_color( $this->options['accent_color'] );
			if ( $accent ) {
				$css .= "
					.login .button-primary {
						background: {$accent} !important;
						border-color: {$accent} !important;
						box-shadow: none !important;
						text-shadow: none !important;
					}
					.login #backtoblog a, .login #nav a { color: {$accent}; }
					.login form .input:focus, .login input[type=text]:focus, .login input[type=password]:focus {
						border-color: {$accent} !important;
						box-shadow: 0 0 0 1px {$accent} !important;
					}
				";
			}
		}

		if ( '' !== trim( $css ) ) {
			echo '<style id="bmi-login-screen-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * @return string
	 */
	public function filter_login_header_url() {
		return home_url( '/' );
	}

	/**
	 * @return string
	 */
	public function filter_login_header_text() {
		return get_bloginfo( 'name' );
	}

	// ------------------------------------------------------------
	// Auteur-archieven uitschakelen
	// ------------------------------------------------------------

	/**
	 * Stuur bezoekers van een auteur-archiefpagina door naar de homepage,
	 * zodat gebruikersnamen niet via /?author=1 te achterhalen zijn.
	 */
	public function maybe_block_author_archive() {
		if ( is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * Voorkom dat WordPress' eigen redirect_canonical() al doorverwijst
	 * naar de "mooie" auteur-archief-URL (bijv. /author/gebruikersnaam/)
	 * vóórdat onze eigen blokkade de kans krijgt om in te grijpen. Zonder
	 * deze fix lekt de gebruikersnaam alsnog via de Location-header van
	 * die eerdere, door WordPress-kern zelf gestuurde doorverwijzing.
	 *
	 * @param string|false $redirect_url  De door WordPress berekende canonieke URL.
	 * @param string       $requested_url De opgevraagde URL.
	 * @return string|false
	 */
	public function maybe_prevent_author_canonical_redirect( $redirect_url, $requested_url ) {
		if ( is_author() ) {
			return false;
		}
		return $redirect_url;
	}

	// ------------------------------------------------------------
	// Eigen login-URL
	// ------------------------------------------------------------

	/**
	 * Haal alleen het pad (zonder query string, zonder leading/trailing slash) op.
	 *
	 * @return string
	 */
	private function get_request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return trim( $path, '/' );
	}

	/**
	 * Als iemand de eigen login-slug bezoekt, laad dan gewoon wp-login.php.
	 */
	public function maybe_serve_login_page() {
		if ( $this->get_request_path() !== $this->login_slug ) {
			return;
		}

		global $pagenow;
		$pagenow = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

		require ABSPATH . 'wp-login.php';
		die;
	}

	/**
	 * Blokkeer directe bezoeken aan het standaard /wp-login.php (en
	 * /wp-signup.php), behalve wanneer WordPress deze URL zelf nodig
	 * heeft om een formulier te verwerken.
	 */
	public function maybe_block_default_login() {
		$path = $this->get_request_path();

		if ( 'wp-login.php' !== $path && 'wp-signup.php' !== $path ) {
			return;
		}

		if ( $this->is_allowed_direct_request() ) {
			return;
		}

		global $wp_query;
		if ( $wp_query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
		require get_404_template() ? get_404_template() : ABSPATH . 'wp-content/themes/index.php';
		die;
	}

	/**
	 * @return bool
	 */
	private function is_allowed_direct_request() {
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			return true;
		}

		$allowed_get_actions = array( 'rp', 'resetpass', 'postpass', 'confirmaction' );
		if ( isset( $_GET['action'] ) && in_array( wp_unslash( $_GET['action'] ), $allowed_get_actions, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( isset( $_GET['loggedout'] ) || isset( $_GET['checkemail'] ) || isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * @param string $url De originele URL.
	 * @return string
	 */
	public function filter_login_url( $url ) {
		if ( is_string( $url ) && false !== strpos( $url, 'wp-login.php' ) ) {
			$url = str_replace( 'wp-login.php', $this->login_slug, $url );
		}
		return $url;
	}
}
