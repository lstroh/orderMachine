<?php
/**
 * Sprint U2 smoke: suppliers CRUD, PO create/receive/partial/close, preferred supplier.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u2-smoke.php
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

$out( 'plugin', SOM_VERSION );
$assert( SOM_VERSION === '0.13.0', 'SOM_VERSION_0.13.0' );

$supplier_id = SOM_Suppliers::create(
	array(
		'name'    => 'U2 Smoke Supplier',
		'website' => 'https://example.com/u2',
	)
);
$assert( ! is_wp_error( $supplier_id ), 'supplier_create' );
$supplier_id = (int) $supplier_id;

$mat_a = SOM_Materials::create(
	array(
		'name'                  => 'U2 Vinyl Smoke',
		'unit'                  => 'sheet',
		'preferred_supplier_id' => $supplier_id,
		'is_active'             => 1,
	)
);
$assert( ! is_wp_error( $mat_a ), 'material_a_create' );
$mat_a = (int) $mat_a;

$mat_b = SOM_Materials::create(
	array(
		'name'      => 'U2 Laminate Smoke',
		'unit'      => 'sheet',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $mat_b ), 'material_b_create' );
$mat_b = (int) $mat_b;

$mat_row = SOM_Materials::get( $mat_a );
$assert( $mat_row && (int) $mat_row->preferred_supplier_id === $supplier_id, 'preferred_supplier_set' );

$stock_before_a = (float) $mat_row->current_stock;
$stock_before_b = (float) SOM_Materials::get( $mat_b )->current_stock;

$po_id = SOM_Purchase_Orders::create(
	array(
		'supplier_id'   => $supplier_id,
		'order_date'    => current_time( 'Y-m-d' ),
		'shipping_cost' => 6,
		'other_cost'    => 1.5,
		'notes'         => 'U2 smoke PO',
		'items'         => array(
			array(
				'material_id'      => $mat_a,
				'quantity_ordered' => 50,
				'item_cost'        => 30,
			),
			array(
				'material_id'      => $mat_b,
				'quantity_ordered' => 20,
				'item_cost'        => 9,
			),
		),
	)
);
$assert( ! is_wp_error( $po_id ), 'po_create' );
$po_id = (int) $po_id;

$po = SOM_Purchase_Orders::get( $po_id );
$assert( $po && 'ordered' === $po->status, 'po_status_ordered' );
$assert( 2 === count( $po->items ), 'po_two_items' );

$item_a = $po->items[0];
$item_b = $po->items[1];

$recv = SOM_Purchase_Orders::receive(
	$po_id,
	array(
		(int) $item_a->id => 30,
		(int) $item_b->id => 0,
	)
);
$assert( ! is_wp_error( $recv ), 'po_partial_receive' );

$po = SOM_Purchase_Orders::get( $po_id );
$assert( $po && 'partially_received' === $po->status, 'po_status_partial' );
$assert( ! empty( $po->received_date ), 'po_received_date_set' );
$assert( ! empty( $po->lines_locked ), 'po_lines_locked' );
$assert( empty( $po->can_edit_lines ), 'po_cannot_edit_lines' );

$mat_a_after = SOM_Materials::get( $mat_a );
$assert( abs( ( (float) $mat_a_after->current_stock ) - ( $stock_before_a + 30 ) ) < 0.001, 'stock_a_plus_30' );

$log = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE material_id = %d AND reason = %s ORDER BY id DESC LIMIT 1',
		$mat_a,
		'purchase_received'
	)
);
$assert( $log && (int) $log->purchase_order_item_id === (int) $item_a->id, 'stock_log_poi_link' );
$assert( $log && null === $log->value_change, 'value_change_still_null_u2' );

$recv2 = SOM_Purchase_Orders::receive(
	$po_id,
	array(
		(int) $item_a->id => 25,
		(int) $item_b->id => 20,
	)
);
$assert( ! is_wp_error( $recv2 ), 'po_second_receive_over' );

$po = SOM_Purchase_Orders::get( $po_id );
$assert( $po && 'received' === $po->status, 'po_status_received_after_full' );

$item_a_fresh = null;
foreach ( $po->items as $line ) {
	if ( (int) $line->id === (int) $item_a->id ) {
		$item_a_fresh = $line;
	}
}
$assert( $item_a_fresh && (float) $item_a_fresh->quantity_received >= 55, 'over_receive_accumulated' );

// Second PO for mark-received / cancel paths.
$po2 = SOM_Purchase_Orders::create(
	array(
		'supplier_id'   => $supplier_id,
		'order_date'    => current_time( 'Y-m-d' ),
		'shipping_cost' => 0,
		'items'         => array(
			array(
				'material_id'      => $mat_b,
				'quantity_ordered' => 10,
				'item_cost'        => 5,
			),
		),
	)
);
$assert( ! is_wp_error( $po2 ), 'po2_create' );
$po2 = (int) $po2;
$po2_row = SOM_Purchase_Orders::get( $po2 );
$line2   = $po2_row->items[0];

SOM_Purchase_Orders::receive( $po2, array( (int) $line2->id => 4 ) );
$po2_row = SOM_Purchase_Orders::get( $po2 );
$assert( 'partially_received' === $po2_row->status, 'po2_partial' );

$closed = SOM_Purchase_Orders::mark_received( $po2 );
$assert( ! is_wp_error( $closed ), 'po2_mark_received' );
$po2_row = SOM_Purchase_Orders::get( $po2 );
$assert( 'received' === $po2_row->status, 'po2_closed_received' );

$po3 = SOM_Purchase_Orders::create(
	array(
		'supplier_id' => $supplier_id,
		'order_date'  => current_time( 'Y-m-d' ),
		'items'       => array(
			array(
				'material_id'      => $mat_a,
				'quantity_ordered' => 1,
				'item_cost'        => 1,
			),
		),
	)
);
$assert( ! is_wp_error( $po3 ), 'po3_create' );
$cancelled = SOM_Purchase_Orders::cancel( (int) $po3 );
$assert( ! is_wp_error( $cancelled ), 'po3_cancel' );
$assert( 'cancelled' === SOM_Purchase_Orders::get( (int) $po3 )->status, 'po3_cancelled_status' );

$out( 'summary', 0 === $fail ? 'PASS — Sprint U2 smoke' : "FAIL — {$fail} assertion(s)" );
exit( 0 === $fail ? 0 : 1 );
