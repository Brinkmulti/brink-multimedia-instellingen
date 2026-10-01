<?php
// Voorkom direct aanroepen van dit bestand.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lichtgewicht GitHub-updater, zonder externe library (geen
 * plugin-update-checker of vergelijkbare dependency) — consistent met
 * het patroon dat in de rest van de Brinkmulti-suite gebruikt wordt
 * (Analytics, Frontend Posting Pro, SMTP).
 *
 * Bevraagt de GitHub Releases API van deze plugin, cachet het
 * resultaat (12 uur, of 15 minuten bij een mislukte aanroep, zodat de
 * API niet op elke admin-load bevraagd wordt), en corrigeert na een
 * update de mapnaam van de door GitHub aangeleverde zip.
 *
 * Pakket: bij voorkeur de zip die als bijlage (asset) aan de release hangt
 * ("brink-multimedia-instellingen.zip"); anders de broncode-zip van GitHub.
 * Een tag mag met "v" of "V" beginnen. Zit de plugin niet in het pakket,
 * dan wordt de update afgebroken in plaats van de plugin te beschadigen.
 */
class BMI_Updater {

	/**
	 * GitHub-repo in de vorm "organisatie/repo".
	 *
	 * @var string
	 */
	const GITHUB_REPO = 'Brinkmulti/brink-multimedia-instellingen';

	/**
	 * Transient-sleutel voor de gecachte laatste release.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'bmi_github_release';

	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * Vergelijk de laatste GitHub-release met de geïnstalleerde versie,
	 * en meld een update aan WordPress als die nieuwer is.
	 *
	 * @param object $transient De update-transient van WordPress.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$plugin_file = BMI_PLUGIN_BASENAME;
		$release     = $this->get_github_release();

		if ( ! $release || empty( $release['tag_name'] ) ) {
			return $transient;
		}

		$remote_version = self::parse_tag( $release['tag_name'] );

		if ( '' !== $remote_version && version_compare( $remote_version, BMI_VERSION, '>' ) ) {
			$package = self::get_package_url( $release );

			$transient->response[ $plugin_file ] = (object) array(
				'slug'        => dirname( $plugin_file ),
				'plugin'      => $plugin_file,
				'new_version' => $remote_version,
				'url'         => 'https://github.com/' . self::GITHUB_REPO,
				'package'     => $package,
			);
		} else {
			unset( $transient->response[ $plugin_file ] );
		}

		return $transient;
	}

	/**
	 * Haal de laatste release op van de GitHub Releases API, met
	 * caching zodat dit geen externe HTTP-aanroep per admin-load wordt.
	 *
	 * @return array|false
	 */
	private function get_github_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return ! empty( $cached ) ? $cached : false;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
			array(
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, array(), 15 * MINUTE_IN_SECONDS );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body ) || ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( self::CACHE_KEY, array(), 15 * MINUTE_IN_SECONDS );
			return false;
		}

		set_transient( self::CACHE_KEY, $body, 12 * HOUR_IN_SECONDS );

		return $body;
	}

	/**
	 * GitHub's zipball plaatst de bestanden in een map als
	 * "Brinkmulti-brink-multimedia-instellingen-abc1234" in plaats van
	 * de verwachte pluginmap-naam. Zonder deze fix denkt WordPress dat
	 * de plugin na een update gedeactiveerd/verwijderd is.
	 *
	 * @param string      $source        Pad naar de uitgepakte, aangeleverde map.
	 * @param string      $remote_source Pad naar de tijdelijke downloadmap.
	 * @param WP_Upgrader $upgrader      De upgrader-instantie.
	 * @param array       $hook_extra    Extra context, incl. de bijgewerkte plugin.
	 * @return string
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			return $source;
		}

		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== BMI_PLUGIN_BASENAME ) {
			return $source;
		}

		// Veiligheid: zit het hoofdbestand van de plugin niet in het pakket (bijv. omdat de repository
		// alleen een zip bevat), dan de update afbreken. WordPress laat de huidige versie dan staan.
		if ( ! $wp_filesystem->exists( trailingslashit( $source ) . basename( BMI_PLUGIN_FILE ) ) ) {
			return new WP_Error(
				'bmi_invalid_package',
				__( 'Het updatepakket van Brink Multimedia Instellingen bevat de plugin niet. De update is afgebroken en de huidige versie blijft actief. Hang de plugin-zip als bijlage aan de GitHub-release.', 'brink-multimedia-instellingen' )
			);
		}

		$correct_dir = trailingslashit( $remote_source ) . dirname( BMI_PLUGIN_BASENAME ) . '/';

		if ( trailingslashit( $source ) !== $correct_dir && $wp_filesystem->move( $source, $correct_dir ) ) {
			return $correct_dir;
		}

		return $source;
	}
	/**
	 * Versienummer uit een tag: "v1.6.0" en "V1.6.0" worden allebei "1.6.0".
	 * Geeft '' terug als de tag geen versienummer is.
	 *
	 * @param string $tag Tag van de release.
	 * @return string
	 */
	public static function parse_tag( $tag ) {
		$version = ltrim( trim( (string) $tag ), 'vV' );
		return preg_match( '/^\d+(\.\d+)*$/', $version ) ? $version : '';
	}

	/**
	 * Downloadadres van het pakket: eerst de bijlage "brink-multimedia-instellingen.zip", dan een andere
	 * zip-bijlage, en anders de broncode-zip (zipball) van GitHub.
	 *
	 * @param array $release Release-gegevens van de GitHub API.
	 * @return string
	 */
	public static function get_package_url( $release ) {
		$expected = dirname( BMI_PLUGIN_BASENAME ) . '.zip';
		$fallback = '';

		foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
			if ( empty( $asset['browser_download_url'] ) || empty( $asset['name'] ) || '.zip' !== strtolower( substr( $asset['name'], -4 ) ) ) {
				continue;
			}
			if ( strtolower( $asset['name'] ) === $expected ) {
				return $asset['browser_download_url'];
			}
			if ( ! $fallback ) {
				$fallback = $asset['browser_download_url'];
			}
		}

		if ( $fallback ) {
			return $fallback;
		}
		return ! empty( $release['zipball_url'] ) ? $release['zipball_url'] : '';
	}
}
