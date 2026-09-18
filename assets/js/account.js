/**
 * Woodesh Account Panel — drawer, avatar menu, mode toggle, FX.
 * Read-only UI glue; all data is rendered server-side by WooCommerce.
 */
(function (window, document) {
	'use strict';

	var cfg = window.salesbinAccount || {};

	function faNum(n) {
		return String(n).replace(/\d/g, function (d) {
			return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d);
		});
	}

	function groupDigits(n) {
		return String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	function init(root) {
		// Mobile drawer (floating button + scrim)
		var burger = root.querySelector('[data-sb-account-burger]');
		var scrim = root.querySelector('[data-sb-account-scrim]');
		function setDrawer(open) {
			root.classList.toggle('is-drawer-open', open);
			if (scrim) { scrim.hidden = !open; }
			if (burger) {
				burger.setAttribute('aria-expanded', open ? 'true' : 'false');
				burger.classList.toggle('is-active', open);
			}
		}
		if (burger) {
			burger.addEventListener('click', function () {
				setDrawer(!root.classList.contains('is-drawer-open'));
			});
		}
		if (scrim) {
			scrim.addEventListener('click', function () { setDrawer(false); });
		}

		// Close the drawer with Escape as well
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { setDrawer(false); }
		});

		// Avatar menu
		var userBtn = root.querySelector('[data-sb-account-user]');
		var userMenu = root.querySelector('.sb-account__user-menu');
		if (userBtn && userMenu) {
			userBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				var open = userMenu.hidden;
				userMenu.hidden = !open;
				userBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
			document.addEventListener('click', function (e) {
				if (!root.contains(e.target)) { userMenu.hidden = true; }
			});
		}

		// Visitor dark/light toggle (persisted per browser; default from settings)
		var modeBtn = root.querySelector('[data-sb-account-mode]');
		function applyMode(mode) {
			if (mode !== 'dark' && mode !== 'light') { mode = cfg.mode || 'dark'; }
			root.setAttribute('data-sb-mode', mode);
			if (modeBtn) { modeBtn.textContent = mode === 'dark' ? '☀️' : '🌙'; }
			try { window.localStorage.setItem('salesbinAccountMode', mode); } catch (e) { /* private mode */ }
		}
		if (modeBtn) {
			var savedMode = null;
			try { savedMode = window.localStorage.getItem('salesbinAccountMode'); } catch (e) { /* noop */ }
			if (savedMode) { applyMode(savedMode); }
			modeBtn.addEventListener('click', function () {
				applyMode(root.getAttribute('data-sb-mode') === 'dark' ? 'light' : 'dark');
			});
		}

		// Animated counters (Money format uses the currency symbol passed by WC)
		var moneyWrap = root.querySelector('[data-sb-money]');
		root.querySelectorAll('[data-sb-count]').forEach(function (el) {
			var target = Number(el.getAttribute('data-sb-count')) || 0;
			var isMoney = !!(moneyWrap && moneyWrap.contains(el));
			var fmt = function (n) {
				return faNum(groupDigits(n)) + (isMoney ? ' ' + (cfg.currency || '') : '');
			};
			el.textContent = fmt(0);
			if (window.SalesbinFX) {
				SalesbinFX.countUp(el, target, fmt);
			} else {
				el.textContent = fmt(target);
			}
		});

		if (window.SalesbinFX) {
			SalesbinFX.enhance(root);
		}
	}

	function boot() {
		var root = document.querySelector('.sb-account');
		if (root) { init(root); }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window, document);
