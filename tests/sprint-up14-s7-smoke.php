<?php
/**
 * UP14-S7 smoke: instruction list markers, and editor preview directly under the textarea (BUG-006).
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up14-s7-smoke.php
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

$out( 'plugin', SOM_VERSION );
$assert( method_exists( 'SOM_Step_Instructions', 'render_html' ), 'method_render_html' );
$assert( method_exists( 'SOM_Step_Instructions', 'render_editor_help' ), 'method_render_editor_help' );

$html = SOM_Step_Instructions::render_html( "- first\n- second" );
$out( 'dash_list_html', $html );
$assert( false !== strpos( $html, '<ul>' ) && false !== strpos( $html, '<li>' ), 'dash_list_contains_ul_li' );
$assert( false !== strpos( $html, 'first' ) && false !== strpos( $html, 'second' ), 'dash_list_keeps_items' );

$numbered = SOM_Step_Instructions::render_html( "1. one\n2. two" );
$assert( false !== strpos( $numbered, '<ol>' ) && false !== strpos( $numbered, '<li>' ), 'numbered_list_contains_ol_li' );

$allowed = SOM_Step_Instructions::allowed_html();
$assert(
	array( 'p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'a', 'code', 'pre', 'h2', 'h3' ) === array_keys( $allowed ),
	'allowlist_unchanged'
);
$assert( ! isset( $allowed['img'] ), 'allowlist_still_excludes_images' );

$css = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/css/admin.css' );
$assert(
	1 === preg_match( '/\.som-step-instructions-body ul\s*\{[^}]*list-style:\s*disc\s*;/', $css ),
	'css_ul_disc'
);
$assert(
	1 === preg_match( '/\.som-step-instructions-body ol\s*\{[^}]*list-style:\s*decimal\s*;/', $css ),
	'css_ol_decimal'
);
$assert(
	1 === preg_match( '/\.som-step-instructions-body ul,\s*\.som-step-instructions-body ol\s*\{[^}]*padding:\s*0 0 0 1\.5em\s*;/', $css ),
	'css_list_inset'
);

ob_start();
SOM_Step_Instructions::render_editor_toolbar();
$toolbar = (string) ob_get_clean();

ob_start();
SOM_Step_Instructions::render_editor_help();
$help = (string) ob_get_clean();

$preview_at = strpos( $help, 'data-som-md-preview' );
$help_at    = strpos( $help, 'som-md-help' );
$cheat_at   = strpos( $help, 'som-md-cheatsheet' );
$trimmed    = ltrim( $help );

$assert( 0 === strpos( $trimmed, '<div class="som-step-instructions som-md-preview"' ), 'preview_is_first_block' );
$assert( false !== $preview_at && false !== $help_at && false !== $cheat_at && $preview_at < $help_at && $help_at < $cheat_at, 'preview_before_help_and_cheatsheet' );
$assert( false !== strpos( $help, 'data-som-md-preview hidden' ), 'preview_starts_closed' );
$assert( false !== strpos( $help, 'som-step-instructions-body' ), 'preview_uses_instruction_body' );
$assert( false === strpos( $help, 'data-som-md-preview-toggle' ), 'preview_stays_a_toolbar_button' );
$assert( false !== strpos( $toolbar, 'data-som-md-action="preview"' ), 'toolbar_preview_button' );
$assert( false !== strpos( $toolbar, 'data-som-md-preview-hide' ), 'toolbar_hide_preview_button' );

$under_textarea = '/<\/textarea>\s*<\?php SOM_Step_Instructions::render_editor_help\(\); \?>/';
$editor         = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$product        = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/product-edit.php' );
$detail         = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );

$assert( 1 === preg_match( $under_textarea, $editor ), 'workflow_preview_under_textarea' );
$assert( 1 === preg_match( $under_textarea, $product ), 'product_preview_under_textarea' );
$assert( 3 === substr_count( $detail, 'class="som-step-instructions-body"' ), 'order_detail_shares_body_class' );
$assert( false !== strpos( $detail, 'SOM_Step_Instructions::render_html' ), 'order_detail_renders_html' );

if ( $fail > 0 ) {
	echo "FAIL — UP14-S7 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP14-S7 smoke\n";
