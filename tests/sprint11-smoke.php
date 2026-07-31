<?php
/**
 * Sprint 11 smoke: external POST /orders, advance-step, MCP abilities toggle.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint11-smoke.php
 *
 * @package OrderMachine
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

$out = static function ( $label, $value ) {
	echo $label . ': ' . ( is_string( $value ) ? $value : wp_json_encode( $value ) ) . "\n";
};

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();
SOM_Seed::maybe_seed_catalogue();

$out( 'plugin', SOM_VERSION );

$settings = SOM_Settings::get();
if ( '' === (string) $settings['api_key'] ) {
	SOM_Settings::update( array( 'api_key' => 'sprint11-test-key' ) );
	$settings = SOM_Settings::get();
}
$api_key = (string) $settings['api_key'];
$out( 'api_key_set', '' !== $api_key ? 'yes' : 'no' );

$ext = SOM_Channels::get_by_slug( 'external' );
$out( 'external_channel', $ext ? ( 'id=' . (int) $ext->id . ' active=' . (int) $ext->is_active ) : 'MISSING' );

$product_id = (int) $GLOBALS['wpdb']->get_var(
	'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_active = 1 ORDER BY id ASC LIMIT 1'
);
$out( 'seed_product_id', $product_id );

$external_id = 'ext-smoke-' . gmdate( 'YmdHis' );
$req         = new WP_REST_Request( 'POST', '/som/v1/orders' );
$req->set_header( 'x-som-api-key', $api_key );
$req->set_header( 'content-type', 'application/json' );
$req->set_body(
	wp_json_encode(
		array(
			'external_order_id' => $external_id,
			'buyer_name'        => 'Sprint 11 Buyer',
			'shipping_address'  => array(
				'line1'    => '1 Test Street',
				'city'     => 'London',
				'postcode' => 'E1 1AA',
				'country'  => 'GB',
			),
			'items'             => array(
				array(
					'product_id'           => $product_id,
					'quantity'             => 1,
					'personalisation_text' => 'Hello Sprint 11',
				),
			),
		)
	)
);

$create = rest_do_request( $req );
$cdata  = $create->get_data();
$out( 'create_status', (string) $create->get_status() );
$out( 'create_ok', ! empty( $cdata['ok'] ) ? '1' : '0' );
$order_id = isset( $cdata['order_id'] ) ? (int) $cdata['order_id'] : 0;
$out( 'order_id', $order_id );

if ( $order_id < 1 ) {
	echo "FAIL: create order\n";
	exit( 1 );
}

$dup = new WP_REST_Request( 'POST', '/som/v1/orders' );
$dup->set_header( 'x-som-api-key', $api_key );
$dup->set_header( 'content-type', 'application/json' );
$dup->set_body(
	wp_json_encode(
		array(
			'external_order_id' => $external_id,
			'items'             => array(
				array( 'product_id' => $product_id, 'quantity' => 1 ),
			),
		)
	)
);
$dup_res = rest_do_request( $dup );
$out( 'duplicate_status', (string) $dup_res->get_status() );

$order = SOM_Orders::get( $order_id );
$out( 'after_create_step', (string) $order->current_step_name );
$out( 'workflow_rows', (string) count( $order->workflow_progress ) );

$adv = new WP_REST_Request( 'POST', '/som/v1/orders/' . $order_id . '/advance-step' );
$adv->set_header( 'x-som-api-key', $api_key );
$adv_res = rest_do_request( $adv );
$adata   = $adv_res->get_data();
$out( 'advance_status', (string) $adv_res->get_status() );
$out( 'advance_ok', ! empty( $adata['ok'] ) ? '1' : '0' );
$out( 'after_advance_step', isset( $adata['current_step_name'] ) ? (string) $adata['current_step_name'] : '' );

// MCP toggle is read at registration time (plugins_loaded / abilities hooks).
SOM_Settings::update( array( 'mcp_enabled' => false ) );
$out( 'mcp_enabled_off', SOM_Abilities::is_enabled() ? 'yes' : 'no' );

SOM_Settings::update( array( 'mcp_enabled' => true ) );
$out( 'mcp_enabled_on', SOM_Abilities::is_enabled() ? 'yes' : 'no' );

// If MCP was already on when this request started, abilities are registered.
$has_on = false;
if ( function_exists( 'wp_get_ability' ) ) {
	$ability = wp_get_ability( 'order-machine/get-orders' );
	$has_on  = ( null !== $ability && false !== $ability );
}
$out( 'ability_registered_this_request', $has_on ? 'yes' : 'no (enable MCP then re-run for registry check)' );

wp_set_current_user( 1 );
$listed = SOM_Abilities::get_orders( array( 'channel' => 'external', 's' => $external_id ) );
$out( 'ability_get_orders_count', (string) count( $listed['orders'] ) );

$detail = SOM_Abilities::get_order_detail( array( 'order_id' => $order_id ) );
$out( 'ability_detail_buyer', is_wp_error( $detail ) ? $detail->get_error_message() : (string) $detail['buyer_name'] );

$mcp_plugin = class_exists( 'WP\MCP\Core\McpAdapter' );
$out( 'mcp_adapter', $mcp_plugin ? 'present' : 'not_active_yet' );

$fail = false;
if ( 409 !== (int) $dup_res->get_status() ) {
	echo "FAIL: expected 409 on duplicate\n";
	$fail = true;
}
if ( empty( $adata['ok'] ) ) {
	echo "FAIL: advance-step\n";
	$fail = true;
}
if ( true !== SOM_Abilities::is_enabled() ) {
	echo "FAIL: mcp_enabled should be true after update\n";
	$fail = true;
}
if ( is_wp_error( $detail ) || empty( $listed['orders'] ) ) {
	echo "FAIL: ability execute callbacks\n";
	$fail = true;
}
if ( ! $mcp_plugin ) {
	echo "FAIL: MCP Adapter plugin/class not present in wp-env\n";
	$fail = true;
}

echo $fail ? "FAIL\n" : "PASS\n";
exit( $fail ? 1 : 0 );
