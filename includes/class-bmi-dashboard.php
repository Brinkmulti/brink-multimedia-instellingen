<?php
// Voorkom direct aanroepen van dit bestand.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regelt het tabblad "Dashboard Layout": breedte/lettergrootte van het
 * beheermenu, darkmodus, volgorde + groepering (witruimte), verbergen
 * van menu-items per rol, en het beheren van de dashboardwidgets.
 */
class BMI_Dashboard {

	/**
	 * Opgeslagen instellingen voor dit tabblad.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Aantal "witruimte"-plekken (spacers) in de opgeslagen menu-volgorde.
	 *
	 * @var int
	 */
	private $spacer_count = 0;

	/**
	 * Optie waarin alle ooit gevonden dashboardwidgets van deze site staan
	 * (id => titel), zodat de instellingenpagina ze kan tonen.
	 */
	const DETECTED_WIDGETS_OPTION = 'bmi_detected_dashboard_widgets';

	/**
	 * Vaste id voor het welkomstpaneel van WordPress (geen gewone widget).
	 */
	const WELCOME_PANEL_ID = 'welcome_panel';

	public function __construct() {
		$raw           = get_option( 'bmi_dashboard_options', array() );
		$this->options = wp_parse_args( $raw, $this->get_default_options() );

		// Oude, vaste widget-schakelaars (vóór 1.4.0) omzetten naar "verborgen voor alle rollen".
		if ( ! isset( $raw['hidden_widgets'] ) ) {
			$this->options['hidden_widgets'] = self::migrate_legacy_widget_flags( $raw );
		}

		add_action( 'admin_head', array( $this, 'output_custom_css' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'configure_dashboard_widgets' ), 999 );

		// 'do_meta_boxes' vuurt voor het dashboard pas nádat alle plugins hun widgets hebben
		// toegevoegd (ongeacht de prioriteit die ze gebruiken), dus hier herkennen en
		// verbergen we álle widgets, ook die van andere plugins.
		add_action( 'do_meta_boxes', array( $this, 'handle_dashboard_widgets' ), 999, 1 );

		if ( ! empty( $this->options['menu_order'] ) ) {
			$this->spacer_count = substr_count( $this->options['menu_order'], 'spacer' );

			if ( $this->spacer_count > 0 ) {
				add_action( 'admin_menu', array( $this, 'register_menu_spacers' ), 9999 );
			}

			add_filter( 'custom_menu_order', '__return_true' );
			add_filter( 'menu_order', array( $this, 'apply_custom_menu_order' ) );
		}
	}

	/**
	 * Standaardwaarden voor dit tabblad.
	 *
	 * @return array
	 */
	private function get_default_options() {
		return array(
			'menu_width'                => '',
			'menu_font_size'            => '',
			'dark_mode'                 => 0,
			'menu_order'                => '',
			'menu_spacing'              => '',
			'submenu_expand_down'       => 0,
			'hidden_widgets'            => array(), // widget-id => [rollen waarvoor verborgen].
			'custom_dashboard_message'  => '',
			'custom_dashboard_logo_url' => '',
		);
	}

