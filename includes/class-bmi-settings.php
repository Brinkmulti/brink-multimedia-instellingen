<?php
// Voorkom direct aanroepen van dit bestand.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bouwt de instellingenpagina van de plugin op, met tabbladen per
 * categorie: Log in/uit | Registreer, Dashboard Layout, Inhoudsbeheer, E-mail.
 */
class BMI_Settings {

	/**
	 * Slug van de instellingenpagina.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'bmi-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Voeg de plugin toe als eigen menu-item in het WordPress-menu.
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Brink Multimedia Instellingen', 'brink-multimedia-instellingen' ),
			__( 'Brink Instellingen', 'brink-multimedia-instellingen' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-admin-generic',
			80
		);
	}

	/**
	 * Registreer de instellingen-groepen (1 per tabblad) met hun
	 * sanitize-functies.
	 */
	public function register_settings() {
		register_setting(
			'bmi_login_group',
			'bmi_login_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_login_options' ),
				'default'           => $this->get_login_defaults(),
			)
		);

		register_setting(
			'bmi_dashboard_group',
			'bmi_dashboard_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_dashboard_options' ),
				'default'           => $this->get_dashboard_defaults(),
			)
		);

		register_setting(
			'bmi_content_group',
			'bmi_content_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_content_options' ),
				'default'           => array(
					'enable_duplicate'  => 0,
					'allow_svg'         => 0,
					'allow_avif'        => 0,
					'show_reading_time' => 0,
				),
			)
		);

		register_setting(
			'bmi_email_group',
			'bmi_email_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_email_options' ),
				'default'           => array(
					'sender_name'  => '',
					'sender_email' => '',
				),
			)
		);

		register_setting(
			'bmi_roles_group',
			'bmi_roles_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_roles_options' ),
				'default'           => $this->get_roles_defaults(),
			)
		);
	}

	/**
	 * @return array
	 */
	private function get_login_defaults() {
		return array(
			'login_slug'               => '',
			'enable_lockout'           => 0,
			'max_attempts'             => 5,
			'lockout_minutes'          => 15,
			'logo_url'                 => '',
			'logo_width'               => 84,
			'background_color'         => '',
			'accent_color'             => '',
			'background_image_url'     => '',
			'disable_author_archives'  => 0,
		);
	}

	/**
	 * @return array
	 */
	private function get_dashboard_defaults() {
		return array(
			'menu_width'                => '',
			'menu_font_size'            => '',
			'dark_mode'                 => 0,
			'menu_order'                => '',
			'menu_spacing'              => '',
			'submenu_expand_down'       => 0,
			'hidden_widgets'            => array(),
			'custom_dashboard_message'  => '',
			'custom_dashboard_logo_url' => '',
		);
	}

	/**
	 * @return array
	 */
	private function get_roles_defaults() {
		return BMI_Roles::get_defaults();
	}

	/**
	 * Laad CSS/JS alleen op de eigen instellingenpagina.
	 *
	 * @param string $hook De huidige admin-pagina hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'bmi-admin-css',
			BMI_PLUGIN_URL . 'assets/admin.css',
			array(),
			BMI_VERSION
		);

		wp_enqueue_script(
			'bmi-admin-js',
			BMI_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
			BMI_VERSION,
			true
		);

		wp_localize_script(
			'bmi-admin-js',
			'bmiSettings',
			array(
				'spacerLabel'      => __( 'Witruimte (groepering)', 'brink-multimedia-instellingen' ),
				'removeLabel'      => __( 'Verwijder witruimte', 'brink-multimedia-instellingen' ),
				'mediaTitle'       => __( 'Kies een afbeelding', 'brink-multimedia-instellingen' ),
				'mediaButtonLabel' => __( 'Gebruik deze afbeelding', 'brink-multimedia-instellingen' ),
			)
		);
	}

	// ------------------------------------------------------------
	// Sanitize-functies
	// ------------------------------------------------------------

	/**
	 * @param array $input Ruwe invoer van tabblad 1.
	 * @return array
	 */
	public function sanitize_login_options( $input ) {
		$output = $this->get_login_defaults();

		$slug                 = isset( $input['login_slug'] ) ? sanitize_title( wp_unslash( $input['login_slug'] ) ) : '';
		$output['login_slug'] = $slug;

		if ( ! empty( $slug ) && in_array( $slug, array( 'wp-login', 'wp-login.php', 'wp-admin' ), true ) ) {
			add_settings_error(
				'bmi_login_options',
				'bmi_login_slug_invalid',
				__( 'Kies een andere login-URL; deze waarde is niet toegestaan.', 'brink-multimedia-instellingen' )
			);
			$output['login_slug'] = '';
		}

		$output['enable_lockout']          = ! empty( $input['enable_lockout'] ) ? 1 : 0;
		$output['max_attempts']            = isset( $input['max_attempts'] ) ? max( 1, absint( $input['max_attempts'] ) ) : 5;
		$output['lockout_minutes']         = isset( $input['lockout_minutes'] ) ? max( 1, absint( $input['lockout_minutes'] ) ) : 15;

		$output['logo_url']                = isset( $input['logo_url'] ) ? esc_url_raw( wp_unslash( $input['logo_url'] ) ) : '';
		$output['logo_width']              = isset( $input['logo_width'] ) ? absint( $input['logo_width'] ) : 84;
		$output['background_color']        = isset( $input['background_color'] ) ? sanitize_hex_color( wp_unslash( $input['background_color'] ) ) : '';
		$output['accent_color']            = isset( $input['accent_color'] ) ? sanitize_hex_color( wp_unslash( $input['accent_color'] ) ) : '';
		$output['background_image_url']    = isset( $input['background_image_url'] ) ? esc_url_raw( wp_unslash( $input['background_image_url'] ) ) : '';

		$output['disable_author_archives'] = ! empty( $input['disable_author_archives'] ) ? 1 : 0;

		return $output;
	}

	/**
	 * @param array $input Ruwe invoer van tabblad 2.
	 * @return array
	 */
	public function sanitize_dashboard_options( $input ) {
		$output = array(
			'menu_width'          => isset( $input['menu_width'] ) ? absint( $input['menu_width'] ) : '',
			'menu_font_size'      => isset( $input['menu_font_size'] ) ? absint( $input['menu_font_size'] ) : '',
			'dark_mode'           => ! empty( $input['dark_mode'] ) ? 1 : 0,
			'menu_order'          => isset( $input['menu_order'] ) ? sanitize_text_field( wp_unslash( $input['menu_order'] ) ) : '',
			'menu_spacing'        => isset( $input['menu_spacing'] ) ? absint( $input['menu_spacing'] ) : '',
			'submenu_expand_down' => ! empty( $input['submenu_expand_down'] ) ? 1 : 0,
		);

		// Dashboardwidgets: widget-id => [rollen waarvoor de widget verborgen is].
		$output['hidden_widgets'] = array();
		if ( ! empty( $input['hidden_widgets'] ) && is_array( $input['hidden_widgets'] ) ) {
			foreach ( $input['hidden_widgets'] as $widget_id => $roles ) {
				$widget_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $widget_id );
				if ( '' !== $widget_id && is_array( $roles ) ) {
					$output['hidden_widgets'][ $widget_id ] = array_values( array_map( 'sanitize_key', $roles ) );
				}
			}
		}

		$output['custom_dashboard_message']  = isset( $input['custom_dashboard_message'] ) ? wp_kses_post( wp_unslash( $input['custom_dashboard_message'] ) ) : '';
		$output['custom_dashboard_logo_url'] = isset( $input['custom_dashboard_logo_url'] ) ? esc_url_raw( wp_unslash( $input['custom_dashboard_logo_url'] ) ) : '';

		return $output;
	}

	/**
	 * @param array $input Ruwe invoer van tabblad 3.
	 * @return array
	 */
	public function sanitize_content_options( $input ) {
		return array(
			'enable_duplicate'  => ! empty( $input['enable_duplicate'] ) ? 1 : 0,
			'allow_svg'         => ! empty( $input['allow_svg'] ) ? 1 : 0,
			'allow_avif'        => ! empty( $input['allow_avif'] ) ? 1 : 0,
			'show_reading_time' => ! empty( $input['show_reading_time'] ) ? 1 : 0,
		);
	}

	/**
	 * @param array $input Ruwe invoer van tabblad 4.
	 * @return array
	 */
	public function sanitize_email_options( $input ) {
		return array(
			'sender_name'  => isset( $input['sender_name'] ) ? sanitize_text_field( wp_unslash( $input['sender_name'] ) ) : '',
			'sender_email' => isset( $input['sender_email'] ) ? sanitize_email( wp_unslash( $input['sender_email'] ) ) : '',
		);
	}

	/**
	 * @param array $input Ruwe invoer van tabblad "Rollen & Rechten".
	 * @return array
	 */
	public function sanitize_roles_options( $input ) {
		$output = BMI_Roles::get_defaults();

		if ( ! empty( $input['allowed_pages'] ) && is_array( $input['allowed_pages'] ) ) {
			foreach ( $input['allowed_pages'] as $slug => $roles ) {
				$slug = sanitize_text_field( wp_unslash( $slug ) );
				if ( is_array( $roles ) && ! empty( $roles ) ) {
					$output['allowed_pages'][ $slug ] = array_map( 'sanitize_key', $roles );
				}
			}
		}

		foreach ( array( 'restrict_duplicate', 'restrict_svg_upload', 'restrict_avif_upload', 'restrict_delete' ) as $key ) {
			if ( ! empty( $input[ $key ] ) && is_array( $input[ $key ] ) ) {
				$output[ $key ] = array_map( 'sanitize_key', $input[ $key ] );
			}
		}

		// Deze sanitize-functie hoort alleen bij het (nieuwe) "mag zien"-scherm,
		// dus zodra die ooit is opgeslagen staat de migratie definitief vast —
		// anders zou een volgende keer opslaan de migratie opnieuw laten
		// draaien en alle configuratie resetten naar "iedereen mag alles".
		$output['migrated_to_allow_model'] = 1;

		return $output;
	}

	// ------------------------------------------------------------
	// Weergave
	// ------------------------------------------------------------

	/**
	 * Render de volledige instellingenpagina met tabbladen.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'login'     => __( 'Log in/uit | Registreer', 'brink-multimedia-instellingen' ),
			'dashboard' => __( 'Dashboard Layout', 'brink-multimedia-instellingen' ),
			'content'   => __( 'Inhoudsbeheer', 'brink-multimedia-instellingen' ),
			'roles'     => __( 'Rollen & Rechten', 'brink-multimedia-instellingen' ),
			'email'     => __( 'E-mail', 'brink-multimedia-instellingen' ),
		);

		$active_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 'login';
		?>
		<div class="wrap bmi-settings-wrap">
			<h1><?php esc_html_e( 'Brink Multimedia Instellingen', 'brink-multimedia-instellingen' ); ?> <span class="bmi-version">v<?php echo esc_html( BMI_VERSION ); ?></span></h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $tab_slug ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div class="bmi-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'dashboard':
						$this->render_dashboard_tab();
						break;
					case 'content':
						$this->render_content_tab();
						break;
					case 'roles':
						$this->render_roles_tab();
						break;
					case 'email':
						$this->render_email_tab();
						break;
					case 'login':
					default:
						$this->render_login_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tabblad 1: Log in/uit | Registreer.
	 */
	private function render_login_tab() {
		$options = wp_parse_args( get_option( 'bmi_login_options', array() ), $this->get_login_defaults() );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bmi_login_group' ); ?>
			<?php settings_errors( 'bmi_login_options' ); ?>

			<h2><?php esc_html_e( 'Eigen login-URL', 'brink-multimedia-instellingen' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="bmi_login_slug"><?php esc_html_e( 'Eigen login-URL', 'brink-multimedia-instellingen' ); ?></label>
					</th>
					<td>
						<code><?php echo esc_html( home_url( '/' ) ); ?></code>
						<input type="text" id="bmi_login_slug" name="bmi_login_options[login_slug]"
							value="<?php echo esc_attr( $options['login_slug'] ); ?>"
							class="regular-text" placeholder="mijn-geheime-login" />
						<p class="description">
							<?php esc_html_e( 'Laat leeg om de standaard /wp-login.php te blijven gebruiken. Bewaar de nieuwe URL goed, anders kun je zelf niet meer inloggen!', 'brink-multimedia-instellingen' ); ?>
						</p>
						<?php if ( ! empty( $options['login_slug'] ) ) : ?>
							<p class="description bmi-highlight">
								<?php
								printf(
									/* translators: %s: the custom login URL. */
									esc_html__( 'Actieve login-URL: %s', 'brink-multimedia-instellingen' ),
									'<strong>' . esc_html( home_url( '/' . $options['login_slug'] ) ) . '</strong>'
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Brute-force bescherming', 'brink-multimedia-instellingen' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Inlogpogingen beperken', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bmi_login_options[enable_lockout]" value="1" <?php checked( 1, $options['enable_lockout'] ); ?> />
							<?php esc_html_e( 'Blokkeer een IP-adres tijdelijk na te veel mislukte inlogpogingen.', 'brink-multimedia-instellingen' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bmi_max_attempts"><?php esc_html_e( 'Max. mislukte pogingen', 'brink-multimedia-instellingen' ); ?></label></th>
					<td>
						<input type="number" min="1" max="20" id="bmi_max_attempts" name="bmi_login_options[max_attempts]"
							value="<?php echo esc_attr( $options['max_attempts'] ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bmi_lockout_minutes"><?php esc_html_e( 'Blokkeerduur (minuten)', 'brink-multimedia-instellingen' ); ?></label></th>
					<td>
						<input type="number" min="1" max="1440" id="bmi_lockout_minutes" name="bmi_login_options[lockout_minutes]"
							value="<?php echo esc_attr( $options['lockout_minutes'] ); ?>" class="small-text" />
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Uiterlijk inlogscherm', 'brink-multimedia-instellingen' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Eigen logo', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<?php $this->render_media_field( 'bmi_login_options[logo_url]', $options['logo_url'], 'bmi-logo-url' ); ?>
						<p class="description"><?php esc_html_e( 'Vervangt het WordPress-logo boven het inlogformulier.', 'brink-multimedia-instellingen' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bmi_logo_width"><?php esc_html_e( 'Logobreedte/-hoogte (px)', 'brink-multimedia-instellingen' ); ?></label></th>
					<td>
						<input type="number" min="20" max="400" id="bmi_logo_width" name="bmi_login_options[logo_width]"
							value="<?php echo esc_attr( $options['logo_width'] ); ?>" class="small-text" /> px
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Achtergrondkleur', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<input type="text" class="bmi-color-picker" name="bmi_login_options[background_color]" value="<?php echo esc_attr( $options['background_color'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Accentkleur (knop/links)', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<input type="text" class="bmi-color-picker" name="bmi_login_options[accent_color]" value="<?php echo esc_attr( $options['accent_color'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Achtergrondafbeelding', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<?php $this->render_media_field( 'bmi_login_options[background_image_url]', $options['background_image_url'], 'bmi-bg-image-url' ); ?>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Extra beveiliging', 'brink-multimedia-instellingen' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Auteur-archieven', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bmi_login_options[disable_author_archives]" value="1" <?php checked( 1, $options['disable_author_archives'] ); ?> />
							<?php esc_html_e( 'Schakel auteur-archiefpagina\'s uit, zodat gebruikersnamen niet via /?author=1 te achterhalen zijn.', 'brink-multimedia-instellingen' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Wijzigingen opslaan', 'brink-multimedia-instellingen' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render een tekstveld met een knop om via de mediabibliotheek een
	 * afbeelding te kiezen, inclusief kleine voorvertoning.
	 *
	 * @param string $field_name Naam-attribuut van het verborgen tekstveld.
	 * @param string $value      Huidige URL.
	 * @param string $id_prefix  Unieke prefix voor de HTML-ID's.
	 */
	private function render_media_field( $field_name, $value, $id_prefix ) {
		?>
		<div class="bmi-media-field">
			<img src="<?php echo esc_url( $value ); ?>" id="<?php echo esc_attr( $id_prefix ); ?>-preview" class="bmi-media-preview" style="<?php echo $value ? '' : 'display:none;'; ?>" alt="" />
			<input type="text" id="<?php echo esc_attr( $id_prefix ); ?>-input" class="regular-text bmi-media-url" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<button type="button" class="button bmi-media-select" data-target="<?php echo esc_attr( $id_prefix ); ?>"><?php esc_html_e( 'Kies afbeelding', 'brink-multimedia-instellingen' ); ?></button>
			<button type="button" class="button bmi-media-remove" data-target="<?php echo esc_attr( $id_prefix ); ?>"><?php esc_html_e( 'Verwijder', 'brink-multimedia-instellingen' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Tabblad 2: Dashboard Layout.
	 */
	private function render_dashboard_tab() {
		$options = wp_parse_args( get_option( 'bmi_dashboard_options', array() ), $this->get_dashboard_defaults() );

		$ordered_list = $this->get_ordered_menu_list_for_display( $options['menu_order'] );
		?>
		<h2><?php esc_html_e( 'Uiterlijk beheermenu', 'brink-multimedia-instellingen' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'bmi_dashboard_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="bmi_menu_width"><?php esc_html_e( 'Breedte beheermenu (px)', 'brink-multimedia-instellingen' ); ?></label>
					</th>
					<td>
						<input type="number" min="100" max="600" id="bmi_menu_width" name="bmi_dashboard_options[menu_width]"
							value="<?php echo esc_attr( $options['menu_width'] ); ?>" class="small-text" /> px
						<p class="description"><?php esc_html_e( 'Standaardbreedte van WordPress is 160px. Laat leeg voor de standaardbreedte.', 'brink-multimedia-instellingen' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bmi_menu_font_size"><?php esc_html_e( 'Lettergrootte beheermenu (px)', 'brink-multimedia-instellingen' ); ?></label>
					</th>
					<td>
						<input type="number" min="8" max="24" id="bmi_menu_font_size" name="bmi_dashboard_options[menu_font_size]"
							value="<?php echo esc_attr( $options['menu_font_size'] ); ?>" class="small-text" /> px
						<p class="description"><?php esc_html_e( 'Laat leeg voor de standaardgrootte.', 'brink-multimedia-instellingen' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bmi_menu_spacing"><?php esc_html_e( 'Ruimte tussen menu-items (px)', 'brink-multimedia-instellingen' ); ?></label>
					</th>
					<td>
						<input type="number" min="0" max="40" id="bmi_menu_spacing" name="bmi_dashboard_options[menu_spacing]"
							value="<?php echo esc_attr( $options['menu_spacing'] ); ?>" class="small-text" /> px
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Darkmodus', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bmi_dashboard_options[dark_mode]" value="1" <?php checked( 1, $options['dark_mode'] ); ?> />
							<?php esc_html_e( 'Schakel een donker kleurenschema in voor het WordPress-dashboard.', 'brink-multimedia-instellingen' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Submenu\'s uitklappen', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bmi_dashboard_options[submenu_expand_down]" value="1" <?php checked( 1, $options['submenu_expand_down'] ); ?> />
							<?php esc_html_e( 'Klap submenu\'s naar beneden uit (en duw onderliggende menu-items omlaag) in plaats van als los venster naar rechts.', 'brink-multimedia-instellingen' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Het submenu verschijnt alleen voor het menu-item waar je op hebt geklikt, niet bij het overheen bewegen van de muis, en zonder animatie.', 'brink-multimedia-instellingen' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Volgorde beheermenu', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<p class="description"><?php esc_html_e( 'Sleep de items naar de gewenste volgorde. Voeg witruimte toe om items in groepen te clusteren.', 'brink-multimedia-instellingen' ); ?></p>
						<ul id="bmi-menu-order-list" class="bmi-sortable-list">
							<?php foreach ( $ordered_list as $entry ) : ?>
								<?php if ( 'spacer' === $entry['type'] ) : ?>
									<li class="bmi-spacer-item" data-slug="spacer">
										<span class="dashicons dashicons-minus"></span> <?php esc_html_e( 'Witruimte (groepering)', 'brink-multimedia-instellingen' ); ?>
										<button type="button" class="bmi-remove-spacer button-link" aria-label="<?php esc_attr_e( 'Verwijder witruimte', 'brink-multimedia-instellingen' ); ?>">&times;</button>
									</li>
								<?php else : ?>
									<li data-slug="<?php echo esc_attr( $entry['slug'] ); ?>">
										<span class="dashicons dashicons-menu"></span> <?php echo esc_html( $entry['label'] ); ?>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
						<p>
							<button type="button" id="bmi-add-spacer" class="button">
								<?php esc_html_e( '+ Witruimte toevoegen', 'brink-multimedia-instellingen' ); ?>
							</button>
						</p>
						<input type="hidden" id="bmi_menu_order_input" name="bmi_dashboard_options[menu_order]"
							value="<?php echo esc_attr( $options['menu_order'] ); ?>" />
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Dashboardwidgets', 'brink-multimedia-instellingen' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Widgets verbergen per rol', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<?php $this->render_widget_matrix( $options ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Eigen welkomstwidget', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<?php $this->render_media_field( 'bmi_dashboard_options[custom_dashboard_logo_url]', $options['custom_dashboard_logo_url'], 'bmi-dashboard-logo-url' ); ?>
						<p>
							<textarea name="bmi_dashboard_options[custom_dashboard_message]" rows="4" class="large-text"><?php echo esc_textarea( $options['custom_dashboard_message'] ); ?></textarea>
						</p>
						<p class="description"><?php esc_html_e( 'Toont een eigen widget bovenaan het dashboard, met logo en/of welkomsttekst.', 'brink-multimedia-instellingen' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Wijzigingen opslaan', 'brink-multimedia-instellingen' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Haal de huidige top-level items van het beheermenu op (zonder separators).
	 *
	 * @return array[] Lijst met ['slug' => ..., 'label' => ...].
	 */
	private function get_admin_menu_items() {
		global $menu;
		$items = array();

		if ( empty( $menu ) || ! is_array( $menu ) ) {
			return $items;
		}

		foreach ( $menu as $item ) {
			if ( empty( $item[0] ) || false !== strpos( $item[2], 'separator' ) || false !== strpos( $item[2], 'bmi-spacer' ) ) {
				continue;
			}
			$items[] = array(
				'slug'  => $item[2],
				'label' => wp_strip_all_tags( $item[0] ),
			);
		}

		return $items;
	}

	/**
	 * Bouw de lijst op die in het sleepbare volgordescherm getoond wordt.
	 *
	 * @param string $stored_order_string De opgeslagen, kommagescheiden volgorde.
	 * @return array[]
	 */
	private function get_ordered_menu_list_for_display( $stored_order_string ) {
		$menu_items   = $this->get_admin_menu_items();
		$menu_by_slug = array();
		foreach ( $menu_items as $item ) {
			$menu_by_slug[ $item['slug'] ] = $item['label'];
		}

		$tokens = array_filter(
			array_map( 'trim', explode( ',', (string) $stored_order_string ) ),
			function ( $token ) {
				return '' !== $token;
			}
		);

		$ordered_list = array();
		$used_slugs   = array();

		foreach ( $tokens as $token ) {
			if ( 'spacer' === $token ) {
				$ordered_list[] = array( 'type' => 'spacer' );
			} elseif ( isset( $menu_by_slug[ $token ] ) && ! in_array( $token, $used_slugs, true ) ) {
				$ordered_list[] = array(
					'type'  => 'item',
					'slug'  => $token,
					'label' => $menu_by_slug[ $token ],
				);
				$used_slugs[]   = $token;
			}
		}

		foreach ( $menu_items as $item ) {
			if ( ! in_array( $item['slug'], $used_slugs, true ) ) {
				$ordered_list[] = array(
					'type'  => 'item',
					'slug'  => $item['slug'],
					'label' => $item['label'],
				);
			}
		}

		return $ordered_list;
	}

	/**
	 * Bouw de volledige menuboom op (top-level items + hun submenu's,
	 * inclusief zaken als "Nieuw toevoegen" en "Widgets") voor de
	 * matrix op het tabblad "Rollen & Rechten".
	 *
	 * @return array[]
	 */
	private function get_full_menu_tree() {
		global $menu, $submenu;
		$tree = array();

		if ( empty( $menu ) || ! is_array( $menu ) ) {
			return $tree;
		}

		foreach ( $menu as $item ) {
			if ( empty( $item[0] ) || false !== strpos( $item[2], 'separator' ) || false !== strpos( $item[2], 'bmi-spacer' ) || in_array( $item[2], BMI_Roles::ALWAYS_ALLOWED_SLUGS, true ) ) {
				continue;
			}

			$parent_slug = $item[2];
			$entry       = array(
				'slug'     => $parent_slug,
				'label'    => wp_strip_all_tags( $item[0] ),
				'children' => array(),
			);

			if ( ! empty( $submenu[ $parent_slug ] ) && is_array( $submenu[ $parent_slug ] ) ) {
				foreach ( $submenu[ $parent_slug ] as $sub_item ) {
					if ( empty( $sub_item[2] ) || $sub_item[2] === $parent_slug || in_array( $sub_item[2], BMI_Roles::ALWAYS_ALLOWED_SLUGS, true ) ) {
						continue; // Sla de dubbele "overzicht"-vermelding en altijd-toegestane pagina's over.
					}

					$entry['children'][] = array(
						'slug'  => $sub_item[2],
						'label' => wp_strip_all_tags( $sub_item[0] ),
					);
				}
			}

			$tree[] = $entry;
		}

		return $tree;
	}

	/**
	 * Tabblad "Rollen & Rechten".
	 */
	/**
	 * Tabel met alle bekende dashboardwidgets (ook van andere plugins) x alle rollen.
	 * Aangevinkt = verborgen voor die rol.
	 *
	 * @param array $options Opties van het tabblad Dashboard Layout.
	 */
	private function render_widget_matrix( $options ) {
		$raw    = get_option( 'bmi_dashboard_options', array() );
		$hidden = isset( $raw['hidden_widgets'] ) ? (array) $raw['hidden_widgets'] : BMI_Dashboard::migrate_legacy_widget_flags( $raw );

		$widgets = BMI_Dashboard::get_known_widgets();
		$roles   = wp_roles()->roles;
		?>
		<p class="description" style="margin-bottom:10px">
			<?php esc_html_e( 'Vink per rol aan welke widgets verborgen moeten worden. Widgets van andere plugins (zoals Elementor of een SMTP-plugin) verschijnen hier automatisch zodra het dashboard één keer is geopend door een gebruiker die ze te zien krijgt; open dus eerst even het Dashboard als er een widget ontbreekt.', 'brink-multimedia-instellingen' ); ?>
		</p>
		<table class="widefat bmi-role-matrix">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Widget', 'brink-multimedia-instellingen' ); ?></th>
					<?php foreach ( $roles as $role_slug => $role ) : ?>
						<th>
							<?php echo esc_html( translate_user_role( $role['name'] ) ); ?><br />
							<button type="button" class="button-link bmi-role-select-all" data-role="<?php echo esc_attr( $role_slug ); ?>"><?php esc_html_e( 'Alles', 'brink-multimedia-instellingen' ); ?></button>
							|
							<button type="button" class="button-link bmi-role-select-none" data-role="<?php echo esc_attr( $role_slug ); ?>"><?php esc_html_e( 'Niets', 'brink-multimedia-instellingen' ); ?></button>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $widgets as $widget_id => $title ) : ?>
					<tr>
						<td>
							<?php echo esc_html( $title ); ?>
							<br /><code style="font-size:11px;opacity:.7"><?php echo esc_html( $widget_id ); ?></code>
						</td>
						<?php foreach ( $roles as $role_slug => $role ) : ?>
							<td>
								<input type="checkbox" class="bmi-role-checkbox" data-role="<?php echo esc_attr( $role_slug ); ?>"
									name="bmi_dashboard_options[hidden_widgets][<?php echo esc_attr( $widget_id ); ?>][]"
									value="<?php echo esc_attr( $role_slug ); ?>"
									aria-label="<?php echo esc_attr( sprintf( /* translators: 1: widget, 2: rol */ __( '%1$s verbergen voor %2$s', 'brink-multimedia-instellingen' ), $title, translate_user_role( $role['name'] ) ) ); ?>"
									<?php checked( in_array( $role_slug, isset( $hidden[ $widget_id ] ) ? (array) $hidden[ $widget_id ] : array(), true ) ); ?> />
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_roles_tab() {
		$options = wp_parse_args( get_option( 'bmi_roles_options', array() ), BMI_Roles::get_defaults() );

		$tree     = $this->get_full_menu_tree();
		$wp_roles = wp_roles();
		$roles    = $wp_roles->roles;
		unset( $roles['administrator'] );
		?>
		<form method="post" action="options.php" id="bmi-roles-form">
			<?php settings_fields( 'bmi_roles_group' ); ?>

			<h2><?php esc_html_e( 'Menu-onderdelen per rol', 'brink-multimedia-instellingen' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Vink per rol aan welke menu-onderdelen (inclusief submenu\'s, zoals "Nieuw toevoegen" en "Widgets") die rol mag zien en gebruiken. Alles wat je niet aanvinkt, is verborgen én geblokkeerd bij direct bezoeken van de URL. Nieuwe onderdelen die later verschijnen (bijv. door een andere plugin) staan om veiligheidsredenen standaard uit, totdat je ze hier bewust toestaat. De rol "Beheerder" en je eigen profiel/dashboard zijn hier bewust niet in te stellen, om te voorkomen dat je jezelf buitensluit.', 'brink-multimedia-instellingen' ); ?>
			</p>

			<?php if ( empty( $tree ) || empty( $roles ) ) : ?>
				<p><em><?php esc_html_e( 'Geen menu-onderdelen of rollen gevonden op deze site.', 'brink-multimedia-instellingen' ); ?></em></p>
			<?php else : ?>
				<table class="widefat bmi-role-matrix">
					<thead>
						<tr>
							<th></th>
							<?php foreach ( $roles as $role_slug => $role ) : ?>
								<th>
									<?php echo esc_html( translate_user_role( $role['name'] ) ); ?><br />
									<button type="button" class="button-link bmi-role-select-all" data-role="<?php echo esc_attr( $role_slug ); ?>"><?php esc_html_e( 'Alles', 'brink-multimedia-instellingen' ); ?></button>
									|
									<button type="button" class="button-link bmi-role-select-none" data-role="<?php echo esc_attr( $role_slug ); ?>"><?php esc_html_e( 'Niets', 'brink-multimedia-instellingen' ); ?></button>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tree as $item ) : ?>
							<tr class="bmi-parent-row">
								<td><strong><?php echo esc_html( $item['label'] ); ?></strong></td>
								<?php foreach ( $roles as $role_slug => $role ) : ?>
									<td>
										<input type="checkbox" class="bmi-role-checkbox bmi-parent-checkbox" data-role="<?php echo esc_attr( $role_slug ); ?>"
											name="bmi_roles_options[allowed_pages][<?php echo esc_attr( $item['slug'] ); ?>][]"
											value="<?php echo esc_attr( $role_slug ); ?>"
											<?php checked( in_array( $role_slug, isset( $options['allowed_pages'][ $item['slug'] ] ) ? $options['allowed_pages'][ $item['slug'] ] : array(), true ) ); ?> />
									</td>
								<?php endforeach; ?>
							</tr>
							<?php foreach ( $item['children'] as $child ) : ?>
								<tr class="bmi-child-row">
									<td>&nbsp;&nbsp;&nbsp;&#8627; <?php echo esc_html( $child['label'] ); ?></td>
									<?php foreach ( $roles as $role_slug => $role ) : ?>
										<td>
											<input type="checkbox" class="bmi-role-checkbox bmi-child-checkbox" data-role="<?php echo esc_attr( $role_slug ); ?>"
												name="bmi_roles_options[allowed_pages][<?php echo esc_attr( $child['slug'] ); ?>][]"
												value="<?php echo esc_attr( $role_slug ); ?>"
												<?php checked( in_array( $role_slug, isset( $options['allowed_pages'][ $child['slug'] ] ) ? $options['allowed_pages'][ $child['slug'] ] : array(), true ) ); ?> />
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Functies van deze plugin per rol', 'brink-multimedia-instellingen' ); ?></h2>
			<?php if ( ! empty( $roles ) ) : ?>
				<p class="description"><?php esc_html_e( 'Dit onderdeel werkt nog met een uitsluitingslijst: vink aan voor welke rol een functie uitgezet moet worden (in plaats van "mag gebruiken"), omdat deze drie functies al standaard uitstaan totdat je ze op het tabblad "Inhoudsbeheer" inschakelt.', 'brink-multimedia-instellingen' ); ?></p>
				<table class="widefat bmi-role-matrix">
					<thead>
						<tr>
							<th></th>
							<?php foreach ( $roles as $role ) : ?>
								<th><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Dupliceren-knop uitsluiten', 'brink-multimedia-instellingen' ); ?></td>
							<?php foreach ( $roles as $role_slug => $role ) : ?>
								<td><input type="checkbox" name="bmi_roles_options[restrict_duplicate][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $options['restrict_duplicate'], true ) ); ?> /></td>
							<?php endforeach; ?>
						</tr>
						<tr>
							<td><?php esc_html_e( 'SVG-uploads uitsluiten', 'brink-multimedia-instellingen' ); ?></td>
							<?php foreach ( $roles as $role_slug => $role ) : ?>
								<td><input type="checkbox" name="bmi_roles_options[restrict_svg_upload][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $options['restrict_svg_upload'], true ) ); ?> /></td>
							<?php endforeach; ?>
						</tr>
						<tr>
							<td><?php esc_html_e( 'AVIF-uploads uitsluiten', 'brink-multimedia-instellingen' ); ?></td>
							<?php foreach ( $roles as $role_slug => $role ) : ?>
								<td><input type="checkbox" name="bmi_roles_options[restrict_avif_upload][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $options['restrict_avif_upload'], true ) ); ?> /></td>
							<?php endforeach; ?>
						</tr>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Deze restricties gelden bovenop de algemene aan/uit-schakelaars op het tabblad "Inhoudsbeheer" — een functie moet daar aanstaan én hier niet uitgesloten zijn voor de rol.', 'brink-multimedia-instellingen' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Verwijderen', 'brink-multimedia-instellingen' ); ?></h2>
			<?php if ( ! empty( $roles ) ) : ?>
				<p class="description"><?php esc_html_e( 'Vink aan welke rol niets mag verwijderen. Die rol kan nog wel alles toevoegen en aanpassen waar hij toegang toe heeft, maar kan geen pagina\'s, berichten, media, eigen berichttypes, categorieën of tags verwijderen of naar de prullenbak verplaatsen. De knoppen daarvoor verdwijnen vanzelf. Dit geldt overal, ook in de blokeditor en de REST API. De rol "Beheerder" kan altijd alles.', 'brink-multimedia-instellingen' ); ?></p>
				<table class="widefat bmi-role-matrix">
					<thead>
						<tr>
							<th></th>
							<?php foreach ( $roles as $role ) : ?>
								<th><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Verwijderen uitsluiten', 'brink-multimedia-instellingen' ); ?></td>
							<?php foreach ( $roles as $role_slug => $role ) : ?>
								<td><input type="checkbox" name="bmi_roles_options[restrict_delete][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $options['restrict_delete'], true ) ); ?> /></td>
							<?php endforeach; ?>
						</tr>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Andere plugins kunnen hierop aansluiten door voor een verwijderknop te controleren of de gebruiker het recht "delete_others_posts" heeft.', 'brink-multimedia-instellingen' ); ?></p>
			<?php endif; ?>

			<?php submit_button( __( 'Wijzigingen opslaan', 'brink-multimedia-instellingen' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Tabblad 3: Inhoudsbeheer.
	 */
	private function render_content_tab() {
		// Merge with defaults so options saved by an older version (without newer keys) don't trigger warnings.
		$options = wp_parse_args(
			(array) get_option( 'bmi_content_options', array() ),
			array(
				'enable_duplicate'  => 0,
				'allow_svg'         => 0,
				'allow_avif'        => 0,
				'show_reading_time' => 0,
			)
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bmi_content_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Dupliceren met 1 klik', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bmi_content_options[enable_duplicate]" value="1" <?php checked( 1, $options['enable_duplicate'] ); ?> />
							<?php esc_html_e( 'Voeg een "Dupliceer" link toe aan pagina\'s en berichten, waarmee je met 1 klik een kopie (als concept) maakt.', 'brink-multimedia-instellingen' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'SVG-uploads', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bmi_content_options[allow_svg]" value="1" <?php checked( 1, $options['allow_svg'] ); ?> />
							<?php esc_html_e( 'Sta het uploaden van SVG-bestanden toe in de mediabibliotheek.', 'brink-multimedia-instellingen' ); ?>
						</label>
						<p class="description bmi-highlight">
							<?php esc_html_e( 'Let op: SVG-bestanden kunnen schadelijke code bevatten. Zet dit alleen aan voor vertrouwde gebruikers.', 'brink-multimedia-instellingen' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'AVIF-uploads', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bmi_content_options[allow_avif]" value="1" <?php checked( 1, $options['allow_avif'] ); ?> />
							<?php esc_html_e( 'Sta het uploaden van AVIF-afbeeldingen toe in de mediabibliotheek.', 'brink-multimedia-instellingen' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Leestijd tonen', 'brink-multimedia-instellingen' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bmi_content_options[show_reading_time]" value="1" <?php checked( 1, $options['show_reading_time'] ); ?> />
							<?php esc_html_e( 'Toon het woordenaantal en de geschatte leestijd als kolom in de lijst met berichten/pagina\'s.', 'brink-multimedia-instellingen' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Wijzigingen opslaan', 'brink-multimedia-instellingen' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Tabblad 4: E-mail.
	 */
	private function render_email_tab() {
		$options = get_option(
			'bmi_email_options',
			array(
				'sender_name'  => '',
				'sender_email' => '',
			)
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bmi_email_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bmi_sender_name"><?php esc_html_e( 'Afzendernaam', 'brink-multimedia-instellingen' ); ?></label></th>
					<td>
						<input type="text" id="bmi_sender_name" name="bmi_email_options[sender_name]"
							value="<?php echo esc_attr( $options['sender_name'] ); ?>" class="regular-text" placeholder="Brink Multimedia" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bmi_sender_email"><?php esc_html_e( 'Afzenderadres', 'brink-multimedia-instellingen' ); ?></label></th>
					<td>
						<input type="email" id="bmi_sender_email" name="bmi_email_options[sender_email]"
							value="<?php echo esc_attr( $options['sender_email'] ); ?>" class="regular-text" placeholder="info@voorbeeld.nl" />
						<p class="description"><?php esc_html_e( 'Wordt gebruikt als afzender voor alle uitgaande WordPress-e-mails (in plaats van "WordPress" / wordpress@domein.nl).', 'brink-multimedia-instellingen' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Wijzigingen opslaan', 'brink-multimedia-instellingen' ) ); ?>
		</form>
		<?php
	}
}
