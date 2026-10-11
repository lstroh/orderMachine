<?php
/**
 * UP9-S4 smoke: optional step task lists; override wins; never gate Mark done.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up9-s4-smoke.php
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
SOM_Batch_Groups::ensure_rows();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );
$assert( class_exists( 'SOM_Step_Tasks' ), 'class_SOM_Step_Tasks' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.22.0', '>=' ), 'db_gte_1.22.0' );

$steps_t = SOM_DB::table( 'workflow_steps' );
$tasks_t = SOM_DB::table( 'product_step_tasks' );
$ip_t    = SOM_DB::table( 'order_item_step_progress' );
$op_t    = SOM_DB::table( 'order_step_progress' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$steps_t} LIKE 'task_list_json'" ), 'col_task_list_json' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tasks_t ) ) === $tasks_t, 'table_product_step_tasks' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$ip_t} LIKE 'task_checks_json'" ), 'col_item_task_checks' );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$assert( (bool) $wpdb->get_row( "SHOW COLUMNS FROM {$op_t} LIKE 'task_checks_json'" ), 'col_order_task_checks' );

$suffix = wp_generate_password( 4, false, false );

$wf = SOM_Workflows::create(
	array(
		'name'      => 'UP9-S4 tasks ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf ), 'create_workflow' );
$wf_id = (int) $wf;

$saved = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'name'                    => 'Prep with tasks',
			'requires_manual_confirm' => 1,
			'tasks'                   => array(
				array(
					'id'    => 't_load',
					'label' => 'Load vinyl',
				),
				array(
					'id'    => 't_align',
					'label' => 'Align print',
				),
			),
		),
		array(
			'name'                    => 'Confirm print',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'print_vs_request',
			'tasks'                   => array(
				array(
					'id'    => 't_preview',
					'label' => 'Preview proof',
				),
			),
		),
	)
);
$assert( true === $saved, 'save_steps_with_tasks' );

$steps = SOM_Workflows::get_steps( $wf_id );
$assert( 2 === count( $steps ), 'two_steps' );
$step_prep    = $steps[0];
$step_confirm = $steps[1];
$prep_list    = SOM_Step_Tasks::decode_list( $step_prep->task_list_json );
$assert( 2 === count( $prep_list ), 'template_has_two_tasks' );
$assert( 'Load vinyl' === $prep_list[0]['label'], 'template_task_label' );

$product = SOM_Products::create(
	array(
		'name'                 => 'UP9-S4 product ' . $suffix,
		'sku'                  => 'UP9S4-' . $suffix,
		'workflow_template_id' => $wf_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$eff_default = SOM_Step_Tasks::effective( $product_id, (int) $step_prep->id );
$assert( 2 === count( $eff_default ) && 'Load vinyl' === $eff_default[0]['label'], 'effective_uses_template' );

$sync = SOM_Step_Tasks::sync_for_product(
	$product_id,
	$wf_id,
	array(
		(int) $step_prep->id => array(
			array(
				'id'    => 't_override',
				'label' => 'Product-only prep',
			),
		),
	)
);
$assert( ! is_wp_error( $sync ), 'sync_override_ok' );

$eff_override = SOM_Step_Tasks::effective( $product_id, (int) $step_prep->id );
$assert( 1 === count( $eff_override ) && 'Product-only prep' === $eff_override[0]['label'], 'override_wins_full_replace' );
$assert( 't_override' === $eff_override[0]['id'], 'override_keeps_id' );

$eff_no_product = SOM_Step_Tasks::effective( 0, (int) $step_prep->id );
$assert( 2 === count( $eff_no_product ) && 'Load vinyl' === $eff_no_product[0]['label'], 'pack_style_zero_product_uses_template' );

$clear = SOM_Step_Tasks::sync_for_product(
	$product_id,
	$wf_id,
	array( (int) $step_prep->id => array() )
);
$assert( ! is_wp_error( $clear ), 'clear_override_ok' );
$assert( 2 === count( SOM_Step_Tasks::effective( $product_id, (int) $step_prep->id ) ), 'cleared_falls_back_to_template' );

// Re-apply override for runtime order tests.
SOM_Step_Tasks::sync_for_product(
	$product_id,
	$wf_id,
	array(
		(int) $step_prep->id => array(
			array(
				'id'    => 't_override',
				'label' => 'Product-only prep',
			),
		),
	)
);

$order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up9s4-' . $suffix,
		'buyer_name'        => 'UP9-S4 Tester',
		'items'             => array(
			array(
				'product_id'           => $product_id,
				'quantity'             => 1,
				'unit_price'           => 5,
				'personalisation_text' => 'Tasks smoke',
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$order = SOM_Orders::get( $order_id );
$assert( $order && ! empty( $order->items ), 'order_loaded' );
$item_id = (int) $order->items[0]->id;

$current = SOM_Item_Make::current_item_progress( $item_id );
$assert( $current && 'Prep with tasks' === (string) $current->step_name, 'current_is_prep' );
$assert( 'in_progress' === (string) $current->status, 'prep_in_progress' );

$step_row = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d LIMIT 1',
		(int) $current->workflow_step_id
	)
);
$assert( SOM_Workflow_Engine::can_mark_done( $current, $step_row ), 'can_mark_done_with_unticked_tasks' );

$line_tasks = SOM_Step_Tasks::effective( $product_id, (int) $current->workflow_step_id );
$assert( 1 === count( $line_tasks ) && 'Product-only prep' === $line_tasks[0]['label'], 'order_effective_override' );
$assert( empty( SOM_Step_Tasks::decode_checks( $current ) ), 'starts_with_no_ticks' );

$done = SOM_Item_Make::mark_done( $order_id, $item_id );
$assert( ! is_wp_error( $done ), 'mark_done_with_unticked_tasks' );

// Fresh order for tick persistence (prep step again).
$order2_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up9s4b-' . $suffix,
		'buyer_name'        => 'UP9-S4 Tick',
		'items'             => array(
			array(
				'product_id'           => $product_id,
				'quantity'             => 1,
				'unit_price'           => 5,
				'personalisation_text' => 'Tick persist',
			),
		),
	)
);
$assert( ! is_wp_error( $order2_id ), 'create_order_for_ticks' );
$order2_id = (int) $order2_id;
$order2    = SOM_Orders::get( $order2_id );
$item2_id  = (int) $order2->items[0]->id;
$current2  = SOM_Item_Make::current_item_progress( $item2_id );
$assert( $current2 && 'Prep with tasks' === (string) $current2->step_name, 'tick_order_on_prep' );

$save_ticks = SOM_Step_Tasks::save_for_order(
	$order2_id,
	(int) $current2->workflow_step_id,
	array( 't_override' => 1 ),
	$item2_id
);
$assert( true === $save_ticks, 'save_task_ticks' );

$current2 = SOM_Item_Make::current_item_progress( $item2_id );
$checks   = SOM_Step_Tasks::decode_checks( $current2 );
$assert( ! empty( $checks['t_override'] ), 'ticks_persist' );

// Advance landed on confirm step — confirmation still gates; tasks do not.
$current = SOM_Item_Make::current_item_progress( $item_id );
$assert( $current && 'Confirm print' === (string) $current->step_name, 'now_on_confirm' );
$step_row = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d LIMIT 1',
		(int) $current->workflow_step_id
	)
);
$assert( ! SOM_Workflow_Engine::can_mark_done( $current, $step_row ), 'confirmation_still_gates' );
$blocked = SOM_Item_Make::mark_done( $order_id, $item_id );
$assert(
	is_wp_error( $blocked ) && 'som_confirmation_required' === $blocked->get_error_code(),
	'mark_done_blocked_by_checklist_not_tasks'
);

// Tick optional confirm-step task without checklist — still blocked.
SOM_Step_Tasks::save_for_order(
	$order_id,
	(int) $current->workflow_step_id,
	array( 't_preview' => 1 ),
	$item_id
);
$current = SOM_Item_Make::current_item_progress( $item_id );
$assert( ! SOM_Workflow_Engine::can_mark_done( $current, $step_row ), 'tasks_do_not_satisfy_checklist' );

SOM_Step_Confirmations::save_for_order(
	$order_id,
	array( 'print_matches_request' => 1 ),
	$item_id
);
$current = SOM_Item_Make::current_item_progress( $item_id );
$assert( SOM_Workflow_Engine::can_mark_done( $current, $step_row ), 'checklist_unlocks_mark_done' );

// UI / docs surfaces.
$editor  = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$product_v = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/product-edit.php' );
$detail  = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$menu    = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$js      = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$guide   = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md' );
$ref     = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-REFERENCE.md' );
$feat    = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/FEATURES-AND-TESTING.md' );

$assert( false !== strpos( $editor, 'Optional checklist for operators. Does not block Mark done.' ), 'editor_task_help' );
$assert( false !== strpos( $editor, 'data-som-task-list' ), 'editor_task_list_attr' );
$assert( false !== strpos( $product_v, 'Step tasks' ), 'product_step_tasks_heading' );
$assert( false !== strpos( $detail, 'som-tasks-panel' ), 'order_detail_tasks_panel' );
$assert( false !== strpos( $detail, 'SOM_Step_Tasks::effective' ), 'order_detail_effective' );
$assert( false !== strpos( $menu, 'som_save_tasks' ), 'admin_save_tasks' );
$assert( false !== strpos( $js, 'initStepTaskLists' ), 'js_task_lists' );
$assert( false !== strpos( $guide, 'Tasks' ) && false !== strpos( $guide, 'Confirmation checklist' ), 'guide_tasks_vs_confirm' );
$assert( false !== strpos( $ref, 'Tasks' ) && false !== strpos( $ref, 'does not block Mark done' ), 'ref_tasks_optional' );
$assert( false !== strpos( $feat, 'UP9-S4' ), 'features_up9_s4' );

// Engine must not mention task checks in can_mark_done.
$engine = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-workflow-engine.php' );
$assert( false === strpos( $engine, 'task_checks' ), 'engine_no_task_checks_gate' );

if ( $fail > 0 ) {
	echo "FAIL — UP9-S4 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP9-S4 smoke\n";
