<?php
/**
 * UP5-S1 smoke: order notes schema + append + empty reject; not in REST payload.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up5-s1-smoke.php
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
$assert( version_compare( SOM_VERSION, '0.28.0', '>=' ), 'SOM_VERSION_gte_0.28.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.13.0', '>=' ), 'db_gte_1.13.0' );

$notes_t = SOM_DB::table( 'order_notes' );
$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $notes_t ) );
$assert( $exists === $notes_t, 'table_order_notes' );

$product_id = (int) $wpdb->get_var(
	'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_active = 1 ORDER BY id ASC LIMIT 1'
);
$assert( $product_id > 0, 'has_product' );

$external_id = 'up5s1-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
$order_id    = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $external_id,
		'buyer_name'        => 'UP5-S1 Tester',
		'shipping_address'  => array(
			'line1'    => '1 Test Street',
			'city'     => 'London',
			'postcode' => 'E1 1AA',
			'country'  => 'GB',
		),
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
				'unit_price' => 10,
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$empty = SOM_Order_Notes::add( $order_id, '   ' );
$assert( is_wp_error( $empty ) && 'som_note_empty' === $empty->get_error_code(), 'rejects_empty' );

$too_long = SOM_Order_Notes::add( $order_id, str_repeat( 'a', SOM_Order_Notes::MAX_LENGTH + 1 ) );
$assert( is_wp_error( $too_long ) && 'som_note_length' === $too_long->get_error_code(), 'rejects_too_long' );

$user_id = get_current_user_id();
if ( $user_id < 1 ) {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	$user_id = ! empty( $admins[0] ) ? (int) $admins[0] : 0;
}
$assert( $user_id > 0, 'has_user' );

$note_id = SOM_Order_Notes::add( $order_id, "First note\nline two", $user_id );
$assert( ! is_wp_error( $note_id ) && (int) $note_id > 0, 'add_note_1' );

$note_id_2 = SOM_Order_Notes::add( $order_id, 'Second note', $user_id );
$assert( ! is_wp_error( $note_id_2 ), 'add_note_2' );

$list = SOM_Order_Notes::list_for_order( $order_id );
$assert( 2 === count( $list ), 'list_count_2' );
$assert( isset( $list[0]->body ) && 0 === strpos( (string) $list[0]->body, 'First note' ), 'oldest_first' );
$assert( isset( $list[1]->body ) && 'Second note' === (string) $list[1]->body, 'newest_last' );
$assert( ! empty( $list[0]->author_name ), 'has_author_label' );

// REST order payload must not include notes (whitelist check via reflection-free path).
$order = SOM_Orders::get( $order_id );
$assert( $order && ! isset( $order->notes ) && ! isset( $order->order_notes ), 'orders_get_no_notes_prop' );

if ( class_exists( 'SOM_REST_API' ) ) {
	$ref = new ReflectionClass( 'SOM_REST_API' );
	if ( $ref->hasMethod( 'order_to_rest' ) ) {
		$method = $ref->getMethod( 'order_to_rest' );
		$method->setAccessible( true );
		$payload = $method->invoke( null, $order );
		$assert( is_array( $payload ) && ! array_key_exists( 'notes', $payload ), 'rest_payload_no_notes' );
	} else {
		$assert( false, 'rest_payload_no_notes' );
	}
} else {
	$assert( false, 'rest_payload_no_notes' );
}

if ( class_exists( 'SOM_Abilities' ) ) {
	$detail = SOM_Abilities::get_order_detail( array( 'order_id' => $order_id ) );
	$assert( ! is_wp_error( $detail ) && is_array( $detail ) && ! array_key_exists( 'notes', $detail ), 'mcp_detail_no_notes' );
} else {
	$assert( false, 'mcp_detail_no_notes' );
}

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'OK' );
$out( 'order_id', (string) $order_id );
$out( 'note_ids', wp_json_encode( array( (int) $note_id, (int) $note_id_2 ) ) );
