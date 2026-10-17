/**
 * Unit tests for the Markdown toolbar helpers. No WordPress.
 *
 * Run: node tests/som-md-insert.test.js
 */

'use strict';

var assert = require( 'assert' );
var md = require( '../admin/assets/js/som-md-insert.js' );

var failed = 0;

function check( name, fn ) {
	try {
		fn();
		console.log( name + ': PASS' );
	} catch ( err ) {
		failed += 1;
		console.log( name + ': FAIL' );
		console.log( err && err.stack ? err.stack : err );
	}
}

function selected( result ) {
	return result.value.slice( result.selectionStart, result.selectionEnd );
}

check( 'bold_wraps_selection', function () {
	var result = md.wrapSelection( 'say hello now', 4, 9, 'bold', 5000 );
	assert.strictEqual( result.applied, true );
	assert.strictEqual( result.value, 'say **hello** now' );
	assert.strictEqual( selected( result ), 'hello' );
} );

check( 'bold_inserts_placeholder', function () {
	var result = md.wrapSelection( 'say ', 4, 4, 'bold', 5000 );
	assert.strictEqual( result.value, 'say **bold text**' );
	assert.strictEqual( selected( result ), 'bold text' );
} );

check( 'italic_and_code_placeholders', function () {
	var italic = md.wrapSelection( '', 0, 0, 'italic', 5000 );
	assert.strictEqual( italic.value, '*italic text*' );
	assert.strictEqual( selected( italic ), 'italic text' );
	var code = md.wrapSelection( '', 0, 0, 'code', 5000 );
	assert.strictEqual( code.value, '`code`' );
	assert.strictEqual( selected( code ), 'code' );
} );

check( 'bold_does_not_unwrap', function () {
	var result = md.wrapSelection( '**hello**', 0, 9, 'bold', 5000 );
	assert.strictEqual( result.value, '****hello****' );
	assert.notStrictEqual( result.value, 'hello' );
} );

check( 'link_with_selection_selects_url', function () {
	var result = md.wrapSelection( 'see docs please', 4, 8, 'link', 5000 );
	assert.strictEqual( result.value, 'see [docs](https://) please' );
	assert.strictEqual( selected( result ), 'https://' );
} );

check( 'link_without_selection_selects_text', function () {
	var result = md.wrapSelection( '', 0, 0, 'link', 5000 );
	assert.strictEqual( result.value, '[link text](https://)' );
	assert.strictEqual( selected( result ), 'link text' );
} );

check( 'numbered_three_lines', function () {
	var value = 'one\ntwo\nthree';
	var result = md.prefixLines( value, 0, value.length, 'numbered', 5000 );
	assert.strictEqual( result.value, '1. one\n2. two\n3. three' );
	assert.strictEqual( selected( result ), result.value );
} );

check( 'numbered_skips_blank_lines', function () {
	var value = 'alpha\n\nbeta\n\ngamma';
	var result = md.prefixLines( value, 0, value.length, 'numbered', 5000 );
	assert.strictEqual( result.value, '1. alpha\n\n2. beta\n\n3. gamma' );
} );

check( 'numbered_selection_ending_on_newline_excludes_next_line', function () {
	var value = 'one\ntwo\nthree';
	var result = md.prefixLines( value, 0, 'one\ntwo\n'.length, 'numbered', 5000 );
	assert.strictEqual( result.value, '1. one\n2. two\nthree' );
} );

check( 'bullet_each_line_and_toggle_off', function () {
	var value = 'alpha\nbeta\ngamma';
	var on = md.prefixLines( value, 0, value.length, 'bullet', 5000 );
	assert.strictEqual( on.value, '- alpha\n- beta\n- gamma' );
	var off = md.prefixLines( on.value, 0, on.value.length, 'bullet', 5000 );
	assert.strictEqual( off.value, value );
} );

check( 'numbered_toggle_off', function () {
	var value = '1. one\n\n2. two\n3. three';
	var result = md.prefixLines( value, 0, value.length, 'numbered', 5000 );
	assert.strictEqual( result.value, 'one\n\ntwo\nthree' );
} );

check( 'numbered_replaces_bullets', function () {
	var result = md.prefixLines( '- alpha\n- beta', 0, 14, 'numbered', 5000 );
	assert.strictEqual( result.value, '1. alpha\n2. beta' );
} );

