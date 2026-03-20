<?php
defined( 'ABSPATH' ) || exit;

class Mecspe_Trucks_Page {

	public function __construct() {
		add_shortcode( 'mecspe_trucks', [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_mecspe_filter_trucks', [ $this, 'ajax_filter' ] );
		add_action( 'wp_ajax_nopriv_mecspe_filter_trucks', [ $this, 'ajax_filter' ] );
	}

	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'mecspe_trucks' ) ) {
			wp_enqueue_style(
				'mecspe-trucks',
				MECSPE_TRUCKS_URL . 'assets/css/trucks.css',
				[],
				MECSPE_TRUCKS_VERSION
			);
			wp_enqueue_script(
				'mecspe-trucks',
				MECSPE_TRUCKS_URL . 'assets/js/trucks-filter.js',
				[ 'jquery' ],
				MECSPE_TRUCKS_VERSION,
				true
			);
			wp_localize_script( 'mecspe-trucks', 'mecspeTrucks', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mecspe_filter_trucks' ),
			] );
		}
	}

	// ------------------------------------------------------------------ //
	//  Shortcode
	// ------------------------------------------------------------------ //

	public function render_shortcode( $atts ): string {
		$filters = $this->get_filter_options();
		$trucks  = $this->query_trucks( [] );

		ob_start();
		include MECSPE_TRUCKS_PATH . 'templates/trucks-list.php';
		return ob_get_clean();
	}

	// ------------------------------------------------------------------ //
	//  AJAX
	// ------------------------------------------------------------------ //

	public function ajax_filter(): void {
		check_ajax_referer( 'mecspe_filter_trucks', 'nonce' );

		$args = [
			'marca'    => sanitize_text_field( $_POST['marca']    ?? '' ),
			'modello'  => sanitize_text_field( $_POST['modello']  ?? '' ),
			'prezzo_min' => absint( $_POST['prezzo_min'] ?? 0 ),
			'prezzo_max' => absint( $_POST['prezzo_max'] ?? 0 ),
			'km_max'   => absint( $_POST['km_max']   ?? 0 ),
		];

		$trucks = $this->query_trucks( $args );

		ob_start();
		if ( empty( $trucks ) ) {
			echo '<p class="mecspe-no-results">' . esc_html__( 'Nessun veicolo trovato con i filtri selezionati.', 'mecspe-trucks' ) . '</p>';
		} else {
			foreach ( $trucks as $truck ) {
				include MECSPE_TRUCKS_PATH . 'templates/truck-card.php';
			}
		}
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html, 'count' => count( $trucks ) ] );
	}

	// ------------------------------------------------------------------ //
	//  Query
	// ------------------------------------------------------------------ //

	private function query_trucks( array $args ): array {
		$query_args = [
			'post_type'      => 'prodotto',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [ 'relation' => 'AND' ],
		];

		// Filtro marca (ripetitore: cerca nei valori serializzati)
		if ( ! empty( $args['marca'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'marca_prodotto',
				'value'   => $args['marca'],
				'compare' => 'LIKE',
			];
		}

		// Filtro modello
		if ( ! empty( $args['modello'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'modello_prodotto',
				'value'   => $args['modello'],
				'compare' => 'LIKE',
			];
		}

		// Filtro prezzo
		if ( ! empty( $args['prezzo_min'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'prezzo_prodotto',
				'value'   => $args['prezzo_min'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			];
		}
		if ( ! empty( $args['prezzo_max'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'prezzo_prodotto',
				'value'   => $args['prezzo_max'],
				'compare' => '<=',
				'type'    => 'NUMERIC',
			];
		}

		// Filtro km
		if ( ! empty( $args['km_max'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'km_percorsi_prodotto',
				'value'   => $args['km_max'],
				'compare' => '<=',
				'type'    => 'NUMERIC',
			];
		}

		$posts  = get_posts( $query_args );
		$trucks = [];

		foreach ( $posts as $post ) {
			$trucks[] = [
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'link'    => get_permalink( $post->ID ),
				'thumb'   => get_the_post_thumbnail_url( $post->ID, 'medium' ),
				'modello' => get_field( 'modello_prodotto', $post->ID ),
				'targa'   => get_field( 'targa_prodotto', $post->ID ),
				'prezzo'  => get_field( 'prezzo_prodotto', $post->ID ),
				'km'      => get_field( 'km_percorsi_prodotto', $post->ID ),
				'marca'   => get_field( 'marca_prodotto', $post->ID ),
				'cavalli' => get_field( 'cavalli_prodotto', $post->ID ),
				'prima_immatricolazione' => get_field( 'prima_immatricolazione_prodotto', $post->ID ),
				'veicolo_pronto' => get_field( 'veicolo_pronto_prodotto', $post->ID ),
			];
		}

		return $trucks;
	}

	// ------------------------------------------------------------------ //
	//  Opzioni per i filtri (valori distinti nel DB)
	// ------------------------------------------------------------------ //

	private function get_filter_options(): array {
		global $wpdb;

		$marche = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value",
			'modello_prodotto'
		) );

		$modelli = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value",
			'modello_prodotto'
		) );

		return [
			'marche'  => $marche,
			'modelli' => $modelli,
		];
	}
}
