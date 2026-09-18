/**
 * Notifications page (reuses salesbinApp).
 */
(function () {
	'use strict';

	var cfg = window.salesbinApp;
	if (!cfg || !document.getElementById('salesbin-note-list')) {
		return;
	}
	if (document.getElementById('salesbin-app')) {
		return;
	}

	var i18n = cfg.i18n || {};

	function escapeHtml(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function api(path, options) {
		return fetch(cfg.restUrl + path, Object.assign({
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce,
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			}
		}, options || {})).then(function (r) {
			return r.json();
		});
	}

	function render(data) {
		var root = document.getElementById('salesbin-note-list');
		var items = (data && data.items) || [];
		if (!items.length) {
			root.innerHTML = '<div class="salesbin-empty">' + escapeHtml(i18n.emptyNotes) + '</div>';
			return;
		}
		root.innerHTML = '';
		items.forEach(function (n) {
			var node = document.createElement('article');
			node.className = 'salesbin-note' + (n.is_read ? '' : ' is-unread');
			node.innerHTML = '<strong>' + escapeHtml(n.title) + '</strong><p class="salesbin-muted">' + escapeHtml(n.description || '') + '</p>' +
				(n.link ? '<a class="salesbin-link" href="' + escapeHtml(n.link) + '">' + escapeHtml(i18n.view) + '</a> ' : '') +
				(n.is_read ? '' : '<button type="button" class="salesbin-link" data-id="' + escapeHtml(n.id) + '">' + escapeHtml(i18n.markRead) + '</button>');
			root.appendChild(node);
		});
		root.querySelectorAll('button[data-id]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				api('notifications/' + btn.getAttribute('data-id') + '/read', { method: 'POST' }).then(load);
			});
		});
	}

	function load() {
		api('notifications?per_page=50').then(function (body) {
			render(body.data || {});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var all = document.getElementById('salesbin-read-all');
		if (all) {
			all.addEventListener('click', function () {
				api('notifications/read-all', { method: 'POST' }).then(load);
			});
		}
		load();
	});
})();
