/**
 * Pure Markdown insert helpers for the step-instructions toolbar (UP12-S3).
 *
 * No DOM and no WordPress. UP12-S4 should reuse insertAtCursor() for the
 * copy-command placeholder picker (pass that field's max length, 4000).
 */
( function ( root, factory ) {
	'use strict';

	var api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}
	if ( root ) {
		root.SOMMdInsert = api;
	}
} )( typeof globalThis !== 'undefined' ? globalThis : this, function () {
	'use strict';

	var MAX_LENGTH = 5000;
	var BULLET_RE = /^-\s+/;
	var NUMBER_RE = /^\d+\.\s+/;
	var LIST_RE = /^(?:-\s+|\d+\.\s+)/;
	var HEADING_RE = /^#{1,6}\s*/;
	var H2_RE = /^##(?!#)\s+/;
	var H3_RE = /^###(?!#)\s+/;

	var WRAP = {
		bold: { open: '**', close: '**', placeholder: 'bold text' },
		italic: { open: '*', close: '*', placeholder: 'italic text' },
		code: { open: '`', close: '`', placeholder: 'code' }
	};

	function resolveMax( maxLength ) {
		var max = Number( maxLength );
		if ( ! isFinite( max ) || max <= 0 ) {
			return MAX_LENGTH;
		}
		return Math.floor( max );
	}

	function normalizeIndex( value, index ) {
		var n = Number( index );
		if ( ! isFinite( n ) || n < 0 ) {
			return 0;
		}
		n = Math.floor( n );
		if ( n > value.length ) {
			return value.length;
		}
		return n;
	}

	function unchanged( value, start, end ) {
		return {
			value: value,
			selectionStart: start,
			selectionEnd: end,
			applied: false,
			editStart: start,
			editEnd: end,
			inserted: ''
		};
	}

	/**
	 * Replace [start, end) with insertText.
	 *
	 * selectFrom / selectTo are offsets within insertText (default: the whole insert).
	 * If the result would be longer than maxLength, nothing is inserted.
	 *
	 * @param {string} value
	 * @param {number} start
	 * @param {number} end
	 * @param {string} insertText
	 * @param {number=} selectFrom
	 * @param {number=} selectTo
	 * @param {number=} maxLength
	 * @return {{value:string, selectionStart:number, selectionEnd:number, applied:boolean, editStart:number, editEnd:number, inserted:string}}
	 */
	function insertAtCursor( value, start, end, insertText, selectFrom, selectTo, maxLength ) {
		value = String( value == null ? '' : value );
		start = normalizeIndex( value, start );
		end = normalizeIndex( value, end );
		if ( start > end ) {
			var swap = start;
			start = end;
			end = swap;
		}
		insertText = String( insertText == null ? '' : insertText );
		if ( selectFrom == null ) {
			selectFrom = 0;
		}
		if ( selectTo == null ) {
			selectTo = insertText.length;
		}
		var next = value.slice( 0, start ) + insertText + value.slice( end );
		if ( next.length > resolveMax( maxLength ) ) {
			return unchanged( value, start, end );
		}
		return {
			value: next,
			selectionStart: start + selectFrom,
			selectionEnd: start + selectTo,
			applied: true,
			editStart: start,
			editEnd: end,
			inserted: insertText
		};
	}

	/**
	 * Bold, italic, inline code, and link: wrap a selection or insert a placeholder.
	 *
	 * @param {string} value
	 * @param {number} start
	 * @param {number} end
	 * @param {string} action bold|italic|code|link
	 * @param {number=} maxLength
	 * @return {{value:string, selectionStart:number, selectionEnd:number, applied:boolean, editStart:number, editEnd:number, inserted:string}}
	 */
	function wrapSelection( value, start, end, action, maxLength ) {
		value = String( value == null ? '' : value );
		start = normalizeIndex( value, start );
		end = normalizeIndex( value, end );
		if ( start > end ) {
			var swap = start;
			start = end;
			end = swap;
		}
		var selected = value.slice( start, end );
		var hasSelection = selected.length > 0;

		if ( action === 'link' ) {
			if ( hasSelection ) {
				var linked = '[' + selected + '](https://)';
				var urlFrom = selected.length + 3;
				return insertAtCursor( value, start, end, linked, urlFrom, urlFrom + 'https://'.length, maxLength );
			}
			var linkPlaceholder = 'link text';
			var linkInsert = '[' + linkPlaceholder + '](https://)';
			return insertAtCursor( value, start, end, linkInsert, 1, 1 + linkPlaceholder.length, maxLength );
		}

		var spec = WRAP[ action ];
		if ( ! spec ) {
			return unchanged( value, start, end );
		}
		var inner = hasSelection ? selected : spec.placeholder;
		var insertText = spec.open + inner + spec.close;
		var from = spec.open.length;
		return insertAtCursor( value, start, end, insertText, from, from + inner.length, maxLength );
	}

	function isBlankLine( line ) {
		return String( line ).trim() === '';
	}

	function lineBounds( value, start, end ) {
		var lineStart = value.lastIndexOf( '\n', start - 1 );
		lineStart = lineStart === -1 ? 0 : lineStart + 1;

		var effectiveEnd = end;
		if ( end > start && effectiveEnd > 0 && value.charAt( effectiveEnd - 1 ) === '\n' ) {
			effectiveEnd -= 1;
		}
		if ( effectiveEnd < lineStart ) {
			effectiveEnd = lineStart;
		}
		var lineEnd = value.indexOf( '\n', effectiveEnd );
		if ( lineEnd === -1 ) {
			lineEnd = value.length;
		}
		return { lineStart: lineStart, lineEnd: lineEnd };
	}

	function lineHasPrefix( line, action ) {
		if ( action === 'bullet' ) {
			return BULLET_RE.test( line );
		}
		if ( action === 'numbered' ) {
			return NUMBER_RE.test( line );
		}
		if ( action === 'h2' ) {
			return H2_RE.test( line );
		}
		if ( action === 'h3' ) {
			return H3_RE.test( line );
		}
		return false;
	}

	function stripPrefix( line, action ) {
		if ( action === 'bullet' || action === 'numbered' ) {
			return line.replace( LIST_RE, '' );
		}
		if ( action === 'h2' || action === 'h3' ) {
			return line.replace( HEADING_RE, '' );
		}
		return line;
	}

	function prefixFor( action, number ) {
		if ( action === 'bullet' ) {
			return '- ';
		}
		if ( action === 'numbered' ) {
			return String( number ) + '. ';
		}
		if ( action === 'h2' ) {
			return '## ';
		}
		if ( action === 'h3' ) {
			return '### ';
		}
		return '';
	}

	/**
	 * Bullet, numbered list, and H2 / H3: prefix each selected line.
	 *
	 * Blank lines inside a multi-line selection are skipped. The same prefix
	 * again removes it. A list prefix replaces the other list prefix.
	 * A heading prefix replaces any existing # prefix.
	 *
	 * @param {string} value
	 * @param {number} start
	 * @param {number} end
	 * @param {string} action bullet|numbered|h2|h3
	 * @param {number=} maxLength
	 * @return {{value:string, selectionStart:number, selectionEnd:number, applied:boolean, editStart:number, editEnd:number, inserted:string}}
	 */
	function prefixLines( value, start, end, action, maxLength ) {
		value = String( value == null ? '' : value );
		start = normalizeIndex( value, start );
		end = normalizeIndex( value, end );
		if ( start > end ) {
			var swap = start;
			start = end;
			end = swap;
		}
		if ( ! prefixFor( action, 1 ) ) {
			return unchanged( value, start, end );
		}

		var bounds = lineBounds( value, start, end );
		var block = value.slice( bounds.lineStart, bounds.lineEnd );
		var lines = block.split( '\n' );
		var multi = lines.length > 1;
		var nonBlank = [];
		var i;
		for ( i = 0; i < lines.length; i++ ) {
			if ( ! isBlankLine( lines[ i ] ) ) {
				nonBlank.push( i );
			}
		}

		var toggleOff = nonBlank.length > 0;
		for ( i = 0; i < nonBlank.length; i++ ) {
			if ( ! lineHasPrefix( lines[ nonBlank[ i ] ], action ) ) {
				toggleOff = false;
				break;
			}
		}

		var number = 1;
		var nextLines = lines.map( function ( line ) {
			if ( isBlankLine( line ) ) {
				if ( ! multi ) {
					return prefixFor( action, 1 );
				}
				return line;
			}
			if ( toggleOff ) {
				return stripPrefix( line, action );
			}
			var body = stripPrefix( line, action );
			var prefixed = prefixFor( action, number ) + body;
			if ( action === 'numbered' ) {
				number += 1;
			}
			return prefixed;
		} );

		var insertText = nextLines.join( '\n' );
		if ( insertText === block ) {
			return unchanged( value, bounds.lineStart, bounds.lineEnd );
		}

		var caretAtEnd = ! multi && isBlankLine( block );
		var selectFrom = caretAtEnd ? insertText.length : 0;
		var selectTo = insertText.length;
		return insertAtCursor( value, bounds.lineStart, bounds.lineEnd, insertText, selectFrom, selectTo, maxLength );
	}

	/**
	 * @param {string} value
	 * @param {number} start
	 * @param {number} end
	 * @param {string} action
	 * @param {number=} maxLength
	 * @return {{value:string, selectionStart:number, selectionEnd:number, applied:boolean, editStart:number, editEnd:number, inserted:string}}
	 */
	function applyMarkdownAction( value, start, end, action, maxLength ) {
		if ( action === 'bold' || action === 'italic' || action === 'code' || action === 'link' ) {
			return wrapSelection( value, start, end, action, maxLength );
		}
		if ( action === 'bullet' || action === 'numbered' || action === 'h2' || action === 'h3' ) {
			return prefixLines( value, start, end, action, maxLength );
		}
		value = String( value == null ? '' : value );
		start = normalizeIndex( value, start );
		end = normalizeIndex( value, end );
		if ( start > end ) {
			var swap = start;
			start = end;
			end = swap;
		}
		return unchanged( value, start, end );
	}

	return {
		MAX_LENGTH: MAX_LENGTH,
		insertAtCursor: insertAtCursor,
		wrapSelection: wrapSelection,
		prefixLines: prefixLines,
		applyMarkdownAction: applyMarkdownAction
	};
} );
