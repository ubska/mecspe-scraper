<?php
defined( 'ABSPATH' ) || exit;

class Mecspe_Truck_Importer {

	/** Campi ripetitore ACF che arrivano come array dal gestionale. */
	private const REPEATER_FIELDS = [
		'allestimento_prodotto',
		'cabina_prodotto',
		'cambio_prodotto',
		'equipaggiamento_prodotto',
		'fender_laterale_prodotto',
		'marca_prodotto',
		'minigonne_prodotto',
		'motore_prodotto',
		'pneumatici_prodotto',
		'spoiler_prodotto',
		'tipo_offerta_prodotto',
		'elenco_allegati',
	];

	/**
	 * Punto di ingresso: recupera i veicoli e li sincronizza.
	 */
	public static function run_sync(): void {
		$trucks = Mecspe_Gestionale_Connector::fetch_trucks();

		if ( is_wp_error( $trucks ) ) {
			update_option( 'mecspe_trucks_last_sync', current_time( 'mysql' ) );
			update_option( 'mecspe_trucks_last_result', $trucks->get_error_message() );
			return;
		}

		$created  = 0;
		$updated  = 0;
		$field_map = Mecspe_Gestionale_Connector::field_map();

		foreach ( $trucks as $truck ) {
			if ( empty( $truck[ 'id_gestionale' ] ?? $truck['id'] ?? null ) ) {
				continue;
			}

			$gestionale_id = sanitize_text_field( $truck['id_gestionale'] ?? $truck['id'] );
			$post_id       = self::find_post_by_gestionale_id( $gestionale_id );
			$title         = sanitize_text_field( $truck['modello'] ?? $gestionale_id );
			$is_new        = false;

			if ( ! $post_id ) {
				$post_id = wp_insert_post( [
					'post_title'  => $title,
					'post_type'   => 'prodotti',
					'post_status' => 'publish',
				] );
				$is_new = true;
			} else {
				wp_update_post( [
					'ID'         => $post_id,
					'post_title' => $title,
				] );
			}

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			// Aggiorna campi ACF
			foreach ( $field_map as $json_key => $acf_key ) {
				if ( ! isset( $truck[ $json_key ] ) ) {
					continue;
				}

				$value = $truck[ $json_key ];

				if ( in_array( $acf_key, self::REPEATER_FIELDS, true ) ) {
					$value = self::normalize_repeater( $value );
				} elseif ( in_array( $acf_key, [ 'veicolo_pronto_prodotto', 'trattativa_in_sede' ], true ) ) {
					$value = (bool) $value ? 1 : 0;
				} else {
					$value = sanitize_text_field( (string) $value );
				}

				update_field( $acf_key, $value, $post_id );
			}

			// Salva id_gestionale per trovare il post nelle sync successive
			update_post_meta( $post_id, '_mecspe_gestionale_id', $gestionale_id );

			$is_new ? $created++ : $updated++;
		}

		$result = sprintf(
			__( 'Sync completato: %d creati, %d aggiornati.', 'mecspe-trucks' ),
			$created,
			$updated
		);

		update_option( 'mecspe_trucks_last_sync', current_time( 'mysql' ) );
		update_option( 'mecspe_trucks_last_result', $result );
	}

	/**
	 * Cerca un post prodotto tramite il suo ID gestionale.
	 */
	private static function find_post_by_gestionale_id( string $gestionale_id ): ?int {
		$posts = get_posts( [
			'post_type'      => 'prodotti',
			'posts_per_page' => 1,
			'meta_key'       => '_mecspe_gestionale_id',
			'meta_value'     => $gestionale_id,
			'fields'         => 'ids',
		] );
		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	/**
	 * Normalizza un valore per i campi ripetitore ACF.
	 * Il gestionale può inviare una stringa, un array di stringhe,
	 * o già un array nel formato ACF { valore: "..." }.
	 */
	private static function normalize_repeater( $value ): array {
		if ( is_string( $value ) ) {
			return [ [ 'valore' => sanitize_text_field( $value ) ] ];
		}
		if ( is_array( $value ) ) {
			$rows = [];
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					$rows[] = array_map( 'sanitize_text_field', $item );
				} else {
					$rows[] = [ 'valore' => sanitize_text_field( (string) $item ) ];
				}
			}
			return $rows;
		}
		return [];
	}
}
