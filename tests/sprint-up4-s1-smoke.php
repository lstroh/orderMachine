<?php
/**
 * UP4-S1 smoke: step instructions schema + override resolve + orphan cleanup.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up4-s1-smoke.php
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
$out( 'db_version', (string) get_option( 'som_db_version', '' ) );

$assert( version_compare( SOM_VERSION, '0.24.0', '>=' ), 'SOM_VERSION_gte_0.24.0' );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.11.0', '>=' ), 'som_db_version_gte_1.11.0' );

$instr_table = SOM_DB::table( 'product_step_instructions' );
$exists      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $instr_table ) );
$assert( $exists === $instr_table, 'table_product_step_instructions' );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$col = $wpdb->get_row( 'SHOW COLUMNS FROM ' . SOM_DB::table( 'workflow_steps' ) . " LIKE 'instructions'" );
$assert( ! empty( $col ), 'workflow_steps_instructions_column' );

$template_id = (int) $wpdb->get_var(
	'SELECT id FROM ' . SOM_DB::table( 'workflow_templates' ) . ' ORDER BY id ASC LIMIT 1'
);
$assert( $template_id > 0, 'has_workflow_template' );

$steps = $template_id > 0 ? SOM_Workflows::get_steps( $template_id ) : array();
$assert( ! empty( $steps ), 'has_workflow_steps' );

$step_a = ! empty( $steps[0] ) ? (int) $steps[0]->id : 0;
$step_b = ! empty( $steps[1] ) ? (int) $steps[1]->id : $step_a;

$product_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT id FROM ' . SOM_DB::table( 'products' ) . ' WHERE workflow_template_id = %d ORDER BY id ASC LIMIT 1',
		$template_id
	)
);
$assert( $product_id > 0, 'has_product_on_template' );

if ( $step_a > 0 ) {
	$wpdb->update(
		SOM_DB::table( 'workflow_steps' ),
		array(
			'instructions' => 'Default print profile',
			'updated_at'   => current_time( 'mysql', true ),
		),
		array( 'id' => $step_a ),
		array( '%s', '%s' ),
		array( '%d' )
	);
}

$eff_default = SOM_Step_Instructions::effective( $product_id, $step_a );
$assert( 'Default print profile' === $eff_default, 'effective_uses_step_default' );

$sync = SOM_Step_Instructions::sync_for_product(
	$product_id,
	$template_id,
	array(
		$step_a => 'Product-specific print notes',
		$step_b => '',
	)
);
$assert( ! is_wp_error( $sync ), 'sync_override_ok' );

$eff_override = SOM_Step_Instructions::effective( $product_id, $step_a );
$assert( 'Product-specific print notes' === $eff_override, 'effective_uses_override' );

$eff_empty_product = SOM_Step_Instructions::effective( 0, $step_a );
$assert( 'Default print profile' === $eff_empty_product, 'effective_no_product_uses_default' );

$clear = SOM_Step_Instructions::sync_for_product(
	$product_id,
	$template_id,
	array( $step_a => '' )
);
$assert( ! is_wp_error( $clear ), 'clear_override_ok' );
$assert( 'Default print profile' === SOM_Step_Instructions::effective( $product_id, $step_a ), 'cleared_falls_back_to_default' );

// Orphan cleanup when template cleared.
SOM_Step_Instructions::sync_for_product(
	$product_id,
	$template_id,
	array( $step_a => 'Temp override' )
);
SOM_Step_Instructions::delete_orphans_for_product( $product_id, 0 );
$count = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . SOM_DB::table( 'product_step_instructions' ) . ' WHERE product_id = %d',
		$product_id
	)
);
$assert( 0 === $count, 'orphans_cleared_when_no_template' );

$too_long = str_repeat( 'x', SOM_Step_Instructions::MAX_LENGTH + 1 );
$bad      = SOM_Step_Instructions::sanitize( $too_long );
$assert( is_wp_error( $bad ), 'rejects_over_max_length' );

if ( $fail > 0 ) {
	$out( 'RESULT', 'FAILED (' . $fail . ')' );
	exit( 1 );
}

$out( 'RESULT', 'OK' );
