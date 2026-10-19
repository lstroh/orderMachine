<?php
/**
 * UP7-S1 smoke: zero-gate auto-done, gated first step stays in_progress, checklist gates Mark done.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up7-s1-smoke.php
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
SOM_Seed::maybe_seed_catalogue();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );
$assert( version_compare( SOM_VERSION, '0.32.0', '>=' ), 'SOM_VERSION_gte_0.32.0' );
$assert( class_exists( 'SOM_Item_Make' ), 'class_SOM_Item_Make' );

// View / copy checks (no schema bump for S1).
$detail_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( is_string( $detail_src ) && false === strpos( $detail_src, 'Pack & Ship controls arrive in UP6-S2' ), 'no_stale_up6_s2_make_copy' );
$assert( is_string( $detail_src ) && false !== strpos( $detail_src, 'Confirmation checklist — %s' ), 'checklist_heading_includes_step_name' );
$make_ol_pos      = strpos( $detail_src, 'som-workflow-progress' );
$make_confirm_pos = strpos( $detail_src, 'som_save_confirmation' );
$assert( false !== $make_ol_pos && false !== $make_confirm_pos && $make_confirm_pos > $make_ol_pos, 'make_checklist_after_step_list_open' );

$editor_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$assert( is_string( $editor_src ) && false !== strpos( $editor_src, 'data-som-zero-gate-warning' ), 'editor_zero_gate_warning_markup' );
$assert( is_string( $editor_src ) && false !== strpos( $editor_src, 'will auto-complete when entered' ), 'editor_zero_gate_copy' );

$js_src = file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$assert( is_string( $js_src ) && false !== strpos( $js_src, 'stepHasGate' ), 'js_step_has_gate_helper' );
$assert( is_string( $js_src ) && false !== strpos( $js_src, 'will auto-complete when entered' ), 'js_zero_gate_save_warn' );

$suffix    = wp_generate_password( 4, false, false );
$prev_pack = SOM_Pack::default_template_id();

// Pack template with gated first step (same enter_step zero-gate rule as make).
$wf_pack = SOM_Workflows::create(
	array(
		'name'      => 'UP7-S1 pack ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf_pack ), 'create_pack_template' );
$wf_pack_id = (int) $wf_pack;
$saved_pack = SOM_Workflows::save_steps(
	$wf_pack_id,
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'packing_items',
		),
		array(
			'name'                    => 'Ship',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( true === $saved_pack, 'save_pack_steps' );
$assert( true === SOM_Pack::set_default_template_id( $wf_pack_id ), 'set_default_pack' );

// --- A: ungated first make step auto-dones; checklist gates Mark done on current step ---
$wf_zero = SOM_Workflows::create(
	array(
		'name'      => 'UP7-S1 zero ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf_zero ), 'create_zero_gate_template' );
$wf_zero_id = (int) $wf_zero;

$saved_zero = SOM_Workflows::save_steps(
	$wf_zero_id,
	array(
		array(
			'name' => 'Create PDF',
			// Intentionally no gates — pass-through.
		),
		array(
			'name'                    => 'Print on plain paper',
			'requires_manual_confirm' => 1,
			'confirmation_kind'       => 'print_vs_request',
		),
	)
);
$assert( true === $saved_zero, 'save_zero_gate_steps' );

$product_zero = SOM_Products::create(
	array(
		'name'                 => 'UP7-S1 zero product ' . $suffix,
		'sku'                  => 'UP7S1Z-' . $suffix,
		'workflow_template_id' => $wf_zero_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product_zero ), 'create_zero_gate_product' );
$product_zero_id = (int) $product_zero;

$order_zero_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up7s1z-' . $suffix,
		'buyer_name'        => 'UP7-S1 Zero',
		'items'             => array(
			array(
				'product_id'           => $product_zero_id,
				'quantity'             => 1,
				'unit_price'           => 5,
				'personalisation_text' => 'Zero gate',
			),
		),
	)
);
$assert( ! is_wp_error( $order_zero_id ), 'create_zero_gate_order' );
$order_zero_id = (int) $order_zero_id;

$order_zero = SOM_Orders::get( $order_zero_id );
$assert( $order_zero && ! empty( $order_zero->items ), 'zero_order_loaded' );
$item_zero  = $order_zero->items[0];
$progress_z = SOM_Item_Make::get_item_progress( (int) $item_zero->id );
$assert( is_array( $progress_z ) && count( $progress_z ) >= 2, 'zero_order_has_two_steps' );

$create_pdf = null;
$print_step = null;
foreach ( $progress_z as $row ) {
	if ( 'Create PDF' === (string) $row->step_name ) {
		$create_pdf = $row;
	}
	if ( 'Print on plain paper' === (string) $row->step_name ) {
		$print_step = $row;
	}
}
$assert( $create_pdf && 'done' === (string) $create_pdf->status, 'ungated_create_pdf_auto_done' );
$assert( $print_step && 'in_progress' === (string) $print_step->status, 'print_step_in_progress_after_passthrough' );

$current_z = SOM_Item_Make::current_item_progress( (int) $item_zero->id );
$assert( $current_z && 'Print on plain paper' === (string) $current_z->step_name, 'current_is_print_after_zero_gate' );
$assert( ! empty( $current_z->confirmation_kind ), 'current_print_has_confirmation_kind' );

$blocked_z = SOM_Item_Make::mark_done( $order_zero_id, (int) $item_zero->id );
$assert(
	is_wp_error( $blocked_z ) && 'som_confirmation_required' === $blocked_z->get_error_code(),
	'checklist_gates_mark_done_on_current_step'
);

$saved_ticks = SOM_Step_Confirmations::save_for_order(
	$order_zero_id,
	array( 'print_matches_request' => 1 ),
	(int) $item_zero->id
);
$assert( true === $saved_ticks, 'save_print_checklist_for_line' );

$current_z2 = SOM_Item_Make::current_item_progress( (int) $item_zero->id );
$step_z2    = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE id = %d LIMIT 1',
		(int) $current_z2->workflow_step_id
	)
);
$assert( SOM_Workflow_Engine::can_mark_done( $current_z2, $step_z2 ), 'can_mark_done_after_checklist' );

$done_z = SOM_Item_Make::mark_done( $order_zero_id, (int) $item_zero->id );
$assert( ! is_wp_error( $done_z ), 'mark_print_done_after_checklist' );

// Pack bound on create: gated first pack step must not auto-done.
$order_zero = SOM_Orders::get( $order_zero_id );
$assert( (int) $order_zero->pack_workflow_template_id === $wf_pack_id, 'pack_bound_on_zero_order' );
$pack_current = null;
foreach ( $order_zero->workflow_progress as $prow ) {
	if ( (int) $prow->workflow_step_id === (int) $order_zero->current_step_id ) {
		$pack_current = $prow;
		break;
	}
}
$assert( $pack_current && 'Confirm pack' === (string) $pack_current->step_name, 'pack_current_is_confirm_pack' );
$assert( 'in_progress' === (string) $pack_current->status, 'pack_gated_first_step_in_progress' );
$assert( 'done' !== (string) $pack_current->status, 'pack_gated_first_step_not_done' );

// --- B: gated first make step stays in_progress (engine not buggy when gates set) ---
$wf_gated = SOM_Workflows::create(
	array(
		'name'      => 'UP7-S1 gated ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf_gated ), 'create_gated_template' );
$wf_gated_id = (int) $wf_gated;

$saved_gated = SOM_Workflows::save_steps(
	$wf_gated_id,
	array(
		array(
			'name'                    => 'Create PDF',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Print',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( true === $saved_gated, 'save_gated_steps' );

$product_gated = SOM_Products::create(
	array(
		'name'                 => 'UP7-S1 gated product ' . $suffix,
		'sku'                  => 'UP7S1G-' . $suffix,
		'workflow_template_id' => $wf_gated_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product_gated ), 'create_gated_product' );
$product_gated_id = (int) $product_gated;

$order_gated_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up7s1g-' . $suffix,
		'buyer_name'        => 'UP7-S1 Gated',
		'items'             => array(
			array(
				'product_id' => $product_gated_id,
				'quantity'   => 1,
				'unit_price' => 5,
			),
		),
	)
);
$assert( ! is_wp_error( $order_gated_id ), 'create_gated_order' );
$order_gated_id = (int) $order_gated_id;

$order_gated = SOM_Orders::get( $order_gated_id );
$item_gated  = $order_gated->items[0];
$current_g   = SOM_Item_Make::current_item_progress( (int) $item_gated->id );
$assert( $current_g && 'Create PDF' === (string) $current_g->step_name, 'gated_first_step_is_current' );
$assert( 'in_progress' === (string) $current_g->status, 'gated_first_step_stays_in_progress' );

$first_done_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'order_item_step_progress' ) . ' p
		INNER JOIN ' . SOM_DB::table( 'workflow_steps' ) . ' s ON s.id = p.workflow_step_id
		WHERE p.order_item_id = %d AND s.name = %s AND p.status = %s',
		(int) $item_gated->id,
		'Create PDF',
		'done'
	)
);
$assert( 0 === $first_done_count, 'gated_create_pdf_not_auto_done' );

if ( $prev_pack > 0 ) {
	SOM_Pack::set_default_template_id( (int) $prev_pack );
}

$out( 'fail_count', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "UP7-S1 smoke OK\n";
