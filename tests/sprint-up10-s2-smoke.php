<?php
/**
 * UP10-S2 smoke: Analytics workflow timing aggregates + over-plan flag.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up10-s2-smoke.php
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
$out( 'db', (string) get_option( 'som_db_version', '' ) );

$assert( class_exists( 'SOM_Analytics' ), 'class_analytics' );
$assert( 1.2 === (float) SOM_Analytics::OVER_PLAN_RATIO, 'over_plan_ratio_1_2' );

// Static over-plan helper.
$assert( true === SOM_Analytics::is_over_plan( 130, 100 ), 'over_plan_true_130_100' );
$assert( false === SOM_Analytics::is_over_plan( 120, 100 ), 'over_plan_false_exact_120' );
$assert( false === SOM_Analytics::is_over_plan( 100, 100 ), 'over_plan_false_on_plan' );
$assert( false === SOM_Analytics::is_over_plan( null, 100 ), 'over_plan_false_null_actual' );
$assert( false === SOM_Analytics::is_over_plan( 200, null ), 'over_plan_false_null_planned' );

$view_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/analytics.php' );
$js_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/analytics.js' );
$assert( false !== strpos( $view_src, 'Workflow timing' ), 'view_timing_heading' );
$assert( false !== strpos( $view_src, 'som_timing_kind' ), 'view_timing_kind' );
$assert( false !== strpos( $view_src, 'som_timing_template' ), 'view_timing_template' );
$assert( false !== strpos( $view_src, 'som-chart-timing-steps' ), 'view_timing_chart_canvas' );
$assert( false !== strpos( $view_src, 'Step averages' ), 'view_step_averages_table' );
$assert( false !== strpos( $js_src, 'som-chart-timing-steps' ), 'js_timing_chart' );
$assert( false !== strpos( $js_src, 'timing.chart' ), 'js_timing_payload' );

$guide = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md' );
$ref   = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-REFERENCE.md' );
$assert( false !== strpos( $guide, 'Planned vs timer vs actual' ), 'guide_planned_vs_timer' );
$assert( false !== strpos( $ref, 'Planned vs timer vs actual' ), 'ref_planned_vs_timer' );
$assert( false !== strpos( $ref, 'Workflow timing' ), 'ref_analytics_timing' );

$suffix    = wp_generate_password( 4, false, false );
$prev_pack = SOM_Pack::default_template_id();
$item_prog_t = SOM_DB::table( 'order_item_step_progress' );
$order_prog_t = SOM_DB::table( 'order_step_progress' );

// Pack template.
$wf_pack = SOM_Workflows::create(
	array(
		'name'      => 'UP10-S2 pack ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf_pack ), 'create_pack_template' );
$wf_pack_id = (int) $wf_pack;

$saved_pack = SOM_Workflows::save_steps(
	$wf_pack_id,
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
			'planned_value'           => '10',
			'planned_unit'            => 'minutes',
		),
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
			'planned_value'           => '5',
			'planned_unit'            => 'minutes',
		),
	)
);
$assert( true === $saved_pack, 'save_pack_steps' );
$assert( true === SOM_Pack::set_default_template_id( $wf_pack_id ), 'set_default_pack' );

// Make template: Print planned 10m; Cut planned 5m — Print will be over plan.
$wf_make = SOM_Workflows::create(
	array(
		'name'      => 'UP10-S2 make ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf_make ), 'create_make_template' );
$wf_make_id = (int) $wf_make;

$saved_make = SOM_Workflows::save_steps(
	$wf_make_id,
	array(
		array(
			'name'                    => 'Print',
			'requires_manual_confirm' => 1,
			'planned_value'           => '10',
			'planned_unit'            => 'minutes',
		),
		array(
			'name'                    => 'Cut',
			'requires_manual_confirm' => 1,
			'planned_value'           => '5',
			'planned_unit'            => 'minutes',
		),
	)
);
$assert( true === $saved_make, 'save_make_steps' );

$product = SOM_Products::create(
	array(
		'name'                 => 'UP10-S2 product ' . $suffix,
		'sku'                  => 'UP10S2-' . $suffix,
		'workflow_template_id' => $wf_make_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up10s2-' . $suffix,
		'buyer_name'        => 'UP10-S2 Buyer',
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
				'unit_price' => 12,
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$order = SOM_Orders::get( $order_id );
$assert( $order && ! empty( $order->items ), 'order_loaded' );
$item_id = (int) $order->items[0]->id;

// Force order_date into a known custom window for filter tests.
$order_date = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-15 12:00:00' ) );
$wpdb->update(
	SOM_DB::table( 'orders' ),
	array( 'order_date' => $order_date ),
	array( 'id' => $order_id ),
	array( '%s' ),
	array( '%d' )
);

$progress = SOM_Item_Make::get_item_progress( $item_id );
$print    = null;
$cut      = null;
foreach ( $progress as $row ) {
	if ( 'Print' === (string) $row->step_name ) {
		$print = $row;
	}
	if ( 'Cut' === (string) $row->step_name ) {
		$cut = $row;
	}
}
$assert( $print && $cut, 'make_steps_found' );

// Print: 15 minutes actual vs 10 planned → over plan (1.5×).
$print_start = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-15 10:00:00' ) );
$wpdb->update(
	$item_prog_t,
	array( 'started_at' => $print_start ),
	array( 'id' => (int) $print->id ),
	array( '%s' ),
	array( '%d' )
);
// Freeze "now" for wall clock by completing with known completed_at via direct update after mark_done.
$done_print = SOM_Item_Make::mark_done( $order_id, $item_id );
$assert( ! is_wp_error( $done_print ), 'mark_print_done' );

$progress2 = SOM_Item_Make::get_item_progress( $item_id );
$print2    = null;
$cut2      = null;
foreach ( $progress2 as $row ) {
	if ( 'Print' === (string) $row->step_name ) {
		$print2 = $row;
	}
	if ( 'Cut' === (string) $row->step_name ) {
		$cut2 = $row;
	}
}
$assert( $print2 && 'done' === (string) $print2->status, 'print_done' );

// Rewrite Print actual to exactly 900s (15m) and Cut window to 120s (2m under 5m plan).
$print_end = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-15 10:15:00' ) );
$wpdb->update(
	$item_prog_t,
	array(
		'started_at'     => $print_start,
		'completed_at'   => $print_end,
		'actual_seconds' => 900,
	),
	array( 'id' => (int) $print2->id ),
	array( '%s', '%s', '%d' ),
	array( '%d' )
);

$cut_start = $print_end;
$cut_end   = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-15 10:17:00' ) );
$wpdb->update(
	$item_prog_t,
	array( 'started_at' => $cut_start ),
	array( 'id' => (int) $cut2->id ),
	array( '%s' ),
	array( '%d' )
);

$done_cut = SOM_Item_Make::mark_done( $order_id, $item_id );
$assert( ! is_wp_error( $done_cut ), 'mark_cut_done' );

$progress3 = SOM_Item_Make::get_item_progress( $item_id );
$cut3      = null;
foreach ( $progress3 as $row ) {
	if ( 'Cut' === (string) $row->step_name ) {
		$cut3 = $row;
	}
}
$assert( $cut3 && 'done' === (string) $cut3->status, 'cut_done' );
$wpdb->update(
	$item_prog_t,
	array(
		'started_at'     => $cut_start,
		'completed_at'   => $cut_end,
		'actual_seconds' => 120,
	),
	array( 'id' => (int) $cut3->id ),
	array( '%s', '%s', '%d' ),
	array( '%d' )
);

// Pack: Confirm 13m (strictly over 10m × 1.2 = 12m), Ship 2m (under 5m).
$order = SOM_Orders::get( $order_id );
$assert( (int) $order->pack_workflow_template_id === $wf_pack_id, 'pack_bound' );

$pack_confirm = null;
$pack_ship    = null;
foreach ( $order->workflow_progress as $prow ) {
	if ( 'Confirm pack' === (string) $prow->step_name ) {
		$pack_confirm = $prow;
	}
	if ( 'Ship' === (string) $prow->step_name ) {
		$pack_ship = $prow;
	}
}
$assert( $pack_confirm && $pack_ship, 'pack_steps_found' );

$confirm_start = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-15 11:00:00' ) );
$wpdb->update(
	$order_prog_t,
	array( 'started_at' => $confirm_start ),
	array( 'id' => (int) $pack_confirm->id ),
	array( '%s' ),
	array( '%d' )
);
$done_confirm = SOM_Workflow_Engine::mark_done( $order_id );
$assert( ! is_wp_error( $done_confirm ), 'mark_confirm_done' );

$order = SOM_Orders::get( $order_id );
foreach ( $order->workflow_progress as $prow ) {
	if ( 'Confirm pack' === (string) $prow->step_name ) {
		$pack_confirm = $prow;
	}
	if ( 'Ship' === (string) $prow->step_name ) {
		$pack_ship = $prow;
	}
}
// 13m actual > planned 10m × 1.2 (exactly 12m is not over-plan).
$confirm_end = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-15 11:13:00' ) );
$wpdb->update(
	$order_prog_t,
	array(
		'started_at'     => $confirm_start,
		'completed_at'   => $confirm_end,
		'actual_seconds' => 780,
	),
	array( 'id' => (int) $pack_confirm->id ),
	array( '%s', '%s', '%d' ),
	array( '%d' )
);

// Ship gates: shipping package + shipment row.
$pkg_id = (int) SOM_Pack::site_default_package_id();
if ( $pkg_id < 1 ) {
	$pkgs   = SOM_Shipping_Packages::list_active();
	$pkg_id = ! empty( $pkgs[0]->id ) ? (int) $pkgs[0]->id : 0;
}
if ( $pkg_id > 0 ) {
	$set_pkg = SOM_Pack::set_shipping_package( $order_id, $pkg_id );
	$assert( ! is_wp_error( $set_pkg ), 'set_shipping_package' );
} else {
	$assert( false, 'set_shipping_package' );
}
$ship_ok = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'         => 'Royal Mail',
		'service'         => 'Tracked 48',
		'shipped_at'      => gmdate( 'Y-m-d H:i:s' ),
		'postage_paid'    => '3.50',
		'tracking_number' => 'UP10S2' . $suffix,
	)
);
$assert( ! is_wp_error( $ship_ok ), 'save_shipment' );

$ship_start = $confirm_end;
$ship_end   = gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-15 11:15:00' ) );
$wpdb->update(
	$order_prog_t,
	array( 'started_at' => $ship_start ),
	array( 'id' => (int) $pack_ship->id ),
	array( '%s' ),
	array( '%d' )
);
$done_ship = SOM_Workflow_Engine::mark_done( $order_id );
$assert( ! is_wp_error( $done_ship ), 'mark_ship_done' );

$order = SOM_Orders::get( $order_id );
foreach ( $order->workflow_progress as $prow ) {
	if ( 'Ship' === (string) $prow->step_name ) {
		$pack_ship = $prow;
	}
}
$wpdb->update(
	$order_prog_t,
	array(
		'started_at'     => $ship_start,
		'completed_at'   => $ship_end,
		'actual_seconds' => 120,
	),
	array( 'id' => (int) $pack_ship->id ),
	array( '%s', '%s', '%d' ),
	array( '%d' )
);

// Filters for the fixture date.
$filters = SOM_Analytics::parse_filters(
	array(
		'som_range'            => 'custom',
		'som_date_from'        => '2026-09-01',
		'som_date_to'          => '2026-09-30',
		'som_granularity'      => 'daily',
		'som_channel'          => '0',
		'som_timing_kind'      => 'make',
		'som_timing_template'  => (string) $wf_make_id,
	)
);
$assert( 'make' === $filters['timing_kind'], 'filters_timing_kind' );
$assert( $wf_make_id === (int) $filters['timing_template_id'], 'filters_timing_template' );

$payload = SOM_Analytics::dashboard_payload( $filters );
$assert( isset( $payload['timing'] ) && is_array( $payload['timing'] ), 'payload_has_timing' );
$timing  = $payload['timing'];
$assert( $wf_make_id === (int) $timing['template_id'], 'timing_template_resolved' );
$assert( isset( $timing['steps'], $timing['workflow_totals'], $timing['chart'] ), 'timing_shape_keys' );
$assert( isset( $timing['chart']['labels'], $timing['chart']['avg_planned'], $timing['chart']['avg_actual'] ), 'chart_shape' );

$steps_by_name = array();
foreach ( $timing['steps'] as $step ) {
	$steps_by_name[ (string) $step['name'] ] = $step;
	$assert( array_key_exists( 'avg_planned', $step ), 'step_has_avg_planned_' . sanitize_title( (string) $step['name'] ) );
	$assert( array_key_exists( 'avg_actual', $step ), 'step_has_avg_actual_' . sanitize_title( (string) $step['name'] ) );
	$assert( array_key_exists( 'over_plan', $step ), 'step_has_over_plan_' . sanitize_title( (string) $step['name'] ) );
	$assert( array_key_exists( 'sample_count', $step ), 'step_has_samples_' . sanitize_title( (string) $step['name'] ) );
}

$assert( isset( $steps_by_name['Print'], $steps_by_name['Cut'] ), 'make_steps_in_timing' );
$assert( 600 === (int) $steps_by_name['Print']['avg_planned'], 'print_avg_planned_600' );
$assert( 900 === (int) $steps_by_name['Print']['avg_actual'], 'print_avg_actual_900' );
$assert( true === $steps_by_name['Print']['over_plan'], 'print_over_plan_flag' );
$assert( 300 === (int) $steps_by_name['Cut']['avg_planned'], 'cut_avg_planned_300' );
$assert( 120 === (int) $steps_by_name['Cut']['avg_actual'], 'cut_avg_actual_120' );
$assert( false === $steps_by_name['Cut']['over_plan'], 'cut_not_over_plan' );

$wf_totals = $timing['workflow_totals'];
$assert( 1 === (int) $wf_totals['count'], 'make_workflow_count_1' );
$assert( 900 === (int) $wf_totals['avg_planned'], 'make_planned_total_900' ); // 10m+5m
// Span: 10:00 → 10:17 = 1020s. Over-plan threshold = 900 × 1.2 = 1080 → not over.
$assert( 1020 === (int) $wf_totals['avg_actual'], 'make_span_avg_1020' );
$assert( isset( $wf_totals['rows'][0]['actual_seconds'], $wf_totals['rows'][0]['planned_seconds'], $wf_totals['rows'][0]['over_plan'] ), 'make_totals_row_shape' );
$assert( false === $wf_totals['rows'][0]['over_plan'], 'make_workflow_not_over_plan_span' );

// Pack timing payload.
$pack_filters = SOM_Analytics::parse_filters(
	array(
		'som_range'           => 'custom',
		'som_date_from'       => '2026-09-01',
		'som_date_to'         => '2026-09-30',
		'som_timing_kind'     => 'pack',
		'som_timing_template' => (string) $wf_pack_id,
	)
);
$pack_timing = SOM_Analytics::timing_payload( $pack_filters );
$assert( 'pack' === $pack_timing['kind'], 'pack_timing_kind' );
$pack_steps  = array();
foreach ( $pack_timing['steps'] as $step ) {
	$pack_steps[ (string) $step['name'] ] = $step;
}
$assert( isset( $pack_steps['Confirm pack'], $pack_steps['Ship'] ), 'pack_steps_present' );
$assert( 780 === (int) $pack_steps['Confirm pack']['avg_actual'], 'pack_confirm_avg_780' );
$assert( true === $pack_steps['Confirm pack']['over_plan'], 'pack_confirm_over_plan' );
$assert( 120 === (int) $pack_steps['Ship']['avg_actual'], 'pack_ship_avg_120' );
$assert( false === $pack_steps['Ship']['over_plan'], 'pack_ship_not_over_plan' );
$assert( 1 === (int) $pack_timing['workflow_totals']['count'], 'pack_workflow_count_1' );
// Span 11:00 → 11:15 = 900s; planned 900s.
$assert( 900 === (int) $pack_timing['workflow_totals']['avg_actual'], 'pack_span_900' );

// Empty range → zero samples.
$empty_filters = SOM_Analytics::parse_filters(
	array(
		'som_range'           => 'custom',
		'som_date_from'       => '2020-01-01',
		'som_date_to'         => '2020-01-31',
		'som_timing_kind'     => 'make',
		'som_timing_template' => (string) $wf_make_id,
	)
);
$empty_timing = SOM_Analytics::timing_payload( $empty_filters );
$assert( 0 === (int) $empty_timing['workflow_totals']['count'], 'empty_range_zero_workflows' );
foreach ( $empty_timing['steps'] as $step ) {
	$assert( 0 === (int) $step['sample_count'], 'empty_range_zero_samples_' . sanitize_title( (string) $step['name'] ) );
}

// Restore default pack.
if ( $prev_pack ) {
	SOM_Pack::set_default_template_id( (int) $prev_pack );
}

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "UP10-S2 smoke: ALL PASS\n";
