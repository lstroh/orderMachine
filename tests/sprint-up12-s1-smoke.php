<?php
/**
 * UP12-S1 smoke: Produce N is not a false No workflow; Status exposes the current make step; Needs workflow matches the badge.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s1-smoke.php
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

$list_view = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/orders-list.php' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, 'current_make_step_name' ), 'list_status_uses_make_step' );
$assert( is_string( $list_view ) && false !== strpos( $list_view, '(int) $order->needs_workflow' ), 'list_badge_uses_query_flag' );
$assert( is_string( $list_view ) && false === strpos( $list_view, 'progress_count' ), 'list_badge_not_order_progress_only' );

$suffix = wp_generate_password( 4, false, false );

$find_row = static function ( $external_id, $status = '' ) {
	$result = SOM_Orders::query(
		array(
			'status'   => $status,
			's'        => $external_id,
			'per_page' => 50,
		)
	);
	if ( empty( $result['orders'] ) || ! is_array( $result['orders'] ) ) {
		return null;
	}
	foreach ( $result['orders'] as $row ) {
		if ( (string) $row->external_order_id === (string) $external_id ) {
			return $row;
		}
	}
	return null;
};

$input_id = SOM_Materials::create(
	array(
		'name'      => 'UP12-S1 Input ' . $suffix,
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

$create_make = static function ( $name, array $step_names ) use ( $assert ) {
	$wf = SOM_Workflows::create(
		array(
			'name'       => $name,
			'kind'       => 'make',
			'is_active'  => 1,
			'uses_trays' => 0,
		)
	);
	if ( is_wp_error( $wf ) ) {
		$assert( false, 'create_wf_' . $name );
		return 0;
	}
	$steps = array();
	foreach ( $step_names as $step_name ) {
		$steps[] = array(
			'name'                    => $step_name,
			'requires_manual_confirm' => 1,
		);
	}
	$saved = SOM_Workflows::save_steps( (int) $wf, $steps );
	$assert( true === $saved, 'save_steps_' . $name );
	return (int) $wf;
};

$create_internal = static function ( $name, $sku, $workflow_id ) use ( $assert, $input_id ) {
	$product_id = SOM_Products::create(
		array(
			'name'                 => $name,
			'sku'                  => $sku,
			'workflow_template_id' => $workflow_id,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	);
	if ( is_wp_error( $product_id ) ) {
		$assert( false, 'create_product_' . $sku );
		return 0;
	}
	$product_id = (int) $product_id;
	if ( $input_id > 0 ) {
		$recipe = SOM_Products::save_recipe(
			$product_id,
			array(
				array(
					'material_id'       => $input_id,
					'quantity_per_unit' => 1,
				),
			)
		);
		$assert( ! is_wp_error( $recipe ), 'recipe_' . $sku );
	}
	return $product_id;
};

// --- Produce N: open internal job with item make progress, no Pack bind ---
$print_name = 'UP12 Print ' . $suffix;
$print_wf   = $create_make( 'UP12-S1 Print ' . $suffix, array( $print_name ) );
$print_prod = $create_internal( 'UP12-S1 Internal ' . $suffix, 'UP12S1-' . $suffix, $print_wf );
$assert( $print_prod > 0, 'produce_product_ready' );

$prod_id = 0;
if ( $print_prod > 0 ) {
	$produced = SOM_Production::produce( $print_prod, 1 );
	$assert( ! is_wp_error( $produced ), 'produce_n_ok' );
	$prod_id = is_wp_error( $produced ) ? 0 : (int) $produced;
}

if ( $prod_id > 0 ) {
	$prod_order = SOM_Orders::get( $prod_id );
	$assert( $prod_order && 'internal' === (string) $prod_order->channel_slug, 'produce_n_internal' );
	$assert( $prod_order && empty( $prod_order->is_complete ), 'produce_n_still_open' );
	$assert( $prod_order && empty( $prod_order->current_step_id ), 'produce_n_no_order_step' );
	$assert( $prod_order && SOM_Item_Make::has_item_progress( $prod_id ), 'produce_n_has_item_progress' );

	$prod_ext = $prod_order ? (string) $prod_order->external_order_id : '';
	$prod_row = '' !== $prod_ext ? $find_row( $prod_ext ) : null;
	$assert( $prod_row, 'produce_n_on_list' );
	if ( $prod_row ) {
		$assert( 0 === (int) $prod_row->needs_workflow, 'produce_n_badge_not_needs_workflow' );
		$assert( (int) $prod_row->item_progress_count > 0, 'produce_n_query_item_progress' );
		$assert( 0 === (int) $prod_row->progress_count, 'produce_n_no_order_level_progress' );
		$assert( $print_name === trim( (string) $prod_row->current_make_step_name ), 'produce_n_status_make_step' );
		$assert( '' === trim( (string) $prod_row->current_step_name ), 'produce_n_no_order_level_step_name' );
	}
	if ( '' !== $prod_ext ) {
		$assert( null === $find_row( $prod_ext, 'needs_workflow' ), 'produce_n_filter_excludes' );
	}
}

// --- O1: several in-progress make lines → earliest step_order name ---
$alpha_first  = 'UP12 Alpha first ' . $suffix;
$alpha_second = 'UP12 Alpha second ' . $suffix;
$beta_early   = 'UP12 Beta early ' . $suffix;
$alpha_wf     = $create_make( 'UP12-S1 Alpha ' . $suffix, array( $alpha_first, $alpha_second ) );
$beta_wf      = $create_make( 'UP12-S1 Beta ' . $suffix, array( $beta_early ) );
$alpha_prod   = $create_internal( 'UP12-S1 Alpha product ' . $suffix, 'UP12A-' . $suffix, $alpha_wf );
$beta_prod    = $create_internal( 'UP12-S1 Beta product ' . $suffix, 'UP12B-' . $suffix, $beta_wf );

$ml_ext = 'up12s1-ml-' . $suffix;
$ml_id  = 0;
if ( $alpha_prod > 0 && $beta_prod > 0 ) {
	$ml = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => SOM_Production::CHANNEL_SLUG,
			'external_order_id' => $ml_ext,
			'buyer_name'        => 'UP12 multi ' . $suffix,
			'items'             => array(
				array(
					'product_id' => $alpha_prod,
					'quantity'   => 1,
					'unit_price' => 0,
				),
				array(
					'product_id' => $beta_prod,
					'quantity'   => 1,
					'unit_price' => 0,
				),
			),
			'raw_payload'       => array(
				'kind' => 'production',
			),
		)
	);
	$assert( ! is_wp_error( $ml ), 'multi_line_create' );
	$ml_id = is_wp_error( $ml ) ? 0 : (int) $ml;
}

if ( $ml_id > 0 ) {
	$ml_order  = SOM_Orders::get( $ml_id );
	$alpha_item = 0;
	if ( $ml_order && ! empty( $ml_order->items ) ) {
		foreach ( $ml_order->items as $item ) {
			if ( (int) $item->product_id === $alpha_prod ) {
				$alpha_item = (int) $item->id;
				break;
			}
		}
	}
	$assert( $alpha_item > 0, 'multi_line_alpha_item' );
	if ( $alpha_item > 0 ) {
		$advanced = SOM_Item_Make::mark_done( $ml_id, $alpha_item );
		$assert( true === $advanced, 'multi_line_advance_alpha' );
	}
	$ml_row = $find_row( $ml_ext );
	$assert( $ml_row, 'multi_line_on_list' );
	if ( $ml_row ) {
		$assert( 0 === (int) $ml_row->needs_workflow, 'multi_line_not_needs_workflow' );
		$assert( '' === trim( (string) $ml_row->current_step_name ), 'multi_line_no_order_level_step' );
		$assert( $beta_early === trim( (string) $ml_row->current_make_step_name ), 'multi_line_earliest_make_step' );
	}
}

// --- Customer order with no make and no pack/post-ship progress still matches ---
$prev_pack = SOM_Pack::default_template_id();
$cust_ext  = 'up12s1-nw-' . $suffix;
try {
	SOM_Pack::set_default_template_id( 0 );
	$cust = SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'external',
			'external_order_id' => $cust_ext,
			'buyer_name'        => 'UP12 no workflow ' . $suffix,
			'items'             => array(
				array(
					'quantity'            => 1,
					'unit_price'          => 5,
					'external_listing_id' => 'up12-unmatched-' . $suffix,
				),
			),
		)
	);
	$assert( ! is_wp_error( $cust ), 'customer_no_progress_create' );
	$cust_id = is_wp_error( $cust ) ? 0 : (int) $cust;
	if ( $cust_id > 0 ) {
		$cust_order = SOM_Orders::get( $cust_id );
		$assert( $cust_order && empty( $cust_order->is_complete ), 'customer_still_open' );
		$assert( $cust_order && ! SOM_Item_Make::has_item_progress( $cust_id ), 'customer_no_item_progress' );
		$assert( $cust_order && ! SOM_Workflow_Engine::has_progress( $cust_id ), 'customer_no_order_progress' );
	}
	$cust_row = $find_row( $cust_ext, 'needs_workflow' );
	$assert( $cust_row, 'customer_filter_includes' );
	if ( $cust_row ) {
		$assert( 1 === (int) $cust_row->needs_workflow, 'customer_badge_needs_workflow' );
		$assert( '' === trim( (string) $cust_row->current_make_step_name ), 'customer_no_make_step_name' );
		$assert( 0 === (int) $cust_row->item_progress_count, 'customer_query_no_item_progress' );
	}
} finally {
	if ( $prev_pack > 0 ) {
		SOM_Pack::set_default_template_id( $prev_pack );
	}
}

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "OK\n";
