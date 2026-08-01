<?php
/**
 * Plugin Name:       Order Machine
 * Description:       Aggregates eBay/Etsy orders, tracks production workflows, and manages material stock.
 * Version:           0.17.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Order Machine
 * Text Domain:       order-machine
 * License:           GPL-2.0-or-later
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

define( 'SOM_VERSION', '0.17.0' );
define( 'SOM_PLUGIN_FILE', __FILE__ );
define( 'SOM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SOM_PLUGIN_DIR . 'includes/class-som-db.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-crypto.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-settings.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-channels.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-channel-ebay.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-channel-etsy.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-suppliers.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-purchase-orders.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-batch-groups.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-batches.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-materials.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-material-costing.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-workflow-material-goals.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-products.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-material-stock.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-workflows.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-local-actions.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-script-dispatch.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-workflow-engine.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-orders.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-order-sync.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-listings.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-rest-api.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-abilities.php';
require_once SOM_PLUGIN_DIR . 'includes/class-som-cron.php';
require_once SOM_PLUGIN_DIR . 'includes/seed/class-som-seed.php';
require_once SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php';

/**
 * Plugin activation: create schema, channel rows, schedule cron.
 *
 * @return void
 */
function som_activate() {
	SOM_DB::create_tables();
	SOM_Channels::ensure_rows();
	SOM_Batch_Groups::ensure_rows();
	SOM_Cron::init();
	SOM_Cron::schedule_events();
	SOM_Seed::maybe_load_dummy_credentials();
	SOM_Batch_Groups::convert_thankyou_steps();
}

/**
 * Plugin deactivation: clear cron; leave tables and options intact.
 *
 * @return void
 */
function som_deactivate() {
	SOM_Cron::clear_events();
}

register_activation_hook( __FILE__, 'som_activate' );
register_deactivation_hook( __FILE__, 'som_deactivate' );

/**
 * Bootstrap after plugins load.
 *
 * @return void
 */
function som_init() {
	SOM_DB::maybe_upgrade();
	SOM_Channels::ensure_rows();
	SOM_Batch_Groups::ensure_rows();
	SOM_Cron::init();
	SOM_REST_API::init();
	SOM_Abilities::init();
	SOM_Seed::maybe_load_dummy_credentials();
	SOM_Batch_Groups::convert_thankyou_steps();

	if ( is_admin() ) {
		SOM_Admin_Menu::init();
		add_action( 'admin_notices', 'som_admin_notices' );
	}
}
add_action( 'plugins_loaded', 'som_init' );

/**
 * Schedule cron after init so translation loading is valid.
 *
 * @return void
 */
function som_schedule_cron() {
	SOM_Cron::schedule_events();
}
add_action( 'init', 'som_schedule_cron' );

/**
 * Surface settings_errors stored across redirect.
 *
 * @return void
 */
function som_admin_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( ! in_array( $page, array( 'som-settings', 'som-orders', 'som-products', 'som-materials', 'som-suppliers', 'som-purchase-orders', 'som-batches', 'som-workflows', 'som-listings' ), true ) ) {
		return;
	}

	$stored = get_transient( 'som_admin_errors' );
	if ( is_array( $stored ) ) {
		foreach ( $stored as $error ) {
			if ( ! is_array( $error ) || empty( $error['code'] ) ) {
				continue;
			}
			add_settings_error(
				'som_admin',
				$error['code'],
				isset( $error['message'] ) ? $error['message'] : '',
				isset( $error['type'] ) ? $error['type'] : 'error'
			);
		}
		delete_transient( 'som_admin_errors' );
	}

	settings_errors( 'som_admin' );

	// Legacy settings transient (Sprint 2–4).
	if ( 'som-settings' !== $page ) {
		return;
	}

	$legacy = get_transient( 'som_settings_errors' );
	if ( is_array( $legacy ) ) {
		foreach ( $legacy as $error ) {
			if ( ! is_array( $error ) || empty( $error['code'] ) ) {
				continue;
			}
			add_settings_error(
				'som_settings',
				$error['code'],
				isset( $error['message'] ) ? $error['message'] : '',
				isset( $error['type'] ) ? $error['type'] : 'error'
			);
		}
		delete_transient( 'som_settings_errors' );
	}

	settings_errors( 'som_settings' );
}
