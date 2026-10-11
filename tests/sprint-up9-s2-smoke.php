<?php
/**
 * UP9-S2 smoke: remove local/api/n8n; legacy script_config ignored; seed copy-command.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up9-s2-smoke.php
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
SOM_Seed::maybe_seed_catalogue();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );
$assert( class_exists( 'SOM_Copy_Command' ), 'class_SOM_Copy_Command' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.21.0', '>=' ), 'db_gte_1.21.0' );

// --- Editor: script UI gone; legacy warning copy present ---
$editor_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$assert( false !== strpos( $editor_src, 'Copy command template' ), 'editor_copy_template' );
$assert( false !== strpos( $editor_src, 'Legacy script_config (disabled)' ), 'editor_legacy_warning' );
$assert( false === strpos( $editor_src, 'som-script-type' ), 'editor_no_script_type_select' );
$assert( false === strpos( $editor_src, 'data-script-panel' ), 'editor_no_script_panels' );
$assert( false === strpos( $editor_src, 'script_raw_mode' ), 'editor_no_raw_script' );
$assert( false === strpos( $editor_src, 'Script / API (legacy' ), 'editor_no_legacy_details' );

$js_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$assert( false === strpos( $js_src, 'syncScriptPanels' ), 'js_no_sync_script_panels' );
$assert( false === strpos( $js_src, 'som-script-type' ), 'js_no_script_type' );
$assert( false !== strpos( $js_src, 'som-command-template' ), 'js_keeps_copy_gate' );

// --- Dispatch hard-disabled ---
$exec = SOM_Script_Dispatch::execute( (object) array(), (object) array( 'script_config' => '{"type":"local","action":"run_thankyou_card_script"}' ), (object) array( 'id' => 1 ) );
$assert( is_wp_error( $exec ) && 'som_script_removed' === $exec->get_error_code(), 'execute_disabled' );

// --- Seed: Print step has /make-sticker-order copy template ---
$make_id = (int) get_option( 'som_seed_workflow_id', 0 );
$assert( $make_id > 0, 'seed_make_workflow_id' );
$print = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'workflow_steps' ) . ' WHERE workflow_template_id = %d AND name = %s ORDER BY step_order ASC LIMIT 1',
		$make_id,
		'Print'
	)
);
$assert( $print && false !== strpos( (string) $print->command_template, '/make-sticker-order' ), 'seed_print_copy_template' );
$assert( $print && ( null === $print->script_config || '' === trim( (string) $print->script_config ) ), 'seed_print_no_script_config' );

// --- Docs ---
$guide = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md' );
$ref   = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-REFERENCE.md' );
$feat  = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/FEATURES-AND-TESTING.md' );
$assert( false !== strpos( $guide, 'Copy command' ), 'guide_copy_command' );
$assert( false !== strpos( $guide, 'script steps are removed' ) || false !== strpos( $guide, 'Script / n8n' ), 'guide_scripts_removed' );
$assert( false !== strpos( $ref, 'Copy command template' ), 'ref_copy_template' );
$assert( false !== strpos( $ref, 'Removed:' ) || false !== strpos( $ref, 'script_config' ), 'ref_scripts_removed' );
$assert( false !== strpos( $feat, 'Copy command' ), 'features_copy_command' );
$assert( false !== strpos( $feat, 'Removed' ) && false !== strpos( $feat, 'UP9-S2' ), 'features_up9_s2' );

$suffix = wp_generate_password( 4, false, false );

// --- Runtime: legacy script_config alone → in_progress, never waiting_script ---
$wf = SOM_Workflows::create(
	array(
		'name'      => 'UP9-S2 legacy ' . $suffix,
		'kind'      => 'make',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $wf ), 'create_legacy_template' );
$wf_id = (int) $wf;

$saved = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'name'                    => 'Legacy script step',
			'requires_manual_confirm' => 0,
		),
	)
);
$assert( true === $saved, 'save_blank_step' );

$steps = SOM_Workflows::get_steps( $wf_id );
$assert( 1 === count( $steps ), 'one_legacy_step' );
$step_id = (int) $steps[0]->id;

$legacy_json = wp_json_encode(
	array(
		'type'   => 'local',
		'action' => 'run_thankyou_card_script',
		'params' => array(),
	)
);
$wpdb->update(
	SOM_DB::table( 'workflow_steps' ),
	array(
		'script_config'           => $legacy_json,
		'requires_manual_confirm' => 0,
		'command_template'        => null,
		'updated_at'              => current_time( 'mysql', true ),
	),
	array( 'id' => $step_id ),
	array( '%s', '%d', '%s', '%s' ),
	array( '%d' )
);

$steps = SOM_Workflows::get_steps( $wf_id );
$assert( SOM_Script_Dispatch::has_script( $steps[0] ), 'legacy_has_script_flag' );
$assert( ! SOM_Copy_Command::has_template( $steps[0] ), 'legacy_no_copy' );

// Save from editor path clears script_config.
$cleared = SOM_Workflows::save_steps(
	$wf_id,
	array(
		array(
			'id'                      => $step_id,
			'name'                    => 'Legacy script step',
			'requires_manual_confirm' => 1,
			'script_type'             => 'local',
			'script_action'           => 'run_thankyou_card_script',
		),
	)
);
$assert( true === $cleared, 'save_clears_script' );
$steps_cleared = SOM_Workflows::get_steps( $wf_id );
$assert( empty( $steps_cleared[0]->script_config ), 'script_cleared_on_save' );

// Re-inject legacy for enter-step runtime check.
$wpdb->update(
	SOM_DB::table( 'workflow_steps' ),
	array(
		'script_config'           => $legacy_json,
		'requires_manual_confirm' => 0,
		'command_template'        => null,
		'updated_at'              => current_time( 'mysql', true ),
	),
	array( 'id' => $step_id ),
	array( '%s', '%d', '%s', '%s' ),
	array( '%d' )
);

$product = SOM_Products::create(
	array(
		'name'                 => 'UP9-S2 product ' . $suffix,
		'sku'                  => 'UP9S2-' . $suffix,
		'workflow_template_id' => $wf_id,
		'is_active'            => 1,
	)
);
$assert( ! is_wp_error( $product ), 'create_product' );
$product_id = (int) $product;

$order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up9-s2-' . $suffix,
		'buyer_name'        => 'Alex Tester',
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
			),
		),
	)
);
$assert( ! is_wp_error( $order_id ), 'create_order' );
$order_id = (int) $order_id;

$order = SOM_Orders::get( $order_id );
$assert( $order && ! empty( $order->make_lines ), 'order_make_lines' );
$make_item = $order->make_lines[0];
$current   = isset( $make_item->make_current ) ? $make_item->make_current : null;
$assert( $current && 'in_progress' === (string) $current->status, 'legacy_enters_in_progress' );
$assert( 'waiting_script' !== (string) $current->status, 'legacy_not_waiting_script' );

$waiting = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'order_item_step_progress' ) . " WHERE order_item_id = %d AND status = 'waiting_script'",
		(int) $make_item->id
	)
);
$assert( 0 === $waiting, 'no_waiting_script_rows' );

// Pack/order engine path: reflect enter_step with legacy script_config → in_progress.
$pack_wf = SOM_Workflows::create(
	array(
		'name'      => 'UP9-S2 pack legacy ' . $suffix,
		'kind'      => 'pack',
		'is_active' => 1,
	)
);
$assert( ! is_wp_error( $pack_wf ), 'create_pack_legacy_template' );
$pack_wf_id = (int) $pack_wf;
$pack_saved = SOM_Workflows::save_steps(
	$pack_wf_id,
	array(
		array(
			'name'                    => 'Pack legacy',
			'requires_manual_confirm' => 0,
		),
	)
);
$assert( true === $pack_saved, 'save_pack_legacy_step' );
$pack_steps   = SOM_Workflows::get_steps( $pack_wf_id );
$pack_step_id = (int) $pack_steps[0]->id;
$wpdb->update(
	SOM_DB::table( 'workflow_steps' ),
	array(
		'script_config'           => $legacy_json,
		'requires_manual_confirm' => 0,
		'updated_at'              => current_time( 'mysql', true ),
	),
	array( 'id' => $pack_step_id ),
	array( '%s', '%d', '%s' ),
	array( '%d' )
);

$pack_order_id = SOM_Order_Sync::create_from_external(
	array(
		'channel'           => 'external',
		'external_order_id' => 'up9-s2-pack-' . $suffix,
		'buyer_name'        => 'Blake Tester',
		'items'             => array(
			array(
				'product_id' => $product_id,
				'quantity'   => 1,
			),
		),
	)
);
$assert( ! is_wp_error( $pack_order_id ), 'create_pack_order' );
$pack_order_id = (int) $pack_order_id;

$now = current_time( 'mysql', true );
$wpdb->insert(
	SOM_DB::table( 'order_step_progress' ),
	array(
		'order_id'         => $pack_order_id,
		'workflow_step_id' => $pack_step_id,
		'status'           => 'pending',
		'retry_count'      => 0,
	),
	array( '%d', '%d', '%s', '%d' )
);
$wpdb->update(
	SOM_DB::table( 'orders' ),
	array(
		'current_step_id' => $pack_step_id,
		'updated_at'      => $now,
	),
	array( 'id' => $pack_order_id ),
	array( '%d', '%s' ),
	array( '%d' )
);

$enter = new ReflectionMethod( 'SOM_Workflow_Engine', 'enter_step' );
$enter->setAccessible( true );
$entered = $enter->invoke( null, $pack_order_id, $pack_step_id );
$assert( true === $entered || ! is_wp_error( $entered ), 'pack_enter_ok' );
$pack_progress = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM ' . SOM_DB::table( 'order_step_progress' ) . ' WHERE order_id = %d AND workflow_step_id = %d LIMIT 1',
		$pack_order_id,
		$pack_step_id
	)
);
$assert( $pack_progress && 'in_progress' === (string) $pack_progress->status, 'pack_legacy_in_progress' );
$assert( $pack_progress && 'waiting_script' !== (string) $pack_progress->status, 'pack_legacy_not_waiting_script' );

// --- Batches O6: script release soft-fails without Local_Actions dispatch ---
$group = $wpdb->get_row(
	"SELECT * FROM " . SOM_DB::table( 'batch_groups' ) . " WHERE group_key = 'thank_you_card' LIMIT 1"
);
$assert( $group && 'script' === (string) $group->action_type, 'thank_you_group_script_type' );

$now = current_time( 'mysql', true );
$wpdb->insert(
	SOM_DB::table( 'step_batches' ),
	array(
		'batch_group_id'    => (int) $group->id,
		'status'            => 'collecting',
		'released_manually' => 0,
		'retry_count'       => 0,
		'created_at'        => $now,
		'updated_at'        => $now,
	),
	array( '%d', '%s', '%d', '%d', '%s', '%s' )
);
$batch_id = (int) $wpdb->insert_id;
$assert( $batch_id > 0, 'batch_row_created' );

$release = SOM_Batches::release( $batch_id, true );
$assert( is_wp_error( $release ), 'script_batch_soft_fail' );
$batch_after = SOM_Batches::get( $batch_id );
$assert( $batch_after && 'error' === (string) $batch_after->status, 'script_batch_error_status' );
$assert(
	$batch_after && false !== strpos( (string) $batch_after->last_error, 'disabled' ),
	'script_batch_error_message'
);

$out( 'failures', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "UP9-S2 smoke OK\n";
