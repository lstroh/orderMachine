<?php
/**
 * UP13-S3 smoke: import eBay active listings and Amazon inventory reports.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up13-s3-smoke.php
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
$row_by_sku = static function ( $parsed, $sku ) {
	if ( empty( $parsed['rows'] ) || ! is_array( $parsed['rows'] ) ) {
		return null;
	}
	foreach ( $parsed['rows'] as $row ) {
		if ( isset( $row['sku'] ) && (string) $row['sku'] === (string) $sku ) {
			return $row;
		}
	}
	return null;
};
$unmatched_skus = static function ( $result ) {
	$skus = array();
	if ( empty( $result['unmatched'] ) || ! is_array( $result['unmatched'] ) ) {
		return $skus;
	}
	foreach ( $result['unmatched'] as $row ) {
		$skus[] = isset( $row['sku'] ) ? (string) $row['sku'] : '';
	}
	return $skus;
};

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );

$list_view   = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/listings.php' );
$menu_file   = file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$listing_src = file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-listings.php' );
$import_src  = file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-listing-import.php' );
$guide       = file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md' );

$assert( is_string( $list_view ) && false !== strpos( $list_view, 'Import listing report' ), 'import_summary' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'name="som_listing_import_file"' ), 'import_file_input' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'som_import_listings' ), 'import_submit' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, "wp_nonce_field( 'som_import_listings', 'som_listing_nonce' )" ), 'import_nonce' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'enctype="multipart/form-data"' ), 'import_multipart' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'Created %1$d, skipped %2$d already mapped.' ), 'import_result_message' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'Unmatched rows' ), 'import_unmatched_list' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, "'amazon' => __( 'Amazon', 'order-machine' )" ), 'listings_channel_filter_amazon' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, "check_admin_referer( 'som_import_listings', 'som_listing_nonce' )" ), 'import_nonce_check' );
$assert( is_string( $menu_file ) && false !== strpos( $menu_file, "current_user_can( 'manage_options' )" ), 'import_cap_gate' );
$assert( is_string( $listing_src ) && false !== strpos( $listing_src, "array( 'ebay', 'etsy', 'amazon' )" ), 'create_and_filter_allow_amazon' );
$assert( is_string( $import_src ) && false !== strpos( $import_src, 'SOM_Listings::create' ), 'uses_listings_create' );
$assert( is_string( $import_src ) && false === strpos( $import_src, 'SOM_Channel_' ), 'no_marketplace_api' );
$assert( is_string( $import_src ) && false === strpos( $import_src, 'update_local' ) && false === strpos( $import_src, '::push' ), 'does_not_update_or_push' );
$assert( is_string( $guide ) && false !== strpos( $guide, 'Reports → Listings → Active listings' ), 'guide_ebay_listings_download' );
$assert( is_string( $guide ) && false !== strpos( $guide, 'Inventory report' ), 'guide_amazon_listings_download' );
$assert( is_string( $guide ) && false !== strpos( $guide, 'seller-sku' ), 'guide_amazon_sku' );
$assert( is_string( $guide ) && false !== strpos( $guide, 'not sent to eBay or Amazon' ), 'guide_does_not_push' );

global $wpdb;
$listings_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'listings' ) );

$ebay_csv   = file_get_contents( SOM_PLUGIN_DIR . 'tests/fixtures/ebay-listings-import.csv' );
$amazon_txt = file_get_contents( SOM_PLUGIN_DIR . 'tests/fixtures/amazon-listings-import.txt' );
$assert( is_string( $ebay_csv ) && is_string( $amazon_txt ), 'fixtures_readable' );
$assert( is_string( $amazon_txt ) && false !== strpos( $amazon_txt, "\t" ), 'amazon_fixture_is_tab_separated' );

$ebay = SOM_Listing_Import::parse( (string) $ebay_csv );
$assert( 'ebay' === $ebay['format'], 'ebay_format' );
$assert( 2 === count( $ebay['rows'] ), 'ebay_two_rows' );
$ebay_match = $row_by_sku( $ebay, 'UP13S3-MATCH-EBAY' );
$ebay_miss  = $row_by_sku( $ebay, 'UP13S3-MISS-EBAY' );
$assert( is_array( $ebay_match ) && 2 === (int) $ebay_match['row'], 'ebay_match_row' );
$assert( is_array( $ebay_match ) && '123456789012' === $ebay_match['external_listing_id'], 'ebay_item_number' );
$assert( is_array( $ebay_match ) && 'Oak House Sign' === $ebay_match['title'], 'ebay_title' );
$assert( is_array( $ebay_match ) && 4 === (int) $ebay_match['quantity_available'], 'ebay_available_quantity_not_quantity' );
$assert( is_array( $ebay_match ) && '12.50' === $money( $ebay_match['price'] ), 'ebay_current_price_not_start_or_price' );
$assert( is_array( $ebay_miss ) && '999000111222' === $ebay_miss['external_listing_id'], 'ebay_unknown_item_number' );
$assert( is_array( $ebay_miss ) && 'Mystery Sign' === $ebay_miss['title'], 'ebay_unknown_title' );

$ebay_bom = SOM_Listing_Import::parse( "\xEF\xBB\xBF" . (string) $ebay_csv );
$assert( 'ebay' === $ebay_bom['format'] && 2 === count( $ebay_bom['rows'] ), 'ebay_bom_stripped' );

$quoted_tab = SOM_Listing_Import::parse( "Item number,Custom label,Title\n555,SKU-M,\"Oak\tSign\"\n" );
$quoted_row = $row_by_sku( $quoted_tab, 'SKU-M' );
$assert( 'ebay' === $quoted_tab['format'] && is_array( $quoted_row ) && "Oak\tSign" === $quoted_row['title'], 'comma_file_with_tab_inside_quotes' );

$ebay_tab = SOM_Listing_Import::parse( str_replace( ',', "\t", (string) $ebay_csv ) );
$ebay_tab_row = $row_by_sku( $ebay_tab, 'UP13S3-MATCH-EBAY' );
$assert( 'ebay' === $ebay_tab['format'] && is_array( $ebay_tab_row ) && '12.50' === $money( $ebay_tab_row['price'] ), 'ebay_tab_delimiter' );

$amazon = SOM_Listing_Import::parse( (string) $amazon_txt );
$assert( 'amazon' === $amazon['format'], 'amazon_format' );
$assert( 2 === count( $amazon['rows'] ), 'amazon_two_rows' );
$amz_match = $row_by_sku( $amazon, 'UP13S3-MATCH-AMZ' );
$amz_miss  = $row_by_sku( $amazon, 'UP13S3-MISS-AMZ' );
$assert( is_array( $amz_match ) && 'B0UP13S3A1' === $amz_match['external_listing_id'], 'amazon_asin' );
$assert( is_array( $amz_match ) && 'Amazon Oak Sign' === $amz_match['title'], 'amazon_item_name' );
$assert( is_array( $amz_match ) && '9.99' === $money( $amz_match['price'] ), 'amazon_price' );
$assert( is_array( $amz_match ) && 7 === (int) $amz_match['quantity_available'], 'amazon_quantity' );
$assert( is_array( $amz_miss ) && 'B0UP13S3XX' === $amz_miss['external_listing_id'], 'amazon_unknown_asin' );

$amazon_comma = SOM_Listing_Import::parse( str_replace( "\t", ',', (string) $amazon_txt ) );
$amazon_comma_row = $row_by_sku( $amazon_comma, 'UP13S3-MATCH-AMZ' );
$assert( 'amazon' === $amazon_comma['format'] && is_array( $amazon_comma_row ) && 'B0UP13S3A1' === $amazon_comma_row['external_listing_id'], 'amazon_comma_delimiter' );

$fallback_qty = SOM_Listing_Import::parse( "Item number,Custom label,Quantity,Start price\n555,SKU-Q,6,8.25\n" );
$fallback_row = $row_by_sku( $fallback_qty, 'SKU-Q' );
$assert( is_array( $fallback_row ) && 6 === (int) $fallback_row['quantity_available'], 'ebay_quantity_fallback' );
$assert( is_array( $fallback_row ) && '8.25' === $money( $fallback_row['price'] ), 'ebay_start_price_fallback' );

$price_only = SOM_Listing_Import::parse( "Item number,Custom label,Price\n555,SKU-P,1.75\n" );
$price_row  = $row_by_sku( $price_only, 'SKU-P' );
$assert( is_array( $price_row ) && '1.75' === $money( $price_row['price'] ) && 0 === (int) $price_row['quantity_available'], 'ebay_price_column_fallback' );

$empty_current = SOM_Listing_Import::parse( "Item number,Custom label,Current price,Start price,Price\n555,SKU-E,,4.00,2.00\n" );
$empty_row     = $row_by_sku( $empty_current, 'SKU-E' );
$assert( is_array( $empty_row ) && '4.00' === $money( $empty_row['price'] ), 'ebay_empty_current_price_uses_start_price' );

$zero_available = SOM_Listing_Import::parse( "Item number,Custom label,Available quantity,Quantity\n555,SKU-Z,0,9\n" );
$zero_row       = $row_by_sku( $zero_available, 'SKU-Z' );
$assert( is_array( $zero_row ) && 0 === (int) $zero_row['quantity_available'], 'ebay_zero_available_quantity_not_quantity' );

$pound = SOM_Listing_Import::parse( "Item number,Custom label,Current price\n555,SKU-L,£12.50\n" );
$pound_row = $row_by_sku( $pound, 'SKU-L' );
$assert( is_array( $pound_row ) && '12.50' === $money( $pound_row['price'] ), 'ebay_currency_symbol_stripped' );

$asin_blank = SOM_Listing_Import::parse( "seller-sku\tasin\titem-name\tprice\tquantity\nONLY-SKU\t\tName Only\t1.25\t3\n" );
$asin_row   = $row_by_sku( $asin_blank, 'ONLY-SKU' );
$assert( is_array( $asin_row ) && 'ONLY-SKU' === $asin_row['external_listing_id'], 'amazon_blank_asin_uses_seller_sku' );
$assert( is_array( $asin_row ) && 'Name Only' === $asin_row['title'] && 3 === (int) $asin_row['quantity_available'], 'amazon_blank_asin_other_fields' );

$orders_file = SOM_Listing_Import::parse( "Order Number,Item Number,Custom Label,Quantity,Sold For\n14-1,111,SKU,1,2.00\n" );
$assert( '' === $orders_file['format'] && array() === $orders_file['rows'], 'ebay_orders_file_not_a_listing_report' );

$amazon_orders = SOM_Listing_Import::parse( "order-id\tsku\titem-price\tquantity-purchased\n123-1\tSKU\t10.00\t1\n" );
$assert( '' === $amazon_orders['format'] && array() === $amazon_orders['rows'], 'amazon_orders_file_not_a_listing_report' );

$unknown = SOM_Listing_Import::parse( "Hello,World\n1,2\n" );
$assert( '' === $unknown['format'] && array() === $unknown['rows'], 'unrecognised_header' );
$unknown_commit = SOM_Listing_Import::commit( "Hello,World\n1,2\n" );
$assert( is_wp_error( $unknown_commit ) && 'som_listing_import_format' === $unknown_commit->get_error_code(), 'unrecognised_header_not_imported' );

$listings_after_parse = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'listings' ) );
$assert( $listings_before === $listings_after_parse, 'parse_saves_nothing' );

$suffix          = wp_generate_password( 4, false, false );
$ebay_sku        = 'UP13S3E-' . $suffix;
$ebay_miss_sku   = 'UP13S3X-' . $suffix;
$amz_sku         = 'UP13S3A-' . $suffix;
$amz_miss_sku    = 'UP13S3Y-' . $suffix;
$ebay_item       = 'up13s3-item-' . $suffix;
$ebay_miss_item  = 'up13s3-miss-' . $suffix;
$amz_asin        = 'B0UP13' . strtoupper( $suffix );
$amz_miss_asin   = 'B0MISS' . strtoupper( $suffix );

$ebay_body = str_replace(
	array( 'UP13S3-MATCH-EBAY', 'UP13S3-MISS-EBAY', '123456789012', '999000111222' ),
	array( $ebay_sku, $ebay_miss_sku, $ebay_item, $ebay_miss_item ),
	(string) $ebay_csv
);
$amazon_body = str_replace(
	array( 'UP13S3-MATCH-AMZ', 'UP13S3-MISS-AMZ', 'B0UP13S3A1', 'B0UP13S3XX' ),
	array( $amz_sku, $amz_miss_sku, $amz_asin, $amz_miss_asin ),
	(string) $amazon_txt
);

$ebay_product = SOM_Products::create(
	array(
		'name'        => 'UP13-S3 eBay ' . $suffix,
		'sku'         => $ebay_sku,
		'is_internal' => 0,
		'is_active'   => 1,
	)
);
$amz_product = SOM_Products::create(
	array(
		'name'        => 'UP13-S3 Amazon ' . $suffix,
		'sku'         => $amz_sku,
		'is_internal' => 0,
		'is_active'   => 1,
	)
);
$assert( ! is_wp_error( $ebay_product ) && ! is_wp_error( $amz_product ), 'create_products' );
$ebay_product_id = is_wp_error( $ebay_product ) ? 0 : (int) $ebay_product;
$amz_product_id  = is_wp_error( $amz_product ) ? 0 : (int) $amz_product;

$count_listing = static function ( $slug, $external_id ) {
	global $wpdb;
	$channel = SOM_Channels::get_by_slug( $slug );
	if ( ! $channel ) {
		return 0;
	}
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'listings' ) . ' WHERE channel_id = %d AND external_listing_id = %s',
			(int) $channel->id,
			$external_id
		)
	);
};
$listing_for = static function ( $slug, $external_id ) {
	global $wpdb;
	$channel = SOM_Channels::get_by_slug( $slug );
	if ( ! $channel ) {
		return null;
	}
	$id = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT id FROM ' . SOM_DB::table( 'listings' ) . ' WHERE channel_id = %d AND external_listing_id = %s LIMIT 1',
			(int) $channel->id,
			$external_id
		)
	);
	return $id > 0 ? SOM_Listings::get( $id ) : null;
};

$ebay_first = SOM_Listing_Import::commit( $ebay_body );
$assert( ! is_wp_error( $ebay_first ) && 1 === (int) $ebay_first['created'], 'ebay_created_one' );
$assert( ! is_wp_error( $ebay_first ) && 0 === (int) $ebay_first['skipped'], 'ebay_skipped_none' );
$assert( ! is_wp_error( $ebay_first ) && array( $ebay_miss_sku ) === $unmatched_skus( $ebay_first ), 'ebay_unknown_sku_reported' );
$assert( ! is_wp_error( $ebay_first ) && isset( $ebay_first['unmatched'][0]['code'] ) && 'unmatched_sku' === $ebay_first['unmatched'][0]['code'], 'ebay_unknown_sku_code' );
$assert( 1 === $count_listing( 'ebay', $ebay_item ), 'ebay_item_number_mapped' );
$assert( 0 === $count_listing( 'ebay', $ebay_miss_item ), 'ebay_unknown_sku_not_inserted' );

$ebay_listing = $listing_for( 'ebay', $ebay_item );
$assert( $ebay_listing && 'ebay' === (string) $ebay_listing->channel_slug, 'ebay_listing_channel' );
$assert( $ebay_listing && (int) $ebay_listing->product_id === $ebay_product_id, 'ebay_listing_product' );
$assert( $ebay_listing && 'Oak House Sign' === (string) $ebay_listing->title, 'ebay_listing_title' );
$assert( $ebay_listing && '12.50' === $money( $ebay_listing->price ), 'ebay_listing_price_stored' );
$assert( $ebay_listing && 4 === (int) $ebay_listing->quantity_available, 'ebay_listing_quantity_stored' );
$assert( $ebay_listing && empty( $ebay_listing->last_synced_at ), 'ebay_listing_not_pushed' );
$ebay_listing_id = $ebay_listing ? (int) $ebay_listing->id : 0;

$amz_first = SOM_Listing_Import::commit( $amazon_body );
$assert( ! is_wp_error( $amz_first ) && 1 === (int) $amz_first['created'], 'amazon_created_one' );
$assert( ! is_wp_error( $amz_first ) && 0 === (int) $amz_first['skipped'], 'amazon_skipped_none' );
$assert( ! is_wp_error( $amz_first ) && array( $amz_miss_sku ) === $unmatched_skus( $amz_first ), 'amazon_unknown_sku_reported' );
$assert( 1 === $count_listing( 'amazon', $amz_asin ), 'amazon_asin_mapped' );
$assert( 0 === $count_listing( 'amazon', $amz_miss_asin ), 'amazon_unknown_sku_not_inserted' );

$amz_listing = $listing_for( 'amazon', $amz_asin );
$assert( $amz_listing && 'amazon' === (string) $amz_listing->channel_slug, 'amazon_listing_channel' );
$assert( $amz_listing && (int) $amz_listing->product_id === $amz_product_id, 'amazon_listing_product' );
$assert( $amz_listing && 'Amazon Oak Sign' === (string) $amz_listing->title, 'amazon_listing_title' );
$assert( $amz_listing && '9.99' === $money( $amz_listing->price ), 'amazon_listing_price_stored' );
$assert( $amz_listing && 7 === (int) $amz_listing->quantity_available, 'amazon_listing_quantity_stored' );
$assert( $amz_listing && empty( $amz_listing->last_synced_at ), 'amazon_listing_not_pushed' );
$amz_listing_id = $amz_listing ? (int) $amz_listing->id : 0;

$ebay_second = SOM_Listing_Import::commit( $ebay_body );
$assert( ! is_wp_error( $ebay_second ) && 0 === (int) $ebay_second['created'], 'ebay_again_created_none' );
$assert( ! is_wp_error( $ebay_second ) && 1 === (int) $ebay_second['skipped'], 'ebay_again_skipped_already_mapped' );
$assert( ! is_wp_error( $ebay_second ) && array( $ebay_miss_sku ) === $unmatched_skus( $ebay_second ), 'ebay_again_still_reports_unknown_sku' );
$assert( 1 === $count_listing( 'ebay', $ebay_item ), 'ebay_again_did_not_create_second_map' );
$assert( 0 === $count_listing( 'ebay', $ebay_miss_item ), 'ebay_again_unknown_still_absent' );

$amz_second = SOM_Listing_Import::commit( $amazon_body );
$assert( ! is_wp_error( $amz_second ) && 0 === (int) $amz_second['created'], 'amazon_again_created_none' );
$assert( ! is_wp_error( $amz_second ) && 1 === (int) $amz_second['skipped'], 'amazon_again_skipped_already_mapped' );
$assert( ! is_wp_error( $amz_second ) && array( $amz_miss_sku ) === $unmatched_skus( $amz_second ), 'amazon_again_still_reports_unknown_sku' );
$assert( 1 === $count_listing( 'amazon', $amz_asin ), 'amazon_again_did_not_create_second_map' );
$assert( 0 === $count_listing( 'amazon', $amz_miss_asin ), 'amazon_again_unknown_still_absent' );

$ebay_changed = str_replace(
	$ebay_item . ',Oak House Sign,' . $ebay_sku . ',4,99,12.50,9.00,1.00',
	$ebay_item . ',Oak House Sign,' . $ebay_sku . ',40,99,88.00,9.00,1.00',
	$ebay_body
);
$amz_changed = str_replace(
	$amz_sku . "\t" . $amz_asin . "\tAmazon Oak Sign\t9.99\t7",
	$amz_sku . "\t" . $amz_asin . "\tAmazon Oak Sign\t77.00\t70",
	$amazon_body
);
$assert( false !== strpos( $ebay_changed, '88.00' ) && false === strpos( $ebay_changed, '12.50' ), 'ebay_changed_file_ready' );
$assert( false !== strpos( $amz_changed, "77.00\t70" ) && false === strpos( $amz_changed, "9.99\t7" ), 'amazon_changed_file_ready' );

$ebay_third = SOM_Listing_Import::commit( $ebay_changed );
$amz_third  = SOM_Listing_Import::commit( $amz_changed );
$assert( ! is_wp_error( $ebay_third ) && 0 === (int) $ebay_third['created'] && 1 === (int) $ebay_third['skipped'], 'ebay_rewrite_skipped' );
$assert( ! is_wp_error( $amz_third ) && 0 === (int) $amz_third['created'] && 1 === (int) $amz_third['skipped'], 'amazon_rewrite_skipped' );

$ebay_after = $listing_for( 'ebay', $ebay_item );
$amz_after  = $listing_for( 'amazon', $amz_asin );
$assert( $ebay_after && $ebay_listing_id === (int) $ebay_after->id, 'ebay_rewrite_same_row' );
$assert( $ebay_after && '12.50' === $money( $ebay_after->price ), 'ebay_rewrite_did_not_change_price' );
$assert( $ebay_after && 4 === (int) $ebay_after->quantity_available, 'ebay_rewrite_did_not_change_quantity' );
$assert( $amz_after && $amz_listing_id === (int) $amz_after->id, 'amazon_rewrite_same_row' );
$assert( $amz_after && '9.99' === $money( $amz_after->price ), 'amazon_rewrite_did_not_change_price' );
$assert( $amz_after && 7 === (int) $amz_after->quantity_available, 'amazon_rewrite_did_not_change_quantity' );

$blank_item = SOM_Listing_Import::commit( "Item number,Custom label,Title,Price\n,{$ebay_sku},No Item,1.00\n" );
$assert( ! is_wp_error( $blank_item ) && 0 === (int) $blank_item['created'] && 0 === (int) $blank_item['skipped'], 'blank_item_number_not_created' );
$assert( ! is_wp_error( $blank_item ) && isset( $blank_item['unmatched'][0]['code'] ) && 'som_listing_external' === $blank_item['unmatched'][0]['code'], 'blank_item_number_create_rejected' );

$wf = SOM_Workflows::create(
	array(
		'name'       => 'UP13-S3 Make ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 0,
	)
);
$assert( ! is_wp_error( $wf ), 'create_workflow' );
$wf_id = is_wp_error( $wf ) ? 0 : (int) $wf;
$internal_sku  = 'UP13S3I-' . $suffix;
$internal_item = 'up13s3-internal-' . $suffix;
$internal_id   = 0;
if ( $wf_id > 0 ) {
	$internal = SOM_Products::create(
		array(
			'name'                 => 'UP13-S3 Internal ' . $suffix,
			'sku'                  => $internal_sku,
			'workflow_template_id' => $wf_id,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	);
	$assert( ! is_wp_error( $internal ), 'create_internal_product' );
	$internal_id = is_wp_error( $internal ) ? 0 : (int) $internal;
}
if ( $internal_id > 0 ) {
	$rejected = SOM_Listing_Import::commit( "Item number,Custom label,Title,Price\n{$internal_item},{$internal_sku},Internal,5.00\n" );
	$assert( ! is_wp_error( $rejected ) && 0 === (int) $rejected['created'] && 0 === (int) $rejected['skipped'], 'internal_not_created' );
	$assert( ! is_wp_error( $rejected ) && isset( $rejected['unmatched'][0]['code'] ) && 'som_listing_internal' === $rejected['unmatched'][0]['code'], 'internal_create_rejected' );
	$assert( 0 === $count_listing( 'ebay', $internal_item ), 'internal_no_listing_row' );
}

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK\n";