	/**
	 * Print de custom CSS in de <head> van het WordPress-dashboard,
	 * op basis van de gekozen instellingen.
	 */
	public function output_custom_css() {
		$css         = '';
		$expand_down = ! empty( $this->options['submenu_expand_down'] );

		if ( ! empty( $this->options['menu_width'] ) ) {
			$width = max( 100, intval( $this->options['menu_width'] ) );
			$css  .= "
				#adminmenuwrap, #adminmenu, #adminmenuback { width: {$width}px; }
				#wpcontent, #wpfooter { margin-left: {$width}px; }
				.folded #wpcontent, .folded #wpfooter { margin-left: 36px; }
			";

			if ( ! $expand_down ) {
				$css .= "
					body:not(.folded) #adminmenu li.menu-top:not(.wp-has-current-submenu) > .wp-submenu,
					body:not(.folded) #adminmenu li.menu-top:not(.wp-has-current-submenu) .wp-menu-image + .wp-submenu {
						left: {$width}px !important;
					}

					/* De submenu van het actieve (aangeklikte) onderdeel moet
					   altijd gewoon inline onder het menu-item blijven staan,
					   ook als de muis erover blijft staan, en nooit als los
					   zwevend venster blijven \"plakken\". */
					body:not(.folded) #adminmenu li.menu-top.wp-has-current-submenu > .wp-submenu {
						position: static !important;
						left: auto !important;
						top: auto !important;
						display: block !important;
						float: none !important;
						margin: 0 !important;
					}
				";
			}
		}

		if ( ! empty( $this->options['menu_font_size'] ) ) {
			$font_size = max( 8, intval( $this->options['menu_font_size'] ) );
			$css      .= "#adminmenu, #adminmenu a, #adminmenu div.wp-menu-name { font-size: {$font_size}px; }";
		}

		if ( '' !== $this->options['menu_spacing'] && null !== $this->options['menu_spacing'] ) {
			$spacing = max( 0, intval( $this->options['menu_spacing'] ) );
			$css    .= "
				#adminmenu li.menu-top { margin-bottom: {$spacing}px; }
				#adminmenu li.wp-menu-separator { margin-bottom: 0; }
			";
		}

		if ( $expand_down ) {
			$css .= $this->get_submenu_expand_down_css();
		}

		if ( $this->spacer_count > 0 ) {
			$css .= '
				#adminmenu li.bmi-menu-spacer {
					height: 24px !important;
					margin: 0 !important;
					padding: 0 !important;
					border: none !important;
					background: transparent !important;
				}
			';
		}

		if ( ! empty( $this->options['dark_mode'] ) ) {
			$css .= $this->get_dark_mode_css();
		}

		if ( '' !== trim( $css ) ) {
			echo '<style id="bmi-dashboard-layout-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * CSS om submenu's inline naar beneden te laten uitklappen, alleen
	 * zichtbaar voor het actieve item, zonder animatie.
	 *
	 * @return string
	 */
	private function get_submenu_expand_down_css() {
		return "
			body:not(.folded) #adminmenu li.menu-top { position: relative; }

			body:not(.folded) #adminmenu li.menu-top > .wp-submenu {
				display: none !important;
				position: static !important;
				float: none !important;
				width: auto !important;
				min-width: 0 !important;
				left: auto !important;
				top: auto !important;
				margin: 0 !important;
				padding: 6px 0 6px 0 !important;
				box-shadow: none !important;
				opacity: 1 !important;
				transition: none !important;
				animation: none !important;
			}

			body:not(.folded) #adminmenu li.menu-top.wp-has-current-submenu > .wp-submenu,
			body:not(.folded) #adminmenu li.menu-top.opensub > .wp-submenu {
				display: block !important;
			}

			body:not(.folded) #adminmenu .wp-submenu .wp-submenu-head { display: none !important; }
		";
	}

	/**
	 * CSS voor de darkmodus van het WordPress-dashboard.
	 *
	 * @return string
	 */
	private function get_dark_mode_css() {
		return "
			#wpwrap, #wpcontent, #wpbody, #wpbody-content, #wpfooter { background: #16171a; color: #d4d4d8; }
			#adminmenuback, #adminmenuwrap, #adminmenu, #adminmenu .wp-submenu { background: #1e1f23 !important; }
			#adminmenu a { color: #d4d4d8 !important; }
			#adminmenu li.menu-top:hover, #adminmenu li.opensub > a.menu-top, #adminmenu a.menu-top:focus,
			#adminmenu li.wp-has-current-submenu a.wp-has-current-submenu, #adminmenu li.current a.menu-top {
				background: #2b2c31 !important; color: #fff !important;
			}
			#adminmenu .wp-submenu a:hover, #adminmenu .wp-submenu a:focus { color: #fff !important; }
			#wpadminbar { background: #1e1f23 !important; }
			#wpadminbar .ab-item, #wpadminbar a.ab-item, #wpadminbar > #wp-toolbar span.ab-label,
			#wpadminbar > #wp-toolbar span.noticon { color: #d4d4d8 !important; }
			.wrap h1, .wrap h2, .wrap h3, h1, h2, h3 { color: #f0f0f2 !important; }
			.postbox, .stuffbox, table.wp-list-table, table.widefat, .widefat, .form-table,
			#dashboard-widgets .postbox, .card, .settings_page_bmi-settings .form-table th {
				background: #1e1f23 !important; color: #d4d4d8 !important; border-color: #33343a !important;
			}
			.wp-list-table tbody tr, .wp-list-table tr.alternate { background: #1a1b1e !important; }
			input[type=text], input[type=search], input[type=number], input[type=email],
			input[type=url], input[type=password], select, textarea {
				background: #1e1f23 !important; color: #d4d4d8 !important; border-color: #45464d !important;
			}
			a { color: #7dbcff; }
		";
	}

	/**
	 * Voeg lege "witruimte"-items toe aan het beheermenu.
	 */
	public function register_menu_spacers() {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		for ( $i = 0; $i < $this->spacer_count; $i++ ) {
			$key = 9500 + $i;
			while ( isset( $menu[ $key ] ) ) {
				$key += 0.001;
			}
			$menu[ $key ] = array( '', 'read', 'bmi-spacer-' . $i, '', 'wp-menu-separator bmi-menu-spacer' );
		}

		ksort( $menu );
	}

	/**
	 * Pas de opgeslagen volgorde (inclusief witruimtes) toe op het
	 * top-level beheermenu.
	 *
	 * @param array $menu_order De originele volgorde (array van slugs).
	 * @return array
	 */
	public function apply_custom_menu_order( $menu_order ) {
		$tokens = array_filter(
			array_map( 'trim', explode( ',', $this->options['menu_order'] ) ),
			function ( $token ) {
				return '' !== $token;
			}
		);

		if ( empty( $tokens ) ) {
			return $menu_order;
		}

		$custom       = array();
		$spacer_index = 0;
		foreach ( $tokens as $token ) {
			if ( 'spacer' === $token ) {
				$custom[] = 'bmi-spacer-' . $spacer_index;
				++$spacer_index;
			} else {
				$custom[] = $token;
			}
		}

		$custom    = array_values( array_intersect( $custom, $menu_order ) );
		$remaining = array_values( array_diff( $menu_order, $custom ) );

		return array_merge( $custom, $remaining );
	}

	/**
	 * Zet de oude vaste schakelaars om naar het nieuwe per-rol-formaat
	 * (verborgen voor alle rollen), zodat bestaande sites niets merken.
	 *
	 * @param array $raw Opgeslagen (ruwe) dashboard-opties.
	 * @return array widget-id => [rollen]
	 */
	public static function migrate_legacy_widget_flags( $raw ) {
		$map = array(
			'hide_widget_welcome'     => self::WELCOME_PANEL_ID,
			'hide_widget_at_a_glance' => 'dashboard_right_now',
			'hide_widget_activity'    => 'dashboard_activity',
			'hide_widget_site_health' => 'dashboard_site_health',
			'hide_widget_quick_draft' => 'dashboard_quick_press',
			'hide_widget_news'        => 'dashboard_primary',
		);

		$all_roles = array_keys( wp_roles()->roles );
		$hidden    = array();
		foreach ( $map as $old_key => $widget_id ) {
			if ( ! empty( $raw[ $old_key ] ) ) {
				$hidden[ $widget_id ] = $all_roles;
			}
		}
		return $hidden;
	}

	/**
	 * Is een widget verborgen voor (een van de rollen van) de huidige gebruiker?
	 *
	 * @param string $widget_id Id van de widget.
	 * @return bool
	 */
	private function is_hidden_for_current_user( $widget_id ) {
		$hidden = isset( $this->options['hidden_widgets'][ $widget_id ] ) ? (array) $this->options['hidden_widgets'][ $widget_id ] : array();
		if ( ! $hidden ) {
			return false;
		}
		$user = wp_get_current_user();
		return $user && array_intersect( $hidden, (array) $user->roles );
	}

	/**
	 * Herken alle widgets op het dashboard (ook van andere plugins), bewaar
	 * ze voor de instellingenpagina, en verberg wat voor deze rol uit staat.
	 *
	 * @param string|WP_Screen $screen Scherm waarvoor meta boxes worden getoond.
	 */
	public function handle_dashboard_widgets( $screen ) {
		$screen_id = is_object( $screen ) ? $screen->id : $screen;
		if ( 'dashboard' !== $screen_id ) {
			return;
		}

		// 'do_meta_boxes' vuurt per kolom; we hoeven maar één keer te werken.
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		global $wp_meta_boxes;
		if ( empty( $wp_meta_boxes['dashboard'] ) || ! is_array( $wp_meta_boxes['dashboard'] ) ) {
			return;
		}

		$detected = get_option( self::DETECTED_WIDGETS_OPTION, array() );
		$detected = is_array( $detected ) ? $detected : array();
		$changed  = false;

		foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
			foreach ( (array) $priorities as $boxes ) {
				foreach ( (array) $boxes as $id => $box ) {
					// Leeg gemaakte of eigen widgets overslaan.
					if ( empty( $box ) || ! is_array( $box ) || 'bmi_custom_dashboard_widget' === $id ) {
						continue;
					}

					$title = trim( wp_strip_all_tags( (string) ( isset( $box['title'] ) ? $box['title'] : $id ) ) );
					if ( ! isset( $detected[ $id ] ) || $detected[ $id ] !== $title ) {
						$detected[ $id ] = $title ? $title : $id;
						$changed         = true;
					}

					if ( $this->is_hidden_for_current_user( $id ) ) {
						remove_meta_box( $id, 'dashboard', $context );
					}
				}
			}
		}

		if ( $changed ) {
			update_option( self::DETECTED_WIDGETS_OPTION, $detected, false );
		}
	}

	/**
	 * Alle bekende widgets voor de instellingenpagina, met de standaard
	 * WordPress-widgets altijd erbij (ook als het dashboard nog nooit geopend is).
	 *
	 * @return array id => titel
	 */
	public static function get_known_widgets() {
		$core = array(
			self::WELCOME_PANEL_ID    => __( 'Welkom bij WordPress', 'brink-multimedia-instellingen' ),
			'dashboard_right_now'     => __( 'Op een oogopslag', 'brink-multimedia-instellingen' ),
			'dashboard_activity'      => __( 'Activiteit', 'brink-multimedia-instellingen' ),
			'dashboard_site_health'   => __( 'Status van de site', 'brink-multimedia-instellingen' ),
			'dashboard_quick_press'   => __( 'Snel een concept', 'brink-multimedia-instellingen' ),
			'dashboard_primary'       => __( 'WordPress-evenementen en -nieuws', 'brink-multimedia-instellingen' ),
		);

		$detected = get_option( self::DETECTED_WIDGETS_OPTION, array() );
		$others   = array_diff_key( is_array( $detected ) ? $detected : array(), $core );
		natcasesort( $others );

		return $core + $others;
	}

	/**
	 * Verberg het welkomstpaneel (per rol) en toon eventueel een eigen
	 * welkomstwidget met logo/tekst. Gewone widgets worden verborgen in
	 * handle_dashboard_widgets().
	 */
	public function configure_dashboard_widgets() {
		if ( $this->is_hidden_for_current_user( self::WELCOME_PANEL_ID ) ) {
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
		}

		if ( ! empty( $this->options['custom_dashboard_message'] ) || ! empty( $this->options['custom_dashboard_logo_url'] ) ) {
			wp_add_dashboard_widget(
				'bmi_custom_dashboard_widget',
				__( 'Welkom', 'brink-multimedia-instellingen' ),
				array( $this, 'render_custom_dashboard_widget' )
			);

			// Zet de eigen widget bovenaan.
			global $wp_meta_boxes;
			if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['bmi_custom_dashboard_widget'] ) ) {
				$widget = $wp_meta_boxes['dashboard']['normal']['core']['bmi_custom_dashboard_widget'];
				unset( $wp_meta_boxes['dashboard']['normal']['core']['bmi_custom_dashboard_widget'] );
				$wp_meta_boxes['dashboard']['normal']['core'] = array( 'bmi_custom_dashboard_widget' => $widget ) + $wp_meta_boxes['dashboard']['normal']['core'];
			}
		}
	}

	/**
	 * Render de eigen welkomstwidget (logo + tekst) op het dashboard.
	 */
	public function render_custom_dashboard_widget() {
		if ( ! empty( $this->options['custom_dashboard_logo_url'] ) ) {
			printf(
				'<p><img src="%s" alt="" style="max-width:100%%;height:auto;" /></p>',
				esc_url( $this->options['custom_dashboard_logo_url'] )
			);
		}

		if ( ! empty( $this->options['custom_dashboard_message'] ) ) {
			echo wp_kses_post( wpautop( $this->options['custom_dashboard_message'] ) );
		}
	}
}
