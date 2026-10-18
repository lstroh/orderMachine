<?php
/**
 * UP12-S6 smoke: clone a workflow template without moving products, orders, or site defaults.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s6-smoke.php
 *
 * @package OrderMachine
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

if ( ! function_exists( 'som_up12_s6_strlen' ) ) {
	/**
	 * Character length, matching the cloner when mbstring is present.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	function som_up12_s6_strlen( $text ) {
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

$template_count = static function () {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . SOM_DB::table( 'workflow_templates' ) );
};

$name_count = static function ( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE name = %s',
			$name
		)
	);
};

$nullable_int = static function ( $value ) {
	if ( null === $value || '' === $value ) {
		return null;
	}
	return (int) $value;
};

$nullable_text = static function ( $value ) {
	if ( null === $value ) {
		return null;
	}
	$text = (string) $value;
	return '' === $text ? null : $text;
};

$step_sig = static function ( $template_id ) use ( $nullable_int, $nullable_text ) {
	$out = array();
	foreach ( SOM_Workflows::get_steps( $template_id ) as $step ) {
		$out[] = array(
			'id'                      => (int) $step->id,
			'step_order'              => (int) $step->step_order,
			'name'                    => (string) $step->name,
			'requires_manual_confirm' => ! empty( $step->requires_manual_confirm ) ? 1 : 0,
			'timer_seconds'           => $nullable_int( isset( $step->timer_seconds ) ? $step->timer_seconds : null ),
			'planned_seconds'         => $nullable_int( isset( $step->planned_seconds ) ? $step->planned_seconds : null ),
			'command_template'        => $nullable_text( isset( $step->command_template ) ? $step->command_template : null ),
			'batch_group_id'          => $nullable_int( isset( $step->batch_group_id ) ? $step->batch_group_id : null ),
			'confirmation_kind'       => $nullable_text( isset( $step->confirmation_kind ) ? $step->confirmation_kind : null ),
			'instructions'            => $nullable_text( isset( $step->instructions ) ? $step->instructions : null ),
			'task_list_json'          => $nullable_text( isset( $step->task_list_json ) ? $step->task_list_json : null ),
			'script_config'           => $nullable_text( isset( $step->script_config ) ? $step->script_config : null ),
		);
	}
	return $out;
};

$goal_sig = static function ( $template_id ) {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT material_id, goal_unit_cost, warning_threshold_percent FROM ' . SOM_DB::table( 'workflow_material_goals' ) . ' WHERE workflow_template_id = %d ORDER BY material_id ASC, id ASC',
			(int) $template_id
		)
	);
	$out = array();
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	foreach ( $rows as $row ) {
		$out[] = array(
			'material_id'               => (int) $row->material_id,
			'goal_unit_cost'            => (string) $row->goal_unit_cost,
			'warning_threshold_percent' => (string) $row->warning_threshold_percent,
		);
	}
	return $out;
};

$budget_ids_for = static function ( $template_id ) {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT budget_id FROM ' . SOM_DB::table( 'budget_workflow_links' ) . ' WHERE workflow_template_id = %d ORDER BY budget_id ASC',
			(int) $template_id
		)
	);
	return array_map( 'intval', is_array( $ids ) ? $ids : array() );
};

$count_where = static function ( $table_suffix, $column, $id ) {
	global $wpdb;
	$table = SOM_DB::table( $table_suffix );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column and table are fixed literals at the call site.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE {$column} = %d",
			(int) $id
		)
	);
};

$count_progress = static function ( $table_suffix, array $step_ids ) {
	global $wpdb;
	$step_ids = array_values( array_filter( array_map( 'intval', $step_ids ) ) );
	if ( ! $step_ids ) {
		return 0;
	}
	$in    = implode( ',', $step_ids );
	$table = SOM_DB::table( $table_suffix );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are ints.
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE workflow_step_id IN ({$in})" );
};

$suffix           = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );
$arrival_message  = SOM_Workflows::post_ship_arrival_required_message();
$combo_message    = 'A confirmation step cannot also have timer or batch gates.';
$pack_before      = get_option( SOM_Pack::OPTION_TEMPLATE, null );
$post_ship_before = get_option( SOM_Post_Ship::OPTION_TEMPLATE, null );
$auto_before      = get_option( SOM_Post_Ship::OPTION_AUTO_START, null );

$restore_option = static function ( $option, $previous ) {
	if ( false === $previous || null === $previous || '' === $previous ) {
		delete_option( $option );
		return;
	}
	update_option( $option, $previous, false );
};

$restore_defaults = static function () use ( $restore_option, $pack_before, $post_ship_before, $auto_before ) {
	$restore_option( SOM_Pack::OPTION_TEMPLATE, $pack_before );
	$restore_option( SOM_Post_Ship::OPTION_TEMPLATE, $post_ship_before );
	$restore_option( SOM_Post_Ship::OPTION_AUTO_START, $auto_before );
};
register_shutdown_function( $restore_defaults );

$out( 'plugin', SOM_VERSION );
$assert( method_exists( 'SOM_Workflows', 'clone_template' ), 'method_clone_template' );
$assert( method_exists( 'SOM_Workflows', 'render_clone_button' ), 'method_render_clone_button' );

$workflows_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-workflows.php' );
$menu_src      = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$list_src      = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-templates.php' );
$editor_src    = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );

$clone_fn = strpos( $workflows_src, 'function clone_template' );
$valid_at = false !== $clone_fn ? strpos( $workflows_src, 'validate_submission', $clone_fn ) : false;
$tx_at    = false !== $clone_fn ? strpos( $workflows_src, 'START TRANSACTION', $clone_fn ) : false;
$create_at = false !== $clone_fn ? strpos( $workflows_src, 'self::create', $clone_fn ) : false;
$assert( false !== $valid_at && false !== $tx_at && false !== $create_at && $valid_at < $tx_at && $valid_at < $create_at, 'validate_before_write' );
$assert( false !== strpos( $workflows_src, 'ROLLBACK' ), 'rollback_on_failure' );
$insert_at   = strpos( $workflows_src, 'function insert_cloned_steps' );
$goals_fn_at = false !== $insert_at ? strpos( $workflows_src, 'function copy_material_goals', $insert_at ) : false;
$insert_body = ( false !== $insert_at && false !== $goals_fn_at ) ? substr( $workflows_src, $insert_at, $goals_fn_at - $insert_at ) : '';
$assert( '' !== $insert_body && false !== strpos( $insert_body, "'script_config'" ) && false === strpos( $insert_body, '->script_config' ), 'clone_clears_script_config' );

$handler_at = strpos( $menu_src, 'function handle_workflows_actions' );
$cap_at     = false !== $handler_at ? strpos( $menu_src, 'manage_options', $handler_at ) : false;
$post_at    = false !== $handler_at ? strpos( $menu_src, "isset( \$_POST['som_clone_workflow'] )", $handler_at ) : false;
$nonce_at   = false !== $handler_at ? strpos( $menu_src, "check_admin_referer( 'som_clone_workflow', 'som_workflow_clone_nonce' )", $handler_at ) : false;
$notice_at  = false !== $handler_at ? strpos( $menu_src, 'Cloned from %s.', $handler_at ) : false;
$assert( false !== $cap_at && false !== $post_at && $cap_at < $post_at, 'handler_requires_manage_options' );
$assert( false !== $nonce_at && false !== $post_at && $post_at < $nonce_at, 'handler_post_nonce' );
$assert( false !== $notice_at, 'handler_cloned_from_notice' );
$assert( false === strpos( $menu_src, "\$_GET['som_clone_workflow']" ), 'no_mutating_get' );

$assert( false !== strpos( $list_src, "render_clone_button( (int) \$template->id, 'list', 'button-link' )" ), 'list_clone_action' );
$assert( false !== strpos( $editor_src, "render_clone_button( (int) \$template->id, 'editor', 'page-title-action' )" ), 'editor_clone_button' );
$assert( false !== strpos( $workflows_src, 'method="post"' ), 'clone_form_is_post' );
$assert( false !== strpos( $workflows_src, "wp_nonce_field( 'som_clone_workflow', 'som_workflow_clone_nonce' )" ), 'clone_form_nonce' );

$make_name = 'UP12-S6 make ' . $suffix;
$make      = SOM_Workflows::save_submission(
	0,
	array(
		'name'        => $make_name,
		'description' => 'Shop notes for clone',
		'kind'        => 'make',
		'is_active'   => 1,
		'uses_trays'  => 1,
	),
	array(
		array(
			'name'                    => 'Print',
			'requires_manual_confirm' => 1,
			'timer_value'             => '2',
			'timer_unit'              => 'minutes',
			'planned_value'           => '5',
			'planned_unit'            => 'minutes',
			'instructions'            => 'Use **bold** vinyl',
			'tasks'                   => array(
				array(
					'id'    => 't1',
					'label' => 'Check vinyl',
				),
				array(
					'id'    => 't2',
					'label' => 'Load cutter',
				),
			),
		),
		array(
			'name'             => 'Copy label',
			'command_template' => 'Order {{order_number}}',
		),
		array(
			'name'              => 'Confirm cut',
			'confirmation_kind' => SOM_Step_Confirmations::KIND_PRINT_VS_REQUEST,
		),
	)
);
$assert( ! is_wp_error( $make ) && (int) $make > 0, 'create_source_make' );
$make_id = is_wp_error( $make ) ? 0 : (int) $make;

$legacy_script = '{"type":"local","action":"run_thankyou_card_script"}';
if ( $make_id > 0 ) {
	$first_step = SOM_Workflows::get_steps( $make_id );
	$first_id   = ! empty( $first_step[0]->id ) ? (int) $first_step[0]->id : 0;
	$script_set = $first_id > 0
		? $wpdb->update(
			SOM_DB::table( 'workflow_steps' ),
			array( 'script_config' => $legacy_script ),
			array(
				'id'                   => $first_id,
				'workflow_template_id' => $make_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		)
		: false;
	$assert( false !== $script_set, 'source_script_config_set' );
}

$material_id = SOM_Materials::create(
	array(
		'name'      => 'UP12-S6 vinyl ' . $suffix,
		'unit'      => 'sheet',
		'unit_cost' => 1,
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $material_id ) && (int) $material_id > 0, 'create_material' );
$material_id = is_wp_error( $material_id ) ? 0 : (int) $material_id;

if ( $make_id > 0 && $material_id > 0 ) {
	$goal = SOM_Workflow_Material_Goals::upsert(
		array(
			'workflow_template_id'      => $make_id,
			'material_id'               => $material_id,
			'goal_unit_cost'            => '1.25',
			'warning_threshold_percent' => 80,
		)
	);
	$assert( ! is_wp_error( $goal ) && (int) $goal > 0, 'create_goal' );
}

$budget_id = SOM_Budgets::create(
	array(
		'name'           => 'UP12-S6 budget ' . $suffix,
		'type'           => 'manual',
		'funding_method' => 'fixed_amount',
		'funding_value'  => '2.5',
	)
);
$assert( ! is_wp_error( $budget_id ) && (int) $budget_id > 0, 'create_budget' );
$budget_id = is_wp_error( $budget_id ) ? 0 : (int) $budget_id;
if ( $make_id > 0 && $budget_id > 0 ) {
	$linked = SOM_Budgets::set_workflow_links( $budget_id, array( $make_id ) );
	$assert( true === $linked, 'link_budget' );
}

$pack_name = 'UP12-S6 pack ' . $suffix;
$pack      = SOM_Workflows::save_submission(
	0,
	array(
		'name'       => $pack_name,
		'kind'       => 'pack',
		'is_active'  => 1,
		'uses_trays' => 1,
	),
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $pack ) && (int) $pack > 0, 'create_pack' );
$pack_id = is_wp_error( $pack ) ? 0 : (int) $pack;

$post_name = 'UP12-S6 post-ship ' . $suffix;
$post      = SOM_Workflows::save_submission(
	0,
	array(
		'name'        => $post_name,
		'description' => 'Aftercare notes',
		'kind'        => 'post_ship',
		'is_active'   => 1,
		'uses_trays'  => 1,
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

$product_id = 0;
if ( $make_id > 0 ) {
	$product = SOM_Products::create(
		array(
			'name'                           => 'UP12-S6 product ' . $suffix,
			'sku'                            => 'UP12S6-' . $suffix,
			'workflow_template_id'           => $make_id,
			'post_ship_workflow_template_id' => $post_id > 0 ? $post_id : '',
			'is_active'                      => 1,
		)
	);
	$assert( ! is_wp_error( $product ) && (int) $product > 0, 'create_product' );
	$product_id = is_wp_error( $product ) ? 0 : (int) $product;
}

if ( $pack_id > 0 ) {
	$set_pack = SOM_Pack::set_default_template_id( $pack_id );
	$assert( true === $set_pack, 'set_pack_default' );
}
if ( $post_id > 0 ) {
	$set_post = SOM_Post_Ship::set_default_template_id( $post_id );
	$assert( true === $set_post, 'set_post_ship_default' );
}

$source_steps = $make_id > 0 ? $step_sig( $make_id ) : array();
$source_ids   = array();
foreach ( $source_steps as $step ) {
	$source_ids[] = (int) $step['id'];
}
$orders_pack_before = $pack_id > 0 ? $count_where( 'orders', 'pack_workflow_template_id', $pack_id ) : 0;
$orders_post_before = $post_id > 0 ? $count_where( 'orders', 'post_ship_workflow_template_id', $post_id ) : 0;
$progress_before    = $count_progress( 'order_step_progress', $source_ids );
$item_before        = $count_progress( 'order_item_step_progress', $source_ids );
$goals_before       = $make_id > 0 ? $goal_sig( $make_id ) : array();
$budgets_before     = $make_id > 0 ? $budget_ids_for( $make_id ) : array();

$clone_id = 0;
if ( $make_id > 0 ) {
	$cloned = SOM_Workflows::clone_template( $make_id );
	$assert( ! is_wp_error( $cloned ) && (int) $cloned > 0, 'clone_make' );
	$clone_id = is_wp_error( $cloned ) ? 0 : (int) $cloned;
}

$copy = $clone_id > 0 ? SOM_Workflows::get( $clone_id ) : null;
$assert( $copy && $make_name . ' (copy)' === (string) $copy->name, 'copy_name' );
$assert( $copy && 'Shop notes for clone' === (string) $copy->description, 'copy_description' );
$assert( $copy && 'make' === SOM_Workflows::sanitize_kind( $copy->kind ), 'copy_kind' );
$assert( $copy && 1 === (int) $copy->uses_trays, 'copy_uses_trays' );
$assert( $copy && 1 === (int) $copy->is_active, 'copy_stays_active' );
$assert( $clone_id > 0 && $clone_id !== $make_id, 'copy_is_new_row' );

$copy_steps = $clone_id > 0 ? $step_sig( $clone_id ) : array();
$assert( count( $source_steps ) === count( $copy_steps ) && count( $copy_steps ) === 3, 'step_count' );

$source_orders = array();
$copy_orders   = array();
$copy_ids      = array();
$scripts_clear = true;
foreach ( $copy_steps as $step ) {
	$copy_orders[] = (int) $step['step_order'];
	$copy_ids[]    = (int) $step['id'];
	if ( null !== $step['script_config'] ) {
		$scripts_clear = false;
	}
}
$source_orders_list = array();
$source_kept_script = false;
foreach ( $source_steps as $step ) {
	$source_orders_list[] = (int) $step['step_order'];
	if ( $legacy_script === $step['script_config'] ) {
		$source_kept_script = true;
	}
}
$assert( array( 1, 2, 3 ) === $source_orders_list && $source_orders_list === $copy_orders, 'step_order' );
$assert( $scripts_clear, 'copy_script_config_null' );
$assert( $source_kept_script, 'source_script_config_kept' );

$source_compare = $source_steps;
$copy_compare   = $copy_steps;
foreach ( $source_compare as $index => $step ) {
	unset( $source_compare[ $index ]['id'], $source_compare[ $index ]['script_config'] );
}
foreach ( $copy_compare as $index => $step ) {
	unset( $copy_compare[ $index ]['id'], $copy_compare[ $index ]['script_config'] );
}
$assert( $source_compare === $copy_compare, 'copied_step_fields' );
$assert( $copy_ids && $source_ids !== $copy_ids, 'copy_step_ids_differ' );

if ( ! empty( $copy_compare[0] ) ) {
	$assert( 'Print' === $copy_compare[0]['name'], 'step_print_name' );
	$assert( 1 === $copy_compare[0]['requires_manual_confirm'], 'step_print_manual' );
	$assert( 120 === $copy_compare[0]['timer_seconds'], 'step_print_timer' );
	$assert( 300 === $copy_compare[0]['planned_seconds'], 'step_print_planned' );
	$assert( 'Use **bold** vinyl' === $copy_compare[0]['instructions'], 'step_print_instructions' );
	$assert( false !== strpos( (string) $copy_compare[0]['task_list_json'], 'Check vinyl' ), 'step_print_tasks' );
}
if ( ! empty( $copy_compare[1] ) ) {
	$assert( 'Order {{order_number}}' === $copy_compare[1]['command_template'], 'step_command' );
	$assert( 1 === $copy_compare[1]['requires_manual_confirm'], 'step_command_manual' );
}
if ( ! empty( $copy_compare[2] ) ) {
	$assert( SOM_Step_Confirmations::KIND_PRINT_VS_REQUEST === $copy_compare[2]['confirmation_kind'], 'step_confirmation' );
}

$assert( $goals_before === ( $clone_id > 0 ? $goal_sig( $clone_id ) : array() ), 'goals_copied' );
$assert( $goals_before === ( $make_id > 0 ? $goal_sig( $make_id ) : array() ), 'source_goals_kept' );
$assert( ! empty( $goals_before ), 'source_had_a_goal' );
$assert( $budgets_before === ( $clone_id > 0 ? $budget_ids_for( $clone_id ) : array() ), 'budget_links_copied' );
$assert( $budgets_before === ( $make_id > 0 ? $budget_ids_for( $make_id ) : array() ), 'source_budget_links_kept' );
$assert( $budget_id > 0 && in_array( $budget_id, $budgets_before, true ), 'budget_link_is_ours' );

$product_row = $product_id > 0
	? $wpdb->get_row(
		$wpdb->prepare(
			'SELECT workflow_template_id, post_ship_workflow_template_id FROM ' . SOM_DB::table( 'products' ) . ' WHERE id = %d',
			$product_id
		)
	)
	: null;
$assert( $product_row && (int) $product_row->workflow_template_id === $make_id, 'product_stays_on_original' );
$assert( $product_row && (int) $product_row->post_ship_workflow_template_id === $post_id, 'product_post_ship_stays' );
$assert( 0 === ( $clone_id > 0 ? $count_where( 'products', 'workflow_template_id', $clone_id ) : 1 ), 'no_product_points_at_copy' );

$pack_clone = $pack_id > 0 ? SOM_Workflows::clone_template( $pack_id ) : 0;
$assert( ! is_wp_error( $pack_clone ) && (int) $pack_clone > 0, 'clone_pack' );
$pack_clone_id = is_wp_error( $pack_clone ) ? 0 : (int) $pack_clone;
$assert( SOM_Pack::default_template_id() === $pack_id, 'pack_default_unchanged' );
$assert( $pack_clone_id < 1 || SOM_Pack::default_template_id() !== $pack_clone_id, 'pack_default_not_the_copy' );
$assert( $orders_pack_before === ( $pack_id > 0 ? $count_where( 'orders', 'pack_workflow_template_id', $pack_id ) : 0 ), 'orders_keep_pack_template' );
$assert( 0 === ( $pack_clone_id > 0 ? $count_where( 'orders', 'pack_workflow_template_id', $pack_clone_id ) : 1 ), 'orders_not_on_pack_copy' );

$post_clone = $post_id > 0 ? SOM_Workflows::clone_template( $post_id ) : 0;
$assert( ! is_wp_error( $post_clone ) && (int) $post_clone > 0, 'clone_post_ship' );
$post_clone_id = is_wp_error( $post_clone ) ? 0 : (int) $post_clone;
$post_copy     = $post_clone_id > 0 ? SOM_Workflows::get( $post_clone_id ) : null;
$assert( $post_copy && 0 === (int) $post_copy->uses_trays, 'post_ship_copy_trays_off' );
$assert( $post_copy && SOM_Workflows::template_has_arrival_step( $post_clone_id ), 'post_ship_copy_has_arrival' );
$assert( SOM_Post_Ship::default_template_id() === $post_id, 'post_ship_default_unchanged' );
$assert( $post_clone_id < 1 || SOM_Post_Ship::default_template_id() !== $post_clone_id, 'post_ship_default_not_the_copy' );
$assert( get_option( SOM_Post_Ship::OPTION_AUTO_START, null ) === $auto_before, 'post_ship_auto_start_unchanged' );
$assert( $orders_post_before === ( $post_id > 0 ? $count_where( 'orders', 'post_ship_workflow_template_id', $post_id ) : 0 ), 'orders_keep_post_ship_template' );
$assert( 0 === ( $post_clone_id > 0 ? $count_where( 'orders', 'post_ship_workflow_template_id', $post_clone_id ) : 1 ), 'orders_not_on_post_ship_copy' );

$product_after = $product_id > 0
	? $wpdb->get_row(
		$wpdb->prepare(
			'SELECT workflow_template_id, post_ship_workflow_template_id FROM ' . SOM_DB::table( 'products' ) . ' WHERE id = %d',
			$product_id
		)
	)
	: null;
$assert( $product_after && (int) $product_after->workflow_template_id === $make_id, 'product_make_after_post_ship_clone' );
$assert( $product_after && (int) $product_after->post_ship_workflow_template_id === $post_id, 'product_post_ship_after_clone' );
$assert( $progress_before === $count_progress( 'order_step_progress', $source_ids ), 'order_progress_stays' );
$assert( $item_before === $count_progress( 'order_item_step_progress', $source_ids ), 'item_progress_stays' );
$assert( 0 === $count_progress( 'order_step_progress', $copy_ids ), 'order_progress_not_on_copy_steps' );
$assert( 0 === $count_progress( 'order_item_step_progress', $copy_ids ), 'item_progress_not_on_copy_steps' );

$restore_defaults();
$assert( get_option( SOM_Pack::OPTION_TEMPLATE, null ) === $pack_before, 'pack_default_restored' );
$assert( get_option( SOM_Post_Ship::OPTION_TEMPLATE, null ) === $post_ship_before, 'post_ship_default_restored' );
$assert( get_option( SOM_Post_Ship::OPTION_AUTO_START, null ) === $auto_before, 'auto_start_restored' );

$missing_name  = 'UP12-S6 no arrival ' . $suffix;
$before_missing = $template_count();
$now            = current_time( 'mysql', true );
$legacy_ins     = $wpdb->insert(
	SOM_DB::table( 'workflow_templates' ),
	array(
		'name'        => $missing_name,
		'description' => 'must not be cloned',
		'kind'        => 'post_ship',
		'uses_trays'  => 0,
		'is_active'   => 1,
		'created_at'  => $now,
		'updated_at'  => $now,
	),
	array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
);
$legacy_id = $legacy_ins ? (int) $wpdb->insert_id : 0;
$assert( $legacy_id > 0, 'legacy_post_ship_inserted' );
$legacy_step = $legacy_id > 0
	? $wpdb->insert(
		SOM_DB::table( 'workflow_steps' ),
		array(
			'workflow_template_id'    => $legacy_id,
			'step_order'              => 1,
			'name'                    => 'Update buyer',
			'requires_manual_confirm' => 1,
			'created_at'              => $now,
			'updated_at'              => $now,
		),
		array( '%d', '%d', '%s', '%d', '%s', '%s' )
	)
	: false;
$assert( (bool) $legacy_step, 'legacy_step_inserted' );
$after_insert   = $template_count();
$legacy_clone   = $legacy_id > 0 ? SOM_Workflows::clone_template( $legacy_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( is_wp_error( $legacy_clone ), 'missing_arrival_errors' );
$assert( is_wp_error( $legacy_clone ) && 'som_post_ship_arrival_required' === $legacy_clone->get_error_code(), 'missing_arrival_code' );
$assert( is_wp_error( $legacy_clone ) && $arrival_message === $legacy_clone->get_error_message(), 'missing_arrival_message' );
$assert( $after_insert === $template_count(), 'missing_arrival_no_new_row' );
$assert( 0 === $name_count( $missing_name . ' (copy)' ), 'missing_arrival_copy_name_absent' );
$assert( 1 === $name_count( $missing_name ), 'missing_arrival_source_remains' );
$assert( $legacy_id > 0 && ! SOM_Workflows::template_has_arrival_step( $legacy_id ), 'missing_arrival_source_not_repaired' );
$assert( $after_insert === $before_missing + 1, 'legacy_insert_was_the_only_new_row' );

$combo_name = 'UP12-S6 combo ' . $suffix;
$combo      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $combo_name,
		'kind'      => 'make',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Confirm only',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => SOM_Step_Confirmations::KIND_PACKING_ITEMS,
		),
	)
);
$assert( ! is_wp_error( $combo ) && (int) $combo > 0, 'create_combo_source' );
$combo_id = is_wp_error( $combo ) ? 0 : (int) $combo;
if ( $combo_id > 0 ) {
	$combo_steps = SOM_Workflows::get_steps( $combo_id );
	$combo_step  = ! empty( $combo_steps[0]->id ) ? (int) $combo_steps[0]->id : 0;
	$corrupted   = $combo_step > 0
		? $wpdb->update(
			SOM_DB::table( 'workflow_steps' ),
			array(
				'timer_seconds'     => 90,
				'confirmation_kind' => SOM_Step_Confirmations::KIND_PACKING_ITEMS,
			),
			array(
				'id'                   => $combo_step,
				'workflow_template_id' => $combo_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		)
		: false;
	$assert( false !== $corrupted, 'combo_step_corrupted' );
}
$before_combo = $template_count();
$combo_clone  = $combo_id > 0 ? SOM_Workflows::clone_template( $combo_id ) : new WP_Error( 'skipped', 'skipped' );
$assert( is_wp_error( $combo_clone ) && 'som_confirmation_only_step' === $combo_clone->get_error_code(), 'combo_errors' );
$assert( is_wp_error( $combo_clone ) && $combo_message === $combo_clone->get_error_message(), 'combo_message' );
$assert( $before_combo === $template_count(), 'combo_no_new_row' );
$assert( 0 === $name_count( $combo_name . ' (copy)' ), 'combo_copy_name_absent' );
$combo_after = $combo_id > 0 ? SOM_Workflows::get_steps( $combo_id ) : array();
$assert(
	! empty( $combo_after[0] ) && 90 === (int) $combo_after[0]->timer_seconds && SOM_Step_Confirmations::KIND_PACKING_ITEMS === (string) $combo_after[0]->confirmation_kind,
	'combo_source_not_repaired'
);

$long_name = str_repeat( 'N', 100 );
$long      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $long_name,
		'kind'      => 'make',
		'is_active' => 0,
	),
	array(
		array(
			'name'                    => 'Only step',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $long ) && (int) $long > 0, 'create_long_inactive' );
$long_id = is_wp_error( $long ) ? 0 : (int) $long;
if ( $long_id > 0 ) {
	$long_row = SOM_Workflows::get( $long_id );
	if ( $long_row && som_up12_s6_strlen( (string) $long_row->name ) < 100 ) {
		$wpdb->update(
			SOM_DB::table( 'workflow_templates' ),
			array( 'name' => $long_name ),
			array( 'id' => $long_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
$long_clone = $long_id > 0 ? SOM_Workflows::clone_template( $long_id ) : 0;
$assert( ! is_wp_error( $long_clone ) && (int) $long_clone > 0, 'clone_long_inactive' );
$long_copy = ! is_wp_error( $long_clone ) && (int) $long_clone > 0 ? SOM_Workflows::get( (int) $long_clone ) : null;
$expected_long = str_repeat( 'N', 93 ) . ' (copy)';
$assert( $long_copy && $expected_long === (string) $long_copy->name, 'long_name_trimmed' );
$assert( $long_copy && 100 === som_up12_s6_strlen( (string) $long_copy->name ), 'long_name_fits_column' );
$assert( $long_copy && 0 === (int) $long_copy->is_active, 'inactive_copy_stays_inactive' );
$long_source = $long_id > 0 ? SOM_Workflows::get( $long_id ) : null;
$assert( $long_source && 0 === (int) $long_source->is_active, 'inactive_source_stays_inactive' );

$missing = SOM_Workflows::clone_template( 0 );
$assert( is_wp_error( $missing ) && 'som_workflow_missing' === $missing->get_error_code(), 'missing_template_errors' );

if ( $fail > 0 ) {
	echo "FAIL — UP12-S6 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP12-S6 smoke\n";