check( 'bullet_replaces_numbers', function () {
	var value = '1. alpha\n2. beta';
	var result = md.prefixLines( value, 0, value.length, 'bullet', 5000 );
	assert.strictEqual( result.value, '- alpha\n- beta' );
} );

check( 'current_line_when_caret_only', function () {
	var value = 'keep\nthis line\nstays';
	var caret = value.indexOf( 'line' );
	var result = md.prefixLines( value, caret, caret, 'bullet', 5000 );
	assert.strictEqual( result.value, 'keep\n- this line\nstays' );
} );

check( 'empty_line_gets_prefix_and_caret_at_end', function () {
	var result = md.prefixLines( 'keep\n', 5, 5, 'numbered', 5000 );
	assert.strictEqual( result.value, 'keep\n1. ' );
	assert.strictEqual( result.selectionStart, result.value.length );
	assert.strictEqual( result.selectionEnd, result.value.length );
} );

check( 'heading_replaces_hash_prefix', function () {
	assert.strictEqual( md.prefixLines( '# Title', 0, 7, 'h2', 5000 ).value, '## Title' );
	assert.strictEqual( md.prefixLines( '### Title', 0, 9, 'h2', 5000 ).value, '## Title' );
	assert.strictEqual( md.prefixLines( '##Title', 0, 7, 'h2', 5000 ).value, '## Title' );
	assert.strictEqual( md.prefixLines( '## Title', 0, 8, 'h3', 5000 ).value, '### Title' );
	assert.strictEqual( md.prefixLines( '## Title', 0, 8, 'h2', 5000 ).value, 'Title' );
	var stacked = md.prefixLines( '## Title', 0, 8, 'h3', 5000 );
	assert.strictEqual( stacked.value.indexOf( '## ##' ), -1 );
	assert.strictEqual( stacked.value, '### Title' );
} );

check( 'over_length_inserts_nothing', function () {
	var value = new Array( 4991 ).join( 'a' );
	assert.strictEqual( value.length, 4990 );
	var result = md.wrapSelection( value, value.length, value.length, 'bold', 5000 );
	assert.strictEqual( result.applied, false );
	assert.strictEqual( result.value, value );
	var exact = new Array( 4988 ).join( 'a' );
	assert.strictEqual( exact.length, 4987 );
	var allowed = md.wrapSelection( exact, exact.length, exact.length, 'bold', 5000 );
	assert.strictEqual( allowed.applied, true );
	assert.strictEqual( allowed.value.length, 5000 );
} );

check( 'numbered_over_length_skips_whole_insert', function () {
	var line = new Array( 4998 ).join( 'a' );
	var value = line + '\n' + line;
	var result = md.prefixLines( value, 0, value.length, 'numbered', 5000 );
	assert.strictEqual( result.applied, false );
	assert.strictEqual( result.value, value );
} );

check( 'insert_at_cursor_replaces_selection', function () {
	var result = md.insertAtCursor( 'abcdef', 2, 4, '{{sku}}', 0, 7, 4000 );
	assert.strictEqual( result.applied, true );
	assert.strictEqual( result.value, 'ab{{sku}}ef' );
	assert.strictEqual( selected( result ), '{{sku}}' );
	assert.strictEqual( result.editStart, 2 );
	assert.strictEqual( result.editEnd, 4 );
	var blocked = md.insertAtCursor( 'abcd', 4, 4, 'xyz', 0, 3, 6 );
	assert.strictEqual( blocked.applied, false );
	assert.strictEqual( blocked.value, 'abcd' );
} );

check( 'apply_action_dispatches', function () {
	var numbered = md.applyMarkdownAction( 'a\nb\nc', 0, 5, 'numbered', 5000 );
	assert.strictEqual( numbered.value, '1. a\n2. b\n3. c' );
	var bold = md.applyMarkdownAction( 'a', 0, 1, 'bold', 5000 );
	assert.strictEqual( bold.value, '**a**' );
	var preview = md.applyMarkdownAction( 'a', 0, 1, 'preview', 5000 );
	assert.strictEqual( preview.applied, false );
	assert.strictEqual( preview.value, 'a' );
} );

if ( failed > 0 ) {
	console.log( 'FAIL — som-md-insert (' + failed + ' failed)' );
	process.exit( 1 );
}

console.log( 'PASS — som-md-insert' );
