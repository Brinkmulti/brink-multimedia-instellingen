<?php
// Voorkom direct aanroepen van dit bestand.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regelt het tabblad "Inhoudsbeheer": pagina's/berichten met 1 klik
 * dupliceren, en SVG- en AVIF-uploads aan- of uitzetten.
 */
class BMI_Content {

	/**
	 * Opgeslagen instellingen voor dit tabblad.
	 *
	 * @var array
	 */
	private $options;

	public function __construct() {
		$this->options = get_option(
			'bmi_content_options',
			array(
				'enable_duplicate'  => 0,
				'allow_svg'         => 0,
				'allow_avif'        => 0,
				'show_reading_time' => 0,
			)
		);

		if ( ! empty( $this->options['enable_duplicate'] ) ) {
			add_filter( 'post_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
			add_filter( 'page_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
			add_action( 'admin_action_bmi_duplicate_post', array( $this, 'handle_duplicate_post' ) );
		}

		if ( ! empty( $this->options['allow_svg'] ) ) {
			add_filter( 'upload_mimes', array( $this, 'allow_svg_mime' ) );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_filetype' ), 10, 4 );
		}

		if ( ! empty( $this->options['allow_avif'] ) ) {
			add_filter( 'upload_mimes', array( $this, 'allow_avif_mime' ) );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_avif_filetype' ), 10, 4 );
		}

		if ( ! empty( $this->options['show_reading_time'] ) ) {
			add_filter( 'manage_posts_columns', array( $this, 'add_reading_time_column' ) );
			add_filter( 'manage_pages_columns', array( $this, 'add_reading_time_column' ) );
			add_action( 'manage_posts_custom_column', array( $this, 'render_reading_time_column' ), 10, 2 );
			add_action( 'manage_pages_custom_column', array( $this, 'render_reading_time_column' ), 10, 2 );
		}
	}

	// ------------------------------------------------------------
	// Leestijd/woordenaantal in de berichtenlijst
	// ------------------------------------------------------------

	/**
	 * Voeg een kolom "Leestijd" toe aan de lijst met berichten/pagina's.
	 *
	 * @param array $columns Bestaande kolommen.
	 * @return array
	 */
	public function add_reading_time_column( $columns ) {
		$columns['bmi_reading_time'] = __( 'Leestijd', 'brink-multimedia-instellingen' );
		return $columns;
	}

	/**
	 * Toon het woordenaantal en de geschatte leestijd in de kolom.
	 *
	 * @param string $column  Kolomnaam.
	 * @param int    $post_id Post-ID.
	 */
	public function render_reading_time_column( $column, $post_id ) {
		if ( 'bmi_reading_time' !== $column ) {
			return;
		}

		$content    = get_post_field( 'post_content', $post_id );
		$word_count = str_word_count( wp_strip_all_tags( strip_shortcodes( (string) $content ) ) );
		$minutes    = max( 1, (int) ceil( $word_count / 200 ) );

		printf(
			/* translators: 1: aantal woorden, 2: leestijd in minuten. */
			esc_html__( '%1$d woorden (~%2$d min)', 'brink-multimedia-instellingen' ),
			(int) $word_count,
			(int) $minutes
		);
	}

	// ------------------------------------------------------------
	// Dupliceren van een bericht/pagina met 1 klik
	// ------------------------------------------------------------

	/**
	 * Voeg een "Dupliceer" link toe aan de rijacties in de lijst met
	 * berichten/pagina's.
	 *
	 * @param array   $actions Bestaande acties.
	 * @param WP_Post $post    Het post-object.
	 * @return array
	 */
	public function add_duplicate_link( $actions, $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) || BMI_Roles::role_is_restricted( 'restrict_duplicate' ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'bmi_duplicate_post',
					'post'   => $post->ID,
				),
				admin_url( 'admin.php' )
			),
			'bmi_duplicate_post_' . $post->ID
		);

		$actions['bmi_duplicate'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr__( 'Dupliceer dit item', 'brink-multimedia-instellingen' ),
			esc_html__( 'Dupliceer', 'brink-multimedia-instellingen' )
		);

