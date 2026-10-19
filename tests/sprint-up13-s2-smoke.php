<?php
/**
 * UP13-S2 smoke: import eBay, Amazon, and Order Machine order files.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up13-s2-smoke.php
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
$money = static function ( $value ) {
	return number_format( (float) $value, 2, '.', '' );
};
$codes_of = static function ( $parsed ) {
	$codes = array();
	if ( empty( $parsed['errors'] ) || ! is_array( $parsed['errors'] ) ) {
		return $codes;
	}
	foreach ( $parsed['errors'] as $error ) {
		$codes[] = isset( $error['code'] ) ? (string) $error['code'] : '';
	}
	return $codes;
};
$order_by_id = static function ( $parsed, $external_id ) {
	if ( empty( $parsed['orders'] ) || ! is_array( $parsed['orders'] ) ) {
		return null;
	}
	foreach ( $parsed['orders'] as $order ) {
		if ( isset( $order['external_order_id'] ) && (string) $order['external_order_id'] === (string) $external_id ) {
			return $order;
		}
	}
	return null;
};

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();
SOM_Production::init();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );

$list_view    = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/orders-list.php' );
$menu_file    = file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$import_file  = file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-order-import.php' );
$guide        = file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md' );
$header_exact = implode( ',', SOM_Order_Import::TEMPLATE_HEADERS );

$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_import_file"' ), 'import_file_input' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'som_preview_orders' ), 'import_preview_button' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'som_import_orders' ), 'import_commit_button' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, "wp_nonce_field( 'som_import_orders_preview'" ), 'import_preview_nonce' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, "wp_nonce_field( 'som_import_orders_commit'" ), 'import_commit_nonce' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'enctype="multipart/form-data"' ), 'import_multipart' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, "check_admin_referer( 'som_import_orders_preview', 'som_order_nonce' )" ), 'import_preview_nonce_check' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, "check_admin_referer( 'som_import_orders_commit', 'som_order_nonce' )" ), 'import_commit_nonce_check' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, "current_user_can( 'manage_options' )" ), 'import_cap_gate' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, 'Created %1$d, skipped %2$d already imported, failed %3$d.' ), 'import_result_message' );
$assert( is_string( $import_file ) && false !== strpos( $import_file, 'SOM_Order_Sync::create_from_external' ), 'uses_create_from_external' );

$assert( is_string( $list_view ) && false !== strpos( $list_view, 'som_add_order' ), 'add_order_form' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_add_lines[0][house_number]"' ), 'add_order_house_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_add_lines[0][street_name]"' ), 'add_order_street_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_add_lines[0][style]"' ), 'add_order_style_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_add_lines[0][item_number]"' ), 'add_order_item_number_field' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'som_create_test_order' ), 'test_order_form_remains' );
$assert( is_string( $list_view ) && false === strpos( $list_view, 'som_test_lines[0][house_number]' ), 'test_order_form_unchanged' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, "check_admin_referer( 'som_add_order', 'som_order_nonce' )" ), 'add_order_nonce' );

$assert( $header_exact === 'channel,order_number,order_date,buyer_name,address_1,address_2,city,county,postcode,country,item_number,sku,quantity,unit_price,house_number,street_name,style', 'template_header_exact' );
$assert( is_string( $guide ) && false !== strpos( $guide, $header_exact ), 'guide_template_header' );
$assert( is_string( $guide ) && false !== strpos( $guide, 'Download report' ), 'guide_ebay_download' );
$assert( is_string( $guide ) && false !== strpos( $guide, 'Order Reports' ), 'guide_amazon_download' );

global $wpdb;
$orders_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'orders' ) );

$ebay_csv     = file_get_contents( SOM_PLUGIN_DIR . 'tests/fixtures/ebay-orders-import.csv' );
$amazon_txt   = file_get_contents( SOM_PLUGIN_DIR . 'tests/fixtures/amazon-orders-import.txt' );
$template_csv = file_get_contents( SOM_PLUGIN_DIR . 'tests/fixtures/ordermachine-orders-import.csv' );
$assert( is_string( $ebay_csv ) && is_string( $amazon_txt ) && is_string( $template_csv ), 'fixtures_readable' );

$ebay = SOM_Order_Import::parse( (string) $ebay_csv );
$assert( 'ebay' === $ebay['format'], 'ebay_format' );
$assert( 1 === (int) $ebay['order_count'], 'ebay_grouped_one_order' );
$assert( 2 === (int) $ebay['line_count'], 'ebay_grouped_two_lines' );
$assert( 0 === (int) $ebay['failed'], 'ebay_no_failed_orders' );
$assert( array() === $codes_of( $ebay ), 'ebay_no_row_errors' );
$ebay_order = $order_by_id( $ebay, '14-13002-26019' );
$assert( is_array( $ebay_order ) && 2 === count( $ebay_order['items'] ), 'ebay_order_lines' );
if ( is_array( $ebay_order ) ) {
	$assert( 'Jane Buyer' === $ebay_order['buyer_name'], 'ebay_buyer_from_first_row' );
	$assert( '2026-10-01 00:00:00' === $ebay_order['order_date'], 'ebay_date_from_first_row' );
	$address = $ebay_order['shipping_address'];
	$assert( 'Jane Post' === $address['full_name'], 'ebay_post_to_name' );
	$assert( '10 High Street' === $address['line1'], 'ebay_post_to_address_not_buyer_address' );
	$assert( '99 Buyer Street' !== $address['line1'], 'ebay_buyer_address_ignored' );
	$assert( 'Flat 2' === $address['line2'], 'ebay_address_2_from_first_row' );
	$assert( 'Leeds' === $address['city'], 'ebay_city_from_first_row' );
	$assert( 'West Yorkshire' === $address['state'], 'ebay_state' );
	$assert( 'LS1 1AA' === $address['postcode'], 'ebay_postcode' );
	$assert( 'GB' === $address['country'], 'ebay_country' );
	$line_a = $ebay_order['items'][0];
	$line_b = $ebay_order['items'][1];
	$assert( '111222333' === $line_a['external_listing_id'], 'ebay_item_number' );
	$assert( 'SIGN-A' === $line_a['sku'], 'ebay_custom_label' );
	$assert( 1 === (int) $line_a['quantity'], 'ebay_line_a_qty' );
	$assert( '12.50' === $money( $line_a['unit_price'] ), 'ebay_sold_for_is_unit_price' );
	$assert( '36' === $line_a['house_number'], 'ebay_variation_house' );
	$assert( 'Oak Lane' === $line_a['street_name'], 'ebay_variation_street' );
	$assert( 'Flourish' === $line_a['style'], 'ebay_variation_design_not_title' );
	$assert( '444555666' === $line_b['external_listing_id'], 'ebay_line_b_item_number' );
	$assert( 'SIGN-B' === $line_b['sku'], 'ebay_line_b_sku' );
	$assert( 2 === (int) $line_b['quantity'], 'ebay_line_b_qty' );
	$assert( '8.00' === $money( $line_b['unit_price'] ), 'ebay_sold_for_not_divided' );
	$assert( '10' === $line_b['house_number'], 'ebay_alias_house_no' );
	$assert( 'Mill Road' === $line_b['street_name'], 'ebay_alias_street' );
	$assert( 'Plain Sign' === $line_b['style'], 'ebay_title_when_no_design' );
}

$ebay_bom = SOM_Order_Import::parse( "\xEF\xBB\xBF" . (string) $ebay_csv );
$assert( 'ebay' === $ebay_bom['format'] && 1 === (int) $ebay_bom['order_count'], 'ebay_bom_stripped' );

$ebay_tab = SOM_Order_Import::parse( str_replace( ',', "\t", (string) $ebay_csv ) );
$ebay_tab_order = $order_by_id( $ebay_tab, '14-13002-26019' );
$assert( 'ebay' === $ebay_tab['format'] && is_array( $ebay_tab_order ) && 2 === count( $ebay_tab_order['items'] ), 'ebay_tab_delimiter' );
$assert( is_array( $ebay_tab_order ) && '36' === $ebay_tab_order['items'][0]['house_number'], 'ebay_tab_house' );

$amazon = SOM_Order_Import::parse( (string) $amazon_txt );
$assert( 'amazon' === $amazon['format'], 'amazon_format' );
$assert( 1 === (int) $amazon['order_count'] && 1 === (int) $amazon['line_count'], 'amazon_one_line' );
$amazon_order = $order_by_id( $amazon, '123-7654321-1300219' );
$assert( is_array( $amazon_order ), 'amazon_order' );
if ( is_array( $amazon_order ) ) {
	$assert( 'Sam Recipient' === $amazon_order['buyer_name'], 'amazon_buyer_fallback' );
	$assert( '2026-10-02 09:15:00' === $amazon_order['order_date'], 'amazon_purchase_date' );
	$amz_address = $amazon_order['shipping_address'];
	$assert( '10 King Street' === $amz_address['line1'], 'amazon_address_1' );
	$assert( 'Unit 4, Dock 3' === $amz_address['line2'], 'amazon_address_3_kept' );
	$assert( 'Bristol' === $amz_address['city'], 'amazon_city' );
	$assert( 'BS1 4DJ' === $amz_address['postcode'], 'amazon_postcode' );
	$assert( 'GB' === $amz_address['country'], 'amazon_country' );
	$amz_line = $amazon_order['items'][0];
	$assert( 2 === (int) $amz_line['quantity'], 'amazon_qty' );
	$assert( '9.99' === $money( $amz_line['unit_price'] ), 'amazon_unit_price_is_line_total_over_qty' );
	$assert( 'AMZ-SKU-1' === $amz_line['sku'], 'amazon_sku' );
	$assert( empty( $amz_line['external_listing_id'] ), 'amazon_sku_not_stored_as_listing_id' );
	$assert( 'Oak House Sign' === $amz_line['style'], 'amazon_product_name_style' );
}

$amazon_comma = SOM_Order_Import::parse( "order-id,quantity-purchased,item-price,sku,product-name\nCOMMA-AMZ,4,10.00,SKU-C,Comma Name\n" );
$amazon_comma_order = $order_by_id( $amazon_comma, 'COMMA-AMZ' );
$assert( 'amazon' === $amazon_comma['format'] && is_array( $amazon_comma_order ), 'amazon_comma_delimiter' );
$assert( is_array( $amazon_comma_order ) && '2.50' === $money( $amazon_comma_order['items'][0]['unit_price'] ), 'amazon_comma_unit_price' );

$template = SOM_Order_Import::parse( (string) $template_csv );
$assert( 'template' === $template['format'], 'template_format' );
$assert( 2 === (int) $template['order_count'] && 3 === (int) $template['line_count'], 'template_grouped' );
$assert( array() === $codes_of( $template ), 'template_no_row_errors' );
$om = $order_by_id( $template, 'OM-1001' );
$az = $order_by_id( $template, 'AMZ-2002' );
$assert( is_array( $om ) && 'ebay' === $om['channel'] && 2 === count( $om['items'] ), 'template_ebay_group' );
$assert( is_array( $az ) && 'amazon' === $az['channel'] && 1 === count( $az['items'] ), 'template_amazon_order' );
if ( is_array( $om ) ) {
	$assert( 'Pat Buyer' === $om['buyer_name'], 'template_buyer_from_first_row' );
	$assert( 'York' === $om['shipping_address']['city'], 'template_city_from_first_row' );
	$assert( 'North Yorkshire' === $om['shipping_address']['state'], 'template_county_is_state' );
	$assert( '14' === $om['items'][0]['house_number'] && 'River Road' === $om['items'][0]['street_name'], 'template_line_a_address' );
	$assert( 'Classic' === $om['items'][0]['style'] && '6.50' === $money( $om['items'][0]['unit_price'] ), 'template_line_a_price' );
	$assert( '999888777' === $om['items'][0]['external_listing_id'], 'template_item_number' );
	$assert( '15' === $om['items'][1]['house_number'] && 'Bridge Street' === $om['items'][1]['street_name'], 'template_line_b_address' );
	$assert( 'Modern' === $om['items'][1]['style'] && '3.25' === $money( $om['items'][1]['unit_price'] ), 'template_unit_price_not_divided' );
	$assert( 2 === (int) $om['items'][1]['quantity'], 'template_line_b_qty' );
}
if ( is_array( $az ) ) {
	$assert( '22' === $az['items'][0]['house_number'] && 'Park Lane' === $az['items'][0]['street_name'], 'template_amazon_house_street' );
	$assert( 'Bold' === $az['items'][0]['style'] && '4.25' === $money( $az['items'][0]['unit_price'] ), 'template_amazon_style_price' );
	$assert( 'AMZ-OM-1' === $az['items'][0]['sku'] && empty( $az['items'][0]['external_listing_id'] ), 'template_amazon_sku' );
}

$alias = SOM_Order_Import::parse( "Order Number,Quantity,Sold For,Variation Details,Item Title\nALIAS-1,1,5.00,\"House #=8; Road Name=Hill Street; eBay Design=Stripe\",Title Fallback\n" );
$alias_order = $order_by_id( $alias, 'ALIAS-1' );
$assert( is_array( $alias_order ), 'alias_parsed' );
if ( is_array( $alias_order ) ) {
	$assert( '8' === $alias_order['items'][0]['house_number'], 'alias_house_hash' );
	$assert( 'Hill Street' === $alias_order['items'][0]['street_name'], 'alias_road_name' );
	$assert( 'Stripe' === $alias_order['items'][0]['style'], 'alias_ebay_design_not_title' );
	$assert( '5.00' === $money( $alias_order['items'][0]['unit_price'] ), 'alias_price' );
}

$newline = SOM_Order_Import::parse( "Order Number,Quantity,Variation Details,Item Title\nNL-1,1,\"House Number: 7\nStreet Name: New Road\",Fallback Title\n" );
$newline_order = $order_by_id( $newline, 'NL-1' );
$assert( is_array( $newline_order ), 'newline_variation_parsed' );
if ( is_array( $newline_order ) ) {
	$assert( '7' === $newline_order['items'][0]['house_number'], 'newline_house' );
	$assert( 'New Road' === $newline_order['items'][0]['street_name'], 'newline_street' );
	$assert( 'Fallback Title' === $newline_order['items'][0]['style'], 'newline_title_fallback' );
}

$pound = SOM_Order_Import::parse( "Order Number,Quantity,Sold For,Item Title\nPOUND-1,1,£12.50,Pound Sign\n" );
$pound_order = $order_by_id( $pound, 'POUND-1' );
$assert( is_array( $pound_order ) && '12.50' === $money( $pound_order['items'][0]['unit_price'] ), 'currency_symbol_stripped' );
$assert( is_array( $pound_order ) && 'Pound Sign' === $pound_order['items'][0]['style'], 'title_style_without_variation' );

$dated = SOM_Order_Import::parse( "Order Number,Quantity,Sold For,Sale Date\nDATE-1,1,1.00,01-Oct-2026\n" );
$dated_order = $order_by_id( $dated, 'DATE-1' );
$assert( is_array( $dated_order ) && '2026-10-01 00:00:00' === $dated_order['order_date'], 'ebay_month_name_date' );

$fallback = SOM_Order_Import::parse( "Order Number,Quantity,Sold For,Buyer Name,Post To Name\nFB-1,1,1.00,,Post Only\n" );
$fallback_order = $order_by_id( $fallback, 'FB-1' );
$assert( is_array( $fallback_order ) && 'Post Only' === $fallback_order['buyer_name'], 'ebay_buyer_falls_back_to_post_to_name' );

$mixed = SOM_Order_Import::parse( "Order Number,Quantity,Sold For,Variation Details\nMIX-1,1,3.00,\"House Number: 1\tkept\"\n" );
$assert( 'ebay' === $mixed['format'] && is_array( $order_by_id( $mixed, 'MIX-1' ) ), 'comma_file_with_tab_inside_quotes' );

$unknown_header = SOM_Order_Import::parse( "Hello,World\n1,2\n" );
$assert( '' === $unknown_header['format'] && 0 === (int) $unknown_header['order_count'], 'unrecognised_header_no_orders' );
$assert( array( 'unrecognised_header' ) === $codes_of( $unknown_header ), 'unrecognised_header_error' );

$orders_after_parse = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'orders' ) );
$assert( $orders_before === $orders_after_parse, 'preview_parse_saves_nothing' );

$suffix   = wp_generate_password( 4, false, false );
$good     = 'UP13S2-' . $suffix;
$bad      = 'UP13S2B-' . $suffix;
$good2    = 'UP13S2C-' . $suffix;
$etsy_id  = 'UP13S2E-' . $suffix;
$item_no  = 'up13s2-item-' . $suffix;

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

$csv_line = static function ( $channel, $order, $qty, $house, $street, $item, $sku ) {
	return implode(
		',',
		array(
			$channel,
			$order,
			'2026-10-03',
			'Pat Buyer',
			'5 River Road',
			'',
			'York',
			'North Yorkshire',
			'YO1 7HH',
			'GB',
			$item,
			$sku,
			(string) $qty,
			'6.50',
			$house,
			$street,
			'Classic',
		)
	);
};

$commit_csv = $header_exact . "\n"
	. $csv_line( 'ebay', $good, '1', '14', 'River Road', $item_no, 'SKU-1' ) . "\n"
	. $csv_line( 'ebay', $bad, '0', '1', 'Bad Street', $item_no, 'SKU-1' ) . "\n"
	. $csv_line( 'ebay', $bad, '1', '2', 'Also Bad', $item_no, 'SKU-2' ) . "\n"
	. $csv_line( 'etsy', $etsy_id, '1', '9', 'Nope', $item_no, 'SKU-E' ) . "\n"
	. $csv_line( 'ebay', '', '1', '9', 'Nope', $item_no, 'SKU-M' ) . "\n"
	. $csv_line( 'amazon', $good2, '1', '22', 'Park Lane', '', 'SKU-A' ) . "\n";

$commit_parsed = SOM_Order_Import::parse( $commit_csv );
$commit_codes  = $codes_of( $commit_parsed );
$assert( 2 === (int) $commit_parsed['order_count'] && 2 === (int) $commit_parsed['line_count'], 'commit_file_recognises_good_orders_only' );
$assert( 1 === (int) $commit_parsed['failed'], 'one_bad_line_fails_that_order' );
$assert( in_array( 'bad_quantity', $commit_codes, true ), 'bad_quantity_error' );
$assert( in_array( 'unknown_channel', $commit_codes, true ), 'unknown_channel_error' );
$assert( in_array( 'missing_order_number', $commit_codes, true ), 'missing_order_number_error' );
$assert( is_array( $order_by_id( $commit_parsed, $good ) ) && ! is_array( $order_by_id( $commit_parsed, $bad ) ), 'bad_order_not_recognised' );
$bad_qty_for_order = 0;
foreach ( $commit_parsed['errors'] as $error ) {
	if ( 'bad_quantity' === $error['code'] && $bad === $error['order_number'] ) {
		++$bad_qty_for_order;
	}
}
$assert( 1 === $bad_qty_for_order, 'only_the_bad_line_is_an_error' );

$wf = SOM_Workflows::create(
	array(
		'name'       => 'UP13-S2 Make ' . $suffix,
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
				'name'                     => 'UP13 S2 Print ' . $suffix,
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
			'name'                 => 'UP13-S2 Product ' . $suffix,
			'sku'                  => 'UP13S2-' . $suffix,
			'workflow_template_id' => $wf_id,
			'is_internal'          => 0,
			'is_active'            => 1,
		)
	);
	$assert( ! is_wp_error( $created ), 'create_product' );
	$product_id = is_wp_error( $created ) ? 0 : (int) $created;
}

if ( $product_id > 0 ) {
	$listing = SOM_Listings::create(
		array(
			'product_id'          => $product_id,
			'channel_slug'        => 'ebay',
			'external_listing_id' => $item_no,
			'title'               => 'UP13 S2 listing ' . $suffix,
			'price'               => 6.5,
		)
	);
	$assert( ! is_wp_error( $listing ), 'create_listing_map' );
}

$first = SOM_Order_Import::commit( $commit_csv );
$assert( 2 === (int) $first['created'], 'commit_created_two' );
$assert( 0 === (int) $first['skipped'], 'commit_skipped_none' );
$assert( 1 === (int) $first['failed'], 'commit_failed_bad_order' );
$assert( 1 === $count_orders( 'ebay', $good ), 'good_order_saved' );
$assert( 1 === $count_orders( 'amazon', $good2 ), 'later_order_saved_after_failure' );
$assert( 0 === $count_orders( 'ebay', $bad ), 'bad_order_not_saved' );
$assert( 0 === $count_orders( 'etsy', $etsy_id ), 'unknown_channel_not_saved' );

$good_row = null;
$ebay_channel = SOM_Channels::get_by_slug( 'ebay' );
if ( $ebay_channel ) {
	$good_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT id FROM ' . SOM_DB::table( 'orders' ) . ' WHERE channel_id = %d AND external_order_id = %s',
			(int) $ebay_channel->id,
			$good
		)
	);
	$good_row = $good_id > 0 ? SOM_Orders::get( $good_id ) : null;
}
$good_item = ( $good_row && ! empty( $good_row->items ) ) ? $good_row->items[0] : null;
$assert( $good_row && 1 === count( $good_row->items ), 'good_order_one_line' );
$assert( $good_item && '14' === (string) $good_item->house_number, 'good_house_stored' );
$assert( $good_item && 'River Road' === (string) $good_item->street_name, 'good_street_stored' );
$assert( $good_item && $item_no === (string) $good_item->external_listing_id, 'good_item_number_stored' );
if ( $product_id > 0 && $good_row ) {
	$assert( $good_item && (int) $good_item->product_id === $product_id, 'good_line_matched' );
	$assert( SOM_Item_Make::has_item_progress( (int) $good_row->id ), 'matched_line_has_workflow' );
}

$second = SOM_Order_Import::commit( $commit_csv );
$assert( 0 === (int) $second['created'], 'duplicate_created_none' );
$assert( 2 === (int) $second['skipped'], 'duplicate_skipped' );
$assert( 1 === (int) $second['failed'], 'duplicate_still_fails_bad_order' );
$assert( 1 === $count_orders( 'ebay', $good ), 'duplicate_did_not_create_second' );

$rewrite = $header_exact . "\n" . $csv_line( 'ebay', $good, '1', '99', 'Rewritten', 'other-item', 'OTHER' ) . "\n";
$third   = SOM_Order_Import::commit( $rewrite );
$assert( 0 === (int) $third['created'] && 1 === (int) $third['skipped'] && 0 === (int) $third['failed'], 'rewrite_skipped' );

$good_after = ( $good_row && ! empty( $good_row->id ) ) ? SOM_Orders::get( (int) $good_row->id ) : null;
$after_item = ( $good_after && ! empty( $good_after->items ) ) ? $good_after->items[0] : null;
$assert( $good_after && 1 === count( $good_after->items ), 'skip_did_not_add_line' );
$assert( $after_item && '14' === (string) $after_item->house_number, 'skip_did_not_rewrite_house' );
$assert( $after_item && 'River Road' === (string) $after_item->street_name, 'skip_did_not_rewrite_street' );
$assert( $after_item && $item_no === (string) $after_item->external_listing_id, 'skip_did_not_rewrite_item_number' );

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK\n";
