<?php
/**
 * UP12-S7 smoke: clone a product without copying listings, and give an internal clone its own output material.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s7-smoke.php
 *
 * @package OrderMachine
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

if ( ! function_exists( 'som_up12_s7_strlen' ) ) {
	/**
	 * Character length, matching the cloner when mbstring is present.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	function som_up12_s7_strlen( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( (string) $text );
		}
		return strlen( (string) $text );
	}
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

$product_count = static function () {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'products' ) );
};

$name_count = static function ( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'products' ) . ' WHERE name = %s',
			$name
		)
	);
};

$material_count = static function () {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'materials' ) );
};

$money = static function ( $value ) {
	if ( null === $value || '' === $value ) {
		return null;
	}
	return number_format( (float) $value, 2, '.', '' );
};

$recipe_sig = static function ( $product_id ) use ( $money ) {
	$out = array();
	foreach ( SOM_Products::get_recipe( $product_id ) as $row ) {
		$out[] = array(
			'material_id'       => (int) $row->material_id,
			'quantity_per_unit' => $money( $row->quantity_per_unit ),
		);
	}
	return $out;
};

$budget_ids_for = static function ( $product_id ) {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT budget_id FROM ' . SOM_DB::table( 'budget_product_links' ) . ' WHERE product_id = %d ORDER BY budget_id ASC',
			(int) $product_id
		)
	);
	return array_map( 'intval', is_array( $ids ) ? $ids : array() );
};

$listing_ids_for = static function ( $product_id ) {
	$ids = array();
	foreach ( SOM_Products::get_listings( $product_id ) as $listing ) {
		$ids[] = (int) $listing->id;
	}
	return $ids;
};

$stock_log_count = static function ( $material_id ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE material_id = %d',
			(int) $material_id
		)
	);
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$out( 'plugin', SOM_VERSION );
$assert( method_exists( 'SOM_Products', 'clone_product' ), 'method_clone_product' );
$assert( method_exists( 'SOM_Products', 'render_clone_button' ), 'method_render_clone_button' );

$products_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-products.php' );
$menu_src     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$list_src     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/products-list.php' );
$edit_src     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/product-edit.php' );

$clone_fn    = strpos( $products_src, 'function clone_product' );
$render_fn   = false !== $clone_fn ? strpos( $products_src, 'function render_clone_button', $clone_fn ) : false;
$clone_body  = ( false !== $clone_fn && false !== $render_fn ) ? substr( $products_src, $clone_fn, $render_fn - $clone_fn ) : '';
$prep_at     = false !== $clone_fn ? strpos( $products_src, 'prepare_create_row', $clone_fn ) : false;
$tx_at       = false !== $clone_fn ? strpos( $products_src, 'START TRANSACTION', $clone_fn ) : false;
$insert_at   = false !== $clone_fn ? strpos( $products_src, 'insert_prepared_product', $clone_fn ) : false;
$ensure_at   = false !== $clone_fn ? strpos( $products_src, 'ensure_linked_material', $clone_fn ) : false;
$assert( '' !== $clone_body && false !== $prep_at && false !== $tx_at && false !== $insert_at && $prep_at < $tx_at && $tx_at < $insert_at, 'validate_before_write' );
$assert( false !== $ensure_at && false !== $insert_at && $insert_at < $ensure_at && false !== $render_fn && $ensure_at < $render_fn, 'internal_ensure_after_insert' );
$assert( false !== strpos( $products_src, 'ROLLBACK' ), 'rollback_on_failure' );
$assert( false === strpos( $clone_body, 'SOM_Listings' ) && false === strpos( $clone_body, "'listings'" ), 'clone_does_not_copy_listings' );

$handler_at = strpos( $menu_src, 'function handle_products_actions' );
$cap_at     = false !== $handler_at ? strpos( $menu_src, 'manage_options', $handler_at ) : false;
$post_at    = false !== $handler_at ? strpos( $menu_src, "isset( \$_POST['som_clone_product'] )", $handler_at ) : false;
$nonce_at   = false !== $handler_at ? strpos( $menu_src, "check_admin_referer( 'som_clone_product', 'som_product_clone_nonce' )", $handler_at ) : false;
$notice_at  = false !== $handler_at ? strpos( $menu_src, 'Cloned from %s. SKU left blank; listings not copied.', $handler_at ) : false;
$assert( false !== $cap_at && false !== $post_at && $cap_at < $post_at, 'handler_requires_manage_options' );
$assert( false !== $nonce_at && false !== $post_at && $post_at < $nonce_at, 'handler_post_nonce' );
$assert( false !== $notice_at, 'handler_cloned_from_notice' );
$assert( false === strpos( $menu_src, "\$_GET['som_clone_product']" ), 'no_mutating_get' );

$assert( false !== strpos( $list_src, "render_clone_button( (int) \$product->id, 'list', 'button-link' )" ), 'list_clone_action' );
$assert( false !== strpos( $edit_src, "render_clone_button( (int) \$product->id, 'editor', 'page-title-action' )" ), 'edit_clone_button' );
$assert( false !== strpos( $products_src, 'method="post"' ), 'clone_form_is_post' );
$assert( false !== strpos( $products_src, "wp_nonce_field( 'som_clone_product', 'som_product_clone_nonce' )" ), 'clone_form_nonce' );

$make_name = 'UP12-S7 make ' . $suffix;
$make      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $make_name,
		'kind'      => 'make',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Print',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Weed',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $make ) && (int) $make > 0, 'create_make' );
$make_id = is_wp_error( $make ) ? 0 : (int) $make;

$post_name = 'UP12-S7 post-ship ' . $suffix;
$post      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $post_name,
		'kind'      => 'post_ship',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Update buyer',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Confirm package arrived',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => SOM_Step_Confirmations::KIND_PACKAGE_ARRIVED,
		),
	)
);
$assert( ! is_wp_error( $post ) && (int) $post > 0, 'create_post_ship' );
$post_id = is_wp_error( $post ) ? 0 : (int) $post;

$steps  = $make_id > 0 ? SOM_Workflows::get_steps( $make_id ) : array();
$step_a = ! empty( $steps[0]->id ) ? (int) $steps[0]->id : 0;
$step_b = ! empty( $steps[1]->id ) ? (int) $steps[1]->id : 0;
$assert( $step_a > 0 && $step_b > 0 && $step_a !== $step_b, 'make_steps' );

$vinyl = SOM_Materials::create(
	array(
		'name' => 'UP12-S7 vinyl ' . $suffix,
		'unit' => 'sheet',
	)
);
$assert( ! is_wp_error( $vinyl ) && (int) $vinyl > 0, 'create_vinyl' );
$vinyl_id = is_wp_error( $vinyl ) ? 0 : (int) $vinyl;

$pkg_one = SOM_Shipping_Packages::create(
	array(
		'name'              => 'UP12-S7 small ' . $suffix,
		'length_mm'         => 200,
		'width_mm'          => 100,
		'height_mm'         => 10,
		'tare_weight_grams' => 12,
		'is_active'         => 1,
		'is_default'        => 0,
	)
);
$assert( ! is_wp_error( $pkg_one ) && (int) $pkg_one > 0, 'create_package_one' );
$pkg_one_id = is_wp_error( $pkg_one ) ? 0 : (int) $pkg_one;

$pkg_multi = SOM_Shipping_Packages::create(
	array(
		'name'              => 'UP12-S7 multi ' . $suffix,
		'length_mm'         => 300,
		'width_mm'          => 200,
		'height_mm'         => 20,
		'tare_weight_grams' => 25,
		'is_active'         => 1,
		'is_default'        => 0,
	)
);
$assert( ! is_wp_error( $pkg_multi ) && (int) $pkg_multi > 0, 'create_package_multi' );
$pkg_multi_id = is_wp_error( $pkg_multi ) ? 0 : (int) $pkg_multi;

$sell_name = 'UP12-S7 sellable ' . $suffix;
$sell_sku  = 'UP12S7-' . $suffix;
$sellable  = ( $make_id > 0 && $post_id > 0 && $pkg_one_id > 0 && $pkg_multi_id > 0 )
	? SOM_Products::create(
		array(
			'name'                           => $sell_name,
			'sku'                            => $sell_sku,
			'workflow_template_id'           => $make_id,
			'post_ship_workflow_template_id' => $post_id,
			'target_selling_price'           => '19.99',
			'weight_grams'                   => '42.5',
			'package_id'                     => $pkg_one_id,
			'package_id_multi'               => $pkg_multi_id,
			'planned_shipping_gbp'           => '3.50',
			'is_active'                      => 0,
			'is_internal'                    => 0,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $sellable ) && (int) $sellable > 0, 'create_sellable' );
$sellable_id = is_wp_error( $sellable ) ? 0 : (int) $sellable;

$recipe_ok = ( $sellable_id > 0 && $vinyl_id > 0 )
	? SOM_Products::save_recipe(
		$sellable_id,
		array(
			array(
				'material_id'       => $vinyl_id,
				'quantity_per_unit' => '1.25',
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $recipe_ok, 'save_sellable_recipe' );

$instr_text = 'Cut **slowly**';
$instr_ok   = ( $sellable_id > 0 && $step_a > 0 )
	? SOM_Step_Instructions::sync_for_product(
		$sellable_id,
		$make_id,
		array(
			$step_a => $instr_text,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $instr_ok, 'save_sellable_instructions' );

$task_ok = ( $sellable_id > 0 && $step_b > 0 )
	? SOM_Step_Tasks::sync_for_product(
		$sellable_id,
		$make_id,
		array(
			$step_b => array(
				array(
					'id'    => 'wipe',
					'label' => 'Wipe the bed',
				),
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $task_ok, 'save_sellable_tasks' );

$budget = SOM_Budgets::create(
	array(
		'name'           => 'UP12-S7 sellable budget ' . $suffix,
		'type'           => 'manual',
		'funding_method' => 'fixed_amount',
		'funding_value'  => '1.50',
	)
);
$assert( ! is_wp_error( $budget ) && (int) $budget > 0, 'create_sellable_budget' );
$budget_id = is_wp_error( $budget ) ? 0 : (int) $budget;
$link_ok   = ( $budget_id > 0 && $sellable_id > 0 ) ? SOM_Budgets::set_product_links( $budget_id, array( $sellable_id ) ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $link_ok, 'link_sellable_budget' );

$listing = $sellable_id > 0
	? SOM_Listings::create(
		array(
			'product_id'          => $sellable_id,
			'channel_slug'        => 'ebay',
			'external_listing_id' => 'UP12S7-' . $suffix,
			'title'               => 'UP12-S7 listing ' . $suffix,
			'price'               => 19.99,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $listing ) && (int) $listing > 0, 'create_listing' );
$listing_id = is_wp_error( $listing ) ? 0 : (int) $listing;

$sell_recipe_before = $sellable_id > 0 ? $recipe_sig( $sellable_id ) : array();
$sell_instr_before  = $sellable_id > 0 ? SOM_Step_Instructions::get_overrides_for_product( $sellable_id ) : array();
$sell_tasks_before  = $sellable_id > 0 ? SOM_Step_Tasks::get_overrides_for_product( $sellable_id ) : array();
$sell_budgets_before = $sellable_id > 0 ? $budget_ids_for( $sellable_id ) : array();
$sell_listings_before = $sellable_id > 0 ? $listing_ids_for( $sellable_id ) : array();

$sell_clone_id = 0;
if ( $sellable_id > 0 ) {
	$cloned = SOM_Products::clone_product( $sellable_id );
	$assert( ! is_wp_error( $cloned ) && (int) $cloned > 0 && (int) $cloned !== $sellable_id, 'clone_sellable' );
	$sell_clone_id = is_wp_error( $cloned ) ? 0 : (int) $cloned;
}

$sell_copy   = $sell_clone_id > 0 ? SOM_Products::get( $sell_clone_id ) : null;
$sell_source = $sellable_id > 0 ? SOM_Products::get( $sellable_id ) : null;
$assert( $sell_copy && $sell_name . ' (copy)' === (string) $sell_copy->name, 'sellable_copy_name' );
$assert( $sell_copy && null === $sell_copy->sku, 'sellable_copy_sku_null' );
$assert( $sell_copy && $make_id === (int) $sell_copy->workflow_template_id, 'sellable_copy_make_workflow' );
$assert( $sell_copy && $post_id === (int) $sell_copy->post_ship_workflow_template_id, 'sellable_copy_post_ship_workflow' );
$assert( $sell_copy && 0 === (int) $sell_copy->is_internal, 'sellable_copy_not_internal' );
$assert( $sell_copy && 0 === (int) $sell_copy->is_active, 'sellable_copy_stays_inactive' );
$assert( $sell_copy && '19.99' === $money( $sell_copy->target_selling_price ), 'sellable_copy_price' );
$assert( $sell_copy && '42.50' === $money( $sell_copy->weight_grams ), 'sellable_copy_weight' );
$assert( $sell_copy && $pkg_one_id === (int) $sell_copy->package_id, 'sellable_copy_package' );
$assert( $sell_copy && $pkg_multi_id === (int) $sell_copy->package_id_multi, 'sellable_copy_package_multi' );
$assert( $sell_copy && '3.50' === $money( $sell_copy->planned_shipping_gbp ), 'sellable_copy_shipping' );
$assert( $sell_copy && empty( $sell_copy->linked_material_id ), 'sellable_copy_no_linked_material' );
$assert( $sell_clone_id > 0 && $sell_recipe_before === $recipe_sig( $sell_clone_id ), 'sellable_recipe_copied' );
$assert( $sell_clone_id > 0 && $sell_instr_before === SOM_Step_Instructions::get_overrides_for_product( $sell_clone_id ), 'sellable_instructions_copied' );
$assert( $sell_clone_id > 0 && isset( $sell_instr_before[ $step_a ] ) && $instr_text === $sell_instr_before[ $step_a ], 'sellable_instruction_same_step' );
$assert( $sell_clone_id > 0 && $sell_tasks_before === SOM_Step_Tasks::get_overrides_for_product( $sell_clone_id ), 'sellable_tasks_copied' );
$assert( $sell_clone_id > 0 && isset( $sell_tasks_before[ $step_b ] ), 'sellable_tasks_same_step' );
$assert( $sell_clone_id > 0 && $sell_budgets_before === $budget_ids_for( $sell_clone_id ) && array( $budget_id ) === $budget_ids_for( $sell_clone_id ), 'sellable_budget_links_copied' );
$assert( $sell_clone_id > 0 && array() === $listing_ids_for( $sell_clone_id ), 'sellable_listings_not_copied' );
$assert( $sell_source && $sell_sku === (string) $sell_source->sku, 'sellable_original_sku_unchanged' );
$assert( $sell_source && $sell_name === (string) $sell_source->name, 'sellable_original_name_unchanged' );
$assert( $sellable_id > 0 && $sell_listings_before === $listing_ids_for( $sellable_id ), 'sellable_original_listings_stay' );
$listing_row = $listing_id > 0 ? SOM_Listings::get( $listing_id ) : null;
$assert( $listing_row && $sellable_id === (int) $listing_row->product_id, 'listing_still_on_original' );

$internal_name = 'UP12-S7 internal ' . $suffix;
$internal      = $make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => $internal_name,
			'sku'                  => 'UP12S7I-' . $suffix,
			'workflow_template_id' => $make_id,
			'target_selling_price' => '4.00',
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $internal ) && (int) $internal > 0, 'create_internal' );
$internal_id = is_wp_error( $internal ) ? 0 : (int) $internal;

$internal_recipe = ( $internal_id > 0 && $vinyl_id > 0 )
	? SOM_Products::save_recipe(
		$internal_id,
		array(
			array(
				'material_id'       => $vinyl_id,
				'quantity_per_unit' => '2.00',
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $internal_recipe, 'save_internal_recipe' );

$internal_instr = 'Press for 10 seconds';
$internal_instr_ok = ( $internal_id > 0 && $step_a > 0 )
	? SOM_Step_Instructions::sync_for_product(
		$internal_id,
		$make_id,
		array(
			$step_a => $internal_instr,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $internal_instr_ok, 'save_internal_instructions' );

$internal_task_ok = ( $internal_id > 0 && $step_b > 0 )
	? SOM_Step_Tasks::sync_for_product(
		$internal_id,
		$make_id,
		array(
			$step_b => array(
				array(
					'id'    => 'check',
					'label' => 'Check the sheet',
				),
			),
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $internal_task_ok, 'save_internal_tasks' );

$internal_budget = SOM_Budgets::create(
	array(
		'name'           => 'UP12-S7 internal budget ' . $suffix,
		'type'           => 'manual',
		'funding_method' => 'fixed_amount',
		'funding_value'  => '0.25',
	)
);
$assert( ! is_wp_error( $internal_budget ) && (int) $internal_budget > 0, 'create_internal_budget' );
$internal_budget_id = is_wp_error( $internal_budget ) ? 0 : (int) $internal_budget;
$internal_link_ok   = ( $internal_budget_id > 0 && $internal_id > 0 )
	? SOM_Budgets::set_product_links( $internal_budget_id, array( $internal_id ) )
	: new WP_Error( 'skipped', 'skipped' );
$assert( true === $internal_link_ok, 'link_internal_budget' );

$internal_before = $internal_id > 0 ? SOM_Products::get( $internal_id ) : null;
$original_material_id = ( $internal_before && ! empty( $internal_before->linked_material_id ) ) ? (int) $internal_before->linked_material_id : 0;
$assert( $original_material_id > 0, 'internal_has_linked_material' );
$stock_ok = $original_material_id > 0 ? SOM_Materials::adjust_stock( $original_material_id, 4 ) : new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $stock_ok ), 'stock_original_material' );
$original_material = $original_material_id > 0 ? SOM_Materials::get( $original_material_id ) : null;
$assert( $original_material && '4.00' === $money( $original_material->current_stock ), 'original_stock_is_4' );
$original_logs = $original_material_id > 0 ? $stock_log_count( $original_material_id ) : 0;

$internal_recipe_before = $internal_id > 0 ? $recipe_sig( $internal_id ) : array();
$internal_instr_before  = $internal_id > 0 ? SOM_Step_Instructions::get_overrides_for_product( $internal_id ) : array();
$internal_tasks_before  = $internal_id > 0 ? SOM_Step_Tasks::get_overrides_for_product( $internal_id ) : array();
$internal_budgets_before = $internal_id > 0 ? $budget_ids_for( $internal_id ) : array();

$internal_clone_id = 0;
if ( $internal_id > 0 ) {
	$cloned = SOM_Products::clone_product( $internal_id );
	$assert( ! is_wp_error( $cloned ) && (int) $cloned > 0 && (int) $cloned !== $internal_id, 'clone_internal' );
	$internal_clone_id = is_wp_error( $cloned ) ? 0 : (int) $cloned;
}

$internal_copy   = $internal_clone_id > 0 ? SOM_Products::get( $internal_clone_id ) : null;
$internal_source = $internal_id > 0 ? SOM_Products::get( $internal_id ) : null;
$copy_material_id = ( $internal_copy && ! empty( $internal_copy->linked_material_id ) ) ? (int) $internal_copy->linked_material_id : 0;
$copy_material    = $copy_material_id > 0 ? SOM_Materials::get( $copy_material_id ) : null;
$source_material  = ( $internal_source && ! empty( $internal_source->linked_material_id ) ) ? SOM_Materials::get( (int) $internal_source->linked_material_id ) : null;

$assert( $internal_copy && $internal_name . ' (copy)' === (string) $internal_copy->name, 'internal_copy_name' );
$assert( $internal_copy && null === $internal_copy->sku, 'internal_copy_sku_null' );
$assert( $internal_copy && $make_id === (int) $internal_copy->workflow_template_id, 'internal_copy_same_workflow' );
$assert( $internal_copy && empty( $internal_copy->post_ship_workflow_template_id ), 'internal_copy_no_post_ship' );
$assert( $internal_copy && 1 === (int) $internal_copy->is_internal, 'internal_copy_stays_internal' );
$assert( $internal_copy && 1 === (int) $internal_copy->is_active, 'internal_copy_stays_active' );
$assert( $internal_copy && '4.00' === $money( $internal_copy->target_selling_price ), 'internal_copy_price' );
$assert( $internal_clone_id > 0 && $internal_recipe_before === $recipe_sig( $internal_clone_id ), 'internal_recipe_copied' );
$assert( $internal_clone_id > 0 && $internal_instr_before === SOM_Step_Instructions::get_overrides_for_product( $internal_clone_id ), 'internal_instructions_copied' );
$assert( $internal_clone_id > 0 && isset( $internal_instr_before[ $step_a ] ) && $internal_instr === $internal_instr_before[ $step_a ], 'internal_instruction_same_step' );
$assert( $internal_clone_id > 0 && $internal_tasks_before === SOM_Step_Tasks::get_overrides_for_product( $internal_clone_id ), 'internal_tasks_copied' );
$assert( $internal_clone_id > 0 && $internal_budgets_before === $budget_ids_for( $internal_clone_id ) && array( $internal_budget_id ) === $budget_ids_for( $internal_clone_id ), 'internal_budget_links_copied' );
$assert( $internal_clone_id > 0 && array() === $listing_ids_for( $internal_clone_id ), 'internal_listings_not_copied' );
$assert( $copy_material_id > 0 && $copy_material_id !== $original_material_id, 'internal_clone_new_material' );
$assert( $copy_material && '0.00' === $money( $copy_material->current_stock ), 'internal_clone_stock_zero' );
$assert( $copy_material && 0 === $stock_log_count( $copy_material_id ), 'internal_clone_no_stock_log' );
$assert( $copy_material && $internal_clone_id === (int) $copy_material->source_product_id, 'internal_clone_material_owner' );
$assert( $copy_material && $internal_name . ' (copy)' === (string) $copy_material->name, 'internal_clone_material_name' );
$assert( $internal_source && $original_material_id === (int) $internal_source->linked_material_id, 'original_keeps_linked_material' );
$assert( $source_material && '4.00' === $money( $source_material->current_stock ), 'original_stock_unchanged' );
$assert( $original_material_id > 0 && $original_logs === $stock_log_count( $original_material_id ), 'original_stock_log_unchanged' );
$assert( $internal_source && 'UP12S7I-' . $suffix === (string) $internal_source->sku, 'internal_original_sku_unchanged' );
$assert( $source_material && $internal_id === (int) $source_material->source_product_id, 'original_material_owner_unchanged' );

$missing = SOM_Products::clone_product( 0 );
$assert( is_wp_error( $missing ) && 'som_product_missing' === $missing->get_error_code(), 'missing_product_errors' );

$bad_name = 'UP12-S7 missing workflow ' . $suffix;
$bad      = $make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => $bad_name,
			'workflow_template_id' => $make_id,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $bad ) && (int) $bad > 0, 'create_bad_workflow_product' );
$bad_id = is_wp_error( $bad ) ? 0 : (int) $bad;
if ( $bad_id > 0 ) {
	$wpdb->update(
		SOM_DB::table( 'products' ),
		array( 'workflow_template_id' => 999999999 ),
		array( 'id' => $bad_id ),
		array( '%d' ),
		array( '%d' )
	);
}
$before_bad = $product_count();
$bad_clone  = $bad_id > 0 ? SOM_Products::clone_product( $bad_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( is_wp_error( $bad_clone ) && 'som_workflow_missing' === $bad_clone->get_error_code(), 'missing_workflow_errors' );
$assert( $before_bad === $product_count(), 'missing_workflow_no_new_product' );
$assert( 0 === $name_count( $bad_name . ' (copy)' ), 'missing_workflow_copy_name_absent' );

$bare_name = 'UP12-S7 bare internal ' . $suffix;
$bare      = $make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => $bare_name,
			'workflow_template_id' => $make_id,
			'is_internal'          => 1,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $bare ) && (int) $bare > 0, 'create_bare_internal' );
$bare_id = is_wp_error( $bare ) ? 0 : (int) $bare;
if ( $bare_id > 0 ) {
	$wpdb->update(
		SOM_DB::table( 'products' ),
		array(
			'workflow_template_id' => null,
		),
		array( 'id' => $bare_id ),
		array( '%s' ),
		array( '%d' )
	);
}
$before_bare           = $product_count();
$before_bare_materials = $material_count();
$bare_clone            = $bare_id > 0 ? SOM_Products::clone_product( $bare_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( is_wp_error( $bare_clone ) && 'som_product_internal_workflow' === $bare_clone->get_error_code(), 'internal_without_workflow_errors' );
$assert( $before_bare === $product_count(), 'internal_without_workflow_no_new_product' );
$assert( $before_bare_materials === $material_count(), 'internal_without_workflow_no_new_material' );
$assert( 0 === $name_count( $bare_name . ' (copy)' ), 'internal_without_workflow_copy_name_absent' );

$long_name = str_repeat( 'P', 100 );
$long      = $make_id > 0
	? SOM_Products::create(
		array(
			'name'                 => $long_name,
			'workflow_template_id' => $make_id,
			'is_active'            => 1,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $long ) && (int) $long > 0, 'create_long_name' );
$long_id = is_wp_error( $long ) ? 0 : (int) $long;
if ( $long_id > 0 ) {
	$long_row = SOM_Products::get( $long_id );
	if ( $long_row && som_up12_s7_strlen( (string) $long_row->name ) < 100 ) {
		$wpdb->update(
			SOM_DB::table( 'products' ),
			array( 'name' => $long_name ),
			array( 'id' => $long_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
$long_clone = $long_id > 0 ? SOM_Products::clone_product( $long_id ) : 0;
$assert( ! is_wp_error( $long_clone ) && (int) $long_clone > 0, 'clone_long_name' );
$long_copy = ! is_wp_error( $long_clone ) && (int) $long_clone > 0 ? SOM_Products::get( (int) $long_clone ) : null;
$expected_long = str_repeat( 'P', 93 ) . ' (copy)';
$assert( $long_copy && $expected_long === (string) $long_copy->name, 'long_name_trimmed' );
$assert( $long_copy && 100 === som_up12_s7_strlen( (string) $long_copy->name ), 'long_name_fits_column' );

if ( $fail > 0 ) {
	echo "FAIL — UP12-S7 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP12-S7 smoke\n";
