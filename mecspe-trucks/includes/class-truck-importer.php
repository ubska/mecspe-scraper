<?php
defined( 'ABSPATH' ) || exit;

class Mecspe_Truck_Importer {

	/** Campi ripetitore ACF che arrivano come array. */
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

	// ------------------------------------------------------------------ //
	//  Upsert singolo truck (chiamato dall'endpoint /receive)
	// ------------------------------------------------------------------ //

	/**
	 * Inserisce o aggiorna un truck ricevuto dal gestionale.
	 *
	 * @param array $truck  Array associativo con i campi del veicolo.
	 * @return int|WP_Error  Post ID in caso di successo, WP_Error altrimenti.
	 */
	public static function upsert_truck( array $truck ): int|WP_Error {
		$gestionale_id = sanitize_text_field( $truck['id_gestionale'] ?? $truck['id'] ?? '' );

		if ( empty( $gestionale_id ) ) {
			return new WP_Error( 'no_id', 'id_gestionale mancante nel payload.' );
		}

		$title   = sanitize_text_field( $truck['modello'] ?? $gestionale_id );
		$post_id = self::find_post_by_gestionale_id( $gestionale_id );

		if ( ! $post_id ) {
			$post_id = wp_insert_post( [
				'post_title'  => $title,
				'post_type'   => 'prodotti',
				'post_status' => 'publish',
			] );
		} else {
			wp_update_post( [
				'ID'         => $post_id,
				'post_title' => $title,
			] );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_acf_fields( $post_id, $truck );
		update_post_meta( $post_id, '_mecspe_gestionale_id', $gestionale_id );

		return $post_id;
	}

	// ------------------------------------------------------------------ //
	//  Cancellazione singolo truck (chiamato dall'endpoint /receive)
	// ------------------------------------------------------------------ //

	/**
	 * Elimina il post WordPress corrispondente all'ID gestionale.
	 *
	 * @return bool  true se il post è stato eliminato, false se non trovato.
	 */
	public static function delete_truck( string $gestionale_id ): bool {
		$post_id = self::find_post_by_gestionale_id( $gestionale_id );
		if ( ! $post_id ) {
			return false;
		}
		return (bool) wp_delete_post( $post_id, true );
	}

	// ------------------------------------------------------------------ //
	//  Sync bulk via cron (fallback / importazione iniziale)
	// ------------------------------------------------------------------ //

	public static function run_sync(): void {
		$trucks = Mecspe_Gestionale_Connector::fetch_trucks();

		if ( is_wp_error( $trucks ) ) {
			update_option( 'mecspe_trucks_last_sync', current_time( 'mysql' ) );
			update_option( 'mecspe_trucks_last_result', $trucks->get_error_message() );
			return;
		}

		$created = 0;
		$updated = 0;

		foreach ( $trucks as $truck ) {
			$gestionale_id = $truck['id_gestionale'] ?? $truck['id'] ?? null;
			if ( empty( $gestionale_id ) ) {
				continue;
			}
			$exists  = self::find_post_by_gestionale_id( sanitize_text_field( $gestionale_id ) );
			$post_id = self::upsert_truck( $truck );
			if ( ! is_wp_error( $post_id ) ) {
				$exists ? $updated++ : $created++;
			}
		}

		$result = sprintf(
			__( 'Sync completato: %d creati, %d aggiornati.', 'mecspe-trucks' ),
			$created,
			$updated
		);

		update_option( 'mecspe_trucks_last_sync', current_time( 'mysql' ) );
		update_option( 'mecspe_trucks_last_result', $result );
	}

	// ------------------------------------------------------------------ //
	//  Helpers privati
	// ------------------------------------------------------------------ //

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

	private static function save_acf_fields( int $post_id, array $truck ): void {
		$field_map = Mecspe_Gestionale_Connector::field_map();

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
	}

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
