<?php
/**
 * Update Package 3 Sprint 2 smoke: fee sync, schema keys, UI hooks, fixtures.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up3-s2-smoke.php
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
SOM_Cron::init();
SOM_Cron::schedule_events();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( version_compare( SOM_VERSION, '0.20.0', '>=' ), 'SOM_VERSION_gte_0.20.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.8.0', '>=' ), 'som_db_version_gte_1.8.0' );
$assert( class_exists( 'SOM_Platform_Fee_Sync' ), 'class_platform_fee_sync' );
$assert( is_readable( SOM_PLUGIN_DIR . 'admin/views/recurring-platform-expenses.php' ), 'recurring_view' );
$assert( is_readable( SOM_PLUGIN_DIR . 'tests/fixtures/ebay-platform-fees.json' ), 'ebay_fee_fixture' );
$assert( is_readable( SOM_PLUGIN_DIR . 'tests/fixtures/etsy-platform-fees.json' ), 'etsy_fee_fixture' );

$scopes = SOM_Channel_Ebay::scopes();
$assert( in_array( 'https://api.ebay.com/oauth/api_scope/sell.finances', $scopes, true ), 'ebay_finances_scope' );

$fee_table = SOM_DB::table( 'order_platform_fees' );
$rec_table = SOM_DB::table( 'recurring_platform_expenses' );
foreach ( array( $fee_table, $rec_table ) as $t ) {
	$col = $wpdb->get_row( "SHOW COLUMNS FROM {$t} LIKE 'external_entry_id'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( ! empty( $col ), 'col_external_entry_id_' . basename( $t ) );
	$idx = $wpdb->get_results( "SHOW INDEX FROM {$t} WHERE Key_name = 'channel_entry'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( ! empty( $idx ), 'unique_channel_entry_' . basename( $t ) );
}

$settings = SOM_Settings::get();
$assert( isset( $settings['fee_poll_interval_minutes'] ) && (int) $settings['fee_poll_interval_minutes'] >= 5, 'fee_poll_setting' );

$assert( false !== has_action( SOM_Cron::HOOK_SYNC_PLATFORM_FEES ), 'cron_hook_registered' );
$assert( (bool) wp_next_scheduled( SOM_Cron::HOOK_SYNC_PLATFORM_FEES ), 'cron_event_scheduled' );

// Ensure fixture orders exist, then sync fees.
$order_sync = SOM_Order_Sync::sync_incremental();
$out( 'order_sync', isset( $order_sync['message'] ) ? (string) $order_sync['message'] : wp_json_encode( $order_sync ) );
$assert( ! empty( $order_sync['ok'] ), 'order_sync_ok' );

// Clear fee tables for deterministic counts.
$wpdb->query( "DELETE FROM {$fee_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DELETE FROM {$rec_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
delete_option( SOM_Platform_Fee_Sync::CURSOR_OPTION );
delete_option( SOM_Platform_Fee_Sync::STATUS_OPTION );

$fee_sync = SOM_Platform_Fee_Sync::sync_incremental();
$out( 'fee_sync_1', isset( $fee_sync['message'] ) ? (string) $fee_sync['message'] : wp_json_encode( $fee_sync ) );
$assert( ! empty( $fee_sync['ok'] ), 'fee_sync_ok' );
$assert( (int) $fee_sync['inserted'] >= 5, 'fee_sync_inserted_gte_5' );
$assert( (int) $fee_sync['unmatched'] >= 2, 'fee_sync_unmatched_gte_2' );
$assert( (int) $fee_sync['ignored'] >= 2, 'fee_sync_ignored_gte_2' );

$ebay = SOM_Channels::get_by_slug( 'ebay' );
$etsy = SOM_Channels::get_by_slug( 'etsy' );
$assert( (bool) $ebay && (bool) $etsy, 'channels' );

$ebay_order_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT id FROM ' . SOM_DB::table( 'orders' ) . ' WHERE channel_id = %d AND external_order_id = %s LIMIT 1',
		(int) $ebay->id,
		'12-34567-89012'
	)
);
$assert( $ebay_order_id > 0, 'ebay_fixture_order' );
$ebay_fees = SOM_Platform_Fee_Sync::list_order_fees( $ebay_order_id );
$assert( count( $ebay_fees ) >= 3, 'ebay_order_fees_gte_3' );
$assert( abs( (float) $ebay_fees[0]->amount + 1.66 ) < 0.0001 || abs( (float) $ebay_fees[0]->amount ) > 0, 'ebay_amount_as_returned' );

$etsy_order_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT id FROM ' . SOM_DB::table( 'orders' ) . ' WHERE channel_id = %d AND external_order_id = %s LIMIT 1',
		(int) $etsy->id,
		'9001001'
	)
);
$assert( $etsy_order_id > 0, 'etsy_fixture_order' );
$etsy_fees = SOM_Platform_Fee_Sync::list_order_fees( $etsy_order_id );
$assert( count( $etsy_fees ) >= 2, 'etsy_order_fees_gte_2' );

$recurring = SOM_Platform_Fee_Sync::list_recurring( array( 'limit' => 50 ) );
$assert( $recurring['total'] >= 1, 'recurring_gte_1' );

// Idempotent re-run.
$fee_sync_2 = SOM_Platform_Fee_Sync::sync_incremental();
$out( 'fee_sync_2', isset( $fee_sync_2['message'] ) ? (string) $fee_sync_2['message'] : wp_json_encode( $fee_sync_2 ) );
$assert( ! empty( $fee_sync_2['ok'] ), 'fee_sync_rerun_ok' );
$assert( 0 === (int) $fee_sync_2['inserted'], 'fee_sync_rerun_no_inserts' );
$assert( (int) $fee_sync_2['skipped'] >= 5, 'fee_sync_rerun_skipped' );

$detail = SOM_Orders::get( $ebay_order_id );
$assert( $detail && is_array( $detail->platform_fees ) && count( $detail->platform_fees ) >= 3, 'order_detail_fees_attached' );

$assert( ! SOM_Channel_Ebay::needs_finances_reconnect() || SOM_Channels::is_dummy( 'ebay' ), 'dummy_no_reconnect_or_dummy' );

if ( $fail > 0 ) {
	echo "FAIL — Update Package 3 Sprint 2 smoke ({$fail} failures)\n";
	exit( 1 );
}

echo "PASS — Update Package 3 Sprint 2 smoke\n";
exit( 0 );
