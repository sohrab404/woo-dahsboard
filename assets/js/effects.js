/**
 * SalesbinFX — Magic UI inspired micro-interactions in vanilla JS.
 * - countUp: animated number ticker for KPI values
 * - magic cards: cursor-tracking radial glow on cards (CSS consumes --mx/--my)
 * - ripple: soft press feedback on primary buttons
 * All effects respect prefers-reduced-motion.
 */
(function (window, document) {
	'use strict';

	function reducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function countUp(el, target, formatFn) {
		if (!el) {
			return;
		}
		var to = Number(target) || 0;
		var fmt = formatFn || function (n) {
			return String(Math.round(n));
		};
		if (reducedMotion() || to === 0) {
			el.textContent = fmt(to);
			return;
		}
		var duration = 800;
		var start = null;
		function frame(ts) {
			if (start === null) {
				start = ts;
			}
			var p = Math.min(1, (ts - start) / duration);
			var eased = 1 - Math.pow(1 - p, 3);
			el.textContent = fmt(to * eased);
			if (p < 1) {
				window.requestAnimationFrame(frame);
			} else {
				el.textContent = fmt(to);
			}
		}
		window.requestAnimationFrame(frame);
	}

	function bindMagicCards(root) {
		var scope = root || document;
		scope.querySelectorAll('.salesbin-card, .salesbin-kpi, .salesbin-login-card').forEach(function (card) {
			if (card.dataset.sbMagicBound) {
				return;
			}
			card.dataset.sbMagicBound = '1';
			card.classList.add('sb-magic');
			card.addEventListener('mousemove', function (e) {
				var r = card.getBoundingClientRect();
				card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(2) + '%');
				card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(2) + '%');
			});
		});
	}

	function bindRipples(root) {
		var scope = root || document;
		scope.querySelectorAll('.salesbin-btn--primary').forEach(function (btn) {
			if (btn.dataset.sbRippleBound) {
				return;
			}
			btn.dataset.sbRippleBound = '1';
			btn.addEventListener('click', function (e) {
				if (reducedMotion()) {
					return;
				}
				var r = btn.getBoundingClientRect();
				var d = Math.max(r.width, r.height) * 2;
				var s = document.createElement('span');
				s.className = 'sb-ripple';
				s.style.width = s.style.height = d + 'px';
				s.style.left = (e.clientX - r.left - d / 2) + 'px';
				s.style.top = (e.clientY - r.top - d / 2) + 'px';
				btn.appendChild(s);
				window.setTimeout(function () {
					s.remove();
				}, 650);
			});
		});
	}

	function enhance(root) {
		bindMagicCards(root);
		bindRipples(root);
	}

	window.SalesbinFX = {
		countUp: countUp,
		enhance: enhance,
		reducedMotion: reducedMotion
	};
})(window, document);
