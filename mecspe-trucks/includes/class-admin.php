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
		$last_sync   = get_option( 'mecspe_trucks_last_sync' );
		$last_result = get_option( 'mecspe_trucks_last_result' );
		$test_result = get_transient( 'mecspe_trucks_api_test' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mecspe Trucks – Sync Gestionale', 'mecspe-trucks' ); ?></h1>

			<?php if ( $last_sync ) : ?>
				<p>
					<?php printf(
						esc_html__( 'Ultima sincronizzazione: %1$s — %2$s', 'mecspe-trucks' ),
						esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $last_sync ) ) ),
						esc_html( $last_result )
					); ?>
				</p>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'mecspe_trucks_settings' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'URL API gestionale', 'mecspe-trucks' ); ?></th>
						<td>
							<input type="url" name="mecspe_trucks_api_url"
								value="<?php echo esc_attr( get_option( 'mecspe_trucks_api_url' ) ); ?>"
								class="regular-text" placeholder="https://gestionale.esempio.it/api/trucks" />
							<p class="description"><?php esc_html_e( 'Endpoint REST del gestionale che restituisce l\'elenco dei veicoli in JSON.', 'mecspe-trucks' ); ?></p>
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
						<th scope="row"><?php esc_html_e( 'Utente (facoltativo)', 'mecspe-trucks' ); ?></th>
						<td>
							<input type="text" name="mecspe_trucks_api_user"
								value="<?php echo esc_attr( get_option( 'mecspe_trucks_api_user' ) ); ?>"
								class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Password (facoltativa)', 'mecspe-trucks' ); ?></th>
						<td>
							<input type="password" name="mecspe_trucks_api_password"
								value="<?php echo esc_attr( get_option( 'mecspe_trucks_api_password' ) ); ?>"
								class="regular-text" />
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Salva impostazioni', 'mecspe-trucks' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Test connessione API', 'mecspe-trucks' ); ?></h2>
			<p><?php esc_html_e( 'Verifica che WordPress riesca a connettersi al gestionale e mostra la risposta raw (primi 2 record).', 'mecspe-trucks' ); ?></p>
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
							<?php printf(
								esc_html__( 'Connessione OK. Trovati %d veicoli nel gestionale.', 'mecspe-trucks' ),
								(int) $test_result['total']
							); ?>
						</p>
						<?php if ( ! empty( $test_result['campi_mancanti'] ) ) : ?>
							<p style="color:#dba617;">
								<strong><?php esc_html_e( 'Attenzione — campi attesi ma NON presenti nella risposta del gestionale:', 'mecspe-trucks' ); ?></strong><br>
								<code><?php echo esc_html( implode( ', ', $test_result['campi_mancanti'] ) ); ?></code><br>
								<?php esc_html_e( 'Verifica i nomi dei campi nella mappa (class-gestionale-connector.php).', 'mecspe-trucks' ); ?>
							</p>
						<?php endif; ?>
						<?php if ( ! empty( $test_result['campi_extra'] ) ) : ?>
							<p style="color:#2271b1;">
								<strong><?php esc_html_e( 'Campi ricevuti dal gestionale ma NON mappati:', 'mecspe-trucks' ); ?></strong><br>
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

			<hr>
			<h2><?php esc_html_e( 'Sincronizzazione manuale', 'mecspe-trucks' ); ?></h2>
			<p><?php esc_html_e( 'Avvia subito la sincronizzazione dal gestionale.', 'mecspe-trucks' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mecspe_trucks_sync" />
				<?php wp_nonce_field( 'mecspe_trucks_sync_nonce' ); ?>
				<?php submit_button( __( 'Avvia sync ora', 'mecspe-trucks' ), 'secondary' ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Mappa campi JSON → ACF', 'mecspe-trucks' ); ?></h2>
			<p><?php esc_html_e( 'Il connettore si aspetta che il gestionale restituisca un array JSON con questi campi (personalizzabili in class-gestionale-connector.php):', 'mecspe-trucks' ); ?></p>
			<table class="widefat striped" style="max-width:600px">
				<thead><tr><th>Campo JSON</th><th>ACF WordPress</th></tr></thead>
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
