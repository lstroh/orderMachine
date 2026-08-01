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
						var batchSelect = card.querySelector( '.som-batch-group-select' );
						if ( batchSelect ) {
							batchSelect.value = '0';
						}
						var idField = card.querySelector( 'input[name*="[id]"]' );
						if ( idField ) {
							idField.value = '0';
						}
						syncScriptPanels( card );
						syncBatchWarning( card );
						return;
					}
					card.remove();
					refreshOrder();
				} );
			}
			if ( typeSelect ) {
				typeSelect.addEventListener( 'change', function () {
					syncScriptPanels( card );
					syncBatchWarning( card );
				} );
			}
			if ( rawToggle ) {
				rawToggle.addEventListener( 'change', function () {
					syncScriptPanels( card );
				} );
			}

			var batchSelectBind = card.querySelector( '.som-batch-group-select' );
			var manualBind = card.querySelector( 'input[name*="[requires_manual_confirm]"]' );
			var timerBind = card.querySelector( 'input[name*="[timer_value]"]' );
			if ( batchSelectBind ) {
				batchSelectBind.addEventListener( 'change', function () {
					syncBatchWarning( card );
				} );
			}
			if ( manualBind ) {
				manualBind.addEventListener( 'change', function () {
					syncBatchWarning( card );
				} );
			}
			if ( timerBind ) {
				timerBind.addEventListener( 'input', function () {
					syncBatchWarning( card );
				} );
			}

			syncScriptPanels( card );
			syncBatchWarning( card );
		}

		function syncBatchWarning( card ) {
			var warning = card.querySelector( '[data-som-batch-warning]' );
			var batchSelect = card.querySelector( '.som-batch-group-select' );
			if ( ! warning || ! batchSelect ) {
				return;
			}
			var hasBatch = batchSelect.value && batchSelect.value !== '0';
			if ( ! hasBatch ) {
				warning.hidden = true;
				return;
			}
			var manual = card.querySelector( 'input[name*="[requires_manual_confirm]"]' );
			var timer = card.querySelector( 'input[name*="[timer_value]"]' );
			var typeSelect = card.querySelector( '.som-script-type' );
			var hasManual = manual && manual.checked;
			var hasTimer = timer && String( timer.value ).trim() !== '';
			var hasScript = typeSelect && typeSelect.value !== 'none';
			warning.hidden = ! ( hasManual || hasTimer || hasScript );
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

	function initListingEditor() {
		var modeSelect = document.getElementById( 'som_inventory_mode' );
		var panel = document.querySelector( '.som-variations-panel' );
		var flatRow = document.querySelector( '.som-flat-qty-row' );
		var tbody = document.getElementById( 'som-variations-body' );
		var template = document.getElementById( 'som-variation-row-template' );
		var addButton = document.getElementById( 'som-variation-add' );

		function syncMode() {
			if ( ! modeSelect ) {
				return;
			}
			var isVariations = modeSelect.value === 'variations';
			if ( panel ) {
				panel.hidden = ! isVariations;
			}
			if ( flatRow ) {
				flatRow.hidden = isVariations;
			}
		}

		function bindRemove( scope ) {
			( scope || document ).querySelectorAll( '.som-variation-remove' ).forEach( function ( button ) {
				if ( button.dataset.bound ) {
					return;
				}
				button.dataset.bound = '1';
				button.addEventListener( 'click', function () {
					var row = button.closest( 'tr' );
					if ( ! row || ! tbody ) {
						return;
					}
					if ( tbody.querySelectorAll( '.som-variation-row' ).length > 1 ) {
						row.remove();
					} else {
						row.querySelectorAll( 'input' ).forEach( function ( field ) {
							field.value = field.type === 'number' ? '0' : '';
						} );
					}
				} );
			} );
		}

		if ( modeSelect ) {
			modeSelect.addEventListener( 'change', syncMode );
			syncMode();
		}

		if ( addButton && template && tbody ) {
			addButton.addEventListener( 'click', function () {
				var wrapper = document.createElement( 'tbody' );
				wrapper.innerHTML = template.innerHTML.trim();
				var row = wrapper.firstElementChild;
				if ( row ) {
					tbody.appendChild( row );
					bindRemove( row );
				}
			} );
		}

		bindRemove( tbody );
	}

	initRecipeEditor();
	initGoalEditor();
	initPoLineEditor();
	initPoPreviewImpact();
	initWorkflowEditor();
	initListingEditor();
	initBatchesPage();
	initCountdowns();
	initAdvanceStepRest();

	function initBatchesPage() {
		var list = document.querySelector( '[data-som-batch-list]' );
		if ( ! list ) {
			return;
		}

		function setExpanded( card, expanded ) {
			var members = card.querySelector( '[data-som-batch-members]' );
			var toggle = card.querySelector( '[data-som-batch-toggle]' );
			var icon = card.querySelector( '.som-batch-toggle-icon' );
			if ( members ) {
				members.hidden = ! expanded;
			}
			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
			}
			if ( icon ) {
				icon.textContent = expanded ? '▼' : '▶';
			}
			if ( expanded ) {
				card.classList.add( 'is-expanded' );
			} else {
				card.classList.remove( 'is-expanded' );
			}
		}

		list.querySelectorAll( '[data-som-batch-toggle]' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var card = toggle.closest( '[data-som-batch]' );
				if ( ! card ) {
					return;
				}
				var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
				setExpanded( card, ! open );
			} );
		} );

		list.querySelectorAll( '[data-som-address-toggle]' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var cell = toggle.parentElement;
				var address = cell ? cell.querySelector( '.som-batch-address' ) : null;
				if ( ! address ) {
					return;
				}
				var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
				address.hidden = open;
				toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				toggle.textContent = open ? 'Show address' : 'Hide address';
			} );
		} );

		var focusId = list.getAttribute( 'data-focus-batch' );
		if ( focusId ) {
			var focusCard = document.getElementById( 'som-batch-' + focusId );
			if ( focusCard ) {
				setExpanded( focusCard, true );
				focusCard.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		}
	}

	function initGoalEditor() {
		var goalIndex = 0;
		var tbody = document.getElementById( 'som-goal-rows' );
		var template = document.getElementById( 'som-goal-row-template' );
		var addButton = document.getElementById( 'som-goal-add-row' );

		if ( ! tbody || ! template ) {
			return;
		}

		function nextIndex() {
			goalIndex += 1;
			return 'g' + goalIndex + '_' + Date.now();
		}

		function bindRemoveButtons( scope ) {
			var buttons = ( scope || document ).querySelectorAll( '.som-goal-remove' );
			buttons.forEach( function ( button ) {
				if ( button.dataset.bound ) {
					return;
				}
				button.dataset.bound = '1';
				button.addEventListener( 'click', function () {
					var row = button.closest( 'tr' );
					if ( row && tbody.querySelectorAll( '.som-goal-row' ).length > 1 ) {
						row.remove();
					} else if ( row ) {
						row.querySelectorAll( 'select, input' ).forEach( function ( field ) {
							if ( field.name && field.name.indexOf( 'som_goal_threshold' ) !== -1 ) {
								field.value = '90';
							} else {
								field.value = '';
							}
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

	function initPoPreviewImpact() {
		var button = document.getElementById( 'som-po-preview-impact' );
		var results = document.getElementById( 'som-po-preview-results' );
		if ( ! button || ! results || typeof somAdmin === 'undefined' || ! somAdmin.ajaxUrl ) {
			return;
		}

		function money( n, places ) {
			var v = Number( n );
			if ( isNaN( v ) ) {
				return '—';
			}
			return '£' + v.toFixed( places );
		}

		function alertBadge( level ) {
			if ( ! level ) {
				return '';
			}
			var label = level === 'over' ? 'Over goal' : 'Approaching goal';
			return '<span class="som-badge som-badge-goal-' + level + '">' + label + '</span>';
		}

		button.addEventListener( 'click', function () {
			var items = [];
			document.querySelectorAll( '#som-po-line-rows .som-po-line-row' ).forEach( function ( row ) {
				var material = row.querySelector( 'select[name^="som_po_material"]' );
				var qty = row.querySelector( 'input[name^="som_po_qty"]' );
				var cost = row.querySelector( 'input[name^="som_po_item_cost"]' );
				if ( ! material || ! material.value ) {
					return;
				}
				items.push( {
					material_id: material.value,
					quantity_ordered: qty ? qty.value : '',
					item_cost: cost ? cost.value : ''
				} );
			} );

			var shipping = document.getElementById( 'som_po_shipping' );
			var other = document.getElementById( 'som_po_other' );
			var body = new window.FormData();
			body.append( 'action', 'som_preview_po_impact' );
			body.append( 'nonce', somAdmin.previewNonce || '' );
			body.append( 'shipping_cost', shipping ? shipping.value : '0' );
			body.append( 'other_cost', other ? other.value : '0' );
			body.append( 'items', JSON.stringify( items ) );

			button.disabled = true;
			results.hidden = false;
			results.innerHTML = '<p class="som-muted">Calculating preview…</p>';

			fetch( somAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( payload ) {
					button.disabled = false;
					if ( ! payload || ! payload.success ) {
						var msg = payload && payload.data && payload.data.message ? payload.data.message : 'Preview failed.';
						results.innerHTML = '<div class="notice notice-error inline"><p>' + msg + '</p></div>';
						return;
					}

					var data = payload.data || {};
					var html = '<h2>Preview Impact</h2>';
					if ( data.warnings && data.warnings.length ) {
						html += '<div class="notice notice-warning inline"><p>' + data.warnings.join( ' ' ) + '</p></div>';
					}

					html += '<h3>Materials</h3><table class="widefat striped"><thead><tr>' +
						'<th>Material</th><th>Landed unit</th><th>Current WA</th><th>Projected WA</th><th>Alerts</th>' +
						'</tr></thead><tbody>';
					( data.lines || [] ).forEach( function ( line ) {
						var alerts = ( line.goal_alerts || [] ).map( function ( a ) {
							return alertBadge( a.level ) + ' ' + ( a.workflow_name || '' );
						} ).join( '<br />' ) || '—';
						html += '<tr>' +
							'<td>' + ( line.material_name || '' ) + '</td>' +
							'<td>' + money( line.landed_unit_cost, 4 ) + '</td>' +
							'<td>' + money( line.current_unit_cost, 4 ) + '</td>' +
							'<td>' + money( line.projected_unit_cost, 4 ) + '</td>' +
							'<td>' + alerts + '</td>' +
							'</tr>';
					} );
					html += '</tbody></table>';

					html += '<h3>Product impact</h3>';
					if ( ! data.products || ! data.products.length ) {
						html += '<p class="som-muted">No products use these materials.</p>';
					} else {
						html += '<table class="widefat striped"><thead><tr>' +
							'<th>Product</th><th>Material cost</th><th>Target</th><th>Margin</th><th>Alerts</th>' +
							'</tr></thead><tbody>';
						data.products.forEach( function ( p ) {
							var alerts = ( p.goal_alerts || [] ).map( function ( a ) {
								return alertBadge( a.level ) + ' ' + ( a.material_name || '' );
							} ).join( '<br />' ) || '—';
							html += '<tr>' +
								'<td>' + ( p.product_name || '' ) + '</td>' +
								'<td>' + money( p.material_cost, 4 ) + '</td>' +
								'<td>' + ( p.target_selling_price == null ? '—' : money( p.target_selling_price, 2 ) ) + '</td>' +
								'<td>' + ( p.margin_percent == null ? '—' : Number( p.margin_percent ).toFixed( 1 ) + '%' ) + '</td>' +
								'<td>' + alerts + '</td>' +
								'</tr>';
						} );
						html += '</tbody></table>';
					}

					results.innerHTML = html;
				} )
				.catch( function () {
					button.disabled = false;
					results.innerHTML = '<div class="notice notice-error inline"><p>Preview failed (network error).</p></div>';
				} );
		} );
	}

	function initPoLineEditor() {
		var lineIndex = 0;
		var tbody = document.getElementById( 'som-po-line-rows' );
		var template = document.getElementById( 'som-po-line-template' );
		var addButton = document.getElementById( 'som-po-add-line' );

		if ( ! tbody || ! template ) {
			return;
		}

		function nextIndex() {
			lineIndex += 1;
			return 'p' + lineIndex + '_' + Date.now();
		}

		function bindRemoveButtons( scope ) {
			var buttons = ( scope || document ).querySelectorAll( '.som-po-line-remove' );
			buttons.forEach( function ( button ) {
				if ( button.dataset.bound ) {
					return;
				}
				button.dataset.bound = '1';
				button.addEventListener( 'click', function () {
					var row = button.closest( 'tr' );
					if ( row && tbody.querySelectorAll( '.som-po-line-row' ).length > 1 ) {
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

	function initAdvanceStepRest() {
		if ( typeof somAdmin === 'undefined' || ! somAdmin.restUrl ) {
			return;
		}

		document.querySelectorAll( '[data-som-advance-step]' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var orderId = form.getAttribute( 'data-order-id' );
				var btn = form.querySelector( '[type="submit"]' );
				if ( btn && btn.disabled ) {
					return;
				}
				if ( btn ) {
					btn.disabled = true;
				}

				fetch( somAdmin.restUrl + 'orders/' + encodeURIComponent( orderId ) + '/advance-step', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': somAdmin.restNonce || ''
					},
					body: '{}'
				} )
					.then( function ( res ) {
						return res.json().then( function ( data ) {
							return { ok: res.ok, status: res.status, data: data };
						} );
					} )
					.then( function ( result ) {
						if ( ! result.ok ) {
							var msg = ( result.data && result.data.message ) ? result.data.message : 'Could not advance step.';
							window.alert( msg );
							if ( btn ) {
								btn.disabled = false;
							}
							return;
						}
						window.location.reload();
					} )
					.catch( function () {
						window.alert( 'Could not advance step (network error).' );
						if ( btn ) {
							btn.disabled = false;
						}
					} );
			} );
		} );
	}

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
