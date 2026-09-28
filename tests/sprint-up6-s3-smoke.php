<?php
/**
 * UP6-S3 smoke: seed make/pack shape, no thank-you batch on pack, repair binding.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up6-s3-smoke.php
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
$out( 'db', (string) get_option( 'som_db_version', '' ) );
$assert( version_compare( SOM_VERSION, '0.32.0', '>=' ), 'SOM_VERSION_gte_0.32.0' );

// Clean reseed without requiring Restore's dummy gate.
SOM_Seed::remove_seed_data();
SOM_Seed::seed_catalogue_now();
SOM_Batch_Groups::ensure_rows();
SOM_Batch_Groups::convert_thankyou_steps();

$ids = SOM_Seed::resolve_seed_ids();
$assert( (int) $ids['workflow_id'] > 0, 'seed_make_workflow_id' );
$assert( (int) $ids['pack_workflow_id'] > 0, 'seed_pack_workflow_id' );
$assert( (int) get_option( 'som_seed_workflow_id', 0 ) === (int) $ids['workflow_id'], 'option_seed_workflow_id' );
$assert( (int) get_option( 'som_seed_pack_workflow_id', 0 ) === (int) $ids['pack_workflow_id'], 'option_seed_pack_workflow_id' );

$make = SOM_Workflows::get( (int) $ids['workflow_id'] );
$assert( $make && 'Bin Sticker Make' === (string) $make->name, 'make_name_bin_sticker_make' );
$assert( $make && 'make' === SOM_Workflows::sanitize_kind( $make->kind ), 'make_kind' );

$make_names = array_map(
	static function ( $s ) {
		return (string) $s->name;
	},
	isset( $make->steps ) ? $make->steps : array()
);
$assert( array( 'Print', 'Confirm print', 'Dry', 'Laminate', 'Cut' ) === $make_names, 'make_steps_print_to_cut' );
$assert( ! in_array( 'Confirm pack', $make_names, true ), 'make_no_confirm_pack' );
$assert( ! in_array( 'Ship', $make_names, true ), 'make_no_ship' );
$assert( ! in_array( 'Thank-you', $make_names, true ), 'make_no_thank_you' );
$assert( ! in_array( 'Review reminder', $make_names, true ), 'make_no_review' );

$pack = SOM_Workflows::get( (int) $ids['pack_workflow_id'] );
$assert( $pack && 'Order Pack & Ship' === (string) $pack->name, 'pack_name' );
$assert( $pack && 'pack' === SOM_Workflows::sanitize_kind( $pack->kind ), 'pack_kind' );

$pack_names = array_map(
	static function ( $s ) {
		return (string) $s->name;
	},
	isset( $pack->steps ) ? $pack->steps : array()
);
$assert(
	array( 'Confirm pack', 'Confirm address', 'Package', 'Ship', 'Review reminder' ) === $pack_names,
	'pack_steps_shape'
);

$pack_kinds = array();
$pack_batch = false;
foreach ( $pack->steps as $step ) {
	$pack_kinds[ (string) $step->name ] = isset( $step->confirmation_kind ) ? (string) $step->confirmation_kind : '';
	if ( ! empty( $step->batch_group_id ) ) {
		$pack_batch = true;
	}
}
$assert( 'packing_items' === ( $pack_kinds['Confirm pack'] ?? '' ), 'pack_confirm_packing_items' );
$assert( 'shipping_address' === ( $pack_kinds['Confirm address'] ?? '' ), 'pack_confirm_address' );
$assert( ! $pack_batch, 'pack_no_batch_groups' );

$assert( SOM_Pack::default_template_id() === (int) $ids['pack_workflow_id'], 'default_pack_is_seed' );

// convert_thankyou must not attach batch to pack steps even if a thank-you script sneaks in.
$rogue = $wpdb->insert(
	SOM_DB::table( 'workflow_steps' ),
	array(
		'workflow_template_id'    => (int) $ids['pack_workflow_id'],
		'step_order'              => 99,
		'name'                    => 'Rogue thank-you',
		'requires_manual_confirm' => 0,
		'timer_seconds'           => null,
		'script_config'           => wp_json_encode(
			array(
				'type'   => 'local',
				'action' => 'run_thankyou_card_script',
				'params' => array(),
			)
		),
		'created_at'              => current_time( 'mysql', true ),
		'updated_at'              => current_time( 'mysql', true ),
	),
	array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
);
$assert( (bool) $rogue, 'insert_rogue_thankyou_on_pack' );
$rogue_id = (int) $wpdb->insert_id;
$converted = SOM_Batch_Groups::convert_thankyou_steps();
$out( 'convert_thankyou_count', (string) $converted );
$rogue_row = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT batch_group_id, script_config FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d',
		$rogue_id
	)
);
$assert( $rogue_row && empty( $rogue_row->batch_group_id ), 'convert_skips_pack_kind' );
$wpdb->delete( SOM_DB::table( 'workflow_steps' ), array( 'id' => $rogue_id ), array( '%d' ) );

// Product assigned to make template.
$product = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT id, workflow_template_id FROM ' . SOM_DB::table( 'products' ) . ' WHERE sku = %s LIMIT 1',
		SOM_Seed::SAMPLE_PRODUCT_SKU
	)
);
$assert( $product && (int) $product->workflow_template_id === (int) $ids['workflow_id'], 'product_uses_make_template' );

// New order binds pack.
$external_id = 'up6s3-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
$order_id    = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $external_id,
		'buyer_name'        => 'UP6-S3 Tester',
		'items'             => array(
			array(
				'product_id' => (int) $product->id,
				'quantity'   => 1,
				'unit_price' => 10,
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;
$order    = SOM_Orders::get( $order_id );
$assert( $order && (int) $order->pack_workflow_template_id === (int) $ids['pack_workflow_id'], 'order_pack_bound' );

// Simulate unbound order (item make kept; order pack cleared) then repair.
$wpdb->update(
	SOM_DB::table( 'orders' ),
	array(
		'pack_workflow_template_id' => null,
		'current_step_id'           => null,
		'updated_at'                => current_time( 'mysql', true ),
	),
	array( 'id' => $order_id ),
	array( '%s', '%s', '%s' ),
	array( '%d' )
);
$wpdb->delete( SOM_DB::table( 'order_step_progress' ), array( 'order_id' => $order_id ), array( '%d' ) );
$assert( SOM_Item_Make::item_has_progress( (int) $order->items[0]->id ), 'item_make_kept' );

$repair = SOM_Pack::repair_unbound_orders();
$out( 'repair', wp_json_encode( $repair ) );
$assert( (int) $repair['repaired'] >= 1, 'repair_repaired_at_least_one' );
$order = SOM_Orders::get( $order_id );
$assert( $order && (int) $order->pack_workflow_template_id === (int) $ids['pack_workflow_id'], 'repair_rebound_order' );
$assert( SOM_Item_Make::item_has_progress( (int) $order->items[0]->id ), 'item_make_untouched_after_repair' );

// Legacy order with order-level progress skipped.
$legacy_ext = 'up6s3l-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );
$legacy_id  = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $legacy_ext,
		'buyer_name'        => 'UP6 legacy',
		'items'             => array(
			array(
				'product_id' => (int) $product->id,
				'quantity'   => 1,
				'unit_price' => 5,
			),
		),
	)
);
$assert( ! is_wp_error( $legacy_id ), 'create_legacy_candidate' );
$legacy_id = (int) $legacy_id;
// Force "legacy" shape: clear pack bind but keep order_step_progress rows.
$wpdb->update(
	SOM_DB::table( 'orders' ),
	array( 'pack_workflow_template_id' => null ),
	array( 'id' => $legacy_id ),
	array( '%s' ),
	array( '%d' )
);
$assert( SOM_Workflow_Engine::has_progress( $legacy_id ), 'legacy_has_order_progress' );
$before_skip = SOM_Pack::repair_unbound_orders();
$legacy      = SOM_Orders::get( $legacy_id );
$assert( empty( $legacy->pack_workflow_template_id ), 'legacy_skipped_unbound' );
$assert( (int) $before_skip['skipped'] >= 1, 'repair_skipped_legacy' );

// Idempotent pack seed when already present.
$pack_before = (int) get_option( 'som_seed_pack_workflow_id', 0 );
SOM_Seed::maybe_seed_pack_workflow();
$assert( $pack_before === (int) get_option( 'som_seed_pack_workflow_id', 0 ), 'pack_seed_idempotent' );
$pack2 = SOM_Workflows::get( $pack_before );
$assert( $pack2 && 5 === count( $pack2->steps ), 'pack_steps_unchanged_on_reseed' );

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK\n";
