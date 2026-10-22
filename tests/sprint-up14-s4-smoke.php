<?php
/**
 * UP14-S4 smoke: Add material can create one material budget, and leaves none when the box is clear.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s4-smoke.php
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

$budget_count = static function ( $material_id ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'budgets' ) . ' WHERE material_id = %d',
			(int) $material_id
		)
	);
};

$is_empty = static function ( $value ) {
	return null === $value || '' === $value;
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );

$out( 'plugin', SOM_VERSION );
$assert( method_exists( 'SOM_Budgets', 'create_on_material_add' ), 'method_create_on_material_add' );

$budgets_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-budgets.php' );
$menu_src    = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$edit_src    = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/material-edit.php' );
$import_src  = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-material-import.php' );

$fn_at   = strpos( $budgets_src, 'function create_on_material_add' );
$next_at = false !== $fn_at ? strpos( $budgets_src, 'function update', $fn_at ) : false;
$body    = ( false !== $fn_at && false !== $next_at ) ? substr( $budgets_src, $fn_at, $next_at - $fn_at ) : '';
$skip_at = false !== $body ? strpos( $body, 'if ( ! $requested )' ) : false;
$create_at = false !== $body ? strpos( $body, 'self::create' ) : false;
$assert( false !== $skip_at && false !== $create_at && $skip_at < $create_at, 'flag_off_returns_before_create' );
$assert( false !== strpos( $body, "'type'                  => 'material'" ), 'create_type_material' );
$assert( false !== strpos( $body, "'is_active'             => 1" ), 'create_active' );
$assert( false === strpos( $body, 'set_workflow_links' ), 'create_writes_no_workflow_links' );
$assert( false === strpos( $body, '->delete' ), 'create_does_not_delete_material' );

$box_at  = strpos( $edit_src, 'name="som_create_material_budget"' );
$guard   = false !== $box_at ? strrpos( substr( $edit_src, 0, $box_at ), 'if ( $is_new )' ) : false;
$endif   = false !== $box_at ? strpos( $edit_src, 'endif;', $box_at ) : false;
$input_from = false !== $box_at ? strrpos( substr( $edit_src, 0, $box_at ), '<input' ) : false;
$input_to   = ( false !== $box_at ) ? strpos( $edit_src, '/>', $box_at ) : false;
$input      = ( false !== $input_from && false !== $input_to ) ? substr( $edit_src, $input_from, $input_to - $input_from ) : '';
$assert( false !== $box_at && false !== $guard && false !== $endif && $guard < $box_at && $box_at < $endif, 'checkbox_only_on_add' );
$assert( false !== strpos( $edit_src, 'Create a material budget' ), 'checkbox_label' );
$assert( '' !== $input && false === strpos( $input, 'checked' ), 'checkbox_unchecked' );
$assert( 1 === substr_count( $edit_src, 'name="som_create_material_budget"' ), 'checkbox_once' );

$handler_at = strpos( $menu_src, 'function handle_materials_actions' );
$next_fn    = false !== $handler_at ? strpos( $menu_src, 'function handle_budgets_actions', $handler_at ) : false;
$handler    = ( false !== $handler_at && false !== $next_fn ) ? substr( $menu_src, $handler_at, $next_fn - $handler_at ) : '';
$save_at    = false !== $handler ? strpos( $handler, "isset( \$_POST['som_save_material'] )" ) : false;
$save       = false !== $save_at ? substr( $handler, $save_at ) : '';
$creating_at = false !== $save ? strpos( $save, '$creating    = $material_id < 1;' ) : false;
$material_create_at = false !== $save ? strpos( $save, 'SOM_Materials::create' ) : false;
$budget_at  = false !== $save ? strpos( $save, 'SOM_Budgets::create_on_material_add' ) : false;
$error_at   = false !== $save ? strpos( $save, 'Material saved. The budget was not created: %s' ) : false;
$kept_at    = false !== $error_at ? strpos( $save, 'SOM_Materials::detail_url( $material_id )', $error_at ) : false;
$error_end  = false !== $error_at ? strpos( $save, 'exit;', $error_at ) : false;
$error_body = ( false !== $error_at && false !== $error_end ) ? substr( $save, $error_at, $error_end - $error_at ) : '';
$assert( false !== $creating_at && false !== $material_create_at && false !== $budget_at && $material_create_at < $budget_at, 'budget_after_material_insert' );
$assert( false !== strpos( $save, "if ( \$creating )" ) && false !== $budget_at, 'budget_only_when_creating' );
$assert( false !== strpos( $save, "! empty( \$_POST['som_create_material_budget'] )" ), 'handler_reads_checkbox' );
$assert( false !== $error_at && false !== $kept_at && $error_at < $kept_at, 'budget_error_stays_on_material' );
$assert( '' !== $error_body && false === strpos( $error_body, '->delete' ), 'budget_error_does_not_delete_material' );

$assert( false === strpos( $import_src, 'SOM_Budgets' ), 'csv_does_not_create_budgets' );
$assert( false === strpos( $import_src, 'som_create_material_budget' ), 'csv_has_no_budget_flag' );

$on_name = 'UP14-S4 on ' . $suffix;
$on      = SOM_Materials::create(
	array(
		'name'      => $on_name,
		'unit'      => 'sheet',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $on ) && (int) $on > 0, 'create_material_flag_on' );
$on_id  = is_wp_error( $on ) ? 0 : (int) $on;
$on_row = $on_id > 0 ? SOM_Materials::get( $on_id ) : null;

$budget_id = $on_id > 0 ? SOM_Budgets::create_on_material_add( $on_id, true ) : new WP_Error( 'skipped', 'skipped' );
$assert( ! is_wp_error( $budget_id ) && (int) $budget_id > 0, 'flag_on_creates_budget' );
$budget = ( ! is_wp_error( $budget_id ) && (int) $budget_id > 0 ) ? SOM_Budgets::get( (int) $budget_id ) : null;
$assert( $on_id > 0 && 1 === $budget_count( $on_id ), 'flag_on_one_budget' );
$assert( $budget && $on_row && (string) $budget->name === (string) $on_row->name, 'budget_name_matches_material' );
$assert( $budget && 'material' === (string) $budget->type, 'budget_type_material' );
$assert( $budget && (int) $budget->material_id === $on_id, 'budget_material_id' );
$assert( $budget && 'material_cost' === (string) $budget->funding_method, 'budget_funding_material_cost' );
$assert( $budget && $is_empty( $budget->funding_value ), 'budget_funding_value_empty' );
$assert( $budget && $is_empty( $budget->target_reserve_amount ), 'budget_target_reserve_empty' );
$assert( $budget && $is_empty( $budget->notes ), 'budget_notes_empty' );
$assert( $budget && 1 === (int) $budget->is_active, 'budget_active' );
$assert( $budget && abs( (float) $budget->current_balance ) < 0.0000001, 'budget_balance_zero' );
$assert(
	$budget && array() === SOM_Budgets::get_workflow_link_ids( (int) $budget->id ),
	'budget_no_workflow_links'
);
$assert( $on_id > 0 && SOM_Budgets::get_for_material( $on_id, true ), 'flag_on_active_material_budget' );

$off_name = 'UP14-S4 off ' . $suffix;
$off      = SOM_Materials::create(
	array(
		'name'      => $off_name,
		'unit'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $off ) && (int) $off > 0, 'create_material_flag_off' );
$off_id  = is_wp_error( $off ) ? 0 : (int) $off;
$skipped = $off_id > 0 ? SOM_Budgets::create_on_material_add( $off_id, false ) : new WP_Error( 'skipped', 'skipped' );
$assert( true === $skipped, 'flag_off_skips_create' );
$assert( $off_id > 0 && 0 === $budget_count( $off_id ), 'flag_off_no_budget' );
$assert( $off_id > 0 && null === SOM_Budgets::get_for_material( $off_id, false ), 'flag_off_no_material_budget_row' );

$dup = $on_id > 0
	? SOM_Budgets::create(
		array(
			'name'        => 'UP14-S4 second ' . $suffix,
			'type'        => 'material',
			'material_id' => $on_id,
		)
	)
	: new WP_Error( 'skipped', 'skipped' );
$assert( is_wp_error( $dup ) && 'som_budget_material_dup' === $dup->get_error_code(), 'create_rejects_second_budget' );
$assert( $on_id > 0 && SOM_Materials::get( $on_id ), 'material_kept_after_rejected_budget' );
$assert( $on_id > 0 && 1 === $budget_count( $on_id ), 'still_one_budget' );

if ( $fail > 0 ) {
	echo "FAIL — UP14-S4 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S4 smoke\n";
