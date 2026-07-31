<?php
/**
 * Sprint U3 smoke: landed cost, WA, consumption value, goals, preview parity.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u3-smoke.php
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
$approx = static function ( $a, $b, $eps = 0.00015 ) {
	return abs( (float) $a - (float) $b ) < $eps;
};

global $wpdb;

SOM_DB::maybe_upgrade();

$out( 'plugin', SOM_VERSION );
$assert( version_compare( SOM_VERSION, '0.14.0', '>=' ), 'SOM_VERSION_gte_0.14.0' );

// --- Worked example from 03 §2: allocate + full receive WA ---
$supplier_id = SOM_Suppliers::create( array( 'name' => 'U3 Smoke Supplier' ) );
$assert( ! is_wp_error( $supplier_id ), 'supplier_create' );
$supplier_id = (int) $supplier_id;

$vinyl = SOM_Materials::create(
	array(
		'name'      => 'U3 Vinyl',
		'unit'      => 'sheet',
		'is_active' => 1,
	)
);
$lam = SOM_Materials::create(
	array(
		'name'      => 'U3 Laminate',
		'unit'      => 'sheet',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $vinyl ) && ! is_wp_error( $lam ), 'materials_create' );
$vinyl = (int) $vinyl;
$lam   = (int) $lam;

// Seed vinyl: stock 30, value 18 → WA 0.60
SOM_Materials::adjust_stock( $vinyl, 30, array( 'reason' => 'restock', 'unit_cost_at_time' => 0.6, 'value_change' => 18.0, 'sync_unit_cost' => true ) );
$v = SOM_Materials::get( $vinyl );
$assert( $approx( $v->current_stock, 30 ) && $approx( $v->total_value_on_hand, 18 ) && $approx( $v->unit_cost, 0.6 ), 'vinyl_seeded' );

$po_id = SOM_Purchase_Orders::create(
	array(
		'supplier_id'   => $supplier_id,
		'order_date'    => current_time( 'Y-m-d' ),
		'shipping_cost' => 6,
		'other_cost'    => 0,
		'items'         => array(
			array(
				'material_id'      => $vinyl,
				'quantity_ordered' => 50,
				'item_cost'        => 30,
			),
			array(
				'material_id'      => $lam,
				'quantity_ordered' => 20,
				'item_cost'        => 9,
			),
		),
	)
);
$assert( ! is_wp_error( $po_id ), 'po_create' );
$po_id = (int) $po_id;
$po    = SOM_Purchase_Orders::get( $po_id );

// Preview before receive — must match math without writing.
$preview = SOM_Material_Costing::preview_impact(
	array(
		'shipping_cost' => 6,
		'other_cost'    => 0,
		'items'         => array(
			array(
				'material_id'      => $vinyl,
				'quantity_ordered' => 50,
				'item_cost'        => 30,
			),
			array(
				'material_id'      => $lam,
				'quantity_ordered' => 20,
				'item_cost'        => 9,
			),
		),
	)
);
$assert( ! is_wp_error( $preview ), 'preview_ok' );
$assert( empty( $preview['warnings'] ), 'preview_no_warnings' );

$pv = null;
$pl = null;
foreach ( $preview['lines'] as $line ) {
	if ( (int) $line['material_id'] === $vinyl ) {
		$pv = $line;
	}
	if ( (int) $line['material_id'] === $lam ) {
		$pl = $line;
	}
}
$assert( $pv && $approx( $pv['landed_unit_cost'], 0.6923 ), 'preview_vinyl_landed' );
$assert( $pl && $approx( $pl['landed_unit_cost'], 0.51923, 0.0001 ), 'preview_lam_landed' );
$assert( $pv && $approx( $pv['projected_unit_cost'], 0.6577, 0.0002 ), 'preview_vinyl_wa' );

$stock_before_preview = (float) SOM_Materials::get( $vinyl )->current_stock;
$value_before_preview = (float) SOM_Materials::get( $vinyl )->total_value_on_hand;

$item_v = $po->items[0];
$item_l = $po->items[1];
$recv   = SOM_Purchase_Orders::receive(
	$po_id,
	array(
		(int) $item_v->id => 50,
		(int) $item_l->id => 20,
	)
);
$assert( ! is_wp_error( $recv ), 'po_receive_full' );

$po = SOM_Purchase_Orders::get( $po_id );
$assert( 'received' === $po->status, 'po_received' );

$item_v = $po->items[0];
$item_l = $po->items[1];
$assert( $approx( $item_v->allocated_shipping_cost, 4.6154, 0.0002 ), 'vinyl_alloc_ship' );
$assert( $approx( $item_l->allocated_shipping_cost, 1.3846, 0.0002 ), 'lam_alloc_ship' );
$assert( $approx( $item_v->landed_unit_cost, 0.6923 ), 'vinyl_landed_stored' );
$assert( $approx( $item_l->landed_unit_cost, 0.51923, 0.0001 ), 'lam_landed_stored' );

$v = SOM_Materials::get( $vinyl );
$assert( $approx( $v->current_stock, 80 ), 'vinyl_stock_80' );
$assert( $approx( $v->total_value_on_hand, 52.615, 0.002 ), 'vinyl_value_52615' );
$assert( $approx( $v->unit_cost, 0.6577, 0.0002 ), 'vinyl_wa_synced' );

$log = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE material_id = %d AND reason = %s ORDER BY id DESC LIMIT 1',
		$vinyl,
		'purchase_received'
	)
);
$assert( $log && $approx( $log->unit_cost_at_time, 0.6923 ), 'purchase_log_landed' );
$assert( $log && $approx( $log->value_change, 34.615, 0.002 ), 'purchase_log_value' );

// Preview did not mutate before receive — stock only moved on receive (+50).
$assert( $approx( $stock_before_preview, 30 ) && $approx( $value_before_preview, 18 ), 'preview_no_db_writes' );

// Preview projected WA matches post-receive WA.
$assert( $pv && $approx( $pv['projected_unit_cost'], (float) $v->unit_cost, 0.0002 ), 'preview_matches_receive' );

// --- Consumption keeps value consistent ---
$wa_before = (float) $v->unit_cost;
$consume   = SOM_Materials::adjust_stock( $vinyl, -10, array( 'reason' => 'new_order' ) );
$assert( ! is_wp_error( $consume ), 'consume_ok' );
$v2 = SOM_Materials::get( $vinyl );
$assert( $approx( $v2->current_stock, 70 ), 'consume_stock' );
$assert( $approx( $v2->total_value_on_hand, 52.615 - ( 10 * $wa_before ), 0.01 ), 'consume_value' );
$clog = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE material_id = %d AND reason = %s ORDER BY id DESC LIMIT 1',
		$vinyl,
		'new_order'
	)
);
$assert( $clog && $approx( $clog->unit_cost_at_time, $wa_before, 0.0002 ), 'consume_log_wa' );
$assert( $clog && $approx( $clog->value_change, -10 * $wa_before, 0.01 ), 'consume_log_value' );

// --- Goals approaching / over ---
$templates = SOM_Workflows::list_for_dropdown();
$assert( ! empty( $templates ), 'workflow_exists' );
$wf_id = (int) $templates[0]->id;

$goal_id = SOM_Workflow_Material_Goals::upsert(
	array(
		'workflow_template_id'      => $wf_id,
		'material_id'               => $vinyl,
		'goal_unit_cost'            => 0.70,
		'warning_threshold_percent' => 90,
	)
);
$assert( ! is_wp_error( $goal_id ), 'goal_upsert' );

// WA ~0.6577 → approaching (90% of 0.70 = 0.63)
$alerts = SOM_Material_Costing::goal_alerts_for_material( $vinyl );
$levels = wp_list_pluck( $alerts, 'level' );
$assert( in_array( 'approaching', $levels, true ), 'goal_approaching' );

SOM_Workflow_Material_Goals::update(
	(int) $goal_id,
	array( 'goal_unit_cost' => 0.60 )
);
$alerts = SOM_Material_Costing::goal_alerts_for_material( $vinyl );
$levels = wp_list_pluck( $alerts, 'level' );
$assert( in_array( 'over', $levels, true ), 'goal_over' );

// --- Zero line-cost allocation warning ---
$zero_alloc = SOM_Material_Costing::allocate_line_costs(
	5,
	1,
	array(
		array(
			'item_cost'        => 0,
			'quantity_ordered' => 10,
		),
	)
);
$assert( ! empty( $zero_alloc['warnings'] ), 'zero_cost_warning' );
$assert( 0.0 === (float) $zero_alloc['allocations'][0]['allocated_shipping_cost'], 'zero_cost_no_alloc' );

// --- Manual unit_cost revalue (correcting path) ---
$reval = SOM_Materials::update( $lam, array( 'unit_cost' => 0.55 ) );
$assert( ! is_wp_error( $reval ), 'revalue_ok' );
$lam_row = SOM_Materials::get( $lam );
$assert( $approx( $lam_row->total_value_on_hand, (float) $lam_row->current_stock * 0.55, 0.01 ), 'revalue_value' );

// --- Product recipe costing helper ---
$product_id = SOM_Products::create(
	array(
		'name'                 => 'U3 Cost Product',
		'sku'                  => 'U3-COST',
		'workflow_template_id' => $wf_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product_id ), 'product_create' );
$product_id = (int) $product_id;
SOM_Products::update( $product_id, array( 'target_selling_price' => 5.00 ) );
SOM_Products::save_recipe(
	$product_id,
	array(
		array(
			'material_id'       => $vinyl,
			'quantity_per_unit' => 1,
		),
	)
);
$costing = SOM_Products::recipe_costing( $product_id );
$assert( $costing && null !== $costing['material_cost'] && null !== $costing['profit'], 'recipe_costing' );

$out( 'summary', 0 === $fail ? 'PASS — Sprint U3 smoke' : "FAIL — {$fail} assertion(s)" );
exit( 0 === $fail ? 0 : 1 );
