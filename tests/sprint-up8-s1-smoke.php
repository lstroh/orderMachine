<?php
/**
 * UP8-S1 smoke: tray schema, site max, assign/release, double-book reject, multi-tray, O5 max lower.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up8-s1-smoke.php
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
$assert( class_exists( 'SOM_Trays' ), 'class_SOM_Trays' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.19.0', '>=' ), 'db_gte_1.19.0' );

$templates_t = SOM_DB::table( 'workflow_templates' );
$uses_col    = $wpdb->get_row( "SHOW COLUMNS FROM {$templates_t} LIKE 'uses_trays'" );
$assert( ! empty( $uses_col ), 'column_workflow_templates_uses_trays' );

$trays_t = SOM_DB::table( 'tray_assignments' );
$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $trays_t ) );
$assert( $exists === $trays_t, 'table_tray_assignments' );

$settings_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/settings.php' );
$assert( is_string( $settings_src ) && false !== strpos( $settings_src, 'som_tray_max' ), 'settings_tray_max_field' );

$editor_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$assert( is_string( $editor_src ) && false !== strpos( $editor_src, 'som_workflow_uses_trays' ), 'editor_uses_trays_checkbox' );

$detail_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'id="som-trays"' ), 'order_detail_trays_panel' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'som_assign_trays' ), 'order_detail_assign_form' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'som_release_trays' ), 'order_detail_release_form' );

$suffix = wp_generate_password( 4, false, false );

// Ensure a known pool size for the run.
$prev_max = SOM_Trays::get_max();
$set_max  = SOM_Trays::set_max( 10 );
$assert( true === $set_max, 'set_tray_max_10' );
$assert( 10 === SOM_Trays::get_max(), 'get_tray_max_10' );

// Force-release leftover holds from prior smoke runs (keep≥1 blocked previous S1 teardown).
$held_map = SOM_Trays::held_tray_numbers();
foreach ( array_unique( array_map( 'intval', array_values( $held_map ) ) ) as $held_oid ) {
	if ( $held_oid > 0 ) {
		SOM_Trays::release( $held_oid, array(), SOM_Trays::REASON_ORDER_COMPLETE );
	}
}
$assert( array() === SOM_Trays::held_tray_numbers(), 'preflight_no_held_trays' );

$range_err = SOM_Trays::set_max( 0 );
$assert( is_wp_error( $range_err ) && 'som_tray_max_range' === $range_err->get_error_code(), 'max_rejects_below_1' );
$range_err2 = SOM_Trays::set_max( 21 );
$assert( is_wp_error( $range_err2 ) && 'som_tray_max_range' === $range_err2->get_error_code(), 'max_rejects_above_20' );

// Template uses_trays checkbox persistence.
$wf = SOM_Workflows::create(
	array(
		'name'       => 'UP8-S1 make ' . $suffix,
		'kind'       => 'make',
		'uses_trays' => 1,
		'is_active'  => 1,
	)
);
$assert( ! is_wp_error( $wf ), 'create_make_template' );
$wf_id = (int) $wf;
$tpl   = SOM_Workflows::get( $wf_id );
$assert( $tpl && ! empty( $tpl->uses_trays ), 'template_uses_trays_on_create' );
$upd = SOM_Workflows::update( $wf_id, array( 'uses_trays' => 0 ) );
$assert( true === $upd, 'update_uses_trays_off' );
$tpl = SOM_Workflows::get( $wf_id );
$assert( $tpl && empty( $tpl->uses_trays ), 'template_uses_trays_off' );
$upd = SOM_Workflows::update( $wf_id, array( 'uses_trays' => 1 ) );
$assert( true === $upd && ! empty( SOM_Workflows::get( $wf_id )->uses_trays ), 'template_uses_trays_on_again' );

$product = SOM_Products::create(
	array(
		'name'                 => 'UP8-S1 product ' . $suffix,
		'sku'                  => 'UP8S1-' . $suffix,
		'workflow_template_id' => $wf_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$order_a = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up8s1a-' . $suffix,
		'buyer_name'        => 'UP8-S1 A',
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
				'unit_price' => 5,
			),
		),
	)
);
$assert( ! is_wp_error( $order_a ), 'create_order_a' );
$order_a = (int) $order_a;

$order_b = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up8s1b-' . $suffix,
		'buyer_name'        => 'UP8-S1 B',
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
				'unit_price' => 5,
			),
		),
	)
);
$assert( ! is_wp_error( $order_b ), 'create_order_b' );
$order_b = (int) $order_b;

// Assign free trays (multi) to order A — pick from the free pool (not hardcoded numbers).
$free_before = SOM_Trays::free_tray_numbers();
$assert( count( $free_before ) >= 4, 'enough_free_trays' );
$a_trays = array( (int) $free_before[0], (int) $free_before[1], (int) $free_before[2] );
sort( $a_trays, SORT_NUMERIC );
$tray_for_dup = $a_trays[0];
$tray_to_release = $a_trays[1];
$assign = SOM_Trays::assign( $order_a, $a_trays );
$assert( true === $assign, 'assign_multi_trays' );
$held_a = SOM_Trays::held_numbers_for_order( $order_a );
$assert( $a_trays === $held_a, 'order_a_holds_1_3_5' );
$assert( ! in_array( $tray_for_dup, SOM_Trays::free_tray_numbers(), true ), 'tray_1_not_free' );
$assert( SOM_Trays::is_held( $tray_for_dup ), 'tray_1_is_held' );

// Double-book reject.
$dup = SOM_Trays::assign( $order_b, array( $tray_for_dup ) );
$assert( is_wp_error( $dup ) && 'som_tray_in_use' === $dup->get_error_code(), 'reject_double_book' );
$assert( array() === SOM_Trays::held_numbers_for_order( $order_b ), 'order_b_still_empty' );

// Assign another free tray to order B.
$free_for_b = SOM_Trays::free_tray_numbers();
$assert( count( $free_for_b ) >= 1, 'free_tray_for_order_b' );
$tray_b    = (int) $free_for_b[0];
$assign_b  = SOM_Trays::assign( $order_b, array( $tray_b ) );
$assert( true === $assign_b, 'assign_order_b_tray_2' );
$assert( array( $tray_b ) === SOM_Trays::held_numbers_for_order( $order_b ), 'order_b_holds_2' );

// Manual release one tray from A; released row retained.
$release = SOM_Trays::release( $order_a, array( $tray_to_release ), SOM_Trays::REASON_MANUAL );
$assert( true === $release, 'manual_release_tray_3' );
$held_after = array_values( array_diff( $a_trays, array( $tray_to_release ) ) );
sort( $held_after, SORT_NUMERIC );
$assert( $held_after === SOM_Trays::held_numbers_for_order( $order_a ), 'order_a_after_release' );
$assert( in_array( $tray_to_release, SOM_Trays::free_tray_numbers(), true ), 'tray_3_free_again' );

$released_row = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$trays_t}
		WHERE order_id = %d AND tray_number = %d AND status = %s
		ORDER BY id DESC LIMIT 1",
		$order_a,
		$tray_to_release,
		SOM_Trays::STATUS_RELEASED
	)
);
$assert( $released_row && SOM_Trays::REASON_MANUAL === (string) $released_row->release_reason, 'released_row_retained' );
$assert( ! empty( $released_row->released_at ), 'released_at_set' );

// O5: hold the current max tray, then block lowering max below it.
$high_tray = SOM_Trays::get_max();
$assert( in_array( $high_tray, SOM_Trays::free_tray_numbers(), true ), 'high_tray_free_for_o5' );
$assign_high = SOM_Trays::assign( $order_a, array( $high_tray ) );
$assert( true === $assign_high, 'assign_tray_10' );
$block_max = SOM_Trays::set_max( $high_tray - 1 );
$assert( is_wp_error( $block_max ) && 'som_tray_max_held' === $block_max->get_error_code(), 'o5_block_lower_max' );
$assert( $high_tray === SOM_Trays::get_max(), 'max_unchanged_after_block' );

$release_high = SOM_Trays::release( $order_a, array( $high_tray ), SOM_Trays::REASON_MANUAL );
$assert( true === $release_high, 'release_tray_10' );
$ok_lower = SOM_Trays::set_max( $high_tray - 1 );
$assert( true === $ok_lower && ( $high_tray - 1 ) === SOM_Trays::get_max(), 'lower_max_after_release' );

// Restore max, then force-release leftover holds (manual release keeps ≥1 while uses_trays + open).
SOM_Trays::set_max( 10 );
$clean_a = SOM_Trays::release( $order_a, array(), SOM_Trays::REASON_ORDER_COMPLETE );
$clean_b = SOM_Trays::release( $order_b, array(), SOM_Trays::REASON_ORDER_COMPLETE );
$assert( true === $clean_a || ( is_wp_error( $clean_a ) && 'som_tray_none_held' === $clean_a->get_error_code() ), 'cleanup_release_order_a' );
$assert( true === $clean_b || ( is_wp_error( $clean_b ) && 'som_tray_none_held' === $clean_b->get_error_code() ), 'cleanup_release_order_b' );
$assert( array() === SOM_Trays::held_numbers_for_order( $order_a ), 'cleanup_order_a_no_holds' );
$assert( array() === SOM_Trays::held_numbers_for_order( $order_b ), 'cleanup_order_b_no_holds' );

// Restore prior site max when valid.
if ( $prev_max >= SOM_Trays::MIN_MAX && $prev_max <= SOM_Trays::MAX_MAX ) {
	SOM_Trays::set_max( $prev_max );
} else {
	SOM_Trays::set_max( SOM_Trays::DEFAULT_MAX );
}

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "UP8-S1 smoke OK\n";
