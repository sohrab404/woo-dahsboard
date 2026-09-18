/**
 * Lightweight SVG charts for Salesbin (no CDN).
 */
(function (window) {
	'use strict';

	function maxOf(values) {
		var m = 0;
		values.forEach(function (v) {
			if (v > m) {
				m = v;
			}
		});
		return m || 1;
	}

	function themeColors() {
		var s = getComputedStyle(document.body);
		return {
			accent: (s.getPropertyValue('--sb-accent') || '').trim() || '#8b5cf6',
			accent2: (s.getPropertyValue('--sb-accent-2') || '').trim() || '#a78bfa',
			border: (s.getPropertyValue('--sb-border') || '').trim() || 'rgba(255,255,255,0.07)',
			muted: (s.getPropertyValue('--sb-muted') || '').trim() || '#8b91a7'
		};
	}

	function render(el, series, metric, type) {
		if (!el) {
			return;
		}
		var c = themeColors();
		var key = metric === 'orders' ? 'orders' : (metric === 'aov' ? 'aov' : 'sales');
		var values = (series || []).map(function (p) {
			return Number(p[key] || 0);
		});
		var labels = (series || []).map(function (p) {
			return p.date || '';
		});
		var w = el.clientWidth || 640;
		var h = el.clientHeight || 280;
		var pad = { t: 16, r: 12, b: 28, l: 8 };
		var iw = Math.max(40, w - pad.l - pad.r);
		var ih = Math.max(40, h - pad.t - pad.b);
		var max = maxOf(values);
		var n = values.length;
		if (!n) {
			el.innerHTML = '';
			return;
		}

		var pts = values.map(function (v, i) {
			var x = pad.l + (n === 1 ? iw / 2 : (i / (n - 1)) * iw);
			var y = pad.t + ih - (v / max) * ih;
			return { x: x, y: y, v: v, label: labels[i] };
		});

		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
		svg.setAttribute('width', '100%');
		svg.setAttribute('height', '100%');
		svg.setAttribute('role', 'img');
		svg.setAttribute('aria-label', 'chart');

		var grid = document.createElementNS(ns, 'g');
		for (var g = 0; g < 4; g++) {
			var gy = pad.t + (ih / 3) * g;
			var line = document.createElementNS(ns, 'line');
			line.setAttribute('x1', pad.l);
			line.setAttribute('x2', pad.l + iw);
			line.setAttribute('y1', gy);
			line.setAttribute('y2', gy);
			line.setAttribute('stroke', c.border);
			grid.appendChild(line);
		}
		svg.appendChild(grid);

		if (type === 'bar') {
			var bw = Math.max(2, iw / n * 0.6);
			pts.forEach(function (p) {
				var rect = document.createElementNS(ns, 'rect');
				rect.setAttribute('x', p.x - bw / 2);
				rect.setAttribute('y', p.y);
				rect.setAttribute('width', bw);
				rect.setAttribute('height', Math.max(1, pad.t + ih - p.y));
				rect.setAttribute('rx', 4);
				rect.setAttribute('fill', 'url(#salesbinGrad)');
				svg.appendChild(rect);
			});
		} else {
			var d = pts.map(function (p, i) {
				return (i ? 'L' : 'M') + p.x + ',' + p.y;
			}).join(' ');
			var path = document.createElementNS(ns, 'path');
			path.setAttribute('d', d);
			path.setAttribute('fill', 'none');
			path.setAttribute('stroke', c.accent2);
			path.setAttribute('stroke-width', '2.4');
			path.setAttribute('stroke-linejoin', 'round');
			svg.appendChild(path);
		}

		var defs = document.createElementNS(ns, 'defs');
		var lg = document.createElementNS(ns, 'linearGradient');
		lg.setAttribute('id', 'salesbinGrad');
		lg.setAttribute('x1', '0');
		lg.setAttribute('x2', '0');
		lg.setAttribute('y1', '0');
		lg.setAttribute('y2', '1');
		var s1 = document.createElementNS(ns, 'stop');
		s1.setAttribute('offset', '0%');
		s1.setAttribute('stop-color', c.accent2);
		var s2 = document.createElementNS(ns, 'stop');
		s2.setAttribute('offset', '100%');
		s2.setAttribute('stop-color', c.accent);
		lg.appendChild(s1);
		lg.appendChild(s2);
		defs.appendChild(lg);
		svg.insertBefore(defs, svg.firstChild);

		var last = labels[labels.length - 1] || '';
		var first = labels[0] || '';
		[first, last].forEach(function (lab, idx) {
			var t = document.createElementNS(ns, 'text');
			t.setAttribute('x', idx ? w - 8 : 8);
			t.setAttribute('y', h - 8);
			t.setAttribute('fill', c.muted);
			t.setAttribute('font-size', '11');
			t.setAttribute('text-anchor', idx ? 'end' : 'start');
			t.textContent = lab;
			svg.appendChild(t);
		});

		el.innerHTML = '';
		el.appendChild(svg);
	}

	window.SalesbinCharts = { render: render };
})(window);
