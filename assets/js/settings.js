/**
 * Salesbin settings page — tabs + live theme/mode preview.
 * Saving still goes through the WP Settings API; preview is instant, persistence is server-side.
 */
(function () {
	'use strict';

	var cfg = window.salesbinSettings || {};

	function initTabs() {
		var tabs = document.querySelectorAll('.sb-tab');
		var panels = document.querySelectorAll('.sb-panel');
		if (!tabs.length) {
			return;
		}
		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				tabs.forEach(function (t) {
					t.classList.remove('is-active');
					t.setAttribute('aria-selected', 'false');
				});
				tab.classList.add('is-active');
				tab.setAttribute('aria-selected', 'true');
				panels.forEach(function (panel) {
					var match = panel.getAttribute('data-panel') === tab.getAttribute('data-tab');
					panel.hidden = !match;
					panel.classList.toggle('is-active', match);
				});
			});
		});
	}

	function initSwatches() {
		var swatches = document.querySelectorAll('.sb-swatch');
		swatches.forEach(function (sw) {
			sw.addEventListener('click', function () {
				var theme = sw.getAttribute('data-swatch');
				if (window.SalesbinTheme && theme) {
					SalesbinTheme.setTheme(theme);
				}
			});
		});
		document.addEventListener('salesbin:theme-changed', function (e) {
			swatches.forEach(function (sw) {
				sw.classList.toggle('is-active', sw.getAttribute('data-swatch') === e.detail.theme);
			});
			var select = document.getElementById('salesbin_theme');
			if (select) {
				select.value = e.detail.theme;
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initTabs();
		initSwatches();
		if (window.SalesbinTheme) {
			SalesbinTheme.apply(SalesbinTheme.theme(), SalesbinTheme.mode());
		}
	});
})();