		return $actions;
	}

	/**
	 * Verwerk het dupliceren van een bericht/pagina, inclusief
	 * taxonomieën en custom fields. De kopie wordt als concept opgeslagen.
	 */
	public function handle_duplicate_post() {
		if ( BMI_Roles::role_is_restricted( 'restrict_duplicate' ) ) {
			wp_die(
				esc_html__( 'Je hebt geen toestemming om items te dupliceren.', 'brink-multimedia-instellingen' ),
				esc_html__( 'Geen toegang', 'brink-multimedia-instellingen' ),
				array( 'response' => 403 )
			);
		}

		if ( empty( $_GET['post'] ) ) {
			wp_die( esc_html__( 'Er is geen item gevonden om te dupliceren.', 'brink-multimedia-instellingen' ) );
		}

		$post_id = absint( $_GET['post'] );
		check_admin_referer( 'bmi_duplicate_post_' . $post_id );

		$post = get_post( $post_id );

		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Je hebt geen toestemming om dit item te dupliceren.', 'brink-multimedia-instellingen' ) );
		}

		$new_post_args = array(
			/* translators: %s: original post title. */
			'post_title'     => sprintf( __( '%s (kopie)', 'brink-multimedia-instellingen' ), $post->post_title ),
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => 'draft',
			'post_type'      => $post->post_type,
			'post_author'    => get_current_user_id(),
			'post_parent'    => $post->post_parent,
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
		);

		$new_post_id = wp_insert_post( $new_post_args, true );

		if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
			wp_die( esc_html__( 'Dupliceren is mislukt. Probeer het opnieuw.', 'brink-multimedia-instellingen' ) );
		}

		// Taxonomieën (categorieën, tags, custom taxonomieën) meekopiëren.
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $new_post_id, $terms, $taxonomy );
			}
		}

		// Custom fields (post meta) meekopiëren, behalve interne unieke velden.
		$skip_meta = array( '_edit_lock', '_edit_last' );
		$meta      = get_post_meta( $post_id );
		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $skip_meta, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
			}
		}

		$redirect = ( 'page' === $post->post_type )
			? admin_url( 'edit.php?post_type=page' )
			: admin_url( 'edit.php' );

		wp_safe_redirect( $redirect );
		exit;
	}

	// ------------------------------------------------------------
	// SVG-uploads
	// ------------------------------------------------------------

	/**
	 * Sta SVG-bestanden toe in de mediabibliotheek.
	 *
	 * Let op: SVG's kunnen (kwaadaardige) scripts bevatten. Zet dit
	 * alleen aan voor gebruikers die je vertrouwt.
	 *
	 * @param array $mimes Toegestane mimetypes.
	 * @return array
	 */
	public function allow_svg_mime( $mimes ) {
		if ( BMI_Roles::role_is_restricted( 'restrict_svg_upload' ) ) {
			return $mimes;
		}

		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Zorg dat WordPress SVG-bestanden herkent bij het valideren van uploads.
	 *
	 * @param array  $data     Gedetecteerde bestandsgegevens.
	 * @param string $file     Volledig pad naar het bestand.
	 * @param string $filename Bestandsnaam.
	 * @param array  $mimes    Toegestane mimetypes.
	 * @return array
	 */
	public function fix_svg_filetype( $data, $file, $filename, $mimes ) {
		if ( BMI_Roles::role_is_restricted( 'restrict_svg_upload' ) ) {
			return $data;
		}

		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( in_array( $extension, array( 'svg', 'svgz' ), true ) ) {
			$data['ext']             = $extension;
			$data['type']            = 'image/svg+xml';
			$data['proper_filename'] = $filename;
		}

		return $data;
	}

	// ------------------------------------------------------------
	// AVIF-uploads
	// ------------------------------------------------------------

	/**
	 * Sta AVIF-bestanden toe in de mediabibliotheek.
	 *
	 * @param array $mimes Toegestane mimetypes.
	 * @return array
	 */
	public function allow_avif_mime( $mimes ) {
		if ( BMI_Roles::role_is_restricted( 'restrict_avif_upload' ) ) {
			return $mimes;
		}

		$mimes['avif'] = 'image/avif';
		return $mimes;
	}

	/**
	 * Zorg dat WordPress AVIF-bestanden herkent bij het valideren van uploads
	 * (op oudere PHP/WordPress-installaties waar dit nog niet standaard werkt).
	 *
	 * @param array  $data     Gedetecteerde bestandsgegevens.
	 * @param string $file     Volledig pad naar het bestand.
	 * @param string $filename Bestandsnaam.
	 * @param array  $mimes    Toegestane mimetypes.
	 * @return array
	 */
	public function fix_avif_filetype( $data, $file, $filename, $mimes ) {
		if ( BMI_Roles::role_is_restricted( 'restrict_avif_upload' ) ) {
			return $data;
		}

		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'avif' === $extension ) {
			$data['ext']             = 'avif';
			$data['type']            = 'image/avif';
			$data['proper_filename'] = $filename;
		}

		return $data;
	}
}
