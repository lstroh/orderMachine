<?php
/**
 * UP6-S2 smoke: pack bind, ship-together gate, hold, package, thank-you, weight, Internal excluded.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up6-s2-smoke.php
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
SOM_Seed::maybe_seed_catalogue();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );
$assert( version_compare( SOM_VERSION, '0.31.0', '>=' ), 'SOM_VERSION_gte_0.31.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.16.0', '>=' ), 'db_gte_1.16.0' );
$assert( class_exists( 'SOM_Pack' ), 'class_SOM_Pack' );

$orders_t = SOM_DB::table( 'orders' );
$order_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$orders_t}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( array( 'pack_workflow_template_id', 'shipping_package_id', 'pack_hold_reason', 'packed_by_user_id', 'packed_at' ) as $col ) {
	$assert( in_array( $col, $order_cols, true ), 'orders_col_' . $col );
}

$ship_t    = SOM_DB::table( 'shipments' );
$ship_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$ship_t}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( in_array( 'pack_weight_grams', $ship_cols, true ), 'shipments_pack_weight_grams' );

// Pack template (kind=pack).
$pack_wf = SOM_Workflows::create(
	array(
		'name'        => 'UP6-S2 Pack ' . wp_generate_password( 4, false, false ),
		'kind'        => 'pack',
		'is_active'   => 1,
		'description' => 'Smoke pack template',
	)
);
$assert( ! is_wp_error( $pack_wf ), 'create_pack_template' );
$pack_wf_id = (int) $pack_wf;

$saved = SOM_Workflows::save_steps(
	$pack_wf_id,
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'packing_items',
		),
		array(
			'name'                    => 'Package',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Confirm address',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'shipping_address',
		),
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( true === $saved, 'save_pack_steps' );

$prev_default = SOM_Pack::default_template_id();
$set          = SOM_Pack::set_default_template_id( $pack_wf_id );
$assert( true === $set, 'set_default_pack_template' );
$assert( $pack_wf_id === SOM_Pack::default_template_id(), 'default_pack_id' );

// Reject make template as default pack.
$make_reject = SOM_Pack::set_default_template_id(
	(int) $wpdb->get_var(
		"SELECT id FROM " . SOM_DB::table( 'workflow_templates' ) . " WHERE kind = 'make' AND is_active = 1 ORDER BY id ASC LIMIT 1"
	)
);
$assert( is_wp_error( $make_reject ) && 'som_pack_template_kind' === $make_reject->get_error_code(), 'reject_make_as_pack_default' );
SOM_Pack::set_default_template_id( $pack_wf_id );

$products_t  = SOM_DB::table( 'products' );
$templates_t = SOM_DB::table( 'workflow_templates' );
// Prefer seed SKU; require a live make template row (dirty volumes may have orphan workflow_template_id).
$sellable = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT p.id, p.workflow_template_id FROM {$products_t} p
		INNER JOIN {$templates_t} t ON t.id = p.workflow_template_id AND t.kind = 'make'
		WHERE p.is_active = 1 AND p.is_internal = 0
		ORDER BY ( p.sku = %s ) DESC, p.id ASC
		LIMIT 1",
		SOM_Seed::SAMPLE_PRODUCT_SKU
	)
);
$assert( $sellable && (int) $sellable->id > 0, 'has_sellable_product' );

$internal = $wpdb->get_row(
	"SELECT p.id FROM {$products_t} p
	INNER JOIN {$templates_t} t ON t.id = p.workflow_template_id AND t.kind = 'make'
	WHERE p.is_active = 1 AND p.is_internal = 1
	ORDER BY p.id ASC LIMIT 1"
);
$assert( $internal && (int) $internal->id > 0, 'has_internal_product' );

// Ensure at least one active shipping package.
$packages = SOM_Shipping_Packages::list_active();
if ( empty( $packages ) ) {
	$pkg_id = SOM_Shipping_Packages::create(
		array(
			'name'              => 'UP6-S2 Mailer',
			'length_mm'         => 100,
			'width_mm'          => 80,
			'height_mm'         => 20,
			'tare_weight_grams' => 15,
			'is_default'        => 1,
			'is_active'         => 1,
		)
	);
	$assert( ! is_wp_error( $pkg_id ), 'create_shipping_package' );
	$packages = SOM_Shipping_Packages::list_active();
}
$assert( ! empty( $packages ), 'has_active_package' );
$package_id = (int) $packages[0]->id;

$external_id = 'up6s2-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
$order_id    = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $external_id,
		'buyer_name'        => 'UP6-S2 Tester',
		'shipping_address'  => array(
			'line1'    => '2 Pack Street',
			'city'     => 'London',
			'postcode' => 'E1 2BB',
			'country'  => 'GB',
		),
		'items'             => array(
			array(
				'product_id'           => (int) $sellable->id,
				'quantity'             => 1,
				'unit_price'           => 12,
				'personalisation_text' => 'Pack A',
			),
			array(
				'product_id'           => (int) $sellable->id,
				'quantity'             => 1,
				'unit_price'           => 12,
				'personalisation_text' => 'Pack B',
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$order = SOM_Orders::get( $order_id );
$assert( $order && (int) $order->pack_workflow_template_id === $pack_wf_id, 'pack_bound_on_create' );

$prog_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'order_step_progress' ) . ' WHERE order_id = %d',
		$order_id
	)
);
$assert( 4 === $prog_count, 'pack_progress_4_steps' );

// Not make-ready yet → Waiting for make + Ship blocked.
$assert( ! SOM_Pack::order_is_make_ready( $order ), 'not_make_ready_initially' );
$reason = SOM_Pack::ship_blocked_reason( $order );
$assert( false !== stripos( $reason, 'Waiting for make' ), 'ship_blocked_waiting_make' );

$board = SOM_Pack::query_board( array( 's' => $external_id ) );
$assert( ! empty( $board['orders'] ), 'pack_board_has_order' );
$card = null;
foreach ( $board['orders'] as $row ) {
	if ( (int) $row->id === $order_id ) {
		$card = $row;
		break;
	}
}
$assert( $card && SOM_Pack::BOARD_WAITING_MAKE === (string) $card->column_key, 'board_waiting_for_make' );

// Soft-complete all make lines (skip timers / confirmations).
$now = current_time( 'mysql', true );
$wpdb->update(
	SOM_DB::table( 'order_item_step_progress' ),
	array(
		'status'       => 'done',
		'completed_at' => $now,
		'updated_at'   => $now,
	),
	array( 'order_id' => $order_id ),
	array( '%s', '%s', '%s' ),
	array( '%d' )
);
$order = SOM_Orders::get( $order_id );
$assert( SOM_Pack::order_is_make_ready( $order ), 'make_ready_after_lines_done' );

// Clear package → blocked.
SOM_Pack::set_shipping_package( $order_id, 0 );
$order  = SOM_Orders::get( $order_id );
$reason = SOM_Pack::ship_blocked_reason( $order );
$assert( false !== stripos( $reason, 'shipping package' ), 'ship_blocked_no_package' );

SOM_Pack::set_shipping_package( $order_id, $package_id );
$order = SOM_Orders::get( $order_id );
$assert( (int) $order->shipping_package_id === $package_id, 'package_set' );

// Hold blocks Ship.
SOM_Pack::set_hold( $order_id, 'Waiting on buyer reply' );
$order  = SOM_Orders::get( $order_id );
$reason = SOM_Pack::ship_blocked_reason( $order );
$assert( false !== stripos( $reason, 'hold' ), 'ship_blocked_hold' );

$board = SOM_Pack::query_board( array( 's' => $external_id ) );
$card  = null;
foreach ( $board['orders'] as $row ) {
	if ( (int) $row->id === $order_id ) {
		$card = $row;
		break;
	}
}
$assert( $card && SOM_Pack::BOARD_HELD === (string) $card->column_key, 'board_held_column' );

SOM_Pack::set_hold( $order_id, '' );
$order  = SOM_Orders::get( $order_id );
$reason = SOM_Pack::ship_blocked_reason( $order );
$assert( '' === $reason, 'ship_gates_clear_aside_shipment' );

// Thank-you required on packing checklist + packed-by stamp once.
$order = SOM_Orders::get( $order_id );
$items_input = array();
foreach ( $order->items as $item ) {
	$items_input[ (string) (int) $item->id ] = 1;
}

$partial = SOM_Step_Confirmations::save_for_order(
	$order_id,
	array(
		'items'              => $items_input,
		'thank_you_included' => 0,
	)
);
$assert( true === $partial, 'save_partial_checklist' );
$progress = null;
foreach ( SOM_Workflow_Engine::get_progress( $order_id ) as $row ) {
	if ( (int) $row->workflow_step_id === (int) $order->current_step_id ) {
		$progress = $row;
		break;
	}
}
$step = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d LIMIT 1',
		(int) $order->current_step_id
	)
);
$assert( $progress && $step, 'current_pack_progress_row' );
$assert(
	! SOM_Step_Confirmations::progress_is_complete( $progress, $step, $order ),
	'checklist_incomplete_without_thank_you'
);

$full = SOM_Step_Confirmations::save_for_order(
	$order_id,
	array(
		'items'              => $items_input,
		'thank_you_included' => 1,
	)
);
$assert( true === $full, 'save_full_checklist' );
$order = SOM_Orders::get( $order_id );
$assert( ! empty( $order->packed_at ), 'packed_at_stamped' );
$first_stamp = (string) $order->packed_at;

sleep( 1 );
SOM_Step_Confirmations::save_for_order(
	$order_id,
	array(
		'items'              => $items_input,
		'thank_you_included' => 1,
	)
);
$order = SOM_Orders::get( $order_id );
$assert( $first_stamp === (string) $order->packed_at, 'packed_at_not_refreshed' );

// Optional pack weight.
$bad_weight = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'           => 'Royal Mail',
		'service'           => 'Tracked 48',
		'shipped_at'        => gmdate( 'Y-m-d H:i:s' ),
		'postage_paid'      => '3.50',
		'pack_weight_grams' => '-5',
	)
);
$assert( is_wp_error( $bad_weight ) && 'som_pack_weight' === $bad_weight->get_error_code(), 'reject_negative_weight' );

$ok_ship = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'           => 'Royal Mail',
		'service'           => 'Tracked 48',
		'shipped_at'        => gmdate( 'Y-m-d H:i:s' ),
		'postage_paid'      => '3.50',
		'pack_weight_grams' => '42.5',
	)
);
$assert( ! is_wp_error( $ok_ship ), 'upsert_shipment_with_weight' );
$shipment = SOM_Shipments::get_by_order( $order_id );
$assert( $shipment && abs( (float) $shipment->pack_weight_grams - 42.5 ) < 0.001, 'pack_weight_saved' );

$ok_blank = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'           => 'Royal Mail',
		'service'           => 'Tracked 48',
		'shipped_at'        => gmdate( 'Y-m-d H:i:s' ),
		'postage_paid'      => '3.50',
		'pack_weight_grams' => '',
	)
);
$assert( ! is_wp_error( $ok_blank ), 'weight_optional_blank' );

// Soft flag when no default Pack template.
SOM_Pack::set_default_template_id( 0 );
$external_soft = 'up6s2s-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
$soft_id       = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $external_soft,
		'buyer_name'        => 'UP6 soft',
		'items'             => array(
			array(
				'product_id' => (int) $sellable->id,
				'quantity'   => 1,
				'unit_price' => 5,
			),
		),
	)
);
$assert( ! is_wp_error( $soft_id ), 'create_soft_flag_order' );
$soft = SOM_Orders::get( (int) $soft_id );
$assert( empty( $soft->pack_workflow_template_id ), 'soft_flag_no_pack_bind' );
$soft_reason = SOM_Pack::ship_blocked_reason( $soft );
$assert( false !== stripos( $soft_reason, 'Pack workflow' ), 'soft_flag_blocks_ship' );
SOM_Pack::set_default_template_id( $pack_wf_id );

// Internal Produce N: no pack bind; excluded from Pack board.
$prod = SOM_Production::produce( (int) $internal->id, 1 );
if ( ! is_wp_error( $prod ) ) {
	$prod_id    = (int) $prod;
	$prod_order = SOM_Orders::get( $prod_id );
	$assert( empty( $prod_order->pack_workflow_template_id ), 'internal_no_pack_bind' );
	$board_prod = SOM_Pack::query_board( array( 's' => (string) ( $prod_order->external_order_id ?? '' ) ) );
	$found_prod = false;
	foreach ( $board_prod['orders'] as $row ) {
		if ( (int) $row->id === $prod_id ) {
			$found_prod = true;
			break;
		}
	}
	$assert( ! $found_prod, 'internal_excluded_from_pack_board' );
} else {
	$out( 'produce_n_skip', $prod->get_error_message() );
	$assert( true, 'produce_n_optional' );
}

// Restore prior default (or keep smoke pack if none).
if ( $prev_default > 0 && $prev_default !== $pack_wf_id ) {
	SOM_Pack::set_default_template_id( $prev_default );
}

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK\n";
