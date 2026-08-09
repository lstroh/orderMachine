<?php
/**
 * Update Package 3 Sprint 1 smoke: fee schema, seed defaults, estimate CRUD, tiers.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s1-smoke.php
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
SOM_Channel_Fee_Estimates::ensure_defaults();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( version_compare( SOM_VERSION, '0.19.0', '>=' ), 'SOM_VERSION_gte_0.19.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.7.0', '>=' ), 'som_db_version_gte_1.7.0' );

$tables = array(
	'channel_fee_estimates',
	'order_platform_fees',
	'recurring_platform_expenses',
);
foreach ( $tables as $suffix ) {
	$name   = SOM_DB::table( $suffix );
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) );
	$assert( $exists === $name, 'schema_' . $suffix );
}

$est_table = SOM_DB::table( 'channel_fee_estimates' );
foreach ( array( 'order_value_min', 'order_value_max', 'is_enabled', 'rate_type', 'fee_component' ) as $col ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( "SHOW COLUMNS FROM {$est_table} LIKE '{$col}'" );
	$assert( ! empty( $row ), 'estimates_col_' . $col );
}

$assert( is_readable( SOM_PLUGIN_DIR . 'admin/views/channel-fee-estimates.php' ), 'admin_view_exists' );
$assert( class_exists( 'SOM_Channel_Fee_Estimates' ), 'class_exists' );

$ebay = SOM_Channels::get_by_slug( 'ebay' );
$etsy = SOM_Channels::get_by_slug( 'etsy' );
$assert( (bool) $ebay, 'ebay_channel' );
$assert( (bool) $etsy, 'etsy_channel' );

$ebay_id = (int) $ebay->id;
$etsy_id = (int) $etsy->id;

$ebay_rows = SOM_Channel_Fee_Estimates::list_all( $ebay_id );
$etsy_rows = SOM_Channel_Fee_Estimates::list_all( $etsy_id );
$assert( count( $ebay_rows ) >= 5, 'ebay_seed_count_gte_5' );
$assert( count( $etsy_rows ) >= 7, 'etsy_seed_count_gte_7' );

$tier_low = SOM_Channel_Fee_Estimates::find_matching(
	array(
		'channel_id'      => $ebay_id,
		'fee_component'   => 'per_order_fee',
		'order_value_min' => null,
		'order_value_max' => 10.0,
	)
);
$tier_high = SOM_Channel_Fee_Estimates::find_matching(
	array(
		'channel_id'      => $ebay_id,
		'fee_component'   => 'per_order_fee',
		'order_value_min' => 10.0,
		'order_value_max' => null,
	)
);
$assert( $tier_low && abs( (float) $tier_low->rate_value - 0.30 ) < 0.0001, 'ebay_tier_under_10' );
$assert( $tier_high && abs( (float) $tier_high->rate_value - 0.40 ) < 0.0001, 'ebay_tier_at_or_above_10' );
$assert( $tier_low && SOM_Channel_Fee_Estimates::matches_order_value( $tier_low, 9.99 ), 'tier_low_matches_9_99' );
$assert( $tier_low && ! SOM_Channel_Fee_Estimates::matches_order_value( $tier_low, 10.0 ), 'tier_low_excludes_10' );
$assert( $tier_high && SOM_Channel_Fee_Estimates::matches_order_value( $tier_high, 10.0 ), 'tier_high_matches_10' );
$assert( $tier_high && ! SOM_Channel_Fee_Estimates::matches_order_value( $tier_high, 9.99 ), 'tier_high_excludes_9_99' );

$pp = SOM_Channel_Fee_Estimates::find_matching(
	array(
		'channel_id'      => $etsy_id,
		'fee_component'   => 'payment_processing',
		'order_value_min' => null,
		'order_value_max' => null,
	)
);
$pp_fixed = SOM_Channel_Fee_Estimates::find_matching(
	array(
		'channel_id'      => $etsy_id,
		'fee_component'   => 'payment_processing_fixed',
		'order_value_min' => null,
		'order_value_max' => null,
	)
);
$assert( $pp && 'percent' === $pp->rate_type && abs( (float) $pp->rate_value - 4.0 ) < 0.0001, 'etsy_payment_processing_pct' );
$assert( $pp_fixed && 'fixed' === $pp_fixed->rate_type && abs( (float) $pp_fixed->rate_value - 0.20 ) < 0.0001, 'etsy_payment_processing_fixed' );

$vat = SOM_Channel_Fee_Estimates::find_matching(
	array(
		'channel_id'      => $etsy_id,
		'fee_component'   => 'vat_on_fees',
		'order_value_min' => null,
		'order_value_max' => null,
	)
);
$assert( $vat && abs( (float) $vat->rate_value - 20.0 ) < 0.0001, 'etsy_vat_on_fees' );

$promoted = SOM_Channel_Fee_Estimates::find_matching(
	array(
		'channel_id'      => $ebay_id,
		'fee_component'   => 'promoted_listings',
		'order_value_min' => null,
		'order_value_max' => null,
	)
);
$offsite = SOM_Channel_Fee_Estimates::find_matching(
	array(
		'channel_id'      => $etsy_id,
		'fee_component'   => 'offsite_ads',
		'order_value_min' => null,
		'order_value_max' => null,
	)
);
$assert( $promoted && (int) $promoted->is_enabled === 1, 'promoted_listings_enabled_by_default' );
$assert( $offsite && (int) $offsite->is_enabled === 1, 'offsite_ads_enabled_by_default' );

// Idempotent seed: second call must not duplicate.
$count_before = count( SOM_Channel_Fee_Estimates::list_all() );
SOM_Channel_Fee_Estimates::ensure_defaults();
$count_after = count( SOM_Channel_Fee_Estimates::list_all() );
$assert( $count_before === $count_after, 'seed_idempotent' );

// CRUD round-trip.
$created = SOM_Channel_Fee_Estimates::create(
	array(
		'channel_id'      => $ebay_id,
		'fee_component'   => 'smoke_test_fee',
		'rate_type'       => 'fixed',
		'rate_value'      => 1.25,
		'order_value_min' => 5.0,
		'order_value_max' => 15.0,
		'is_enabled'      => 1,
		'notes'           => 'UP3 S1 smoke',
	)
);
$assert( ! is_wp_error( $created ) && (int) $created > 0, 'estimate_create' );
$created_id = (int) $created;

$row = SOM_Channel_Fee_Estimates::get( $created_id );
$assert( $row && 'smoke_test_fee' === $row->fee_component, 'estimate_get' );
$assert( $row && SOM_Channel_Fee_Estimates::matches_order_value( $row, 5.0 ), 'crud_tier_min_inclusive' );
$assert( $row && ! SOM_Channel_Fee_Estimates::matches_order_value( $row, 15.0 ), 'crud_tier_max_exclusive' );
$assert( $row && SOM_Channel_Fee_Estimates::matches_order_value( $row, 14.99 ), 'crud_tier_under_max' );

$updated = SOM_Channel_Fee_Estimates::update(
	$created_id,
	array(
		'rate_value' => 2.5,
		'is_enabled' => 0,
		'notes'      => 'updated',
	)
);
$assert( true === $updated, 'estimate_update' );
$row = SOM_Channel_Fee_Estimates::get( $created_id );
$assert( $row && abs( (float) $row->rate_value - 2.5 ) < 0.0001, 'estimate_rate_updated' );
$assert( $row && (int) $row->is_enabled === 0, 'estimate_disabled' );

// Seed must not overwrite user edits on matching key.
SOM_Channel_Fee_Estimates::ensure_defaults();
if ( $promoted ) {
	SOM_Channel_Fee_Estimates::update(
		(int) $promoted->id,
		array(
			'rate_value' => 9.9,
			'is_enabled' => 0,
		)
	);
	SOM_Channel_Fee_Estimates::ensure_defaults();
	$promoted_after = SOM_Channel_Fee_Estimates::get( (int) $promoted->id );
	$assert(
		$promoted_after
			&& abs( (float) $promoted_after->rate_value - 9.9 ) < 0.0001
			&& (int) $promoted_after->is_enabled === 0,
		'seed_preserves_user_edits'
	);
	// Restore seed-ish values for other tests / env cleanliness.
	SOM_Channel_Fee_Estimates::update(
		(int) $promoted->id,
		array(
			'rate_value' => 3.0,
			'is_enabled' => 1,
		)
	);
}

$deleted = SOM_Channel_Fee_Estimates::delete( $created_id );
$assert( true === $deleted, 'estimate_delete' );
$assert( null === SOM_Channel_Fee_Estimates::get( $created_id ), 'estimate_gone' );

$bad = SOM_Channel_Fee_Estimates::create(
	array(
		'channel_id'    => $ebay_id,
		'fee_component' => 'bad_tier',
		'rate_type'     => 'fixed',
		'rate_value'    => 1,
		'order_value_min' => 20,
		'order_value_max' => 10,
	)
);
$assert( is_wp_error( $bad ), 'rejects_inverted_tier' );

if ( $fail > 0 ) {
	echo "FAIL: {$fail} assertion(s)\n";
	exit( 1 );
}

echo "PASS — Update Package 3 Sprint 1 smoke\n";
exit( 0 );
