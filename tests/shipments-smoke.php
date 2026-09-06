<?php
/**
 * Outbound shipments smoke: schema, CRUD, Ship gates, placeholders, tracking push (dummy).
 *
 * Run: wp eval-file wp-content/plugins/orderMachine/tests/shipments-smoke.php
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

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( class_exists( 'SOM_Shipments' ), 'class_SOM_Shipments' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.9.0', '>=' ), 'som_db_version_gte_1.9.0' );

$ship_t = SOM_DB::table( 'shipments' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$cols = $wpdb->get_results( "SHOW COLUMNS FROM {$ship_t}" );
$col_names = array();
if ( is_array( $cols ) ) {
	foreach ( $cols as $c ) {
		$col_names[] = (string) $c->Field;
	}
}
$assert( in_array( 'tracking_number', $col_names, true ), 'shipments_tracking_number' );
$assert( in_array( 'postage_paid', $col_names, true ), 'shipments_postage_paid' );
$assert( in_array( 'proof_attachment_id', $col_names, true ), 'shipments_proof_attachment_id' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$indexes = $wpdb->get_results( "SHOW INDEX FROM {$ship_t} WHERE Key_name = 'order_id'" );
$assert( ! empty( $indexes ), 'shipments_unique_order_id' );

$scopes = SOM_Channel_Etsy::scopes();
$assert( in_array( 'transactions_w', $scopes, true ), 'etsy_scope_transactions_w' );

// --- Workflow + product with a Ship step ---
$wf = SOM_Workflows::create(
	array(
		'name'      => 'Shipments smoke ' . wp_generate_password( 4, false, false ),
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf ), 'create_workflow' );
$wf_id = (int) $wf;

$saved = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'name'                    => 'Pack',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $saved ), 'save_steps' );
$steps = SOM_Workflows::get_steps( $wf_id );
$assert( 2 === count( $steps ), 'two_steps' );
$ship_step = $steps[1];
$assert( SOM_Shipments::is_ship_step( $ship_step ), 'is_ship_step_by_name' );

$product = SOM_Products::create(
	array(
		'name'                 => 'Shipments smoke product',
		'workflow_template_id' => $wf_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$ext_id   = 'ship-smoke-' . wp_generate_password( 6, false, false );
$order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'ebay',
		'external_order_id' => $ext_id,
		'buyer_name'        => 'Ship Smoke Buyer',
		'shipping_address'  => array(
			'line1'    => '1 Smoke Street',
			'city'     => 'London',
			'postcode' => 'E1 1AA',
			'country'  => 'GB',
		),
		'raw_payload'       => array(
			'tracking_number' => 'RAWONLY999',
			'lineItems'       => array(
				array(
					'lineItemId' => '1001',
					'quantity'   => 1,
				),
			),
		),
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
				'unit_price' => 5.0,
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ) && (int) $order_id > 0, 'create_order' );
$order_id = (int) $order_id;

$order = SOM_Orders::get( $order_id );
$assert( $order && (int) $order->current_step_id === (int) $steps[0]->id, 'order_on_pack' );

// Placeholder prefers raw until shipment has tracking.
$map = SOM_Script_Dispatch::placeholder_map( $order );
$assert( 'RAWONLY999' === (string) $map['tracking_number'], 'placeholder_from_raw' );

// Mark Pack done → Ship.
$done_pack = SOM_Workflow_Engine::mark_done( $order_id );
$assert( true === $done_pack, 'mark_pack_done' );

$order = SOM_Orders::get( $order_id );
$assert( $order && (int) $order->current_step_id === (int) $ship_step->id, 'order_on_ship' );

// Ship without shipment must fail.
$blocked = SOM_Workflow_Engine::mark_done( $order_id );
$assert( is_wp_error( $blocked ) && 'som_shipment_required' === $blocked->get_error_code(), 'ship_blocked_without_shipment' );

$assert( ! SOM_Shipments::has_required( $order_id ), 'has_required_false' );
$assert( 'missing' === SOM_Shipments::status_key( $order_id ), 'status_missing' );

// Validation: missing postage.
$bad = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'    => 'Royal Mail',
		'service'    => '2nd Class',
		'shipped_at' => current_time( 'Y-m-d' ),
		'postage_paid' => '',
	)
);
$assert( is_wp_error( $bad ), 'reject_empty_postage' );

// Untracked shipment.
$saved_ship = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'            => 'Royal Mail',
		'service'            => '2nd Class',
		'shipped_at'         => current_time( 'Y-m-d' ),
		'postage_paid'       => '1.25',
		'tracking_number'    => '',
		'click_and_drop_ref' => 'CD-TEST-1',
	)
);
$assert( ! is_wp_error( $saved_ship ), 'upsert_untracked' );
$assert( SOM_Shipments::has_required( $order_id ), 'has_required_true' );
$assert( 'untracked' === SOM_Shipments::status_key( $order_id ), 'status_untracked' );

// Unique 1:1 — upsert again updates same row.
$again = SOM_Shipments::upsert(
	$order_id,
	array(
		'carrier'      => 'Royal Mail',
		'service'      => '2nd Class',
		'shipped_at'   => current_time( 'Y-m-d' ),
		'postage_paid' => '1.50',
	)
);
$assert( ! is_wp_error( $again ), 'upsert_update' );
$row = SOM_Shipments::get_by_order( $order_id );
$assert( $row && abs( (float) $row->postage_paid - 1.5 ) < 0.001, 'postage_updated' );
$count = (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$ship_t} WHERE order_id = %d", $order_id )
);
$assert( 1 === $count, 'one_row_per_order' );

// Proof MIME reject via validate path (attach with empty file skipped — test mime list).
$assert( in_array( 'application/pdf', SOM_Shipments::proof_mimes(), true ), 'proof_mime_pdf' );

// Untracked push is no-op success; Ship completes.
$push_noop = SOM_Shipments::push_tracking( $order_id );
$assert( true === $push_noop, 'untracked_push_noop' );

$done_ship = SOM_Workflow_Engine::mark_done( $order_id );
$assert( true === $done_ship, 'ship_done_untracked' );

// New order for tracked + dummy push.
$ext2 = 'ship-smoke-trk-' . wp_generate_password( 6, false, false );
$oid2 = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'ebay',
		'external_order_id' => $ext2,
		'buyer_name'        => 'Tracked Buyer',
		'shipping_address'  => array(
			'line1'    => '2 Smoke Street',
			'city'     => 'London',
			'postcode' => 'E1 1BB',
			'country'  => 'GB',
		),
		'raw_payload'       => array(
			'lineItems' => array(
				array(
					'lineItemId' => '2002',
					'quantity'   => 1,
				),
			),
		),
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
			),
		),
	)
);
$assert( ! is_wp_error( $oid2 ), 'create_tracked_order' );
$oid2 = (int) $oid2;

// Ensure ebay channel is dummy for push.
$ebay_creds = SOM_Channels::get_credentials( 'ebay' );
if ( empty( $ebay_creds['dummy'] ) ) {
	SOM_Channels::save_credentials(
		'ebay',
		array_merge(
			is_array( $ebay_creds ) ? $ebay_creds : array(),
			array(
				'dummy'         => true,
				'access_token'  => 'dummy-token',
				'refresh_token' => 'dummy-refresh',
				'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		)
	);
}
$assert( SOM_Channels::is_dummy( 'ebay' ), 'ebay_dummy' );

SOM_Workflow_Engine::mark_done( $oid2 ); // Pack → Ship.
$trk = SOM_Shipments::upsert(
	$oid2,
	array(
		'carrier'         => 'Royal Mail',
		'service'         => 'Tracked 48',
		'shipped_at'      => current_time( 'Y-m-d' ),
		'postage_paid'    => '3.45',
		'tracking_number' => 'AB123456789GB',
	)
);
$assert( ! is_wp_error( $trk ), 'upsert_tracked' );
$assert( 'tracked' === SOM_Shipments::status_key( $oid2 ), 'status_tracked' );

$order2 = SOM_Orders::get( $oid2 );
$map2   = SOM_Script_Dispatch::placeholder_map( $order2 );
$assert( 'AB123456789GB' === (string) $map2['tracking_number'], 'placeholder_prefers_shipment' );

$pushed = SOM_Shipments::push_tracking( $oid2 );
$assert( true === $pushed, 'dummy_ebay_push' );
$row2 = SOM_Shipments::get_by_order( $oid2 );
$assert( $row2 && ! empty( $row2->tracking_pushed_at ), 'tracking_pushed_at_set' );
$assert( 'pushed' === SOM_Shipments::status_key( $oid2 ), 'status_pushed' );

$dummy_ful = get_option( 'som_dummy_ebay_fulfillments', array() );
$assert( is_array( $dummy_ful ) && isset( $dummy_ful[ $ext2 ] ), 'dummy_fulfillment_stored' );

// Etsy dummy push.
$etsy_creds = SOM_Channels::get_credentials( 'etsy' );
SOM_Channels::save_credentials(
	'etsy',
	array_merge(
		is_array( $etsy_creds ) ? $etsy_creds : array(),
		array(
			'dummy'         => true,
			'access_token'  => 'dummy-etsy',
			'refresh_token' => 'dummy-etsy-r',
			'shop_id'       => '12345',
			'expires_at'    => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
		)
	)
);
$ext3 = 'ship-smoke-etsy-' . wp_generate_password( 6, false, false );
$oid3 = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'etsy',
		'external_order_id' => $ext3,
		'buyer_name'        => 'Etsy Buyer',
		'shipping_address'  => array(
			'line1'    => '3 Smoke Street',
			'city'     => 'London',
			'postcode' => 'E1 1CC',
			'country'  => 'GB',
		),
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
			),
		),
	)
);
$assert( ! is_wp_error( $oid3 ), 'create_etsy_order' );
$oid3 = (int) $oid3;
SOM_Shipments::upsert(
	$oid3,
	array(
		'carrier'         => 'Royal Mail',
		'service'         => 'Tracked 24',
		'shipped_at'      => current_time( 'Y-m-d' ),
		'postage_paid'    => '4.00',
		'tracking_number' => 'CD987654321GB',
	)
);
$etsy_push = SOM_Shipments::push_tracking( $oid3 );
$assert( true === $etsy_push, 'dummy_etsy_push' );
$etsy_dummy = get_option( 'som_dummy_etsy_shipments', array() );
$assert( is_array( $etsy_dummy ) && isset( $etsy_dummy[ $ext3 ] ), 'dummy_etsy_shipment_stored' );

// shipping_label batch gate.
$ship_group = SOM_Batch_Groups::get_by_key( 'shipping_label' );
$assert( $ship_group, 'shipping_label_group' );

$wf_b = SOM_Workflows::create(
	array(
		'name'      => 'Ship batch smoke ' . wp_generate_password( 4, false, false ),
		'is_active' => 1,
	)
);
$wf_b = (int) $wf_b;
SOM_Workflows::save_steps(
	$wf_b,
	array(
		array(
			'name'           => 'Ship',
			'batch_group_id' => (int) $ship_group->id,
		),
	)
);
$b_steps = SOM_Workflows::get_steps( $wf_b );
$assert( SOM_Shipments::is_ship_step( $b_steps[0] ), 'is_ship_step_by_batch_group' );

$prod_b = SOM_Products::create(
	array(
		'name'                 => 'Ship batch product',
		'workflow_template_id' => $wf_b,
		'is_active'            => 1,
	)
);
$prod_b = (int) $prod_b;

$oids = array();
for ( $i = 0; $i < 2; $i++ ) {
	$id = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => 'ship-batch-' . $i . '-' . wp_generate_password( 4, false, false ),
			'buyer_name'        => 'Batch Buyer ' . $i,
			'shipping_address'  => array(
				'line1'    => ( $i + 1 ) . ' Batch Rd',
				'city'     => 'London',
				'postcode' => 'E1 1DD',
				'country'  => 'GB',
			),
			'items'             => array(
				array(
					'product_id' => $prod_b,
					'quantity'   => 1,
				),
			),
		)
	);
	$assert( ! is_wp_error( $id ), 'batch_order_' . $i );
	$oids[] = (int) $id;
}

// Force release when collecting (size may be 4).
$batch_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT bi.batch_id FROM ' . SOM_DB::table( 'step_batch_items' ) . ' bi
		WHERE bi.order_id = %d LIMIT 1',
		$oids[0]
	)
);
$assert( $batch_id > 0, 'batch_enqueued' );

$release = SOM_Batches::release( $batch_id );
$assert( ! is_wp_error( $release ), 'batch_release' );

$mark = SOM_Batches::mark_done( $batch_id );
$assert( is_wp_error( $mark ) && 'som_shipment_required' === $mark->get_error_code(), 'batch_blocked_missing_shipment' );

foreach ( $oids as $oid ) {
	SOM_Shipments::upsert(
		$oid,
		array(
			'carrier'      => 'Royal Mail',
			'service'      => '2nd Class',
			'shipped_at'   => current_time( 'Y-m-d' ),
			'postage_paid' => '1.10',
		)
	);
}

$mark2 = SOM_Batches::mark_done( $batch_id );
$assert( true === $mark2, 'batch_mark_done_with_shipments' );

$out( 'fail_count', (string) $fail );
if ( $fail > 0 ) {
	echo "RESULT: FAIL\n";
	exit( 1 );
}
echo "RESULT: PASS\n";
