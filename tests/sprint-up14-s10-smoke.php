<?php
/**
 * UP14-S10 smoke: Make and Post-ship Mark done reload in place, like Pack.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s10-smoke.php
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

require_once ABSPATH . 'wp-admin/includes/template.php';

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin || ! user_can( $admin, 'manage_options' ) ) {
	$created = wp_insert_user(
		array(
			'user_login' => 'up14s10admin',
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'up14s10admin@example.com',
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

$section_between = static function ( $html, $start_marker, $end_marker ) {
	$start = strpos( $html, $start_marker );
	if ( false === $start ) {
		return '';
	}
	$end = strpos( $html, $end_marker, $start + strlen( $start_marker ) );
	if ( false === $end ) {
		return substr( $html, $start );
	}
	return substr( $html, $start, $end - $start );
};

$mark_done_form = static function ( $html ) {
	$class_at = strpos( $html, 'som-mark-done-form' );
	if ( false === $class_at ) {
		return '';
	}
	$open = strrpos( substr( $html, 0, $class_at ), '<form' );
	if ( false === $open ) {
		return '';
	}
	$close = strpos( $html, '</form>', $class_at );
	if ( false === $close ) {
		return '';
	}
	return substr( $html, $open, ( $close + strlen( '</form>' ) ) - $open );
};

$detail_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$js         = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$rest       = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-rest-api.php' );

$assert( 3 === substr_count( $detail_src, 'data-som-advance-step' ), 'source_three_advance_forms' );

$pack_src = $section_between( $detail_src, 'class="som-panel som-panel-pack"', 'id="som-post-ship"' );
$assert( 1 === substr_count( $pack_src, 'som-mark-done-form' ), 'source_pack_one_mark_done' );
$assert( false !== strpos( $pack_src, 'data-som-advance-step' ), 'source_pack_advance_step' );
$assert( false === strpos( $pack_src, 'som_order_item_id' ), 'source_pack_no_item_id' );

$unblock_has_advance = false;
foreach ( explode( "\n", $detail_src ) as $line ) {
	if ( false !== strpos( $line, 'som-unblock-step-form' ) && false !== strpos( $line, 'data-som-advance-step' ) ) {
		$unblock_has_advance = true;
	}
}
$assert( ! $unblock_has_advance, 'source_unblock_stays_full_post' );

$fn_start = strpos( $js, 'function initAdvanceStepRest()' );
$fn_end   = strpos( $js, 'function initCountdowns()' );
$fn       = ( false !== $fn_start && false !== $fn_end && $fn_end > $fn_start )
	? substr( $js, $fn_start, $fn_end - $fn_start )
	: '';
$assert( '' !== $fn, 'js_function_present' );

$guard    = strpos( $fn, '! somAdmin.restUrl' );
$listener = strpos( $fn, 'addEventListener' );
$item_key = strpos( $fn, 'body.order_item_id' );
$fetch_at = strpos( $fn, '/advance-step' );
$reload   = strpos( $fn, 'window.location.reload()' );
$fail_at  = strpos( $fn, 'if ( ! result.ok )' );
$alert_at = strpos( $fn, 'window.alert( msg )' );

$assert( false !== $guard && false !== $listener && $guard < $listener, 'js_missing_rest_leaves_form_post' );
$assert( false !== strpos( $fn, 'input[name="som_order_item_id"]' ), 'js_reads_form_item_id' );
$assert( false !== strpos( $fn, 'if ( itemId > 0 )' ), 'js_sends_item_only_when_present' );
$assert( false !== strpos( $fn, 'JSON.stringify( body )' ), 'js_json_body' );
$assert( false === strpos( $fn, "body: '{}'" ), 'js_no_empty_body_literal' );
$assert( false !== $item_key && false !== $fetch_at && false !== $reload && $item_key < $fetch_at && $fetch_at < $reload, 'js_sends_order_item_id_then_reloads' );
$assert( false !== $fail_at && false !== $alert_at && $fail_at < $alert_at && $alert_at < $reload, 'js_alerts_before_reload' );
$between = ( false !== $alert_at && false !== $reload && $reload > $alert_at ) ? substr( $fn, $alert_at, $reload - $alert_at ) : '';
$assert( false !== strpos( $between, 'return;' ), 'js_failure_returns_before_reload' );
$assert( false !== strpos( $between, 'btn.disabled = false' ), 'js_failure_reenables_button' );
$assert( false === strpos( $between, 'location.href' ) && false === strpos( $between, 'location.assign' ), 'js_failure_does_not_navigate' );

$assert( false !== strpos( $rest, '/orders/(?P<id>\d+)/advance-step' ), 'existing_advance_route' );
$assert( false !== strpos( $rest, 'SOM_Item_Make::mark_done' ), 'route_calls_item_mark_done' );

$render_detail = static function ( $order_id ) {
	$_GET['order_id'] = (int) $order_id;
	ob_start();
	SOM_Admin_Menu::render_orders();
	return (string) ob_get_clean();
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$make = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => 'UP14-S10 make ' . $suffix,
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

$post_ship = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => 'UP14-S10 post-ship ' . $suffix,
		'kind'      => 'post_ship',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Update buyer',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Confirm arrived',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'package_arrived',
		),
	)
);
$assert( ! is_wp_error( $post_ship ) && (int) $post_ship > 0, 'create_post_ship' );
$post_ship_id = is_wp_error( $post_ship ) ? 0 : (int) $post_ship;

$product = $make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => 'UP14-S10 sellable ' . $suffix,
			'workflow_template_id' => $make_id,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $product ) && (int) $product > 0, 'create_product' );
$product_id = is_wp_error( $product ) ? 0 : (int) $product;

$order_id = $product_id > 0
	? SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'ebay',
			'external_order_id' => 'up14s10-' . $suffix,
			'buyer_name'        => 'UP14-S10 buyer',
			'shipping_address'  => array(
				'line1'    => '1 Mark Done Street',
				'city'     => 'London',
				'postcode' => 'E1 1AA',
				'country'  => 'GB',
			),
			'items'             => array(
				array(
					'product_id' => $product_id,
					'quantity'   => 1,
					'unit_price' => 5,
				),
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $order_id ) && (int) $order_id > 0, 'create_order' );
$order_id = is_wp_error( $order_id ) ? 0 : (int) $order_id;

$started = ( $order_id > 0 && $post_ship_id > 0 ) ? SOM_Post_Ship::start( $order_id, $post_ship_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $started, 'start_post_ship' );

$order   = $order_id > 0 ? SOM_Orders::get( $order_id ) : null;
$line_id = ( $order && ! empty( $order->items[0]->id ) ) ? (int) $order->items[0]->id : 0;
$assert( $line_id > 0, 'order_line' );
$assert( $order && empty( $order->is_complete ) && empty( $order->is_cancelled ), 'order_open' );

$html = $order_id > 0 ? $render_detail( $order_id ) : '';

$make_html = $section_between( $html, 'id="som-make-line-' . $line_id . '"', 'id="som-pack"' );
$make_form = $mark_done_form( $make_html );
$assert( '' !== $make_form, 'rendered_make_mark_done' );
$assert( false !== strpos( $make_form, 'data-som-advance-step' ), 'make_form_advance_step' );
$assert( false !== strpos( $make_form, 'data-order-id="' . $order_id . '"' ), 'make_form_order_id' );
$assert(
	false !== strpos( $make_form, 'name="som_order_item_id" value="' . $line_id . '"' ),
	'make_form_line_order_item_id'
);

$post_html = $section_between( $html, 'id="som-post-ship"', 'class="som-panel som-panel-stock"' );
$post_form = $mark_done_form( $post_html );
$assert( '' !== $post_form, 'rendered_post_ship_mark_done' );
$assert( false !== strpos( $post_form, 'data-som-advance-step' ), 'post_ship_form_advance_step' );
$assert( false !== strpos( $post_form, 'data-order-id="' . $order_id . '"' ), 'post_ship_form_order_id' );
$assert( false === strpos( $post_form, 'som_order_item_id' ), 'post_ship_form_no_som_order_item_id' );
$assert( false === strpos( $post_form, 'order_item_id' ), 'post_ship_form_no_order_item_id' );

if ( $fail > 0 ) {
	echo "FAIL — UP14-S10 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S10 smoke\n";
