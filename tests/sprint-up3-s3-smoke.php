<?php
/**
 * Update Package 3 Sprint 3 smoke: fee-aware costing + budgets.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s3-smoke.php
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

$assert( version_compare( SOM_VERSION, '0.21.0', '>=' ), 'SOM_VERSION_gte_0.21.0' );
$assert( class_exists( 'SOM_Platform_Fees' ), 'class_platform_fees' );

$ebay = SOM_Channels::get_by_slug( 'ebay' );
$etsy = SOM_Channels::get_by_slug( 'etsy' );
$assert( $ebay && $etsy, 'channels_present' );

// Estimate: eBay £10 → per_order £0.40 tier + percents + promoted; no vat_on_fees on eBay seed.
$ebay_est = SOM_Platform_Fees::estimate_total( (int) $ebay->id, 10.0 );
$out( 'ebay_est', $ebay_est );
$assert( $approx( $ebay_est['total'], 2.02 ), 'ebay_estimate_total_10' );
$assert( $approx( $ebay_est['percent'], 20.2 ), 'ebay_estimate_pct_10' );

// Estimate: eBay £9.99 → per_order £0.30.
$ebay_low = SOM_Platform_Fees::estimate_total( (int) $ebay->id, 9.99 );
$assert( $approx( $ebay_low['total'], 1.917, 0.05 ), 'ebay_estimate_total_under_10' );

// Etsy includes listing_fee + vat_on_fees on other components + offsite ads.
$etsy_est = SOM_Platform_Fees::estimate_total( (int) $etsy->id, 6.0 );
$out( 'etsy_est', $etsy_est );
$assert( $etsy_est['total'] > 1.0, 'etsy_estimate_includes_stack' );
$vat_seen = false;
$listing_seen = false;
foreach ( $etsy_est['components'] as $comp ) {
	if ( 'vat_on_fees' === $comp['fee_component'] ) {
		$vat_seen = true;
	}
	if ( 'listing_fee' === $comp['fee_component'] ) {
		$listing_seen = true;
	}
}
$assert( $vat_seen, 'etsy_vat_on_fees_applied' );
$assert( $listing_seen, 'etsy_listing_fee_included' );

// Product with target — fee-aware recipe_costing.
$product_id = SOM_Products::create(
	array(
		'name'                 => 'UP3-S3 Costing Product',
		'sku'                  => 'UP3-S3-' . wp_generate_password( 6, false ),
		'is_active'            => 1,
		'target_selling_price' => 10.00,
	)
);
$assert( ! is_wp_error( $product_id ), 'product_create' );
$product_id = (int) $product_id;

$costing = SOM_Products::recipe_costing( $product_id );
$assert( is_array( $costing ), 'recipe_costing_array' );
$assert( isset( $costing['fee_channels'] ) && count( $costing['fee_channels'] ) >= 2, 'fee_channels_present' );
$assert( 'estimate' === $costing['fee_source'] || 'actual' === $costing['fee_source'], 'fee_source_set' );
$assert( null !== $costing['platform_fees'] && (float) $costing['platform_fees'] > 0, 'platform_fees_positive' );
$assert( null !== $costing['material_only_profit'], 'material_only_profit_kept' );
$assert(
	null !== $costing['profit'] && (float) $costing['profit'] < (float) $costing['material_only_profit'],
	'fee_aware_profit_less_than_material_only'
);

$ebay_row = null;
foreach ( $costing['fee_channels'] as $row ) {
	if ( 'ebay' === $row['channel_slug'] ) {
		$ebay_row = $row;
		break;
	}
}
$assert( $ebay_row && 'target' === $ebay_row['representative_source'], 'rep_price_target_without_listing' );
$assert( $ebay_row && null === $ebay_row['variance_pp'], 'no_variance_without_actuals' );

// Sync fixture fees then confirm actual path when product is on a fee order.
SOM_Order_Sync::sync_incremental();
SOM_Platform_Fee_Sync::sync_incremental();

$orders_t = SOM_DB::table( 'orders' );
$items_t  = SOM_DB::table( 'order_items' );
$fees_t   = SOM_DB::table( 'order_platform_fees' );

$fee_order = $wpdb->get_row(
	"SELECT o.id, o.channel_id FROM {$orders_t} o
	INNER JOIN {$fees_t} f ON f.order_id = o.id
	WHERE o.channel_id = " . (int) $ebay->id . '
	GROUP BY o.id LIMIT 1'
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$assert( $fee_order, 'ebay_fee_order_exists' );

if ( $fee_order ) {
	$wpdb->insert(
		$items_t,
		array(
			'order_id'   => (int) $fee_order->id,
			'product_id' => $product_id,
			'quantity'   => 1,
			'unit_price' => 10.00,
		),
		array( '%d', '%d', '%d', '%f' )
	);

	$actuals = SOM_Platform_Fees::product_channel_actuals( $product_id, (int) $ebay->id );
	$out( 'actuals', $actuals );
	$assert( (int) $actuals['order_count'] >= 1, 'actual_order_count' );
	$assert( (float) $actuals['total_fees'] > 0, 'actual_total_fees' );

	$costing2 = SOM_Products::recipe_costing( $product_id );
	$ebay_row2 = null;
	foreach ( $costing2['fee_channels'] as $row ) {
		if ( 'ebay' === $row['channel_slug'] ) {
			$ebay_row2 = $row;
			break;
		}
	}
	$assert( $ebay_row2 && 'actual' === $ebay_row2['fee_source'], 'ebay_fee_source_actual' );
	$assert( $ebay_row2 && null !== $ebay_row2['variance_pp'], 'variance_computed' );

	$abs_fee = SOM_Platform_Fees::order_actual_fee_total( (int) $fee_order->id );
	$assert( null !== $abs_fee && $abs_fee > 0, 'order_actual_abs' );

	$line = SOM_Platform_Fees::line_profit( 10.0, 1, 0.5, (int) $fee_order->id, (int) $ebay->id );
	$out( 'line_profit', $line );
	$assert( 'actual' === $line['fee_source'], 'line_profit_actual' );
	$assert( $approx( $line['fees'], $abs_fee ), 'line_profit_uses_full_order_fees' );

	// Estimate path when order has no fees.
	$est_line = SOM_Platform_Fees::line_profit( 10.0, 1, 0.5, 0, (int) $ebay->id );
	$assert( 'estimate' === $est_line['fee_source'], 'line_profit_estimate_fallback' );
}

// Listing price becomes representative when linked.
$listing_id = SOM_Listings::create(
	array(
		'product_id'          => $product_id,
		'channel_slug'        => 'ebay',
		'external_listing_id' => 'up3-s3-' . wp_generate_password( 6, false ),
		'title'               => 'UP3 S3 listing',
		'price'               => 12.50,
		'quantity_available'  => 1,
	)
);
$assert( ! is_wp_error( $listing_id ), 'listing_create' );

$costing3 = SOM_Products::recipe_costing( $product_id );
$ebay_row3 = null;
foreach ( $costing3['fee_channels'] as $row ) {
	if ( 'ebay' === $row['channel_slug'] ) {
		$ebay_row3 = $row;
		break;
	}
}
$assert( $ebay_row3 && 'listing' === $ebay_row3['representative_source'], 'rep_price_listing_when_linked' );
$assert( $ebay_row3 && $approx( $ebay_row3['representative_price'], 12.50 ), 'rep_price_12_50' );

$out( 'summary', 0 === $fail ? 'PASS — Update Package 3 Sprint 3 smoke' : "FAIL — {$fail} assertion(s)" );
exit( 0 === $fail ? 0 : 1 );
