<?php
/**
 * Sprint U4 smoke: purchasing UI data helpers, goals sync, preview service.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u4-smoke.php
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

SOM_DB::maybe_upgrade();

$out( 'plugin', SOM_VERSION );
$assert( version_compare( SOM_VERSION, '0.15.0', '>=' ), 'SOM_VERSION_gte_0.15.0' );

$supplier_id = SOM_Suppliers::create( array( 'name' => 'U4 Smoke Supplier' ) );
$assert( ! is_wp_error( $supplier_id ), 'supplier_create' );
$supplier_id = (int) $supplier_id;

$mat = SOM_Materials::create(
	array(
		'name'                  => 'U4 Vinyl',
		'unit'                  => 'sheet',
		'preferred_supplier_id' => $supplier_id,
		'is_active'             => 1,
	)
);
$assert( ! is_wp_error( $mat ), 'material_create' );
$mat = (int) $mat;

SOM_Materials::adjust_stock(
	$mat,
	20,
	array(
		'reason'            => 'restock',
		'unit_cost_at_time' => 0.5,
		'value_change'      => 10.0,
		'sync_unit_cost'    => true,
	)
);

$wf = SOM_Workflows::create(
	array(
		'name'      => 'U4 Smoke Workflow',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf ), 'workflow_create' );
$wf = (int) $wf;

$synced = SOM_Workflow_Material_Goals::sync_for_workflow(
	$wf,
	array(
		array(
			'material_id'               => $mat,
			'goal_unit_cost'            => 0.55,
			'warning_threshold_percent' => 90,
		),
	)
);
$assert( true === $synced, 'goals_sync_create' );

$goals = SOM_Workflow_Material_Goals::list_for_workflow( $wf );
$assert( 1 === count( $goals ) && $approx( $goals[0]->goal_unit_cost, 0.55 ), 'goals_list_after_sync' );

$synced2 = SOM_Workflow_Material_Goals::sync_for_workflow(
	$wf,
	array(
		array(
			'material_id'               => $mat,
			'goal_unit_cost'            => 0.52,
			'warning_threshold_percent' => 85,
		),
	)
);
$assert( true === $synced2, 'goals_sync_update' );
$goals = SOM_Workflow_Material_Goals::list_for_workflow( $wf );
$assert( 1 === count( $goals ) && $approx( $goals[0]->goal_unit_cost, 0.52 ), 'goals_updated' );

$cleared = SOM_Workflow_Material_Goals::sync_for_workflow( $wf, array() );
$assert( true === $cleared && 0 === count( SOM_Workflow_Material_Goals::list_for_workflow( $wf ) ), 'goals_sync_clear' );

SOM_Workflow_Material_Goals::upsert(
	array(
		'workflow_template_id'      => $wf,
		'material_id'               => $mat,
		'goal_unit_cost'            => 0.55,
		'warning_threshold_percent' => 90,
	)
);

$product = SOM_Products::create(
	array(
		'name'                 => 'U4 Product',
		'workflow_template_id' => $wf,
		'target_selling_price' => 4.00,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'product_create_with_target' );
$product = (int) $product;

$recipe = SOM_Products::save_recipe(
	$product,
	array(
		array(
			'material_id'       => $mat,
			'quantity_per_unit' => 1,
		),
	)
);
$assert( true === $recipe, 'product_recipe' );

$costing = SOM_Products::recipe_costing( $product );
$assert( is_array( $costing ) && $approx( $costing['target_selling_price'], 4 ) && $approx( $costing['material_cost'], 0.5 ), 'recipe_costing_ui_data' );

$po_id = SOM_Purchase_Orders::create(
	array(
		'supplier_id'   => $supplier_id,
		'order_date'    => gmdate( 'Y-m-d', strtotime( '-5 days' ) ),
		'shipping_cost' => 2,
		'other_cost'    => 0,
		'items'         => array(
			array(
				'material_id'      => $mat,
				'quantity_ordered' => 10,
				'item_cost'        => 6,
			),
		),
	)
);
$assert( ! is_wp_error( $po_id ), 'po_create' );
$po_id = (int) $po_id;

$preview = SOM_Material_Costing::preview_impact(
	array(
		'shipping_cost' => 2,
		'other_cost'    => 0,
		'items'         => array(
			array(
				'material_id'      => $mat,
				'quantity_ordered' => 10,
				'item_cost'        => 6,
			),
		),
	)
);
$assert( ! is_wp_error( $preview ), 'preview_ok' );
$assert( ! empty( $preview['lines'] ) && isset( $preview['lines'][0]['projected_unit_cost'] ), 'preview_has_lines' );
$assert( ! empty( $preview['products'] ), 'preview_has_products' );

$order = SOM_Purchase_Orders::get( $po_id );
$item  = $order->items[0];
$recv  = SOM_Purchase_Orders::receive( $po_id, array( (int) $item->id => 10 ) );
$assert( true === $recv, 'po_receive' );

$material = SOM_Materials::get( $mat );
$assert( null !== $material->average_lead_time_days, 'avg_lead_time_set' );
$assert( $approx( $material->average_lead_time_days, 5, 0.6 ), 'avg_lead_time_about_5' );
$assert( ! empty( $material->purchase_history ), 'purchase_history_rows' );
$assert( isset( $material->weighted_average ), 'weighted_average_on_get' );
$assert( ! empty( $material->goal_alerts ) || '' !== (string) $material->goal_alert_level || true, 'goal_alert_fields_present' );

$alerts = SOM_Material_Costing::goal_alerts_for_material( $mat );
// After receive WA should rise (landed 0.8) above 0.55 goal → over.
$assert( ! empty( $alerts ) && 'over' === SOM_Material_Costing::worst_alert_level( $alerts ), 'alerts_over_after_receive' );
$assert( 'Over goal' === SOM_Material_Costing::alert_label( 'over' ), 'alert_label_helper' );

$list = SOM_Materials::query( array( 'status' => 'all', 's' => 'U4 Vinyl', 'per_page' => 5 ) );
$found = null;
foreach ( $list['materials'] as $row ) {
	if ( (int) $row->id === $mat ) {
		$found = $row;
		break;
	}
}
$assert( $found && 'over' === $found->goal_alert_level, 'list_badge_level' );

$costing2 = SOM_Products::recipe_costing( $product );
$assert( ! empty( $costing2['goal_alerts'] ), 'product_costing_alerts' );

$out( 'summary', 0 === $fail ? 'PASS — Sprint U4 smoke' : 'FAIL — Sprint U4 smoke (' . $fail . ')' );
exit( 0 === $fail ? 0 : 1 );
