<?php
/**
 * UP12-S3 smoke: instructions Markdown toolbar on both editors; render allowlist unchanged.
 *
 * Run: npx @wordpress/env run cli wp eval-file wp-content/plugins/orderMachine/tests/sprint-up12-s3-smoke.php
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
$assert( method_exists( 'SOM_Step_Instructions', 'render_editor_toolbar' ), 'method_render_editor_toolbar' );
$assert( 5000 === SOM_Step_Instructions::MAX_LENGTH, 'max_length_5000' );

$actions = array( 'bold', 'italic', 'bullet', 'numbered', 'h2', 'h3', 'link', 'code', 'preview' );

ob_start();
SOM_Step_Instructions::render_editor_toolbar();
$toolbar = (string) ob_get_clean();

ob_start();
SOM_Step_Instructions::render_editor_help();
$help = (string) ob_get_clean();

$assert( false !== strpos( $toolbar, 'role="toolbar"' ), 'toolbar_role' );
$assert( false !== strpos( $toolbar, 'Formatting' ), 'toolbar_label' );
foreach ( $actions as $action ) {
	$assert( false !== strpos( $toolbar, 'data-som-md-action="' . $action . '"' ), 'toolbar_action_' . $action );
}
$assert( false !== strpos( $toolbar, 'data-som-md-preview-toggle' ), 'toolbar_preview_toggle' );
$assert( false !== strpos( $toolbar, 'data-som-md-preview-hide' ), 'toolbar_preview_hide' );
$assert( 1 === substr_count( $toolbar, 'data-som-md-preview-toggle' ), 'toolbar_one_preview_toggle' );

$assert( false !== strpos( $help, 'Markdown supported. No images; no HTML editor. Max 5000 characters.' ), 'help_line' );
$assert( false !== strpos( $help, 'som-md-cheatsheet' ), 'help_cheatsheet' );
$assert( false !== strpos( $help, 'data-som-md-preview' ), 'help_preview_panel' );
$assert( false === strpos( $help, 'data-som-md-preview-toggle' ), 'help_preview_not_duplicated' );

$editor  = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/workflow-step-editor.php' );
$product = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/views/product-edit.php' );
$js      = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/admin.js' );
$insert  = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/js/som-md-insert.js' );
$css     = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/assets/css/admin.css' );
$menu    = (string) file_get_contents( SOM_PLUGIN_DIR . 'admin/class-som-admin-menu.php' );

$assert( 1 === substr_count( $editor, 'render_editor_toolbar' ), 'workflow_calls_toolbar' );
$assert( false !== strpos( $editor, 'data-som-md-editor' ), 'workflow_md_editor' );
$assert( false !== strpos( $editor, 'som-md-source' ), 'workflow_md_source' );
$assert( 1 === substr_count( $product, 'render_editor_toolbar' ), 'product_calls_toolbar' );
$assert( false !== strpos( $product, 'data-som-md-editor' ), 'product_md_editor' );
$assert( false !== strpos( $product, 'som-md-source' ), 'product_md_source' );
$assert( false !== strpos( $js, 'initMarkdownToolbar' ), 'js_init_toolbar' );
$assert( false !== strpos( $js, 'commitTextareaEdit' ), 'js_commit_helper' );
$assert( false !== strpos( $js, 'initMarkdownPreview' ), 'js_preview_still_present' );
$assert( false !== strpos( $insert, 'insertAtCursor' ), 'insert_helper_exported' );
$assert( false !== strpos( $insert, 'wrapSelection' ), 'wrap_helper' );
$assert( false !== strpos( $insert, 'prefixLines' ), 'prefix_helper' );
$assert( false !== strpos( $css, '.som-md-toolbar' ), 'css_toolbar' );
$assert( false !== strpos( $menu, 'som-md-insert.js' ), 'script_enqueued' );

// Toolbar-shaped Markdown still renders through the unchanged allowlist.
$md   = "## Prep\n\n### Detail\n\n**bold** and *italic* and `code`\n\n- bullet\n\n1. one\n2. two\n\n[docs](https://example.com/path)";
$html = SOM_Step_Instructions::render_html( $md );
$out( 'toolbar_md_html', $html );
$assert( false !== strpos( $html, '<strong>' ), 'renders_strong' );
$assert( false !== strpos( $html, '<em>' ), 'renders_em' );
$assert( false !== strpos( $html, '<ul>' ), 'renders_ul' );
$assert( false !== strpos( $html, '<ol>' ), 'renders_ol' );
$assert( false !== strpos( $html, '<h2>' ), 'renders_h2' );
$assert( false !== strpos( $html, '<h3>' ), 'renders_h3' );
$assert( false !== strpos( $html, '<a href="https://example.com/path"' ), 'renders_link' );
$assert( false !== strpos( $html, '<code>' ), 'renders_code' );
$assert( false === stripos( $html, '<img' ), 'images_still_stripped' );
$assert( false === stripos( $html, '<script' ), 'script_still_stripped' );

$allowed = SOM_Step_Instructions::allowed_html();
$assert(
	array( 'p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'a', 'code', 'pre', 'h2', 'h3' ) === array_keys( $allowed ),
	'allowlist_tags_unchanged'
);
$assert( array( 'href', 'title', 'rel', 'target' ) === array_keys( $allowed['a'] ), 'allowlist_anchor_attrs_unchanged' );

if ( $fail > 0 ) {
	echo "FAIL — UP12-S3 smoke ({$fail} failed)\n";
	exit( 1 );
}

echo "PASS — UP12-S3 smoke\n";
