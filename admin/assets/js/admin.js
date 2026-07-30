/**
 * Order Machine admin scripts (recipe rows + workflow step editor).
 */
( function () {
	'use strict';

	function initRecipeEditor() {
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
	}

	function initWorkflowEditor() {
		var list = document.getElementById( 'som-step-list' );
		var template = document.getElementById( 'som-step-card-template' );
		var addButton = document.getElementById( 'som-step-add' );
		var stepIndex = 0;

		if ( ! list || ! template ) {
			return;
		}

		function nextIndex() {
			stepIndex += 1;
			return 's' + stepIndex + '_' + Date.now();
		}

		function cards() {
			return Array.prototype.slice.call( list.querySelectorAll( '[data-som-step]' ) );
		}

		function reindexNames( card, index ) {
			card.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /som_step\[[^\]]+\]/, 'som_step[' + index + ']' );
			} );
		}

		function refreshOrder() {
			cards().forEach( function ( card, i ) {
				reindexNames( card, i + 1 );
			} );
		}

		function syncScriptPanels( card ) {
			var typeSelect = card.querySelector( '.som-script-type' );
			var rawToggle = card.querySelector( '.som-script-raw-mode' );
			var rawPanel = card.querySelector( '[data-script-raw]' );
			if ( ! typeSelect ) {
				return;
			}
			var type = typeSelect.value;
			card.querySelectorAll( '[data-script-panel]' ).forEach( function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-script-panel' ) !== type;
			} );
			if ( rawPanel && rawToggle ) {
				rawPanel.hidden = ! rawToggle.checked;
			}
		}

		function bindCard( card ) {
			if ( card.dataset.bound ) {
				return;
			}
			card.dataset.bound = '1';

			var up = card.querySelector( '.som-step-up' );
			var down = card.querySelector( '.som-step-down' );
			var remove = card.querySelector( '.som-step-remove' );
			var typeSelect = card.querySelector( '.som-script-type' );
			var rawToggle = card.querySelector( '.som-script-raw-mode' );

			if ( up ) {
				up.addEventListener( 'click', function () {
					var prev = card.previousElementSibling;
					if ( prev ) {
						list.insertBefore( card, prev );
						refreshOrder();
					}
				} );
			}
			if ( down ) {
				down.addEventListener( 'click', function () {
					var next = card.nextElementSibling;
					if ( next ) {
						list.insertBefore( next, card );
						refreshOrder();
					}
				} );
			}
			if ( remove ) {
				remove.addEventListener( 'click', function () {
					if ( cards().length <= 1 ) {
						card.querySelectorAll( 'input[type="text"], input[type="number"], input[type="url"], textarea' ).forEach( function ( field ) {
							field.value = '';
						} );
						card.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( field ) {
							field.checked = false;
						} );
						var type = card.querySelector( '.som-script-type' );
						if ( type ) {
							type.value = 'none';
						}
						var idField = card.querySelector( 'input[name*="[id]"]' );
						if ( idField ) {
							idField.value = '0';
						}
						syncScriptPanels( card );
						return;
					}
					card.remove();
					refreshOrder();
				} );
			}
			if ( typeSelect ) {
				typeSelect.addEventListener( 'change', function () {
					syncScriptPanels( card );
				} );
			}
			if ( rawToggle ) {
				rawToggle.addEventListener( 'change', function () {
					syncScriptPanels( card );
				} );
			}

			syncScriptPanels( card );
		}

		cards().forEach( bindCard );

		if ( addButton ) {
			addButton.addEventListener( 'click', function () {
				var index = nextIndex();
				var html = template.innerHTML.replace( /__INDEX__/g, index );
				var wrapper = document.createElement( 'div' );
				wrapper.innerHTML = html.trim();
				var card = wrapper.firstElementChild;
				if ( card ) {
					list.appendChild( card );
					bindCard( card );
					refreshOrder();
				}
			} );
		}
	}

	initRecipeEditor();
	initWorkflowEditor();
	initCountdowns();

	function initCountdowns() {
		var nodes = document.querySelectorAll( '[data-som-countdown]' );
		if ( ! nodes.length ) {
			return;
		}

		function tick() {
			var now = Math.floor( Date.now() / 1000 );
			nodes.forEach( function ( el ) {
				var ends = parseInt( el.getAttribute( 'data-ends-at' ), 10 );
				if ( ! ends ) {
					return;
				}
				var remaining = ends - now;
				if ( remaining <= 0 ) {
					el.textContent = el.getAttribute( 'data-unlocked-label' ) || 'Timer elapsed — refresh or wait for the next engine tick to unlock Mark done.';
					return;
				}
				var h = Math.floor( remaining / 3600 );
				var m = Math.floor( ( remaining % 3600 ) / 60 );
				var s = remaining % 60;
				var parts = [];
				if ( h > 0 ) {
					parts.push( h + 'h' );
				}
				parts.push( m + 'm' );
				parts.push( s + 's' );
				el.textContent = 'Unlocks in ' + parts.join( ' ' );
			} );
		}

		tick();
		setInterval( tick, 1000 );
	}
}() );
