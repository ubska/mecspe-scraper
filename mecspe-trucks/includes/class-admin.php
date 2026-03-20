<?php
defined( 'ABSPATH' ) || exit;

class Mecspe_Trucks_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_post_mecspe_trucks_sync', [ $this, 'handle_manual_sync' ] );
		add_action( 'admin_notices', [ $this, 'show_notices' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=prodotto',
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
		$last_sync = get_option( 'mecspe_trucks_last_sync' );
		$last_result = get_option( 'mecspe_trucks_last_result' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mecspe Trucks – Sync Gestionale', 'mecspe-trucks' ); ?></h1>

			<?php if ( $last_sync ) : ?>
				<p>
					<?php printf(
						/* translators: 1: date, 2: result */
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
			admin_url( 'edit.php?post_type=prodotto' )
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
