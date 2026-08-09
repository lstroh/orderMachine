<?php
/**
 * Sprint U7 smoke: REST purchasing/batches + Abilities + schema.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u7-smoke.php
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

/**
 * @param string                    $method  Method.
 * @param string                    $path    Route path under /som/v1 (no query string).
 * @param string                    $api_key API key.
 * @param array<string,mixed>|null  $body    JSON body.
 * @param array<string,mixed>       $query   Query params.
 * @return WP_REST_Response
 */
$rest = static function ( $method, $path, $api_key, $body = null, $query = array() ) {
	$req = new WP_REST_Request( $method, '/som/v1' . $path );
	$req->set_header( 'x-som-api-key', $api_key );
	if ( $query ) {
		$req->set_query_params( $query );
	}
	if ( null !== $body ) {
		$req->set_header( 'content-type', 'application/json' );
		$req->set_body( wp_json_encode( $body ) );
	}
	return rest_do_request( $req );
};

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();
SOM_Batch_Groups::ensure_rows();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( version_compare( SOM_VERSION, '0.18.0', '>=' ), 'SOM_VERSION_gte_0.18.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.5.0', '>=' ), 'som_db_version_gte_1.5.0' );

// Schema presence (update tables).
global $wpdb;
$tables = array(
	'suppliers',
	'purchase_orders',
	'purchase_order_items',
	'workflow_material_goals',
	'batch_groups',
	'step_batches',
	'step_batch_items',
);
foreach ( $tables as $t ) {
	$name = SOM_DB::table( $t );
	$assert( $name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ), 'schema_' . $t );
}

$settings = SOM_Settings::get();
if ( '' === (string) $settings['api_key'] ) {
	SOM_Settings::update( array( 'api_key' => 'sprint-u7-test-key' ) );
	$settings = SOM_Settings::get();
}
$api_key = (string) $settings['api_key'];

// --- Suppliers REST ---
$sup = $rest(
	'POST',
	'/suppliers',
	$api_key,
	array(
		'name'    => 'U7 Supplier ' . wp_generate_password( 4, false, false ),
		'website' => 'https://example.com/u7',
	)
);
$sup_data = $sup->get_data();
$assert( 200 === (int) $sup->get_status() && ! empty( $sup_data['ok'] ), 'rest_supplier_create' );
$supplier_id = isset( $sup_data['id'] ) ? (int) $sup_data['id'] : 0;

$list_sup = $rest( 'GET', '/suppliers', $api_key, null, array( 's' => 'U7 Supplier' ) );
$list_data = $list_sup->get_data();
$assert( 200 === (int) $list_sup->get_status() && ! empty( $list_data['suppliers'] ), 'rest_supplier_list' );

$get_sup = $rest( 'GET', '/suppliers/' . $supplier_id, $api_key );
$assert( 200 === (int) $get_sup->get_status() && (int) $get_sup->get_data()['id'] === $supplier_id, 'rest_supplier_get' );

// --- Materials for PO WA path (03 §2 vinyl numbers) ---
$vinyl = SOM_Materials::create(
	array(
		'name'      => 'U7 Vinyl ' . wp_generate_password( 4, false, false ),
		'unit'      => 'sheet',
		'is_active' => 1,
	)
);
$lam = SOM_Materials::create(
	array(
		'name'      => 'U7 Laminate ' . wp_generate_password( 4, false, false ),
		'unit'      => 'sheet',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $vinyl ) && ! is_wp_error( $lam ), 'materials_create' );
$vinyl = (int) $vinyl;
$lam   = (int) $lam;

SOM_Materials::adjust_stock(
	$vinyl,
	30,
	array(
		'reason'            => 'restock',
		'unit_cost_at_time' => 0.6,
		'value_change'      => 18.0,
		'sync_unit_cost'    => true,
	)
);

$po = $rest(
	'POST',
	'/purchase-orders',
	$api_key,
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
				'item_cost'        => 10,
			),
		),
	)
);
$po_data = $po->get_data();
$assert( 200 === (int) $po->get_status() && ! empty( $po_data['ok'] ), 'rest_po_create' );
$po_id = isset( $po_data['id'] ) ? (int) $po_data['id'] : 0;

$preview = $rest(
	'POST',
	'/purchase-orders/preview',
	$api_key,
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
				'item_cost'        => 10,
			),
		),
	)
);
$prev_data = $preview->get_data();
$assert( 200 === (int) $preview->get_status() && ! empty( $prev_data['ok'] ), 'rest_po_preview' );

