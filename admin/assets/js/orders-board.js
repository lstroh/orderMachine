/**
 * Order Board — pins, pinned-only filter, column ←/→ reorder.
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
	var pinnedOnly = document.querySelector('[data-som-board-pinned-only]');

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
			if (!col) {
				return;
			}
			var dir = moveBtn.getAttribute('data-som-col-move');
			var sibling = dir === 'left' ? col.previousElementSibling : col.nextElementSibling;
			if (!sibling || !sibling.hasAttribute('data-som-column-key')) {
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
			keys.push(col.getAttribute('data-som-column-key'));
		});
		post('som_board_save_columns', { columns: JSON.stringify(keys) });
	}
})();
