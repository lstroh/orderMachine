<?php
/**
 * UP5-S2 smoke: shipping packages, product fields, order seed, costing, internal null.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up5-s2-smoke.php
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
$assert( version_compare( SOM_VERSION, '0.29.0', '>=' ), 'SOM_VERSION_gte_0.29.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.14.0', '>=' ), 'db_gte_1.14.0' );

$packages_t = SOM_DB::table( 'shipping_packages' );
$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $packages_t ) );
$assert( $exists === $packages_t, 'table_shipping_packages' );

$products_t = SOM_DB::table( 'products' );
$col_w      = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $products_t . ' LIKE %s', 'weight_grams' ) );
$col_p      = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $products_t . ' LIKE %s', 'package_id' ) );
$col_ps     = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $products_t . ' LIKE %s', 'planned_shipping_gbp' ) );
$assert( ! empty( $col_w ) && ! empty( $col_p ) && ! empty( $col_ps ), 'product_shipping_columns' );

$orders_t = SOM_DB::table( 'orders' );
$col_ops  = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $orders_t . ' LIKE %s', 'planned_shipping_gbp' ) );
$assert( ! empty( $col_ops ), 'order_planned_shipping_column' );

$suffix = gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );

$pkg_a = SOM_Shipping_Packages::create(
	array(
		'name'              => 'UP5S2 Mailer ' . $suffix,
		'length_mm'         => 230,
		'width_mm'          => 160,
		'height_mm'         => 20,
		'tare_weight_grams' => 15.5,
		'is_active'         => 1,
		'is_default'        => 1,
	)
);
$assert( ! is_wp_error( $pkg_a ) && (int) $pkg_a > 0, 'package_create_a' );

$pkg_b = SOM_Shipping_Packages::create(
	array(
		'name'              => 'UP5S2 Box ' . $suffix,
		'length_mm'         => 300,
		'width_mm'          => 200,
		'height_mm'         => 100,
		'tare_weight_grams' => 80,
		'is_active'         => 1,
		'is_default'        => 1,
	)
);
$assert( ! is_wp_error( $pkg_b ) && (int) $pkg_b > 0, 'package_create_b_default' );

$default = SOM_Shipping_Packages::get_default();
$assert( $default && (int) $default->id === (int) $pkg_b, 'single_default_is_b' );
$pkg_a_row = SOM_Shipping_Packages::get( (int) $pkg_a );
$assert( $pkg_a_row && empty( $pkg_a_row->is_default ), 'previous_default_cleared' );

$wf = $wpdb->get_var( 'SELECT id FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE is_active = 1 ORDER BY id ASC LIMIT 1' );
$assert( (int) $wf > 0, 'has_workflow' );

$product_id = SOM_Products::create(
	array(
		'name'                 => 'UP5S2 Sellable ' . $suffix,
		'sku'                  => 'UP5S2-' . $suffix,
		'workflow_template_id' => (int) $wf,
		'target_selling_price' => '12.00',
		'weight_grams'         => '40',
		'package_id'           => (int) $pkg_b,
		'planned_shipping_gbp' => '1.55',
		'is_active'            => 1,
		'is_internal'          => 0,
	)
);
$assert( ! is_wp_error( $product_id ), 'product_create_sellable' );
$product_id = (int) $product_id;
$product    = SOM_Products::get( $product_id );
$assert( $product && '40.00' === (string) $product->weight_grams, 'product_weight' );
$assert( $product && (int) $product->package_id === (int) $pkg_b, 'product_package' );
$assert( $product && '1.55' === (string) $product->planned_shipping_gbp, 'product_planned' );

$costing = SOM_Products::recipe_costing( $product_id );
$assert( is_array( $costing ) && null !== $costing['planned_shipping_gbp'] && abs( (float) $costing['planned_shipping_gbp'] - 1.55 ) < 0.001, 'costing_has_planned_shipping' );

$order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up5s2-' . $suffix,
		'buyer_name'        => 'UP5-S2 Tester',
		'shipping_address'  => array(
			'line1'    => '1 Test Street',
			'city'     => 'London',
			'postcode' => 'E1 1AA',
			'country'  => 'GB',
		),
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 2,
				'unit_price' => 12,
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order_external' );
$order_id = (int) $order_id;
$order    = SOM_Orders::get( $order_id );
$assert( $order && '3.10' === (string) $order->planned_shipping_gbp, 'seed_sum_qty_2x1.55' );

SOM_Orders::set_planned_shipping( $order_id, '4.00' );
$order = SOM_Orders::get( $order_id );
$assert( $order && '4.00' === (string) $order->planned_shipping_gbp, 'planned_editable' );

// Re-sync (upsert update path) must not clobber operator-edited planned shipping.
$ext_channel = SOM_Channels::get_by_slug( 'external' );
$assert( $ext_channel, 'has_external_channel' );
if ( $ext_channel ) {
	$ref = new ReflectionClass( 'SOM_Order_Sync' );
	$assert( $ref->hasMethod( 'upsert_order' ), 'upsert_order_method' );
	$method = $ref->getMethod( 'upsert_order' );
	$method->setAccessible( true );
	$upd = $method->invoke(
		null,
		(int) $ext_channel->id,
		array(
			'external_order_id' => 'up5s2-' . $suffix,
			'buyer_name'        => 'UP5-S2 Tester Updated',
			'shipping_address'  => array(
				'line1'    => '1 Test Street',
				'city'     => 'London',
				'postcode' => 'E1 1AA',
				'country'  => 'GB',
			),
			'items'             => array(
				array(
					'product_id' => $product_id,
					'quantity'   => 2,
					'unit_price' => 12,
				),
			),
		),
		false
	);
	$assert( 'updated' === $upd, 'resync_returns_updated' );
	$order = SOM_Orders::get( $order_id );
	$assert( $order && '4.00' === (string) $order->planned_shipping_gbp, 'resync_does_not_clobber' );
}

$del_blocked = SOM_Shipping_Packages::delete( (int) $pkg_b );
$assert( is_wp_error( $del_blocked ) && 'som_package_in_use' === $del_blocked->get_error_code(), 'delete_blocked_when_referenced' );

$internal_id = SOM_Products::create(
	array(
		'name'                 => 'UP5S2 Internal ' . $suffix,
		'workflow_template_id' => (int) $wf,
		'weight_grams'         => '99',
		'package_id'           => (int) $pkg_a,
		'planned_shipping_gbp' => '9.99',
		'is_active'            => 1,
		'is_internal'          => 1,
	)
);
$assert( ! is_wp_error( $internal_id ), 'product_create_internal' );
$internal = SOM_Products::get( (int) $internal_id );
$assert(
	$internal
	&& ( null === $internal->weight_grams || '' === $internal->weight_grams )
	&& ( null === $internal->package_id || '' === $internal->package_id || 0 === (int) $internal->package_id )
	&& ( null === $internal->planned_shipping_gbp || '' === $internal->planned_shipping_gbp ),
	'internal_clears_shipping_fields'
);

if ( class_exists( 'SOM_Production' ) ) {
	$prod_order = SOM_Production::produce( (int) $internal_id, 1 );
	if ( ! is_wp_error( $prod_order ) ) {
		$prod = SOM_Orders::get( (int) $prod_order );
		$assert(
			$prod && ( null === $prod->planned_shipping_gbp || '' === $prod->planned_shipping_gbp ),
			'internal_order_planned_null'
		);
	} else {
		// Produce may fail without recipe; create via sync as fallback.
		$prod_order = SOM_Order_Sync::create_from_external(
			array(
				'channel'           => 'internal',
				'external_order_id' => 'up5s2-int-' . $suffix,
				'buyer_name'        => 'Production',
				'items'             => array(
					array(
						'product_id' => (int) $internal_id,
						'quantity'   => 1,
						'unit_price' => 0,
					),
				),
			)
		);
		$assert( ! is_wp_error( $prod_order ), 'create_internal_order' );
		$prod = SOM_Orders::get( (int) $prod_order );
		$assert(
			$prod && ( null === $prod->planned_shipping_gbp || '' === $prod->planned_shipping_gbp ),
			'internal_order_planned_null'
		);
	}
} else {
	$assert( false, 'internal_order_planned_null' );
}

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'OK' );
$out( 'package_ids', wp_json_encode( array( (int) $pkg_a, (int) $pkg_b ) ) );
$out( 'product_id', (string) $product_id );
$out( 'order_id', (string) $order_id );
