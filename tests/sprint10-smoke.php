<?php
/**
 * Sprint 10 smoke: seed listings, refresh/push dummy path, variation qty.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint10-smoke.php
 *
 * @package OrderMachine
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

$out = static function ( $label, $value ) {
	echo $label . ': ' . ( is_string( $value ) ? $value : wp_json_encode( $value ) ) . "\n";
};

SOM_DB::maybe_upgrade();
SOM_Seed::maybe_seed_catalogue();

$version = get_option( 'som_db_version' );
$out( 'som_db_version', $version );
$out( 'plugin', SOM_VERSION );

global $wpdb;
$listings_t = SOM_DB::table( 'listings' );
$count      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$listings_t}" );
$out( 'listing_count', $count );

$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$listings_t}", 0 );
$out( 'has_description', in_array( 'description', $cols, true ) ? 'yes' : 'no' );
$out( 'has_inventory_json', in_array( 'inventory_json', $cols, true ) ? 'yes' : 'no' );

$ebay_var = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT l.id, l.quantity_available, l.inventory_json FROM {$listings_t} l
		INNER JOIN {$wpdb->prefix}som_channels c ON c.id = l.channel_id
		WHERE c.slug = %s AND l.external_listing_id = %s LIMIT 1",
		'ebay',
		SOM_Seed::EBAY_VARIATION_LISTING_ID
	)
);
$etsy = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT l.id, l.quantity_available, l.inventory_json FROM {$listings_t} l
		INNER JOIN {$wpdb->prefix}som_channels c ON c.id = l.channel_id
		WHERE c.slug = %s AND l.external_listing_id = %s LIMIT 1",
		'etsy',
		SOM_Seed::ETSY_LISTING_ID
	)
);

if ( ! $ebay_var || ! $etsy ) {
	echo "FAIL: missing variation seed listings\n";
	exit( 1 );
}

$ebay_inv = SOM_Listings::decode_inventory( $ebay_var->inventory_json );
$etsy_inv = SOM_Listings::decode_inventory( $etsy->inventory_json );
$out( 'ebay_var_mode', $ebay_inv['mode'] );
$out( 'ebay_var_count', count( $ebay_inv['variations'] ) );
$out( 'etsy_mode', $etsy_inv['mode'] );
$out( 'etsy_var_count', count( $etsy_inv['variations'] ) );

$refresh = SOM_Listings::refresh( (int) $ebay_var->id );
$out( 'refresh_ebay_var', is_wp_error( $refresh ) ? $refresh->get_error_message() : 'ok' );

$listing = SOM_Listings::get( (int) $ebay_var->id );
$out( 'after_refresh_title', $listing ? (string) $listing->title : '' );
$out( 'after_refresh_qty', $listing ? (int) $listing->quantity_available : 0 );

$update = SOM_Listings::update_local(
	(int) $etsy->id,
	array(
		'price'     => 15.49,
		'inventory' => array(
			'mode'       => 'variations',
			'sku'        => '',
			'variations' => array(
				array(
					'sku'         => 'ETSY-BIN-S',
					'quantity'    => 9,
					'options'     => array( 'Size' => 'Small' ),
					'external_id' => '9001',
				),
				array(
					'sku'         => 'ETSY-BIN-M',
					'quantity'    => 7,
					'options'     => array( 'Size' => 'Medium' ),
					'external_id' => '9002',
				),
				array(
					'sku'         => 'ETSY-BIN-L',
					'quantity'    => 3,
					'options'     => array( 'Size' => 'Large' ),
					'external_id' => '9003',
				),
			),
		),
	)
);
$out( 'update_etsy', is_wp_error( $update ) ? $update->get_error_message() : 'ok' );

$push = SOM_Listings::push( (int) $etsy->id );
$out( 'push_etsy', is_wp_error( $push ) ? $push->get_error_message() : 'ok' );

$again = SOM_Listings::refresh( (int) $etsy->id );
$out( 'refresh_after_push', is_wp_error( $again ) ? $again->get_error_message() : 'ok' );
$etsy2 = SOM_Listings::get( (int) $etsy->id );
$out( 'etsy_qty_after', $etsy2 ? (int) $etsy2->quantity_available : 0 );
$out( 'etsy_price_after', $etsy2 ? (float) $etsy2->price : 0 );

$query = SOM_Listings::query( array( 'channel' => 'ebay' ) );
$out( 'ebay_list_count', $query['total'] );

$ok = ! is_wp_error( $refresh )
	&& ! is_wp_error( $update )
	&& ! is_wp_error( $push )
	&& ! is_wp_error( $again )
	&& $etsy2
	&& 19 === (int) $etsy2->quantity_available
	&& abs( (float) $etsy2->price - 15.49 ) < 0.001
	&& 'variations' === $ebay_inv['mode']
	&& 'variations' === $etsy_inv['mode'];

echo $ok ? "PASS\n" : "FAIL\n";
exit( $ok ? 0 : 1 );
