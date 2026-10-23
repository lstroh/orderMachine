<?php
/**
 * UP14-S11 smoke: a finished pack leaves the Pack board and stays on Post-ship.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s11-smoke.php
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

$out( 'plugin', SOM_VERSION );

$board_has = static function ( $board, $order_id ) {
	if ( empty( $board['orders'] ) || ! is_array( $board['orders'] ) ) {
		return false;
	}
	foreach ( $board['orders'] as $row ) {
		if ( (int) $row->id === (int) $order_id ) {
			return true;
		}
	}
	return false;
};

$pack_progress_count = static function ( $order_id, $open_only ) {
	global $wpdb;
	$status_sql = $open_only ? " AND p.status <> 'done'" : '';
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'order_step_progress' ) . ' p
			INNER JOIN ' . SOM_DB::table( 'workflow_steps' ) . ' s ON s.id = p.workflow_step_id
			INNER JOIN ' . SOM_DB::table( 'workflow_templates' ) . " t ON t.id = s.workflow_template_id
			WHERE p.order_id = %d AND t.kind = 'pack'{$status_sql}",
			(int) $order_id
		)
	);
};

$item_progress_count = static function ( $order_id ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'order_item_step_progress' ) . ' WHERE order_id = %d',
			(int) $order_id
		)
	);
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$prior_pack_default = SOM_Pack::default_template_id();
$restore_pack       = static function () use ( $prior_pack_default ) {
	SOM_Pack::set_default_template_id( $prior_pack_default );
};

$make = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => 'UP14-S11 make ' . $suffix,
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

$pack = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => 'UP14-S11 pack ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $pack ) && (int) $pack > 0, 'create_pack' );
$pack_id = is_wp_error( $pack ) ? 0 : (int) $pack;

$post = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => 'UP14-S11 post-ship ' . $suffix,
		'kind'      => 'post_ship',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Update buyer',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Confirm package arrived',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => SOM_Step_Confirmations::KIND_PACKAGE_ARRIVED,
		),
	)
);
$assert( ! is_wp_error( $post ) && (int) $post > 0, 'create_post_ship' );
$post_id = is_wp_error( $post ) ? 0 : (int) $post;

$product = $make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => 'UP14-S11 sticker ' . $suffix,
			'workflow_template_id' => $make_id,
			'is_internal'          => 0,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $product ) && (int) $product > 0, 'create_product' );
$product_id = is_wp_error( $product ) ? 0 : (int) $product;

$default_set = $pack_id > 0 ? SOM_Pack::set_default_template_id( $pack_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $default_set, 'set_pack_default' );

$create_order = static function ( $external_id, $buyer ) use ( $product_id ) {
	if ( $product_id < 1 ) {
		return new WP_Error( 'skipped', 'skipped' );
	}
	return SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => $external_id,
			'buyer_name'        => $buyer,
			'items'             => array(
				array(
					'product_id' => $product_id,
					'quantity'   => 1,
					'unit_price' => 8,
				),
			),
		)
	);
};

$pending_external = 'up14s11-open-' . $suffix;
$pending_id       = $create_order( $pending_external, 'UP14-S11 open' );
$assert( ! is_wp_error( $pending_id ) && (int) $pending_id > 0, 'create_open_pack_order' );
$pending_id = is_wp_error( $pending_id ) ? 0 : (int) $pending_id;

if ( $pending_id > 0 ) {
	$pending = SOM_Orders::get( $pending_id );
	$assert( $pending && (int) $pending->pack_workflow_template_id === $pack_id, 'open_order_pack_bound' );
	$assert( $pack_progress_count( $pending_id, true ) > 0, 'open_order_has_open_pack_step' );

	$pending_board = SOM_Pack::query_board( array( 's' => $pending_external ) );
	$assert( $board_has( $pending_board, $pending_id ), 'open_pack_step_stays_on_pack_board' );

	$pending_card = null;
	foreach ( $pending_board['orders'] as $row ) {
		if ( (int) $row->id === $pending_id ) {
			$pending_card = $row;
			break;
		}
	}
	$assert( $pending_card && SOM_Pack::BOARD_WAITING_MAKE === (string) $pending_card->column_key, 'open_pack_step_waiting_for_make' );

	$held = SOM_Pack::set_hold( $pending_id, 'Waiting on buyer' );
	$assert( true === $held, 'hold_open_pack_order' );
	$held_board = SOM_Pack::query_board( array( 's' => $pending_external ) );
	$assert( $board_has( $held_board, $pending_id ), 'held_open_pack_step_stays_on_pack_board' );
	$held_card = null;
	foreach ( $held_board['orders'] as $row ) {
		if ( (int) $row->id === $pending_id ) {
			$held_card = $row;
			break;
		}
	}
	$assert( $held_card && SOM_Pack::BOARD_HELD === (string) $held_card->column_key, 'open_pack_step_held_column' );
	SOM_Pack::set_hold( $pending_id, '' );
}

$done_external = 'up14s11-done-' . $suffix;
$done_id       = $create_order( $done_external, 'UP14-S11 done' );
$assert( ! is_wp_error( $done_id ) && (int) $done_id > 0, 'create_finished_pack_order' );
$done_id = is_wp_error( $done_id ) ? 0 : (int) $done_id;

if ( $done_id > 0 && $pack_progress_count( $done_id, true ) > 0 ) {
	$now = current_time( 'mysql', true );
	$marked = $wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . SOM_DB::table( 'order_step_progress' ) . ' p
			INNER JOIN ' . SOM_DB::table( 'workflow_steps' ) . ' s ON s.id = p.workflow_step_id
			INNER JOIN ' . SOM_DB::table( 'workflow_templates' ) . " t ON t.id = s.workflow_template_id
			SET p.status = 'done', p.completed_at = %s
			WHERE p.order_id = %d AND t.kind = 'pack'",
			$now,
			$done_id
		)
	);
	$assert( false !== $marked && $marked > 0, 'mark_pack_steps_done' );
	$assert( $pack_progress_count( $done_id, false ) > 0, 'finished_pack_progress_remains' );
	$assert( 0 === $pack_progress_count( $done_id, true ), 'finished_pack_has_no_open_step' );

	$started = $post_id > 0 ? SOM_Post_Ship::start( $done_id, $post_id ) : new WP_Error( 'skipped', 'skipped' );
	$assert( true === $started, 'start_post_ship' );

	$done_order = SOM_Orders::get( $done_id );
	$assert( $done_order && empty( $done_order->is_complete ), 'finished_pack_order_still_open' );
	$assert( $done_order && (int) $done_order->post_ship_workflow_template_id === $post_id, 'finished_pack_post_ship_bound' );

	$done_pack_board = SOM_Pack::query_board( array( 's' => $done_external ) );
	$assert( ! $board_has( $done_pack_board, $done_id ), 'finished_pack_leaves_pack_board' );

	$done_post_board = SOM_Post_Ship::query_board(
		array(
			'scope' => SOM_Post_Ship::SCOPE_ACTIVE,
			's'     => $done_external,
		)
	);
	$assert( $board_has( $done_post_board, $done_id ), 'finished_pack_stays_on_active_post_ship_board' );
} else {
	$assert( false, 'finished_pack_fixture' );
}

$unbound_set = SOM_Pack::set_default_template_id( 0 );
$assert( true === $unbound_set, 'clear_pack_default' );

$unbound_external = 'up14s11-none-' . $suffix;
$unbound_id       = $create_order( $unbound_external, 'UP14-S11 unbound' );
$assert( ! is_wp_error( $unbound_id ) && (int) $unbound_id > 0, 'create_unbound_order' );
$unbound_id = is_wp_error( $unbound_id ) ? 0 : (int) $unbound_id;

if ( $unbound_id > 0 ) {
	$unbound = SOM_Orders::get( $unbound_id );
	$assert( $unbound && empty( $unbound->pack_workflow_template_id ), 'unbound_has_no_pack_template' );
	$assert( 0 === $pack_progress_count( $unbound_id, false ), 'unbound_has_no_pack_progress' );
	$assert( $item_progress_count( $unbound_id ) > 0, 'unbound_has_make_progress' );

	$unbound_board = SOM_Pack::query_board( array( 's' => $unbound_external ) );
	$assert( $board_has( $unbound_board, $unbound_id ), 'no_pack_progress_stays_on_pack_board' );
} else {
	$assert( false, 'unbound_fixture' );
}

$restore_pack();
$assert( SOM_Pack::default_template_id() === $prior_pack_default, 'restore_pack_default' );

if ( $fail > 0 ) {
	echo "FAIL — UP14-S11 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S11 smoke\n";
