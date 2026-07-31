<?php
/**
 * Sprint 9 smoke checks (run via: wp eval-file tests/sprint9-smoke.php).
 *
 * @package OrderMachine
 */

echo 'version=' . SOM_VERSION . PHP_EOL;
echo 'local=' . ( class_exists( 'SOM_Local_Actions' ) ? '1' : '0' ) . PHP_EOL;
echo 'dispatch=' . ( class_exists( 'SOM_Script_Dispatch' ) ? '1' : '0' ) . PHP_EOL;
echo 'rest=' . ( class_exists( 'SOM_REST_API' ) ? '1' : '0' ) . PHP_EOL;

$routes = rest_get_server()->get_routes();
$found  = false;
foreach ( array_keys( $routes ) as $route ) {
	if ( false !== strpos( $route, 'workflow-callback' ) ) {
		$found = true;
		echo 'route=' . $route . PHP_EOL;
	}
}
echo 'route_ok=' . ( $found ? '1' : '0' ) . PHP_EOL;

// Print stub.
$print = SOM_Local_Actions::run( 'send_print_job', array(), (object) array( 'id' => 0 ) );
echo 'print_stub=' . ( is_wp_error( $print ) ? $print->get_error_code() : 'unexpected_ok' ) . PHP_EOL;

// Thank-you without Python should fail clearly.
SOM_Settings::update( array( 'api_key' => 'test-sprint9-key-abc123xyz' ) );
$settings = SOM_Settings::get();
echo 'api_key_set=' . ( '' !== $settings['api_key'] ? '1' : '0' ) . PHP_EOL;

// Ensure catalogue + one matched order with workflow.
SOM_Seed::maybe_seed_catalogue();

global $wpdb;
$orders_t = SOM_DB::table( 'orders' );
$wpdb->query( "DELETE FROM " . SOM_DB::table( 'material_stock_log' ) );
$wpdb->query( "DELETE FROM " . SOM_DB::table( 'order_step_progress' ) );
$wpdb->query( "DELETE FROM " . SOM_DB::table( 'order_items' ) );
$wpdb->query( "DELETE FROM {$orders_t}" );

$result = SOM_Order_Sync::sync_incremental();
echo 'sync_created=' . (int) $result['created'] . PHP_EOL;

// Find an order at Thank-you or with progress.
$order_id = (int) $wpdb->get_var(
	"SELECT o.id FROM {$orders_t} o
	INNER JOIN " . SOM_DB::table( 'order_step_progress' ) . " p ON p.order_id = o.id
	WHERE o.current_step_id IS NOT NULL
	LIMIT 1"
);
echo 'sample_order=' . $order_id . PHP_EOL;

if ( $order_id < 1 ) {
	echo 'FAIL no assigned order' . PHP_EOL;
	return;
}

// Advance until Thank-you (script step) or error — skip timers by forcing unlock.
$guard = 0;
while ( $guard < 20 ) {
	++$guard;
	$order = SOM_Orders::get( $order_id );
	if ( ! $order || ! empty( $order->is_complete ) ) {
		echo 'reached_complete=1' . PHP_EOL;
		break;
	}
	$step_id  = (int) $order->current_step_id;
	$progress = null;
	foreach ( $order->workflow_progress as $row ) {
		if ( (int) $row->workflow_step_id === $step_id ) {
			$progress = $row;
			break;
		}
	}
	if ( ! $progress ) {
		echo 'FAIL no progress' . PHP_EOL;
		break;
	}

	echo 'step=' . $progress->step_name . ' status=' . $progress->status . PHP_EOL;

	if ( 'waiting_script' === $progress->status || 'error' === $progress->status ) {
		echo 'script_gate_hit=1 last_error=' . substr( (string) $progress->last_error, 0, 120 ) . PHP_EOL;
		$retry = SOM_Workflow_Engine::retry_script( $order_id );
		$order = SOM_Orders::get( $order_id );
		foreach ( $order->workflow_progress as $row ) {
			if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
				echo 'after_retry status=' . $row->status . ' retries=' . $row->retry_count . PHP_EOL;
				echo 'after_retry_error=' . substr( (string) $row->last_error, 0, 160 ) . PHP_EOL;
				break;
			}
		}
		break;
	}

	if ( 'waiting_timer' === $progress->status ) {
		$wpdb->update(
			SOM_DB::table( 'order_step_progress' ),
			array( 'timer_ends_at' => gmdate( 'Y-m-d H:i:s', time() - 10 ) ),
			array( 'id' => (int) $progress->id ),
			array( '%s' ),
			array( '%d' )
		);
		SOM_Workflow_Engine::tick();
		continue;
	}

	if ( 'in_progress' === $progress->status ) {
		$r = SOM_Workflow_Engine::mark_done( $order_id );
		if ( is_wp_error( $r ) ) {
			echo 'mark_done_err=' . $r->get_error_message() . PHP_EOL;
			break;
		}
		continue;
	}

	echo 'unexpected_status=' . $progress->status . PHP_EOL;
	break;
}

// Callback token round-trip (simulate async).
$token = SOM_Script_Dispatch::issue_callback_token( $order_id, 1 );
$bind  = SOM_Script_Dispatch::resolve_callback_token( $token );
echo 'callback_token_ok=' . ( is_array( $bind ) && (int) $bind['order_id'] === $order_id ? '1' : '0' ) . PHP_EOL;

// Placeholder map.
$order = SOM_Orders::get( $order_id );
$map   = SOM_Script_Dispatch::placeholder_map( $order );
echo 'placeholder_buyer=' . ( isset( $map['buyer_name'] ) && '' !== $map['buyer_name'] ? '1' : '0' ) . PHP_EOL;

echo 'DONE' . PHP_EOL;
