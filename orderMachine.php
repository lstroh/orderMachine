<?php
/**
 * Plugin Name:       Order Machine
 * Description:       Aggregates eBay/Etsy orders, tracks production workflows, and manages material stock.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Order Machine
 * Text Domain:       order-machine
 * License:           GPL-2.0-or-later
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

define( 'SOM_VERSION', '0.1.0' );
define( 'SOM_PLUGIN_FILE', __FILE__ );
define( 'SOM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SOM_PLUGIN_DIR . 'includes/class-som-db.php';
require_once SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php';

/**
 * Plugin activation: create schema; do not seed data.
 *
 * @return void
 */
function som_activate() {
	SOM_DB::create_tables();
}

/**
 * Plugin deactivation: leave tables and options intact.
 *
 * @return void
 */
function som_deactivate() {
	// Intentionally empty — deactivation must not destroy order/stock data.
}

register_activation_hook( __FILE__, 'som_activate' );
register_deactivation_hook( __FILE__, 'som_deactivate' );

/**
 * Bootstrap admin and schema checks after plugins load.
 *
 * @return void
 */
function som_init() {
	SOM_DB::maybe_upgrade();

	if ( is_admin() ) {
		SOM_Admin_Menu::init();
	}
}
add_action( 'plugins_loaded', 'som_init' );
