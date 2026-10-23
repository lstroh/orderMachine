<?php
/**
 * UP14-S3 smoke: product CSV creates a product and its recipe, and does not create listings.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s3-smoke.php
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

$count_table = static function ( $table ) {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( $table ) );
};

$count_named = static function ( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'products' ) . ' WHERE name = %s',
			$name
		)
	);
};

$find_product = static function ( $name ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . SOM_DB::table( 'products' ) . ' WHERE name = %s ORDER BY id ASC LIMIT 1',
			$name
		)
	);
};

$count_material_name = static function ( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'materials' ) . ' WHERE name = %s',
			$name
		)
	);
};

$row_by_code = static function ( $parsed, $code ) {
	if ( empty( $parsed['rows'] ) || ! is_array( $parsed['rows'] ) ) {
		return null;
	}
	foreach ( $parsed['rows'] as $row ) {
		if ( isset( $row['code'] ) && $code === $row['code'] ) {
			return $row;
		}
	}
	return null;
};

$row_by_file_name = static function ( $parsed, $name ) {
	if ( empty( $parsed['rows'] ) || ! is_array( $parsed['rows'] ) ) {
		return null;
	}
	foreach ( $parsed['rows'] as $row ) {
		if ( isset( $row['file_name'] ) && $name === $row['file_name'] ) {
			return $row;
		}
	}
	return null;
};

$suffix   = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );
$columns  = array(
	'name',
	'sku',
	'is_internal',
	'workflow_name',
	'post_ship_workflow_name',
	'pack_workflow_name',
	'target_selling_price',
	'weight_grams',
	'package_name',
	'package_name_multi',
	'planned_shipping_gbp',
	'is_active',
	'material_name',
	'quantity_per_unit',
);
$header   = implode( ',', $columns );
$line     = static function ( array $cells ) use ( $columns ) {
	$out = array();
	foreach ( $columns as $column ) {
		$out[] = isset( $cells[ $column ] ) ? (string) $cells[ $column ] : '';
	}
	return implode( ',', $out );
};

$paper_name = 'UP14 Paper ' . $suffix;
$ink_name   = 'UP14 Ink ' . $suffix;
$make_name  = 'UP14 Make ' . $suffix;
$post_name  = 'UP14 Post ' . $suffix;
$pack_name  = 'UP14 Mailer ' . $suffix;
$good       = 'UP14 Sticker ' . $suffix;
$bad        = 'UP14 Missing material ' . $suffix;
$unknown    = 'UP14 No such material ' . $suffix;
$sku        = 'UP14-SKU-' . $suffix;
$internal   = 'UP14 Component ' . $suffix;
$internal_sku = 'UP14-INT-' . $suffix;

$out( 'plugin', SOM_VERSION );
$assert( class_exists( 'SOM_Product_Import', false ), 'product_import_class' );
$assert( 'products' === SOM_Product_Import::KIND, 'preview_kind' );
$assert( $columns === SOM_Product_Import::COLUMNS, 'column_list' );
$assert( in_array( 'pack_workflow_name', SOM_Product_Import::COLUMNS, true ), 'optional_pack_workflow_column' );
$assert( method_exists( 'SOM_Products', 'delete_failed_import' ), 'rollback_method' );

$import_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-product-import.php' );
$list_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/products-list.php' );
$menu_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );

$assert( false !== strpos( $import_src, 'SOM_Products::create' ), 'creates_through_products' );
$assert( false !== strpos( $import_src, 'SOM_Products::save_recipe' ), 'saves_recipe' );
$assert( false !== strpos( $import_src, 'SOM_Products::delete_failed_import' ), 'rolls_back_half_product' );
$assert( false === strpos( $import_src, 'SOM_Products::update' ), 'import_does_not_update' );
$assert( false === strpos( $import_src, 'SOM_Listings' ), 'import_does_not_touch_listings' );
$assert( false === strpos( $import_src, 'linked_material_id' ), 'import_does_not_set_linked_material' );

$assert( false !== strpos( $list_src, 'Export CSV' ), 'export_button' );
$assert( false !== strpos( $list_src, 'Download template' ), 'template_button' );
$assert( false !== strpos( $list_src, 'name="som_import_file"' ), 'import_file_input' );
$assert( false !== strpos( $list_src, 'som_preview_products' ), 'preview_button' );
$assert( false !== strpos( $list_src, 'som_import_products' ), 'import_button' );
$assert( false !== strpos( $list_src, "wp_nonce_field( 'som_import_products_preview'" ), 'preview_nonce' );
$assert( false !== strpos( $list_src, "wp_nonce_field( 'som_import_products_commit'" ), 'commit_nonce' );
$assert( false !== strpos( $list_src, 'som_product_export' ), 'export_nonce_url' );
$assert( false !== strpos( $list_src, 'som_product_template' ), 'template_nonce_url' );

$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_product_export' )" ), 'export_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_product_template' )" ), 'template_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_import_products_preview', 'som_product_nonce' )" ), 'preview_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_import_products_commit', 'som_product_nonce' )" ), 'commit_nonce_check' );
$assert( false !== strpos( $menu_src, 'Created %1$d, failed %2$d.' ), 'result_copy' );

$template = SOM_Product_Import::template_csv();
$assert( str_starts_with( $template, "\xEF\xBB\xBF" ), 'template_has_bom' );
$assert( $header === trim( substr( $template, 3 ) ), 'template_header_only' );

$paper_id = SOM_Materials::create(
	array(
		'name' => $paper_name,
		'unit' => 'sheet',
	)
);
$ink_id = SOM_Materials::create(
	array(
		'name' => $ink_name,
		'unit' => 'ml',
	)
);
$make_id = SOM_Workflows::create(
	array(
		'name' => $make_name,
		'kind' => 'make',
	)
);
$post_id = SOM_Workflows::create(
	array(
		'name' => $post_name,
		'kind' => 'post_ship',
	)
);
$package_id = SOM_Shipping_Packages::create(
	array(
		'name'              => $pack_name,
		'length_mm'         => 200,
		'width_mm'          => 150,
		'height_mm'         => 20,
		'tare_weight_grams' => '5',
		'is_active'         => 1,
	)
);
$assert( ! is_wp_error( $paper_id ) && (int) $paper_id > 0, 'paper_created' );
$assert( ! is_wp_error( $ink_id ) && (int) $ink_id > 0, 'ink_created' );
$assert( ! is_wp_error( $make_id ) && (int) $make_id > 0, 'make_created' );
$assert( ! is_wp_error( $post_id ) && (int) $post_id > 0, 'post_ship_created' );
$assert( ! is_wp_error( $package_id ) && (int) $package_id > 0, 'package_created' );
$paper_id   = is_wp_error( $paper_id ) ? 0 : (int) $paper_id;
$ink_id     = is_wp_error( $ink_id ) ? 0 : (int) $ink_id;
$make_id    = is_wp_error( $make_id ) ? 0 : (int) $make_id;
$post_id    = is_wp_error( $post_id ) ? 0 : (int) $post_id;
$package_id = is_wp_error( $package_id ) ? 0 : (int) $package_id;

$shared = array(
	'workflow_name'            => $make_name,
	'post_ship_workflow_name'  => $post_name,
	'target_selling_price'     => '4.50',
	'weight_grams'             => '10',
	'package_name'             => $pack_name,
	'planned_shipping_gbp'     => '1.25',
	'is_active'                => 'yes',
);
$csv = "\xEF\xBB\xBF" . $header . "\n"
	. $line(
		array_merge(
			$shared,
			array(
				'name'              => $bad,
				'sku'               => 'UP14-BAD-' . $suffix,
				'material_name'     => $paper_name,
				'quantity_per_unit' => '1',
			)
		)
	) . "\n"
	. $line(
		array_merge(
			$shared,
			array(
				'name'              => $bad,
				'sku'               => 'UP14-BAD-' . $suffix,
				'material_name'     => $unknown,
				'quantity_per_unit' => '2',
			)
		)
	) . "\n"
	. $line(
		array_merge(
			$shared,
			array(
				'name'              => $good,
				'sku'               => $sku,
				'material_name'     => $paper_name,
				'quantity_per_unit' => '2',
			)
		)
	) . "\n"
	. $line(
		array_merge(
			$shared,
			array(
				'name'              => $good,
				'sku'               => $sku,
				'material_name'     => $ink_name,
				'quantity_per_unit' => '0.5',
			)
		)
	) . "\n";

$products_before  = $count_table( 'products' );
$materials_before = $count_table( 'materials' );
$workflows_before = $count_table( 'workflow_templates' );
$listings_before  = $count_table( 'listings' );
$packages_before  = $count_table( 'shipping_packages' );

$parsed = SOM_Product_Import::parse( $csv );
$assert( $products_before === $count_table( 'products' ), 'parse_does_not_insert' );
$assert( '' === $parsed['file_error'], 'parse_file_ok' );
$assert( 1 === (int) $parsed['ready'], 'parse_ready_one' );
$assert( 1 === (int) $parsed['failed'], 'parse_failed_one' );
$bad_row = $row_by_code( $parsed, 'unknown_material' );
$assert( is_array( $bad_row ) && $bad === $bad_row['file_name'], 'unknown_material_fails_product' );
$good_row = $row_by_file_name( $parsed, $good );
$assert( is_array( $good_row ) && ! empty( $good_row['ok'] ) && $good === $good_row['save_name'], 'preview_keeps_free_name' );
$assert( is_array( $good_row ) && '' === (string) $good_row['warning'], 'new_sku_has_no_warning' );

$first = SOM_Product_Import::commit( $csv );
$assert( 1 === (int) $first['created'], 'first_created' );
$assert( 1 === (int) $first['failed'], 'first_failed' );
$assert( 0 === (int) $first['warnings'], 'first_warnings' );
$assert( 1 === $count_named( $good ), 'good_product_saved' );
$assert( 0 === $count_named( $bad ), 'bad_product_not_saved' );
$assert( 0 === $count_material_name( $unknown ), 'unknown_material_not_created' );
$assert( $materials_before === $count_table( 'materials' ), 'sellable_import_creates_no_material' );
$assert( $workflows_before === $count_table( 'workflow_templates' ), 'import_creates_no_workflow' );
$assert( $packages_before === $count_table( 'shipping_packages' ), 'import_creates_no_package' );
$assert( $listings_before === $count_table( 'listings' ), 'listings_not_created' );

$product = $find_product( $good );
$assert( $product && $sku === (string) $product->sku, 'file_sku_stored' );
$assert( $product && 0 === (int) $product->is_internal, 'blank_internal_means_sellable' );
$assert( $product && 1 === (int) $product->is_active, 'yes_means_active' );
$assert( $product && $make_id === (int) $product->workflow_template_id, 'workflow_matched' );
$assert( $product && $post_id === (int) $product->post_ship_workflow_template_id, 'post_ship_matched' );
$assert( $product && $package_id === (int) $product->package_id, 'package_matched' );
$assert( $product && empty( $product->linked_material_id ), 'sellable_has_no_output_material' );
$assert( $product && 4.5 === (float) $product->target_selling_price, 'price_stored' );
$good_id = $product ? (int) $product->id : 0;
$recipe  = $good_id > 0 ? SOM_Products::get_recipe( $good_id ) : array();
$assert( 2 === count( $recipe ), 'recipe_line_count' );
$assert(
	isset( $recipe[0], $recipe[1] )
	&& $paper_id === (int) $recipe[0]->material_id
	&& 2.0 === (float) $recipe[0]->quantity_per_unit
	&& $ink_id === (int) $recipe[1]->material_id
	&& 0.5 === (float) $recipe[1]->quantity_per_unit,
	'recipe_lines_attach_by_material_name'
);
$assert( array() === SOM_Products::get_listings( $good_id ), 'product_has_no_listings' );

$export = SOM_Product_Import::export_csv();
$assert( str_starts_with( $export, "\xEF\xBB\xBF" ), 'export_has_bom' );
$export_body = substr( $export, 3 );
$assert( $header === trim( (string) strtok( $export_body, "\n" ) ), 'export_header' );
$assert( 2 === substr_count( $export_body, $good ), 'export_one_row_per_recipe_line' );

$second_view = SOM_Product_Import::parse( $csv );
$second_row  = $row_by_file_name( $second_view, $good );
$assert(
	is_array( $second_row ) && $good . ' (2)' === (string) $second_row['save_name'],
	'second_preview_suffix'
);
$assert(
	is_array( $second_row ) && false !== strpos( (string) $second_row['warning'], 'SKU' ),
	'suffix_preview_lists_sku_warning'
);
$second = SOM_Product_Import::commit( $csv );
$assert( 1 === (int) $second['created'] && 1 === (int) $second['failed'], 'second_counts' );
$assert( 1 === (int) $second['warnings'], 'second_sku_warning_counted' );
$copy_name = $good . ' (2)';
$assert( 1 === $count_named( $good ), 'second_import_did_not_duplicate_exact_name' );
$assert( 1 === $count_named( $copy_name ), 'second_import_suffix' );
$copy = $find_product( $copy_name );
$assert( $copy && $good_id !== (int) $copy->id, 'second_import_new_product' );
$assert( $copy && $sku === (string) $copy->sku, 'suffixed_name_keeps_file_sku' );
$copy_id     = $copy ? (int) $copy->id : 0;
$copy_recipe = $copy_id > 0 ? SOM_Products::get_recipe( $copy_id ) : array();
$assert(
	2 === count( $copy_recipe )
	&& isset( $copy_recipe[0], $copy_recipe[1] )
	&& $paper_id === (int) $copy_recipe[0]->material_id
	&& $ink_id === (int) $copy_recipe[1]->material_id,
	'suffix_keeps_recipe'
);
$original = $good_id > 0 ? SOM_Products::get( $good_id ) : null;
$assert( $original && $good === (string) $original->name, 'original_name_unchanged' );
$assert( $original && $sku === (string) $original->sku, 'original_sku_unchanged' );
$assert( $listings_before === $count_table( 'listings' ), 'suffix_import_creates_no_listings' );

$existing_material_id = SOM_Materials::create(
	array(
		'name' => $internal,
		'unit' => 'each',
	)
);
$assert( ! is_wp_error( $existing_material_id ) && (int) $existing_material_id > 0, 'same_name_material_created' );
$existing_material_id = is_wp_error( $existing_material_id ) ? 0 : (int) $existing_material_id;
$materials_before_internal = $count_table( 'materials' );
$internal_csv              = $header . "\n" . $line(
	array(
		'name'              => $internal,
		'sku'               => $internal_sku,
		'is_internal'       => 'yes',
		'workflow_name'     => $make_name,
		'material_name'     => $paper_name,
		'quantity_per_unit' => '3',
	)
) . "\n";
$internal_commit = SOM_Product_Import::commit( $internal_csv );
$assert( 1 === (int) $internal_commit['created'] && 0 === (int) $internal_commit['failed'], 'internal_created' );
$assert( $materials_before_internal + 1 === $count_table( 'materials' ), 'internal_adds_one_output_material' );
$internal_row = $find_product( $internal );
$assert( $internal_row && 1 === (int) $internal_row->is_internal, 'internal_flag' );
$assert( $internal_row && $internal_sku === (string) $internal_row->sku, 'internal_sku' );
$assert( $internal_row && $make_id === (int) $internal_row->workflow_template_id, 'internal_workflow' );
$linked_id = $internal_row ? (int) $internal_row->linked_material_id : 0;
$assert( $linked_id > 0 && $linked_id !== $existing_material_id, 'internal_output_is_a_new_material' );
$linked = $linked_id > 0 ? SOM_Materials::get( $linked_id ) : null;
$assert( $linked && (int) $internal_row->id === (int) $linked->source_product_id, 'output_material_points_at_new_product' );
$assert( $linked && 0.0 === (float) $linked->current_stock, 'output_material_stock_zero' );
$assert( $linked && $internal === (string) $linked->name, 'output_material_uses_product_name' );
$existing_material = SOM_Materials::get( $existing_material_id );
$assert( $existing_material && empty( $existing_material->source_product_id ), 'existing_material_not_linked' );
$internal_recipe = $internal_row ? SOM_Products::get_recipe( (int) $internal_row->id ) : array();
$assert(
	1 === count( $internal_recipe )
	&& isset( $internal_recipe[0] )
	&& $paper_id === (int) $internal_recipe[0]->material_id
	&& 3.0 === (float) $internal_recipe[0]->quantity_per_unit,
	'internal_recipe_uses_named_material'
);
$assert( array() === SOM_Products::get_listings( $internal_row ? (int) $internal_row->id : 0 ), 'internal_has_no_listings' );
$assert( $listings_before === $count_table( 'listings' ), 'listings_still_unchanged' );

$internal_id = $internal_row ? (int) $internal_row->id : 0;
SOM_Products::delete_failed_import( $internal_id );
$assert( 0 === $count_named( $internal ), 'rollback_deletes_product' );
$assert( null === SOM_Materials::get( $linked_id ), 'rollback_deletes_output_material' );
$assert( $existing_material_id === (int) ( SOM_Materials::get( $existing_material_id ) ? SOM_Materials::get( $existing_material_id )->id : 0 ), 'rollback_keeps_other_material' );
$assert( $paper_id === (int) ( SOM_Materials::get( $paper_id ) ? SOM_Materials::get( $paper_id )->id : 0 ), 'rollback_keeps_recipe_material' );

$plain = 'UP14 Plain ' . $suffix;
$plain_commit = SOM_Product_Import::commit( $header . "\n" . $line( array( 'name' => $plain ) ) . "\n" );
$assert( 1 === (int) $plain_commit['created'], 'blank_recipe_created' );
$plain_row = $find_product( $plain );
$assert( $plain_row && 1 === (int) $plain_row->is_active, 'blank_active_means_active' );
$assert( $plain_row && array() === SOM_Products::get_recipe( (int) $plain_row->id ), 'blank_recipe_has_no_lines' );

$split = 'UP14 Split ' . $suffix;
$split_csv = $header . "\n"
	. $line(
		array(
			'name'              => $split,
			'sku'               => 'ONE',
			'material_name'     => $paper_name,
			'quantity_per_unit' => '1',
		)
	) . "\n"
	. $line(
		array(
			'name'              => $split,
			'sku'               => 'TWO',
			'material_name'     => $ink_name,
			'quantity_per_unit' => '1',
		)
	) . "\n";
$split_view = SOM_Product_Import::parse( $split_csv );
$split_row  = $row_by_code( $split_view, 'header_disagreement' );
$assert( is_array( $split_row ) && $split === $split_row['file_name'], 'header_disagreement_row' );
$products_before_split = $count_table( 'products' );
$split_commit          = SOM_Product_Import::commit( $split_csv );
$assert( 0 === (int) $split_commit['created'] && 1 === (int) $split_commit['failed'], 'header_disagreement_creates_nothing' );
$assert( $products_before_split === $count_table( 'products' ), 'header_disagreement_count' );

$no_flow = 'UP14 No flow ' . $suffix;
$no_flow_csv = $header . "\n" . $line(
	array(
		'name'              => $no_flow,
		'is_internal'       => '1',
		'material_name'     => $paper_name,
		'quantity_per_unit' => '1',
	)
) . "\n";
$no_flow_view = SOM_Product_Import::parse( $no_flow_csv );
$assert( is_array( $row_by_code( $no_flow_view, 'missing_workflow' ) ), 'internal_requires_workflow' );
$no_flow_commit = SOM_Product_Import::commit( $no_flow_csv );
$assert( 0 === (int) $no_flow_commit['created'] && 0 === $count_named( $no_flow ), 'internal_without_workflow_not_saved' );

$wrong_kind = 'UP14 Wrong kind ' . $suffix;
$wrong_kind_view = SOM_Product_Import::parse(
	$header . "\n" . $line(
		array(
			'name'          => $wrong_kind,
			'workflow_name' => $post_name,
		)
	) . "\n"
);
$assert( is_array( $row_by_code( $wrong_kind_view, 'unknown_workflow' ) ), 'post_ship_name_is_not_a_make_workflow' );

$missing_pkg = 'UP14 Missing package ' . $suffix;
$missing_pkg_commit = SOM_Product_Import::commit(
	$header . "\n" . $line(
		array(
			'name'         => $missing_pkg,
			'package_name' => 'UP14 No such package ' . $suffix,
		)
	) . "\n"
);
$assert( 0 === (int) $missing_pkg_commit['created'] && 0 === $count_named( $missing_pkg ), 'unknown_package_fails_product' );

$twin_a = 'UP14 Twin ' . $suffix;
$twin_b = 'up14 twin ' . $suffix;
$twin_1 = SOM_Materials::create(
	array(
		'name' => $twin_a,
		'unit' => 'sheet',
	)
);
$twin_2 = SOM_Materials::create(
	array(
		'name' => $twin_b,
		'unit' => 'sheet',
	)
);
$assert( ! is_wp_error( $twin_1 ) && ! is_wp_error( $twin_2 ), 'ambiguous_materials_created' );
$amb = 'UP14 Ambiguous ' . $suffix;
$amb_commit = SOM_Product_Import::commit(
	$header . "\n" . $line(
		array(
			'name'              => $amb,
			'material_name'     => $twin_a,
			'quantity_per_unit' => '1',
		)
	) . "\n"
);
$assert( 0 === (int) $amb_commit['created'] && 0 === $count_named( $amb ), 'ambiguous_material_fails_product' );

$tab_name = 'UP14 Tab ' . $suffix;
$tab      = SOM_Product_Import::parse( "name\tmaterial_name\tquantity_per_unit\n{$tab_name}\t{$paper_name}\t1\n" );
$assert(
	'' === $tab['file_error'] && 1 === (int) $tab['ready']
	&& isset( $tab['rows'][0]['save_name'] ) && $tab_name === $tab['rows'][0]['save_name'],
	'tab_delimiter'
);

$user  = get_current_user_id();
$token = SOM_Catalog_Import::remember( SOM_Product_Import::KIND, $user, $csv );
$assert( $csv === SOM_Catalog_Import::recall( SOM_Product_Import::KIND, $user, $token ), 'preview_token_recall' );
$assert( '' === SOM_Catalog_Import::recall( 'materials', $user, $token ), 'preview_token_kind' );
SOM_Catalog_Import::forget( SOM_Product_Import::KIND, $user, $token );
$assert( '' === SOM_Catalog_Import::recall( SOM_Product_Import::KIND, $user, $token ), 'preview_token_forget' );

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	echo "FAIL — UP14-S3 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S3 smoke\n";
