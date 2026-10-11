<?php
/**
 * UP7-S3 smoke: package suggestion (1 vs 2+ / mixed) + multi-line test orders.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up7-s3-smoke.php
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
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.18.0', '>=' ), 'db_gte_1.18.0' );

$products_t = SOM_DB::table( 'products' );
$col        = $wpdb->get_row( "SHOW COLUMNS FROM {$products_t} LIKE 'package_id_multi'" );
$assert( ! empty( $col ), 'package_id_multi_column' );

$suffix = wp_generate_password( 4, false, false );

$pkg_small = SOM_Shipping_Packages::create(
	array(
		'name'              => 'UP7S3 Small ' . $suffix,
		'length_mm'         => 220,
		'width_mm'          => 110,
		'height_mm'         => 0,
		'tare_weight_grams' => 15,
		'is_active'         => 1,
		'is_default'        => 1,
	)
);
$pkg_large = SOM_Shipping_Packages::create(
	array(
		'name'              => 'UP7S3 Large ' . $suffix,
		'length_mm'         => 300,
		'width_mm'          => 220,
		'height_mm'         => 20,
		'tare_weight_grams' => 40,
		'is_active'         => 1,
		'is_default'        => 0,
	)
);
$assert( ! is_wp_error( $pkg_small ), 'create_small_package' );
$assert( ! is_wp_error( $pkg_large ), 'create_large_package' );
$small_id = (int) $pkg_small;
$large_id = (int) $pkg_large;

$thankyou = SOM_Materials::create(
	array(
		'name'      => 'UP7S3 Thank-you ' . $suffix,
		'unit'      => 'each',
		'unit_cost' => 0.10,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $thankyou ), 'create_thankyou' );
$thankyou_id = (int) $thankyou;
SOM_Materials::adjust_stock( $thankyou_id, 100, array( 'reason' => 'restock', 'unit_cost_at_time' => 0.10 ) );
SOM_Shipping_Packages::save_materials(
	$small_id,
	array(
		array(
			'material_id'          => $thankyou_id,
			'quantity_per_package' => 1,
		),
	)
);
SOM_Shipping_Packages::save_materials(
	$large_id,
	array(
		array(
			'material_id'          => $thankyou_id,
			'quantity_per_package' => 2,
		),
	)
);

$wf = SOM_Workflows::create(
	array(
		'name'      => 'UP7S3 Make ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf ), 'create_make_workflow' );
$wf_id = (int) $wf;
SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'name'                    => 'Cut',
			'requires_manual_confirm' => 1,
		),
	)
);

$product_a = SOM_Products::create(
	array(
		'name'                 => 'UP7S3 Product A ' . $suffix,
		'sku'                  => 'UP7S3-A-' . $suffix,
		'workflow_template_id' => $wf_id,
		'package_id'           => $small_id,
		'package_id_multi'     => $large_id,
		'is_active'            => 1,
	)
);
$product_b = SOM_Products::create(
	array(
		'name'                 => 'UP7S3 Product B ' . $suffix,
		'sku'                  => 'UP7S3-B-' . $suffix,
		'workflow_template_id' => $wf_id,
		'package_id'           => $small_id,
		'package_id_multi'     => $small_id, // Conflicts with A on multi.
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product_a ), 'create_product_a' );
$assert( ! is_wp_error( $product_b ), 'create_product_b' );
$product_a_id = (int) $product_a;
$product_b_id = (int) $product_b;

$loaded = SOM_Products::get( $product_a_id );
$assert( $loaded && (int) $loaded->package_id === $small_id, 'product_a_package_id' );
$assert( $loaded && (int) $loaded->package_id_multi === $large_id, 'product_a_package_id_multi' );

$pack_wf = SOM_Workflows::create(
	array(
		'name'      => 'UP7S3 Pack ' . $suffix,
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

$mk_order = static function ( $external_suffix, array $items ) use ( $suffix ) {
	return SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => 'up7s3-' . $external_suffix . '-' . $suffix,
			'buyer_name'        => 'UP7-S3 Tester',
			'shipping_address'  => array(
				'line1'    => '1 Test Street',
				'city'     => 'London',
				'postcode' => 'E1 1AA',
				'country'  => 'GB',
			),
			'items'             => $items,
		)
	);
};

// Qty 1 → small package A.
$order_1 = $mk_order(
	'q1',
	array(
		array(
			'product_id' => $product_a_id,
			'quantity'   => 1,
			'unit_price' => 10,
		),
	)
);
$assert( ! is_wp_error( $order_1 ), 'create_qty1_order' );
$order_1 = (int) $order_1;
$o1      = SOM_Orders::get( $order_1 );
$assert( $o1 && (int) $o1->shipping_package_id === $small_id, 'qty1_suggests_small' );
$suggest_1 = SOM_Pack::suggest_package( $o1 );
$assert( empty( $suggest_1['mixed_conflict'] ), 'qty1_no_mixed_flag' );
$assert( 1 === (int) $suggest_1['total_sellable_qty'], 'qty1_total' );

$usage_1 = SOM_Material_Stock::get_usage_by_material( $order_1 );
$pkg_planned_1 = null;
foreach ( $usage_1 as $row ) {
	if ( SOM_Material_Stock::SOURCE_PACKAGE === (string) $row->source && (int) $row->material_id === $thankyou_id ) {
		$pkg_planned_1 = (float) $row->planned;
		break;
	}
}
$assert( null !== $pkg_planned_1 && abs( $pkg_planned_1 - 1.0 ) < 0.001, 'qty1_package_materials_reserved' );

// Qty 2 → large package B.
$order_2 = $mk_order(
	'q2',
	array(
		array(
			'product_id' => $product_a_id,
			'quantity'   => 2,
			'unit_price' => 10,
		),
	)
);
$assert( ! is_wp_error( $order_2 ), 'create_qty2_order' );
$order_2 = (int) $order_2;
$o2      = SOM_Orders::get( $order_2 );
$assert( $o2 && (int) $o2->shipping_package_id === $large_id, 'qty2_suggests_large' );
$suggest_2 = SOM_Pack::suggest_package( $o2 );
$assert( empty( $suggest_2['mixed_conflict'] ), 'qty2_no_mixed_flag' );

$usage_2 = SOM_Material_Stock::get_usage_by_material( $order_2 );
$pkg_planned_2 = null;
foreach ( $usage_2 as $row ) {
	if ( SOM_Material_Stock::SOURCE_PACKAGE === (string) $row->source && (int) $row->material_id === $thankyou_id ) {
		$pkg_planned_2 = (float) $row->planned;
		break;
	}
}
$assert( null !== $pkg_planned_2 && abs( $pkg_planned_2 - 2.0 ) < 0.001, 'qty2_package_materials_from_large' );

// Mixed conflict → site default + flag.
$order_mix = $mk_order(
	'mix',
	array(
		array(
			'product_id' => $product_a_id,
			'quantity'   => 1,
			'unit_price' => 10,
		),
		array(
			'product_id' => $product_b_id,
			'quantity'   => 1,
			'unit_price' => 12,
		),
	)
);
$assert( ! is_wp_error( $order_mix ), 'create_mixed_order' );
$order_mix = (int) $order_mix;
$o_mix     = SOM_Orders::get( $order_mix );
$assert( $o_mix && 2 === count( $o_mix->items ), 'mixed_two_lines' );
$assert( $o_mix && (int) $o_mix->shipping_package_id === $small_id, 'mixed_suggests_site_default' );
$assert( SOM_Pack::has_mixed_package_conflict( $o_mix ), 'mixed_conflict_flag' );

// Operator override still syncs package materials (S2).
$override = SOM_Pack::set_shipping_package( $order_mix, $large_id );
$assert( ! is_wp_error( $override ), 'mixed_override_to_large' );
$usage_mix = SOM_Material_Stock::get_usage_by_material( $order_mix );
$pkg_planned_mix = null;
foreach ( $usage_mix as $row ) {
	if ( SOM_Material_Stock::SOURCE_PACKAGE === (string) $row->source && (int) $row->material_id === $thankyou_id ) {
		$pkg_planned_mix = (float) $row->planned;
		break;
	}
}
$assert( null !== $pkg_planned_mix && abs( $pkg_planned_mix - 2.0 ) < 0.001, 'override_reserves_large_materials' );

// Multi-line create_from_external (same path as Create test order).
$order_ml = $mk_order(
	'ml',
	array(
		array(
			'product_id'           => $product_a_id,
			'quantity'             => 1,
			'unit_price'           => 9.5,
			'personalisation_text' => 'Hello A',
		),
		array(
			'product_id' => $product_b_id,
			'quantity'   => 3,
			'unit_price' => 11,
		),
	)
);
$assert( ! is_wp_error( $order_ml ), 'create_multiline_order' );
$order_ml = (int) $order_ml;
$o_ml     = SOM_Orders::get( $order_ml );
$assert( $o_ml && 2 === count( $o_ml->items ), 'multiline_two_items' );
$qtys = array();
foreach ( $o_ml->items as $item ) {
	$qtys[ (int) $item->product_id ] = (int) $item->quantity;
}
$assert( isset( $qtys[ $product_a_id ], $qtys[ $product_b_id ] ), 'multiline_both_products' );
$assert( 1 === $qtys[ $product_a_id ] && 3 === $qtys[ $product_b_id ], 'multiline_quantities' );
$suggest_ml = SOM_Pack::suggest_package( $o_ml );
$assert( 4 === (int) $suggest_ml['total_sellable_qty'], 'multiline_total_qty_sum' );

// Seed packages exist when catalogue seeded under dummy credentials.
if ( defined( 'SOM_USE_DUMMY_CREDENTIALS' ) && SOM_USE_DUMMY_CREDENTIALS ) {
	$seed_ids = SOM_Seed::resolve_seed_ids();
	$assert( (int) ( $seed_ids['package_small_id'] ?? 0 ) > 0, 'seed_small_package' );
	$assert( (int) ( $seed_ids['package_large_id'] ?? 0 ) > 0, 'seed_large_package' );
	$seed_product = (int) ( $seed_ids['product_id'] ?? 0 );
	if ( $seed_product > 0 ) {
		$sp = SOM_Products::get( $seed_product );
		$assert( $sp && ! empty( $sp->package_id ), 'seed_product_package_id' );
		$assert( $sp && ! empty( $sp->package_id_multi ), 'seed_product_package_id_multi' );
	} else {
		$assert( false, 'seed_product_present' );
	}
}

SOM_Pack::set_default_template_id( $prev_default );

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'OK' );
$out( 'order_qty1', (string) $order_1 );
$out( 'order_qty2', (string) $order_2 );
$out( 'order_mixed', (string) $order_mix );
$out( 'order_multiline', (string) $order_ml );
