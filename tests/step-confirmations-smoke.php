<?php
/**
 * Step confirmation checklists smoke test.
 *
 * Run: wp eval-file wp-content/plugins/orderMachine/tests/step-confirmations-smoke.php
 *
 * @package OrderMachine
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

$fail = 0;
$out  = static function ( $label, $value ) {
	echo $label . ': ' . ( is_string( $value ) ? $value : wp_json_encode( $value ) ) . "\n";
};
$assert = static function ( $ok, $label ) use ( &$fail, $out ) {
	if ( $ok ) {
		$out( $label, 'PASS' );
		return;
	}
	++$fail;
	$out( $label, 'FAIL' );
};

global $wpdb;

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( class_exists( 'SOM_Step_Confirmations' ), 'class_SOM_Step_Confirmations' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.10.0', '>=' ), 'som_db_version_gte_1.10.0' );

$steps_t = SOM_DB::table( 'workflow_steps' );
$items_t = SOM_DB::table( 'order_items' );
$prog_t  = SOM_DB::table( 'order_step_progress' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$step_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$steps_t}", 0 );
$assert( in_array( 'confirmation_kind', $step_cols, true ), 'workflow_steps_confirmation_kind' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$item_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$items_t}", 0 );
$assert( in_array( 'external_listing_id', $item_cols, true ), 'order_items_external_listing_id' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$prog_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$prog_t}", 0 );
$assert( in_array( 'confirmation_state', $prog_cols, true ), 'order_step_progress_confirmation_state' );

$assert(
	null !== SOM_Step_Confirmations::marketplace_order_url( 'ebay', '12-34567-89012' ),
	'ebay_order_url'
);
$assert(
	null !== SOM_Step_Confirmations::marketplace_order_url( 'etsy', '1234567890' ),
	'etsy_order_url'
);
$assert(
	null !== SOM_Step_Confirmations::marketplace_listing_url( 'ebay', '111' ),
	'ebay_listing_url'
);
$assert(
	null !== SOM_Step_Confirmations::marketplace_listing_url( 'etsy', '222' ),
	'etsy_listing_url'
);

// --- Template with confirmation steps ---
$wf = SOM_Workflows::create(
	array(
		'name'      => 'Confirm smoke ' . wp_generate_password( 4, false, false ),
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf ), 'create_workflow' );
$wf_id = (int) $wf;

$combo_reject = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'name'                    => 'Bad combo',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'print_vs_request',
			'timer_value'             => 5,
			'timer_unit'              => 'minutes',
		),
	)
);
$assert( is_wp_error( $combo_reject ), 'reject_confirm_plus_timer' );

$saved = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'name'                    => 'Confirm print',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'print_vs_request',
		),
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'packing_items',
		),
		array(
			'name'                    => 'Confirm address',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'shipping_address',
		),
		array(
			'name'                    => 'Done manual',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( true === $saved, 'save_confirmation_steps' );

$steps = SOM_Workflows::get_steps( $wf_id );
$assert( 4 === count( $steps ), 'step_count_4' );
$assert( 'print_vs_request' === (string) $steps[0]->confirmation_kind, 'kind_print' );
$assert( 'packing_items' === (string) $steps[1]->confirmation_kind, 'kind_pack' );
$assert( 'shipping_address' === (string) $steps[2]->confirmation_kind, 'kind_address' );

$product = SOM_Products::create(
	array(
		'name'                  => 'Confirm smoke product ' . wp_generate_password( 4, false, false ),
		'sku'                   => 'CONF-SMOKE',
		'workflow_template_id'  => $wf_id,
		'is_active'             => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$external_id = 'confirm-smoke-' . wp_generate_password( 6, false, false );
$order_id    = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'ebay',
		'external_order_id' => $external_id,
		'buyer_name'        => 'Confirm Tester',
		'shipping_address'  => array(
			'full_name' => 'Confirm Tester',
			'line1'     => '1 Confirm Street',
			'city'      => 'London',
			'postcode'  => 'E1 1AA',
			'country'   => 'GB',
		),
		'items'             => array(
			array(
				'product_id'           => $product_id,
				'quantity'             => 2,
				'personalisation_text' => 'Recycling',
				'unit_price'           => 4.5,
				'external_listing_id'  => 'listing-confirm-1',
			),
			array(
				'product_id'           => $product_id,
				'quantity'             => 1,
				'personalisation_text' => 'General',
				'unit_price'           => 3.0,
				'external_listing_id'  => 'listing-confirm-2',
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$order = SOM_Orders::get( $order_id );
$assert( $order && ! empty( $order->items ), 'order_loaded' );
$assert( 2 === count( $order->items ), 'two_line_items' );
$assert( 'listing-confirm-1' === (string) $order->items[0]->external_listing_id, 'listing_id_persisted' );

$progress = SOM_Workflow_Engine::get_progress( $order_id );
$assert( ! empty( $progress ), 'progress_rows' );
$current = null;
foreach ( $progress as $row ) {
	if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
		$current = $row;
		break;
	}
}
$assert( $current && 'print_vs_request' === (string) $current->confirmation_kind, 'current_is_print_confirm' );
$assert( 'in_progress' === (string) $current->status, 'current_in_progress' );

$step_obj = (object) array(
	'name'                    => $current->step_name,
	'timer_seconds'           => $current->timer_seconds,
	'requires_manual_confirm' => $current->requires_manual_confirm,
	'script_config'           => $current->script_config,
	'batch_group_id'          => $current->batch_group_id,
	'confirmation_kind'       => $current->confirmation_kind,
);
$assert( ! SOM_Workflow_Engine::can_mark_done( $current, $step_obj ), 'blocked_before_ticks' );

$blocked = SOM_Workflow_Engine::mark_done( $order_id );
$assert( is_wp_error( $blocked ) && 'som_confirmation_required' === $blocked->get_error_code(), 'mark_done_requires_confirm' );

$saved_ticks = SOM_Step_Confirmations::save_for_order(
	$order_id,
	array( 'print_matches_request' => 1 )
);
$assert( true === $saved_ticks, 'save_print_ticks' );

$order   = SOM_Orders::get( $order_id );
$current = null;
foreach ( $order->workflow_progress as $row ) {
	if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
		$current = $row;
		break;
	}
}
$step_obj->confirmation_kind = $current->confirmation_kind;
$assert( SOM_Workflow_Engine::can_mark_done( $current, $step_obj ), 'can_mark_after_print_ticks' );

$advanced = SOM_Workflow_Engine::mark_done( $order_id );
$assert( true === $advanced, 'advance_print_confirm' );

$order = SOM_Orders::get( $order_id );
$assert( 'Confirm pack' === (string) $order->current_step_name, 'now_on_pack_confirm' );

// Partial packing ticks — one of two.
$item_a = (string) (int) $order->items[0]->id;
$item_b = (string) (int) $order->items[1]->id;
SOM_Step_Confirmations::save_for_order(
	$order_id,
	array(
		'items' => array(
			$item_a => 1,
		),
	)
);
$order   = SOM_Orders::get( $order_id );
$current = null;
foreach ( $order->workflow_progress as $row ) {
	if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
		$current = $row;
		break;
	}
}
$step_obj = (object) array(
	'name'                    => $current->step_name,
	'timer_seconds'           => $current->timer_seconds,
	'requires_manual_confirm' => $current->requires_manual_confirm,
	'script_config'           => $current->script_config,
	'batch_group_id'          => $current->batch_group_id,
	'confirmation_kind'       => $current->confirmation_kind,
);
$assert( ! SOM_Workflow_Engine::can_mark_done( $current, $step_obj ), 'blocked_partial_pack' );

SOM_Step_Confirmations::save_for_order(
	$order_id,
	array(
		'items' => array(
			$item_a => 1,
			$item_b => 1,
		),
	)
);
$order   = SOM_Orders::get( $order_id );
$current = null;
foreach ( $order->workflow_progress as $row ) {
	if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
		$current = $row;
		break;
	}
}
$step_obj->confirmation_kind = $current->confirmation_kind;
$assert( SOM_Workflow_Engine::can_mark_done( $current, $step_obj ), 'can_mark_full_pack' );
$assert( true === SOM_Workflow_Engine::mark_done( $order_id ), 'advance_pack_confirm' );

$order = SOM_Orders::get( $order_id );
$assert( 'Confirm address' === (string) $order->current_step_name, 'now_on_address_confirm' );

SOM_Step_Confirmations::save_for_order(
	$order_id,
	array( 'address_matches_marketplace' => 1 )
);
$assert( true === SOM_Workflow_Engine::mark_done( $order_id ), 'advance_address_confirm' );

$order = SOM_Orders::get( $order_id );
$assert( 'Done manual' === (string) $order->current_step_name, 'now_on_done_manual' );

// Cleanup best-effort.
$wpdb->delete( SOM_DB::table( 'order_step_progress' ), array( 'order_id' => $order_id ) );
$wpdb->delete( SOM_DB::table( 'order_items' ), array( 'order_id' => $order_id ) );
$wpdb->delete( SOM_DB::table( 'orders' ), array( 'id' => $order_id ) );
foreach ( SOM_Workflows::get_steps( $wf_id ) as $s ) {
	$wpdb->delete( SOM_DB::table( 'workflow_steps' ), array( 'id' => (int) $s->id ) );
}
$wpdb->delete( SOM_DB::table( 'products' ), array( 'id' => $product_id ) );
$wpdb->delete( SOM_DB::table( 'workflow_templates' ), array( 'id' => $wf_id ) );

if ( $fail > 0 ) {
	echo "RESULT: FAIL ({$fail})\n";
	exit( 1 );
}

echo "RESULT: PASS\n";
