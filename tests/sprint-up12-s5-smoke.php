<?php
/**
 * UP12-S5 smoke: Post-ship save requires a Package arrived step, and a failed save writes nothing.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s5-smoke.php
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

$expected_message = 'A Post-ship workflow needs at least one step with Confirmation checklist = Package arrived. Nothing was saved.';

$name_count = static function ( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . SOM_DB::table( 'workflow_templates' ) . ' WHERE name = %s',
			$name
		)
	);
};

$step_sig = static function ( $template_id ) {
	$out = array();
	foreach ( SOM_Workflows::get_steps( $template_id ) as $step ) {
		$out[] = array(
			'id'                => (int) $step->id,
			'name'              => (string) $step->name,
			'confirmation_kind' => isset( $step->confirmation_kind ) ? (string) $step->confirmation_kind : '',
			'step_order'        => (int) $step->step_order,
		);
	}
	return $out;
};

$suffix = gmdate( 'YmdHis' ) . '-' . wp_rand( 1000, 9999 );
$note   = array(
	'name'                    => 'Update buyer',
	'requires_manual_confirm' => 1,
);
$arrived = array(
	'name'                    => 'Confirm package arrived',
	'requires_manual_confirm' => 1,
	'confirmation_kind'       => SOM_Step_Confirmations::KIND_PACKAGE_ARRIVED,
);

$out( 'plugin', SOM_VERSION );
$assert( method_exists( 'SOM_Workflows', 'validate_submission' ), 'method_validate_submission' );
$assert( method_exists( 'SOM_Workflows', 'save_submission' ), 'method_save_submission' );
$assert( method_exists( 'SOM_Workflows', 'template_has_arrival_step' ), 'method_template_has_arrival_step' );
$assert( method_exists( 'SOM_Workflows', 'steps_include_arrival' ), 'method_steps_include_arrival' );
$assert( $expected_message === SOM_Workflows::post_ship_arrival_required_message(), 'arrival_message' );

$workflows_src = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/class-som-workflows.php' );
$menu_src      = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$save_fn       = strpos( $workflows_src, 'function save_submission' );
$valid_in_save = false !== $save_fn ? strpos( $workflows_src, 'validate_submission', $save_fn ) : false;
$create_in_save = false !== $save_fn ? strpos( $workflows_src, 'self::create', $save_fn ) : false;
$assert( false !== $valid_in_save && false !== $create_in_save && $valid_in_save < $create_in_save, 'validate_before_create' );
$steps_fn      = strpos( $workflows_src, 'function save_steps' );
$assert( false !== $steps_fn && false !== strpos( $workflows_src, 'assert_post_ship_has_arrival', $steps_fn ), 'save_steps_checks_arrival' );
$assert( false !== strpos( $menu_src, 'SOM_Workflows::save_submission' ), 'handler_uses_save_submission' );

$editor = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$list   = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-templates.php' );
$settings = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/settings.php' );
$detail = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$js     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$seed   = (string) file_get_contents( SOM_PLUGIN_DIR . 'includes/seed/class-som-seed.php' );

$assert( false !== strpos( $list, 'No arrival step' ), 'list_badge_copy' );
$assert( false !== strpos( $list, 'template_has_arrival_step' ), 'list_uses_helper' );
$assert( false !== strpos( $editor, 'som-post-ship-arrival-error' ), 'editor_submit_error' );
$assert( false !== strpos( $editor, 'data-som-arrival-kind' ), 'editor_arrival_kind_attr' );
$assert( false !== strpos( $editor, 'post_ship_arrival_required_message' ), 'editor_error_copy' );
$assert( false !== strpos( $editor, 'show_arrival_warning' ), 'editor_top_warning' );
$assert( false !== strpos( $settings, '(no arrival step)' ), 'settings_option_suffix' );
$assert( false !== strpos( $settings, 'template_has_arrival_step' ), 'settings_uses_helper' );
$assert( false !== strpos( $detail, 'Fix the template for future orders; this order keeps its current steps.' ), 'order_notice_copy' );
$assert( false !== strpos( $detail, 'steps_include_arrival' ), 'order_notice_uses_bound_steps' );
$assert( false !== strpos( $js, 'som-post-ship-arrival-error' ), 'js_shows_error' );
$assert( false !== strpos( $js, 'data-som-arrival-kind' ), 'js_reads_arrival_kind' );
$assert( false !== strpos( $seed, "'confirmation_kind'       => 'package_arrived'" ), 'seed_aftercare_still_has_arrival' );

$missing_name = 'UP12-S5 missing arrival ' . $suffix;
$assert( 0 === $name_count( $missing_name ), 'missing_name_absent_before' );
$missing = SOM_Workflows::save_submission(
	0,
	array(
		'name'        => $missing_name,
		'description' => 'should not be stored',
		'kind'        => 'post_ship',
		'is_active'   => 1,
	),
	array( $note )
);
$assert( is_wp_error( $missing ), 'create_without_arrival_errors' );
$assert( is_wp_error( $missing ) && 'som_post_ship_arrival_required' === $missing->get_error_code(), 'create_without_arrival_code' );
$assert( is_wp_error( $missing ) && $expected_message === $missing->get_error_message(), 'create_without_arrival_message' );
$assert( 0 === $name_count( $missing_name ), 'create_without_arrival_no_row' );

$inactive_missing_name = 'UP12-S5 inactive missing ' . $suffix;
$inactive_missing      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $inactive_missing_name,
		'kind'      => 'post_ship',
		'is_active' => 0,
	),
	array( $note )
);
$assert( is_wp_error( $inactive_missing ), 'inactive_without_arrival_errors' );
$assert( 0 === $name_count( $inactive_missing_name ), 'inactive_without_arrival_no_row' );

$good_name = 'UP12-S5 with arrival ' . $suffix;
$good      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $good_name,
		'kind'      => 'post_ship',
		'is_active' => 1,
	),
	array( $note, $arrived )
);
$assert( ! is_wp_error( $good ) && (int) $good > 0, 'save_with_arrival' );
$good_id = (int) $good;
$good_row = SOM_Workflows::get( $good_id );
$assert( $good_row && 'post_ship' === SOM_Workflows::sanitize_kind( $good_row->kind ), 'saved_kind_post_ship' );
$assert( SOM_Workflows::template_has_arrival_step( $good_id ), 'saved_template_has_arrival' );
$assert( SOM_Workflows::steps_include_arrival( $good_row ? $good_row->steps : array() ), 'saved_steps_include_arrival' );

$before_name  = $good_row ? (string) $good_row->name : '';
$before_kind  = $good_row ? SOM_Workflows::sanitize_kind( $good_row->kind ) : '';
$before_steps = $step_sig( $good_id );
$kept         = array();
foreach ( $before_steps as $step ) {
	if ( SOM_Step_Confirmations::KIND_PACKAGE_ARRIVED === $step['confirmation_kind'] ) {
		continue;
	}
	$kept[] = array(
		'id'                      => $step['id'],
		'name'                    => $step['name'],
		'requires_manual_confirm' => 1,
	);
}
$removed = SOM_Workflows::save_submission(
	$good_id,
	array(
		'name'        => $before_name . ' renamed',
		'description' => 'should not stick',
		'kind'        => 'post_ship',
		'is_active'   => 1,
	),
	$kept
);
$assert( is_wp_error( $removed ) && $expected_message === $removed->get_error_message(), 'remove_arrival_errors' );
$after_remove = SOM_Workflows::get( $good_id );
$assert( $after_remove && $before_name === (string) $after_remove->name, 'remove_arrival_name_unchanged' );
$assert( $after_remove && $before_kind === SOM_Workflows::sanitize_kind( $after_remove->kind ), 'remove_arrival_kind_unchanged' );
$assert( $before_steps === $step_sig( $good_id ), 'remove_arrival_steps_unchanged' );

$direct = SOM_Workflows::save_steps( $good_id, $kept );
$assert( is_wp_error( $direct ) && $expected_message === $direct->get_error_message(), 'save_steps_remove_arrival_errors' );
$assert( $before_steps === $step_sig( $good_id ), 'save_steps_remove_arrival_steps_unchanged' );

$make_name = 'UP12-S5 make ' . $suffix;
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
	)
);
$assert( ! is_wp_error( $make ) && (int) $make > 0, 'save_make_without_arrival' );
$make_id   = (int) $make;
$make_row  = SOM_Workflows::get( $make_id );
$make_steps = $step_sig( $make_id );
$make_kept  = array();
foreach ( $make_steps as $step ) {
	$make_kept[] = array(
		'id'                      => $step['id'],
		'name'                    => $step['name'],
		'requires_manual_confirm' => 1,
	);
}
$kind_change = SOM_Workflows::save_submission(
	$make_id,
	array(
		'name'      => $make_name . ' renamed',
		'kind'      => 'post_ship',
		'is_active' => 1,
	),
	$make_kept
);
$assert( is_wp_error( $kind_change ) && $expected_message === $kind_change->get_error_message(), 'kind_change_without_arrival_errors' );
$make_after = SOM_Workflows::get( $make_id );
$assert( $make_after && 'make' === SOM_Workflows::sanitize_kind( $make_after->kind ), 'kind_change_kind_unchanged' );
$assert( $make_after && $make_name === (string) $make_after->name, 'kind_change_name_unchanged' );
$assert( $make_steps === $step_sig( $make_id ), 'kind_change_steps_unchanged' );

$inactive_name = 'UP12-S5 inactive with arrival ' . $suffix;
$inactive      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $inactive_name,
		'kind'      => 'post_ship',
		'is_active' => 0,
	),
	array( $note, $arrived )
);
$assert( ! is_wp_error( $inactive ) && (int) $inactive > 0, 'inactive_with_arrival_saves' );
$inactive_id  = (int) $inactive;
$inactive_row = SOM_Workflows::get( $inactive_id );
$assert( $inactive_row && 0 === (int) $inactive_row->is_active, 'inactive_flag_stored' );
$assert( SOM_Workflows::template_has_arrival_step( $inactive_id ), 'inactive_has_arrival' );

global $wpdb;
$now         = current_time( 'mysql', true );
$legacy_name = 'UP12-S5 legacy invalid ' . $suffix;
$legacy_ins  = $wpdb->insert(
	SOM_DB::table( 'workflow_templates' ),
	array(
		'name'       => $legacy_name,
		'kind'       => 'post_ship',
		'uses_trays' => 0,
		'is_active'  => 1,
		'created_at' => $now,
		'updated_at' => $now,
	),
	array( '%s', '%s', '%d', '%d', '%s', '%s' )
);
$legacy_id = $legacy_ins ? (int) $wpdb->insert_id : 0;
$assert( $legacy_id > 0, 'legacy_template_inserted' );
$legacy_step = $wpdb->insert(
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
);
$assert( (bool) $legacy_step, 'legacy_step_inserted' );
$assert( ! SOM_Workflows::template_has_arrival_step( $legacy_id ), 'legacy_flagged_missing_arrival' );
$assert( ! SOM_Workflows::template_has_arrival_step( 0 ), 'missing_id_has_no_arrival' );

$pack_name = 'UP12-S5 pack no ship ' . $suffix;
$pack      = SOM_Workflows::save_submission(
	0,
	array(
		'name'      => $pack_name,
		'kind'      => 'pack',
		'is_active' => 1,
	),
	array(
		array(
			'name'                    => 'Confirm pack',
			'requires_manual_confirm' => 1,
		),
		array(
			'name'                    => 'Weigh parcel',
			'requires_manual_confirm' => 1,
		),
	)
);
$assert( ! is_wp_error( $pack ) && (int) $pack > 0, 'pack_without_ship_saves' );
$pack_row = ! is_wp_error( $pack ) ? SOM_Workflows::get( (int) $pack ) : null;
$assert( $pack_row && 'pack' === SOM_Workflows::sanitize_kind( $pack_row->kind ), 'pack_kind_unchanged' );
$pack_names = array();
if ( $pack_row && ! empty( $pack_row->steps ) ) {
	foreach ( $pack_row->steps as $step ) {
		$pack_names[] = (string) $step->name;
	}
}
$assert( array( 'Confirm pack', 'Weigh parcel' ) === $pack_names, 'pack_steps_have_no_ship_name' );
$ship_named = false;
foreach ( $pack_names as $pack_step_name ) {
	if ( 0 === stripos( $pack_step_name, 'Ship' ) ) {
		$ship_named = true;
	}
}
$assert( ! $ship_named, 'pack_has_no_ship_prefix' );

if ( $fail > 0 ) {
	echo "FAIL — UP12-S5 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP12-S5 smoke\n";
