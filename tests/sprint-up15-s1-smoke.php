<?php
/**
 * UP15-S1 smoke: read-only MCP abilities register when MCP is on, and the new reads stay free of secrets.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up15-s1-smoke.php
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

SOM_Settings::update( array( 'mcp_enabled' => true ) );
$assert( true === SOM_Abilities::is_enabled(), 'mcp_enabled' );

$ability_names = array(
	'order-machine/get-workflow-templates',
	'order-machine/get-budgets',
	'order-machine/get-shipping-packages',
	'order-machine/get-channels',
	'order-machine/get-analytics',
);

$registered = static function ( $name ) {
	if ( ! function_exists( 'wp_get_ability' ) ) {
		return null;
	}
	$ability = wp_get_ability( $name );
	return ( $ability && ! is_wp_error( $ability ) ) ? $ability : null;
};

/*
 * wp_register_ability() only succeeds during wp_abilities_api_init. If MCP was
 * off when that hook first ran, register again while the hook name is current.
 */
if ( function_exists( 'wp_register_ability' ) && ! $registered( 'order-machine/get-workflow-templates' ) ) {
	global $wp_current_filter;
	if ( ! is_array( $wp_current_filter ) ) {
		$wp_current_filter = array();
	}
	$wp_current_filter[] = 'wp_abilities_api_categories_init';
	SOM_Abilities::register_category();
	array_pop( $wp_current_filter );

	$wp_current_filter[] = 'wp_abilities_api_init';
	SOM_Abilities::register_abilities();
	array_pop( $wp_current_filter );
}

if ( function_exists( 'wp_get_ability' ) ) {
	foreach ( $ability_names as $name ) {
		$ability = $registered( $name );
		$meta    = $ability ? $ability->get_meta() : array();
		$mcp     = ( is_array( $meta ) && isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) ) ? $meta['mcp'] : array();
		$assert(
			$ability
			&& SOM_Abilities::CATEGORY === $ability->get_category()
			&& ! empty( $mcp['public'] )
			&& isset( $mcp['type'] ) && 'tool' === $mcp['type'],
			'registered_' . $name
		);
	}
} else {
	$out( 'wp_get_ability', 'absent' );
}

$secret_keys = array( 'credentials', 'script_config', 'command_template', 'client_secret', 'api_key' );
$secret_hits = array();
$walk_secrets = static function ( $value ) use ( &$walk_secrets, $secret_keys, &$secret_hits ) {
	if ( is_object( $value ) ) {
		$value = get_object_vars( $value );
	}
	if ( ! is_array( $value ) ) {
		return;
	}
	foreach ( $value as $key => $item ) {
		if ( is_string( $key ) && in_array( $key, $secret_keys, true ) ) {
			$secret_hits[] = $key;
		}
		$walk_secrets( $item );
	}
};

$templates = SOM_Abilities::get_workflow_templates( array() );
$assert( is_array( $templates ) && isset( $templates['templates'], $templates['total'] ), 'workflow_templates_payload' );

$budgets = SOM_Abilities::get_budgets( array() );
$assert( is_array( $budgets ) && isset( $budgets['budgets'], $budgets['total'] ), 'budgets_payload' );

$packages = SOM_Abilities::get_shipping_packages( array() );
$assert( is_array( $packages ) && isset( $packages['packages'], $packages['total'] ), 'shipping_packages_payload' );

$channels = SOM_Abilities::get_channels( array() );
$assert(
	is_array( $channels ) && isset( $channels['channels'] ) && count( $channels['channels'] ) >= 1,
	'channels_payload'
);

$analytics = SOM_Abilities::get_analytics( array() );
$assert(
	is_array( $analytics ) && isset( $analytics['labels'], $analytics['timing'], $analytics['filters'] ),
	'analytics_payload'
);

$secret_hits = array();
$walk_secrets( $templates );
$walk_secrets( $budgets );
$walk_secrets( $packages );
$walk_secrets( $channels );
$walk_secrets( $analytics );
$assert( array() === $secret_hits, 'payloads_omit_secret_keys' );
if ( $secret_hits ) {
	$out( 'secret_keys_found', implode( ',', $secret_hits ) );
}

$probe = SOM_Orders::query( array( 'per_page' => 1 ) );
if ( (int) $probe['total'] < 2 ) {
	SOM_Seed::maybe_seed_catalogue();
	$settings = SOM_Settings::get();
	if ( '' === (string) $settings['api_key'] ) {
		SOM_Settings::update( array( 'api_key' => 'up15s1-test-key' ) );
		$settings = SOM_Settings::get();
	}
	$api_key    = (string) $settings['api_key'];
	$product_id = (int) get_option( 'som_seed_product_id', 0 );
	if ( $product_id > 0 ) {
		$seed_row = SOM_Products::get( $product_id );
		if ( ! $seed_row || empty( $seed_row->is_active ) ) {
			$product_id = 0;
		}
	}
	if ( $product_id < 1 ) {
		$product_id = (int) $GLOBALS['wpdb']->get_var(
			'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_active = 1 ORDER BY id ASC LIMIT 1'
		);
	}
	$assert( $product_id > 0, 'order_fixture_product' );

	$create_order = static function ( $external_id ) use ( $api_key, $product_id ) {
		$req = new WP_REST_Request( 'POST', '/som/v1/orders' );
		$req->set_header( 'x-som-api-key', $api_key );
		$req->set_header( 'content-type', 'application/json' );
		$req->set_body(
			wp_json_encode(
				array(
					'external_order_id' => $external_id,
					'buyer_name'        => 'UP15 S1 Buyer',
					'shipping_address'  => array(
						'line1'    => '1 Test Street',
						'city'     => 'London',
						'postcode' => 'E1 1AA',
						'country'  => 'GB',
					),
					'items'             => array(
						array(
							'product_id'           => $product_id,
							'quantity'             => 1,
							'personalisation_text' => 'UP15 S1',
						),
					),
				)
			)
		);
		$created = rest_do_request( $req );
		$data    = $created->get_data();
		return is_array( $data ) && ! empty( $data['ok'] ) && ! empty( $data['order_id'] );
	};

	$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );
	$assert( $create_order( 'up15-s1-a-' . $suffix ), 'create_order_a' );
	$assert( $create_order( 'up15-s1-b-' . $suffix ), 'create_order_b' );
	$probe = SOM_Orders::query( array( 'per_page' => 1 ) );
}

$listed = SOM_Abilities::get_orders( array( 'per_page' => 1 ) );
$assert(
	is_array( $listed ) && isset( $listed['orders'] ) && count( $listed['orders'] ) <= 1,
	'orders_per_page_at_most_one'
);
if ( (int) $probe['total'] >= 2 ) {
	$assert(
		is_array( $listed ) && isset( $listed['orders'] ) && 1 === count( $listed['orders'] ),
		'orders_per_page_exactly_one'
	);
}

$missing_workflow = SOM_Abilities::get_workflow_template( array( 'workflow_template_id' => 0 ) );
$assert( is_wp_error( $missing_workflow ), 'workflow_template_missing' );

$missing_budget = SOM_Abilities::get_budget( array( 'budget_id' => 0 ) );
$assert( is_wp_error( $missing_budget ), 'budget_missing' );

$missing_package = SOM_Abilities::get_shipping_package( array( 'shipping_package_id' => 0 ) );
$assert( is_wp_error( $missing_package ), 'shipping_package_missing' );

echo $fail ? "FAIL\n" : "PASS\n";
exit( $fail ? 1 : 0 );
