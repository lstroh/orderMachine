<?php
/**
 * UP7-S2 smoke: shipping package materials + Materials used (Package source).
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up7-s2-smoke.php
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
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.17.0', '>=' ), 'db_gte_1.17.0' );

$pkg_mat_t = SOM_DB::table( 'shipping_package_materials' );
$table_ok  = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pkg_mat_t ) ) === $pkg_mat_t;
$assert( $table_ok, 'shipping_package_materials_table' );

$assert(
	__( 'Package reserve', 'order-machine' ) === SOM_Materials::reason_label( 'package_reserve' ),
	'package_reserve_label'
);

$suffix = wp_generate_password( 4, false, false );

$thankyou = SOM_Materials::create(
	array(
		'name'      => 'UP7S2 Thank-you ' . $suffix,
		'unit'      => 'each',
		'unit_cost' => 0.10,
		'is_active' => 1,
	)
);
$envelope = SOM_Materials::create(
	array(
		'name'      => 'UP7S2 Envelope ' . $suffix,
		'unit'      => 'each',
		'unit_cost' => 0.25,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $thankyou ), 'create_thankyou_material' );
$assert( ! is_wp_error( $envelope ), 'create_envelope_material' );
$thankyou_id = (int) $thankyou;
$envelope_id = (int) $envelope;
SOM_Materials::adjust_stock( $thankyou_id, 100, array( 'reason' => 'restock', 'unit_cost_at_time' => 0.10 ) );
SOM_Materials::adjust_stock( $envelope_id, 100, array( 'reason' => 'restock', 'unit_cost_at_time' => 0.25 ) );

$pkg_a = SOM_Shipping_Packages::create(
	array(
		'name'              => 'UP7S2 Small ' . $suffix,
		'length_mm'         => 220,
		'width_mm'          => 110,
		'height_mm'         => 0,
		'tare_weight_grams' => 15,
		'is_active'         => 1,
		'is_default'        => 0,
	)
);
$assert( ! is_wp_error( $pkg_a ), 'create_package_a' );
$pkg_a_id = (int) $pkg_a;

$saved = SOM_Shipping_Packages::save_materials(
	$pkg_a_id,
	array(
		array(
			'material_id'          => $thankyou_id,
			'quantity_per_package' => 1,
		),
		array(
			'material_id'          => $envelope_id,
			'quantity_per_package' => 1,
		),
	)
);
$assert( true === $saved, 'save_package_materials' );

$dup = SOM_Shipping_Packages::save_materials(
	$pkg_a_id,
	array(
		array(
			'material_id'          => $thankyou_id,
			'quantity_per_package' => 1,
		),
		array(
			'material_id'          => $thankyou_id,
			'quantity_per_package' => 2,
		),
	)
);
$assert( is_wp_error( $dup ) && 'som_package_recipe_duplicate' === $dup->get_error_code(), 'reject_duplicate_materials' );

$bom = SOM_Shipping_Packages::get_materials( $pkg_a_id );
$assert( 2 === count( $bom ), 'bom_two_rows' );

$pkg_b = SOM_Shipping_Packages::create(
	array(
		'name'              => 'UP7S2 Large ' . $suffix,
		'length_mm'         => 300,
		'width_mm'          => 220,
		'height_mm'         => 20,
		'tare_weight_grams' => 40,
		'is_active'         => 1,
		'is_default'        => 0,
	)
);
$assert( ! is_wp_error( $pkg_b ), 'create_package_b' );
$pkg_b_id = (int) $pkg_b;
SOM_Shipping_Packages::save_materials(
	$pkg_b_id,
	array(
		array(
			'material_id'          => $thankyou_id,
			'quantity_per_package' => 1,
		),
		array(
			'material_id'          => $envelope_id,
			'quantity_per_package' => 2,
		),
	)
);

$product_id = (int) $wpdb->get_var(
	'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_active = 1 AND is_internal = 0 AND workflow_template_id IS NOT NULL ORDER BY id ASC LIMIT 1'
);
$assert( $product_id > 0, 'has_seed_product' );

// Ensure pack bind can suggest/set packages.
$pack_wf = SOM_Workflows::create(
	array(
		'name'      => 'UP7S2 Pack ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $pack_wf ), 'create_pack_template' );
$pack_wf_id = (int) $pack_wf;
SOM_Workflows::save_steps(
	$pack_wf_id,
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'packing_items',
		),
	)
);
$prev_default = SOM_Pack::default_template_id();
SOM_Pack::set_default_template_id( $pack_wf_id );

$external_id = 'up7s2-' . gmdate( 'YmdHis' ) . '-' . $suffix;
$order_id    = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => $external_id,
		'buyer_name'        => 'UP7-S2 Tester',
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

// UP7-S3 may already have reserved a suggested package on create; count those
// rows before switching so we only assert the +2 from package A.
$reserve_before = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		'package_reserve'
	)
);

// Clear any auto-suggested package reserves, then select package A.
$set = SOM_Pack::set_shipping_package( $order_id, $pkg_a_id );
$assert( ! is_wp_error( $set ), 'set_package_a' );

$usage = SOM_Material_Stock::get_usage_by_material( $order_id );
$pkg_rows = array();
foreach ( $usage as $row ) {
	if ( SOM_Material_Stock::SOURCE_PACKAGE === (string) $row->source ) {
		$pkg_rows[ (int) $row->material_id ] = $row;
	}
}
$assert( isset( $pkg_rows[ $thankyou_id ] ), 'usage_has_thankyou_package' );
$assert( isset( $pkg_rows[ $envelope_id ] ), 'usage_has_envelope_package' );
$assert(
	isset( $pkg_rows[ $thankyou_id ] ) && abs( (float) $pkg_rows[ $thankyou_id ]->planned - 1.0 ) < 0.001,
	'thankyou_planned_1'
);
$assert(
	isset( $pkg_rows[ $envelope_id ] ) && abs( (float) $pkg_rows[ $envelope_id ]->planned - 1.0 ) < 0.001,
	'envelope_planned_1'
);

$reserve_logs = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		'package_reserve'
	)
);
$assert( ( $reserve_before + 2 ) === $reserve_logs, 'two_package_reserve_logs' );

// Idempotent re-set same package.
$set_again = SOM_Pack::set_shipping_package( $order_id, $pkg_a_id );
$assert( ! is_wp_error( $set_again ), 'set_package_a_idempotent' );
$reserve_logs_2 = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		'package_reserve'
	)
);
$assert( $reserve_logs === $reserve_logs_2, 'idempotent_no_extra_reserves' );

$cogs_before_extra = SOM_Analytics::order_material_cogs( $order_id );
$assert( $cogs_before_extra > 0, 'cogs_includes_package_reserve' );

$raise = SOM_Material_Stock::apply_overuse(
	$order_id,
	array(
		SOM_Material_Stock::SOURCE_PACKAGE => array(
			$thankyou_id => 2.0,
		),
	)
);
$assert( ! is_wp_error( $raise ), 'raise_package_actual' );

$usage2 = SOM_Material_Stock::get_usage_by_material( $order_id );
$thank_row = null;
foreach ( $usage2 as $row ) {
	if ( SOM_Material_Stock::SOURCE_PACKAGE === (string) $row->source && (int) $row->material_id === $thankyou_id ) {
		$thank_row = $row;
		break;
	}
}
$assert( null !== $thank_row && abs( (float) $thank_row->actual - 2.0 ) < 0.001, 'package_actual_raised' );
$assert( null !== $thank_row && abs( (float) $thank_row->extra - 1.0 ) < 0.001, 'package_extra_1' );

$cogs_after_extra = SOM_Analytics::order_material_cogs( $order_id );
$assert( $cogs_after_extra > $cogs_before_extra, 'cogs_includes_package_extra' );

// Package change: reverse A, reserve B (envelope qty 2).
$change = SOM_Pack::set_shipping_package( $order_id, $pkg_b_id );
$assert( ! is_wp_error( $change ), 'change_to_package_b' );

$unreserve_logs = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
		$order_id,
		'package_unreserve'
	)
);
$assert( $unreserve_logs >= 2, 'package_unreserve_on_change' );

$usage3 = SOM_Material_Stock::get_usage_by_material( $order_id );
$env_row = null;
foreach ( $usage3 as $row ) {
	if ( SOM_Material_Stock::SOURCE_PACKAGE === (string) $row->source && (int) $row->material_id === $envelope_id ) {
		$env_row = $row;
		break;
	}
}
$assert( null !== $env_row && abs( (float) $env_row->planned - 2.0 ) < 0.001, 'envelope_planned_2_after_change' );

// Internal Produce N skips package reserve.
$internal_product = (int) $wpdb->get_var(
	'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_internal = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1'
);
if ( $internal_product > 0 ) {
	$internal_order = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => SOM_Production::CHANNEL_SLUG,
			'external_order_id' => 'up7s2-int-' . $suffix,
			'buyer_name'        => 'Internal',
			'items'             => array(
				array(
					'product_id' => $internal_product,
					'quantity'   => 1,
				),
			),
		)
	);
	if ( ! is_wp_error( $internal_order ) ) {
		$internal_id = (int) $internal_order;
		SOM_Pack::set_shipping_package( $internal_id, $pkg_a_id );
		$int_pkg = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
				$internal_id,
				'package_reserve'
			)
		);
		$assert( 0 === $int_pkg, 'internal_skips_package_reserve' );
	} else {
		$assert( true, 'internal_skips_package_reserve_skipped' );
	}
} else {
	$assert( true, 'internal_skips_package_reserve_skipped' );
}

SOM_Pack::set_default_template_id( $prev_default );

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'OK' );
$out( 'order_id', (string) $order_id );
$out( 'package_a', (string) $pkg_a_id );
$out( 'package_b', (string) $pkg_b_id );
