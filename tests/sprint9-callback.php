<?php
/**
 * Sprint 9 callback + API sync smoke.
 *
 * @package OrderMachine
 */

global $wpdb;

$order_id = (int) $wpdb->get_var(
	'SELECT id FROM ' . SOM_DB::table( 'orders' ) . ' WHERE current_step_id IS NOT NULL ORDER BY id DESC LIMIT 1'
);
if ( $order_id < 1 ) {
	echo "no_order\n";
	return;
}

$order = SOM_Orders::get( $order_id );
$prog  = null;
foreach ( $order->workflow_progress as $row ) {
	if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
		$prog = $row;
		break;
	}
}
if ( ! $prog ) {
	echo "no_progress\n";
	return;
}

// Force waiting_script + async marker, then succeed via callback.
$wpdb->update(
	SOM_DB::table( 'order_step_progress' ),
	array(
		'status'     => 'waiting_script',
		'last_error' => 'waiting_callback:pending',
	),
	array( 'id' => (int) $prog->id ),
	array( '%s', '%s' ),
	array( '%d' )
);

$token = SOM_Script_Dispatch::issue_callback_token( $order_id, (int) $prog->id );
$req   = new WP_REST_Request( 'POST', '/som/v1/workflow-callback/' . $token );
$req->set_header( 'x-som-api-key', SOM_Settings::get()['api_key'] );
$req->set_body( wp_json_encode( array( 'success' => true ) ) );
$req->set_header( 'content-type', 'application/json' );

$response = rest_do_request( $req );
$data     = $response->get_data();
echo 'callback_status=' . $response->get_status() . PHP_EOL;
echo 'callback_ok=' . ( ! empty( $data['ok'] ) ? '1' : '0' ) . PHP_EOL;

$order = SOM_Orders::get( $order_id );
echo 'after_callback_step=' . (string) $order->current_step_name . PHP_EOL;
echo 'is_complete=' . (int) $order->is_complete . PHP_EOL;

// API type sync against example.com (expect failure or success — just ensure dispatcher runs).
$fake_step = (object) array(
	'script_config' => wp_json_encode(
		array(
			'type'          => 'api',
			'method'        => 'GET',
			'url'           => 'https://example.com/',
			'body_template' => array(),
		)
	),
);
$fake_progress = (object) array( 'id' => 999999, 'order_id' => $order_id );
$api_result    = SOM_Script_Dispatch::execute( $order, $fake_step, $fake_progress );
echo 'api_sync=' . ( true === $api_result ? 'ok' : ( is_wp_error( $api_result ) ? $api_result->get_error_code() : 'other' ) ) . PHP_EOL;

echo 'DONE' . PHP_EOL;
