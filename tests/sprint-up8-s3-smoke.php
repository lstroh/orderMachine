<?php
/**
 * UP8-S3 smoke: seed templates leave uses_trays off (operator opts in).
 *
 * Full trays/Unblock behaviour remains covered by sprint-up8-s1-smoke.php and
 * sprint-up8-s2-smoke.php — this file does not re-test that matrix.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up8-s3-smoke.php
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

SOM_DB::maybe_upgrade();
SOM_Channels::ensure_rows();
SOM_Seed::maybe_seed_catalogue();

$out( 'plugin', SOM_VERSION );
$out( 'db', (string) get_option( 'som_db_version', '' ) );
$assert( version_compare( (string) get_option( 'som_db_version', '' ), '1.20.0', '>=' ), 'db_gte_1.20.0' );

$make_id = (int) get_option( 'som_seed_workflow_id', 0 );
$pack_id = (int) get_option( 'som_seed_pack_workflow_id', 0 );
$assert( $make_id > 0, 'seed_make_workflow_id' );
$assert( $pack_id > 0, 'seed_pack_workflow_id' );

$make = $make_id > 0 ? SOM_Workflows::get( $make_id ) : null;
$pack = $pack_id > 0 ? SOM_Workflows::get( $pack_id ) : null;
$assert( $make && empty( $make->uses_trays ), 'seed_make_uses_trays_off' );
$assert( $pack && empty( $pack->uses_trays ), 'seed_pack_uses_trays_off' );

$guide = file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md' );
$ref   = file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-REFERENCE.md' );
$feat  = file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/FEATURES-AND-TESTING.md' );
$assert( is_string( $guide ) && false !== strpos( $guide, 'Trays & Unblock step' ), 'user_guide_trays_section' );
$assert( is_string( $ref ) && false !== strpos( $ref, 'Unblock step — concise rules' ), 'user_reference_unblock_rules' );
$assert( is_string( $feat ) && false !== strpos( $feat, '3.14a Trays & Unblock step' ), 'features_trays_section' );

$out( 'fail_count', (string) $fail );
if ( $fail > 0 ) {
	exit( 1 );
}
echo "UP8-S3 smoke OK\n";
