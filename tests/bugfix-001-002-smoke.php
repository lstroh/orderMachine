<?php
/**
 * Smoke: BUG-001 current-step filter + BUG-002 Add material detail_url.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/bugfix-001-002-smoke.php
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
SOM_Seed::maybe_seed_catalogue();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

// --- BUG-002: Add material URL must keep material_id=new ---
$new_url = SOM_Materials::detail_url( 'new' );
$out( 'material_new_url', $new_url );
$assert( false !== strpos( $new_url, 'material_id=new' ), 'bug002_detail_url_new' );
$assert( false === strpos( $new_url, 'material_id=0' ), 'bug002_not_cast_to_zero' );

$existing_id = (int) $GLOBALS['wpdb']->get_var(
	'SELECT id FROM ' . SOM_DB::table( 'materials' ) . ' ORDER BY id ASC LIMIT 1'
);
if ( $existing_id > 0 ) {
	$edit_url = SOM_Materials::detail_url( $existing_id );
	$assert( false !== strpos( $edit_url, 'material_id=' . $existing_id ), 'bug002_detail_url_existing' );
}

// --- BUG-001: Current step filter ---
$step_names = SOM_Orders::step_name_options();
$out( 'step_name_options', $step_names );
$assert( is_array( $step_names ) && count( $step_names ) > 0, 'bug001_step_options_nonempty' );
$assert( in_array( 'Print', $step_names, true ), 'bug001_print_in_options' );

// Ensure at least one order on Print via seed + sync (or create external).
$settings = SOM_Settings::get();
if ( '' === (string) $settings['api_key'] ) {
	SOM_Settings::update( array( 'api_key' => 'bugfix-smoke-key' ) );
	$settings = SOM_Settings::get();
}
$api_key = (string) $settings['api_key'];

$product_id = (int) $GLOBALS['wpdb']->get_var(
	"SELECT id FROM " . SOM_DB::table( 'products' ) . " WHERE sku = 'BIN-SET-4PK' AND is_active = 1 LIMIT 1"
);
if ( $product_id < 1 ) {
	$product_id = (int) $GLOBALS['wpdb']->get_var(
		'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE is_active = 1 ORDER BY id ASC LIMIT 1'
	);
}
$assert( $product_id > 0, 'seed_product' );

$print_step_id = (int) $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		'SELECT ws.id FROM ' . SOM_DB::table( 'workflow_steps' ) . ' ws
		INNER JOIN ' . SOM_DB::table( 'products' ) . ' p ON p.workflow_template_id = ws.workflow_template_id
		WHERE p.id = %d AND ws.name = %s
		ORDER BY ws.step_order ASC LIMIT 1',
		$product_id,
		'Print'
	)
);
$assert( $print_step_id > 0, 'print_step_for_product' );

$external_id = 'bugfix-step-' . gmdate( 'YmdHis' );
$req         = new WP_REST_Request( 'POST', '/som/v1/orders' );
$req->set_header( 'x-som-api-key', $api_key );
$req->set_header( 'content-type', 'application/json' );
$req->set_body(
	wp_json_encode(
		array(
			'external_order_id' => $external_id,
			'buyer_name'        => 'Bugfix Step Buyer',
			'shipping_address'  => array(
				'full_name' => 'Bugfix Step Buyer',
				'line1'     => '1 Test St',
				'city'      => 'London',
				'postcode'  => 'E1 1AA',
				'country'   => 'GB',
			),
			'items'             => array(
				array(
					'product_id' => $product_id,
					'quantity'   => 1,
					'title'      => 'Bugfix item',
				),
			),
		)
	)
);
$res = rest_do_request( $req );
$assert( 200 === (int) $res->get_status() || 201 === (int) $res->get_status(), 'create_order_for_step_filter' );

$data     = $res->get_data();
$order_id = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
if ( $order_id < 1 && isset( $data['id'] ) ) {
	$order_id = (int) $data['id'];
}
$out( 'created_order_id', $order_id );
$assert( $order_id > 0, 'created_order_id_positive' );

$order_row = $GLOBALS['wpdb']->get_row(
	$GLOBALS['wpdb']->prepare(
		'SELECT id, current_step_id FROM ' . SOM_DB::table( 'orders' ) . ' WHERE id = %d',
		$order_id
	)
);
$out( 'order_current_step_id', $order_row ? (int) $order_row->current_step_id : 0 );
$assert( $order_row && (int) $order_row->current_step_id === $print_step_id, 'order_on_print_step' );

$filtered = SOM_Orders::query(
	array(
		'current_step' => 'Print',
		'per_page'     => 100,
	)
);
$ids = array_map(
	static function ( $o ) {
		return (int) $o->id;
	},
	$filtered['orders']
);
$out( 'print_filter_count', (int) $filtered['total'] );
$assert( in_array( $order_id, $ids, true ), 'bug001_print_filter_includes_order' );

$dry_filtered = SOM_Orders::query(
	array(
		'current_step' => 'Dry',
		'per_page'     => 100,
	)
);
$dry_ids = array_map(
	static function ( $o ) {
		return (int) $o->id;
	},
	$dry_filtered['orders']
);
$assert( ! in_array( $order_id, $dry_ids, true ), 'bug001_dry_filter_excludes_print_order' );

$combined = SOM_Orders::query(
	array(
		'status'       => 'open',
		'current_step' => 'Print',
		'per_page'     => 100,
	)
);
$combined_ids = array_map(
	static function ( $o ) {
		return (int) $o->id;
	},
	$combined['orders']
);
$assert( in_array( $order_id, $combined_ids, true ), 'bug001_open_plus_print_filter' );

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAIL (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'PASS — BUG-001/002 smoke' );
