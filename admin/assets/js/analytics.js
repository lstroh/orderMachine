/**
 * Analytics dashboard — Chart.js wiring from embedded JSON.
 */
(function () {
	'use strict';

	var dataEl = document.getElementById('som-analytics-data');
	if (!dataEl || typeof Chart === 'undefined') {
		return;
	}

	var payload;
	try {
		payload = JSON.parse(dataEl.textContent || '{}');
	} catch (e) {
		return;
	}

	var labels = payload.labels || [];
	var currency = function (ctx) {
		var v = ctx.parsed && typeof ctx.parsed.y === 'number' ? ctx.parsed.y : ctx.parsed;
		if (v === null || typeof v === 'undefined') {
			return '';
		}
		return '£' + Number(v).toFixed(2);
	};

	function lineChart(canvasId, label, data, color) {
		var el = document.getElementById(canvasId);
		if (!el) {
			return null;
		}
		return new Chart(el, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: label,
						data: data,
						borderColor: color,
						backgroundColor: color,
						tension: 0.2,
						spanGaps: true,
						pointRadius: 2
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: {
					legend: { display: false },
					tooltip: { callbacks: { label: currency } }
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							callback: function (value) {
								return '£' + Number(value).toFixed(0);
							}
						}
					}
				}
			}
		});
	}

	lineChart('som-chart-sales', 'Sales', payload.sales || [], '#2271b1');
	lineChart('som-chart-profit', 'Profit', payload.profit || [], '#00a32a');
	lineChart('som-chart-aov', 'AOV', payload.aov || [], '#d63638');

	var byChannel = payload.orders_by_channel || { labels: [], counts: [] };
	var chEl = document.getElementById('som-chart-orders-channel');
	if (chEl) {
		new Chart(chEl, {
			type: 'bar',
			data: {
				labels: byChannel.labels || [],
				datasets: [
					{
						label: 'Orders',
						data: byChannel.counts || [],
						backgroundColor: '#2271b1'
					}
				]
			},
			options: {
				responsive: true,
				plugins: { legend: { display: false } },
				scales: {
					y: {
						beginAtZero: true,
						ticks: { precision: 0 }
					}
				}
			}
		});
	}

	var stock = payload.stock && payload.stock.series ? payload.stock.series : [];
	var stockEl = document.getElementById('som-chart-stock');
	if (stockEl && stock.length) {
		var palette = ['#2271b1', '#00a32a', '#d63638', '#996800', '#3858e9', '#8c5e58'];
		new Chart(stockEl, {
			type: 'line',
			data: {
				labels: labels,
				datasets: stock.map(function (s, i) {
					var color = palette[i % palette.length];
					return {
						label: s.name + (s.unit ? ' (' + s.unit + ')' : ''),
						data: s.values || [],
						borderColor: color,
						backgroundColor: color,
						tension: 0.2,
						pointRadius: 2
					};
				})
			},
			options: {
				responsive: true,
				plugins: { legend: { display: true, position: 'bottom' } },
				scales: { y: { beginAtZero: true } }
			}
		});
	}

	var range = document.getElementById('som-range');
	var custom = document.querySelector('.som-analytics-custom-dates');
	if (range && custom) {
		range.addEventListener('change', function () {
			if (range.value === 'custom') {
				custom.removeAttribute('hidden');
			} else {
				custom.setAttribute('hidden', 'hidden');
			}
		});
	}
})();