$order_detail = SOM_Purchase_Orders::get( $po_id );
$item_ids     = array();
foreach ( $order_detail->items as $item ) {
	$item_ids[ (int) $item->material_id ] = (int) $item->id;
}

$recv = $rest(
	'POST',
	'/purchase-orders/' . $po_id . '/receive',
	$api_key,
	array(
		'deltas' => array(
			$item_ids[ $vinyl ] => 50,
			$item_ids[ $lam ]   => 20,
		),
	)
);
$recv_data = $recv->get_data();
$assert( 200 === (int) $recv->get_status() && ! empty( $recv_data['ok'] ), 'rest_po_receive' );

$v = SOM_Materials::get( $vinyl );
// Landed vinyl = (30 + 4.5) / 50 = 0.69; WA = (18 + 34.5) / 80 = 0.65625 ≈ 0.6577 with rounding in domain
$assert( $approx( $v->current_stock, 80 ), 'vinyl_stock_after_receive' );
$assert( $approx( $v->weighted_average, 0.6577, 0.002 ) || $approx( $v->unit_cost, 0.6577, 0.002 ), 'vinyl_wa_after_receive' );

$po_get = $rest( 'GET', '/purchase-orders/' . $po_id, $api_key );
$assert( 200 === (int) $po_get->get_status() && 'received' === (string) $po_get->get_data()['status'], 'rest_po_get_received' );

// --- Goals REST ---
$wf = SOM_Workflows::create( array( 'name' => 'U7 Goals WF', 'is_active' => 1 ) );
$assert( ! is_wp_error( $wf ), 'goals_workflow_create' );
$wf = (int) $wf;

$goal = $rest(
	'POST',
	'/workflow-material-goals',
	$api_key,
	array(
		'workflow_template_id'      => $wf,
		'material_id'               => $vinyl,
		'goal_unit_cost'            => 0.5,
		'warning_threshold_percent' => 90,
	)
);
$goal_data = $goal->get_data();
$assert( 200 === (int) $goal->get_status() && ! empty( $goal_data['ok'] ), 'rest_goal_upsert' );
$goal_id = isset( $goal_data['id'] ) ? (int) $goal_data['id'] : 0;

$goals_list = $rest( 'GET', '/workflow-material-goals', $api_key, null, array( 'workflow_template_id' => $wf ) );
$assert( 200 === (int) $goals_list->get_status() && ! empty( $goals_list->get_data()['goals'] ), 'rest_goals_list' );

$goal_del = $rest( 'DELETE', '/workflow-material-goals/' . $goal_id, $api_key );
$assert( 200 === (int) $goal_del->get_status() && ! empty( $goal_del->get_data()['ok'] ), 'rest_goal_delete' );

// --- Batch groups read ---
$groups = $rest( 'GET', '/batch-groups', $api_key );
$gdata  = $groups->get_data();
$assert( 200 === (int) $groups->get_status() && count( $gdata['batch_groups'] ) >= 2, 'rest_batch_groups_list' );

$ship_group = SOM_Batch_Groups::get_by_key( 'shipping_label' );
$assert( (bool) $ship_group, 'shipping_label_group' );

// --- Batch collect → release → mark-done via REST ---
$make_batch_product = static function ( $name, $group_id ) {
	$wf_id = SOM_Workflows::create(
		array(
			'name'      => $name,
			'is_active' => 1,
		)
	);
	if ( is_wp_error( $wf_id ) ) {
		return null;
	}
	$wf_id = (int) $wf_id;
	$saved = SOM_Workflows::save_steps(
		$wf_id,
		array(
			array(
				'name'           => 'Batch gate',
				'batch_group_id' => (int) $group_id,
			),
		)
	);
	if ( is_wp_error( $saved ) ) {
		return null;
	}
	$steps   = SOM_Workflows::get_steps( $wf_id );
	$product = SOM_Products::create(
		array(
			'name'                 => $name . ' Product',
			'workflow_template_id' => $wf_id,
			'is_active'            => 1,
			'target_selling_price' => 10,
		)
	);
	if ( is_wp_error( $product ) || empty( $steps ) ) {
		return null;
	}
	return array(
		'product_id' => (int) $product,
		'step_id'    => (int) $steps[0]->id,
	);
};

$create_order = static function ( $product_id, $suffix ) use ( $rest, $api_key ) {
	$res = $rest(
		'POST',
		'/orders',
		$api_key,
		array(
			'external_order_id' => 'u7-' . $suffix . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false ),
			'buyer_name'        => 'U7 Buyer ' . $suffix,
			'shipping_address'  => array(
				'line1'    => '1 Test Street',
				'city'     => 'London',
				'postcode' => 'E1 1AA',
				'country'  => 'GB',
			),
			'items'             => array(
				array(
					'product_id' => (int) $product_id,
					'quantity'   => 1,
				),
			),
		)
	);
	$data = $res->get_data();
	return isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
};

