/**
 * Order Board — pins, column ←/→ reorder, gated SortableJS advance-step.
 */
(function () {
	'use strict';

	var cfg = typeof somBoard !== 'undefined' ? somBoard : null;
	if (!cfg || !cfg.ajaxUrl) {
		return;
	}

	var board = document.querySelector('[data-som-board]');
	if (!board) {
		return;
	}

	var columnsWrap = board.querySelector('[data-som-board-columns]');
	var boardRow = board.querySelector('.som-board-row') || board;
	var pinnedOnly = document.querySelector('[data-som-board-pinned-only]');
	var completeKey = cfg.completeKey || '__complete__';
	var statusLabels = cfg.statusLabels || {};
	var advancing = {};

	function post(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (res) {
			return res.json();
		});
	}

	function applyPinnedFilter() {
		var only = pinnedOnly && pinnedOnly.checked;
		board.querySelectorAll('.som-board-card').forEach(function (card) {
			var pinned = card.getAttribute('data-som-pinned') === '1';
			card.hidden = only && !pinned;
		});
		refreshColumnCounts();
	}

	function refreshColumnCounts() {
		board.querySelectorAll('.som-board-column').forEach(function (col) {
			var visible = col.querySelectorAll('.som-board-card:not([hidden])').length;
			var countEl = col.querySelector('.som-board-column-count');
			if (countEl) {
				countEl.textContent = String(visible);
			}
		});
	}

	if (pinnedOnly) {
		pinnedOnly.addEventListener('change', applyPinnedFilter);
	}

	board.addEventListener('click', function (event) {
		var pinBtn = event.target.closest('[data-som-board-pin]');
		if (pinBtn) {
			event.preventDefault();
			var card = pinBtn.closest('.som-board-card');
			if (!card) {
				return;
			}
			var orderId = card.getAttribute('data-som-order-id');
			pinBtn.disabled = true;
			post('som_board_toggle_pin', { order_id: orderId })
				.then(function (json) {
					if (!json || !json.success) {
						return;
					}
					var pinned = !!(json.data && json.data.pinned);
					card.setAttribute('data-som-pinned', pinned ? '1' : '0');
					card.classList.toggle('is-pinned', pinned);
					pinBtn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
					pinBtn.title = pinned
						? (cfg.i18n && cfg.i18n.unpin) || 'Unpin'
						: (cfg.i18n && cfg.i18n.pin) || 'Pin';
					applyPinnedFilter();
				})
				.finally(function () {
					pinBtn.disabled = false;
				});
			return;
		}

		var moveBtn = event.target.closest('[data-som-col-move]');
		if (moveBtn && columnsWrap) {
			event.preventDefault();
			var col = moveBtn.closest('[data-som-column-key]');
			if (!col || col.hasAttribute('data-som-complete-zone')) {
				return;
			}
			var dir = moveBtn.getAttribute('data-som-col-move');
			var sibling = dir === 'left' ? col.previousElementSibling : col.nextElementSibling;
			if (!sibling || !sibling.hasAttribute('data-som-column-key') || sibling.hasAttribute('data-som-complete-zone')) {
				return;
			}
			if (dir === 'left') {
				columnsWrap.insertBefore(col, sibling);
			} else {
				columnsWrap.insertBefore(sibling, col);
			}
			persistColumnOrder();
		}
	});

	function persistColumnOrder() {
		if (!columnsWrap) {
			return;
		}
		var keys = [];
		columnsWrap.querySelectorAll('[data-som-column-key]').forEach(function (col) {
			if (col.hasAttribute('data-som-complete-zone')) {
				return;
			}
			keys.push(col.getAttribute('data-som-column-key'));
		});
		post('som_board_save_columns', { columns: JSON.stringify(keys) });
	}

	function validDropTarget(card, listEl) {
		if (!card || !listEl) {
			return false;
		}
		var col = listEl.closest('[data-som-column-key]');
		if (!col) {
			return false;
		}
		var targetKey = col.getAttribute('data-som-column-key');
		var isLast = card.getAttribute('data-som-is-last-step') === '1';
		var next = card.getAttribute('data-som-next-step-name') || '';
		if (isLast) {
			return targetKey === completeKey;
		}
		return !!next && targetKey === next;
	}

	function restoreCard(card, fromList, oldIndex) {
		if (!fromList || !card) {
			return;
		}
		var children = Array.prototype.slice.call(fromList.children).filter(function (el) {
			return el.classList && el.classList.contains('som-board-card');
		});
		var ref = children[oldIndex] || null;
		if (ref && ref.parentNode === fromList) {
			fromList.insertBefore(card, ref);
		} else {
			fromList.appendChild(card);
		}
		refreshColumnCounts();
		applyPinnedFilter();
	}

	function ensureColumn(stepName) {
		if (!columnsWrap || !stepName) {
			return null;
		}
		var existing = columnsWrap.querySelector('[data-som-column-key="' + cssEscape(stepName) + '"]');
		if (existing) {
			return existing;
		}
		var section = document.createElement('section');
		section.className = 'som-board-column';
		section.setAttribute('data-som-column-key', stepName);
		section.innerHTML =
			'<header class="som-board-column-header">' +
			'<div class="som-board-column-title"><strong></strong><span class="som-board-column-count">0</span></div>' +
			'<div class="som-board-column-actions">' +
			'<button type="button" class="button button-small" data-som-col-move="left" title="Move column left">←</button>' +
			'<button type="button" class="button button-small" data-som-col-move="right" title="Move column right">→</button>' +
			'</div></header>' +
			'<div class="som-board-cards" data-som-sortable-list></div>';
		section.querySelector('.som-board-column-title strong').textContent = stepName;
		columnsWrap.appendChild(section);
		initSortableList(section.querySelector('[data-som-sortable-list]'));
		return section;
	}

	function cssEscape(value) {
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(value);
		}
		return String(value).replace(/["\\]/g, '\\$&');
	}

	function statusLabel(status) {
		return statusLabels[status] || status || '';
	}

	function formatBatchLabel(batch) {
		var tpl = (cfg.i18n && cfg.i18n.batchLabel) || 'Batch #%3$d: %1$d of %2$d';
		return tpl
			.replace('%1$d', String(batch.item_count || 0))
			.replace('%2$d', String(Math.max(1, batch.group_batch_size || 1)))
			.replace('%3$d', String(batch.id || 0));
	}

	function updateStatusBadge(card, status) {
		var meta = card.querySelector('.som-board-card-meta');
		if (!meta) {
			return;
		}
		var badge = meta.querySelector('[data-som-card-status]');
		var slug = String(status || '').replace(/[^a-z0-9_]/g, '');
		if (!status) {
			if (badge) {
				badge.remove();
			}
			return;
		}
		if (!badge) {
			badge = document.createElement('span');
			badge.setAttribute('data-som-card-status', '');
			meta.appendChild(badge);
		}
		badge.className = 'som-badge som-badge-step-' + slug;
		badge.textContent = statusLabel(status);
	}

	function updateStepBadge(card, stepName) {
		var stepEl = card.querySelector('[data-som-card-step]');
		if (stepEl) {
			stepEl.textContent = stepName || '';
		}
	}

	function updateBatchBlock(card, batch) {
		var block = card.querySelector('[data-som-card-batch]');
		if (!block) {
			return;
		}
		if (!batch || !batch.id) {
			block.hidden = true;
			block.innerHTML = '';
			return;
		}
		block.hidden = false;
		var href = batch.url || '#';
		block.innerHTML = '<a href="' + href.replace(/"/g, '&quot;') + '"></a>';
		block.querySelector('a').textContent = formatBatchLabel(batch);
	}

	function applyDndMeta(card, data) {
		var canAdvance = !!(data && data.can_advance);
		var isLast = !!(data && data.is_last_step);
		var next = (data && data.next_step_name) ? String(data.next_step_name) : '';
		var status = (data && data.progress_status) ? String(data.progress_status) : '';

		card.setAttribute('data-som-can-advance', canAdvance ? '1' : '0');
		card.setAttribute('data-som-is-last-step', isLast ? '1' : '0');
		card.setAttribute('data-som-next-step-name', next);
		card.setAttribute('data-som-progress-status', status);
		card.classList.toggle('is-locked', !canAdvance);
		if (canAdvance) {
			card.removeAttribute('data-som-locked');
		} else {
			card.setAttribute('data-som-locked', '1');
		}
		updateStatusBadge(card, status);
		updateBatchBlock(card, (data && data.batch) || null);

		if (canAdvance && isLast) {
			ensureCompleteZone();
		} else if (canAdvance && next) {
			ensureColumn(next);
		}
	}

	function ensureCompleteZone() {
		var existing = board.querySelector('[data-som-complete-zone]');
		if (existing) {
			return existing;
		}
		var section = document.createElement('section');
		section.className = 'som-board-column som-board-complete-zone';
		section.setAttribute('data-som-column-key', completeKey);
		section.setAttribute('data-som-complete-zone', '');
		section.innerHTML =
			'<header class="som-board-column-header">' +
			'<div class="som-board-column-title"><strong>Complete</strong><span class="som-board-column-count">0</span></div>' +
			'</header>' +
			'<div class="som-board-cards" data-som-sortable-list>' +
			'<p class="som-muted som-board-complete-hint" data-som-complete-hint>Drop final-step orders here to complete.</p>' +
			'</div>';
		boardRow.appendChild(section);
		initSortableList(section.querySelector('[data-som-sortable-list]'));
		return section;
	}

	function placeCardAfterAdvance(card, data) {
		if (!data || Number(data.is_complete) === 1) {
			if (card.parentNode) {
				card.parentNode.removeChild(card);
			}
			card.classList.remove('is-advancing');
			refreshColumnCounts();
			applyPinnedFilter();
			return;
		}

		var stepName = String(data.current_step_name || '');
		var col = ensureColumn(stepName);
		var list = col ? col.querySelector('[data-som-sortable-list]') : null;
		if (list && card.parentNode !== list) {
			list.appendChild(card);
		}

		updateStepBadge(card, stepName);
		applyDndMeta(card, data);
		card.classList.remove('is-advancing');
		refreshColumnCounts();
		applyPinnedFilter();
	}

	function advanceStep(orderId) {
		if (!cfg.restUrl) {
			return Promise.reject(new Error('missing rest'));
		}
		return fetch(cfg.restUrl + 'orders/' + encodeURIComponent(orderId) + '/advance-step', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.restNonce || ''
			},
			body: '{}'
		}).then(function (res) {
			return res.json().then(function (data) {
				return { ok: res.ok, status: res.status, data: data };
			});
		});
	}

	function onCardDropped(evt) {
		var card = evt.item;
		var fromList = evt.from;
		var oldIndex = evt.oldIndex;
		var orderId = card.getAttribute('data-som-order-id');

		if (!validDropTarget(card, evt.to)) {
			restoreCard(card, fromList, oldIndex);
			return;
		}

		if (!orderId || advancing[orderId]) {
			restoreCard(card, fromList, oldIndex);
			return;
		}

		advancing[orderId] = true;
		card.classList.add('is-advancing');

		advanceStep(orderId)
			.then(function (result) {
				if (!result.ok || !result.data || !result.data.ok) {
					var msg =
						(result.data && result.data.message) ||
						(cfg.i18n && cfg.i18n.advanceError) ||
						'Could not advance step.';
					window.alert(msg);
					restoreCard(card, fromList, oldIndex);
					card.classList.remove('is-advancing');
					return;
				}
				placeCardAfterAdvance(card, result.data);
			})
			.catch(function () {
				window.alert((cfg.i18n && cfg.i18n.networkError) || 'Could not advance step (network error).');
				restoreCard(card, fromList, oldIndex);
				card.classList.remove('is-advancing');
			})
			.finally(function () {
				delete advancing[orderId];
			});
	}

	function initSortableList(listEl) {
		if (!listEl || listEl.getAttribute('data-som-sortable-ready') === '1') {
			return;
		}
		if (typeof Sortable === 'undefined') {
			return;
		}
		listEl.setAttribute('data-som-sortable-ready', '1');
		Sortable.create(listEl, {
			group: 'som-board',
			animation: 150,
			draggable: '.som-board-card[data-som-can-advance="1"]',
			filter: 'a, button, .som-board-pin, .som-board-complete-hint',
			preventOnFilter: false,
			sort: false,
			ghostClass: 'som-board-card-ghost',
			chosenClass: 'som-board-card-chosen',
			dragClass: 'som-board-card-drag',
			onMove: function (evt) {
				return validDropTarget(evt.dragged, evt.to);
			},
			onAdd: onCardDropped
		});
	}

	function initSortables() {
		board.querySelectorAll('[data-som-sortable-list]').forEach(initSortableList);
	}

	initSortables();
})();
