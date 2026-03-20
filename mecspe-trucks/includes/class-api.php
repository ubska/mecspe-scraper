<?php
defined( 'ABSPATH' ) || exit;

/**
 * Espone i veicoli tramite WordPress REST API.
 *
 * Endpoints disponibili:
 *   GET /wp-json/mecspe/v1/trucks          – lista veicoli (con filtri opzionali)
 *   GET /wp-json/mecspe/v1/trucks/{id}     – singolo veicolo
 */
class Mecspe_Trucks_Api {

	const NAMESPACE = 'mecspe/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/trucks', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_trucks' ],
			'permission_callback' => '__return_true',
			'args'                => $this->collection_args(),
		] );

		register_rest_route( self::NAMESPACE, '/trucks/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_truck' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );
	}

	// ------------------------------------------------------------------ //
	//  Handlers
	// ------------------------------------------------------------------ //

	public function get_trucks( WP_REST_Request $request ): WP_REST_Response {
		$query_args = [
			'post_type'      => 'prodotti',
			'post_status'    => 'publish',
			'posts_per_page' => $request->get_param( 'per_page' ) ?? 20,
			'paged'          => $request->get_param( 'page' ) ?? 1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [ 'relation' => 'AND' ],
		];

		// Filtro marca
		if ( $marca = $request->get_param( 'marca' ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'marca_prodotto',
				'value'   => $marca,
				'compare' => 'LIKE',
			];
		}

		// Filtro modello
		if ( $modello = $request->get_param( 'modello' ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'modello_prodotto',
				'value'   => $modello,
				'compare' => 'LIKE',
			];
		}

		// Filtro prezzo
		if ( $prezzo_min = $request->get_param( 'prezzo_min' ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'prezzo_prodotto',
				'value'   => $prezzo_min,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			];
		}
		if ( $prezzo_max = $request->get_param( 'prezzo_max' ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'prezzo_prodotto',
				'value'   => $prezzo_max,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			];
		}

		// Filtro km
		if ( $km_max = $request->get_param( 'km_max' ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'km_percorsi_prodotto',
				'value'   => $km_max,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			];
		}

		// Filtro veicolo_pronto
		if ( null !== $request->get_param( 'veicolo_pronto' ) ) {
			$query_args['meta_query'][] = [
				'key'     => 'veicolo_pronto_prodotto',
				'value'   => $request->get_param( 'veicolo_pronto' ) ? '1' : '0',
				'compare' => '=',
			];
		}

		$query = new WP_Query( $query_args );
		$total = $query->found_posts;
		$data  = [];

		foreach ( $query->posts as $post ) {
			$data[] = $this->format_truck( $post );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	public function get_truck( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = get_post( $request->get_param( 'id' ) );

		if ( ! $post || $post->post_type !== 'prodotti' || $post->post_status !== 'publish' ) {
			return new WP_Error(
				'mecspe_truck_not_found',
				__( 'Veicolo non trovato.', 'mecspe-trucks' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( $this->format_truck( $post, true ) );
	}

	// ------------------------------------------------------------------ //
	//  Serializzazione
	// ------------------------------------------------------------------ //

	private function format_truck( WP_Post $post, bool $full = false ): array {
		$data = [
			'id'                     => $post->ID,
			'title'                  => $post->post_title,
			'link'                   => get_permalink( $post->ID ),
			'thumbnail'              => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: null,
			'modello'                => get_field( 'modello_prodotto', $post->ID ),
			'targa'                  => get_field( 'targa_prodotto', $post->ID ),
			'codice_interno'         => get_field( 'codice_interno_prodotto', $post->ID ),
			'prezzo'                 => (float) get_field( 'prezzo_prodotto', $post->ID ),
			'km'                     => (int) get_field( 'km_percorsi_prodotto', $post->ID ),
			'cavalli'                => get_field( 'cavalli_prodotto', $post->ID ),
			'prima_immatricolazione' => get_field( 'prima_immatricolazione_prodotto', $post->ID ),
			'veicolo_pronto'         => (bool) get_field( 'veicolo_pronto_prodotto', $post->ID ),
			'marca'                  => get_field( 'marca_prodotto', $post->ID ),
		];

		if ( $full ) {
			$data = array_merge( $data, [
				'telaio'               => get_field( 'telaio_prodotto', $post->ID ),
				'serbatoio'            => get_field( 'serbatoio_prodotto', $post->ID ),
				'interasse'            => get_field( 'Interasse_prodotto', $post->ID ),
				'capacita_massima'     => get_field( 'capacita_massima_prodotto', $post->ID ),
				'ultima_revisione'     => get_field( 'ultima_revisione_prodotto', $post->ID ),
				'scadenza_revisione'   => get_field( 'scadenza_revisione_prodotto', $post->ID ),
				'note'                 => get_field( 'note_prodotto', $post->ID ),
				'note_interne'         => get_field( 'note_interne_prodotto', $post->ID ),
				'trattativa_in_sede'   => (bool) get_field( 'trattativa_in_sede', $post->ID ),
				'giorni_opzionati'     => get_field( 'giorni_opzionati', $post->ID ),
				'data_inizio_opzione'  => get_field( 'data_inizio_giorni_opzionati', $post->ID ),
				'data_fine_opzione'    => get_field( 'data_fine_giorni_opzionati', $post->ID ),
				'utente_opzionatore'   => get_field( 'utente_opzionatore_prodotto', $post->ID ),
				'link_scheda_pdf'      => get_field( 'link_scheda_pdf_prodotto', $post->ID ),
				'link_gestionale'      => get_field( 'link_gestionale', $post->ID ),
				'id_gestionale'        => get_field( 'id_gestionale', $post->ID ),
				'allestimento'         => get_field( 'allestimento_prodotto', $post->ID ),
				'cabina'               => get_field( 'cabina_prodotto', $post->ID ),
				'cambio'               => get_field( 'cambio_prodotto', $post->ID ),
				'equipaggiamento'      => get_field( 'equipaggiamento_prodotto', $post->ID ),
				'fender_laterale'      => get_field( 'fender_laterale_prodotto', $post->ID ),
				'minigonne'            => get_field( 'minigonne_prodotto', $post->ID ),
				'motore'               => get_field( 'motore_prodotto', $post->ID ),
				'pneumatici'           => get_field( 'pneumatici_prodotto', $post->ID ),
				'spoiler'              => get_field( 'spoiler_prodotto', $post->ID ),
				'tipo_offerta'         => get_field( 'tipo_offerta_prodotto', $post->ID ),
				'elenco_allegati'      => get_field( 'elenco_allegati', $post->ID ),
			] );
		}

		return $data;
	}

	// ------------------------------------------------------------------ //
	//  Argomenti collection
	// ------------------------------------------------------------------ //

	private function collection_args(): array {
		return [
			'page'           => [
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
			'per_page'       => [
				'default'           => 20,
				'sanitize_callback' => 'absint',
			],
			'marca'          => [
				'sanitize_callback' => 'sanitize_text_field',
			],
			'modello'        => [
				'sanitize_callback' => 'sanitize_text_field',
			],
			'prezzo_min'     => [
				'sanitize_callback' => 'absint',
			],
			'prezzo_max'     => [
				'sanitize_callback' => 'absint',
			],
			'km_max'         => [
				'sanitize_callback' => 'absint',
			],
			'veicolo_pronto' => [
				'sanitize_callback' => fn( $v ) => (bool) $v,
			],
		];
	}
}
