<?php
/**
 * Sprint U1 smoke: schema 1.4.0, batch groups, thank-you convert, value backfill.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-u1-smoke.php
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
SOM_Batch_Groups::ensure_rows();
SOM_Batch_Groups::convert_thankyou_steps();

$out( 'plugin', SOM_VERSION );
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( version_compare( SOM_VERSION, '0.12.0', '>=' ), 'SOM_VERSION_gte_0.12.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.4.0', '>=' ), 'som_db_version_gte_1.4.0' );

$expected_tables = array(
	'suppliers',
	'purchase_orders',
	'purchase_order_items',
	'workflow_material_goals',
	'batch_groups',
	'step_batches',
	'step_batch_items',
);
foreach ( $expected_tables as $suffix ) {
	$table  = SOM_DB::table( $suffix );
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	$assert( $exists === $table, 'table_' . $suffix );
}

$mat_cols = array( 'total_value_on_hand', 'preferred_supplier_id' );
foreach ( $mat_cols as $col ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( "SHOW COLUMNS FROM " . SOM_DB::table( 'materials' ) . " LIKE '{$col}'" );
	$assert( ! empty( $row ), 'materials_' . $col );
}

$log_cols = array( 'purchase_order_item_id', 'unit_cost_at_time', 'value_change' );
foreach ( $log_cols as $col ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( "SHOW COLUMNS FROM " . SOM_DB::table( 'material_stock_log' ) . " LIKE '{$col}'" );
	$assert( ! empty( $row ), 'stock_log_' . $col );
}

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$prod_col = $wpdb->get_row( "SHOW COLUMNS FROM " . SOM_DB::table( 'products' ) . " LIKE 'target_selling_price'" );
$assert( ! empty( $prod_col ), 'products_target_selling_price' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$step_col = $wpdb->get_row( "SHOW COLUMNS FROM " . SOM_DB::table( 'workflow_steps' ) . " LIKE 'batch_group_id'" );
$assert( ! empty( $step_col ), 'workflow_steps_batch_group_id' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$poi_col = $wpdb->get_row( "SHOW COLUMNS FROM " . SOM_DB::table( 'purchase_order_items' ) . " LIKE 'allocated_other_cost'" );
$assert( ! empty( $poi_col ), 'poi_allocated_other_cost' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$status_col = $wpdb->get_row( "SHOW COLUMNS FROM " . SOM_DB::table( 'order_step_progress' ) . " LIKE 'status'" );
$status_ok  = $status_col && false !== strpos( (string) $status_col->Type, 'waiting_batch' );
$assert( $status_ok, 'progress_status_waiting_batch' );

$groups = SOM_Batch_Groups::list_all();
$by_key = array();
foreach ( $groups as $g ) {
	$by_key[ $g->key ] = $g;
}
$assert( isset( $by_key['thank_you_card'] ), 'group_thank_you_card' );
$assert( isset( $by_key['shipping_label'] ), 'group_shipping_label' );
if ( isset( $by_key['thank_you_card'] ) ) {
	$assert( (int) $by_key['thank_you_card']->batch_size === 4, 'thank_you_batch_size_4' );
	$assert( $by_key['thank_you_card']->action_type === 'script', 'thank_you_action_script' );
}
if ( isset( $by_key['shipping_label'] ) ) {
	$assert( (int) $by_key['shipping_label']->batch_size === 4, 'shipping_batch_size_4' );
	$assert( $by_key['shipping_label']->action_type === 'manual_confirm', 'shipping_action_manual' );
}

$thankyou_group_id = isset( $by_key['thank_you_card'] ) ? (int) $by_key['thank_you_card']->id : 0;
$converted         = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE batch_group_id = %d',
		$thankyou_group_id
	)
);
$leftover_script = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM " . SOM_DB::table( 'workflow_steps' ) . " WHERE script_config LIKE '%run_thankyou_card_script%'"
);
$assert( $converted >= 1, 'thankyou_steps_converted' );
$assert( 0 === $leftover_script, 'no_per_order_thankyou_script' );

$materials_t = SOM_DB::table( 'materials' );
$probe_id    = (int) $wpdb->get_var( "SELECT id FROM {$materials_t} ORDER BY id ASC LIMIT 1" );
	$assert( $probe_id > 0, 'material_probe_exists' );
if ( $probe_id > 0 ) {
	$wpdb->update(
		$materials_t,
		array(
			'current_stock'       => 10,
			'unit_cost'           => 1.5,
			'total_value_on_hand' => 0,
		),
		array( 'id' => $probe_id ),
		array( '%f', '%f', '%f' ),
		array( '%d' )
	);
	// Call backfill path without full dbDelta noise: zeroed row + create_tables.
	SOM_DB::create_tables();
	$probe = $wpdb->get_row(
		$wpdb->prepare( "SELECT current_stock, unit_cost, total_value_on_hand FROM {$materials_t} WHERE id = %d", $probe_id )
	);
	$expected = 15.0;
	$actual   = $probe ? round( (float) $probe->total_value_on_hand, 4 ) : -1;
	$assert( abs( $expected - $actual ) < 0.0001, 'material_total_value_backfill' );
}

// group_key column (not reserved `key`) for dbDelta compatibility.
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$gk = $wpdb->get_row( "SHOW COLUMNS FROM " . SOM_DB::table( 'batch_groups' ) . " LIKE 'group_key'" );
$assert( ! empty( $gk ), 'batch_groups_group_key_column' );

$sup_id = SOM_Suppliers::create(
	array(
		'name'    => 'U1 Smoke Supplier',
		'website' => 'https://example.com',
	)
);
$assert( ! is_wp_error( $sup_id ) && (int) $sup_id > 0, 'suppliers_create' );
if ( ! is_wp_error( $sup_id ) ) {
	$got = SOM_Suppliers::get( (int) $sup_id );
	$assert( $got && 'U1 Smoke Supplier' === $got->name, 'suppliers_get' );
}

if ( $fail > 0 ) {
	echo "FAIL: {$fail} assertion(s)\n";
	exit( 1 );
}

echo "PASS — Sprint U1 smoke\n";
exit( 0 );