$suite = $make_batch_product( 'U7 Batch ' . wp_generate_password( 4, false, false ), (int) $ship_group->id );
$assert( null !== $suite, 'batch_product_setup' );

$order_ids = array();
if ( $suite ) {
	for ( $i = 1; $i <= 2; $i++ ) {
		$oid = $create_order( $suite['product_id'], (string) $i );
		$assert( $oid > 0, 'batch_order_' . $i );
		$order_ids[] = $oid;
	}
}

$batch = null;
if ( $order_ids ) {
	$batch = SOM_Batches::find_for_order( $order_ids[0] );
	$assert( (bool) $batch && 'collecting' === (string) $batch->status, 'batch_collecting' );
}

$batch_id = $batch ? (int) $batch->id : 0;
if ( $batch_id > 0 ) {
	$get_b = $rest( 'GET', '/batches/' . $batch_id, $api_key );
	$assert( 200 === (int) $get_b->get_status() && 2 === count( $get_b->get_data()['members'] ), 'rest_batch_get' );

	$rel = $rest( 'POST', '/batches/' . $batch_id . '/release', $api_key, array( 'manual' => true ) );
	$rel_data = $rel->get_data();
	$assert( 200 === (int) $rel->get_status() && 'ready' === (string) $rel_data['status'], 'rest_batch_release' );

	$done = $rest( 'POST', '/batches/' . $batch_id . '/mark-done', $api_key, array() );
	$done_data = $done->get_data();
	$assert( 200 === (int) $done->get_status() && 'done' === (string) $done_data['status'], 'rest_batch_mark_done' );

	foreach ( $order_ids as $oid ) {
		$o = SOM_Orders::get( $oid );
		$assert( $o && (int) $o->is_complete === 1, 'order_advanced_' . $oid );
	}
}

$list_batches = $rest( 'GET', '/batches', $api_key, null, array( 'include_done' => 1, 'status' => 'done' ) );
$assert( 200 === (int) $list_batches->get_status(), 'rest_batches_list' );

// --- Abilities (execute callbacks; enrich materials/products) ---
SOM_Settings::update( array( 'mcp_enabled' => true ) );
wp_set_current_user( 1 );

$ab_sup = SOM_Abilities::get_suppliers( array( 's' => 'U7 Supplier' ) );
$assert( ! empty( $ab_sup['suppliers'] ), 'ability_get_suppliers' );

$ab_po = SOM_Abilities::get_purchase_order( array( 'purchase_order_id' => $po_id ) );
$assert( ! is_wp_error( $ab_po ) && 'received' === (string) $ab_po['status'], 'ability_get_purchase_order' );

$ab_mat = SOM_Abilities::get_materials( array( 's' => 'U7 Vinyl', 'status' => 'active' ) );
$assert( ! empty( $ab_mat['materials'] ), 'ability_get_materials' );
if ( ! empty( $ab_mat['materials'] ) ) {
	$m0 = $ab_mat['materials'][0];
	$assert( array_key_exists( 'weighted_average', $m0 ) && array_key_exists( 'total_value_on_hand', $m0 ), 'ability_materials_costing_fields' );
}

if ( $suite ) {
	$ab_prod = SOM_Abilities::get_products( array( 's' => 'U7 Batch', 'status' => 'active' ) );
	$assert( ! empty( $ab_prod['products'] ), 'ability_get_products' );
	if ( ! empty( $ab_prod['products'] ) ) {
		$p0 = $ab_prod['products'][0];
		$assert( array_key_exists( 'target_selling_price', $p0 ) && array_key_exists( 'material_cost', $p0 ), 'ability_products_costing_fields' );
	}
}

$ab_groups = SOM_Abilities::get_batch_groups();
$assert( ! empty( $ab_groups['batch_groups'] ), 'ability_get_batch_groups' );

if ( $batch_id > 0 ) {
	$ab_batch = SOM_Abilities::get_batch( array( 'batch_id' => $batch_id ) );
	$assert( ! is_wp_error( $ab_batch ) && 'done' === (string) $ab_batch['status'], 'ability_get_batch' );
}

if ( $fail > 0 ) {
	echo "FAIL — Sprint U7 smoke ({$fail} assertions)\n";
	exit( 1 );
}

echo "PASS — Sprint U7 smoke\n";
