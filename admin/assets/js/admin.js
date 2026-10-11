/**
 * Order Machine admin scripts (recipe rows + workflow step editor).
 */
( function () {
	'use strict';

	function initTestOrderLines() {
		var tbody = document.getElementById( 'som-test-order-rows' );
		var template = document.getElementById( 'som-test-order-row-template' );
		var addButton = document.getElementById( 'som-test-order-add-row' );
		var maxLines = 5;
		var nextIndex = 1;

		if ( ! tbody || ! template ) {
			return;
		}

		function rowCount() {
			return tbody.querySelectorAll( '.som-test-order-row' ).length;
		}

		function bindRemove( scope ) {
			( scope || tbody ).querySelectorAll( '.som-test-order-remove' ).forEach( function ( button ) {
				if ( button.dataset.bound ) {
					return;
				}
				button.dataset.bound = '1';
				button.addEventListener( 'click', function () {
					var row = button.closest( 'tr' );
					if ( ! row ) {
						return;
					}
					if ( rowCount() > 1 ) {
						row.remove();
					} else {
						row.querySelectorAll( 'select, input' ).forEach( function ( field ) {
							if ( field.type === 'number' && field.name && field.name.indexOf( '[quantity]' ) !== -1 ) {
								field.value = '1';
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
				if ( rowCount() >= maxLines ) {
					return;
				}
				var html = template.innerHTML.replace( /__INDEX__/g, String( nextIndex ) );
				nextIndex += 1;
				var wrapper = document.createElement( 'tbody' );
				wrapper.innerHTML = html.trim();
				var row = wrapper.firstElementChild;
				if ( row ) {
					tbody.appendChild( row );
					bindRemove( row );
				}
			} );
		}

		bindRemove( tbody );
	}

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
		var form = list ? list.closest( 'form' ) : null;
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

		function stepHasGate( card ) {
			var manual = card.querySelector( 'input[name*="[requires_manual_confirm]"]' );
			var timer = card.querySelector( 'input[name*="[timer_value]"]' );
			var confirmSelect = card.querySelector( '.som-confirmation-kind' );
			var batchSelect = card.querySelector( '.som-batch-group-select' );
			var commandField = card.querySelector( '.som-command-template' );
			var hasManual = manual && manual.checked;
			var hasTimer = timer && String( timer.value ).trim() !== '';
			var hasConfirm = confirmSelect && confirmSelect.value !== '';
			var hasBatch = batchSelect && batchSelect.value && batchSelect.value !== '0';
			var hasCopy = commandField && String( commandField.value || '' ).trim() !== '';
			return !!( hasManual || hasTimer || hasConfirm || hasBatch || hasCopy );
		}

		function stepDisplayName( card ) {
			var nameField = card.querySelector( '.som-step-name' );
			var name = nameField ? String( nameField.value || '' ).trim() : '';
			return name || '(unnamed)';
		}

		function namedCards() {
			return cards().filter( function ( card ) {
				var nameField = card.querySelector( '.som-step-name' );
				return nameField && String( nameField.value || '' ).trim() !== '';
			} );
		}

		function syncZeroGateWarning( card ) {
			var warning = card.querySelector( '[data-som-zero-gate-warning]' );
			if ( ! warning ) {
				return;
			}
			var nameField = card.querySelector( '.som-step-name' );
			var hasName = nameField && String( nameField.value || '' ).trim() !== '';
			warning.hidden = ! hasName || stepHasGate( card );
		}

		function plannedSecondsForCard( card ) {
			var valueField = card.querySelector( '[data-som-planned-value]' );
			var unitField = card.querySelector( '[data-som-planned-unit]' );
			if ( ! valueField ) {
				return null;
			}
			var raw = String( valueField.value || '' ).trim();
			if ( raw === '' || isNaN( Number( raw ) ) || Number( raw ) <= 0 ) {
				return null;
			}
			var unit = unitField ? String( unitField.value || 'minutes' ) : 'minutes';
			var mult = 60;
			if ( unit === 'hours' ) {
				mult = 3600;
			} else if ( unit === 'days' ) {
				mult = 86400;
			}
			return Math.round( Number( raw ) * mult );
		}

		function formatPlannedTotal( seconds ) {
			if ( seconds === null || seconds === undefined ) {
				return '—';
			}
			seconds = Math.max( 0, Math.floor( seconds ) );
			if ( seconds === 0 ) {
				return '0s';
			}
			if ( seconds < 60 ) {
				return seconds + 's';
			}
			if ( seconds < 3600 ) {
				return Math.floor( seconds / 60 ) + 'm';
			}
			if ( seconds < 86400 ) {
				var h = Math.floor( seconds / 3600 );
				var m = Math.floor( ( seconds % 3600 ) / 60 );
				return m > 0 ? ( h + 'h ' + m + 'm' ) : ( h + 'h' );
			}
			var d = Math.floor( seconds / 86400 );
			var hours = Math.floor( ( seconds % 86400 ) / 3600 );
			return hours > 0 ? ( d + 'd ' + hours + 'h' ) : ( d + 'd' );
		}

		function syncPlannedTotal() {
			var totalEl = document.querySelector( '[data-som-planned-total-value]' );
			var partialEl = document.querySelector( '[data-som-planned-partial]' );
			if ( ! totalEl ) {
				return;
			}
			var named = namedCards();
			var sum = 0;
			var plannedCount = 0;
			named.forEach( function ( card ) {
				var secs = plannedSecondsForCard( card );
				if ( secs === null ) {
					return;
				}
				plannedCount += 1;
				sum += secs;
			} );
			totalEl.textContent = plannedCount > 0 ? formatPlannedTotal( sum ) : '—';
			if ( partialEl ) {
				partialEl.hidden = !( plannedCount > 0 && plannedCount < named.length );
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
						var batchSelect = card.querySelector( '.som-batch-group-select' );
						if ( batchSelect ) {
							batchSelect.value = '0';
						}
						var confirmSelect = card.querySelector( '.som-confirmation-kind' );
						if ( confirmSelect ) {
							confirmSelect.value = '';
						}
						var commandField = card.querySelector( '.som-command-template' );
						if ( commandField ) {
							commandField.value = '';
						}
						var idField = card.querySelector( 'input[name*="[id]"]' );
						if ( idField ) {
							idField.value = '0';
						}
						var taskList = card.querySelector( '[data-som-task-list]' );
						if ( taskList ) {
							resetTaskList( taskList );
						}
						syncBatchWarning( card );
						syncConfirmWarning( card );
						syncCopyWarning( card );
						syncZeroGateWarning( card );
						syncPlannedTotal();
						return;
					}
					card.remove();
					refreshOrder();
					syncPlannedTotal();
				} );
			}

			var batchSelectBind = card.querySelector( '.som-batch-group-select' );
			var manualBind = card.querySelector( 'input[name*="[requires_manual_confirm]"]' );
			var timerBind = card.querySelector( 'input[name*="[timer_value]"]' );
			var plannedValueBind = card.querySelector( '[data-som-planned-value]' );
			var plannedUnitBind = card.querySelector( '[data-som-planned-unit]' );
			var confirmBind = card.querySelector( '.som-confirmation-kind' );
			var commandBind = card.querySelector( '.som-command-template' );
			var nameBind = card.querySelector( '.som-step-name' );
			if ( batchSelectBind ) {
				batchSelectBind.addEventListener( 'change', function () {
					syncBatchWarning( card );
					syncConfirmWarning( card );
					syncCopyWarning( card );
					syncZeroGateWarning( card );
				} );
			}
			if ( manualBind ) {
				manualBind.addEventListener( 'change', function () {
					syncBatchWarning( card );
					syncZeroGateWarning( card );
				} );
			}
			if ( timerBind ) {
				timerBind.addEventListener( 'input', function () {
					syncBatchWarning( card );
					syncConfirmWarning( card );
					syncCopyWarning( card );
					syncZeroGateWarning( card );
				} );
			}
			if ( confirmBind ) {
				confirmBind.addEventListener( 'change', function () {
					if ( confirmBind.value ) {
						var manual = card.querySelector( 'input[name*="[requires_manual_confirm]"]' );
						if ( manual ) {
							manual.checked = true;
						}
					}
					syncBatchWarning( card );
					syncConfirmWarning( card );
					syncCopyWarning( card );
					syncZeroGateWarning( card );
				} );
			}
			if ( commandBind ) {
				commandBind.addEventListener( 'input', function () {
					if ( String( commandBind.value || '' ).trim() !== '' ) {
						var manual = card.querySelector( 'input[name*="[requires_manual_confirm]"]' );
						if ( manual ) {
							manual.checked = true;
						}
					}
					syncBatchWarning( card );
					syncConfirmWarning( card );
					syncCopyWarning( card );
					syncZeroGateWarning( card );
				} );
			}
			if ( nameBind ) {
				nameBind.addEventListener( 'input', function () {
					syncZeroGateWarning( card );
					syncPlannedTotal();
				} );
			}
			if ( plannedValueBind ) {
				plannedValueBind.addEventListener( 'input', syncPlannedTotal );
			}
			if ( plannedUnitBind ) {
				plannedUnitBind.addEventListener( 'change', syncPlannedTotal );
			}

			syncBatchWarning( card );
			syncConfirmWarning( card );
			syncCopyWarning( card );
			syncZeroGateWarning( card );
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
			var confirmSelect = card.querySelector( '.som-confirmation-kind' );
			var commandField = card.querySelector( '.som-command-template' );
			var hasManual = manual && manual.checked;
			var hasTimer = timer && String( timer.value ).trim() !== '';
			var hasConfirm = confirmSelect && confirmSelect.value !== '';
			var hasCopy = commandField && String( commandField.value || '' ).trim() !== '';
			warning.hidden = ! ( hasManual || hasTimer || hasConfirm || hasCopy );
		}

		function syncConfirmWarning( card ) {
			var warning = card.querySelector( '[data-som-confirm-warning]' );
			var confirmSelect = card.querySelector( '.som-confirmation-kind' );
			if ( ! warning || ! confirmSelect ) {
				return;
			}
			var hasConfirm = confirmSelect.value !== '';
			if ( ! hasConfirm ) {
				warning.hidden = true;
				return;
			}
			var timer = card.querySelector( 'input[name*="[timer_value]"]' );
			var batchSelect = card.querySelector( '.som-batch-group-select' );
			var hasTimer = timer && String( timer.value ).trim() !== '';
			var hasBatch = batchSelect && batchSelect.value && batchSelect.value !== '0';
			warning.hidden = ! ( hasTimer || hasBatch );
		}

		function syncCopyWarning( card ) {
			var warning = card.querySelector( '[data-som-copy-warning]' );
			var commandField = card.querySelector( '.som-command-template' );
			if ( ! warning || ! commandField ) {
				return;
			}
			var hasCopy = String( commandField.value || '' ).trim() !== '';
			if ( ! hasCopy ) {
				warning.hidden = true;
				return;
			}
			var timer = card.querySelector( 'input[name*="[timer_value]"]' );
			var batchSelect = card.querySelector( '.som-batch-group-select' );
			var hasTimer = timer && String( timer.value ).trim() !== '';
			var hasBatch = batchSelect && batchSelect.value && batchSelect.value !== '0';
			warning.hidden = ! ( hasTimer || hasBatch );
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
					var manual = card.querySelector( 'input[name*="[requires_manual_confirm]"]' );
					if ( manual ) {
						manual.checked = true;
					}
					list.appendChild( card );
					bindCard( card );
					bindTaskLists( card );
					refreshOrder();
					syncPlannedTotal();
				}
			} );
		}

		syncPlannedTotal();

		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				var ungated = namedCards().filter( function ( card ) {
					return ! stepHasGate( card );
				} );
				if ( ! ungated.length ) {
					return;
				}
				var names = ungated.map( stepDisplayName ).join( ', ' );
				var msg =
					'These steps have no gates and will auto-complete when entered:\n\n' +
					names +
					'\n\nSave anyway? (Pass-through steps are allowed.)';
				if ( ! window.confirm( msg ) ) {
					e.preventDefault();
				}
			} );
		}
	}

	function reindexTaskRows( container ) {
		var rows = container.querySelectorAll( '[data-som-task-row]' );
		rows.forEach( function ( row, i ) {
			row.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /\[tasks\]\[[^\]]+\]/, '[tasks][' + i + ']' );
				// Product override: som_step_tasks[STEP][i][…]
				field.name = field.name.replace( /(som_step_tasks\[[^\]]+\])\[[^\]]+\]/, '$1[' + i + ']' );
			} );
		} );
	}

	function resetTaskList( listEl ) {
		var rowsWrap = listEl.querySelector( '[data-som-task-rows]' );
		if ( ! rowsWrap ) {
			return;
		}
		var rows = rowsWrap.querySelectorAll( '[data-som-task-row]' );
		rows.forEach( function ( row, i ) {
			if ( i === 0 ) {
				row.querySelectorAll( 'input' ).forEach( function ( field ) {
					field.value = '';
				} );
			} else {
				row.remove();
			}
		} );
		reindexTaskRows( rowsWrap );
	}

	function bindTaskLists( scope ) {
		var lists = ( scope || document ).querySelectorAll( '[data-som-task-list]' );
		lists.forEach( function ( listEl ) {
			if ( listEl.dataset.taskBound ) {
				return;
			}
			listEl.dataset.taskBound = '1';

			var rowsWrap = listEl.querySelector( '[data-som-task-rows]' );
			var addBtn = listEl.querySelector( '[data-som-task-add]' );
			if ( ! rowsWrap ) {
				return;
			}

			function bindRemove( row ) {
				var btn = row.querySelector( '.som-task-remove' );
				if ( ! btn || btn.dataset.bound ) {
					return;
				}
				btn.dataset.bound = '1';
				btn.addEventListener( 'click', function () {
					var all = rowsWrap.querySelectorAll( '[data-som-task-row]' );
					if ( all.length <= 1 ) {
						row.querySelectorAll( 'input' ).forEach( function ( field ) {
							field.value = '';
						} );
						return;
					}
					row.remove();
					reindexTaskRows( rowsWrap );
				} );
			}

			rowsWrap.querySelectorAll( '[data-som-task-row]' ).forEach( bindRemove );

			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					var first = rowsWrap.querySelector( '[data-som-task-row]' );
					var clone;
					if ( first ) {
						clone = first.cloneNode( true );
					} else {
						return;
					}
					clone.querySelectorAll( 'input' ).forEach( function ( field ) {
						field.value = '';
					} );
					var removeBtn = clone.querySelector( '.som-task-remove' );
					if ( removeBtn ) {
						delete removeBtn.dataset.bound;
					}
					rowsWrap.appendChild( clone );
					reindexTaskRows( rowsWrap );
					bindRemove( clone );
				} );
			}
		} );
	}

	function initStepTaskLists() {
		bindTaskLists( document );
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

	initTestOrderLines();
	initRecipeEditor();
	initGoalEditor();
	initPoLineEditor();
	initPoPreviewImpact();
	initWorkflowEditor();
	initStepTaskLists();
	initMarkdownPreview();
	initListingEditor();
	initBatchesPage();
	initCountdowns();
	initLiveElapsed();
	initAdvanceStepRest();
	initCopyText();

	function initMarkdownPreview() {
		if ( typeof somAdmin === 'undefined' || ! somAdmin.ajaxUrl || ! somAdmin.markdownPreviewNonce ) {
			return;
		}

		document.addEventListener( 'click', function ( event ) {
			var showBtn = event.target.closest( '[data-som-md-preview-toggle]' );
			var hideBtn = event.target.closest( '[data-som-md-preview-hide]' );
			if ( ! showBtn && ! hideBtn ) {
				return;
			}

			var editor = ( showBtn || hideBtn ).closest( '[data-som-md-editor]' );
			if ( ! editor ) {
				return;
			}

			var panel = editor.querySelector( '[data-som-md-preview]' );
			var body = editor.querySelector( '[data-som-md-preview-body]' );
			var toggle = editor.querySelector( '[data-som-md-preview-toggle]' );
			var hide = editor.querySelector( '[data-som-md-preview-hide]' );
			var source = editor.querySelector( '.som-md-source' );

			if ( hideBtn ) {
				if ( panel ) {
					panel.hidden = true;
				}
				if ( hide ) {
					hide.hidden = true;
				}
				if ( toggle ) {
					toggle.hidden = false;
				}
				return;
			}

			if ( ! panel || ! body || ! source ) {
				return;
			}

			var markdown = source.value || '';
			var emptyMsg = ( somAdmin.i18n && somAdmin.i18n.mdPreviewEmpty ) ? somAdmin.i18n.mdPreviewEmpty : 'Nothing to preview.';
			var failMsg = ( somAdmin.i18n && somAdmin.i18n.mdPreviewFailed ) ? somAdmin.i18n.mdPreviewFailed : 'Preview failed.';

			if ( ! markdown.trim() ) {
				body.textContent = emptyMsg;
				panel.hidden = false;
				if ( toggle ) {
					toggle.hidden = true;
				}
				if ( hide ) {
					hide.hidden = false;
				}
				return;
			}

			body.textContent = '…';
			panel.hidden = false;
			if ( toggle ) {
				toggle.hidden = true;
			}
			if ( hide ) {
				hide.hidden = false;
			}

			var form = new window.FormData();
			form.append( 'action', 'som_preview_step_instructions' );
			form.append( 'nonce', somAdmin.markdownPreviewNonce );
			form.append( 'markdown', markdown );

			fetch( somAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: form
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( payload ) {
					if ( ! payload || ! payload.success || ! payload.data ) {
						body.textContent = failMsg;
						return;
					}
					body.innerHTML = payload.data.html || '';
					if ( ! body.innerHTML ) {
						body.textContent = emptyMsg;
					}
				} )
				.catch( function () {
					body.textContent = failMsg;
				} );
		} );
	}

	function initCopyText() {
		document.querySelectorAll( '[data-som-copy-text], [data-som-copy-from]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var text = '';
				var fromId = btn.getAttribute( 'data-som-copy-from' );
				if ( fromId ) {
					var el = document.getElementById( fromId );
					text = el ? String( el.textContent || '' ) : '';
				} else {
					text = btn.getAttribute( 'data-som-copy-text' ) || '';
				}
				if ( ! text ) {
					return;
				}
				var done = function () {
					var original = btn.textContent;
					btn.textContent = 'Copied';
					setTimeout( function () {
						btn.textContent = original;
					}, 1500 );
				};
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( done ).catch( function () {
						window.prompt( 'Copy:', text );
					} );
				} else {
					window.prompt( 'Copy:', text );
				}
			} );
		} );
	}

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

		var unlocking = {};
		var notified = {};

		maybeRequestNotifyPermission();

		function maybeRequestNotifyPermission() {
			if ( typeof Notification === 'undefined' ) {
				return;
			}
			if ( Notification.permission === 'default' ) {
				try {
					Notification.requestPermission();
				} catch ( e ) {
					/* ignore */
				}
			}
		}

		function notifyTimerReady( orderId, stepName, orderRef ) {
			var key = 'som_timer_ready_' + String( orderId );
			if ( notified[ key ] || ( typeof sessionStorage !== 'undefined' && sessionStorage.getItem( key ) ) ) {
				return;
			}
			notified[ key ] = true;
			try {
				if ( typeof sessionStorage !== 'undefined' ) {
					sessionStorage.setItem( key, '1' );
				}
			} catch ( e ) {
				/* ignore */
			}
			if ( typeof Notification === 'undefined' || Notification.permission !== 'granted' ) {
				return;
			}
			var title = ( stepName || 'Step' ) + ' ready';
			var body = orderRef
				? ( 'Order ' + orderRef + ' — ready to advance' )
				: 'Ready to advance';
			try {
				new Notification( title, { body: body, tag: key } );
			} catch ( err ) {
				/* ignore */
			}
		}

		function applyDetailReady( el, data ) {
			var step = el.closest( '.som-workflow-step' );
			var badge = step ? step.querySelector( '[data-som-step-status-badge]' ) : null;
			var readyBadge = el.getAttribute( 'data-ready-badge' ) || 'Timer ready';
			var readyLabel = el.getAttribute( 'data-ready-label' ) || 'Timer ready — you can Mark done.';

			el.textContent = readyLabel;
			el.classList.add( 'som-timer-ready' );
			el.removeAttribute( 'data-som-countdown' );

			if ( step ) {
				step.classList.add( 'is-timer-ready' );
				step.classList.remove( 'status-waiting_timer' );
				step.classList.add( 'status-in_progress' );
			}
			if ( badge ) {
				badge.className = 'som-badge som-badge-step-timer_ready';
				badge.setAttribute( 'data-som-step-status-badge', '' );
				badge.textContent = readyBadge;
			}

			var form = step ? step.querySelector( '.som-mark-done-form' ) : document.querySelector( '.som-mark-done-form[data-som-advance-step]' );
			if ( form ) {
				var btn = form.querySelector( 'input[type="submit"], button[type="submit"]' );
				if ( btn ) {
					btn.disabled = false;
					btn.removeAttribute( 'disabled' );
				}
			}

			var canAdvance = data && data.can_advance;
			if ( canAdvance === false && data && data.status === 'waiting_script' ) {
				/* script took over — leave Mark done as-is */
				return;
			}
			notifyTimerReady(
				el.getAttribute( 'data-order-id' ),
				( data && data.step_name ) || el.getAttribute( 'data-step-name' ),
				( data && data.external_order_id ) || el.getAttribute( 'data-order-ref' )
			);
		}

		function unlockViaRest( el ) {
			var orderId = el.getAttribute( 'data-order-id' );
			if ( ! orderId || unlocking[ orderId ] ) {
				return;
			}
			if ( typeof somAdmin === 'undefined' || ! somAdmin.restUrl ) {
				el.textContent = el.getAttribute( 'data-ready-label' ) || 'Timer ready — refresh to unlock Mark done.';
				return;
			}
			unlocking[ orderId ] = true;
			fetch( somAdmin.restUrl + 'orders/' + encodeURIComponent( orderId ) + '/progress', {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': somAdmin.restNonce || ''
				}
			} )
				.then( function ( res ) {
					return res.json().then( function ( data ) {
						return { ok: res.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					unlocking[ orderId ] = false;
					if ( ! result.ok || ! result.data ) {
						el.textContent = el.getAttribute( 'data-ready-label' ) || 'Timer ready — refresh to unlock Mark done.';
						return;
					}
					applyDetailReady( el, result.data );
				} )
				.catch( function () {
					unlocking[ orderId ] = false;
					el.textContent = el.getAttribute( 'data-ready-label' ) || 'Timer ready — refresh to unlock Mark done.';
				} );
		}

		function tick() {
			var now = Math.floor( Date.now() / 1000 );
			var live = document.querySelectorAll( '[data-som-countdown]' );
			live.forEach( function ( el ) {
				var ends = parseInt( el.getAttribute( 'data-ends-at' ), 10 );
				if ( ! ends ) {
					return;
				}
				var remaining = ends - now;
				if ( remaining <= 0 ) {
					unlockViaRest( el );
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

	/**
	 * Live wall-clock elapsed for in-progress steps (display only; not persisted until Mark done).
	 */
	function initLiveElapsed() {
		var nodes = document.querySelectorAll( '[data-som-elapsed-from]' );
		if ( ! nodes.length ) {
			return;
		}

		function formatElapsed( seconds ) {
			seconds = Math.max( 0, Math.floor( seconds ) );
			if ( seconds < 60 ) {
				return seconds + 's';
			}
			if ( seconds < 3600 ) {
				return Math.floor( seconds / 60 ) + 'm';
			}
			if ( seconds < 86400 ) {
				var h = Math.floor( seconds / 3600 );
				var m = Math.floor( ( seconds % 3600 ) / 60 );
				return m > 0 ? ( h + 'h ' + m + 'm' ) : ( h + 'h' );
			}
			var d = Math.floor( seconds / 86400 );
			var hours = Math.floor( ( seconds % 86400 ) / 3600 );
			return hours > 0 ? ( d + 'd ' + hours + 'h' ) : ( d + 'd' );
		}

		function tickElapsed() {
			var now = Math.floor( Date.now() / 1000 );
			document.querySelectorAll( '[data-som-elapsed-from]' ).forEach( function ( el ) {
				var start = parseInt( el.getAttribute( 'data-som-elapsed-from' ), 10 );
				if ( ! start ) {
					return;
				}
				el.textContent = formatElapsed( now - start );
			} );
		}

		tickElapsed();
		setInterval( tickElapsed, 1000 );
	}
}() );
