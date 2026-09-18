/**
 * Salesbin theme engine — 6 color themes × dark/light mode.
 * Applies [data-sb-theme] / [data-sb-mode] on <body>; CSS variables cascade from there.
 * Persists the visitor choice in localStorage; defaults come from plugin settings.
 */
(function (window, document) {
	'use strict';

	var THEMES = [
		{ value: 'woodesh', label: 'بنفش وودش', dot: '#8b5cf6' },
		{ value: 'ocean', label: 'اقیانوس', dot: '#0ea5e9' },
		{ value: 'emerald', label: 'زمرد', dot: '#10b981' },
		{ value: 'rose', label: 'رز', dot: '#f43f5e' },
		{ value: 'sunset', label: 'غروب', dot: '#f59e0b' },
		{ value: 'graphite', label: 'گرافیت', dot: '#9ca3af' }
	];

	var LS_THEME = 'salesbinTheme';
	var LS_MODE = 'salesbinMode';

	function validTheme(t) {
		for (var i = 0; i < THEMES.length; i++) {
			if (THEMES[i].value === t) {
				return true;
			}
		}
		return false;
	}

	function readLS(key) {
		try {
			return window.localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function writeLS(key, value) {
		try {
			window.localStorage.setItem(key, value);
		} catch (e) {
			/* private mode */
		}
	}

	function defaults() {
		var cfg = window.salesbinApp || window.salesbinSettings || window.salesbinLoginCfg || {};
		return {
			theme: validTheme(cfg.defaultTheme) ? cfg.defaultTheme : (validTheme(cfg.theme) ? cfg.theme : 'woodesh'),
			mode: (cfg.defaultMode === 'light' || cfg.mode === 'light') ? 'light' : 'dark'
		};
	}

	function currentTheme() {
		var ls = readLS(LS_THEME);
		return validTheme(ls) ? ls : defaults().theme;
	}

	function currentMode() {
		var ls = readLS(LS_MODE);
		return ls === 'light' || ls === 'dark' ? ls : defaults().mode;
	}

	function apply(theme, mode) {
		if (!validTheme(theme)) {
			theme = currentTheme();
		}
		if (mode !== 'dark' && mode !== 'light') {
			mode = currentMode();
		}
		writeLS(LS_THEME, theme);
		writeLS(LS_MODE, mode);
		document.body.setAttribute('data-sb-theme', theme);
		document.body.setAttribute('data-sb-mode', mode);
		syncControls(theme, mode);
		document.dispatchEvent(new CustomEvent('salesbin:theme-changed', {
			detail: { theme: theme, mode: mode }
		}));
	}

	function setTheme(theme) {
		apply(theme, currentMode());
	}

	function setMode(mode) {
		apply(currentTheme(), mode);
	}

	function toggleMode() {
		setMode(currentMode() === 'dark' ? 'light' : 'dark');
	}

	function fillThemeSelect(sel, current) {
		if (!sel || sel.options.length) {
			return;
		}
		THEMES.forEach(function (t) {
			var o = document.createElement('option');
			o.value = t.value;
			o.textContent = t.label;
			sel.appendChild(o);
		});
		sel.value = current;
	}

	function syncControls(theme, mode) {
		document.querySelectorAll('[data-sb-theme-control]').forEach(function (sel) {
			if (sel.tagName === 'SELECT') {
				fillThemeSelect(sel, theme);
				sel.value = theme;
			}
		});
		document.querySelectorAll('[data-sb-mode-control]').forEach(function (sel) {
			if (sel.tagName === 'SELECT') {
				sel.value = mode;
			}
		});
		document.querySelectorAll('[data-sb-mode-toggle]').forEach(function (btn) {
			btn.textContent = mode === 'dark' ? '☀️' : '🌙';
			btn.setAttribute('aria-label', mode === 'dark' ? 'حالت روز' : 'حالت شب');
			btn.title = mode === 'dark' ? 'حالت روز' : 'حالت شب';
		});
	}

	function bind() {
		document.querySelectorAll('[data-sb-theme-control]').forEach(function (sel) {
			fillThemeSelect(sel, currentTheme());
			sel.addEventListener('change', function () {
				setTheme(sel.value);
			});
		});
		document.querySelectorAll('[data-sb-mode-control]').forEach(function (sel) {
			sel.value = currentMode();
			sel.addEventListener('change', function () {
				setMode(sel.value);
			});
		});
		document.querySelectorAll('[data-sb-mode-toggle]').forEach(function (btn) {
			btn.addEventListener('click', toggleMode);
		});
	}

	function init() {
		var hasSurface = document.querySelector('.salesbin-app, .salesbin-settings, .salesbin-login-page');
		if (!hasSurface) {
			return;
		}
		apply(currentTheme(), currentMode());
		bind();
	}

	window.SalesbinTheme = {
		themes: THEMES,
		theme: currentTheme,
		mode: currentMode,
		apply: apply,
		setTheme: setTheme,
		setMode: setMode,
		toggleMode: toggleMode
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
