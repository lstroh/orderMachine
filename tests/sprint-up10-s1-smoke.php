<?php
/**
 * UP10-S1 smoke: planned duration + actual_seconds + make/pack span totals.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up10-s1-smoke.php
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
$assert( '1.23.0' === (string) get_option( 'som_db_version', '' ), 'db_version_1.23.0' );
$assert( SOM_DB::DB_VERSION === '1.23.0', 'const_DB_VERSION_1.23.0' );

$steps_t      = SOM_DB::table( 'workflow_steps' );
$item_prog_t  = SOM_DB::table( 'order_item_step_progress' );
$order_prog_t = SOM_DB::table( 'order_step_progress' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$steps_t} LIKE 'planned_seconds'" ), 'col_planned_seconds' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$item_prog_t} LIKE 'actual_seconds'" ), 'col_item_actual_seconds' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$order_prog_t} LIKE 'actual_seconds'" ), 'col_order_actual_seconds' );

$editor_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$assert( false !== strpos( $editor_src, 'Planned duration' ), 'editor_planned_label' );
$assert( false !== strpos( $editor_src, 'planned_value' ), 'editor_planned_value_field' );
$assert( false !== strpos( $editor_src, 'Template planned total' ), 'editor_planned_total' );
$assert( false !== strpos( $editor_src, 'partial plan' ), 'editor_partial_plan_hint' );

$detail_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( false !== strpos( $detail_src, 'render_step_timing_html' ), 'detail_step_timing' );
$assert( false !== strpos( $detail_src, 'render_workflow_timing_footer_html' ), 'detail_workflow_timing_footer' );
$assert( false !== strpos( $detail_src, 'Make total' ), 'detail_make_total_label' );
$assert( false !== strpos( $detail_src, 'Pack total' ), 'detail_pack_total_label' );

$js_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$assert( false !== strpos( $js_src, 'initLiveElapsed' ), 'js_live_elapsed' );
$assert( false !== strpos( $js_src, 'data-som-elapsed-from' ), 'js_elapsed_attr' );
$assert( false !== strpos( $js_src, 'syncPlannedTotal' ), 'js_planned_total' );

// Duration helpers.
$assert( '5m' === SOM_Orders::format_duration_seconds( 300 ), 'format_5m' );
$assert( '1h 30m' === SOM_Orders::format_duration_seconds( 5400 ), 'format_1h30m' );
$assert( '—' === SOM_Orders::format_duration_seconds( null ), 'format_null' );
$assert( '+2m' === SOM_Orders::format_vs_plan( 300, 420 ), 'vs_plan_over' );
$assert( '−1m' === SOM_Orders::format_vs_plan( 300, 240 ), 'vs_plan_under' );
$assert( 'on plan' === SOM_Orders::format_vs_plan( 300, 300 ), 'vs_plan_equal' );

$planned_san = SOM_Workflows::sanitize_planned_seconds(
	array(
		'planned_value' => '15',
		'planned_unit'  => 'minutes',
	)
);
$assert( 900 === $planned_san, 'sanitize_planned_15m' );

$summary = SOM_Workflows::planned_total_summary(
	array(
		(object) array( 'planned_seconds' => 600 ),
		(object) array( 'planned_seconds' => null ),
		(object) array( 'planned_seconds' => 300 ),
	)
);
$assert( 900 === $summary['total'], 'planned_total_sum' );
$assert( true === $summary['is_partial'], 'planned_total_partial' );

$suffix    = wp_generate_password( 4, false, false );
$prev_pack = SOM_Pack::default_template_id();

// Pack template with planned durations.
$wf_pack = SOM_Workflows::create(
	array(
		'name'      => 'UP10-S1 pack ' . $suffix,
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
$assert( true === $saved_pack, 'save_pack_steps_with_planned' );
$assert( true === SOM_Pack::set_default_template_id( $wf_pack_id ), 'set_default_pack' );

$pack_steps = SOM_Workflows::get_steps( $wf_pack_id );
$assert( is_array( $pack_steps ) && count( $pack_steps ) >= 2, 'pack_steps_loaded' );
$pack_planned = array();
foreach ( $pack_steps as $ps ) {
	$pack_planned[ (string) $ps->name ] = isset( $ps->planned_seconds ) ? (int) $ps->planned_seconds : null;
}
$assert( 600 === ( $pack_planned['Confirm pack'] ?? null ), 'pack_step1_planned_saved' );
$assert( 300 === ( $pack_planned['Ship'] ?? null ), 'pack_step2_planned_saved' );

// Make template: two manual steps with planned durations.
$wf_make = SOM_Workflows::create(
	array(
		'name'      => 'UP10-S1 make ' . $suffix,
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
			'planned_value'           => '20',
			'planned_unit'            => 'minutes',
		),
		array(
			'name'                    => 'Cut',
			'requires_manual_confirm' => 1,
			'planned_value'           => '10',
			'planned_unit'            => 'minutes',
		),
	)
);
$assert( true === $saved_make, 'save_make_steps_with_planned' );

$product = SOM_Products::create(
	array(
		'name'                 => 'UP10-S1 product ' . $suffix,
		'sku'                  => 'UP10S1-' . $suffix,
		'workflow_template_id' => $wf_make_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up10s1-' . $suffix,
		'buyer_name'        => 'UP10-S1 Buyer',
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

$progress = SOM_Item_Make::get_item_progress( $item_id );
$assert( is_array( $progress ) && count( $progress ) >= 2, 'make_progress_rows' );

$print = null;
$cut   = null;
foreach ( $progress as $row ) {
	if ( 'Print' === (string) $row->step_name ) {
		$print = $row;
	}
	if ( 'Cut' === (string) $row->step_name ) {
		$cut = $row;
	}
}
$assert( $print && 'in_progress' === (string) $print->status, 'print_in_progress' );
$assert( ! empty( $print->started_at ), 'print_started_at_set' );
$assert( 1200 === (int) $print->planned_seconds, 'print_planned_joined' );

$live = SOM_Orders::step_live_elapsed_seconds( $print );
$assert( null !== $live && $live >= 0, 'live_elapsed_for_in_progress' );

// Backdate started_at so actual is measurable (non-overlapping sequential windows).
$t0          = time();
$print_start = gmdate( 'Y-m-d H:i:s', $t0 - 180 );
$wpdb->update(
	$item_prog_t,
	array( 'started_at' => $print_start ),
	array( 'id' => (int) $print->id ),
	array( '%s' ),
	array( '%d' )
);

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
$assert( null !== $print2->actual_seconds && (int) $print2->actual_seconds >= 100, 'print_actual_seconds_persisted' );
$assert( ! empty( $print2->completed_at ), 'print_completed_at_set' );
$assert( $cut2 && 'in_progress' === (string) $cut2->status, 'cut_in_progress' );
$assert( ! empty( $cut2->started_at ), 'cut_started_at_set' );

// Start cut at print's completed_at (sequential, no overlap), then age it before Mark done.
$print_done_ts = SOM_Orders::mysql_to_timestamp( $print2->completed_at );
$cut_start_ts  = $print_done_ts ? $print_done_ts : time();
$wpdb->update(
	$item_prog_t,
	array( 'started_at' => gmdate( 'Y-m-d H:i:s', $cut_start_ts ) ),
	array( 'id' => (int) $cut2->id ),
	array( '%s' ),
	array( '%d' )
);
// Force a measurable cut duration by shifting both ends: started earlier relative to "now".
sleep( 1 );

$done_cut = SOM_Item_Make::mark_done( $order_id, $item_id );
$assert( ! is_wp_error( $done_cut ), 'mark_cut_done' );

$progress3 = SOM_Item_Make::get_item_progress( $item_id );
$assert( null === SOM_Item_Make::current_item_progress( $item_id ), 'make_line_complete' );

$span = SOM_Orders::progress_span_seconds( $progress3 );
$assert( null !== $span && $span >= 100, 'make_span_first_to_last' );

$sum_steps = 0;
foreach ( $progress3 as $row ) {
	$sum_steps += (int) ( $row->actual_seconds ?? 0 );
}
// Sequential non-overlapping steps → span ≈ sum of step actuals.
$assert( abs( $span - $sum_steps ) <= 3, 'make_span_matches_sum' );
$out( 'make_span_seconds', (string) $span );
$out( 'make_sum_actuals', (string) $sum_steps );

// Pack: mark Confirm pack done with backdated start, then Ship.
$order = SOM_Orders::get( $order_id );
$assert( (int) $order->pack_workflow_template_id === $wf_pack_id, 'pack_bound' );
$assert( ! empty( $order->workflow_progress ), 'pack_progress_present' );

$pack_confirm = null;
foreach ( $order->workflow_progress as $prow ) {
	if ( 'Confirm pack' === (string) $prow->step_name ) {
		$pack_confirm = $prow;
		break;
	}
}
$assert( $pack_confirm && 'in_progress' === (string) $pack_confirm->status, 'pack_confirm_in_progress' );
$assert( 600 === (int) $pack_confirm->planned_seconds, 'pack_confirm_planned_joined' );

$pack_start = gmdate( 'Y-m-d H:i:s', time() - 90 );
$wpdb->update(
	$order_prog_t,
	array( 'started_at' => $pack_start ),
	array( 'id' => (int) $pack_confirm->id ),
	array( '%s' ),
	array( '%d' )
);

$done_pack1 = SOM_Workflow_Engine::mark_done( $order_id );
$assert( ! is_wp_error( $done_pack1 ), 'mark_pack_confirm_done' );

$order = SOM_Orders::get( $order_id );
$pack_confirm2 = null;
$pack_ship     = null;
foreach ( $order->workflow_progress as $prow ) {
	if ( 'Confirm pack' === (string) $prow->step_name ) {
		$pack_confirm2 = $prow;
	}
	if ( 'Ship' === (string) $prow->step_name ) {
		$pack_ship = $prow;
	}
}
$assert( $pack_confirm2 && 'done' === (string) $pack_confirm2->status, 'pack_confirm_done' );
$assert( null !== $pack_confirm2->actual_seconds && (int) $pack_confirm2->actual_seconds >= 60, 'pack_confirm_actual_seconds' );
$assert( $pack_ship && 'in_progress' === (string) $pack_ship->status, 'pack_ship_in_progress' );

// Ensure shipping package selected (Ship gate).
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

// Record shipment so Ship can complete.
$ship_ok = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'         => 'Royal Mail',
		'service'         => 'Tracked 48',
		'shipped_at'      => gmdate( 'Y-m-d H:i:s' ),
		'postage_paid'    => '3.50',
		'tracking_number' => 'UP10S1' . $suffix,
	)
);
$assert( ! is_wp_error( $ship_ok ), 'save_shipment' );

// Sequential: ship starts at confirm's completed_at.
$confirm_done_ts = SOM_Orders::mysql_to_timestamp( $pack_confirm2->completed_at );
$ship_start_ts   = $confirm_done_ts ? $confirm_done_ts : time();
$wpdb->update(
	$order_prog_t,
	array( 'started_at' => gmdate( 'Y-m-d H:i:s', $ship_start_ts ) ),
	array( 'id' => (int) $pack_ship->id ),
	array( '%s' ),
	array( '%d' )
);
sleep( 1 );

$done_ship = SOM_Workflow_Engine::mark_done( $order_id );
$assert( ! is_wp_error( $done_ship ), 'mark_ship_done' );

$order = SOM_Orders::get( $order_id );
$assert( ! empty( $order->is_complete ), 'order_complete' );
$pack_span = SOM_Orders::progress_span_seconds( $order->workflow_progress );
$assert( null !== $pack_span && $pack_span >= 80, 'pack_span_first_to_last' );
$out( 'pack_span_seconds', (string) $pack_span );

$pack_sum = 0;
foreach ( $order->workflow_progress as $prow ) {
	$pack_sum += (int) ( $prow->actual_seconds ?? 0 );
}
$assert( abs( $pack_span - $pack_sum ) <= 3, 'pack_span_matches_sum' );

foreach ( $order->workflow_progress as $prow ) {
	if ( 'done' === (string) $prow->status ) {
		$assert( null !== $prow->actual_seconds, 'pack_done_has_actual_' . sanitize_title( (string) $prow->step_name ) );
	}
}

// Wall clock helper.
$wc = SOM_Workflow_Engine::wall_clock_seconds(
	gmdate( 'Y-m-d H:i:s', time() - 30 ),
	gmdate( 'Y-m-d H:i:s', time() )
);
$assert( null !== $wc && $wc >= 25 && $wc <= 35, 'wall_clock_helper' );

// Restore previous default pack template.
if ( $prev_pack ) {
	SOM_Pack::set_default_template_id( (int) $prev_pack );
}

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAIL (' . $fail . ')' );
	exit( 1 );
}
$out( 'RESULT', 'PASS' );
