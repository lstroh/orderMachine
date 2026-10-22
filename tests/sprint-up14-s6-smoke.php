<?php
/**
 * UP14-S6 smoke: Trays sit above Make, and an Internal job honours Uses trays.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s6-smoke.php
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
			'user_login' => 'up14s6admin',
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'up14s6admin@example.com',
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
$trays_at   = strpos( $detail_src, 'id="som-trays"' );
$make_at    = strpos( $detail_src, 'class="som-panel som-panel-make"' );
$pack_at    = strpos( $detail_src, 'class="som-panel som-panel-pack"' );
$assert( false !== $trays_at && false !== $make_at && $trays_at < $make_at, 'source_trays_above_make' );
$assert( false !== $pack_at && $make_at < $pack_at, 'source_make_before_pack' );
$assert( false !== strpos( $detail_src, 'href="#som-trays"' ), 'source_make_warning_links_trays' );
$assert( false !== strpos( $detail_src, 'som-trays-release-form' ) && false !== strpos( $detail_src, 'som-trays-assign-form' ), 'source_assign_and_release_stay' );
$assert( false === strpos( $detail_src, 'Release anyway' ), 'source_no_release_anyway' );

$render_detail = static function ( $order_id ) {
	$_GET['order_id'] = (int) $order_id;
	ob_start();
	SOM_Admin_Menu::render_orders();
	return (string) ob_get_clean();
};

$clear_tray_pack = static function ( $order_id ) {
	global $wpdb;
	$order = SOM_Orders::get( (int) $order_id );
	if ( ! $order || empty( $order->pack_workflow_template_id ) ) {
		return $order;
	}
	$pack = SOM_Workflows::get( (int) $order->pack_workflow_template_id );
	if ( ! $pack || empty( $pack->uses_trays ) ) {
		return $order;
	}
	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . SOM_DB::table( 'orders' ) . ' SET pack_workflow_template_id = NULL WHERE id = %d',
			(int) $order_id
		)
	);
	return SOM_Orders::get( (int) $order_id );
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$input_id = SOM_Materials::create(
	array(
		'name'      => 'UP14-S6 input ' . $suffix,
		'unit'      => 'sheet',
		'unit_cost' => 1,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $input_id ) && (int) $input_id > 0, 'create_input' );
$input_id = is_wp_error( $input_id ) ? 0 : (int) $input_id;
if ( $input_id > 0 ) {
	$restock = SOM_Materials::adjust_stock(
		$input_id,
		20,
		array(
			'reason'            => 'restock',
			'unit_cost_at_time' => 1,
			'value_change'      => 20,
			'sync_unit_cost'    => true,
		)
	);
	$assert( ! is_wp_error( $restock ), 'restock_input' );
}

$tray_make = SOM_Workflows::create(
	array(
		'name'       => 'UP14-S6 trays ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 1,
	)
);
$assert( ! is_wp_error( $tray_make ) && (int) $tray_make > 0, 'create_tray_make' );
$tray_make_id = is_wp_error( $tray_make ) ? 0 : (int) $tray_make;
if ( $tray_make_id > 0 ) {
	$saved = SOM_Workflows::save_steps(
		$tray_make_id,
		array(
			array(
				'name'                    => 'Print',
				'requires_manual_confirm' => 1,
			),
		)
	);
	$assert( true === $saved, 'save_tray_make_steps' );
}
$tray_tpl = $tray_make_id > 0 ? SOM_Workflows::get( $tray_make_id ) : null;
$assert( $tray_tpl && ! empty( $tray_tpl->uses_trays ), 'tray_make_uses_trays' );

$plain_make = SOM_Workflows::create(
	array(
		'name'       => 'UP14-S6 plain ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 0,
	)
);
$assert( ! is_wp_error( $plain_make ) && (int) $plain_make > 0, 'create_plain_make' );
$plain_make_id = is_wp_error( $plain_make ) ? 0 : (int) $plain_make;
if ( $plain_make_id > 0 ) {
	$saved_plain = SOM_Workflows::save_steps(
		$plain_make_id,
		array(
			array(
				'name'                    => 'Cut',
				'requires_manual_confirm' => 1,
			),
		)
	);
	$assert( true === $saved_plain, 'save_plain_make_steps' );
}

$make_internal = static function ( $name, $workflow_id ) use ( $input_id, $assert ) {
	if ( $workflow_id < 1 || $input_id < 1 ) {
		return 0;
	}
	$created = SOM_Products::create(
		array(
			'name'                 => $name,
			'workflow_template_id' => $workflow_id,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	);
	if ( is_wp_error( $created ) || (int) $created < 1 ) {
		return 0;
	}
	$product_id = (int) $created;
	$recipe     = SOM_Products::save_recipe(
		$product_id,
		array(
			array(
				'material_id'       => $input_id,
				'quantity_per_unit' => 1,
			),
		)
	);
	$assert( ! is_wp_error( $recipe ), 'recipe_' . $product_id );
	return is_wp_error( $recipe ) ? 0 : $product_id;
};

$internal_id = $make_internal( 'UP14-S6 internal trays ' . $suffix, $tray_make_id );
$assert( $internal_id > 0, 'create_internal_trays_product' );
$plain_internal_id = $make_internal( 'UP14-S6 internal plain ' . $suffix, $plain_make_id );
$assert( $plain_internal_id > 0, 'create_internal_plain_product' );

$sellable_plain = $plain_make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => 'UP14-S6 sellable plain ' . $suffix,
			'workflow_template_id' => $plain_make_id,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $sellable_plain ) && (int) $sellable_plain > 0, 'create_sellable_plain' );
$sellable_plain_id = is_wp_error( $sellable_plain ) ? 0 : (int) $sellable_plain;

$sellable_trays = $tray_make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => 'UP14-S6 sellable trays ' . $suffix,
			'workflow_template_id' => $tray_make_id,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $sellable_trays ) && (int) $sellable_trays > 0, 'create_sellable_trays' );
$sellable_trays_id = is_wp_error( $sellable_trays ) ? 0 : (int) $sellable_trays;

$internal_order_id = $internal_id > 0 ? SOM_Production::produce( $internal_id, 1 ) : new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $internal_order_id ) && (int) $internal_order_id > 0, 'produce_internal_trays' );
$internal_order_id = is_wp_error( $internal_order_id ) ? 0 : (int) $internal_order_id;

$plain_order_id = $plain_internal_id > 0 ? SOM_Production::produce( $plain_internal_id, 1 ) : new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $plain_order_id ) && (int) $plain_order_id > 0, 'produce_internal_plain' );
$plain_order_id = is_wp_error( $plain_order_id ) ? 0 : (int) $plain_order_id;

$customer_id = ( $sellable_plain_id > 0 && $internal_id > 0 )
	? SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => 'up14s6-c-' . $suffix,
			'buyer_name'        => 'UP14-S6 customer',
			'items'             => array(
				array(
					'product_id' => $sellable_plain_id,
					'quantity'   => 1,
					'unit_price' => 4,
				),
				array(
					'product_id' => $internal_id,
					'quantity'   => 1,
					'unit_price' => 0,
				),
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $customer_id ) && (int) $customer_id > 0, 'create_customer_order' );
$customer_id = is_wp_error( $customer_id ) ? 0 : (int) $customer_id;

$sellable_order_id = $sellable_trays_id > 0
	? SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => 'up14s6-s-' . $suffix,
			'buyer_name'        => 'UP14-S6 sellable',
			'items'             => array(
				array(
					'product_id' => $sellable_trays_id,
					'quantity'   => 1,
					'unit_price' => 6,
				),
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $sellable_order_id ) && (int) $sellable_order_id > 0, 'create_sellable_order' );
$sellable_order_id = is_wp_error( $sellable_order_id ) ? 0 : (int) $sellable_order_id;

// Internal job whose make template uses trays: blocked until one tray is held.
$internal_order = $internal_order_id > 0 ? SOM_Orders::get( $internal_order_id ) : null;
$assert( $internal_order && SOM_Production::CHANNEL_SLUG === (string) $internal_order->channel_slug, 'internal_channel' );
$internal_product = $internal_id > 0 ? SOM_Products::get( $internal_id ) : null;
$assert( $internal_product && ! empty( $internal_product->is_internal ), 'internal_product_is_internal' );
$assert( $internal_product && (int) $internal_product->workflow_template_id === $tray_make_id, 'internal_product_uses_tray_workflow' );
$assert( $internal_order && SOM_Trays::order_requires_trays( $internal_order ), 'internal_requires_trays' );
$assert( $internal_order && ! SOM_Trays::order_has_held_trays( $internal_order_id ), 'internal_no_tray_yet' );
$assert( $internal_order && ! SOM_Trays::trays_gate_ok( $internal_order ), 'internal_gate_blocked' );

$internal_html = $internal_order_id > 0 ? $render_detail( $internal_order_id ) : '';
$html_trays    = strpos( $internal_html, 'id="som-trays"' );
$html_make     = strpos( $internal_html, 'class="som-panel som-panel-make"' );
$assert( false !== $html_trays && false !== $html_make && $html_trays < $html_make, 'rendered_trays_above_make' );
$make_html     = ( false !== $html_make ) ? substr( $internal_html, $html_make ) : '';
$assert( false !== strpos( $make_html, 'href="#som-trays"' ), 'rendered_make_warning_links_trays' );
$assert( false !== strpos( $make_html, 'Trays are required for this order.' ), 'rendered_make_warning_text' );
$trays_html    = ( false !== $html_trays && false !== $html_make ) ? substr( $internal_html, $html_trays, $html_make - $html_trays ) : '';
$assert( false !== strpos( $trays_html, 'som-trays-assign-form' ), 'rendered_assign_form' );
$assert( false !== strpos( $trays_html, 'No trays held on this order.' ), 'rendered_none_held' );
$assert( false === strpos( $internal_html, 'Release anyway' ), 'rendered_no_release_anyway' );

$free = SOM_Trays::free_tray_numbers();
$assert( count( $free ) >= 2, 'two_free_trays' );
$tray_a = ! empty( $free[0] ) ? (int) $free[0] : 0;
$tray_b = ! empty( $free[1] ) ? (int) $free[1] : 0;

$assigned = ( $internal_order_id > 0 && $tray_a > 0 ) ? SOM_Trays::assign( $internal_order_id, array( $tray_a ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $assigned, 'internal_assign_one_tray' );
$internal_held = $internal_order_id > 0 ? SOM_Orders::get( $internal_order_id ) : null;
$assert( $internal_held && SOM_Trays::trays_gate_ok( $internal_held ), 'internal_gate_passes_with_tray' );
$assert( $internal_held && array( $tray_a ) === SOM_Trays::held_numbers_for_order( $internal_order_id ), 'internal_holds_assigned_tray' );

$keep = ( $internal_order_id > 0 && $tray_a > 0 )
	? SOM_Trays::release( $internal_order_id, array( $tray_a ), SOM_Trays::REASON_MANUAL )
	: new WP_Error( 'skipped', 'skipped' );
$assert( is_wp_error( $keep ) && 'som_tray_keep_one' === $keep->get_error_code(), 'internal_keeps_last_tray' );
$assert( $internal_order_id > 0 && SOM_Trays::trays_gate_ok( $internal_order_id ), 'internal_gate_still_ok_after_refused_release' );

// Internal job whose make template does not use trays stays open without a tray.
$plain_order = $plain_order_id > 0 ? SOM_Orders::get( $plain_order_id ) : null;
$assert( $plain_order && SOM_Production::CHANNEL_SLUG === (string) $plain_order->channel_slug, 'plain_internal_channel' );
$assert( $plain_order && ! SOM_Trays::order_requires_trays( $plain_order ), 'plain_internal_does_not_require' );
$assert( $plain_order && ! SOM_Trays::order_has_held_trays( $plain_order_id ), 'plain_internal_no_tray' );
$assert( $plain_order && SOM_Trays::trays_gate_ok( $plain_order ), 'plain_internal_gate_ok' );

// Customer order: the internal component uses trays; the sellable line does not.
$customer = $customer_id > 0 ? $clear_tray_pack( $customer_id ) : null;
$assert( $customer && 'external' === (string) $customer->channel_slug, 'customer_channel' );
$saw_sellable = false;
$saw_internal = false;
if ( $customer && ! empty( $customer->items ) ) {
	foreach ( $customer->items as $item ) {
		$product_id = isset( $item->product_id ) ? (int) $item->product_id : 0;
		if ( $product_id === $sellable_plain_id ) {
			$saw_sellable = true;
		}
		if ( $product_id === $internal_id ) {
			$saw_internal = true;
		}
	}
}
$assert( $saw_sellable && $saw_internal, 'customer_has_sellable_and_internal_component' );
$assert( $customer && ! SOM_Trays::order_has_held_trays( $customer_id ), 'customer_no_tray_held' );
$assert( $customer && ! SOM_Trays::order_requires_trays( $customer ), 'customer_internal_component_does_not_require' );
$assert( $customer && SOM_Trays::trays_gate_ok( $customer ), 'customer_gate_ok_without_tray' );

// Package 8: a sellable line with Uses trays still requires a tray.
$sellable_order = $sellable_order_id > 0 ? $clear_tray_pack( $sellable_order_id ) : null;
$sellable_row   = $sellable_trays_id > 0 ? SOM_Products::get( $sellable_trays_id ) : null;
$assert( $sellable_row && empty( $sellable_row->is_internal ), 'sellable_line_is_not_internal' );
$assert( $sellable_row && (int) $sellable_row->workflow_template_id === $tray_make_id, 'sellable_line_uses_tray_workflow' );
$assert( $sellable_order && SOM_Trays::order_requires_trays( $sellable_order ), 'sellable_requires_trays' );
$assert( $sellable_order && ! SOM_Trays::order_has_held_trays( $sellable_order_id ), 'sellable_no_tray_yet' );
$assert( $sellable_order && ! SOM_Trays::trays_gate_ok( $sellable_order ), 'sellable_gate_blocked' );
$sellable_assigned = ( $sellable_order_id > 0 && $tray_b > 0 ) ? SOM_Trays::assign( $sellable_order_id, array( $tray_b ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $sellable_assigned, 'sellable_assign_one_tray' );
$assert( $sellable_order_id > 0 && SOM_Trays::trays_gate_ok( $sellable_order_id ), 'sellable_gate_passes_with_tray' );

// A bound Pack template with Uses trays still requires a tray on its own.
$pack = SOM_Workflows::create(
	array(
		'name'       => 'UP14-S6 pack ' . $suffix,
		'kind'       => 'pack',
		'is_active'  => 1,
		'uses_trays' => 1,
	)
);
$assert( ! is_wp_error( $pack ) && (int) $pack > 0, 'create_pack_uses_trays' );
$pack_id = is_wp_error( $pack ) ? 0 : (int) $pack;
if ( $customer_id > 0 && $pack_id > 0 ) {
	global $wpdb;
	$wpdb->update(
		SOM_DB::table( 'orders' ),
		array( 'pack_workflow_template_id' => $pack_id ),
		array( 'id' => $customer_id ),
		array( '%d' ),
		array( '%d' )
	);
}
$customer_pack = $customer_id > 0 ? SOM_Orders::get( $customer_id ) : null;
$assert( $customer_pack && (int) $customer_pack->pack_workflow_template_id === $pack_id, 'customer_pack_bound' );
$assert( $customer_pack && SOM_Trays::order_requires_trays( $customer_pack ), 'pack_uses_trays_still_requires' );
$assert( $customer_pack && ! SOM_Trays::order_has_held_trays( $customer_id ) && ! SOM_Trays::trays_gate_ok( $customer_pack ), 'pack_uses_trays_gate_blocked' );

if ( $fail > 0 ) {
	echo "FAIL — UP14-S6 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S6 smoke\n";
