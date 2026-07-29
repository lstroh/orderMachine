/**
 * Order Machine admin scripts (recipe row editor).
 */
( function () {
	'use strict';

	var recipeIndex = 0;
	var tbody = document.getElementById( 'som-recipe-rows' );
	var template = document.getElementById( 'som-recipe-row-template' );
	var addButton = document.getElementById( 'som-recipe-add-row' );

	if ( ! tbody || ! template ) {
		return;
	}

	function nextIndex() {
		recipeIndex += 1;
		return 'r' + recipeIndex + '_' + Date.now();
	}

	function bindRemoveButtons( scope ) {
		var buttons = ( scope || document ).querySelectorAll( '.som-recipe-remove' );
		buttons.forEach( function ( button ) {
			if ( button.dataset.bound ) {
				return;
			}
			button.dataset.bound = '1';
			button.addEventListener( 'click', function () {
				var row = button.closest( 'tr' );
				if ( row && tbody.querySelectorAll( '.som-recipe-row' ).length > 1 ) {
					row.remove();
				} else if ( row ) {
					row.querySelectorAll( 'select, input' ).forEach( function ( field ) {
						field.value = '';
					} );
				}
			} );
		} );
	}

	if ( addButton ) {
		addButton.addEventListener( 'click', function () {
			var index = nextIndex();
			var html = template.innerHTML.replace( /__INDEX__/g, index );
			var wrapper = document.createElement( 'tbody' );
			wrapper.innerHTML = html.trim();
			var row = wrapper.firstElementChild;
			if ( row ) {
				tbody.appendChild( row );
				bindRemoveButtons( row );
			}
		} );
	}

	bindRemoveButtons( tbody );
}() );
