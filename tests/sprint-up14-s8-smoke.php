<?php
/**
 * UP14-S8 smoke: make-workflow output per run multiplies the Internal material credit.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s8-smoke.php
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
SOM_Production::init();

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$stock_of = static function ( $material_id ) {
	$material = SOM_Materials::get( (int) $material_id );
	return $material ? (float) $material->current_stock : 0.0;
};

$output_count = static function ( $order_id ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s',
			(int) $order_id,
			'production_output'
		)
	);
};

$output_row = static function ( $order_id ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT change_qty, unit_cost_at_time, value_change FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE order_id = %d AND reason = %s ORDER BY id ASC LIMIT 1',
			(int) $order_id,
			'production_output'
		)
	);
};

$named = static function ( $name ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE name = %s LIMIT 1',
			$name
		)
	);
};

$out( 'plugin', SOM_VERSION );
$out( 'db', SOM_DB::DB_VERSION );

$templates_t = SOM_DB::table( 'workflow_templates' );
$col         = $wpdb->get_row( "SHOW COLUMNS FROM {$templates_t} LIKE 'output_per_run'" );
$assert( ! empty( $col ), 'column_output_per_run' );
$assert( $col && isset( $col->Null ) && 'NO' === (string) $col->Null, 'column_not_null' );
$assert( $col && isset( $col->Type ) && false !== stripos( (string) $col->Type, 'int' ), 'column_int' );
$assert( $col && isset( $col->Default ) && '1' === (string) $col->Default, 'column_default_1' );
$assert( '1.25.0' === SOM_DB::DB_VERSION, 'schema_1_25_0' );
$order_col = $wpdb->get_row( 'SHOW COLUMNS FROM ' . SOM_DB::table( 'orders' ) . " LIKE 'output_per_run'" );
$assert( empty( $order_col ), 'yield_not_stored_on_orders' );

$editor = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$menu   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$js     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$assert( false !== strpos( $editor, 'name="som_workflow_output_per_run"' ), 'editor_field' );
$assert( false !== strpos( $editor, 'som-output-per-run' ), 'editor_beside_uses_trays' );
$assert( false !== strpos( $editor, "'make' === \$current_kind" ), 'editor_make_only' );
$assert( false !== strpos( $menu, "'output_per_run'" ), 'save_posts_output_per_run' );
$assert( false !== strpos( $js, "kind === 'make'" ), 'kind_change_shows_make_only' );

$make_name = 'UP14-S8 Make ' . $suffix;
$make      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $make_name,
		'kind'      => 'make',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Cut',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $make ) && (int) $make > 0, 'create_make' );
$make_id = is_wp_error( $make ) ? 0 : (int) $make;
$made    = $make_id > 0 ? SOM_Workflows::get( $make_id ) : null;
$assert( $made && 1 === (int) $made->output_per_run, 'omitted_yield_is_1' );

$saved_12 = $make_id > 0 ? SOM_Workflows::update( $make_id, array( 'output_per_run' => 12 ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $saved_12, 'make_saves_12' );
$made = $make_id > 0 ? SOM_Workflows::get( $make_id ) : null;
$assert( $made && 12 === (int) $made->output_per_run, 'make_stored_12' );

$blank = $make_id > 0 ? SOM_Workflows::update( $make_id, array( 'output_per_run' => '  ' ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $blank, 'blank_saves' );
$made = $make_id > 0 ? SOM_Workflows::get( $make_id ) : null;
$assert( $made && 1 === (int) $made->output_per_run, 'blank_is_1' );

$too_big = $make_id > 0 ? SOM_Workflows::update( $make_id, array( 'output_per_run' => '10001' ) ) : null;
$assert( is_wp_error( $too_big ), 'rejects_over_max' );
$made = $make_id > 0 ? SOM_Workflows::get( $make_id ) : null;
$assert( $made && 1 === (int) $made->output_per_run, 'invalid_does_not_write' );

$pack_name = 'UP14-S8 Pack ' . $suffix;
$pack      = SOM_Workflows::save_submission(
	0,
	array(
		'name'           => $pack_name,
		'kind'           => 'pack',
		'is_active'      => 1,
		'output_per_run' => 12,
	),
	array(
		array(
			'name'                    => 'Box',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $pack ) && (int) $pack > 0, 'create_pack' );
$packed = is_wp_error( $pack ) ? null : SOM_Workflows::get( (int) $pack );
$assert( $packed && 1 === (int) $packed->output_per_run, 'pack_save_leaves_1' );

$post_name = 'UP14-S8 Post ' . $suffix;
$post      = SOM_Workflows::save_submission(
	0,
	array(
		'name'           => $post_name,
		'kind'           => 'post_ship',
		'is_active'      => 1,
		'output_per_run' => 40,
	),
	array(
		array(
			'name'                    => 'Arrived',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => SOM_Step_Confirmations::KIND_PACKAGE_ARRIVED,
		),
	)
);
$assert( ! is_wp_error( $post ) && (int) $post > 0, 'create_post_ship' );
$posted = is_wp_error( $post ) ? null : SOM_Workflows::get( (int) $post );
$assert( $posted && 1 === (int) $posted->output_per_run, 'post_ship_save_leaves_1' );

$input_id = SOM_Materials::create(
	array(
		'name'      => 'UP14-S8 vinyl ' . $suffix,
		'unit'      => 'sheet',
		'unit_cost' => 2.5,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $input_id ), 'create_input' );
$input_id = is_wp_error( $input_id ) ? 0 : (int) $input_id;
if ( $input_id > 0 ) {
	$restock = SOM_Materials::adjust_stock(
		$input_id,
		80,
		array(
			'reason'            => 'restock',
			'unit_cost_at_time' => 2.5,
			'value_change'      => 200.0,
			'sync_unit_cost'    => true,
		)
	);
	$assert( ! is_wp_error( $restock ), 'restock_input' );
	if ( ! SOM_Budgets::get_for_material( $input_id, true ) ) {
		$budget = SOM_Budgets::create(
			array(
				'name'           => 'UP14-S8 vinyl pot ' . $suffix,
				'type'           => 'material',
				'material_id'    => $input_id,
				'funding_method' => 'material_cost',
				'is_active'      => 1,
			)
		);
		$assert( ! is_wp_error( $budget ), 'create_input_budget' );
	}
}

$product_id = ( $make_id > 0 )
	? SOM_Products::create(
		array(
			'name'                 => 'UP14-S8 sheet ' . $suffix,
			'sku'                  => 'UP14S8-' . $suffix,
			'workflow_template_id' => $make_id,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $product_id ) && (int) $product_id > 0, 'create_internal_product' );
$product_id = is_wp_error( $product_id ) ? 0 : (int) $product_id;
$product    = $product_id > 0 ? SOM_Products::get( $product_id ) : null;
$linked_id  = $product ? (int) $product->linked_material_id : 0;
$assert( $linked_id > 0, 'linked_material' );

if ( $product_id > 0 && $input_id > 0 ) {
	$recipe = SOM_Products::save_recipe(
		$product_id,
		array(
			array(
				'material_id'       => $input_id,
				'quantity_per_unit' => 2,
			),
		)
	);
	$assert( ! is_wp_error( $recipe ), 'save_recipe' );
}

$input_before  = $input_id > 0 ? $stock_of( $input_id ) : 0.0;
$linked_before = $linked_id > 0 ? $stock_of( $linked_id ) : 0.0;
$order_id      = ( $product_id > 0 ) ? SOM_Production::produce( $product_id, 3 ) : new WP_Error( 'skipped', 'skipped' );
if ( is_wp_error( $order_id ) ) {
	$out( 'produce_error', $order_id->get_error_message() );
}
$assert( ! is_wp_error( $order_id ) && (int) $order_id > 0, 'produce_3' );
$order_id = is_wp_error( $order_id ) ? 0 : (int) $order_id;

$open = $order_id > 0 ? SOM_Orders::get( $order_id ) : null;
$assert( $open && empty( $open->is_complete ), 'open_before_finish' );
$assert( $order_id > 0 && 0 === $output_count( $order_id ), 'no_credit_at_produce' );
$assert( $input_id > 0 && abs( ( $input_before - 6 ) - $stock_of( $input_id ) ) < 0.001, 'recipe_reserves_produce_n' );
$assert( $linked_id > 0 && abs( $linked_before - $stock_of( $linked_id ) ) < 0.001, 'linked_unchanged_before_finish' );

$set_12 = $make_id > 0 ? SOM_Workflows::update( $make_id, array( 'output_per_run' => 12 ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $set_12, 'yield_changed_before_finish' );

$item_id = 0;
if ( $open && ! empty( $open->items ) ) {
	foreach ( $open->items as $item ) {
		if ( (int) $item->product_id === $product_id ) {
			$item_id = (int) $item->id;
			break;
		}
	}
}
$done = ( $order_id > 0 && $item_id > 0 ) ? SOM_Item_Make::mark_done( $order_id, $item_id ) : new WP_Error( 'skipped', 'skipped' );
if ( is_wp_error( $done ) ) {
	$out( 'mark_done_error', $done->get_error_message() );
}
$assert( true === $done, 'mark_done' );

$finished = $order_id > 0 ? SOM_Orders::get( $order_id ) : null;
$assert( $finished && ! empty( $finished->is_complete ), 'complete_after_mark_done' );
$credit = $order_id > 0 ? $output_row( $order_id ) : null;
$cogs   = $order_id > 0 ? SOM_Analytics::order_material_cogs( $order_id ) : 0.0;
$unit   = SOM_Material_Costing::round4( $cogs / 36 );
$assert( abs( $cogs - 15.0 ) < 0.001, 'input_cogs_15' );
$assert( $credit && abs( (float) $credit->change_qty - 36 ) < 0.001, 'credits_36' );
$assert( $credit && abs( (float) $credit->unit_cost_at_time - $unit ) < 0.00001, 'unit_cost_is_cogs_over_36' );
$assert(
	$credit && abs( (float) $credit->value_change - SOM_Material_Costing::round4( $unit * 36 ) ) < 0.00001,
	'value_change_is_unit_times_units'
);
$assert( $linked_id > 0 && abs( ( $linked_before + 36 ) - $stock_of( $linked_id ) ) < 0.001, 'linked_stock_36' );
$assert( $input_id > 0 && abs( ( $input_before - 6 ) - $stock_of( $input_id ) ) < 0.001, 'recipe_not_multiplied' );
$assert( 1 === $output_count( $order_id ), 'one_production_output_row' );

$again = $order_id > 0 ? SOM_Production::credit_output_for_order( $order_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $again, 'second_credit_ok' );
$assert( 1 === $output_count( $order_id ), 'second_credit_does_not_add' );
$assert( $linked_id > 0 && abs( ( $linked_before + 36 ) - $stock_of( $linked_id ) ) < 0.001, 'second_credit_stock_unchanged' );

$plain_name = 'UP14-S8 Plain ' . $suffix;
$plain      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $plain_name,
		'kind'      => 'make',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Pass',
			'requires_manual_confirm' => 0,
		),
	)
);
$assert( ! is_wp_error( $plain ) && (int) $plain > 0, 'create_yield_1_workflow' );
$plain_id  = is_wp_error( $plain ) ? 0 : (int) $plain;
$plain_row = $plain_id > 0 ? SOM_Workflows::get( $plain_id ) : null;
$assert( $plain_row && 1 === (int) $plain_row->output_per_run, 'yield_1_stored' );

$plain_product = ( $plain_id > 0 )
	? SOM_Products::create(
		array(
			'name'                 => 'UP14-S8 plain product ' . $suffix,
			'sku'                  => 'UP14S8P-' . $suffix,
			'workflow_template_id' => $plain_id,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $plain_product ) && (int) $plain_product > 0, 'create_yield_1_product' );
$plain_product_id = is_wp_error( $plain_product ) ? 0 : (int) $plain_product;
if ( $plain_product_id > 0 && $input_id > 0 ) {
	$plain_recipe = SOM_Products::save_recipe(
		$plain_product_id,
		array(
			array(
				'material_id'       => $input_id,
				'quantity_per_unit' => 1,
			),
		)
	);
	$assert( ! is_wp_error( $plain_recipe ), 'yield_1_recipe' );
}
$plain_linked = $plain_product_id > 0 ? SOM_Products::get( $plain_product_id ) : null;
$plain_linked_id = $plain_linked ? (int) $plain_linked->linked_material_id : 0;
$plain_before    = $plain_linked_id > 0 ? $stock_of( $plain_linked_id ) : 0.0;
$plain_order     = $plain_product_id > 0 ? SOM_Production::produce( $plain_product_id, 4 ) : new WP_Error( 'skipped', 'skipped' );
if ( is_wp_error( $plain_order ) ) {
	$out( 'yield_1_produce_error', $plain_order->get_error_message() );
}
$assert( ! is_wp_error( $plain_order ) && (int) $plain_order > 0, 'produce_4_yield_1' );
$plain_order_id = is_wp_error( $plain_order ) ? 0 : (int) $plain_order;
$plain_credit   = $plain_order_id > 0 ? $output_row( $plain_order_id ) : null;
$assert( $plain_credit && abs( (float) $plain_credit->change_qty - 4 ) < 0.001, 'yield_1_credits_produce_n' );
$assert( $plain_linked_id > 0 && abs( ( $plain_before + 4 ) - $stock_of( $plain_linked_id ) ) < 0.001, 'yield_1_stock' );

$linked_customer_before = $linked_id > 0 ? $stock_of( $linked_id ) : 0.0;
$customer               = ( $product_id > 0 )
	? SOM_Order_Sync::create_from_external(
		array(
			'channel'           => 'ebay',
			'external_order_id' => 'up14s8-c-' . $suffix,
			'buyer_name'        => 'UP14-S8 buyer',
			'shipping_address'  => array(
				'line1'    => '1 Yield Street',
				'city'     => 'London',
				'postcode' => 'E1 1AA',
				'country'  => 'GB',
			),
			'items'             => array(
				array(
					'product_id' => $product_id,
					'quantity'   => 2,
					'unit_price' => 5,
				),
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
if ( is_wp_error( $customer ) ) {
	$out( 'customer_error', $customer->get_error_message() );
}
$assert( ! is_wp_error( $customer ) && (int) $customer > 0, 'customer_order_created' );
$customer_id = is_wp_error( $customer ) ? 0 : (int) $customer;
if ( $customer_id > 0 ) {
	$wpdb->update(
		SOM_DB::table( 'orders' ),
		array( 'is_complete' => 1 ),
		array( 'id' => $customer_id ),
		array( '%d' ),
		array( '%d' )
	);
}
$customer_credit = $customer_id > 0 ? SOM_Production::credit_output_for_order( $customer_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $customer_credit, 'customer_credit_returns' );
$assert( $customer_id > 0 && 0 === $output_count( $customer_id ), 'customer_no_production_output' );
$assert( $linked_id > 0 && abs( $linked_customer_before - $stock_of( $linked_id ) ) < 0.001, 'customer_does_not_credit_stock' );

$copy = $make_id > 0 ? SOM_Workflows::clone_template( $make_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $copy ) && (int) $copy > 0, 'clone_ok' );
$copy_row = is_wp_error( $copy ) ? null : SOM_Workflows::get( (int) $copy );
$assert( $copy_row && 12 === (int) $copy_row->output_per_run && 'make' === (string) $copy_row->kind, 'clone_keeps_12' );
$original = $make_id > 0 ? SOM_Workflows::get( $make_id ) : null;
$assert( $original && 12 === (int) $original->output_per_run, 'original_still_12' );

$assert( in_array( 'output_per_run', SOM_Workflow_Import::COLUMNS, true ), 'csv_column' );
$assert( ! in_array( 'output_per_run', SOM_Workflow_Import::REQUIRED, true ), 'csv_column_optional' );

$csv_make = 'UP14-S8 CSV make ' . $suffix;
$csv_pack = 'UP14-S8 CSV pack ' . $suffix;
$csv_old  = 'UP14-S8 CSV old ' . $suffix;
$csv_bad  = 'UP14-S8 CSV bad ' . $suffix;
$bad_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'workflow_templates' ) );
$bad_commit = SOM_Workflow_Import::commit( "name,kind,output_per_run,step_order,step_name\n{$csv_bad},make,nope,1,Cut\n" );
$assert( 0 === (int) $bad_commit['created'] && $bad_before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'workflow_templates' ) ), 'csv_bad_yield_creates_none' );
$assert( null === $named( $csv_bad ), 'csv_bad_yield_absent' );

$good_commit = SOM_Workflow_Import::commit(
	"name,kind,output_per_run,step_order,step_name\n"
	. "{$csv_make},make,12,1,Cut\n"
	. "{$csv_pack},pack,99,1,Box\n"
);
$assert( 2 === (int) $good_commit['created'] && 0 === (int) $good_commit['failed'], 'csv_make_and_pack_created' );
$csv_make_row = $named( $csv_make );
$csv_pack_row = $named( $csv_pack );
$assert( $csv_make_row && 12 === (int) $csv_make_row->output_per_run, 'csv_make_stores_12' );
$assert( $csv_pack_row && 1 === (int) $csv_pack_row->output_per_run, 'csv_pack_stores_1' );

$old_commit = SOM_Workflow_Import::commit( "name,kind,step_order,step_name\n{$csv_old},make,1,Cut\n" );
$assert( 1 === (int) $old_commit['created'], 'csv_without_column_created' );
$old_row = $named( $csv_old );
$assert( $old_row && 1 === (int) $old_row->output_per_run, 'csv_blank_or_missing_is_1' );

$export = SOM_Workflow_Import::export_csv();
$export = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $export );
$lines  = preg_split( '/\r\n|\n|\r/', trim( (string) $export ) );
$header = isset( $lines[0] ) ? str_getcsv( $lines[0] ) : array();
$yield_at = array_search( 'output_per_run', $header, true );
$exported = false;
if ( false !== $yield_at && is_array( $lines ) ) {
	foreach ( $lines as $line ) {
		$cells = str_getcsv( (string) $line );
		if ( isset( $cells[0], $cells[ $yield_at ] ) && $make_name === $cells[0] && '12' === $cells[ $yield_at ] ) {
			$exported = true;
			break;
		}
	}
}
$assert( false !== $yield_at && $exported, 'export_writes_stored_12' );

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'PASSED' );
exit( 0 );
