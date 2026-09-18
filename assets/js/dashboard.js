/**
 * Salesbin SPA dashboard.
 */
(function () {
	'use strict';

	var cfg = window.salesbinApp || {};
	var i18n = cfg.i18n || {};
	var state = {
		range: cfg.defaultRange || '30d',
		start: '',
		end: '',
		status: 'sales-default',
		page: 1,
		perPage: cfg.perPage || 10,
		metric: 'sales',
		chartType: cfg.chartType || 'line',
		productSort: 'revenue',
		heatMetric: 'orders',
		orderby: 'date',
		order: 'DESC'
	};

	function $(id) {
		return document.getElementById(id);
	}

	function el(tag, cls, html) {
		var node = document.createElement(tag);
		if (cls) {
			node.className = cls;
		}
		if (html !== undefined) {
			node.innerHTML = html;
		}
		return node;
	}

	function escapeHtml(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function faNum(n, digits) {
		var x = Number(n);
		if (isNaN(x)) {
			return '۰';
		}
		return x.toLocaleString('fa-IR', {
			maximumFractionDigits: digits == null ? 1 : digits
		});
	}

	function money(html, raw) {
		if (html) {
			return escapeHtml(html);
		}
		var sym = (cfg.currency && cfg.currency.symbol) ? cfg.currency.symbol : '';
		return escapeHtml(faNum(raw, 0) + ' ' + sym);
	}

	function params(extra) {
		var q = new URLSearchParams();
		if (state.range === 'custom' && state.start && state.end) {
			q.set('range', 'custom');
			q.set('start', state.start);
			q.set('end', state.end);
		} else {
			q.set('range', state.range);
		}
		if (state.status && state.status !== 'sales-default' && state.status !== 'all') {
			q.set('status', state.status);
		} else if (state.status === 'all') {
			q.set('status', 'all');
		}
		Object.keys(extra || {}).forEach(function (k) {
			if (extra[k] !== undefined && extra[k] !== null && extra[k] !== '') {
				q.set(k, extra[k]);
			}
		});
		return q.toString();
	}

	function api(path, extra, options) {
		var url = cfg.restUrl + path.replace(/^\//, '');
		var qs = params(extra);
		if (qs) {
			url += (url.indexOf('?') >= 0 ? '&' : '?') + qs;
		}
		return fetch(url, Object.assign({
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce,
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			}
		}, options || {})).then(function (res) {
			return res.json().then(function (body) {
				if (!res.ok || (body && body.success === false)) {
					var err = new Error((body && (body.message || (body.data && body.data.message))) || i18n.error);
					throw err;
				}
				return body;
			});
		});
	}

	function skeleton(target) {
		target.innerHTML = '<div class="salesbin-skel" aria-busy="true">' + escapeHtml(i18n.loading) + '</div>';
	}

	function errorBox(target, retryFn) {
		target.innerHTML = '';
		var box = el('div', 'salesbin-error');
		box.appendChild(document.createTextNode(i18n.error));
		var btn = el('button', 'salesbin-btn');
		btn.type = 'button';
		btn.textContent = i18n.retry;
		btn.addEventListener('click', retryFn);
		box.appendChild(document.createElement('br'));
		box.appendChild(btn);
		target.appendChild(box);
	}

	function emptyBox(target, msg) {
		target.innerHTML = '<div class="salesbin-empty">' + escapeHtml(msg) + '</div>';
	}

	function growthLabel(kpi) {
		if (!kpi) {
			return { text: i18n.dash, cls: '' };
		}
		if (kpi.trend === 'new') {
			return { text: i18n.new, cls: 'is-new' };
		}
		if (kpi.growth === null || kpi.growth === undefined) {
			return { text: i18n.dash, cls: '' };
		}
		var sign = kpi.growth > 0 ? '+' : '';
		return {
			text: sign + faNum(kpi.growth, 1) + '%',
			cls: kpi.growth >= 0 ? 'is-up' : 'is-down'
		};
	}

	function renderKpis(payload) {
		var root = $('salesbin-kpis');
		if (!root) {
			return;
		}
		var data = payload.data || payload;
		var k = (data.kpis) ? data.kpis : (data.stats && data.stats.kpis) || {};
		var symbol = (cfg.currency && cfg.currency.symbol) ? cfg.currency.symbol : '';
		var items = [
			{ id: 'net_sales', label: i18n.netSales, money: true, hint: i18n.netHint, featured: true },
			{ id: 'gross_sales', label: i18n.grossSales, money: true },
			{ id: 'orders', label: i18n.orders, money: false },
			{ id: 'customers', label: i18n.customers, money: false },
			{ id: 'new_customers', label: i18n.newCustomers, money: false },
			{ id: 'aov', label: i18n.aov, money: true },
			{ id: 'items', label: i18n.items, money: false },
			{ id: 'products', label: i18n.products, money: false },
			{ id: 'low_stock', label: i18n.lowStock, money: false }
		];
		root.innerHTML = '';
		items.forEach(function (item) {
			var kpi = k[item.id] || { current: 0, previous: 0, growth: null, trend: 'empty' };
			var g = growthLabel(kpi);
			var card = el('article', 'salesbin-kpi' + (item.featured ? ' salesbin-kpi--featured' : ''));
			if (item.hint) {
				card.title = item.hint;
			}
			var val = item.money
				? escapeHtml(faNum(kpi.current, 0)) + ' <span class="salesbin-muted">' + escapeHtml(symbol) + '</span>'
				: escapeHtml(faNum(kpi.current, 0));
			var prev = item.money
				? escapeHtml(faNum(kpi.previous, 0)) + ' <span class="salesbin-muted">' + escapeHtml(symbol) + '</span>'
				: escapeHtml(faNum(kpi.previous, 0));
			card.innerHTML =
				'<p class="salesbin-kpi__label">' + escapeHtml(item.label) + '</p>' +
				'<p class="salesbin-kpi__value">' + val + '</p>' +
				'<div class="salesbin-kpi__meta"><span class="salesbin-trend ' + g.cls + '">' + escapeHtml(g.text) + '</span>' +
				'<span class="salesbin-muted">' + escapeHtml(i18n.previousPeriod) + ': ' + prev + '</span></div>';
			root.appendChild(card);
			var valueNode = card.querySelector('.salesbin-kpi__value');
			if (valueNode && window.SalesbinFX) {
				SalesbinFX.countUp(valueNode, kpi.current, function (n) {
					return faNum(n, 0) + (item.money ? ' ' + symbol : '');
				});
			}
		});
		if (window.SalesbinFX) {
			SalesbinFX.enhance(root);
		}
	}

	function loadStats() {
		var root = $('salesbin-kpis');
		skeleton(root);
		return api('stats').then(function (body) {
			renderKpis(body);
		}).catch(function () {
			errorBox(root, loadStats);
		});
	}

	function paintChart(body) {
		var root = $('salesbin-chart');
		if (!root) {
			return;
		}
		var series = (body.data && body.data.series) || [];
		if (window.SalesbinCharts) {
			window.SalesbinCharts.render(root, series, state.metric, state.chartType);
		}
	}

	function loadChart() {
		var root = $('salesbin-chart');
		if (!root) {
			return;
		}
		skeleton(root);
		var extra = {};
		if (state.status === 'all') {
			extra.status = 'all';
		}
		return api('sales-chart', extra).then(paintChart).catch(function () {
			errorBox(root, loadChart);
		});
	}

	function paintOrders(body) {
		var root = $('salesbin-orders');
		if (!root) {
			return;
		}
		var data = body.data || {};
		var rows = data.orders || [];
		if (!rows.length) {
			emptyBox(root, i18n.emptyOrders);
			return;
		}
		var html = '<table class="salesbin-table"><thead><tr>' +
			'<th><button type="button" class="salesbin-link" data-sort="id">' + escapeHtml(i18n.orderNumber) + '</button></th>' +
			'<th>' + escapeHtml(i18n.customer) + '</th>' +
			'<th><button type="button" class="salesbin-link" data-sort="total">' + escapeHtml(i18n.amount) + '</button></th>' +
			'<th>' + escapeHtml(i18n.status) + '</th>' +
			'<th><button type="button" class="salesbin-link" data-sort="date">' + escapeHtml(i18n.date) + '</button></th>' +
			'<th>' + escapeHtml(i18n.actions) + '</th></tr></thead><tbody>';
		rows.forEach(function (o) {
			html += '<tr>' +
				'<td>' + escapeHtml(o.number) + '</td>' +
				'<td>' + escapeHtml(o.customer) + '</td>' +
				'<td>' + escapeHtml(o.total_html || faNum(o.total, 0)) + '</td>' +
				'<td><span class="salesbin-status is-' + escapeHtml(o.status) + '">' + escapeHtml(o.status_label) + '</span></td>' +
				'<td>' + escapeHtml(o.date) + '</td>' +
				'<td><a class="salesbin-link" href="' + escapeHtml(o.edit_url) + '">' + escapeHtml(i18n.view) + '</a></td></tr>';
		});
		html += '</tbody></table>';
		html += '<div class="salesbin-pager">' +
			'<button type="button" class="salesbin-btn" id="sb-prev"' + (state.page <= 1 ? ' disabled' : '') + '>' + escapeHtml(i18n.prev) + '</button>' +
			'<span class="salesbin-muted">' + faNum(state.page, 0) + ' / ' + faNum(data.pages || 1, 0) + '</span>' +
			'<button type="button" class="salesbin-btn" id="sb-next"' + (state.page >= (data.pages || 1) ? ' disabled' : '') + '>' + escapeHtml(i18n.next) + '</button></div>';
		root.innerHTML = html;
		root.querySelectorAll('[data-sort]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var key = btn.getAttribute('data-sort');
				if (state.orderby === key) {
					state.order = state.order === 'DESC' ? 'ASC' : 'DESC';
				} else {
					state.orderby = key;
					state.order = 'DESC';
				}
				loadOrders();
			});
		});
		var prev = $('sb-prev');
		var next = $('sb-next');
		if (prev) {
			prev.addEventListener('click', function () {
				state.page = Math.max(1, state.page - 1);
				loadOrders();
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				state.page += 1;
				loadOrders();
			});
		}
	}

	function loadOrders() {
		var root = $('salesbin-orders');
		if (!root) {
			return;
		}
		skeleton(root);
		var extra = {
			page: state.page,
			per_page: state.perPage,
			orderby: state.orderby,
			order: state.order
		};
		if (state.status === 'sales-default') {
			extra.status = '';
		} else {
			extra.status = state.status;
		}
		return api('orders', extra).then(paintOrders).catch(function () {
			errorBox(root, loadOrders);
		});
	}

	function renderList(root, items, mapper, emptyMsg) {
		if (!items || !items.length) {
			emptyBox(root, emptyMsg);
			return;
		}
		root.innerHTML = '';
		items.forEach(function (item) {
			root.appendChild(mapper(item));
		});
	}

	function paintProducts(body) {
		var root = $('salesbin-products');
		if (!root) {
			return;
		}
		renderList(root, (body.data && body.data.items) || [], function (p) {
			var row = el('div', 'salesbin-row');
			row.innerHTML = '<div><strong>' + escapeHtml(p.name) + '</strong><div class="salesbin-muted">' + escapeHtml(i18n.qty) + ' ' + faNum(p.qty, 0) + ' — ' + escapeHtml(i18n.share) + ' ' + faNum(p.share, 1) + '٪</div><div class="salesbin-bar"><span style="width:' + Math.min(100, p.share) + '%"></span></div></div><div>' + escapeHtml(p.revenue_html || faNum(p.revenue, 0)) + '</div>';
			return row;
		}, i18n.emptyProducts);
	}

	function loadProducts() {
		var root = $('salesbin-products');
		if (!root) {
			return;
		}
		skeleton(root);
		return api('products', { sort: state.productSort }).then(paintProducts).catch(function () {
			errorBox(root, loadProducts);
		});
	}

	function paintCustomers(body) {
		var root = $('salesbin-customers');
		if (!root) {
			return;
		}
		renderList(root, (body.data && body.data.items) || [], function (c) {
			var row = el('div', 'salesbin-row');
			row.innerHTML = '<div><strong>' + escapeHtml(c.name) + '</strong><div class="salesbin-muted">' + escapeHtml(i18n.orders) + ' ' + faNum(c.orders, 0) + ' — ' + escapeHtml(i18n.avg) + ' ' + escapeHtml(c.aov_html || '') + '</div></div><div>' + escapeHtml(c.revenue_html || '') + '</div>';
			return row;
		}, i18n.emptyCustomers);
	}

	function loadCustomers() {
		var root = $('salesbin-customers');
		if (!root) {
			return;
		}
		skeleton(root);
		return api('customers').then(paintCustomers).catch(function () {
			errorBox(root, loadCustomers);
		});
	}

	function paintCats(body) {
		var root = $('salesbin-cats');
		if (!root) {
			return;
		}
		var items = (body.data && body.data.items) || [];
		renderList(root, items, function (c) {
			var row = el('div', 'salesbin-row');
			row.innerHTML = '<div><strong>' + escapeHtml(c.name) + '</strong><div class="salesbin-bar"><span style="width:' + Math.min(100, c.share) + '%"></span></div><div class="salesbin-muted">' + faNum(c.share, 1) + '٪</div></div><div>' + escapeHtml(c.revenue_html || '') + '</div>';
			return row;
		}, i18n.emptyCats);
	}

	function loadCats() {
		var root = $('salesbin-cats');
		if (!root) {
			return;
		}
		skeleton(root);
		return api('categories').then(paintCats).catch(function () {
			errorBox(root, loadCats);
		});
	}

	function paintPay(body) {
		var root = $('salesbin-pay');
		if (!root) {
			return;
		}
		renderList(root, (body.data && body.data.items) || [], function (p) {
			var row = el('div', 'salesbin-row');
			row.innerHTML = '<div><strong>' + escapeHtml(p.label) + '</strong><div class="salesbin-muted">' + escapeHtml(i18n.orders) + ' ' + faNum(p.orders, 0) + ' — ' + faNum(p.share, 1) + '٪</div></div><div>' + escapeHtml(p.revenue_html || '') + '</div>';
			return row;
		}, i18n.emptyPay);
	}

	function loadPay() {
		var root = $('salesbin-pay');
		if (!root) {
			return;
		}
		skeleton(root);
		return api('payments').then(paintPay).catch(function () {
			errorBox(root, loadPay);
		});
	}

	function accentRgb() {
		var v = getComputedStyle(document.body).getPropertyValue('--sb-accent-rgb');
		var s = (v || '').trim();
		return s || '139, 92, 246';
	}

	function paintHeat(body) {
		var root = $('salesbin-heat');
		if (!root) {
			return;
		}
		var points = (body.data && body.data.points) || [];
		var rgb = accentRgb();
		root.innerHTML = '';
		points.forEach(function (p) {
			var cell = el('div', 'salesbin-heat__cell');
			var alpha = 0.08 + (p.intensity || 0) * 0.55;
			cell.style.background = 'rgba(' + rgb + ',' + alpha + ')';
			cell.innerHTML = '<strong>' + escapeHtml(p.label) + '</strong><div>' + (state.heatMetric === 'sales' ? escapeHtml(p.sales_html || '') : faNum(p.orders, 0)) + '</div>';
			root.appendChild(cell);
		});
	}

	function loadHeat() {
		var root = $('salesbin-heat');
		if (!root) {
			return;
		}
		skeleton(root);
		return api('heatmap', { metric: state.heatMetric }).then(paintHeat).catch(function () {
			errorBox(root, loadHeat);
		});
	}

	function paintStock(body) {
		var root = $('salesbin-stock');
		if (!root) {
			return;
		}
		renderList(root, (body.data && body.data.items) || [], function (s) {
			var row = el('div', 'salesbin-row');
			var name = s.edit_url ? '<a class="salesbin-link" href="' + escapeHtml(s.edit_url) + '">' + escapeHtml(s.name) + '</a>' : escapeHtml(s.name);
			row.innerHTML = '<div>' + name + '</div><div class="salesbin-trend is-warn">' + escapeHtml(i18n.stock) + ' ' + faNum(s.stock, 0) + '</div>';
			return row;
		}, i18n.emptyStock);
	}

	function loadStock() {
		var root = $('salesbin-stock');
		if (!root) {
			return;
		}
		skeleton(root);
		return api('stock').then(paintStock).catch(function () {
			errorBox(root, loadStock);
		});
	}

	function paintGoal(body) {
		var root = $('salesbin-goal');
		if (!root) {
			return;
		}
		var g = body.data || {};
		if (g.goal_zero) {
			root.innerHTML = '<div class="salesbin-empty">' + escapeHtml(i18n.goalZero) + '</div><p>' + escapeHtml(i18n.todaySales) + ': ' + escapeHtml(g.sales_html || '') + '</p>';
			return;
		}
		root.innerHTML =
			'<p>' + escapeHtml(i18n.todaySales) + ': <strong>' + escapeHtml(g.sales_html || '') + '</strong></p>' +
			'<p>' + escapeHtml(i18n.dailyGoal) + ': ' + escapeHtml(g.goal_html || '') + '</p>' +
			'<div class="salesbin-progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(g.bar) + '"><span style="width:' + escapeHtml(g.bar) + '%"></span></div>' +
			'<p>' + faNum(g.progress, 1) + '٪ — ' + escapeHtml(i18n.remaining) + ': ' + escapeHtml(g.remaining_html || '') + '</p>';
	}

	function loadGoal() {
		var root = $('salesbin-goal');
		if (!root) {
			return;
		}
		skeleton(root);
		return api('goal').then(paintGoal).catch(function () {
			errorBox(root, loadGoal);
		});
	}

	function renderNotes(list) {
		var root = $('salesbin-note-list');
		if (!root) {
			return;
		}
		var items = (list && list.items) || [];
		if (!items.length) {
			emptyBox(root, i18n.emptyNotes);
			return;
		}
		root.innerHTML = '';
		items.forEach(function (n) {
			var node = el('article', 'salesbin-note' + (n.is_read ? '' : ' is-unread'));
			node.innerHTML = '<div class="salesbin-note__title"><strong>' + escapeHtml(n.title) + '</strong></div>' +
				'<p class="salesbin-muted">' + escapeHtml(n.description || '') + '</p>' +
				'<p class="salesbin-muted">' + escapeHtml(n.created_at) + '</p>' +
				(n.link ? '<a class="salesbin-link" href="' + escapeHtml(n.link) + '">' + escapeHtml(i18n.view) + '</a> ' : '') +
				(n.is_read || n.type === 'wp_update' ? '' : '<button type="button" class="salesbin-link" data-id="' + escapeHtml(n.id) + '">' + escapeHtml(i18n.markRead) + '</button>');
			root.appendChild(node);
		});
		root.querySelectorAll('button[data-id]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				api('notifications/' + btn.getAttribute('data-id') + '/read', {}, { method: 'POST' }).then(loadNotes);
			});
		});
	}

	function paintNotes(body) {
		var data = body.data || {};
		var wp = cfg.wpAlerts || [];
		data.items = wp.concat(data.items || []);
		data.unread_count = (data.unread_count || 0) + wp.length;
		renderNotes(data);
		var count = $('salesbin-bell-count');
		if (count) {
			var n = data.unread_count || 0;
			count.hidden = n < 1;
			count.textContent = faNum(n, 0);
		}
	}

	function loadNotes() {
		return api('notifications', { per_page: 12 }).then(paintNotes).catch(function () {
			var root = $('salesbin-note-list');
			if (root) {
				errorBox(root, loadNotes);
			}
		});
	}

	function isHome() {
		var app = $('salesbin-app');
		return cfg.layout === 'home' || (app && app.getAttribute('data-layout') === 'home');
	}

	function refreshAll() {
		state.page = 1;
		var stamp = $('salesbin-updated');
		var home = isHome();
		var homeSections = ['stats', 'chart', 'orders', 'products', 'notifications'];
		var loaders = {
			stats: loadStats,
			chart: loadChart,
			orders: loadOrders,
			products: loadProducts,
			customers: loadCustomers,
			categories: loadCats,
			payments: loadPay,
			heatmap: loadHeat,
			stock: loadStock,
			goal: loadGoal,
			notifications: loadNotes
		};
		var painters = {
			stats: renderKpis,
			chart: paintChart,
			orders: paintOrders,
			products: paintProducts,
			customers: paintCustomers,
			categories: paintCats,
			payments: paintPay,
			heatmap: paintHeat,
			stock: paintStock,
			goal: paintGoal,
			notifications: paintNotes
		};

		function visible(key) {
			return !home || homeSections.indexOf(key) > -1;
		}

		function runFallback() {
			Object.keys(loaders).forEach(function (key) {
				if (visible(key)) {
					loaders[key]();
				}
			});
		}

		function stampTime() {
			if (stamp) {
				stamp.textContent = (i18n.lastUpdated || '') + ': ' + new Date().toLocaleString('fa-IR');
			}
		}

		// One combined request paints every widget; each section falls back to
		// its own endpoint only when missing or marked failed server-side.
		return api('bootstrap', {
			layout: home ? 'home' : 'full',
			page: state.page,
			per_page: state.perPage,
			sort: state.productSort,
			metric: state.heatMetric
		}).then(function (body) {
			var d = (body && body.data) || {};
			Object.keys(painters).forEach(function (key) {
				if (!visible(key)) {
					return;
				}
				if (!d[key] || d[key].error) {
					loaders[key]();
					return;
				}
				painters[key]({ data: d[key] });
			});
			stampTime();
		}).catch(function () {
			runFallback();
			stampTime();
		});
	}

	function bindRange() {
		document.querySelectorAll('[data-range]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				document.querySelectorAll('[data-range]').forEach(function (b) {
					b.classList.remove('is-active');
				});
				btn.classList.add('is-active');
				state.range = btn.getAttribute('data-range');
				var custom = $('salesbin-custom');
				if (custom) {
					custom.hidden = state.range !== 'custom';
				}
				if (state.range !== 'custom') {
					refreshAll();
				}
			});
		});
		var apply = $('salesbin-apply-custom');
		if (apply) {
			apply.addEventListener('click', function () {
				var jalali = window.SalesbinJalali;
				state.start = jalali ? jalali.parseJalaliInput($('salesbin-start').value) : $('salesbin-start').value;
				state.end = jalali ? jalali.parseJalaliInput($('salesbin-end').value) : $('salesbin-end').value;
				if (!state.start || !state.end) {
					return;
				}
				state.range = 'custom';
				refreshAll();
			});
		}
	}

	function fillSelect(id, options, current) {
		var sel = $(id);
		if (!sel) {
			return;
		}
		sel.innerHTML = '';
		options.forEach(function (opt) {
			var o = document.createElement('option');
			o.value = opt.value;
			o.textContent = opt.label;
			if (opt.value === current) {
				o.selected = true;
			}
			sel.appendChild(o);
		});
	}

	function exportUrl(action) {
		var u = new URL(cfg.exportUrl, window.location.origin);
		u.searchParams.set('action', action);
		u.searchParams.set('_wpnonce', cfg.exportNonce);
		u.searchParams.set('range', state.range);
		if (state.range === 'custom') {
			u.searchParams.set('start', state.start);
			u.searchParams.set('end', state.end);
		}
		if (state.status && state.status !== 'sales-default') {
			u.searchParams.set('status', state.status);
		}
		return u.toString();
	}

	function init() {
		if (!$('salesbin-app')) {
			return;
		}
		fillSelect('salesbin-metric', [
			{ value: 'sales', label: i18n.metricSales },
			{ value: 'orders', label: i18n.metricOrders },
			{ value: 'aov', label: i18n.metricAov }
		], state.metric);
		fillSelect('salesbin-chart-type', [
			{ value: 'line', label: i18n.chartLine },
			{ value: 'bar', label: i18n.chartBar }
		], state.chartType);
		var statuses = [{ value: 'sales-default', label: i18n.metricSales + ' (' + (i18n.netSales || '') + ')' }].concat(
			(cfg.statuses || []).map(function (s) {
				return { value: s.slug, label: s.label };
			})
		);
		fillSelect('salesbin-status', statuses, 'sales-default');
		fillSelect('salesbin-per-page', [10, 20, 50, 100].map(function (n) {
			return { value: String(n), label: String(n) };
		}), String(state.perPage));
		fillSelect('salesbin-product-sort', [
			{ value: 'revenue', label: i18n.sortRevenue },
			{ value: 'qty', label: i18n.sortQty }
		], state.productSort);
		fillSelect('salesbin-heat-metric', [
			{ value: 'orders', label: i18n.metricOrders },
			{ value: 'sales', label: i18n.metricSales }
		], state.heatMetric);

		['salesbin-metric', 'salesbin-chart-type', 'salesbin-status', 'salesbin-per-page', 'salesbin-product-sort', 'salesbin-heat-metric'].forEach(function (id) {
			var sel = $(id);
			if (!sel) {
				return;
			}
			sel.addEventListener('change', function () {
				if (id === 'salesbin-metric') {
					state.metric = sel.value;
					loadChart();
				} else if (id === 'salesbin-chart-type') {
					state.chartType = sel.value;
					loadChart();
				} else if (id === 'salesbin-status') {
					state.status = sel.value;
					refreshAll();
				} else if (id === 'salesbin-per-page') {
					state.perPage = parseInt(sel.value, 10);
					state.page = 1;
					loadOrders();
				} else if (id === 'salesbin-product-sort') {
					state.productSort = sel.value;
					loadProducts();
				} else if (id === 'salesbin-heat-metric') {
					state.heatMetric = sel.value;
					loadHeat();
				}
			});
		});

		bindRange();
		var def = document.querySelector('[data-range="' + state.range + '"]');
		if (def) {
			document.querySelectorAll('[data-range]').forEach(function (b) {
				b.classList.remove('is-active');
			});
			def.classList.add('is-active');
		}

		document.addEventListener('salesbin:theme-changed', function () {
			if ($('salesbin-chart')) {
				loadChart();
			}
			if ($('salesbin-heat')) {
				loadHeat();
			}
		});

		$('salesbin-refresh') && $('salesbin-refresh').addEventListener('click', refreshAll);
		$('salesbin-bell') && $('salesbin-bell').addEventListener('click', function () {
			var d = $('salesbin-drawer');
			if (!d) {
				return;
			}
			d.hidden = !d.hidden;
			$('salesbin-bell').setAttribute('aria-expanded', d.hidden ? 'false' : 'true');
		});
		$('salesbin-read-all') && $('salesbin-read-all').addEventListener('click', function () {
			api('notifications/read-all', {}, { method: 'POST' }).then(loadNotes);
		});
		$('salesbin-export-orders') && $('salesbin-export-orders').addEventListener('click', function () {
			window.location.href = exportUrl('salesbin_export_orders');
		});
		$('salesbin-export-products') && $('salesbin-export-products').addEventListener('click', function () {
			window.location.href = exportUrl('salesbin_export_products');
		});

		refreshAll();

		if (cfg.autoRefresh && cfg.autoRefresh > 0) {
			setInterval(function () {
				if (!document.hidden) {
					refreshAll();
				}
			}, cfg.autoRefresh * 1000);
		}
	}

	document.addEventListener('DOMContentLoaded', init);
})();
