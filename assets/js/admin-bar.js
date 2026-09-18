/**
 * Admin bar live stats.
 */
(function () {
	'use strict';

	var cfg = window.salesbinBar;
	if (!cfg) {
		return;
	}

	function fetchBar() {
		return fetch(cfg.restUrl, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, 'Accept': 'application/json' }
		}).then(function (r) {
			return r.json();
		});
	}

	function miniChart(series) {
		var w = 220, h = 42, n = series.length || 1;
		var max = Math.max.apply(null, series.concat([1]));
		var d = series.map(function (v, i) {
			var x = n === 1 ? w / 2 : (i / (n - 1)) * w;
			var y = h - (v / max) * (h - 4) - 2;
			return (i ? 'L' : 'M') + x + ',' + y;
		}).join(' ');
		return '<svg viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true"><path d="' + d + '" fill="none" stroke="#a78bfa" stroke-width="2"/></svg>';
	}

	function render(data) {
		var stats = document.getElementById('salesbin-ab-stats');
		if (stats) {
			stats.textContent = (data.today_html || '') + ' · ' + (data.today_orders || 0);
		}
		var node = document.getElementById('wp-admin-bar-salesbin-bar');
		if (!node) {
			return;
		}
		var fly = node.querySelector('.salesbin-ab-flyout');
		if (!fly) {
			fly = document.createElement('div');
			fly.className = 'salesbin-ab-flyout';
			fly.hidden = true;
			node.appendChild(fly);
			node.addEventListener('mouseenter', function () {
				fly.hidden = false;
			});
			node.addEventListener('mouseleave', function () {
				fly.hidden = true;
			});
			node.addEventListener('focusin', function () {
				fly.hidden = false;
			});
			node.addEventListener('focusout', function (e) {
				if (!node.contains(e.relatedTarget)) {
					fly.hidden = true;
				}
			});
		}
		var stock = '';
		if (data.show_stock) {
			stock = '<p>' + cfg.i18n.stock + ': ' + (data.low_stock || 0) + '</p>';
		}
		fly.innerHTML = '<p>' + cfg.i18n.sales + ': <strong>' + (data.today_html || '') + '</strong></p>' +
			'<p>' + cfg.i18n.orders + ': ' + (data.today_orders || 0) + '</p>' +
			stock + miniChart(data.series || []) +
			'<p><a href="' + cfg.dashboard + '">' + cfg.i18n.open + '</a></p>';
	}

	function tick() {
		fetchBar().then(function (body) {
			if (body && body.success && body.data) {
				render(body.data);
			}
		}).catch(function () {});
	}

	document.addEventListener('DOMContentLoaded', function () {
		tick();
		var interval = Math.max(30, parseInt(cfg.interval, 10) || 60);
		setInterval(function () {
			if (!document.hidden) {
				tick();
			}
		}, interval * 1000);
	});
})();
