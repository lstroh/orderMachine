<?php
/**
 * UP14-S5 smoke: order detail Shipment is a disclosure, closed on each visit.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s5-smoke.php
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

$out( 'plugin', SOM_VERSION );

require_once ABSPATH . 'wp-admin/includes/template.php';

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin || ! user_can( $admin, 'manage_options' ) ) {
	$created = wp_insert_user(
		array(
			'user_login' => 'up14s5admin',
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'up14s5admin@example.com',
			'role'       => 'administrator',
		)
	);
	$assert( ! is_wp_error( $created ) && (int) $created > 0, 'create_admin' );
	$admin = is_wp_error( $created ) ? null : get_user_by( 'id', (int) $created );
}
$assert( $admin && user_can( $admin, 'manage_options' ), 'admin_can_manage' );
if ( $admin ) {
	wp_set_current_user( (int) $admin->ID );
}

$detail_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( false !== strpos( $detail_src, '<details class="som-shipment-disclosure">' ), 'source_disclosure_closed' );
$assert( false === strpos( $detail_src, 'som-shipment-disclosure" open' ), 'source_no_open_attribute' );
$assert( false === strpos( $detail_src, 'localStorage' ) && false === strpos( $detail_src, 'sessionStorage' ), 'source_does_not_remember_open' );
$assert( false !== strpos( $detail_src, "esc_html__( 'Make', 'order-machine' )" ), 'source_make_heading' );
$assert( false !== strpos( $detail_src, "esc_html__( 'Pack & Ship', 'order-machine' )" ), 'source_pack_heading' );
$assert( false !== strpos( $detail_src, "esc_html__( 'Post-ship', 'order-machine' )" ), 'source_post_ship_heading' );

$shipment_markup = static function ( $html ) {
	$open  = '<details class="som-shipment-disclosure">';
	$start = strpos( $html, $open );
	if ( false === $start ) {
		return null;
	}
	$end = strpos( $html, '</details>', $start );
	if ( false === $end ) {
		return null;
	}
	$block     = substr( $html, $start, ( $end + strlen( '</details>' ) ) - $start );
	$tag_end   = strpos( $block, '>' );
	$tag       = false !== $tag_end ? substr( $block, 0, $tag_end + 1 ) : '';
	$sum_open  = '<summary class="som-shipment-summary">';
	$sum_start = strpos( $block, $sum_open );
	$sum_end   = false !== $sum_start ? strpos( $block, '</summary>', $sum_start ) : false;
	$summary   = '';
	if ( false !== $sum_start && false !== $sum_end ) {
		$inner = substr( $block, $sum_start + strlen( $sum_open ), $sum_end - ( $sum_start + strlen( $sum_open ) ) );
		$flat  = preg_replace( '/\s+/', ' ', wp_strip_all_tags( $inner ) );
		$summary = trim( is_string( $flat ) ? $flat : '' );
	}
	return array(
		'block'   => $block,
		'summary' => $summary,
		'tag'     => $tag,
	);
};

$render_detail = static function ( $order_id ) {
	$_GET['order_id'] = (int) $order_id;
	ob_start();
	SOM_Admin_Menu::render_orders();
	return (string) ob_get_clean();
};

$assert_closed = static function ( $html, $prefix ) use ( $assert, $shipment_markup ) {
	$markup = $shipment_markup( $html );
	$assert( is_array( $markup ), $prefix . '_disclosure' );
	$assert( is_array( $markup ) && '<details class="som-shipment-disclosure">' === $markup['tag'], $prefix . '_not_open' );
	$assert( 1 === substr_count( $html, '<details class="som-shipment-disclosure">' ), $prefix . '_one_disclosure' );
	return is_array( $markup ) ? $markup : array(
		'block'   => '',
		'summary' => '',
		'tag'     => '',
	);
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$make = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => 'UP14-S5 make ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Print',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $make ) && (int) $make > 0, 'create_make' );
$make_id = is_wp_error( $make ) ? 0 : (int) $make;

$sellable = SOM_Products::create(
	array(
		'name'      => 'UP14-S5 sellable ' . $suffix,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $sellable ) && (int) $sellable > 0, 'create_sellable' );
$sellable_id = is_wp_error( $sellable ) ? 0 : (int) $sellable;

$internal = $make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => 'UP14-S5 internal ' . $suffix,
			'workflow_template_id' => $make_id,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $internal ) && (int) $internal > 0, 'create_internal' );
$internal_id = is_wp_error( $internal ) ? 0 : (int) $internal;

$customer_id = ( $sellable_id > 0 )
	? SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'ebay',
			'external_order_id' => 'up14s5-c-' . $suffix,
			'buyer_name'        => 'UP14-S5 buyer',
			'shipping_address'  => array(
				'line1'    => '1 Shipment Street',
				'city'     => 'London',
				'postcode' => 'E1 1AA',
				'country'  => 'GB',
			),
			'items'             => array(
				array(
					'product_id' => $sellable_id,
					'quantity'   => 1,
					'unit_price' => 5,
				),
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $customer_id ) && (int) $customer_id > 0, 'create_customer_order' );
$customer_id = is_wp_error( $customer_id ) ? 0 : (int) $customer_id;

$internal_order_id = $internal_id > 0
	? SOM_Order_Sync::create_from_external(
		array(
			'channel'           => SOM_Production::CHANNEL_SLUG,
			'external_order_id' => 'up14s5-i-' . $suffix,
			'buyer_name'        => 'Production',
			'items'             => array(
				array(
					'product_id' => $internal_id,
					'quantity'   => 1,
					'unit_price' => 0,
				),
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $internal_order_id ) && (int) $internal_order_id > 0, 'create_internal_order' );
$internal_order_id = is_wp_error( $internal_order_id ) ? 0 : (int) $internal_order_id;

$form_ids = array(
	'som_ship_carrier',
	'som_ship_service',
	'som_ship_shipped_at',
	'som_ship_postage',
	'som_ship_pack_weight',
	'som_ship_tracking',
	'som_ship_cad',
	'som_shipment_proof',
);

$customer_html = $customer_id > 0 ? $render_detail( $customer_id ) : '';
$customer_ship = $assert_closed( $customer_html, 'customer_closed' );
$assert( 'Shipment' === $customer_ship['summary'], 'customer_heading_shipment_only' );
$assert( false !== strpos( $customer_ship['block'], 'name="som_save_shipment"' ), 'customer_save_form' );
$assert( false === strpos( $customer_ship['block'], 'name="som_push_tracking"' ), 'customer_no_push_without_tracking' );
foreach ( $form_ids as $field_id ) {
	$assert( false !== strpos( $customer_ship['block'], 'id="' . $field_id . '"' ), 'customer_field_' . $field_id );
}
$assert( false !== strpos( $customer_html, '<section class="som-panel som-panel-planned-shipping"' ), 'planned_shipping_stays' );
$assert( false !== strpos( $customer_html, '<h2>Shipping address</h2>' ), 'shipping_address_stays' );
$assert( false !== strpos( $customer_html, '<section class="som-panel som-panel-make">' ), 'make_stays_expanded' );
$assert( false !== strpos( $customer_html, '<h2>Make</h2>' ), 'make_heading' );
$assert( false !== strpos( $customer_html, '<section class="som-panel som-panel-pack"' ), 'pack_stays_expanded' );
$assert( false !== strpos( $customer_html, '<h2>Pack &amp; Ship</h2>' ), 'pack_heading' );
$assert( false !== strpos( $customer_html, '<section class="som-panel som-panel-post-ship"' ), 'post_ship_stays_expanded' );
$assert( false !== strpos( $customer_html, '<h2>Post-ship</h2>' ), 'post_ship_heading' );

$ship_base = array(
	'carrier'      => 'Royal Mail',
	'service'      => '2nd Class',
	'shipped_at'   => current_time( 'Y-m-d' ),
	'postage_paid' => '2.45',
);

$untracked = $customer_id > 0
	? SOM_Shipments::upsert(
		$customer_id,
		array_merge(
			$ship_base,
			array(
				'tracking_number' => '',
			)
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $untracked ), 'save_untracked' );

$untracked_html = $customer_id > 0 ? $render_detail( $customer_id ) : '';
$untracked_ship = $assert_closed( $untracked_html, 'customer_untracked' );
$assert( false !== strpos( $untracked_ship['summary'], 'Shipment' ), 'untracked_heading_shipment' );
$assert( false !== strpos( $untracked_ship['summary'], SOM_Shipments::status_label( 'untracked' ) ), 'untracked_heading_status' );

$tracking = 'UP14S5C' . $suffix;
$tracked  = $customer_id > 0
	? SOM_Shipments::upsert(
		$customer_id,
		array_merge(
			$ship_base,
			array(
				'tracking_number'    => $tracking,
				'click_and_drop_ref' => 'CD-' . $suffix,
			)
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $tracked ), 'save_tracked' );
$assert( false === strpos( $untracked_ship['summary'], $tracking ), 'untracked_heading_omits_later_number' );

$tracked_html = $customer_id > 0 ? $render_detail( $customer_id ) : '';
$tracked_ship = $assert_closed( $tracked_html, 'customer_tracked' );
$assert( false !== strpos( $tracked_ship['summary'], $tracking ), 'summary_includes_tracking' );
$assert( false !== strpos( $tracked_ship['summary'], SOM_Shipments::status_label( 'tracked' ) ), 'tracked_heading_status' );
$assert( false !== strpos( $tracked_ship['block'], 'name="som_save_shipment"' ), 'tracked_save_stays' );
$assert( false !== strpos( $tracked_ship['block'], 'name="som_push_tracking"' ), 'tracked_push_stays' );

$again_html = $customer_id > 0 ? $render_detail( $customer_id ) : '';
$again_ship = $assert_closed( $again_html, 'customer_reload' );
$assert( false !== strpos( $again_ship['summary'], $tracking ), 'reload_summary_includes_tracking' );

$internal_html = $internal_order_id > 0 ? $render_detail( $internal_order_id ) : '';
$internal_ship = $assert_closed( $internal_html, 'internal_closed' );
$assert( 'Shipment' === $internal_ship['summary'], 'internal_heading_shipment_only' );
$assert( false !== strpos( $internal_ship['block'], 'name="som_save_shipment"' ), 'internal_save_stays' );
$assert( false === strpos( $internal_html, '<h2>Pack &amp; Ship</h2>' ), 'internal_pack_hidden' );
$assert( false !== strpos( $internal_html, '<section class="som-panel som-panel-make">' ), 'internal_make_stays_expanded' );
$assert( false !== strpos( $internal_html, '<h2>Shipping address</h2>' ), 'internal_shipping_address_stays' );
$assert( false !== strpos( $internal_html, 'id="som-planned-shipping"' ), 'internal_planned_shipping_stays' );

$internal_tracking = 'UP14S5I' . $suffix;
$internal_saved    = $internal_order_id > 0
	? SOM_Shipments::upsert(
		$internal_order_id,
		array_merge(
			$ship_base,
			array(
				'tracking_number' => $internal_tracking,
			)
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $internal_saved ), 'internal_save_shipment' );

$internal_tracked_html = $internal_order_id > 0 ? $render_detail( $internal_order_id ) : '';
$internal_tracked      = $assert_closed( $internal_tracked_html, 'internal_tracked' );
$assert( false !== strpos( $internal_tracked['summary'], $internal_tracking ), 'internal_summary_includes_tracking' );
$assert( false !== strpos( $internal_tracked['summary'], SOM_Shipments::status_label( 'tracked' ) ), 'internal_heading_status' );
$assert( false !== strpos( $internal_tracked['block'], 'name="som_push_tracking"' ), 'internal_push_stays' );

if ( $fail > 0 ) {
	echo "FAIL — UP14-S5 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S5 smoke\n";
