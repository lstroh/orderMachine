<?php
/**
 * UP14-S1 smoke: materials CSV creates rows, suffixes a taken name, and skips made-in-house export.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s1-smoke.php
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

$find = static function ( $name ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . SOM_DB::table( 'materials' ) . ' WHERE name = %s LIMIT 1',
			$name
		)
	);
};

$count_named = static function ( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'materials' ) . ' WHERE name = %s',
			$name
		)
	);
};

$material_count = static function () {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'materials' ) );
};

$stock_logs = static function ( $material_id ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'material_stock_log' ) . ' WHERE material_id = %d',
			(int) $material_id
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

$suffix       = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );
$good         = 'UP14 Vinyl ' . $suffix;
$bad          = 'UP14 Missing supplier ' . $suffix;
$supplier     = 'UP14 Supplier ' . $suffix;
$unknown      = 'UP14 No such supplier ' . $suffix;
$header       = 'name,unit,low_stock_threshold,unit_cost,preferred_supplier,is_active';
$columns      = array( 'name', 'unit', 'low_stock_threshold', 'unit_cost', 'preferred_supplier', 'is_active' );

$out( 'plugin', SOM_VERSION );
$assert( class_exists( 'SOM_Catalog_Import', false ), 'helper_class' );
$assert( class_exists( 'SOM_Material_Import', false ), 'material_import_class' );
$assert( method_exists( 'SOM_Catalog_Import', 'allocate_name' ), 'helper_allocate_name' );
$assert( method_exists( 'SOM_Catalog_Import', 'remember' ), 'helper_remember' );
$assert( method_exists( 'SOM_Catalog_Import', 'recall' ), 'helper_recall' );
$assert( method_exists( 'SOM_Catalog_Import', 'forget' ), 'helper_forget' );
$assert( $columns === SOM_Material_Import::COLUMNS, 'column_list' );

$import_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-material-import.php' );
$list_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/materials-list.php' );
$menu_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );

$assert( false !== strpos( $import_src, 'SOM_Materials::create' ), 'creates_through_materials' );
$assert( false === strpos( $import_src, 'SOM_Materials::update' ), 'import_does_not_update' );
$assert( false === strpos( $import_src, 'adjust_stock' ), 'import_does_not_adjust_stock' );
$assert( false !== strpos( $import_src, 'source_product_id IS NULL' ), 'export_sql_omits_made_in_house' );

$assert( false !== strpos( $list_src, 'Export CSV' ), 'export_button' );
$assert( false !== strpos( $list_src, 'Download template' ), 'template_button' );
$assert( false !== strpos( $list_src, 'name="som_import_file"' ), 'import_file_input' );
$assert( false !== strpos( $list_src, 'som_preview_materials' ), 'preview_button' );
$assert( false !== strpos( $list_src, 'som_import_materials' ), 'import_button' );
$assert( false !== strpos( $list_src, "wp_nonce_field( 'som_import_materials_preview'" ), 'preview_nonce' );
$assert( false !== strpos( $list_src, "wp_nonce_field( 'som_import_materials_commit'" ), 'commit_nonce' );
$assert( false !== strpos( $list_src, 'som_material_export' ), 'export_nonce_url' );
$assert( false !== strpos( $list_src, 'som_material_template' ), 'template_nonce_url' );

$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_material_export' )" ), 'export_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_material_template' )" ), 'template_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_import_materials_preview', 'som_material_nonce' )" ), 'preview_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_import_materials_commit', 'som_material_nonce' )" ), 'commit_nonce_check' );
$assert( false !== strpos( $menu_src, 'Created %1$d, failed %2$d.' ), 'result_copy' );

$template = SOM_Material_Import::template_csv();
$assert( str_starts_with( $template, "\xEF\xBB\xBF" ), 'template_has_bom' );
$template_body = substr( $template, 3 );
$assert( $header === trim( $template_body ), 'template_header_only' );

$taken = array();
$first_name = SOM_Catalog_Import::allocate_name( 'Shared', $taken );
$second_name = SOM_Catalog_Import::allocate_name( 'shared', $taken );
$assert( 'Shared' === $first_name && 'shared (2)' === $second_name, 'suffix_keeps_file_case' );

$long_base = str_repeat( 'L', 100 );
$long_taken = array( SOM_Catalog_Import::name_key( $long_base ) => true );
$long_name  = SOM_Catalog_Import::allocate_name( $long_base, $long_taken );
$assert( ( str_repeat( 'L', 96 ) . ' (2)' ) === $long_name, 'long_name_trimmed' );
$assert( ! is_wp_error( $long_name ) && 100 === SOM_Catalog_Import::text_length( (string) $long_name ), 'long_name_fits' );

$over_taken = array();
$over       = SOM_Catalog_Import::allocate_name( str_repeat( 'Z', 101 ), $over_taken );
$assert( is_wp_error( $over ), 'free_name_over_limit_fails' );
$assert( array() === $over_taken, 'failed_name_not_reserved' );

$sample = "name,unit\nToken,sheet\n";
$user   = get_current_user_id();
$token  = SOM_Catalog_Import::remember( 'materials', $user, $sample );
$assert( $sample === SOM_Catalog_Import::recall( 'materials', $user, $token ), 'preview_token_recall' );
$assert( '' === SOM_Catalog_Import::recall( 'workflows', $user, $token ), 'preview_token_kind' );
SOM_Catalog_Import::forget( 'materials', $user, $token );
$assert( '' === SOM_Catalog_Import::recall( 'materials', $user, $token ), 'preview_token_forget' );

$supplier_id = SOM_Suppliers::create( array( 'name' => $supplier ) );
$assert( ! is_wp_error( $supplier_id ) && (int) $supplier_id > 0, 'supplier_created' );
$supplier_id = is_wp_error( $supplier_id ) ? 0 : (int) $supplier_id;

$csv = $header . "\n"
	. $good . ',sheet,2,1.25,' . $supplier . ",yes\n"
	. $bad . ',sheet,,,' . $unknown . ",\n";

$before = $material_count();
$parsed = SOM_Material_Import::parse( $csv );
$assert( $before === $material_count(), 'parse_does_not_insert' );
$assert( '' === $parsed['file_error'], 'parse_file_ok' );
$assert( 1 === (int) $parsed['ready'], 'parse_ready_one' );
$assert( 1 === (int) $parsed['failed'], 'parse_failed_one' );
$assert( isset( $parsed['rows'][0]['save_name'] ) && $good === $parsed['rows'][0]['save_name'], 'preview_keeps_free_name' );
$bad_row = $row_by_code( $parsed, 'unknown_supplier' );
$assert( is_array( $bad_row ) && $bad === $bad_row['file_name'], 'unknown_supplier_row' );

$first = SOM_Material_Import::commit( $csv );
$assert( 1 === (int) $first['created'], 'first_created' );
$assert( 1 === (int) $first['failed'], 'first_failed' );
$assert( 1 === $count_named( $good ), 'good_row_saved' );
$assert( 0 === $count_named( $bad ), 'bad_row_not_saved' );

$good_row = $find( $good );
$assert( $good_row && 0.0 === (float) $good_row->current_stock, 'stock_stays_zero' );
$assert( $good_row && 0 === $stock_logs( (int) $good_row->id ), 'no_stock_log' );
$assert( $good_row && empty( $good_row->source_product_id ), 'source_product_not_set' );
$assert( $good_row && 1.25 === (float) $good_row->unit_cost, 'unit_cost_stored' );
$assert( $good_row && 2.0 === (float) $good_row->low_stock_threshold, 'threshold_stored' );
$assert( $good_row && $supplier_id === (int) $good_row->preferred_supplier_id, 'supplier_matched' );
$assert( $good_row && 1 === (int) $good_row->is_active, 'active_from_yes' );
$good_id = $good_row ? (int) $good_row->id : 0;

$second = SOM_Material_Import::commit( $csv );
$assert( 1 === (int) $second['created'], 'second_created' );
$assert( 1 === (int) $second['failed'], 'second_failed' );
$copy_name = $good . ' (2)';
$assert( 1 === $count_named( $good ), 'second_import_did_not_duplicate_exact_name' );
$assert( 1 === $count_named( $copy_name ), 'second_import_suffix' );
$copy = $find( $copy_name );
$assert( $copy && $good_id !== (int) $copy->id, 'second_import_new_row' );
$assert( $copy && 0.0 === (float) $copy->current_stock, 'copy_stock_zero' );
$assert( $copy && 0 === $stock_logs( (int) $copy->id ), 'copy_no_stock_log' );
$assert( $copy && empty( $copy->source_product_id ), 'copy_source_product_not_set' );
$original = $good_id > 0 ? SOM_Materials::get( $good_id ) : null;
$assert( $original && $good === (string) $original->name, 'original_name_unchanged' );
$assert( $original && 0.0 === (float) $original->current_stock, 'original_stock_unchanged' );
$assert( $original && 1.25 === (float) $original->unit_cost, 'original_cost_unchanged' );

$pair      = 'UP14 Pair ' . $suffix;
$pair_csv  = "name,unit\n{$pair},sheet\n{$pair},sheet\n";
$pair_view = SOM_Material_Import::parse( $pair_csv );
$assert( isset( $pair_view['rows'][0]['save_name'], $pair_view['rows'][1]['save_name'] ), 'pair_preview_rows' );
$assert(
	isset( $pair_view['rows'][0]['save_name'] ) && $pair === $pair_view['rows'][0]['save_name']
	&& $pair . ' (2)' === $pair_view['rows'][1]['save_name'],
	'same_file_suffix'
);
$pair_commit = SOM_Material_Import::commit( $pair_csv );
$assert( 2 === (int) $pair_commit['created'] && 0 === (int) $pair_commit['failed'], 'same_file_both_created' );

$case_base = 'UP14 Case ' . $suffix;
$case_id   = SOM_Materials::create(
	array(
		'name' => $case_base,
		'unit' => 'sheet',
	)
);
$assert( ! is_wp_error( $case_id ) && (int) $case_id > 0, 'case_base_created' );
$case_file = strtolower( $case_base );
$case      = SOM_Material_Import::parse( "name,unit\n{$case_file},sheet\n" );
$assert( isset( $case['rows'][0]['save_name'] ) && $case_file . ' (2)' === $case['rows'][0]['save_name'], 'case_collision_suffix' );

$dup_label = 'UP14 Dup ' . $suffix;
$dup_lower = 'up14 dup ' . $suffix;
$dup_a     = SOM_Suppliers::create( array( 'name' => $dup_label ) );
$dup_b     = SOM_Suppliers::create( array( 'name' => $dup_lower ) );
$assert( ! is_wp_error( $dup_a ) && ! is_wp_error( $dup_b ), 'ambiguous_suppliers_created' );
$amb_good = 'UP14 Ambiguous good ' . $suffix;
$amb_bad  = 'UP14 Ambiguous bad ' . $suffix;
$amb_csv  = "name,unit,preferred_supplier\n{$amb_good},sheet,\n{$amb_bad},sheet,{$dup_label}\n";
$amb_view = SOM_Material_Import::parse( $amb_csv );
$amb_row  = $row_by_code( $amb_view, 'ambiguous_supplier' );
$assert( is_array( $amb_row ) && $amb_bad === $amb_row['file_name'], 'ambiguous_supplier_row' );
$amb = SOM_Material_Import::commit( $amb_csv );
$assert( 1 === (int) $amb['created'] && 1 === (int) $amb['failed'], 'ambiguous_counts' );
$assert( 1 === $count_named( $amb_good ) && 0 === $count_named( $amb_bad ), 'ambiguous_saves_good_row' );

$blank_active = 'UP14 Blank active ' . $suffix;
$blank_view   = SOM_Material_Import::parse( "name,unit,is_active\n{$blank_active},sheet,\n" );
$assert(
	isset( $blank_view['rows'][0]['data']['is_active'] ) && 1 === (int) $blank_view['rows'][0]['data']['is_active'],
	'blank_active_means_active'
);

$tab_name = 'UP14 Tab ' . $suffix;
$tab      = SOM_Material_Import::parse( "name\tunit\n{$tab_name}\tsheet\n" );
$assert(
	'' === $tab['file_error'] && 1 === (int) $tab['ready']
	&& isset( $tab['rows'][0]['save_name'] ) && $tab_name === $tab['rows'][0]['save_name'],
	'tab_delimiter'
);

$bom_name = 'UP14 Bom ' . $suffix;
$bom      = SOM_Material_Import::parse( "\xEF\xBB\xBF" . "name,unit\n{$bom_name},sheet\n" );
$assert(
	'' === $bom['file_error'] && 1 === (int) $bom['ready']
	&& isset( $bom['rows'][0]['save_name'] ) && $bom_name === $bom['rows'][0]['save_name'],
	'bom_stripped'
);

$extra_name = 'UP14 Extra ' . $suffix;
$extra      = SOM_Material_Import::parse( "Name,Unit,notes\n{$extra_name},sheet,ignore me\n" );
$assert( '' === $extra['file_error'] && 1 === (int) $extra['ready'], 'unknown_column_and_header_case' );

$orphan     = 'UP14 Orphan ' . $suffix;
$missing    = SOM_Material_Import::parse( "name,notes\n{$orphan},hello\n" );
$assert( 'missing_column' === $missing['file_error_code'], 'missing_unit_column' );
$assert( array() === $missing['rows'], 'missing_column_no_rows' );
$missing_commit = SOM_Material_Import::commit( "name,notes\n{$orphan},hello\n" );
$assert( 0 === (int) $missing_commit['created'], 'missing_column_creates_none' );
$assert( 0 === $count_named( $orphan ), 'missing_column_row_absent' );

$made_name = 'UP14 House ' . $suffix;
$sid       = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(source_product_id), 0) + 1 FROM ' . SOM_DB::table( 'materials' ) );
if ( $sid < 1 ) {
	$sid = 1;
}
$made_id = SOM_Materials::create(
	array(
		'name'              => $made_name,
		'unit'              => 'sheet',
		'source_product_id' => $sid,
	)
);
$assert( ! is_wp_error( $made_id ) && (int) $made_id > 0, 'made_in_house_created' );

$export = SOM_Material_Import::export_csv();
$assert( str_starts_with( $export, "\xEF\xBB\xBF" ), 'export_has_bom' );
$export_body = substr( $export, 3 );
$export_line = strtok( $export_body, "\n" );
$assert( $header === trim( (string) $export_line ), 'export_header' );
$assert( false !== strpos( $export_body, $good ), 'export_includes_imported' );
$assert( false === strpos( $export_body, $made_name ), 'export_omits_made_in_house' );

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	echo "FAIL — UP14-S1 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S1 smoke\n";
