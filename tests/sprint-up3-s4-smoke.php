<?php
/**
 * Update Package 3 Sprint 4 smoke: Analytics dashboard aggregations.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s4-smoke.php
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
$approx = static function ( $a, $b, $eps = 0.02 ) {
	return abs( (float) $a - (float) $b ) <= $eps;
};

global $wpdb;

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();
SOM_Channel_Fee_Estimates::ensure_defaults();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( version_compare( SOM_VERSION, '0.22.0', '>=' ), 'SOM_VERSION_gte_0.22.0' );
$assert( class_exists( 'SOM_Analytics' ), 'class_analytics' );
$assert( class_exists( 'SOM_Platform_Fees' ), 'class_platform_fees' );

$ebay = SOM_Channels::get_by_slug( 'ebay' );
$etsy = SOM_Channels::get_by_slug( 'etsy' );
$assert( $ebay && $etsy, 'channels_present' );

// Filters + bucket labels.
$filters = SOM_Analytics::parse_filters(
	array(
		'som_range'       => 'custom',
		'som_date_from'   => '2026-08-01',
		'som_date_to'     => '2026-08-03',
		'som_granularity' => 'daily',
		'som_channel'     => '0',
	)
);
$assert( '2026-08-01' === $filters['date_from'] && '2026-08-03' === $filters['date_to'], 'custom_date_bounds' );
$labels = SOM_Analytics::bucket_labels( $filters['date_from'], $filters['date_to'], 'daily' );
$assert( array( '2026-08-01', '2026-08-02', '2026-08-03' ) === $labels, 'daily_labels' );

$tag = 'up3s4-' . wp_generate_password( 6, false );
$orders_t = SOM_DB::table( 'orders' );
$items_t  = SOM_DB::table( 'order_items' );
$log_t    = SOM_DB::table( 'material_stock_log' );
$mat_t    = SOM_DB::table( 'materials' );
$now      = gmdate( 'Y-m-d H:i:s' );

// Material for stock series.
$wpdb->insert(
	$mat_t,
	array(
		'name'                 => 'UP3-S4 Material ' . $tag,
		'unit'                 => 'sheet',
		'current_stock'        => 100,
		'total_value_on_hand'  => 50,
		'unit_cost'            => 0.5,
		'low_stock_threshold'  => 0,
		'is_active'            => 1,
		'created_at'           => $now,
		'updated_at'           => $now,
	),
	array( '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%s', '%s' )
);
$material_id = (int) $wpdb->insert_id;
$assert( $material_id > 0, 'material_insert' );

// Stock log: two consumptions after range start, walking backward from 100.
// At end of 2026-08-01: 100 (no changes yet that day after end — insert changes on 08-02 and 08-03).
$wpdb->insert(
	$log_t,
	array(
		'material_id'       => $material_id,
		'order_id'          => null,
		'change_qty'        => -10,
		'reason'            => 'manual_adjustment',
		'unit_cost_at_time' => 0.5,
		'value_change'      => -5,
		'created_at'        => '2026-08-02 12:00:00',
	),
	array( '%d', '%d', '%f', '%s', '%f', '%f', '%s' )
);
$wpdb->insert(
	$log_t,
	array(
		'material_id'       => $material_id,
		'order_id'          => null,
		'change_qty'        => -20,
		'reason'            => 'manual_adjustment',
		'unit_cost_at_time' => 0.5,
		'value_change'      => -10,
		'created_at'        => '2026-08-03 12:00:00',
	),
	array( '%d', '%d', '%f', '%s', '%f', '%f', '%s' )
);

$stock_filters = $filters;
$stock_filters['material_ids'] = array( $material_id );
$stock = SOM_Analytics::aggregate_stock_series( $stock_filters, $labels );
$assert( 1 === count( $stock['series'] ), 'stock_series_one' );
$stock_vals = $stock['series'][0]['values'];
$out( 'stock_vals', $stock_vals );
// current=100; after 08-02 change (-10) and 08-03 (-20).
// End of 08-01: both changes are after → 100 - (-10) - (-20)? Wait: bal -= change_qty for changes AFTER bucket_end.
// change_qty is -10, so bal -= (-10) means bal += 10? That's WRONG!

// Stock reconstruction: current_stock must equal balance after all log rows.
// Logs: -10 on 08-02, -20 on 08-03 → set current_stock = 70.
// End 08-01 → 100; end 08-02 → 90; end 08-03 → 70.
$wpdb->update(
	$mat_t,
	array( 'current_stock' => 70 ),
	array( 'id' => $material_id ),
	array( '%f' ),
	array( '%d' )
);

$stock = SOM_Analytics::aggregate_stock_series( $stock_filters, $labels );
$stock_vals = $stock['series'][0]['values'];
$out( 'stock_vals_corrected', $stock_vals );
$assert( $approx( $stock_vals[0], 100 ), 'stock_day1_100' );
$assert( $approx( $stock_vals[1], 90 ), 'stock_day2_90' );
$assert( $approx( $stock_vals[2], 70 ), 'stock_day3_70' );

$empty_stock = SOM_Analytics::aggregate_stock_series(
	array_merge( $filters, array( 'material_ids' => array() ) ),
	$labels
);
$assert( array() === $empty_stock['series'], 'stock_empty_until_selected' );

// Orders: priced, unpriced line drop, cancelled excluded, multi-line fee once.
$insert_order = static function ( $channel_id, $external_id, $order_date, $payload = '{}' ) use ( $wpdb, $orders_t, $now ) {
	$wpdb->insert(
		$orders_t,
		array(
			'channel_id'        => (int) $channel_id,
			'external_order_id' => $external_id,
			'order_date'        => $order_date,
			'buyer_name'        => 'UP3 S4',
			'raw_payload'       => $payload,
			'created_at'        => $now,
			'updated_at'        => $now,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	return (int) $wpdb->insert_id;
};

$oid_a = $insert_order( (int) $ebay->id, $tag . '-a', '2026-08-01 10:00:00' );
$oid_b = $insert_order( (int) $etsy->id, $tag . '-b', '2026-08-02 10:00:00' );
$oid_c = $insert_order(
	(int) $ebay->id,
	$tag . '-c',
	'2026-08-02 11:00:00',
	wp_json_encode( array( 'orderFulfillmentStatus' => 'CANCELLED' ) )
);
$oid_d = $insert_order( (int) $ebay->id, $tag . '-d', '2026-08-03 10:00:00' );

$wpdb->insert( $items_t, array( 'order_id' => $oid_a, 'product_id' => null, 'quantity' => 1, 'unit_price' => 10.00 ), array( '%d', '%d', '%d', '%f' ) );
$wpdb->insert( $items_t, array( 'order_id' => $oid_b, 'product_id' => null, 'quantity' => 2, 'unit_price' => 6.00 ), array( '%d', '%d', '%d', '%f' ) );
// Cancelled order — should be excluded even with price.
$wpdb->insert( $items_t, array( 'order_id' => $oid_c, 'product_id' => null, 'quantity' => 1, 'unit_price' => 99.00 ), array( '%d', '%d', '%d', '%f' ) );
// Order with one priced + one unpriced line — only priced counts; fees once on priced revenue.
$wpdb->insert( $items_t, array( 'order_id' => $oid_d, 'product_id' => null, 'quantity' => 1, 'unit_price' => 20.00 ), array( '%d', '%d', '%d', '%f' ) );
$wpdb->query(
	$wpdb->prepare(
		"INSERT INTO {$items_t} (order_id, product_id, quantity, unit_price) VALUES (%d, NULL, 1, NULL)",
		$oid_d
	)
);

// Material COGS on order A via stock log.
$wpdb->insert(
	$log_t,
	array(
		'material_id'       => $material_id,
		'order_id'          => $oid_a,
		'change_qty'        => -2,
		'reason'            => 'new_order',
		'unit_cost_at_time' => 1.5,
		'value_change'      => -3,
		'created_at'        => '2026-08-01 10:05:00',
	),
	array( '%d', '%d', '%f', '%s', '%f', '%f', '%s' )
);

$all_orders = SOM_Analytics::load_orders_with_items( $filters );
$ours       = array( $oid_a, $oid_b, $oid_c, $oid_d );
$orders     = array_values(
	array_filter(
		$all_orders,
		static function ( $o ) use ( $ours ) {
			return in_array( (int) $o->id, $ours, true );
		}
	)
);
$ids = array_map(
	static function ( $o ) {
		return (int) $o->id;
	},
	$all_orders
);
$assert( $oid_a > 0 && in_array( $oid_a, $ids, true ), 'order_a_included' );
$assert( $oid_b > 0 && in_array( $oid_b, $ids, true ), 'order_b_included' );
$assert( ! in_array( $oid_c, $ids, true ), 'cancelled_excluded' );
$assert( $oid_d > 0 && in_array( $oid_d, $ids, true ), 'order_d_included' );

$profit_a = null;
foreach ( $orders as $o ) {
	if ( (int) $o->id === $oid_a ) {
		$profit_a = SOM_Analytics::order_profit( $o );
		break;
	}
}
$out( 'profit_a', $profit_a );
$assert( null !== $profit_a, 'profit_a_present' );
$assert( $approx( $profit_a['revenue'], 10 ), 'profit_a_revenue' );
$assert( $approx( $profit_a['material'], 3 ), 'profit_a_material_from_log' ); // abs(-2)*1.5
$assert( $profit_a['fees'] > 0, 'profit_a_fees_estimate' );
$assert( $approx( $profit_a['profit'], 10 - 3 - $profit_a['fees'] ), 'profit_a_order_level' );

// Drop unpriced-only order.
$no_price = SOM_Analytics::order_profit(
	(object) array(
		'id'         => 0,
		'channel_id' => (int) $ebay->id,
		'items'      => array(
			(object) array( 'unit_price' => null, 'quantity' => 1 ),
		),
	)
);
$assert( null === $no_price, 'drop_null_unit_price' );

// Multi-line: revenue 20 only (null line dropped); fees once on 20.
$order_d_row = null;
foreach ( $orders as $o ) {
	if ( (int) $o->id === $oid_d ) {
		$order_d_row = $o;
		break;
	}
}
$profit_d = SOM_Analytics::order_profit( $order_d_row );
$out( 'profit_d', $profit_d );
$assert( null !== $profit_d && $approx( $profit_d['revenue'], 20 ), 'profit_d_drops_null_line' );

$agg = SOM_Analytics::aggregate_sales_profit_aov( $orders, $filters, $labels );
$out( 'sales', $agg['sales'] );
$out( 'totals', $agg['totals'] );
// A:10 + B:12 + D:20 = 42 (cancelled excluded)
$assert( $approx( $agg['totals']['sales'], 42 ), 'total_sales_42' );
$assert( 3 === (int) $agg['totals']['order_count'], 'priced_order_count_3' );
$assert( $approx( $agg['sales'][0], 10 ), 'sales_day1' );
$assert( $approx( $agg['sales'][1], 12 ), 'sales_day2_etsy_only' ); // cancelled not counted
$assert( $approx( $agg['sales'][2], 20 ), 'sales_day3' );

$by_ch = SOM_Analytics::aggregate_orders_by_channel( $orders, $filters );
$out( 'orders_by_channel', $by_ch );
$assert( count( $by_ch['labels'] ) >= 2, 'channel_bars_present' );
$sum_counts = array_sum( $by_ch['counts'] );
$assert( 3 === $sum_counts, 'channel_counts_3' );

$payload = SOM_Analytics::dashboard_payload(
	array_merge( $filters, array( 'material_ids' => array( $material_id ) ) )
);
$assert( isset( $payload['sales'], $payload['profit'], $payload['aov'], $payload['orders_by_channel'], $payload['stock'] ), 'payload_keys' );
$assert( count( $payload['stock']['series'] ) === 1, 'payload_stock_series' );

$view = SOM_PLUGIN_DIR . 'admin/views/analytics.php';
$js   = SOM_PLUGIN_DIR . 'admin/assets/js/analytics.js';
$assert( file_exists( $view ), 'view_exists' );
$assert( file_exists( $js ), 'js_exists' );

$out( 'summary', 0 === $fail ? 'PASS — Update Package 3 Sprint 4 smoke' : "FAIL — {$fail} assertion(s)" );
exit( 0 === $fail ? 0 : 1 );
