<?php
defined( 'ABSPATH' ) || exit;

/**
 * Connettore verso il gestionale esterno.
 *
 * Mappa i campi JSON del gestionale ai nomi ACF WordPress.
 * Modifica field_map() e fetch_trucks() quando conosci i dettagli dell'API.
 */
class Mecspe_Gestionale_Connector {

	/**
	 * Mappa: chiave JSON del gestionale => nome campo ACF.
	 *
	 * Aggiorna le chiavi JSON (lato sinistro) con i nomi reali dell'API
	 * quando saranno disponibili.
	 */
	public static function field_map(): array {
		return [
			// Dati base
			'modello'                  => 'modello_prodotto',
			'targa'                    => 'targa_prodotto',
			'codice_interno'           => 'codice_interno_prodotto',
			'prezzo'                   => 'prezzo_prodotto',
			'prima_immatricolazione'   => 'prima_immatricolazione_prodotto',
			'ultima_revisione'         => 'ultima_revisione_prodotto',
			'scadenza_revisione'       => 'scadenza_revisione_prodotto',
			'telaio'                   => 'telaio_prodotto',
			'serbatoio'                => 'serbatoio_prodotto',
			'interasse'                => 'Interasse_prodotto',
			'cavalli'                  => 'cavalli_prodotto',
			'km'                       => 'km_percorsi_prodotto',
			'capacita_massima'         => 'capacita_massima_prodotto',
			'veicolo_pronto'           => 'veicolo_pronto_prodotto',
			'giorni_opzionati'         => 'giorni_opzionati',
			'data_inizio_opzione'      => 'data_inizio_giorni_opzionati',
			'data_fine_opzione'        => 'data_fine_giorni_opzionati',
			'utente_opzionatore'       => 'utente_opzionatore_prodotto',
			'link_scheda_pdf'          => 'link_scheda_pdf_prodotto',
			'link_gestionale'          => 'link_gestionale',
			'note'                     => 'note_prodotto',
			'note_interne'             => 'note_interne_prodotto',
			'id_gestionale'            => 'id_gestionale',
			'trattativa_in_sede'       => 'trattativa_in_sede',
			// Ripetitori (array nel JSON)
			'allestimento'             => 'allestimento_prodotto',
			'cabina'                   => 'cabina_prodotto',
			'cambio'                   => 'cambio_prodotto',
			'equipaggiamento'          => 'equipaggiamento_prodotto',
			'fender_laterale'          => 'fender_laterale_prodotto',
			'marca'                    => 'marca_prodotto',
			'minigonne'                => 'minigonne_prodotto',
			'motore'                   => 'motore_prodotto',
			'pneumatici'               => 'pneumatici_prodotto',
			'spoiler'                  => 'spoiler_prodotto',
			'tipo_offerta'             => 'tipo_offerta_prodotto',
			'elenco_allegati'          => 'elenco_allegati',
		];
	}

	/**
	 * Recupera l'elenco dei veicoli dal gestionale.
	 *
	 * @return array|WP_Error  Array di veicoli o WP_Error in caso di errore.
	 */
	public static function fetch_trucks() {
		$api_url      = get_option( 'mecspe_trucks_api_url', '' );
		$api_key      = get_option( 'mecspe_trucks_api_key', '' );
		$api_user     = get_option( 'mecspe_trucks_api_user', '' );
		$api_password = get_option( 'mecspe_trucks_api_password', '' );

		if ( empty( $api_url ) ) {
			return new WP_Error( 'no_url', __( 'URL API gestionale non configurato. Vai in Prodotti → Sync Gestionale.', 'mecspe-trucks' ) );
		}

		// Headers autenticazione
		$headers = [ 'Accept' => 'application/json' ];
		if ( ! empty( $api_key ) ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		} elseif ( ! empty( $api_user ) && ! empty( $api_password ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $api_user . ':' . $api_password );
		}

		$response = wp_remote_get( $api_url, [
			'headers' => $headers,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', sprintf( __( 'Risposta API: %d', 'mecspe-trucks' ), $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', __( 'Risposta API non è JSON valido.', 'mecspe-trucks' ) );
		}

		// Supporta sia { "trucks": [...] } sia direttamente [...]
		if ( isset( $data['trucks'] ) && is_array( $data['trucks'] ) ) {
			return $data['trucks'];
		}
		if ( is_array( $data ) ) {
			return $data;
		}

		return new WP_Error( 'no_data', __( 'Nessun dato veicolo trovato nella risposta.', 'mecspe-trucks' ) );
	}
}
