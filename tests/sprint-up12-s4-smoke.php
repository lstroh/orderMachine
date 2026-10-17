<?php
/**
 * UP12-S4 smoke: Copy command placeholder labels, editor dropdown markup, render unchanged.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s4-smoke.php
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

$expected_labels = array(
	'order_number'    => 'Order number',
	'house_number'    => 'House number',
	'street_name'     => 'Street name',
	'style'           => 'Style / design',
	'buyer_name'      => 'Buyer first name',
	'buyer_full_name' => 'Buyer full name',
	'product_name'    => 'Product name',
	'personalisation' => 'Personalisation',
);

$out( 'plugin', SOM_VERSION );
$assert( method_exists( 'SOM_Copy_Command', 'placeholder_labels' ), 'method_placeholder_labels' );
$assert( method_exists( 'SOM_Copy_Command', 'render_placeholder_picker' ), 'method_render_placeholder_picker' );
$assert( 4000 === SOM_Copy_Command::MAX_TEMPLATE_LENGTH, 'max_template_4000' );

$keys   = SOM_Copy_Command::placeholder_keys();
$labels = SOM_Copy_Command::placeholder_labels();
$assert( array_keys( $expected_labels ) === array_values( $keys ), 'keys_unchanged' );
$assert( count( $keys ) === count( $labels ), 'label_per_key' );
foreach ( $keys as $key ) {
	$assert( isset( $labels[ $key ] ) && '' !== (string) $labels[ $key ], 'label_present_' . $key );
	$assert( isset( $expected_labels[ $key ] ) && $expected_labels[ $key ] === $labels[ $key ], 'label_text_' . $key );
}

ob_start();
SOM_Copy_Command::render_placeholder_picker();
$picker = (string) ob_get_clean();

$assert( 1 === substr_count( $picker, 'data-som-copy-placeholder' ), 'picker_one_select' );
$assert( false !== strpos( $picker, 'aria-label="Insert placeholder"' ), 'picker_sr_label' );
$assert( false !== strpos( $picker, '>Insert placeholder…</option>' ), 'picker_prompt' );
$assert( 1 === substr_count( $picker, 'value=""' ), 'picker_prompt_empty_value' );
$assert( ( count( $keys ) + 1 ) === substr_count( $picker, '<option' ), 'picker_option_count' );
foreach ( $keys as $key ) {
	$token = '{{' . $key . '}}';
	$assert( 1 === substr_count( $picker, 'value="' . $token . '"' ), 'option_value_' . $key );
	$assert( 1 === substr_count( $picker, 'title="' . $token . '"' ), 'option_title_' . $key );
	$assert( false !== strpos( $picker, '>' . $labels[ $key ] . '</option>' ), 'option_label_' . $key );
}

$editor = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$js     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$insert = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/som-md-insert.js' );
$css    = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/css/admin.css' );

$picker_at = strpos( $editor, 'render_placeholder_picker' );
$field_at  = strpos( $editor, 'som-command-template' );
$assert( 1 === substr_count( $editor, 'render_placeholder_picker' ), 'editor_calls_picker_once' );
$assert( false !== $picker_at && false !== $field_at && $picker_at < $field_at, 'picker_above_textarea' );
$assert( false !== strpos( $editor, 'id="som-step-card-template"' ), 'new_step_template' );
$assert( false !== strpos( $editor, "\$som_render_step( '__INDEX__', null )" ), 'new_step_uses_shared_renderer' );
$assert(
	false !== strpos( $editor, '{{order_number}}, {{house_number}}, {{street_name}}, {{style}}, {{buyer_name}}, {{buyer_full_name}}, {{product_name}}, {{personalisation}}' ),
	'plain_text_placeholder_list'
);
$assert( false !== strpos( $js, 'initCopyCommandPlaceholderPicker' ), 'js_init_picker' );
$assert( false !== strpos( $js, 'data-som-copy-placeholder' ), 'js_delegated_select' );
$assert( false !== strpos( $js, 'SOMMdInsert.insertAtCursor' ), 'js_reuses_insert_helper' );
$assert( false !== strpos( $js, 'commitTextareaEdit' ), 'js_commits_edit' );
$assert( false !== strpos( $js, '4000' ), 'js_max_4000' );
$assert( false !== strpos( $insert, 'insertAtCursor' ), 'insert_helper_still_exported' );
$assert( false !== strpos( $css, '.som-copy-placeholder-picker' ), 'css_picker' );

$bits   = array();
$values = array();
foreach ( $keys as $key ) {
	$bits[]         = $key . ': {{' . $key . '}}';
	$values[ $key ] = 'val-' . $key;
}
$rendered = SOM_Copy_Command::render( implode( "\n", $bits ), $values );
$out( 'rendered_command', $rendered['command'] );
$assert( array() === $rendered['missing'], 'render_none_missing' );
foreach ( $values as $key => $value ) {
	$assert( false !== strpos( $rendered['command'], $key . ': ' . $value ), 'render_' . $key );
	$assert( false === strpos( $rendered['command'], '{{' . $key . '}}' ), 'token_replaced_' . $key );
}

if ( $fail > 0 ) {
	echo "FAIL — UP12-S4 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP12-S4 smoke\n";
