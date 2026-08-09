<?php
/**
 * Sprint U6 smoke: Batches admin helpers, group update, step editor batch_group_id.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u6-smoke.php
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
SOM_Batch_Groups::ensure_rows();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( version_compare( SOM_VERSION, '0.17.0', '>=' ), 'SOM_VERSION_gte_0.17.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.5.0', '>=' ), 'som_db_version_gte_1.5.0' );
$assert( is_readable( SOM_PLUGIN_DIR . 'admin/views/batches.php' ), 'batches_view_exists' );

$ship_group = SOM_Batch_Groups::get_by_key( 'shipping_label' );
$ty_group   = SOM_Batch_Groups::get_by_key( 'thank_you_card' );
$assert( (bool) $ship_group, 'shipping_label_group' );
$assert( (bool) $ty_group, 'thank_you_group' );

// --- Batch group update (display name / size; key fixed) ---
$orig_name = (string) $ship_group->display_name;
$orig_size = (int) $ship_group->batch_size;
$updated   = SOM_Batch_Groups::update(
	(int) $ship_group->id,
	array(
		'display_name' => 'Shipping labels (U6 test)',
		'batch_size'   => 5,
	)
);
$assert( true === $updated, 'batch_group_update' );
$ship_group = SOM_Batch_Groups::get( (int) $ship_group->id );
$assert( $ship_group && 'Shipping labels (U6 test)' === (string) $ship_group->display_name, 'batch_group_name' );
$assert( $ship_group && 5 === (int) $ship_group->batch_size, 'batch_group_size' );
$assert( $ship_group && 'shipping_label' === (string) $ship_group->key, 'batch_group_key_fixed' );
SOM_Batch_Groups::update(
	(int) $ship_group->id,
	array(
		'display_name' => $orig_name,
		'batch_size'   => $orig_size,
	)
);
$ship_group = SOM_Batch_Groups::get_by_key( 'shipping_label' );

$settings = SOM_Settings::get();
if ( '' === (string) $settings['api_key'] ) {
	SOM_Settings::update( array( 'api_key' => 'sprint-u6-test-key' ) );
	$settings = SOM_Settings::get();
}
$api_key = (string) $settings['api_key'];

/**
 * Create a one-step batch workflow + product.
 *
 * @param string $name     Workflow name.
 * @param int    $group_id Batch group PK.
 * @return array{product_id:int,step_id:int,template_id:int}|null
 */
$make_batch_product = static function ( $name, $group_id ) {
	$wf = SOM_Workflows::create(
		array(
			'name'      => $name,
			'is_active' => 1,
		)
	);
	if ( is_wp_error( $wf ) ) {
		return null;
	}
	$wf = (int) $wf;

	$saved = SOM_Workflows::save_steps(
		$wf,
		array(
			array(
				'name'           => 'Batch gate',
				'batch_group_id' => (int) $group_id,
			),
		)
	);
	if ( is_wp_error( $saved ) ) {
		return null;
	}

	$steps = SOM_Workflows::get_steps( $wf );
	if ( empty( $steps ) || empty( $steps[0]->batch_group_id ) ) {
		return null;
	}

	$product = SOM_Products::create(
		array(
			'name'                 => $name . ' Product',
			'workflow_template_id' => $wf,
			'is_active'            => 1,
		)
	);
	if ( is_wp_error( $product ) ) {
		return null;
	}

	return array(
		'product_id'  => (int) $product,
		'step_id'     => (int) $steps[0]->id,
		'template_id' => $wf,
	);
};

/**
 * Create an order for a product via REST (assigns workflow).
 *
 * @param int    $product_id Product PK.
 * @param string $suffix     External id suffix.
 * @return int Order PK or 0.
 */
$create_order = static function ( $product_id, $suffix ) use ( $api_key ) {
	$external_id = 'u6-' . $suffix . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
	$req         = new WP_REST_Request( 'POST', '/som/v1/orders' );
	$req->set_header( 'x-som-api-key', $api_key );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body(
		wp_json_encode(
			array(
				'external_order_id' => $external_id,
				'buyer_name'        => 'U6 Buyer ' . $suffix,
				'shipping_address'  => array(
					'full_name' => 'U6 Buyer ' . $suffix,
					'line1'     => '1 Test Street',
					'city'      => 'London',
					'postcode'  => 'E1 1AA',
					'country'   => 'GB',
				),
				'items'             => array(
					array(
						'product_id' => (int) $product_id,
						'quantity'   => 1,
					),
				),
			)
		)
	);
	$res  = rest_do_request( $req );
	$data = $res->get_data();
	return isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
};

