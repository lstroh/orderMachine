<?php
/**
 * UP12-S2 smoke: Produce N order detail shows Make controls; customer internal lines stay pack-ready.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s2-smoke.php
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
SOM_Production::init();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );

$detail_view = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( is_string( $detail_view ) && false !== strpos( $detail_view, 'detail_shows_make_controls' ), 'detail_uses_make_controls_helper' );
$assert( is_string( $detail_view ) && false !== strpos( $detail_view, 'detail_make_badge' ), 'detail_uses_badge_helper' );
$assert( is_string( $detail_view ) && false !== strpos( $detail_view, 'progress_steps_all_done' ), 'detail_uses_step_rows_for_complete' );
$assert( is_string( $detail_view ) && false === strpos( $detail_view, '$is_internal_l || empty( $line_progress )' ), 'detail_does_not_hide_every_internal_line' );
$assert( is_string( $detail_view ) && false !== strpos( $detail_view, 'Pack-ready (internal)' ), 'detail_still_has_pack_ready_badge' );
$assert( is_string( $detail_view ) && false !== strpos( $detail_view, 'No make workflow' ), 'detail_keeps_unassigned_notice' );
$assert( is_string( $detail_view ) && false !== strpos( $detail_view, 'som_mark_step_done' ), 'detail_still_has_mark_done' );

// Branch decisions without a database row.
$assert( SOM_Item_Make::detail_shows_make_controls( 'internal', true, true ), 'helper_produce_n_shows_controls' );
$assert( '' === SOM_Item_Make::detail_make_badge( 'internal', true, true, false ), 'helper_produce_n_mid_job_not_pack_ready' );
$assert( 'ready' === SOM_Item_Make::detail_make_badge( 'internal', true, true, true ), 'helper_produce_n_complete_is_ready' );
$assert( 'no_progress' === SOM_Item_Make::detail_make_badge( 'internal', true, false, false ), 'helper_produce_n_gap_is_no_progress' );
$assert( ! SOM_Item_Make::detail_shows_make_controls( 'external', true, false ), 'helper_customer_internal_hides_controls' );
$assert( ! SOM_Item_Make::detail_shows_make_controls( 'external', true, true ), 'helper_customer_internal_hides_even_with_progress' );
$assert( 'pack_ready_internal' === SOM_Item_Make::detail_make_badge( 'external', true, false, false ), 'helper_customer_internal_pack_ready' );
$assert( 'pack_ready_internal' === SOM_Item_Make::detail_make_badge( 'ebay', true, true, false ), 'helper_customer_internal_pack_ready_with_progress' );
$assert( SOM_Item_Make::detail_shows_make_controls( 'ebay', false, true ), 'helper_sellable_shows_controls' );
$assert( 'ready' === SOM_Item_Make::detail_make_badge( 'ebay', false, true, true ), 'helper_sellable_complete_is_ready' );
$assert( ! SOM_Item_Make::progress_steps_all_done( array() ), 'empty_progress_not_complete' );
$assert(
	SOM_Item_Make::progress_steps_all_done(
		array(
			(object) array( 'status' => 'done' ),
			(object) array( 'status' => 'done' ),
		)
	),
	'all_done_progress'
);
$assert(
	! SOM_Item_Make::progress_steps_all_done(
		array(
			(object) array( 'status' => 'done' ),
			(object) array( 'status' => 'in_progress' ),
		)
	),
	'mid_job_progress_not_complete'
);

$suffix = wp_generate_password( 4, false, false );

$input_id = SOM_Materials::create(
	array(
		'name'      => 'UP12-S2 Input ' . $suffix,
		'unit'      => 'sheet',
		'unit_cost' => 1,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $input_id ), 'create_input_material' );
$input_id = is_wp_error( $input_id ) ? 0 : (int) $input_id;

if ( $input_id > 0 ) {
	$restock = SOM_Materials::adjust_stock(
		$input_id,
		20,
		array(
			'reason'            => 'restock',
			'unit_cost_at_time' => 1,
			'value_change'      => 20,
			'sync_unit_cost'    => true,
		)
	);
	$assert( ! is_wp_error( $restock ), 'restock_input' );
}

$print_name = 'UP12-S2 Print ' . $suffix;
$cut_name   = 'UP12-S2 Cut ' . $suffix;
$wf         = SOM_Workflows::create(
	array(
		'name'       => 'UP12-S2 Make ' . $suffix,
		'kind'       => 'make',
		'is_active'  => 1,
		'uses_trays' => 0,
	)
);
$assert( ! is_wp_error( $wf ), 'create_make_wf' );
$wf_id = is_wp_error( $wf ) ? 0 : (int) $wf;
if ( $wf_id > 0 ) {
	$saved = SOM_Workflows::save_steps(
		$wf_id,
		array(
			array(
				'name'                    => $print_name,
				'requires_manual_confirm' => 1,
			),
			array(
				'name'                    => $cut_name,
				'requires_manual_confirm' => 1,
			),
		)
	);
	$assert( true === $saved, 'save_make_steps' );
}

$product_id = 0;
if ( $wf_id > 0 ) {
	$created = SOM_Products::create(
		array(
			'name'                 => 'UP12-S2 Internal ' . $suffix,
			'sku'                  => 'UP12S2-' . $suffix,
			'workflow_template_id' => $wf_id,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	);
	$assert( ! is_wp_error( $created ), 'create_internal_product' );
	$product_id = is_wp_error( $created ) ? 0 : (int) $created;
}
if ( $product_id > 0 && $input_id > 0 ) {
	$recipe = SOM_Products::save_recipe(
		$product_id,
		array(
			array(
				'material_id'       => $input_id,
				'quantity_per_unit' => 1,
			),
		)
	);
	$assert( ! is_wp_error( $recipe ), 'save_recipe' );
}

// --- Produce N: detail path shows controls; no false unassigned notice ---
$prod_id = 0;
if ( $product_id > 0 ) {
	$produced = SOM_Production::produce( $product_id, 1 );
	$assert( ! is_wp_error( $produced ), 'produce_n_ok' );
	$prod_id = is_wp_error( $produced ) ? 0 : (int) $produced;
}

if ( $prod_id > 0 ) {
	$prod_order = SOM_Orders::get( $prod_id );
	$assert( $prod_order && SOM_Production::CHANNEL_SLUG === (string) $prod_order->channel_slug, 'produce_n_internal_channel' );
	$assert( $prod_order && '' === (string) $prod_order->workflow_unassigned, 'produce_n_no_unassigned_notice' );
	$assert( $prod_order && SOM_Item_Make::has_item_progress( $prod_id ), 'produce_n_has_item_progress' );
	$assert( $prod_order && empty( $prod_order->pack_workflow_template_id ), 'produce_n_no_pack_bind' );

	$prod_line = null;
	if ( $prod_order && ! empty( $prod_order->make_lines ) ) {
		foreach ( $prod_order->make_lines as $line ) {
			if ( (int) $line->product_id === $product_id ) {
				$prod_line = $line;
				break;
			}
		}
	}
	$assert( $prod_line, 'produce_n_make_line' );
	if ( $prod_line ) {
		$progress = isset( $prod_line->make_progress ) && is_array( $prod_line->make_progress ) ? $prod_line->make_progress : array();
		$assert( count( $progress ) >= 2, 'produce_n_progress_rows' );
		$assert( ! empty( $prod_line->product_is_internal ), 'produce_n_line_is_internal' );
		$assert(
			SOM_Item_Make::detail_shows_make_controls(
				(string) $prod_order->channel_slug,
				! empty( $prod_line->product_is_internal ),
				! empty( $progress )
			),
			'produce_n_detail_shows_controls'
		);
		$assert(
			'' === SOM_Item_Make::detail_make_badge(
				(string) $prod_order->channel_slug,
				true,
				! empty( $progress ),
				SOM_Item_Make::progress_steps_all_done( $progress )
			),
			'produce_n_mid_job_badge_is_current_step'
		);
		$assert( ! SOM_Item_Make::progress_steps_all_done( $progress ), 'produce_n_not_steps_complete' );
		$assert( $prod_line->make_current && $print_name === (string) $prod_line->make_current->step_name, 'produce_n_current_step' );
		// Pack treats internal lines as make-complete. Detail must not use that as "done".
		$assert( ! empty( $prod_line->make_complete ), 'produce_n_pack_flag_still_complete' );
	}

	$advanced = SOM_Item_Make::mark_done( $prod_id, $prod_line ? (int) $prod_line->id : 0 );
	$assert( true === $advanced, 'produce_n_mark_first_done' );
	$mid = SOM_Orders::get( $prod_id );
	$mid_line = null;
	if ( $mid && ! empty( $mid->make_lines ) ) {
		$mid_line = $mid->make_lines[0];
	}
	if ( $mid_line ) {
		$mid_progress = isset( $mid_line->make_progress ) && is_array( $mid_line->make_progress ) ? $mid_line->make_progress : array();
		$assert( $mid_line->make_current && $cut_name === (string) $mid_line->make_current->step_name, 'produce_n_still_in_progress' );
		$assert(
			'' === SOM_Item_Make::detail_make_badge(
				(string) $mid->channel_slug,
				true,
				! empty( $mid_progress ),
				SOM_Item_Make::progress_steps_all_done( $mid_progress )
			),
			'produce_n_second_step_not_pack_ready'
		);
		$assert( '' === (string) $mid->workflow_unassigned, 'produce_n_still_no_unassigned_notice' );
	}

	$finished = SOM_Item_Make::mark_done( $prod_id, $mid_line ? (int) $mid_line->id : 0 );
	$assert( true === $finished, 'produce_n_mark_last_done' );
	$done_order = SOM_Orders::get( $prod_id );
	$done_line  = ( $done_order && ! empty( $done_order->make_lines ) ) ? $done_order->make_lines[0] : null;
	if ( $done_line ) {
		$done_progress = isset( $done_line->make_progress ) && is_array( $done_line->make_progress ) ? $done_line->make_progress : array();
		$assert( SOM_Item_Make::progress_steps_all_done( $done_progress ), 'produce_n_steps_all_done' );
		$assert(
			'ready' === SOM_Item_Make::detail_make_badge(
				(string) $done_order->channel_slug,
				true,
				! empty( $done_progress ),
				true
			),
			'produce_n_complete_ready_not_pack_ready'
		);
		$assert(
			SOM_Item_Make::detail_shows_make_controls(
				(string) $done_order->channel_slug,
				true,
				! empty( $done_progress )
			),
			'produce_n_complete_still_shows_step_list'
		);
	}
}

// --- Customer order internal line: pack-ready, no make progress ---
$cust_id = 0;
if ( $product_id > 0 ) {
	$cust = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => 'up12s2-cust-' . $suffix,
			'buyer_name'        => 'UP12 customer ' . $suffix,
			'items'             => array(
				array(
					'product_id' => $product_id,
					'quantity'   => 1,
					'unit_price' => 0,
				),
			),
		)
	);
	$assert( ! is_wp_error( $cust ), 'customer_internal_create' );
	$cust_id = is_wp_error( $cust ) ? 0 : (int) $cust;
}

if ( $cust_id > 0 ) {
	$cust_order = SOM_Orders::get( $cust_id );
	$assert( $cust_order && 'external' === (string) $cust_order->channel_slug, 'customer_not_internal_channel' );
	$assert( $cust_order && ! SOM_Item_Make::has_item_progress( $cust_id ), 'customer_internal_no_item_progress' );
	$cust_line = ( $cust_order && ! empty( $cust_order->make_lines ) ) ? $cust_order->make_lines[0] : null;
	$assert( $cust_line && ! empty( $cust_line->product_is_internal ), 'customer_line_is_internal' );
	if ( $cust_line ) {
		$cust_progress = isset( $cust_line->make_progress ) && is_array( $cust_line->make_progress ) ? $cust_line->make_progress : array();
		$assert( empty( $cust_progress ), 'customer_line_progress_empty' );
		$assert(
			! SOM_Item_Make::detail_shows_make_controls(
				(string) $cust_order->channel_slug,
				true,
				! empty( $cust_progress )
			),
			'customer_internal_no_make_controls'
		);
		$assert(
			'pack_ready_internal' === SOM_Item_Make::detail_make_badge(
				(string) $cust_order->channel_slug,
				true,
				! empty( $cust_progress ),
				false
			),
			'customer_internal_pack_ready_badge'
		);
	}
}

// --- True gap still flags unassigned ---
$gap = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up12s2-gap-' . $suffix,
		'buyer_name'        => 'UP12 gap ' . $suffix,
		'items'             => array(
			array(
				'quantity'            => 1,
				'unit_price'          => 5,
				'external_listing_id' => 'up12s2-unmatched-' . $suffix,
			),
		),
	)
);
$assert( ! is_wp_error( $gap ), 'gap_order_create' );
if ( ! is_wp_error( $gap ) ) {
	$gap_order = SOM_Orders::get( (int) $gap );
	$assert( $gap_order && 'needs_mapping' === (string) $gap_order->workflow_unassigned, 'true_gap_keeps_unassigned_reason' );
	$assert( $gap_order && ! SOM_Item_Make::has_item_progress( (int) $gap ), 'true_gap_no_item_progress' );
}

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK\n";
