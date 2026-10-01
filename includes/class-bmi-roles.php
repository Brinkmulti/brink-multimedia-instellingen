<?php
// Voorkom direct aanroepen van dit bestand.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regelt het tabblad "Rollen & Rechten": een "mag zien"-model (allow-list)
 * per rol (behalve Beheerder) voor menu-onderdelen (inclusief submenu's),
 * met echte blokkade bij direct URL-bezoek, plus per-rol restricties op
 * plugin-eigen functies (dupliceren, SVG-/AVIF-uploads) en het recht om
 * te verwijderen.
 *
 * Nieuwe menu-onderdelen die later verschijnen (bijv. door een nieuwe
 * plugin) zijn standaard verborgen/geblokkeerd totdat een beheerder ze
 * bewust toestaat — bewust "veilig-standaard" in plaats van "verberg wat
 * niet mag".
 */
class BMI_Roles {

	/**
	 * Slugs die nooit verborgen/geblokkeerd worden, om te voorkomen dat
	 * iemand zichzelf per ongeluk buitensluit van zijn eigen profiel of
	 * het dashboard direct na het inloggen.
	 *
	 * @var string[]
	 */
	const ALWAYS_ALLOWED_SLUGS = array( 'profile.php', 'index.php' );

	/**
	 * Opgeslagen instellingen voor dit tabblad.
	 *
	 * @var array
	 */
	private $options;

	public function __construct() {
		$this->options = wp_parse_args( get_option( 'bmi_roles_options', array() ), self::get_defaults() );

		// De toegangscontrole draait bewust in 'admin_menu' (niet in 'admin_init'): pas dan is
		// het volledige menu bekend, zodat we alleen échte menupagina's blokkeren. Technische
		// pagina's zoals admin-post.php (formulieren opslaan) en async-upload.php (uploads)
		// laden het menu niet en worden dus nooit ten onrechte geblokkeerd.
		add_action( 'admin_menu', array( $this, 'maybe_migrate_and_apply' ), 999 );
	}

	/**
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'allowed_pages'           => array(),
			'restrict_duplicate'      => array(),
			'restrict_svg_upload'     => array(),
			'restrict_avif_upload'    => array(),
			'restrict_delete'         => array(),
			'migrated_to_allow_model' => 0,
		);
	}

	/**
	 * Migreer (eenmalig) naar het "mag zien"-model, en pas daarna de
	 * zichtbaarheid van menu-items toe voor de huidige gebruiker.
	 */
	public function maybe_migrate_and_apply() {
		if ( empty( $this->options['migrated_to_allow_model'] ) ) {
			$this->migrate_to_allow_model();
		}

		// Eerst controleren (met het volledige menu), daarna pas menu-items verbergen.
		$this->block_direct_access();
		$this->hide_disallowed_pages();
	}

	/**
	 * Zet de oude "verborgen voor rol"-lijst (deny-list) om naar de
	 * nieuwe "mag zien"-lijst (allow-list), zodat het zichtbare gedrag
	 * op bestaande sites in eerste instantie precies gelijk blijft.
	 * Alles wat niet expliciet verborgen was, wordt toegestaan; nieuwe,
	 * nog onbekende menu-items op dit moment worden ook meteen
	 * toegestaan (ze bestonden immers al vóór de omschakeling). Menu-
	 * items die pas ná deze migratie verschijnen, zijn daarna standaard
	 * niet toegestaan totdat een beheerder ze bewust aanvinkt.
	 */
	private function migrate_to_allow_model() {
		$wp_roles      = wp_roles();
		$all_roles     = array_diff( array_keys( $wp_roles->roles ), array( 'administrator' ) );

		$legacy_hidden = array();
		if ( ! empty( $this->options['hidden_pages'] ) && is_array( $this->options['hidden_pages'] ) ) {
			$legacy_hidden = $this->options['hidden_pages'];
		} else {
			$dashboard_options = get_option( 'bmi_dashboard_options', array() );
			if ( ! empty( $dashboard_options['hidden_menu_items'] ) && is_array( $dashboard_options['hidden_menu_items'] ) ) {
				$legacy_hidden = $dashboard_options['hidden_menu_items'];
				unset( $dashboard_options['hidden_menu_items'] );
				update_option( 'bmi_dashboard_options', $dashboard_options );
			}
		}

		$allowed_pages = array();
		foreach ( $this->get_all_current_slugs() as $slug ) {
			$hidden_for            = isset( $legacy_hidden[ $slug ] ) ? $legacy_hidden[ $slug ] : array();
			$allowed_pages[ $slug ] = array_values( array_diff( $all_roles, $hidden_for ) );
		}

		$this->options['allowed_pages']           = $allowed_pages;
		$this->options['migrated_to_allow_model'] = 1;
		unset( $this->options['hidden_pages'] );

		update_option( 'bmi_roles_options', $this->options );
	}

	/**
	 * Alle huidige top-level- en submenu-slugs van deze site (behalve
	 * scheidingslijnen/witruimtes en de altijd-toegestane pagina's).
	 *
	 * @return string[]
	 */
	private function get_all_current_slugs() {
		global $menu, $submenu;
		$slugs = array();

		if ( empty( $menu ) || ! is_array( $menu ) ) {
			return $slugs;
		}

		foreach ( $menu as $item ) {
			if ( empty( $item[0] ) || false !== strpos( $item[2], 'separator' ) || false !== strpos( $item[2], 'bmi-spacer' ) ) {
				continue;
			}

			if ( ! in_array( $item[2], self::ALWAYS_ALLOWED_SLUGS, true ) ) {
				$slugs[] = $item[2];
			}

			if ( ! empty( $submenu[ $item[2] ] ) && is_array( $submenu[ $item[2] ] ) ) {
				foreach ( $submenu[ $item[2] ] as $sub_item ) {
					if ( empty( $sub_item[2] ) || $sub_item[2] === $item[2] ) {
						continue;
					}
					if ( ! in_array( $sub_item[2], self::ALWAYS_ALLOWED_SLUGS, true ) ) {
						$slugs[] = $sub_item[2];
					}
				}
			}
		}

		return array_unique( $slugs );
	}

	/**
	 * Verberg menu- en submenu-items die niet toegestaan zijn voor de
	 * rol(len) van de huidige gebruiker. Beheerders worden nooit beperkt.
	 */
	public function hide_disallowed_pages() {
		$user = wp_get_current_user();

		if ( ! $user || empty( $user->roles ) || in_array( 'administrator', $user->roles, true ) ) {
			return;
		}

		global $submenu;

		foreach ( $this->get_all_current_slugs() as $slug ) {
			$allowed_roles = isset( $this->options['allowed_pages'][ $slug ] ) ? $this->options['allowed_pages'][ $slug ] : array();

			if ( array_intersect( $allowed_roles, $user->roles ) ) {
				continue;
			}

			remove_menu_page( $slug );

			if ( is_array( $submenu ) ) {
				foreach ( $submenu as $parent_slug => $items ) {
					foreach ( $items as $item ) {
						if ( isset( $item[2] ) && $item[2] === $slug ) {
							remove_submenu_page( $parent_slug, $slug );
						}
					}
				}
			}
		}
	}

	/**
	 * Blokkeer (403) het direct bezoeken van een niet-toegestane menupagina
	 * via de URL, zodat verbergen niet alleen cosmetisch is. Een menupagina
	 * die niet in de toegestane-lijst voorkomt (bijv. omdat die pas na de
	 * laatste keer opslaan is verschenen) wordt standaard geweigerd.
	 *
	 * Pagina's die zelf géén menu-item zijn (bijv. post.php, options.php)
	 * worden niet geblokkeerd; die zijn al beschermd door de rechten
	 * (capabilities) van WordPress zelf. Het bewerken van een bericht
	 * (post.php) valt onder het menu-item van het bijbehorende berichttype.
	 */
	public function block_direct_access() {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) || in_array( 'administrator', $user->roles, true ) ) {
			return;
		}

		$current_slug = $this->get_current_admin_page_slug();

		if ( in_array( $current_slug, self::ALWAYS_ALLOWED_SLUGS, true ) ) {
			return;
		}

		// Geen menupagina? Dan laten we WordPress' eigen rechten het werk doen.
		if ( ! in_array( $current_slug, $this->get_all_current_slugs(), true ) ) {
			return;
		}

		$allowed_roles = isset( $this->options['allowed_pages'][ $current_slug ] ) ? $this->options['allowed_pages'][ $current_slug ] : array();

		if ( ! array_intersect( $allowed_roles, $user->roles ) ) {
			wp_die(
				esc_html__( 'Je hebt geen toegang tot deze pagina.', 'brink-multimedia-instellingen' ),
				esc_html__( 'Geen toegang', 'brink-multimedia-instellingen' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Bouw de slug van de huidige admin-pagina op dezelfde manier als
	 * WordPress deze in $menu/$submenu opslaat, zodat we 'm kunnen
	 * vergelijken met de opgeslagen toestemmingen.
	 *
	 * @return string
	 */
	private function get_current_admin_page_slug() {
		global $pagenow;
		$slug = $pagenow;

		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$slug = sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( 'post.php' === $pagenow ) {
			// Een bericht bewerken valt onder het overzicht van zijn berichttype.
			$post_id   = isset( $_GET['post'] ) ? (int) $_GET['post'] : ( isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			$post_type = $post_id ? get_post_type( $post_id ) : '';
			if ( 'post' === $post_type ) {
				$slug = 'edit.php';
			} elseif ( $post_type ) {
				$slug = 'edit.php?post_type=' . $post_type;
			}
		} elseif ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) && isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$slug = $pagenow . '?post_type=' . sanitize_text_field( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) && isset( $_GET['taxonomy'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$slug = $pagenow . '?taxonomy=' . sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return $slug;
	}

	/**
	 * Hulpmiddel voor andere onderdelen van de plugin: mag de huidige
	 * gebruiker een specifieke, door deze plugin aangeboden functie
	 * gebruiken, of is die voor zijn/haar rol uitgesloten?
	 *
	 * @param string $restriction_key bijv. 'restrict_duplicate'.
	 * @return bool True als de functie voor deze gebruiker geblokkeerd is.
	 */
	public static function role_is_restricted( $restriction_key ) {
		$options = get_option( 'bmi_roles_options', array() );

		if ( empty( $options[ $restriction_key ] ) || ! is_array( $options[ $restriction_key ] ) ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		return (bool) array_intersect( $options[ $restriction_key ], $user->roles );
	}
	/**
	 * Rechten die ook buiten wp-admin moeten gelden (bijv. verwijderen via de REST API van de
	 * blokeditor). Wordt altijd geladen, niet alleen in de admin.
	 */
	public static function register_capability_filters() {
		add_filter( 'user_has_cap', array( __CLASS__, 'filter_delete_capabilities' ), 99, 4 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'filter_delete_term_capability' ), 10, 4 );
	}

	/**
	 * Mag deze gebruiker volgens "Verwijderen uitsluiten" niets verwijderen?
	 * Beheerders worden nooit beperkt.
	 *
	 * @param WP_User|null $user Gebruiker.
	 * @return bool
	 */
	public static function delete_is_restricted_for( $user ) {
		static $restricted_roles = null;

		if ( ! $user instanceof WP_User || empty( $user->roles ) || in_array( 'administrator', $user->roles, true ) ) {
			return false;
		}
		if ( null === $restricted_roles ) {
			$options          = get_option( 'bmi_roles_options', array() );
			$restricted_roles = ! empty( $options['restrict_delete'] ) && is_array( $options['restrict_delete'] ) ? $options['restrict_delete'] : array();
		}

		return (bool) array_intersect( $restricted_roles, $user->roles );
	}

	/**
	 * Haalt elk "delete_*"-recht weg (ook van eigen berichttypes, media en gebruikers) voor rollen
	 * die bij "Verwijderen uitsluiten" zijn aangevinkt. De rol kan nog alles toevoegen en aanpassen.
	 * De rollen in de database worden niet gewijzigd.
	 *
	 * @param array   $allcaps Alle rechten van de gebruiker.
	 * @param array   $caps    Gevraagde primitieve rechten.
	 * @param array   $args    Argumenten van de controle.
	 * @param WP_User $user    Gebruiker.
	 * @return array
	 */
	public static function filter_delete_capabilities( $allcaps, $caps, $args, $user ) {
		if ( ! self::delete_is_restricted_for( $user ) ) {
			return $allcaps;
		}
		foreach ( array_keys( $allcaps ) as $capability ) {
			if ( 0 === strpos( $capability, 'delete_' ) ) {
				$allcaps[ $capability ] = false;
			}
		}
		$allcaps['remove_users'] = false;
		return $allcaps;
	}

	/**
	 * Categorieën en tags verwijderen valt in WordPress onder "categorieën beheren" en niet onder een
	 * "delete_*"-recht; die controle daarom apart afvangen.
	 *
	 * @param string[] $caps    Benodigde rechten.
	 * @param string   $cap     Gevraagd (meta)recht.
	 * @param int      $user_id Gebruiker.
	 * @param array    $args    Argumenten.
	 * @return string[]
	 */
	public static function filter_delete_term_capability( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'delete_term', 'delete_categories', 'delete_post_tags' ), true ) ) {
			return $caps;
		}
		return self::delete_is_restricted_for( get_userdata( $user_id ) ) ? array( 'do_not_allow' ) : $caps;
	}
}