// --- Step editor: clear / opt-in shipping_label / combo reject ---
$editor = $make_batch_product( 'U6 Editor ' . wp_generate_password( 4, false, false ), (int) $ship_group->id );
$assert( null !== $editor, 'editor_setup' );
if ( $editor ) {
	$cleared = SOM_Workflows::save_steps(
		$editor['template_id'],
		array(
			array(
				'id'             => $editor['step_id'],
				'name'           => 'Batch gate',
				'batch_group_id' => 0,
			),
		)
	);
	$assert( true === $cleared, 'clear_batch_group_id' );
	$steps = SOM_Workflows::get_steps( $editor['template_id'] );
	$assert( ! empty( $steps ) && empty( $steps[0]->batch_group_id ), 'batch_group_cleared' );

	$reopt = SOM_Workflows::save_steps(
		$editor['template_id'],
		array(
			array(
				'id'             => $editor['step_id'],
				'name'           => 'Ship',
				'batch_group_id' => (int) $ship_group->id,
			),
		)
	);
	$assert( true === $reopt, 'shipping_label_opt_in' );
	$steps = SOM_Workflows::get_steps( $editor['template_id'] );
	$assert( ! empty( $steps ) && (int) $steps[0]->batch_group_id === (int) $ship_group->id, 'shipping_label_assigned' );

	$combo = SOM_Workflows::save_steps(
		$editor['template_id'],
		array(
			array(
				'id'                      => $editor['step_id'],
				'name'                    => 'Ship',
				'batch_group_id'          => (int) $ship_group->id,
				'requires_manual_confirm' => 1,
			),
		)
	);
	$assert( is_wp_error( $combo ) && 'som_batch_only_step' === $combo->get_error_code(), 'batch_combo_rejected' );
}

// --- Collecting batch → query / find_for_order / members / release path ---
$manual_setup = $make_batch_product( 'U6 Manual Ship ' . wp_generate_password( 4, false, false ), (int) $ship_group->id );
$assert( null !== $manual_setup, 'manual_setup' );

$order_ids = array();
if ( $manual_setup ) {
	for ( $i = 1; $i <= 2; $i++ ) {
		$oid = $create_order( $manual_setup['product_id'], 'm' . $i );
		$order_ids[] = $oid;
		$assert( $oid > 0, 'manual_order_' . $i );
	}
}

$batch = ! empty( $order_ids[0] ) ? SOM_Batches::find_for_order( $order_ids[0] ) : null;
$assert( $batch && 'collecting' === (string) $batch->status, 'find_for_order_collecting' );
$assert( $batch && (int) $batch->item_count >= 2, 'find_for_order_count' );

$list = SOM_Batches::query( array( 'include_done' => false ) );
$assert( ! empty( $list['batches'] ), 'query_open_batches' );
$seen = false;
foreach ( $list['batches'] as $row ) {
	if ( $batch && (int) $row->id === (int) $batch->id ) {
		$seen = true;
		break;
	}
}
$assert( $seen, 'query_contains_batch' );

$members = $batch ? SOM_Batches::get_items_with_orders( (int) $batch->id ) : array();
$assert( count( $members ) >= 2, 'items_with_orders' );
$assert( ! empty( $members[0]->buyer_name ), 'member_buyer_name' );
$assert( ! empty( $members[0]->shipping_address ), 'member_shipping_address' );

$url = SOM_Batches::batch_url( $batch ? (int) $batch->id : 1 );
$assert( false !== strpos( $url, 'page=som-batches' ) && false !== strpos( $url, 'batch_id=' ), 'batch_url' );

if ( $batch ) {
	$released = SOM_Batches::release( (int) $batch->id, true );
	$assert( true === $released, 'release_from_ui_path' );
	$batch = SOM_Batches::get( (int) $batch->id );
	$assert( $batch && 'ready' === (string) $batch->status, 'batch_ready' );
	$done = SOM_Batches::mark_done( (int) $batch->id );
	$assert( true === $done, 'mark_done_from_ui_path' );
}

if ( $fail > 0 ) {
	echo "FAIL — Sprint U6 smoke ({$fail} assertion(s))\n";
	exit( 1 );
}

echo "PASS — Sprint U6 smoke\n";
