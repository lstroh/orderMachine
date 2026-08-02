<?php
/**
 * Smoke: remove + restore seed data.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/seed-remove-restore-smoke.php
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

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();

$out( 'plugin', SOM_VERSION );
$assert( version_compare( SOM_VERSION, '0.18.1', '>=' ), 'version_gte_0.18.1' );
$assert( SOM_Seed::is_dummy_mode(), 'dummy_mode_on' );

// Ensure seed exists.
$restored = SOM_Seed::restore_seed_data( true );
$assert( ! is_wp_error( $restored ), 'initial_restore' );
$ids = SOM_Seed::resolve_seed_ids();
$out( 'seed_ids_after_restore', $ids );
$assert( $ids['product_id'] > 0, 'seed_product_present' );
$assert( $ids['workflow_id'] > 0, 'seed_workflow_present' );
$assert( count( $ids['material_ids'] ) >= 2, 'seed_materials_present' );
$assert( count( $ids['listing_ids'] ) >= 1, 'seed_listings_present' );
$assert( SOM_Channels::is_dummy( 'ebay' ), 'ebay_dummy_connected' );

// Sync fixtures so there are seed-related orders.
$sync = SOM_Order_Sync::sync_incremental();
$out( 'sync', $sync );
$orders_before = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'orders' ) );
$out( 'orders_before_remove', $orders_before );
$assert( $orders_before > 0, 'fixture_orders_exist' );

$removed = SOM_Seed::remove_seed_data();
$out( 'remove_summary', $removed );
$assert( (int) $removed['products'] >= 1, 'removed_product' );
$assert( (int) $removed['workflows'] >= 1, 'removed_workflow' );

$ids_after = SOM_Seed::resolve_seed_ids();
$assert( 0 === (int) $ids_after['product_id'], 'product_gone' );
$assert( 0 === (int) $ids_after['workflow_id'], 'workflow_gone' );
$assert( empty( $ids_after['listing_ids'] ), 'listings_gone' );
$assert( ! SOM_Channels::is_connected( 'ebay' ) || ! SOM_Channels::is_dummy( 'ebay' ), 'ebay_dummy_cleared' );

$sku_left = (int) $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'products' ) . ' WHERE sku = %s',
		SOM_Seed::SAMPLE_PRODUCT_SKU
	)
);
$assert( 0 === $sku_left, 'sku_absent' );

// Restore again.
$restored2 = SOM_Seed::restore_seed_data( true );
$assert( ! is_wp_error( $restored2 ), 'second_restore' );
$ids2 = SOM_Seed::resolve_seed_ids();
$assert( $ids2['product_id'] > 0, 'product_restored' );
$assert( $ids2['workflow_id'] > 0, 'workflow_restored' );
$assert( SOM_Channels::is_dummy( 'ebay' ), 'ebay_dummy_restored' );

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAIL (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'PASS — seed remove/restore smoke' );
