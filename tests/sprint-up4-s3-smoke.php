<?php
/**
 * UP4-S3 smoke: internal products Produce N → reserve inputs → complete → output stock.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up4-s3-smoke.php
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
SOM_Production::init();

$out( 'plugin', SOM_VERSION );
$out( 'db', SOM_DB::DB_VERSION );
$assert( version_compare( SOM_VERSION, '0.26.0', '>=' ), 'SOM_VERSION_gte_0.26.0' );
$assert( version_compare( SOM_DB::DB_VERSION, '1.12.0', '>=' ), 'DB_VERSION_gte_1.12.0' );

$internal = SOM_Channels::get_by_slug( 'internal' );
$assert( $internal && (int) $internal->is_active === 1, 'internal_channel_active' );

// Input material with stock + cost.
$input_id = SOM_Materials::create(
	array(
		'name'      => 'UP4-S3 Input Vinyl ' . wp_generate_password( 4, false, false ),
		'unit'      => 'sheet',
		'unit_cost' => 2.5,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $input_id ), 'create_input_material' );
$input_id = (int) $input_id;

$restock = SOM_Materials::adjust_stock(
	$input_id,
	50,
	array(
		'reason'            => 'restock',
		'unit_cost_at_time' => 2.5,
		'value_change'      => 125.0,
		'sync_unit_cost'    => true,
	)
);
$assert( ! is_wp_error( $restock ), 'restock_input' );

$budget = SOM_Budgets::get_for_material( $input_id, true );
if ( ! $budget ) {
	$budget_id = SOM_Budgets::create(
		array(
			'name'           => 'UP4-S3 Input pot',
			'type'           => 'material',
			'material_id'    => $input_id,
			'funding_method' => 'material_cost',
			'is_active'      => 1,
		)
	);
	$assert( ! is_wp_error( $budget_id ), 'create_input_budget' );
	$budget = SOM_Budgets::get( (int) $budget_id );
} else {
	$assert( true, 'input_budget_exists' );
}

// Zero-gate workflow so Produce N completes on assign (after input reservation).
$wf_id = SOM_Workflows::create(
	array(
		'name'        => 'UP4-S3 Produce ' . wp_generate_password( 4, false, false ),
		'description' => 'Smoke production workflow',
		'is_active'   => 1,
	)
);
$assert( ! is_wp_error( $wf_id ), 'create_workflow' );
$wf_id = (int) $wf_id;

$steps_ok = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'name'                    => 'Cut components',
			'requires_manual_confirm' => 0,
		),
	)
);
$assert( ! is_wp_error( $steps_ok ), 'save_workflow_steps' );

$product_id = SOM_Products::create(
	array(
		'name'                 => 'UP4-S3 Logo Sticker ' . wp_generate_password( 4, false, false ),
		'sku'                  => 'UP4S3-' . wp_generate_password( 4, false, false ),
		'workflow_template_id' => $wf_id,
		'is_internal'          => 1,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product_id ), 'create_internal_product' );
$product_id = (int) $product_id;

$product = SOM_Products::get( $product_id );
$assert( $product && (int) $product->is_internal === 1, 'product_is_internal' );
$assert( ! empty( $product->linked_material_id ), 'has_linked_material' );
$linked_id = (int) $product->linked_material_id;

$linked = SOM_Materials::get( $linked_id );
$assert( $linked && (int) $linked->source_product_id === $product_id, 'material_source_product' );

$recipe_ok = SOM_Products::save_recipe(
	$product_id,
	array(
		array(
			'material_id'       => $input_id,
			'quantity_per_unit' => 2,
		),
	)
);
$assert( ! is_wp_error( $recipe_ok ), 'save_recipe' );

$self_cycle = SOM_Products::save_recipe(
	$product_id,
	array(
		array(
			'material_id'       => $linked_id,
			'quantity_per_unit' => 1,
		),
	)
);
$assert( is_wp_error( $self_cycle ), 'rejects_self_output_in_recipe' );

// Restore valid recipe after self-cycle attempt.
SOM_Products::save_recipe(
	$product_id,
	array(
		array(
			'material_id'       => $input_id,
			'quantity_per_unit' => 2,
		),
	)
);

$not_internal = SOM_Production::produce( $product_id, 0 );
$assert( is_wp_error( $not_internal ), 'rejects_qty_zero' );

$input_stock_before  = (float) SOM_Materials::get( $input_id )->current_stock;
$linked_stock_before = (float) SOM_Materials::get( $linked_id )->current_stock;
$balance_before      = $budget ? (float) $budget->current_balance : 0.0;

$qty      = 3;
$order_id = SOM_Production::produce( $product_id, $qty );
$assert( ! is_wp_error( $order_id ), 'produce_ok' );
$order_id = (int) $order_id;

$order = SOM_Orders::get( $order_id );
$assert( $order && 'internal' === (string) $order->channel_slug, 'order_channel_internal' );
$assert( ! empty( $order->is_complete ), 'order_complete_after_zero_gate' );

$input_stock_after = (float) SOM_Materials::get( $input_id )->current_stock;
$assert(
	abs( ( $input_stock_before - ( 2 * $qty ) ) - $input_stock_after ) < 0.001,
	'input_stock_decremented'
);

$new_order_logs = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		'new_order'
	)
);
$assert( $new_order_logs > 0, 'input_new_order_logs' );

$funding = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'budget_ledger' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		'sale_funding'
	)
);
$assert( $funding > 0, 'input_budgets_funded' );

$output_logs = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		'production_output'
	)
);
$assert( $output_logs > 0, 'production_output_log' );

$linked_stock_after = (float) SOM_Materials::get( $linked_id )->current_stock;
$assert(
	abs( ( $linked_stock_before + $qty ) - $linked_stock_after ) < 0.001,
	'output_stock_credited'
);

$linked_after = SOM_Materials::get( $linked_id );
// Input cost: 2 sheets × 2.5 × 3 qty = 15 → unit = 5.
$assert( abs( (float) $linked_after->unit_cost - 5.0 ) < 0.001, 'output_unit_cost_from_inputs' );

$assert(
	__( 'Production output', 'order-machine' ) === SOM_Materials::reason_label( 'production_output' ),
	'production_output_label'
);

// Idempotent re-credit.
$again = SOM_Production::credit_output_for_order( $order_id );
$assert( ! is_wp_error( $again ), 'credit_idempotent_ok' );
$output_logs_2 = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		'production_output'
	)
);
$assert( 1 === $output_logs_2, 'credit_idempotent_single_log' );

$sellable_reject = SOM_Production::produce(
	(int) $wpdb->get_var(
		'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_internal = 0 AND is_active = 1 ORDER BY id ASC LIMIT 1'
	),
	1
);
// May be 0 if no sellable — still assert produce rejects non-internal when id found.
if ( is_wp_error( $sellable_reject ) ) {
	$assert( true, 'rejects_non_internal_or_missing' );
} else {
	$assert( false, 'rejects_non_internal_or_missing' );
}

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'OK' );
$out( 'order_id', (string) $order_id );
$out( 'linked_material_id', (string) $linked_id );
$out( 'input_stock_before', (string) $input_stock_before );
$out( 'input_stock_after', (string) $input_stock_after );
$out( 'linked_stock_after', (string) $linked_stock_after );
$out( 'balance_before', (string) $balance_before );
