<?php
/**
 * UP4-S2 smoke: order material overuse + COGS + Extra material usage funding.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up4-s2-smoke.php
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
SOM_Seed::maybe_seed_catalogue();

$out( 'plugin', SOM_VERSION );
$assert( version_compare( SOM_VERSION, '0.25.0', '>=' ), 'SOM_VERSION_gte_0.25.0' );

$product_id = (int) $wpdb->get_var(
	'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_active = 1 AND workflow_template_id IS NOT NULL ORDER BY id ASC LIMIT 1'
);
$assert( $product_id > 0, 'has_seed_product' );

$recipe = SOM_Products::get_recipe( $product_id );
$assert( ! empty( $recipe ), 'product_has_recipe' );
$material_id = ! empty( $recipe[0] ) ? (int) $recipe[0]->material_id : 0;
$assert( $material_id > 0, 'recipe_has_material' );

// Ensure a material budget exists for funding check.
$budget = SOM_Budgets::get_for_material( $material_id, true );
if ( ! $budget ) {
	$created = SOM_Budgets::create(
		array(
			'name'           => 'UP4-S2 Vinyl pot',
			'type'           => 'material',
			'material_id'    => $material_id,
			'funding_method' => 'material_cost',
			'is_active'      => 1,
		)
	);
	$assert( ! is_wp_error( $created ), 'create_material_budget' );
	$budget = SOM_Budgets::get( (int) $created );
} else {
	$assert( true, 'material_budget_exists' );
}

$stock_before = (float) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT current_stock FROM ' . SOM_DB::table( 'materials' ) . ' WHERE id = %d',
		$material_id
	)
);
$balance_before = $budget ? (float) $budget->current_balance : 0.0;

$external_id = 'up4s2-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
$order_id    = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $external_id,
		'buyer_name'        => 'UP4-S2 Tester',
		'shipping_address'  => array(
			'line1'    => '1 Test Street',
			'city'     => 'London',
			'postcode' => 'E1 1AA',
			'country'  => 'GB',
		),
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
				'unit_price' => 10,
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$usage = SOM_Material_Stock::get_usage_by_material( $order_id );
$assert( isset( $usage[ $material_id ] ), 'usage_has_material' );
$planned = isset( $usage[ $material_id ] ) ? (float) $usage[ $material_id ]->planned : 0.0;
$assert( $planned > 0, 'planned_gt_zero' );

$cogs_planned = SOM_Analytics::order_material_cogs( $order_id );
$assert( $cogs_planned > 0, 'cogs_after_create' );

$below = SOM_Material_Stock::apply_overuse(
	$order_id,
	array( $material_id => $planned - 0.5 )
);
$assert( is_wp_error( $below ), 'rejects_below_planned' );

$extra_qty = 1.5;
$apply     = SOM_Material_Stock::apply_overuse(
	$order_id,
	array( $material_id => $planned + $extra_qty )
);
$assert( ! is_wp_error( $apply ), 'apply_overuse_ok' );

$usage2 = SOM_Material_Stock::get_usage_by_material( $order_id );
$assert(
	isset( $usage2[ $material_id ] ) && abs( (float) $usage2[ $material_id ]->extra - $extra_qty ) < 0.001,
	'extra_recorded'
);
$assert(
	isset( $usage2[ $material_id ] ) && abs( (float) $usage2[ $material_id ]->actual - ( $planned + $extra_qty ) ) < 0.001,
	'actual_updated'
);

$decrease = SOM_Material_Stock::apply_overuse(
	$order_id,
	array( $material_id => $planned + 0.5 )
);
$assert( is_wp_error( $decrease ), 'rejects_decrease' );

$stock_after = (float) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT current_stock FROM ' . SOM_DB::table( 'materials' ) . ' WHERE id = %d',
		$material_id
	)
);
$assert( $stock_after < $stock_before - $planned + 0.001, 'stock_decreased_for_extra' );

$cogs_after = SOM_Analytics::order_material_cogs( $order_id );
$assert( $cogs_after > $cogs_planned, 'cogs_includes_extra' );

$extra_funding = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'budget_ledger' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		SOM_Budgets::REASON_EXTRA_MATERIAL_USAGE
	)
);
$assert( $extra_funding > 0, 'extra_material_usage_ledger_row' );

$assert(
	__( 'Extra material usage', 'order-machine' ) === SOM_Budgets::reason_label( SOM_Budgets::REASON_EXTRA_MATERIAL_USAGE ),
	'budget_reason_label'
);
$assert(
	__( 'Extra material usage', 'order-machine' ) === SOM_Materials::reason_label( 'order_usage_extra' ),
	'stock_reason_label'
);

// History-style: order with no new_order logs.
$empty = SOM_Material_Stock::apply_overuse( 999999991, array( $material_id => 1 ) );
$assert( is_wp_error( $empty ), 'missing_order_errors' );

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'OK' );
$out( 'balance_before', (string) $balance_before );
$out( 'stock_before', (string) $stock_before );
$out( 'stock_after', (string) $stock_after );
$out( 'cogs_planned', (string) $cogs_planned );
$out( 'cogs_after', (string) $cogs_after );
