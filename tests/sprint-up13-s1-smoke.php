<?php
/**
 * UP13-S1 smoke: Amazon channel plus hand-added eBay and Amazon orders.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up13-s1-smoke.php
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
SOM_Production::init();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );

$known = SOM_Channels::known();
$assert( isset( $known['ebay'] ) && 'eBay' === $known['ebay'], 'ebay_unchanged' );
$assert( isset( $known['etsy'] ) && 'Etsy' === $known['etsy'], 'etsy_unchanged' );
$assert( isset( $known['external'] ) && 'External' === $known['external'], 'external_unchanged' );
$assert( isset( $known['internal'] ) && 'Internal' === $known['internal'], 'internal_unchanged' );
$assert( isset( $known['amazon'] ) && 'Amazon' === $known['amazon'], 'amazon_known' );

$amazon = SOM_Channels::get_by_slug( 'amazon' );
$assert( $amazon && 1 === (int) $amazon->is_active, 'amazon_active' );
$assert( $amazon && empty( $amazon->credentials ), 'amazon_no_credentials' );
$assert( $amazon && ! SOM_Channels::is_connected( 'amazon' ), 'amazon_no_oauth' );

$ebay_row = SOM_Channels::get_by_slug( 'ebay' );
$etsy_row = SOM_Channels::get_by_slug( 'etsy' );
$assert( $ebay_row && 'eBay' === (string) $ebay_row->display_name, 'ebay_row_unchanged' );
$assert( $etsy_row && 'Etsy' === (string) $etsy_row->display_name, 'etsy_row_unchanged' );

$list_view = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/orders-list.php' );
$menu_file = file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$sync_file = file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-order-sync.php' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'som_add_order' ), 'add_order_form' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_add_lines[0][house_number]"' ), 'add_order_house_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_add_lines[0][street_name]"' ), 'add_order_street_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_add_lines[0][style]"' ), 'add_order_style_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_add_lines[0][item_number]"' ), 'add_order_item_number_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, "name=\"som_add_channel\"" ), 'add_order_channel_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, "'ebay'" ) && false !== strpos( $list_view, "'amazon'" ), 'add_order_channels' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'som_create_test_order' ), 'test_order_form_remains' );
$assert( is_string( $list_view ) && false === strpos( $list_view, 'som_test_lines[0][house_number]' ), 'test_order_form_unchanged' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, "check_admin_referer( 'som_add_order', 'som_order_nonce' )" ), 'add_order_nonce' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, "current_user_can( 'manage_options' )" ), 'add_order_cap' );
$assert( is_string( $sync_file ) && false !== strpos( $sync_file, "array( 'ebay', 'etsy' )" ), 'sync_still_ebay_etsy' );

$suffix   = wp_generate_password( 4, false, false );
$order_no = 'UP13-' . $suffix;
$item_no  = 'up13-item-' . $suffix;

$count_orders = static function ( $slug, $external_id ) {
	global $wpdb;
	$channel = SOM_Channels::get_by_slug( $slug );
	if ( ! $channel ) {
		return 0;
	}
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'orders' ) . ' WHERE channel_id = %d AND external_order_id = %s',
			(int) $channel->id,
			$external_id
		)
	);
};

$wf = SOM_Workflows::create(
	array(
		'name'       => 'UP13-S1 Make ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 0,
	)
);
$assert( ! is_wp_error( $wf ), 'create_workflow' );
$wf_id = is_wp_error( $wf ) ? 0 : (int) $wf;
if ( $wf_id > 0 ) {
	$saved = SOM_Workflows::save_steps(
		$wf_id,
		array(
			array(
				'name'                    => 'UP13 Print ' . $suffix,
				'requires_manual_confirm' => 1,
			),
		)
	);
	$assert( true === $saved, 'save_steps' );
}

$product_id = 0;
if ( $wf_id > 0 ) {
	$created = SOM_Products::create(
		array(
			'name'                 => 'UP13-S1 Product ' . $suffix,
			'sku'                  => 'UP13S1-' . $suffix,
			'workflow_template_id' => $wf_id,
			'is_internal'          => 0,
			'is_active'            => 1,
		)
	);
	$assert( ! is_wp_error( $created ), 'create_product' );
	$product_id = is_wp_error( $created ) ? 0 : (int) $created;
}

$listing_id = 0;
if ( $product_id > 0 ) {
	$listing = SOM_Listings::create(
		array(
			'product_id'          => $product_id,
			'channel_slug'        => 'ebay',
			'external_listing_id' => $item_no,
			'title'               => 'UP13 listing ' . $suffix,
			'price'               => 4.5,
		)
	);
	$assert( ! is_wp_error( $listing ), 'create_listing_map' );
	$listing_id = is_wp_error( $listing ) ? 0 : (int) $listing;
}

$ebay_id = 0;
if ( $listing_id > 0 ) {
	$ebay = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'ebay',
			'external_order_id' => $order_no,
			'buyer_name'        => 'UP13 eBay ' . $suffix,
			'order_date'        => current_time( 'Y-m-d' ) . ' 00:00:00',
			'shipping_address'  => array(
				'full_name' => 'UP13 Ship',
				'line1'     => '1 High Street',
				'city'      => 'Leeds',
				'state'     => 'West Yorkshire',
				'postcode'  => 'LS1 1AA',
				'country'   => 'GB',
			),
			'items'             => array(
				array(
					'external_listing_id' => $item_no,
					'quantity'            => 1,
					'unit_price'          => 4.5,
					'house_number'        => '36',
					'street_name'         => 'Oak Lane',
					'style'               => 'Flourish',
				),
			),
		)
	);
	$assert( ! is_wp_error( $ebay ), 'ebay_order_created' );
	$ebay_id = is_wp_error( $ebay ) ? 0 : (int) $ebay;
}

if ( $ebay_id > 0 ) {
	$ebay_order = SOM_Orders::get( $ebay_id );
	$assert( $ebay_order && 'ebay' === (string) $ebay_order->channel_slug, 'ebay_channel' );
	$assert( $ebay_order && $order_no === (string) $ebay_order->external_order_id, 'ebay_order_number' );
	$ebay_item = ( $ebay_order && ! empty( $ebay_order->items ) ) ? $ebay_order->items[0] : null;
	$assert( $ebay_item && (int) $ebay_item->product_id === $product_id, 'ebay_line_matched' );
	$assert( $ebay_item && '36' === (string) $ebay_item->house_number, 'ebay_house_stored' );
	$assert( $ebay_item && 'Oak Lane' === (string) $ebay_item->street_name, 'ebay_street_stored' );
	$assert( $ebay_item && 'Flourish' === (string) $ebay_item->style, 'ebay_style_stored' );
	$assert( $ebay_item && $item_no === (string) $ebay_item->external_listing_id, 'ebay_item_number_stored' );
	$assert( SOM_Item_Make::has_item_progress( $ebay_id ), 'ebay_matched_has_workflow' );
}

$amazon_id = 0;
$amazon    = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'amazon',
		'external_order_id' => $order_no,
		'buyer_name'        => 'UP13 Amazon ' . $suffix,
		'order_date'        => current_time( 'Y-m-d' ) . ' 00:00:00',
		'shipping_address'  => array(
			'full_name' => 'UP13 Amazon Ship',
			'line1'     => '2 Market Street',
			'country'   => 'GB',
		),
		'items'             => array(
			array(
				'external_listing_id' => 'up13-unmatched-' . $suffix,
				'sku'                 => 'UP13-NO-MAP-' . $suffix,
				'quantity'            => 1,
				'unit_price'          => 9,
				'house_number'        => '12',
				'street_name'         => 'Baker Street',
				'style'               => 'Plain',
			),
		),
	)
);
$assert( ! is_wp_error( $amazon ), 'amazon_order_created' );
$amazon_id = is_wp_error( $amazon ) ? 0 : (int) $amazon;

if ( $amazon_id > 0 ) {
	$amazon_order = SOM_Orders::get( $amazon_id );
	$assert( $amazon_order && 'amazon' === (string) $amazon_order->channel_slug, 'amazon_channel' );
	$assert( $amazon_order && $order_no === (string) $amazon_order->external_order_id, 'amazon_same_number_other_channel' );
	$amazon_item = ( $amazon_order && ! empty( $amazon_order->items ) ) ? $amazon_order->items[0] : null;
	$assert( $amazon_item && empty( $amazon_item->product_id ), 'amazon_line_unmatched' );
	$assert( $amazon_order && ! empty( $amazon_order->has_unmatched ), 'amazon_order_still_saved' );
	$assert( $amazon_item && '12' === (string) $amazon_item->house_number, 'amazon_house_stored' );
	$assert( $amazon_item && 'Baker Street' === (string) $amazon_item->street_name, 'amazon_street_stored' );
	$assert( ! SOM_Item_Make::has_item_progress( $amazon_id ), 'amazon_unmatched_no_make' );
}

$ebay_dup = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'ebay',
		'external_order_id' => $order_no,
		'buyer_name'        => 'UP13 duplicate',
		'items'             => array(
			array(
				'external_listing_id' => 'up13-dup-' . $suffix,
				'quantity'            => 1,
				'house_number'        => '99',
				'street_name'         => 'Should Not Save',
			),
		),
	)
);
$assert( is_wp_error( $ebay_dup ) && 'som_order_exists' === $ebay_dup->get_error_code(), 'ebay_duplicate_rejected' );
$assert( 1 === $count_orders( 'ebay', $order_no ), 'ebay_duplicate_not_created' );

$amazon_dup = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'amazon',
		'external_order_id' => $order_no,
		'buyer_name'        => 'UP13 duplicate amazon',
		'items'             => array(
			array(
				'sku'      => 'UP13-DUP-' . $suffix,
				'quantity' => 1,
			),
		),
	)
);
$assert( is_wp_error( $amazon_dup ) && 'som_order_exists' === $amazon_dup->get_error_code(), 'amazon_duplicate_rejected' );
$assert( 1 === $count_orders( 'amazon', $order_no ), 'amazon_duplicate_not_created' );

if ( $ebay_id > 0 ) {
	$ebay_after = SOM_Orders::get( $ebay_id );
	$ebay_item  = ( $ebay_after && ! empty( $ebay_after->items ) ) ? $ebay_after->items[0] : null;
	$assert( $ebay_after && 1 === count( $ebay_after->items ), 'ebay_duplicate_did_not_add_line' );
	$assert( $ebay_item && '36' === (string) $ebay_item->house_number, 'ebay_house_not_rewritten' );
}

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK\n";
