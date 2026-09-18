/**
 * Lightweight Jalali helpers for Salesbin date picker (no CDN).
 */
(function (window) {
	'use strict';

	function div(a, b) {
		return ~~(a / b);
	}

	function jalaliToGregorian(jy, jm, jd) {
		jy = parseInt(jy, 10);
		jm = parseInt(jm, 10);
		jd = parseInt(jd, 10);
		var gy, gm, gd, days, sal_a, v;
		if (jy > 979) {
			gy = 1600;
			jy -= 979;
		} else {
			gy = 621;
		}
		days = (365 * jy) + div(jy, 33) * 8 + div(((jy % 33) + 3), 4) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
		gy += 400 * div(days, 146097);
		days %= 146097;
		if (days > 36524) {
			gy += 100 * div(--days, 36524);
			days %= 36524;
			if (days >= 365) {
				days++;
			}
		}
		gy += 4 * div(days, 1461);
		days %= 1461;
		if (days > 365) {
			gy += div(days - 1, 365);
			days = (days - 1) % 365;
		}
		gd = days + 1;
		sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) {
			gd -= sal_a[gm];
		}
		return [gy, gm, gd];
	}

	function gregorianToJalali(gy, gm, gd) {
		gy = parseInt(gy, 10);
		gm = parseInt(gm, 10);
		gd = parseInt(gd, 10);
		var g_d_m, jy, jm, jd, gy2, days;
		g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
		gy2 = (gm > 2) ? (gy + 1) : gy;
		days = 355666 + (365 * gy) + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) + gd + g_d_m[gm - 1];
		jy = -1595 + (33 * div(days, 12053));
		days %= 12053;
		jy += 4 * div(days, 1461);
		days %= 1461;
		if (days > 365) {
			jy += div(days - 1, 365);
			days = (days - 1) % 365;
		}
		if (days < 186) {
			jm = 1 + div(days, 31);
			jd = 1 + (days % 31);
		} else {
			jm = 7 + div(days - 186, 30);
			jd = 1 + ((days - 186) % 30);
		}
		return [jy, jm, jd];
	}

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function parseJalaliInput(value) {
		var m = String(value || '').trim().replace(/-/g, '/').match(/^(\d{3,4})\/(\d{1,2})\/(\d{1,2})$/);
		if (!m) {
			return null;
		}
		var g = jalaliToGregorian(m[1], m[2], m[3]);
		return g[0] + '-' + pad(g[1]) + '-' + pad(g[2]);
	}

	function formatJalaliFromGregorian(ymd) {
		var p = String(ymd || '').split('-');
		if (p.length !== 3) {
			return '';
		}
		var j = gregorianToJalali(p[0], p[1], p[2]);
		return j[0] + '/' + pad(j[1]) + '/' + pad(j[2]);
	}

	window.SalesbinJalali = {
		parseJalaliInput: parseJalaliInput,
		formatJalaliFromGregorian: formatJalaliFromGregorian,
		gregorianToJalali: gregorianToJalali,
		jalaliToGregorian: jalaliToGregorian
	};
})(window);
