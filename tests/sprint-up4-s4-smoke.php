<?php
/**
 * UP4-S4 smoke: nesting/cycle guards, listing exclude, analytics exclude, deactivate.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up4-s4-smoke.php
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
$assert( version_compare( SOM_VERSION, '0.27.0', '>=' ), 'SOM_VERSION_gte_0.27.0' );

// --- Two internal products for cycle A↔B ---
$wf_a = SOM_Workflows::create( array( 'name' => 'UP4-S4 A ' . wp_generate_password( 4, false, false ), 'is_active' => 1 ) );
$wf_b = SOM_Workflows::create( array( 'name' => 'UP4-S4 B ' . wp_generate_password( 4, false, false ), 'is_active' => 1 ) );
$assert( ! is_wp_error( $wf_a ) && ! is_wp_error( $wf_b ), 'create_workflows' );
$wf_a = (int) $wf_a;
$wf_b = (int) $wf_b;
SOM_Workflows::save_steps( $wf_a, array( array( 'name' => 'Make A', 'requires_manual_confirm' => 1 ) ) );
SOM_Workflows::save_steps( $wf_b, array( array( 'name' => 'Make B', 'requires_manual_confirm' => 1 ) ) );

$raw = SOM_Materials::create(
	array(
		'name'      => 'UP4-S4 Raw ' . wp_generate_password( 4, false, false ),
		'unit'      => 'sheet',
		'unit_cost' => 1,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $raw ), 'create_raw' );
$raw = (int) $raw;
SOM_Materials::adjust_stock( $raw, 100, array( 'reason' => 'restock', 'unit_cost_at_time' => 1, 'value_change' => 100, 'sync_unit_cost' => true ) );

$prod_a = SOM_Products::create(
	array(
		'name'                 => 'UP4-S4 Comp A',
		'sku'                  => 'UP4S4A-' . wp_generate_password( 4, false, false ),
		'workflow_template_id' => $wf_a,
		'is_internal'          => 1,
		'is_active'            => 1,
	)
);
$prod_b = SOM_Products::create(
	array(
		'name'                 => 'UP4-S4 Comp B',
		'sku'                  => 'UP4S4B-' . wp_generate_password( 4, false, false ),
		'workflow_template_id' => $wf_b,
		'is_internal'          => 1,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $prod_a ) && ! is_wp_error( $prod_b ), 'create_internals' );
$prod_a = (int) $prod_a;
$prod_b = (int) $prod_b;

$a = SOM_Products::get( $prod_a );
$b = SOM_Products::get( $prod_b );
$assert( ! empty( $a->linked_material_id ) && ! empty( $b->linked_material_id ), 'linked_materials' );
$mat_a = (int) $a->linked_material_id;
$mat_b = (int) $b->linked_material_id;

$ok_a = SOM_Products::save_recipe(
	$prod_a,
	array( array( 'material_id' => $raw, 'quantity_per_unit' => 1 ) )
);
$assert( ! is_wp_error( $ok_a ), 'recipe_a_raw' );

$ok_b = SOM_Products::save_recipe(
	$prod_b,
	array( array( 'material_id' => $mat_a, 'quantity_per_unit' => 1 ) )
);
$assert( ! is_wp_error( $ok_b ), 'recipe_b_uses_a' );

$cycle = SOM_Products::save_recipe(
	$prod_a,
	array( array( 'material_id' => $mat_b, 'quantity_per_unit' => 1 ) )
);
$assert( is_wp_error( $cycle ) && 'som_recipe_cycle' === $cycle->get_error_code(), 'rejects_cycle' );

// Depth > 5: chain C1..C6
$prev_mat = $raw;
$chain    = array();
for ( $i = 1; $i <= 6; $i++ ) {
	$wf = SOM_Workflows::create( array( 'name' => "UP4-S4 D{$i} " . wp_generate_password( 3, false, false ), 'is_active' => 1 ) );
	$wf = (int) $wf;
	SOM_Workflows::save_steps( $wf, array( array( 'name' => "Step {$i}", 'requires_manual_confirm' => 1 ) ) );
	$pid = SOM_Products::create(
		array(
			'name'                 => "UP4-S4 Depth {$i}",
			'workflow_template_id' => $wf,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	);
	$assert( ! is_wp_error( $pid ), 'depth_product_' . $i );
	$pid = (int) $pid;
	$p   = SOM_Products::get( $pid );
	$r   = SOM_Products::save_recipe(
		$pid,
		array( array( 'material_id' => $prev_mat, 'quantity_per_unit' => 1 ) )
	);
	if ( $i <= 5 ) {
		$assert( ! is_wp_error( $r ), 'depth_ok_' . $i );
	} else {
		$assert( is_wp_error( $r ) && 'som_recipe_depth' === $r->get_error_code(), 'rejects_depth_over_5' );
	}
	$prev_mat = (int) $p->linked_material_id;
	$chain[]  = $pid;
}

// Listings exclude / reject.
$options = SOM_Listings::product_options();
$ids     = array_map(
	static function ( $row ) {
		return (int) $row->id;
	},
	$options
);
$assert( ! in_array( $prod_a, $ids, true ), 'options_exclude_internal' );

$listing = SOM_Listings::create(
	array(
		'product_id'          => $prod_a,
		'channel_slug'        => 'ebay',
		'external_listing_id' => 'up4s4-' . wp_generate_password( 6, false, false ),
		'title'               => 'Should fail',
		'price'               => 1,
	)
);
$assert( is_wp_error( $listing ) && 'som_listing_internal' === $listing->get_error_code(), 'create_rejects_internal' );

// Deactivate blocked while open production job (manual step — incomplete).
$order_id = SOM_Production::produce( $prod_a, 1 );
$assert( ! is_wp_error( $order_id ), 'produce_open_job' );
$order_id = (int) $order_id;
$order    = SOM_Orders::get( $order_id );
$assert( $order && empty( $order->is_complete ), 'job_open' );

$deact = SOM_Products::update( $prod_a, array( 'is_active' => 0 ) );
$assert( is_wp_error( $deact ) && 'som_product_open_jobs' === $deact->get_error_code(), 'deactivate_blocked' );

// Analytics excludes internal.
$filters = array(
	'start'      => gmdate( 'Y-m-d 00:00:00', time() - DAY_IN_SECONDS ),
	'end'        => gmdate( 'Y-m-d 23:59:59', time() + DAY_IN_SECONDS ),
	'channel_id' => 0,
	'granularity'=> 'day',
);
$loaded = SOM_Analytics::load_orders_with_items( $filters );
$found  = false;
foreach ( $loaded as $row ) {
	if ( (int) $row->id === $order_id ) {
		$found = true;
		break;
	}
}
$assert( ! $found, 'analytics_excludes_internal_order' );

// Query type filter.
$internal_q = SOM_Products::query( array( 'status' => 'all', 'type' => 'internal', 'per_page' => 100 ) );
$found_a    = false;
foreach ( $internal_q['products'] as $row ) {
	if ( (int) $row->id === $prod_a ) {
		$found_a = true;
		break;
	}
}
$assert( $found_a, 'query_type_internal' );

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'OK' );
$out( 'open_order_id', (string) $order_id );
$out( 'chain_len', (string) count( $chain ) );
