<?php
/**
 * UP8-S2 smoke: trays require/complete, Unblock step (all gates), board tray badges.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up8-s2-smoke.php
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
SOM_Batch_Groups::ensure_rows();
SOM_Seed::maybe_seed_catalogue();
SOM_Trays::init();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.20.0', '>=' ), 'db_gte_1.20.0' );

$osp_t  = SOM_DB::table( 'order_step_progress' );
$oisp_t = SOM_DB::table( 'order_item_step_progress' );
foreach ( array( $osp_t, $oisp_t ) as $t ) {
	$col = $wpdb->get_row( "SHOW COLUMNS FROM {$t} LIKE 'manual_unblock_at'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( ! empty( $col ), 'column_manual_unblock_at_' . basename( str_replace( $wpdb->prefix . 'som_', '', $t ) ) );
	$col = $wpdb->get_row( "SHOW COLUMNS FROM {$t} LIKE 'manual_unblock_by'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( ! empty( $col ), 'column_manual_unblock_by_' . basename( str_replace( $wpdb->prefix . 'som_', '', $t ) ) );
	$col = $wpdb->get_row( "SHOW COLUMNS FROM {$t} LIKE 'manual_unblock_gates'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$assert( ! empty( $col ), 'column_manual_unblock_gates_' . basename( str_replace( $wpdb->prefix . 'som_', '', $t ) ) );
}

$detail_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'som_unblock_step' ), 'ui_unblock_step_control' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'Allows Mark done without meeting this step' ), 'ui_unblock_confirm_copy' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'som-trays-required-notice' ), 'ui_trays_required_notice' );

$board_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/orders-board.php' );
$assert( is_string( $board_src ) && false !== strpos( $board_src, 'som-badge-tray' ), 'make_board_tray_badge' );
$pack_board_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/orders-pack-board.php' );
$assert( is_string( $pack_board_src ) && false !== strpos( $pack_board_src, 'som-badge-tray' ), 'pack_board_tray_badge' );

$admin_user = get_user_by( 'login', 'admin' );
if ( ! $admin_user ) {
	$uid = wp_create_user( 'up8s2admin', wp_generate_password(), 'up8s2admin@example.com' );
	$assert( ! is_wp_error( $uid ), 'create_test_user' );
	$admin_user = get_user_by( 'id', (int) $uid );
}
wp_set_current_user( (int) $admin_user->ID );

$suffix    = wp_generate_password( 4, false, false );
$prev_pack = SOM_Pack::default_template_id();
$prev_max  = SOM_Trays::get_max();
SOM_Trays::set_max( 10 );

// Pack template with uses_trays + ship step (for hold/package/ship gates).
$wf_pack = SOM_Workflows::create(
	array(
		'name'       => 'UP8-S2 pack ' . $suffix,
		'kind'       => 'pack',
		'is_active'  => 1,
		'uses_trays' => 0,
	)
);
$assert( ! is_wp_error( $wf_pack ), 'create_pack_template' );
$wf_pack_id = (int) $wf_pack;
$saved_pack = SOM_Workflows::save_steps(
	$wf_pack_id,
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'packing_items',
		),
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( true === $saved_pack, 'save_pack_steps' );
SOM_Pack::set_default_template_id( $wf_pack_id );

// Make template with uses_trays + timer + checklist.
$wf_make = SOM_Workflows::create(
	array(
		'name'       => 'UP8-S2 make ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 1,
	)
);
$assert( ! is_wp_error( $wf_make ), 'create_make_template' );
$wf_make_id = (int) $wf_make;
$saved_make = SOM_Workflows::save_steps(
	$wf_make_id,
	array(
		array(
			'name'                    => 'Print',
			'requires_manual_confirm' => 1,
			'timer_seconds'           => 3600,
			'confirmation_kind'       => 'print_vs_request',
		),
	)
);
$assert( true === $saved_make, 'save_make_steps' );

$product = SOM_Products::create(
	array(
		'name'                 => 'UP8-S2 product ' . $suffix,
		'sku'                  => 'UP8S2-' . $suffix,
		'workflow_template_id' => $wf_make_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up8s2-' . $suffix,
		'buyer_name'        => 'UP8-S2 Buyer',
		'items'             => array(
			array(
				'product_id'           => $product_id,
				'quantity'             => 1,
				'unit_price'           => 5,
				'personalisation_text' => 'Tray test',
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$order = SOM_Orders::get( $order_id );
$assert( $order && SOM_Trays::order_requires_trays( $order ), 'order_requires_trays' );
$assert( ! SOM_Trays::order_has_held_trays( $order_id ), 'no_trays_yet' );

$item_id = (int) $order->items[0]->id;
$current = SOM_Item_Make::current_item_progress( $item_id );
$assert( $current && 'waiting_timer' === (string) $current->status, 'make_waiting_timer' );

$step = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d LIMIT 1',
		(int) $current->workflow_step_id
	)
);
$assert( ! SOM_Workflow_Engine::can_mark_done( $current, $step ), 'blocked_without_trays_and_timer' );

$blocked = SOM_Item_Make::mark_done( $order_id, $item_id );
$assert( is_wp_error( $blocked ), 'mark_done_blocked_without_trays' );

// Assign tray → still blocked by timer, but trays gate ok.
$assign = SOM_Trays::assign( $order_id, array( 1 ) );
$assert( true === $assign, 'assign_tray_1' );
$assert( array( 1 ) === SOM_Trays::held_numbers_for_order( $order_id ), 'holds_tray_1' );

// Soft O2: cannot release last tray while required + open.
$keep = SOM_Trays::release( $order_id, array( 1 ), SOM_Trays::REASON_MANUAL );
$assert( is_wp_error( $keep ) && 'som_tray_keep_one' === $keep->get_error_code(), 'keep_last_tray_while_required' );

// Board data includes tray numbers.
SOM_Trays::attach_board_tray_numbers(
	array(
		(object) array(
			'order_id' => $order_id,
		),
	)
);
$card = (object) array( 'order_id' => $order_id );
SOM_Trays::attach_board_tray_numbers( array( $card ) );
$assert( isset( $card->tray_numbers ) && array( 1 ) === $card->tray_numbers, 'board_tray_numbers' );

// --- Unblock timer (+ trays already assigned) ---
$unblocked = SOM_Workflow_Engine::unblock_step( $order_id, $item_id, (int) $admin_user->ID );
$assert( true === $unblocked, 'unblock_timer_gate' );
$current = SOM_Item_Make::current_item_progress( $item_id );
$assert( $current && 'in_progress' === (string) $current->status, 'status_in_progress_after_unblock' );
$assert( ! empty( $current->manual_unblock_at ), 'audit_manual_unblock_at' );
$assert( (int) $current->manual_unblock_by === (int) $admin_user->ID, 'audit_manual_unblock_by' );
$assert( false !== strpos( (string) $current->manual_unblock_gates, 'timer' ), 'audit_gates_include_timer' );
$assert( SOM_Workflow_Engine::can_mark_done( $current, $step ), 'can_mark_done_after_timer_unblock' );

$notes = SOM_Order_Notes::list_for_order( $order_id );
$note_ok = false;
foreach ( $notes as $n ) {
	if ( false !== strpos( (string) $n->body, 'unblocked' ) ) {
		$note_ok = true;
		break;
	}
}
$assert( $note_ok, 'order_note_on_unblock' );

// Checklist still needed unless we unblocked checklist too — timer unblock may include checklist if incomplete.
// Fresh unblock already set; can_mark_done returns true when unblock set regardless of checklist.
$assert( SOM_Workflow_Engine::has_manual_unblock( $current ), 'has_manual_unblock' );
$done = SOM_Item_Make::mark_done( $order_id, $item_id );
$assert( ! is_wp_error( $done ), 'mark_done_after_unblock' );
$assert( null === SOM_Item_Make::current_item_progress( $item_id ), 'make_line_complete_after_mark_done' );
// Audit fields left on the done row.
$done_row = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$oisp_t} WHERE order_item_id = %d AND workflow_step_id = %d LIMIT 1",
		$item_id,
		(int) $step->id
	)
);
$assert( $done_row && ! empty( $done_row->manual_unblock_at ) && 'done' === (string) $done_row->status, 'audit_left_after_mark_done' );

// --- Checklist-only unblock on a new order ---
$wf_check = SOM_Workflows::create(
	array(
		'name'       => 'UP8-S2 check ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 0,
	)
);
$wf_check_id = (int) $wf_check;
SOM_Workflows::save_steps(
	$wf_check_id,
	array(
		array(
			'name'                    => 'Confirm print',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'print_vs_request',
		),
	)
);
$prod_c = SOM_Products::create(
	array(
		'name'                 => 'UP8-S2 check prod ' . $suffix,
		'sku'                  => 'UP8S2C-' . $suffix,
		'workflow_template_id' => $wf_check_id,
		'is_active'            => 1,
	)
);
$oid_c = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up8s2c-' . $suffix,
		'buyer_name'        => 'UP8-S2 Check',
		'items'             => array(
			array(
				'product_id' => (int) $prod_c,
				'quantity'   => 1,
				'unit_price' => 3,
			),
		),
	)
);
$oid_c   = (int) $oid_c;
$ord_c   = SOM_Orders::get( $oid_c );
$item_c  = (int) $ord_c->items[0]->id;
$cur_c   = SOM_Item_Make::current_item_progress( $item_c );
$step_c  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d', (int) $cur_c->workflow_step_id ) );
$assert( ! SOM_Workflow_Engine::can_mark_done( $cur_c, $step_c ), 'checklist_blocks' );
$ub_c = SOM_Workflow_Engine::unblock_step( $oid_c, $item_c, (int) $admin_user->ID );
$assert( true === $ub_c, 'unblock_checklist' );
$cur_c = SOM_Item_Make::current_item_progress( $item_c );
$assert( SOM_Workflow_Engine::can_mark_done( $cur_c, $step_c ), 'can_mark_after_checklist_unblock' );
$assert( false !== strpos( (string) $cur_c->manual_unblock_gates, 'checklist' ), 'gates_checklist' );

// --- Trays-required unblock (no trays assigned) ---
$wf_tray = SOM_Workflows::create(
	array(
		'name'       => 'UP8-S2 traygate ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 1,
	)
);
$wf_tray_id = (int) $wf_tray;
SOM_Workflows::save_steps(
	$wf_tray_id,
	array(
		array(
			'name'                    => 'Do work',
			'requires_manual_confirm' => 1,
		),
	)
);
$prod_t = SOM_Products::create(
	array(
		'name'                 => 'UP8-S2 traygate prod ' . $suffix,
		'sku'                  => 'UP8S2T-' . $suffix,
		'workflow_template_id' => $wf_tray_id,
		'is_active'            => 1,
	)
);
$oid_t = (int) SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up8s2t-' . $suffix,
		'buyer_name'        => 'UP8-S2 TrayGate',
		'items'             => array(
			array(
				'product_id' => (int) $prod_t,
				'quantity'   => 1,
				'unit_price' => 2,
			),
		),
	)
);
$ord_t  = SOM_Orders::get( $oid_t );
$item_t = (int) $ord_t->items[0]->id;
$cur_t  = SOM_Item_Make::current_item_progress( $item_t );
$step_t = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d', (int) $cur_t->workflow_step_id ) );
$assert( SOM_Trays::order_requires_trays( $ord_t ) && ! SOM_Trays::order_has_held_trays( $oid_t ), 'tray_gate_order_needs_trays' );
$assert( ! SOM_Workflow_Engine::can_mark_done( $cur_t, $step_t ), 'trays_block_mark_done' );
$ub_t = SOM_Workflow_Engine::unblock_step( $oid_t, $item_t, (int) $admin_user->ID );
$assert( true === $ub_t, 'unblock_trays_gate' );
$cur_t = SOM_Item_Make::current_item_progress( $item_t );
$assert( SOM_Workflow_Engine::can_mark_done( $cur_t, $step_t ), 'can_mark_after_trays_unblock' );
$assert( false !== strpos( (string) $cur_t->manual_unblock_gates, 'trays' ), 'gates_trays' );
$assert( ! SOM_Trays::order_has_held_trays( $oid_t ), 'unblock_does_not_assign_trays' );

// --- Script / error unblock ---
$wpdb->update(
	$oisp_t,
	array(
		'status'               => 'waiting_script',
		'last_error'           => 'forced script wait',
		'manual_unblock_at'    => null,
		'manual_unblock_by'    => null,
		'manual_unblock_gates' => null,
		'updated_at'           => current_time( 'mysql', true ),
	),
	array( 'id' => (int) $cur_t->id ),
	array( '%s', '%s', '%s', '%s', '%s', '%s' ),
	array( '%d' )
);
$cur_t = SOM_Item_Make::current_item_progress( $item_t );
$assert( 'waiting_script' === (string) $cur_t->status, 'forced_waiting_script' );
$ub_script = SOM_Workflow_Engine::unblock_step( $oid_t, $item_t, (int) $admin_user->ID );
$assert( true === $ub_script, 'unblock_script' );
$cur_t = SOM_Item_Make::current_item_progress( $item_t );
$assert( 'in_progress' === (string) $cur_t->status && SOM_Workflow_Engine::can_mark_done( $cur_t, $step_t ), 'after_script_unblock' );

// --- Pack: ship / package / hold unblock ---
$wf_pack2 = SOM_Workflows::create(
	array(
		'name'       => 'UP8-S2 packship ' . $suffix,
		'kind'       => 'pack',
		'is_active'  => 1,
		'uses_trays' => 0,
	)
);
$wf_pack2_id = (int) $wf_pack2;
SOM_Workflows::save_steps(
	$wf_pack2_id,
	array(
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
		),
	)
);
SOM_Pack::set_default_template_id( $wf_pack2_id );

$wf_make2 = SOM_Workflows::create(
	array(
		'name'      => 'UP8-S2 make2 ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$wf_make2_id = (int) $wf_make2;
SOM_Workflows::save_steps(
	$wf_make2_id,
	array(
		array(
			'name'                    => 'Make only',
			'requires_manual_confirm' => 1,
		),
	)
);
$prod2 = SOM_Products::create(
	array(
		'name'                 => 'UP8-S2 ship prod ' . $suffix,
		'sku'                  => 'UP8S2S-' . $suffix,
		'workflow_template_id' => $wf_make2_id,
		'is_active'            => 1,
	)
);
$oid_s = (int) SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up8s2s-' . $suffix,
		'buyer_name'        => 'UP8-S2 Ship',
		'items'             => array(
			array(
				'product_id' => (int) $prod2,
				'quantity'   => 1,
				'unit_price' => 4,
			),
		),
	)
);
$ord_s  = SOM_Orders::get( $oid_s );
$item_s = (int) $ord_s->items[0]->id;
// Finish make so pack ship gates apply (not make_ready).
SOM_Workflow_Engine::unblock_step( $oid_s, $item_s, (int) $admin_user->ID );
SOM_Item_Make::mark_done( $oid_s, $item_s );

$ord_s = SOM_Orders::get( $oid_s );
$assert( SOM_Pack::order_is_make_ready( $ord_s ), 'make_ready_for_ship' );
SOM_Pack::set_hold( $oid_s, 'Waiting on ink', (int) $admin_user->ID );
SOM_Pack::set_shipping_package( $oid_s, 0 );
$ord_s = SOM_Orders::get( $oid_s );
$assert( SOM_Pack::is_held( $ord_s ), 'pack_held' );
$assert( empty( $ord_s->shipping_package_id ), 'no_package' );

$prog_s = null;
foreach ( SOM_Workflow_Engine::get_progress( $oid_s ) as $row ) {
	if ( (int) $row->workflow_step_id === (int) $ord_s->current_step_id ) {
		$prog_s = $row;
		break;
	}
}
$step_s = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d', (int) $ord_s->current_step_id ) );
$assert( $prog_s && $step_s && SOM_Shipments::is_ship_step( $step_s ), 'on_ship_step' );
$assert( ! SOM_Workflow_Engine::can_mark_done( $prog_s, $step_s ), 'ship_gates_block' );

$ub_ship = SOM_Workflow_Engine::unblock_step( $oid_s, 0, (int) $admin_user->ID );
$assert( true === $ub_ship, 'unblock_ship_gates' );
$ord_s = SOM_Orders::get( $oid_s );
$assert( SOM_Pack::is_held( $ord_s ), 'o8_hold_flag_left' );
$prog_s = null;
foreach ( SOM_Workflow_Engine::get_progress( $oid_s ) as $row ) {
	if ( (int) $row->workflow_step_id === (int) $ord_s->current_step_id ) {
		$prog_s = $row;
		break;
	}
}
$assert( SOM_Workflow_Engine::can_mark_done( $prog_s, $step_s ), 'can_mark_after_ship_unblock' );
$gates_s = (string) $prog_s->manual_unblock_gates;
$assert(
	false !== strpos( $gates_s, 'hold' )
	|| false !== strpos( $gates_s, 'package' )
	|| false !== strpos( $gates_s, 'ship' ),
	'ship_unblock_gates_recorded'
);

// --- Batch unblock removes member (O9) ---
$ship_group = SOM_Batch_Groups::get_by_key( 'shipping_label' );
$assert( $ship_group, 'ship_batch_group' );
$wf_batch = SOM_Workflows::create(
	array(
		'name'      => 'UP8-S2 batch pack ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$wf_batch_id = (int) $wf_batch;
SOM_Workflows::save_steps(
	$wf_batch_id,
	array(
		array(
			'name'           => 'Ship labels',
			'batch_group_id' => (int) $ship_group->id,
		),
	)
);
SOM_Pack::set_default_template_id( $wf_batch_id );

$wf_mb = SOM_Workflows::create(
	array(
		'name'      => 'UP8-S2 batch make ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$wf_mb_id = (int) $wf_mb;
SOM_Workflows::save_steps(
	$wf_mb_id,
	array(
		array(
			'name'                    => 'Make B',
			'requires_manual_confirm' => 1,
		),
	)
);
$prod_b = SOM_Products::create(
	array(
		'name'                 => 'UP8-S2 batch prod ' . $suffix,
		'sku'                  => 'UP8S2B-' . $suffix,
		'workflow_template_id' => $wf_mb_id,
		'is_active'            => 1,
	)
);
$oid_b = (int) SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up8s2b-' . $suffix,
		'buyer_name'        => 'UP8-S2 Batch',
		'items'             => array(
			array(
				'product_id' => (int) $prod_b,
				'quantity'   => 1,
				'unit_price' => 2,
			),
		),
	)
);
$ord_b  = SOM_Orders::get( $oid_b );
$item_b = (int) $ord_b->items[0]->id;
SOM_Workflow_Engine::unblock_step( $oid_b, $item_b, (int) $admin_user->ID );
SOM_Item_Make::mark_done( $oid_b, $item_b );

$ord_b = SOM_Orders::get( $oid_b );
$prog_b = null;
foreach ( SOM_Workflow_Engine::get_progress( $oid_b ) as $row ) {
	if ( (int) $row->workflow_step_id === (int) $ord_b->current_step_id ) {
		$prog_b = $row;
		break;
	}
}
$assert( $prog_b && 'waiting_batch' === (string) $prog_b->status, 'waiting_batch_status' );
$batch = SOM_Batches::find_for_order( $oid_b );
$assert( $batch && in_array( (string) $batch->status, array( 'collecting', 'ready' ), true ), 'in_open_batch' );
$batch_id = (int) $batch->id;
$count_before = count( SOM_Batches::get_items( $batch_id ) );

$ub_batch = SOM_Workflow_Engine::unblock_step( $oid_b, 0, (int) $admin_user->ID );
$assert( true === $ub_batch, 'unblock_batch' );
$count_after = count( SOM_Batches::get_items( $batch_id ) );
$assert( $count_after === $count_before - 1, 'o9_removed_from_batch' );
$prog_b = null;
foreach ( SOM_Workflow_Engine::get_progress( $oid_b ) as $row ) {
	if ( (int) $row->workflow_step_id === (int) $ord_b->current_step_id ) {
		$prog_b = $row;
		break;
	}
}
$step_b = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d', (int) $ord_b->current_step_id ) );
$assert( $prog_b && 'in_progress' === (string) $prog_b->status && SOM_Workflow_Engine::can_mark_done( $prog_b, $step_b ), 'can_mark_after_batch_unblock' );

// --- Auto-release on order complete ---
$oid_rel = (int) SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up8s2r-' . $suffix,
		'buyer_name'        => 'UP8-S2 Release',
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
				'unit_price' => 5,
			),
		),
	)
);
SOM_Trays::assign( $oid_rel, array( 2, 4 ) );
$assert( array( 2, 4 ) === SOM_Trays::held_numbers_for_order( $oid_rel ), 'pre_complete_trays' );
$wpdb->update(
	SOM_DB::table( 'orders' ),
	array(
		'is_complete'     => 1,
		'current_step_id' => null,
		'updated_at'      => current_time( 'mysql', true ),
	),
	array( 'id' => $oid_rel ),
	array( '%d', '%s', '%s' ),
	array( '%d' )
);
do_action( SOM_Production::HOOK_ORDER_COMPLETED, $oid_rel );
$assert( array() === SOM_Trays::held_numbers_for_order( $oid_rel ), 'auto_release_on_complete' );
$released = $wpdb->get_results(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'tray_assignments' ) . ' WHERE order_id = %d AND status = %s',
		$oid_rel,
		SOM_Trays::STATUS_RELEASED
	)
);
$reasons_ok = ! empty( $released );
foreach ( (array) $released as $r ) {
	if ( SOM_Trays::REASON_ORDER_COMPLETE !== (string) $r->release_reason ) {
		$reasons_ok = false;
	}
}
$assert( $reasons_ok, 'release_reason_order_complete' );

// Unblock does not auto Mark done — already verified: unblock then separate mark_done.
$assert( true, 'unblock_not_auto_mark_done' );

// Restore.
if ( $prev_pack > 0 ) {
	SOM_Pack::set_default_template_id( (int) $prev_pack );
}
if ( $prev_max >= SOM_Trays::MIN_MAX && $prev_max <= SOM_Trays::MAX_MAX ) {
	SOM_Trays::set_max( $prev_max );
} else {
	SOM_Trays::set_max( SOM_Trays::DEFAULT_MAX );
}

$out( 'summary', $fail ? "FAILED ($fail)" : 'OK' );
exit( $fail ? 1 : 0 );
