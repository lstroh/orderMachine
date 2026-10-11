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

// Clear leftover held trays from prior UP8-S1/S2 smoke runs (hardcoded tray numbers need a clean pool).
$prior_held = $wpdb->get_col(
	"SELECT DISTINCT ta.order_id
	FROM " . SOM_DB::table( 'tray_assignments' ) . " ta
	INNER JOIN " . SOM_DB::table( 'orders' ) . " o ON o.id = ta.order_id
	WHERE ta.status = 'held'
		AND ( o.external_order_id LIKE 'up8s1%' OR o.external_order_id LIKE 'up8s2%' )"
);
if ( is_array( $prior_held ) ) {
	foreach ( $prior_held as $prior_oid ) {
		SOM_Trays::release( (int) $prior_oid, array(), SOM_Trays::REASON_ORDER_COMPLETE );
	}
}
$out( 'prior_smoke_trays_cleared', (string) count( is_array( $prior_held ) ? $prior_held : array() ) );

// Ensure a known pool size for the run.
$prev_max = SOM_Trays::get_max();
$set_max  = SOM_Trays::set_max( 10 );
$assert( true === $set_max, 'set_tray_max_10' );
$assert( 10 === SOM_Trays::get_max(), 'get_tray_max_10' );

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

// Assign free trays (multi) to order A.
$free_before = SOM_Trays::free_tray_numbers();
$assert( count( $free_before ) >= 3, 'enough_free_trays' );
$assign = SOM_Trays::assign( $order_a, array( 1, 3, 5 ) );
$assert( true === $assign, 'assign_multi_trays' );
$held_a = SOM_Trays::held_numbers_for_order( $order_a );
$assert( array( 1, 3, 5 ) === $held_a, 'order_a_holds_1_3_5' );
$assert( ! in_array( 1, SOM_Trays::free_tray_numbers(), true ), 'tray_1_not_free' );
$assert( SOM_Trays::is_held( 1 ), 'tray_1_is_held' );

// Double-book reject.
$dup = SOM_Trays::assign( $order_b, array( 1 ) );
$assert( is_wp_error( $dup ) && 'som_tray_in_use' === $dup->get_error_code(), 'reject_double_book' );
$assert( array() === SOM_Trays::held_numbers_for_order( $order_b ), 'order_b_still_empty' );

// Assign another free tray to order B.
$assign_b = SOM_Trays::assign( $order_b, array( 2 ) );
$assert( true === $assign_b, 'assign_order_b_tray_2' );
$assert( array( 2 ) === SOM_Trays::held_numbers_for_order( $order_b ), 'order_b_holds_2' );

// Manual release one tray from A; released row retained.
$release = SOM_Trays::release( $order_a, array( 3 ), SOM_Trays::REASON_MANUAL );
$assert( true === $release, 'manual_release_tray_3' );
$assert( array( 1, 5 ) === SOM_Trays::held_numbers_for_order( $order_a ), 'order_a_after_release' );
$assert( in_array( 3, SOM_Trays::free_tray_numbers(), true ), 'tray_3_free_again' );

$released_row = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$trays_t}
		WHERE order_id = %d AND tray_number = %d AND status = %s
		ORDER BY id DESC LIMIT 1",
		$order_a,
		3,
		SOM_Trays::STATUS_RELEASED
	)
);
$assert( $released_row && SOM_Trays::REASON_MANUAL === (string) $released_row->release_reason, 'released_row_retained' );
$assert( ! empty( $released_row->released_at ), 'released_at_set' );

// O5: hold a high tray, then block lowering max below it.
$assign_high = SOM_Trays::assign( $order_a, array( 10 ) );
$assert( true === $assign_high, 'assign_tray_10' );
$block_max = SOM_Trays::set_max( 9 );
$assert( is_wp_error( $block_max ) && 'som_tray_max_held' === $block_max->get_error_code(), 'o5_block_lower_max' );
$assert( 10 === SOM_Trays::get_max(), 'max_unchanged_after_block' );

$release_high = SOM_Trays::release( $order_a, array( 10 ), SOM_Trays::REASON_MANUAL );
$assert( true === $release_high, 'release_tray_10' );
$ok_lower = SOM_Trays::set_max( 9 );
$assert( true === $ok_lower && 9 === SOM_Trays::get_max(), 'lower_max_after_release' );

// Restore max, then free held trays. Use order_complete reason so O2 keep-≥1 does not block cleanup
// (these smoke orders use uses_trays make templates and are still open).
SOM_Trays::set_max( 10 );
SOM_Trays::release( $order_a, array(), SOM_Trays::REASON_ORDER_COMPLETE );
SOM_Trays::release( $order_b, array(), SOM_Trays::REASON_ORDER_COMPLETE );

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
