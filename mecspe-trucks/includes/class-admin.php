<?php
defined( 'ABSPATH' ) || exit;

class Mecspe_Trucks_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_mecspe_trucks_sync', [ $this, 'handle_manual_sync' ] );
		add_action( 'admin_post_mecspe_trucks_test_api', [ $this, 'handle_test_api' ] );
		add_action( 'admin_notices', [ $this, 'show_notices' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=prodotti',
			__( 'Impostazioni Sync', 'mecspe-trucks' ),
			__( 'Sync Gestionale', 'mecspe-trucks' ),
			'manage_options',
			'mecspe-trucks-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'mecspe_trucks_settings', 'mecspe_trucks_frontend_token', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		// Mantenuti per eventuale sync bulk manuale
		register_setting( 'mecspe_trucks_settings', 'mecspe_trucks_api_url', [
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		] );
		register_setting( 'mecspe_trucks_settings', 'mecspe_trucks_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( 'mecspe_trucks_settings', 'mecspe_trucks_api_user', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( 'mecspe_trucks_settings', 'mecspe_trucks_api_password', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
	}

	public function render_settings_page() {
		$last_sync      = get_option( 'mecspe_trucks_last_sync' );
		$last_result    = get_option( 'mecspe_trucks_last_result' );
		$test_result    = get_transient( 'mecspe_trucks_api_test' );
		$receive_url    = rest_url( 'mecspe/v1/receive' );
		$current_token  = get_option( 'mecspe_trucks_frontend_token', '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mecspe Trucks – Sync Gestionale', 'mecspe-trucks' ); ?></h1>

			<?php if ( $last_sync ) : ?>
				<p><?php printf(
					esc_html__( 'Ultima sincronizzazione: %1$s — %2$s', 'mecspe-trucks' ),
					esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $last_sync ) ) ),
					esc_html( $last_result )
				); ?></p>
			<?php endif; ?>

			<?php /* ---- SEZIONE PRINCIPALE: ricezione push dal gestionale ---- */ ?>
			<div style="background:#fff; border:1px solid #c3c4c7; border-left:4px solid #00a32a; padding:16px 20px; margin:20px 0; border-radius:4px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Configurazione Push dal Gestionale', 'mecspe-trucks' ); ?></h2>
				<p><?php esc_html_e( 'Il gestionale invia i dati dei veicoli a questo WordPress via POST HTTP con payload XML. Configura questi valori nel file .env del gestionale:', 'mecspe-trucks' ); ?></p>
				<table class="form-table" style="max-width:800px;">
					<tr>
						<th style="width:180px;"><?php esc_html_e( 'FRONTEND_URL', 'mecspe-trucks' ); ?></th>
						<td>
							<code style="font-size:14px; user-select:all;"><?php echo esc_html( $receive_url ); ?></code>
							<p class="description"><?php esc_html_e( 'Copia questo URL nel .env del gestionale come FRONTEND_URL', 'mecspe-trucks' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'FRONTEND_TOKEN', 'mecspe-trucks' ); ?></th>
						<td>
							<?php if ( $current_token ) : ?>
								<code style="font-size:14px; user-select:all;"><?php echo esc_html( $current_token ); ?></code>
								<p class="description"><?php esc_html_e( 'Copia questo token nel .env del gestionale come FRONTEND_TOKEN', 'mecspe-trucks' ); ?></p>
							<?php else : ?>
								<span style="color:#d63638;"><?php esc_html_e( 'Token non ancora configurato — impostalo qui sotto.', 'mecspe-trucks' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<?php /* ---- FORM IMPOSTAZIONI ---- */ ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'mecspe_trucks_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Token segreto (FRONTEND_TOKEN)', 'mecspe-trucks' ); ?></th>
						<td>
							<input type="text" name="mecspe_trucks_frontend_token"
								value="<?php echo esc_attr( $current_token ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'es. un-token-casuale-sicuro', 'mecspe-trucks' ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Deve coincidere esattamente con FRONTEND_TOKEN nel .env del gestionale. Usane uno lungo e casuale.', 'mecspe-trucks' ); ?>
								<br>
								<a href="#" onclick="
									var t = Math.random().toString(36).substring(2,10)+'-'+Math.random().toString(36).substring(2,10)+'-'+Math.random().toString(36).substring(2,10);
									document.querySelector('[name=mecspe_trucks_frontend_token]').value = t;
									return false;
								"><?php esc_html_e( 'Genera token casuale', 'mecspe-trucks' ); ?></a>
							</p>
						</td>
					</tr>

					<tr><td colspan="2"><hr><h3 style="margin:0;"><?php esc_html_e( 'Sync bulk manuale (opzionale)', 'mecspe-trucks' ); ?></h3>
					<p style="margin:4px 0 0;"><?php esc_html_e( 'Solo se il gestionale espone un\'API REST per importare tutti i veicoli in blocco.', 'mecspe-trucks' ); ?></p></td></tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'URL API gestionale', 'mecspe-trucks' ); ?></th>
						<td>
							<input type="url" name="mecspe_trucks_api_url"
								value="<?php echo esc_attr( get_option( 'mecspe_trucks_api_url' ) ); ?>"
								class="regular-text" placeholder="https://gestionale.esempio.it/api/trucks" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API Key', 'mecspe-trucks' ); ?></th>
						<td>
							<input type="text" name="mecspe_trucks_api_key"
								value="<?php echo esc_attr( get_option( 'mecspe_trucks_api_key' ) ); ?>"
								class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Utente', 'mecspe-trucks' ); ?></th>
						<td>
							<input type="text" name="mecspe_trucks_api_user"
								value="<?php echo esc_attr( get_option( 'mecspe_trucks_api_user' ) ); ?>"
								class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Password', 'mecspe-trucks' ); ?></th>
						<td>
							<input type="password" name="mecspe_trucks_api_password"
								value="<?php echo esc_attr( get_option( 'mecspe_trucks_api_password' ) ); ?>"
								class="regular-text" />
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Salva impostazioni', 'mecspe-trucks' ) ); ?>
			</form>

			<?php /* ---- TEST API BULK ---- */ ?>
			<hr>
			<h2><?php esc_html_e( 'Test connessione API bulk', 'mecspe-trucks' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mecspe_trucks_test_api" />
				<?php wp_nonce_field( 'mecspe_trucks_test_api_nonce' ); ?>
				<?php submit_button( __( 'Testa API ora', 'mecspe-trucks' ), 'secondary' ); ?>
			</form>

			<?php if ( false !== $test_result ) : ?>
				<div style="margin-top:16px; padding:16px; background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px;">
					<strong><?php esc_html_e( 'Risultato test:', 'mecspe-trucks' ); ?></strong>
					<?php if ( isset( $test_result['error'] ) ) : ?>
						<p style="color:#d63638;"><?php echo esc_html( $test_result['error'] ); ?></p>
					<?php else : ?>
						<p style="color:#00a32a;">
							<?php printf( esc_html__( 'Connessione OK. Trovati %d veicoli.', 'mecspe-trucks' ), (int) $test_result['total'] ); ?>
						</p>
						<?php if ( ! empty( $test_result['campi_mancanti'] ) ) : ?>
							<p style="color:#dba617;">
								<strong><?php esc_html_e( 'Campi attesi ma non presenti nella risposta:', 'mecspe-trucks' ); ?></strong><br>
								<code><?php echo esc_html( implode( ', ', $test_result['campi_mancanti'] ) ); ?></code>
							</p>
						<?php endif; ?>
						<?php if ( ! empty( $test_result['campi_extra'] ) ) : ?>
							<p style="color:#2271b1;">
								<strong><?php esc_html_e( 'Campi ricevuti non mappati:', 'mecspe-trucks' ); ?></strong><br>
								<code><?php echo esc_html( implode( ', ', $test_result['campi_extra'] ) ); ?></code>
							</p>
						<?php endif; ?>
						<details style="margin-top:8px;">
							<summary style="cursor:pointer;"><?php esc_html_e( 'Mostra JSON raw (primo record)', 'mecspe-trucks' ); ?></summary>
							<pre style="overflow:auto; max-height:400px; background:#fff; padding:12px; margin-top:8px; border:1px solid #ddd;"><?php echo esc_html( $test_result['sample'] ); ?></pre>
						</details>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php /* ---- SYNC MANUALE BULK ---- */ ?>
			<hr>
			<h2><?php esc_html_e( 'Sincronizzazione manuale bulk', 'mecspe-trucks' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mecspe_trucks_sync" />
				<?php wp_nonce_field( 'mecspe_trucks_sync_nonce' ); ?>
				<?php submit_button( __( 'Avvia sync ora', 'mecspe-trucks' ), 'secondary' ); ?>
			</form>

			<?php /* ---- MAPPA CAMPI ---- */ ?>
			<hr>
			<h2><?php esc_html_e( 'Mappa campi XML → ACF', 'mecspe-trucks' ); ?></h2>
			<p><?php esc_html_e( 'I tag XML inviati dal gestionale devono avere questi nomi (colonna sinistra). Modificabili in class-gestionale-connector.php.', 'mecspe-trucks' ); ?></p>
			<table class="widefat striped" style="max-width:600px">
				<thead><tr><th><?php esc_html_e( 'Tag XML gestionale', 'mecspe-trucks' ); ?></th><th><?php esc_html_e( 'Campo ACF WordPress', 'mecspe-trucks' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( Mecspe_Gestionale_Connector::field_map() as $json_key => $acf_key ) : ?>
						<tr><td><code><?php echo esc_html( $json_key ); ?></code></td><td><code><?php echo esc_html( $acf_key ); ?></code></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_manual_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'mecspe-trucks' ) );
		}
		check_admin_referer( 'mecspe_trucks_sync_nonce' );
		Mecspe_Truck_Importer::run_sync();
		wp_redirect( add_query_arg(
			[ 'page' => 'mecspe-trucks-settings', 'synced' => '1' ],
			admin_url( 'edit.php?post_type=prodotti' )
		) );
		exit;
	}

	public function handle_test_api() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'mecspe-trucks' ) );
		}
		check_admin_referer( 'mecspe_trucks_test_api_nonce' );

		$trucks = Mecspe_Gestionale_Connector::fetch_trucks();

		if ( is_wp_error( $trucks ) ) {
			set_transient( 'mecspe_trucks_api_test', [ 'error' => $trucks->get_error_message() ], 5 * MINUTE_IN_SECONDS );
		} else {
			$field_map      = array_keys( Mecspe_Gestionale_Connector::field_map() );
			$first          = $trucks[0] ?? [];
			$campi_ricevuti = array_keys( $first );
			set_transient( 'mecspe_trucks_api_test', [
				'total'          => count( $trucks ),
				'sample'         => wp_json_encode( $first, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
				'campi_mancanti' => array_values( array_diff( $field_map, $campi_ricevuti ) ),
				'campi_extra'    => array_values( array_diff( $campi_ricevuti, $field_map ) ),
			], 5 * MINUTE_IN_SECONDS );
		}

		wp_redirect( add_query_arg(
			[ 'page' => 'mecspe-trucks-settings' ],
			admin_url( 'edit.php?post_type=prodotti' )
		) );
		exit;
	}

	public function show_notices() {
		if ( isset( $_GET['synced'] ) && '1' === $_GET['synced'] ) {
			$result = get_option( 'mecspe_trucks_last_result', '' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $result ) . '</p></div>';
		}
	}
}
