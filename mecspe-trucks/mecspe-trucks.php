<?php
/**
 * Plugin Name: Mecspe Trucks
 * Plugin URI:  https://mecspe.com
 * Description: Sincronizza i veicoli dal gestionale e mostra una pagina con filtri dinamici.
 * Version:     1.0.0
 * Author:      Mecspe
 * Text Domain: mecspe-trucks
 */

defined( 'ABSPATH' ) || exit;

define( 'MECSPE_TRUCKS_VERSION', '1.0.0' );
define( 'MECSPE_TRUCKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MECSPE_TRUCKS_URL', plugin_dir_url( __FILE__ ) );

require_once MECSPE_TRUCKS_PATH . 'includes/class-admin.php';
require_once MECSPE_TRUCKS_PATH . 'includes/class-gestionale-connector.php';
require_once MECSPE_TRUCKS_PATH . 'includes/class-truck-importer.php';
require_once MECSPE_TRUCKS_PATH . 'includes/class-trucks-page.php';

new Mecspe_Trucks_Admin();
new Mecspe_Trucks_Page();

// Cron: sync giornaliero
add_action( 'mecspe_trucks_sync_event', [ 'Mecspe_Truck_Importer', 'run_sync' ] );

register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( 'mecspe_trucks_sync_event' ) ) {
		wp_schedule_event( time(), 'daily', 'mecspe_trucks_sync_event' );
	}
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'mecspe_trucks_sync_event' );
} );
