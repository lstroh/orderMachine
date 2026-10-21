<?php
/**
 * UP14-S2 smoke: workflow CSV creates a template and its steps, suffixes a taken name, and rejects a bad Post-ship file.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s2-smoke.php
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
			'SELECT * FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE name = %s LIMIT 1',
			$name
		)
	);
};

$count_named = static function ( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE name = %s',
			$name
		)
	);
};

$template_count = static function () {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'workflow_templates' ) );
};

$batch_count = static function () {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'batch_groups' ) );
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

$row_by_name = static function ( $parsed, $name ) {
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

$csv_row = static function ( array $fields ) {
	$cells = array();
	foreach ( SOM_Workflow_Import::COLUMNS as $column ) {
		$cells[] = isset( $fields[ $column ] ) ? (string) $fields[ $column ] : '';
	}
	return implode( ',', $cells );
};

$suffix       = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );
$make         = 'UP14 Make ' . $suffix;
$beside       = 'UP14 Beside ' . $suffix;
$bad_batch    = 'UP14 Bad batch ' . $suffix;
$unknown_key  = 'up14-missing-' . $suffix;
$post         = 'UP14 Post ' . $suffix;
$split        = 'UP14 Split ' . $suffix;
$pipe         = 'UP14 Pipe ' . $suffix;
$labels_name  = 'UP14 Labels ' . $suffix;
$header       = implode( ',', SOM_Workflow_Import::COLUMNS );

$out( 'plugin', SOM_VERSION );
$assert( class_exists( 'SOM_Workflow_Import', false ), 'workflow_import_class' );
$assert( ! class_exists( 'SOM_Product_Import', false ), 'no_product_importer' );
$assert( 'workflows' === SOM_Workflow_Import::KIND, 'preview_kind' );
$assert( ! in_array( 'script_config', SOM_Workflow_Import::COLUMNS, true ), 'no_script_config_column' );
$assert( ! in_array( 'output_per_run', SOM_Workflow_Import::COLUMNS, true ), 'no_output_per_run_column' );

$import_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-workflow-import.php' );
$list_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-templates.php' );
$menu_src   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );

$assert( false !== strpos( $import_src, 'SOM_Workflows::validate_submission' ), 'validates_like_save' );
$assert( false !== strpos( $import_src, 'SOM_Workflows::create' ), 'creates_template' );
$assert( false !== strpos( $import_src, 'SOM_Workflows::save_steps' ), 'writes_steps' );
$assert( false !== strpos( $import_src, 'SOM_Workflows::delete_failed_create' ), 'deletes_partial_template' );
$assert( false === strpos( $import_src, 'SOM_Workflows::update' ), 'import_does_not_update' );
$assert( false === strpos( $import_src, 'output_per_run' ), 'import_ignores_output_per_run' );
$assert( false !== strpos( $import_src, 'SOM_Catalog_Import::allocate_name' ), 'uses_suffix_helper' );
$assert( false !== strpos( $import_src, 'SOM_Catalog_Import::remember' ) || false !== strpos( $menu_src, 'SOM_Catalog_Import::remember' ), 'uses_preview_token' );

$assert( false !== strpos( $list_src, 'Export CSV' ), 'export_button' );
$assert( false !== strpos( $list_src, 'Download template' ), 'template_button' );
$assert( false !== strpos( $list_src, 'name="som_import_file"' ), 'import_file_input' );
$assert( false !== strpos( $list_src, 'som_preview_workflows' ), 'preview_button' );
$assert( false !== strpos( $list_src, 'som_import_workflows' ), 'import_button' );
$assert( false !== strpos( $list_src, "wp_nonce_field( 'som_import_workflows_preview'" ), 'preview_nonce' );
$assert( false !== strpos( $list_src, "wp_nonce_field( 'som_import_workflows_commit'" ), 'commit_nonce' );
$assert( false !== strpos( $list_src, 'som_workflow_export' ), 'export_nonce_url' );
$assert( false !== strpos( $list_src, 'som_workflow_template' ), 'template_nonce_url' );

$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_workflow_export' )" ), 'export_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_workflow_template' )" ), 'template_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_import_workflows_preview', 'som_workflow_nonce' )" ), 'preview_nonce_check' );
$assert( false !== strpos( $menu_src, "check_admin_referer( 'som_import_workflows_commit', 'som_workflow_nonce' )" ), 'commit_nonce_check' );
$assert( false !== strpos( $menu_src, 'SOM_Workflow_Import::commit' ), 'commit_handler' );
$assert( false !== strpos( $menu_src, 'Created %1$d, failed %2$d.' ), 'result_copy' );

$template = SOM_Workflow_Import::template_csv();
$assert( str_starts_with( $template, "\xEF\xBB\xBF" ), 'template_has_bom' );
$template_body = substr( $template, 3 );
$assert( $header === trim( $template_body ), 'template_header_only' );

$shared = array(
	'name'        => $make,
	'description' => 'Desk print',
	'kind'        => 'make',
	'uses_trays'  => 'yes',
	'is_active'   => 'yes',
);
$csv = $header . "\n"
	. $csv_row(
		array_merge(
			$shared,
			array(
				'step_order'        => '2',
				'step_name'         => 'Confirm',
				'confirmation_kind' => 'print_vs_request',
			)
		)
	) . "\n"
	. $csv_row(
		array_merge(
			$shared,
			array(
				'step_order'              => '1',
				'step_name'               => 'Print',
				'requires_manual_confirm' => 'yes',
				'planned_seconds'         => '60',
				'instructions'            => 'Keep the sheet flat',
				'tasks'                   => 'Align | Check edges',
				'command_template'        => 'Print {{product_name}}',
			)
		)
	) . "\n";

$before = $template_count();
$parsed = SOM_Workflow_Import::parse( $csv );
$assert( $before === $template_count(), 'parse_does_not_insert' );
$assert( '' === $parsed['file_error'], 'parse_file_ok' );
$assert( 1 === (int) $parsed['ready'] && 0 === (int) $parsed['failed'], 'parse_one_workflow' );
$preview_row = $row_by_name( $parsed, $make );
$assert( is_array( $preview_row ) && $make === $preview_row['save_name'], 'preview_keeps_free_name' );

$first = SOM_Workflow_Import::commit( $csv );
$assert( 1 === (int) $first['created'] && 0 === (int) $first['failed'], 'first_created' );
$assert( 1 === $count_named( $make ), 'make_saved_once' );

$made = $find( $make );
$assert( $made && 'make' === (string) $made->kind, 'kind_make' );
$assert( $made && 1 === (int) $made->uses_trays, 'uses_trays' );
$assert( $made && 1 === (int) $made->is_active, 'is_active' );
$assert( $made && 'Desk print' === (string) $made->description, 'description' );
$make_id = $made ? (int) $made->id : 0;
$steps   = $make_id > 0 ? SOM_Workflows::get_steps( $make_id ) : array();
$assert( 2 === count( $steps ), 'two_steps' );

$print   = isset( $steps[0] ) ? $steps[0] : null;
$confirm = isset( $steps[1] ) ? $steps[1] : null;
$assert( $print && 'Print' === (string) $print->name && 1 === (int) $print->step_order, 'steps_sorted' );
$assert( $confirm && 'Confirm' === (string) $confirm->name && 2 === (int) $confirm->step_order, 'second_step_order' );
$assert( $print && 'Print {{product_name}}' === (string) $print->command_template, 'command_template' );
$assert( $print && 'Keep the sheet flat' === (string) $print->instructions, 'instructions' );
$assert( $print && 60 === (int) $print->planned_seconds, 'planned_seconds' );
$assert( $print && empty( $print->script_config ), 'print_script_config_empty' );
$assert( $confirm && 'print_vs_request' === (string) $confirm->confirmation_kind, 'confirmation_kind' );
$assert( $confirm && empty( $confirm->script_config ), 'confirm_script_config_empty' );

$task_rows = $print ? SOM_Step_Tasks::decode_list( $print->task_list_json ) : array();
$assert( 2 === count( $task_rows ), 'task_count' );
$assert(
	isset( $task_rows[0]['label'], $task_rows[1]['label'] )
	&& 'Align' === $task_rows[0]['label']
	&& 'Check edges' === $task_rows[1]['label'],
	'task_labels'
);
$assert(
	isset( $task_rows[0]['id'], $task_rows[1]['id'] )
	&& 1 === preg_match( '/^t\d+_[a-f0-9]{8}$/', (string) $task_rows[0]['id'] )
	&& 1 === preg_match( '/^t\d+_[a-f0-9]{8}$/', (string) $task_rows[1]['id'] ),
	'task_ids_from_step_tasks'
);

$export = SOM_Workflow_Import::export_csv();
$assert( str_starts_with( $export, "\xEF\xBB\xBF" ), 'export_has_bom' );
$export_body = substr( $export, 3 );
$export_line = strtok( $export_body, "\n" );
$assert( $header === trim( (string) $export_line ), 'export_header' );
$assert( false !== strpos( $export_body, 'Print {{product_name}}' ), 'export_command' );
$assert( false !== strpos( $export_body, 'Align | Check edges' ), 'export_tasks' );
$assert( false !== strpos( $export_body, 'print_vs_request' ), 'export_confirmation' );

$exported = SOM_Workflow_Import::parse( $export );
$exported_row = $row_by_name( $exported, $make );
$assert(
	is_array( $exported_row ) && ! empty( $exported_row['ok'] ) && $make . ' (2)' === $exported_row['save_name'],
	'export_round_trip_suffix'
);

$second = SOM_Workflow_Import::commit( $csv );
$assert( 1 === (int) $second['created'] && 0 === (int) $second['failed'], 'second_created' );
$copy_name = $make . ' (2)';
$assert( 1 === $count_named( $make ), 'second_import_did_not_duplicate_exact_name' );
$assert( 1 === $count_named( $copy_name ), 'second_import_suffix' );
$copy = $find( $copy_name );
$assert( $copy && $make_id !== (int) $copy->id, 'second_import_new_template' );
$copy_steps = $copy ? SOM_Workflows::get_steps( (int) $copy->id ) : array();
$assert( 2 === count( $copy_steps ), 'copy_has_two_steps' );
$assert(
	isset( $copy_steps[0], $copy_steps[1] )
	&& 'Print' === (string) $copy_steps[0]->name
	&& 'Print {{product_name}}' === (string) $copy_steps[0]->command_template
	&& 'print_vs_request' === (string) $copy_steps[1]->confirmation_kind,
	'copy_steps_round_trip'
);
$copy_tasks = isset( $copy_steps[0] ) ? SOM_Step_Tasks::decode_list( $copy_steps[0]->task_list_json ) : array();
$assert(
	isset( $copy_tasks[0]['label'], $copy_tasks[1]['label'] )
	&& 'Align' === $copy_tasks[0]['label']
	&& 'Check edges' === $copy_tasks[1]['label'],
	'copy_tasks'
);
$original_steps = $make_id > 0 ? SOM_Workflows::get_steps( $make_id ) : array();
$assert( 2 === count( $original_steps ), 'original_steps_unchanged' );
$original = $make_id > 0 ? SOM_Workflows::get( $make_id ) : null;
$assert( $original && $make === (string) $original->name, 'original_name_unchanged' );

$case_base = 'UP14 Case ' . $suffix;
$case_id   = SOM_Workflows::create(
	array(
		'name' => $case_base,
		'kind' => 'make',
	)
);
$assert( ! is_wp_error( $case_id ) && (int) $case_id > 0, 'case_base_created' );
$case_file = strtolower( $case_base );
$case      = SOM_Workflow_Import::parse( "name,kind,step_order,step_name\n{$case_file},make,1,Print\n" );
$case_row  = $row_by_name( $case, $case_file );
$assert( is_array( $case_row ) && $case_file . ' (2)' === $case_row['save_name'], 'case_collision_suffix' );

$post_before = $template_count();
$post_csv    = "name,kind,step_order,step_name,confirmation_kind\n{$post},post_ship,1,Send card,\n";
$post_view   = SOM_Workflow_Import::parse( $post_csv );
$post_row    = $row_by_code( $post_view, 'som_post_ship_arrival_required' );
$assert( is_array( $post_row ) && $post === $post_row['file_name'] && '' === $post_row['save_name'], 'post_ship_preview_error' );
$assert(
	is_array( $post_row ) && SOM_Workflows::post_ship_arrival_required_message() === $post_row['message'],
	'post_ship_save_message'
);
$post_commit = SOM_Workflow_Import::commit( $post_csv );
$assert( 0 === (int) $post_commit['created'] && 1 === (int) $post_commit['failed'], 'post_ship_commit_failed' );
$assert( $post_before === $template_count(), 'post_ship_creates_no_template' );
$assert( 0 === $count_named( $post ), 'post_ship_name_absent' );

$batches_before = $batch_count();
$batch_csv      = "name,kind,step_order,step_name,batch_group_key\n"
	. "{$bad_batch},pack,1,Labels,{$unknown_key}\n"
	. "{$beside},make,1,Print,\n";
$batch_view     = SOM_Workflow_Import::parse( $batch_csv );
$batch_row      = $row_by_code( $batch_view, 'unknown_batch_group' );
$assert( is_array( $batch_row ) && $bad_batch === $batch_row['file_name'], 'unknown_batch_row' );
$beside_row = $row_by_name( $batch_view, $beside );
$assert( is_array( $beside_row ) && ! empty( $beside_row['ok'] ) && $beside === $beside_row['save_name'], 'unknown_batch_spares_sibling' );
$batch_commit = SOM_Workflow_Import::commit( $batch_csv );
$assert( 1 === (int) $batch_commit['created'] && 1 === (int) $batch_commit['failed'], 'unknown_batch_counts' );
$assert( 0 === $count_named( $bad_batch ), 'unknown_batch_not_saved' );
$assert( 1 === $count_named( $beside ), 'sibling_saved' );
$assert( $batches_before === $batch_count(), 'unknown_batch_creates_no_group' );

$group = SOM_Batch_Groups::get_by_key( 'shipping_label' );
$assert( $group && ! empty( $group->id ), 'shipping_label_present' );
$group_id       = $group ? (int) $group->id : 0;
$labels_before  = $batch_count();
$labels_csv     = "name,kind,step_order,step_name,batch_group_key\n{$labels_name},pack,1,Group labels,shipping_label\n";
$labels_commit  = SOM_Workflow_Import::commit( $labels_csv );
$assert( 1 === (int) $labels_commit['created'] && 0 === (int) $labels_commit['failed'], 'known_batch_created' );
$assert( $labels_before === $batch_count(), 'known_batch_does_not_create_group' );
$labels = $find( $labels_name );
$label_steps = $labels ? SOM_Workflows::get_steps( (int) $labels->id ) : array();
$assert(
	isset( $label_steps[0] ) && $group_id > 0 && $group_id === (int) $label_steps[0]->batch_group_id,
	'known_batch_linked'
);
$assert( isset( $label_steps[0] ) && empty( $label_steps[0]->script_config ), 'batch_step_script_config_empty' );

$split_before = $template_count();
$split_csv    = "name,kind,step_order,step_name\n{$split},make,1,Print\n{$split},pack,2,Pack\n";
$split_view   = SOM_Workflow_Import::parse( $split_csv );
$split_row    = $row_by_code( $split_view, 'header_disagreement' );
$assert( is_array( $split_row ) && $split === $split_row['file_name'], 'header_disagreement_row' );
$split_commit = SOM_Workflow_Import::commit( $split_csv );
$assert( 0 === (int) $split_commit['created'], 'header_disagreement_creates_none' );
$assert( $split_before === $template_count(), 'header_disagreement_count' );

$pipe_before = $template_count();
$pipe_csv    = "name,kind,step_order,step_name,tasks\n{$pipe},make,1,Print,Size|large\n";
$pipe_view   = SOM_Workflow_Import::parse( $pipe_csv );
$pipe_row    = $row_by_code( $pipe_view, 'bad_task_label' );
$assert( is_array( $pipe_row ) && $pipe === $pipe_row['file_name'], 'task_pipe_row' );
$pipe_commit = SOM_Workflow_Import::commit( $pipe_csv );
$assert( 0 === (int) $pipe_commit['created'] && $pipe_before === $template_count(), 'task_pipe_creates_none' );

$tab_name = 'UP14 Tab ' . $suffix;
$tab      = SOM_Workflow_Import::parse( "\xEF\xBB\xBF" . "name\tkind\tstep_order\tstep_name\n{$tab_name}\tmake\t1\tPrint\n" );
$tab_row  = $row_by_name( $tab, $tab_name );
$assert(
	'' === $tab['file_error'] && is_array( $tab_row ) && ! empty( $tab_row['ok'] ) && $tab_name === $tab_row['save_name'],
	'tab_and_bom'
);

$orphan  = 'UP14 Orphan ' . $suffix;
$missing = SOM_Workflow_Import::parse( "name,kind,step_order\n{$orphan},make,1\n" );
$assert( 'missing_column' === $missing['file_error_code'], 'missing_step_name_column' );
$assert( array() === $missing['rows'], 'missing_column_no_rows' );
$missing_before = $template_count();
$missing_commit = SOM_Workflow_Import::commit( "name,kind,step_order\n{$orphan},make,1\n" );
$assert( 0 === (int) $missing_commit['created'] && $missing_before === $template_count(), 'missing_column_creates_none' );

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	echo "FAIL — UP14-S2 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S2 smoke\n";
