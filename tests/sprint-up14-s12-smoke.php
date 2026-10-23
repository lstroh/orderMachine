<?php
/**
 * UP14-S12 smoke: Release anyway frees the last required tray. Release selected still keeps one.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s12-smoke.php
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
$assert( 'manual_override' === SOM_Trays::REASON_MANUAL_OVERRIDE, 'reason_constant' );

require_once ABSPATH . 'wp-admin/includes/template.php';

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin || ! user_can( $admin, 'manage_options' ) ) {
	$created = wp_insert_user(
		array(
			'user_login' => 'up14s12admin',
			'user_pass'  => wp_generate_password( 24, true, true ),
			'user_email' => 'up14s12admin@example.com',
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
$admin_id   = $admin ? (int) $admin->ID : 0;
$admin_name = $admin_id > 0 ? SOM_Order_Notes::author_label( $admin_id ) : '';

$detail_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$menu_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$selected_at = strpos( $detail_src, 'Release selected' );
$anyway_at   = strpos( $detail_src, 'Release anyway' );
$assert( false !== $selected_at && false !== $anyway_at && $selected_at < $anyway_at, 'source_release_anyway_beside_selected' );
$assert( false !== strpos( $detail_src, 'som_release_trays_anyway' ), 'source_release_anyway_name' );
$assert( false !== strpos( $detail_src, '$trays_required && empty( $order->is_complete )' ), 'source_shows_when_required_and_open' );
$assert( false !== strpos( $menu_src, 'SOM_Trays::REASON_MANUAL_OVERRIDE' ), 'source_handler_uses_override' );
$assert( false !== strpos( $menu_src, 'Select at least one tray to release.' ), 'source_still_requires_a_checked_tray' );

$render_detail = static function ( $order_id ) {
	$_GET['order_id'] = (int) $order_id;
	ob_start();
	SOM_Admin_Menu::render_orders();
	return (string) ob_get_clean();
};

$trays_html = static function ( $html ) {
	$trays_at = strpos( $html, 'id="som-trays"' );
	$make_at  = strpos( $html, 'class="som-panel som-panel-make"' );
	if ( false === $trays_at || false === $make_at || $trays_at >= $make_at ) {
		return '';
	}
	return substr( $html, $trays_at, $make_at - $trays_at );
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

$step_for = static function ( $progress ) {
	global $wpdb;
	if ( ! $progress || empty( $progress->workflow_step_id ) ) {
		return null;
	}
	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d LIMIT 1',
			(int) $progress->workflow_step_id
		)
	);
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$wf_open = SOM_Workflows::create(
	array(
		'name'       => 'UP14-S12 open ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 1,
	)
);
$assert( ! is_wp_error( $wf_open ) && (int) $wf_open > 0, 'create_open_workflow' );
$wf_open_id = is_wp_error( $wf_open ) ? 0 : (int) $wf_open;
if ( $wf_open_id > 0 ) {
	$saved_open = SOM_Workflows::save_steps(
		$wf_open_id,
		array(
			array(
				'name'                    => 'Do work',
				'requires_manual_confirm' => 1,
			),
		)
	);
	$assert( true === $saved_open, 'save_open_steps' );
}

$wf_timer = SOM_Workflows::create(
	array(
		'name'       => 'UP14-S12 timer ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 1,
	)
);
$assert( ! is_wp_error( $wf_timer ) && (int) $wf_timer > 0, 'create_timer_workflow' );
$wf_timer_id = is_wp_error( $wf_timer ) ? 0 : (int) $wf_timer;
if ( $wf_timer_id > 0 ) {
	$saved_timer = SOM_Workflows::save_steps(
		$wf_timer_id,
		array(
			array(
				'name'                    => 'Wait',
				'requires_manual_confirm' => 1,
				'timer_seconds'           => 3600,
			),
		)
	);
	$assert( true === $saved_timer, 'save_timer_steps' );
}

$wf_plain = SOM_Workflows::create(
	array(
		'name'       => 'UP14-S12 plain ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 0,
	)
);
$assert( ! is_wp_error( $wf_plain ) && (int) $wf_plain > 0, 'create_plain_workflow' );
$wf_plain_id = is_wp_error( $wf_plain ) ? 0 : (int) $wf_plain;
if ( $wf_plain_id > 0 ) {
	$saved_plain = SOM_Workflows::save_steps(
		$wf_plain_id,
		array(
			array(
				'name'                    => 'Cut',
				'requires_manual_confirm' => 1,
			),
		)
	);
	$assert( true === $saved_plain, 'save_plain_steps' );
}

$product_for = static function ( $name, $workflow_id ) use ( $suffix ) {
	if ( $workflow_id < 1 ) {
		return 0;
	}
	$created = SOM_Products::create(
		array(
			'name'                 => $name . ' ' . $suffix,
			'sku'                  => $name . '-' . $suffix,
			'workflow_template_id' => $workflow_id,
			'is_active'            => 1,
		)
	);
	return is_wp_error( $created ) ? 0 : (int) $created;
};

$open_product  = $product_for( 'UP14S12A', $wf_open_id );
$timer_product = $product_for( 'UP14S12B', $wf_timer_id );
$plain_product = $product_for( 'UP14S12C', $wf_plain_id );
$assert( $open_product > 0, 'create_open_product' );
$assert( $timer_product > 0, 'create_timer_product' );
$assert( $plain_product > 0, 'create_plain_product' );

$make_order = static function ( $label, $product_id ) use ( $suffix ) {
	if ( $product_id < 1 ) {
		return 0;
	}
	$order_id = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => 'up14s12-' . $label . '-' . $suffix,
			'buyer_name'        => 'UP14-S12 ' . $label,
			'items'             => array(
				array(
					'product_id' => $product_id,
					'quantity'   => 1,
					'unit_price' => 4,
				),
			),
		)
	);
	return is_wp_error( $order_id ) ? 0 : (int) $order_id;
};

$open_id  = $make_order( 'open', $open_product );
$timer_id = $make_order( 'timer', $timer_product );
$plain_id = $make_order( 'plain', $plain_product );
$assert( $open_id > 0, 'create_open_order' );
$assert( $timer_id > 0, 'create_timer_order' );
$assert( $plain_id > 0, 'create_plain_order' );

$plain = $plain_id > 0 ? $clear_tray_pack( $plain_id ) : null;
$assert( $plain && ! SOM_Trays::order_requires_trays( $plain ), 'plain_order_does_not_require_trays' );

$open = $open_id > 0 ? SOM_Orders::get( $open_id ) : null;
$assert( $open && empty( $open->is_complete ) && SOM_Trays::order_requires_trays( $open ), 'open_order_requires_trays' );

$free = SOM_Trays::free_tray_numbers();
$assert( count( $free ) >= 2, 'two_free_trays' );
$tray_a = ! empty( $free[0] ) ? (int) $free[0] : 0;
$tray_b = ! empty( $free[1] ) ? (int) $free[1] : 0;

$plain_assign = ( $plain_id > 0 && $tray_a > 0 ) ? SOM_Trays::assign( $plain_id, array( $tray_a ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $plain_assign, 'plain_assign' );
$plain_html = $plain_id > 0 ? $trays_html( $render_detail( $plain_id ) ) : '';
$assert( false !== strpos( $plain_html, 'Release selected' ), 'plain_shows_release_selected' );
$assert( false === strpos( $plain_html, 'Release anyway' ), 'plain_hides_release_anyway' );
$plain_release = ( $plain_id > 0 && $tray_a > 0 )
	? SOM_Trays::release( $plain_id, array( $tray_a ), SOM_Trays::REASON_MANUAL, $admin_id )
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $plain_release, 'plain_release_selected_frees_last' );

$open_assign = ( $open_id > 0 && $tray_a > 0 ) ? SOM_Trays::assign( $open_id, array( $tray_a ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $open_assign, 'open_assign_only_tray' );
$assert( $open_id > 0 && array( $tray_a ) === SOM_Trays::held_numbers_for_order( $open_id ), 'open_holds_one_tray' );

$timer_assign = ( $timer_id > 0 && $tray_b > 0 ) ? SOM_Trays::assign( $timer_id, array( $tray_b ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $timer_assign, 'timer_assign_tray' );
$timer_held_before = $timer_id > 0 ? SOM_Trays::held_numbers_for_order( $timer_id ) : array();
$assert( array( $tray_b ) === $timer_held_before, 'timer_holds_one_tray' );

$open_item = ( $open && ! empty( $open->items[0]->id ) ) ? (int) $open->items[0]->id : 0;
$open_progress = $open_item > 0 ? SOM_Item_Make::current_item_progress( $open_item ) : null;
$open_step     = $step_for( $open_progress );
$assert( $open_progress && 'in_progress' === (string) $open_progress->status, 'open_step_in_progress' );
$assert( $open_progress && $open_step && SOM_Workflow_Engine::can_mark_done( $open_progress, $open_step ), 'mark_done_allowed_while_tray_held' );

$open_html = $open_id > 0 ? $trays_html( $render_detail( $open_id ) ) : '';
$assert( false !== strpos( $open_html, 'Release selected' ) && false !== strpos( $open_html, 'value="Release anyway"' ), 'open_shows_both_buttons' );
$assert( false !== strpos( $open_html, 'name="som_release_trays_anyway"' ), 'open_anyway_button_name' );

global $wpdb;
if ( $open_id > 0 ) {
	$wpdb->update(
		SOM_DB::table( 'orders' ),
		array( 'is_complete' => 1 ),
		array( 'id' => $open_id ),
		array( '%d' ),
		array( '%d' )
	);
}
$complete_html = $open_id > 0 ? $trays_html( $render_detail( $open_id ) ) : '';
$assert( false !== strpos( $complete_html, 'Release selected' ), 'complete_still_shows_release_selected' );
$assert( false === strpos( $complete_html, 'Release anyway' ), 'complete_hides_release_anyway' );
if ( $open_id > 0 ) {
	$wpdb->update(
		SOM_DB::table( 'orders' ),
		array( 'is_complete' => 0 ),
		array( 'id' => $open_id ),
		array( '%d' ),
		array( '%d' )
	);
}

$keep = ( $open_id > 0 && $tray_a > 0 )
	? SOM_Trays::release( $open_id, array( $tray_a ), SOM_Trays::REASON_MANUAL, $admin_id )
	: new WP_Error( 'skipped', 'skipped' );
$assert( is_wp_error( $keep ) && 'som_tray_keep_one' === $keep->get_error_code(), 'release_selected_keep_one' );
$assert( $open_id > 0 && array( $tray_a ) === SOM_Trays::held_numbers_for_order( $open_id ), 'tray_still_held_after_keep_one' );

$progress_before = $open_item > 0 ? SOM_Item_Make::current_item_progress( $open_item ) : null;
$status_before   = $progress_before ? (string) $progress_before->status : '';
$progress_id     = $progress_before ? (int) $progress_before->id : 0;

$override = ( $open_id > 0 && $tray_a > 0 )
	? SOM_Trays::release( $open_id, array( $tray_a ), SOM_Trays::REASON_MANUAL_OVERRIDE, $admin_id )
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $override, 'release_anyway_succeeds' );
$assert( $open_id > 0 && array() === SOM_Trays::held_numbers_for_order( $open_id ), 'tray_is_free' );
$assert( $tray_a > 0 && in_array( $tray_a, SOM_Trays::free_tray_numbers(), true ), 'tray_back_in_pool' );

$released_row = ( $open_id > 0 && $tray_a > 0 )
	? $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . SOM_DB::table( 'tray_assignments' ) . ' WHERE order_id = %d AND tray_number = %d AND status = %s ORDER BY id DESC LIMIT 1',
			$open_id,
			$tray_a,
			SOM_Trays::STATUS_RELEASED
		)
	)
	: null;
$assert( $released_row && SOM_Trays::REASON_MANUAL_OVERRIDE === (string) $released_row->release_reason, 'release_reason_manual_override' );
$assert( $released_row && (int) $released_row->released_by_user_id === $admin_id, 'released_by_recorded' );

$note_ok = false;
if ( $open_id > 0 ) {
	foreach ( SOM_Order_Notes::list_for_order( $open_id ) as $note ) {
		$body = (string) $note->body;
		if ( false === strpos( $body, 'tray requirement was bypassed' ) ) {
			continue;
		}
		if ( false === strpos( $body, 'does not complete a step' ) ) {
			continue;
		}
		if ( '' !== $admin_name && false === strpos( $body, $admin_name ) ) {
			continue;
		}
		if ( false === strpos( $body, (string) $tray_a ) ) {
			continue;
		}
		if ( (int) $note->user_id !== $admin_id ) {
			continue;
		}
		$note_ok = true;
		break;
	}
}
$assert( $note_ok, 'override_note_stored' );

$progress_after = $open_item > 0 ? SOM_Item_Make::current_item_progress( $open_item ) : null;
$assert(
	$progress_after
		&& (int) $progress_after->id === $progress_id
		&& (string) $progress_after->status === $status_before
		&& 'done' !== (string) $progress_after->status
		&& empty( $progress_after->manual_unblock_at ),
	'note_does_not_complete_step'
);

$open_after = $open_id > 0 ? SOM_Orders::get( $open_id ) : null;
$step_after = $step_for( $progress_after );
$assert( $open_after && ! SOM_Trays::trays_gate_ok( $open_after ), 'gate_blocked_without_tray' );
$assert( $progress_after && $step_after && ! SOM_Workflow_Engine::can_mark_done( $progress_after, $step_after ), 'mark_done_still_blocked' );
$blocked = ( $open_id > 0 && $open_item > 0 ) ? SOM_Item_Make::mark_done( $open_id, $open_item ) : new WP_Error( 'skipped', 'skipped' );
$assert( is_wp_error( $blocked ) && 'som_trays_required' === $blocked->get_error_code(), 'mark_done_blocked_for_lack_of_tray' );
$progress_still = $open_item > 0 ? SOM_Item_Make::current_item_progress( $open_item ) : null;
$assert( $progress_still && 'done' !== (string) $progress_still->status, 'mark_done_did_not_finish_step' );

$timer_order    = $timer_id > 0 ? SOM_Orders::get( $timer_id ) : null;
$timer_item     = ( $timer_order && ! empty( $timer_order->items[0]->id ) ) ? (int) $timer_order->items[0]->id : 0;
$timer_progress = $timer_item > 0 ? SOM_Item_Make::current_item_progress( $timer_item ) : null;
$assert( $timer_progress && 'waiting_timer' === (string) $timer_progress->status, 'timer_step_waiting' );
$unblocked = ( $timer_id > 0 && $timer_item > 0 )
	? SOM_Workflow_Engine::unblock_step( $timer_id, $timer_item, $admin_id )
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $unblocked, 'unblock_step_runs' );
$assert( $timer_held_before === SOM_Trays::held_numbers_for_order( $timer_id ), 'unblock_does_not_change_held_trays' );
$timer_row = ( $timer_id > 0 && $tray_b > 0 )
	? $wpdb->get_row(
		$wpdb->prepare(
			'SELECT status FROM ' . SOM_DB::table( 'tray_assignments' ) . ' WHERE order_id = %d AND tray_number = %d ORDER BY id DESC LIMIT 1',
			$timer_id,
			$tray_b
		)
	)
	: null;
$assert( $timer_row && SOM_Trays::STATUS_HELD === (string) $timer_row->status, 'unblock_leaves_tray_held' );

$other_assign = ( $timer_id > 0 && $tray_a > 0 ) ? SOM_Trays::assign( $timer_id, array( $tray_a ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $other_assign, 'freed_tray_assigned_to_another_order' );
$both = $timer_id > 0 ? SOM_Trays::held_numbers_for_order( $timer_id ) : array();
$expect_both = array( $tray_a, $tray_b );
sort( $expect_both, SORT_NUMERIC );
$assert( $expect_both === $both, 'other_order_holds_both' );
$give_back = ( $timer_id > 0 && $tray_a > 0 )
	? SOM_Trays::release( $timer_id, array( $tray_a ), SOM_Trays::REASON_MANUAL, $admin_id )
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $give_back, 'other_order_can_release_the_extra_tray' );
$again = ( $open_id > 0 && $tray_a > 0 ) ? SOM_Trays::assign( $open_id, array( $tray_a ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $again, 'original_order_can_hold_a_tray_again' );
$open_restored = $open_id > 0 ? SOM_Orders::get( $open_id ) : null;
$progress_restored = $open_item > 0 ? SOM_Item_Make::current_item_progress( $open_item ) : null;
$step_restored     = $step_for( $progress_restored );
$assert( $open_restored && SOM_Trays::trays_gate_ok( $open_restored ), 'gate_ok_after_reassign' );
$assert( $progress_restored && $step_restored && SOM_Workflow_Engine::can_mark_done( $progress_restored, $step_restored ), 'mark_done_allowed_after_reassign' );

if ( $fail > 0 ) {
	echo "FAIL — UP14-S12 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S12 smoke\n";
