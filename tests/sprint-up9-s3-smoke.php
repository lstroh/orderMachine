<?php
/**
 * UP9-S3 smoke: Markdown step instructions → safe HTML; legacy plain text; no images.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up9-s3-smoke.php
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
$assert( defined( 'SOM_Step_Instructions::MAX_LENGTH' ) || 5000 === SOM_Step_Instructions::MAX_LENGTH, 'max_length_5000' );
$assert( 5000 === SOM_Step_Instructions::MAX_LENGTH, 'max_length_value' );

$parsedown = SOM_PLUGIN_DIR . 'includes/lib/parsedown/Parsedown.php';
$assert( file_exists( $parsedown ), 'vendored_parsedown_file' );
require_once $parsedown;
$assert( class_exists( 'Parsedown', false ), 'class_Parsedown' );
$assert( '1.7.4' === Parsedown::version, 'parsedown_version_1_7_4' );

// Bold + list → expected tags.
$md = "**Bold title**\n\n- first\n- second";
$html = SOM_Step_Instructions::render_html( $md );
$out( 'bold_list_html', $html );
$assert( false !== strpos( $html, '<strong>' ) && false !== strpos( $html, 'Bold title' ), 'renders_strong' );
$assert( false !== strpos( $html, '<ul>' ) && false !== strpos( $html, '<li>' ), 'renders_ul_li' );
$assert( false === strpos( $html, '<script' ), 'no_script_in_bold_list' );

// Numbered list + italic + link + code + heading.
$md2 = "## Prep\n\n1. *cut*\n2. Use `laminator`\n\nSee [docs](https://example.com/path).";
$html2 = SOM_Step_Instructions::render_html( $md2 );
$out( 'rich_html', $html2 );
$assert( false !== strpos( $html2, '<h2>' ), 'renders_h2' );
$assert( false !== strpos( $html2, '<ol>' ), 'renders_ol' );
$assert( false !== strpos( $html2, '<em>' ), 'renders_em' );
$assert( false !== strpos( $html2, '<code>' ) && false !== strpos( $html2, 'laminator' ), 'renders_code' );
$assert( false !== strpos( $html2, '<a href="https://example.com/path"' ), 'renders_link' );

// Script tags stripped / escaped — never executable markup.
$evil = "Hello <script>alert(1)</script> world\n\n**ok**";
$html_evil = SOM_Step_Instructions::render_html( $evil );
$out( 'evil_html', $html_evil );
$assert( false === stripos( $html_evil, '<script' ), 'script_tag_stripped' );
$assert( false !== strpos( $html_evil, '<strong>' ), 'evil_still_has_bold' );

// iframe / on* style junk stripped.
$iframe = '<iframe src="https://evil.example"></iframe> **safe**';
$html_iframe = SOM_Step_Instructions::render_html( $iframe );
$assert( false === stripos( $html_iframe, '<iframe' ), 'iframe_stripped' );
$assert( false !== strpos( $html_iframe, 'safe' ), 'iframe_source_keeps_text' );

// Images not allowed (O8).
$img_md = 'Look ![alt](https://example.com/x.png) please';
$html_img = SOM_Step_Instructions::render_html( $img_md );
$out( 'img_html', $html_img );
$assert( false === stripos( $html_img, '<img' ), 'images_stripped' );

// Legacy plain text still readable (line breaks).
$plain = "Line one\nLine two\n\nParagraph two";
$html_plain = SOM_Step_Instructions::render_html( $plain );
$out( 'plain_html', $html_plain );
$assert( false !== strpos( $html_plain, 'Line one' ) && false !== strpos( $html_plain, 'Line two' ), 'plain_text_readable' );
$assert( false !== strpos( $html_plain, '<br' ) || false !== strpos( $html_plain, '<p>' ), 'plain_has_structure' );

// Empty / null.
$assert( '' === SOM_Step_Instructions::render_html( '' ), 'empty_string' );
$assert( '' === SOM_Step_Instructions::render_html( null ), 'null_input' );

// Order detail uses render helper (not esc_html on body).
$detail = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/order-detail.php' );
$assert( false !== strpos( $detail, 'SOM_Step_Instructions::render_html' ), 'order_detail_uses_render_html' );
// preg_match returns 0 (not false) when there is no match.
$assert( 1 !== preg_match( '/som-step-instructions-body">\s*<\?php echo esc_html\(\s*\$step_instructions/', $detail ), 'order_detail_no_esc_html_body' );
$assert( false === strpos( $detail, 'esc_html( $step_instructions' ), 'order_detail_no_esc_html_call' );

// Editor surfaces: Markdown help + preview hooks.
$editor = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$product = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/product-edit.php' );
$js      = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$css     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/css/admin.css' );
$assert( false !== strpos( $editor, 'Markdown supported' ) || false !== strpos( $editor, 'render_editor_help' ), 'editor_markdown_help' );
$assert( false !== strpos( $editor, 'data-som-md-editor' ), 'editor_md_wrapper' );
$assert( false !== strpos( $product, 'data-som-md-editor' ), 'product_md_wrapper' );
$assert( false !== strpos( $product, 'Markdown' ), 'product_markdown_copy' );
$assert( false !== strpos( $js, 'som_preview_step_instructions' ), 'js_preview_ajax' );
$assert( false !== strpos( $js, 'initMarkdownPreview' ), 'js_init_markdown_preview' );
$assert( false !== strpos( $css, '.som-step-instructions-body ul' ), 'css_list_styles' );
$assert( false !== strpos( $css, '.som-step-instructions-body code' ), 'css_code_styles' );

// Docs.
$ref   = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-REFERENCE.md' );
$guide = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/USER-GUIDE.md' );
$feat  = (string) file_get_contents( SOM_PLUGIN_DIR . 'stikerts/wordpress/FEATURES-AND-TESTING.md' );
$assert( false !== strpos( $ref, 'Markdown' ) && false !== strpos( $ref, 'No images' ), 'ref_markdown' );
$assert( false !== strpos( $guide, 'Step instructions' ) && false !== strpos( $guide, 'Markdown' ), 'guide_markdown' );
$assert( false !== strpos( $feat, 'UP9-S3' ), 'features_up9_s3' );

// AJAX handler registered path exists.
$menu = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );
$assert( false !== strpos( $menu, 'ajax_preview_step_instructions' ), 'ajax_handler_present' );

if ( $fail > 0 ) {
	echo "FAIL — UP9-S3 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP9-S3 smoke\n";
