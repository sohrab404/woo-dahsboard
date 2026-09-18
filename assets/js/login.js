/**
 * Salesbin auth card — v2 unified flow.
 * Tabs (login/register), in-tab method toggle (SMS OTP <-> password),
 * OTP boxes with auto-advance/paste, resend timer, REST OTP flow.
 * The mode toggle is local to the card (data-sb-mode on the page root).
 */
(function (window, document) {
	'use strict';

	function toLatinDigits(str) {
		var fa = '۰۱۲۳۴۵۶۷۸۹';
		var ar = '٠١٢٣٤٥٦٧٨٩';
		var out = '';
		str = String(str);
		for (var i = 0; i < str.length; i++) {
			var ch = str.charAt(i);
			var idx = fa.indexOf(ch);
			if (idx > -1) { out += idx; continue; }
			idx = ar.indexOf(ch);
			out += idx > -1 ? idx : ch;
		}
		return out;
	}

	function faNum(n) {
		return String(n).replace(/\d/g, function (d) {
			return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d);
		});
	}

	/* ============ Mode toggle (local, persisted) ============ */
	function initMode(page, cfg) {
		var btn = page.querySelector('[data-sb-login-theme]');
		if (!btn) { return; }
		var KEY = 'salesbinLoginMode';

		function read() {
			try {
				var v = window.localStorage.getItem(KEY);
				if (v === 'light' || v === 'dark') { return v; }
			} catch (e) { /* ignore */ }
			return cfg.defaultMode === 'light' ? 'light' : 'dark';
		}

		function render() {
			var mode = read();
			page.setAttribute('data-sb-mode', mode);
			btn.textContent = mode === 'dark' ? '☀️' : '🌙';
			btn.setAttribute('aria-label', mode === 'dark' ? 'حالت روز' : 'حالت شب');
		}

		btn.addEventListener('click', function () {
			var next = read() === 'dark' ? 'light' : 'dark';
			try { window.localStorage.setItem(KEY, next); } catch (e) { /* ignore */ }
			render();
		});

		render();
	}

	/* ============ Tabs ============ */
	function initTabs(page) {
		var tabsWrap = page.querySelector('[data-sb-login-tabs]');
		var tabs = page.querySelectorAll('[data-sb-login-tab]');
		var panels = page.querySelectorAll('[data-sb-login-panel]');

		function setIndicator(tab) {
			if (!tabsWrap) { return; }
			var list = Array.prototype.slice.call(tabs);
			var index = Math.max(0, list.indexOf(tab));
			tabsWrap.style.setProperty('--sb-tab-i', String(index));
			tabsWrap.style.setProperty('--sb-tab-n', String(list.length));
		}

		function activate(tab) {
			var key = tab.getAttribute('data-sb-login-tab');
			tabs.forEach(function (t) {
				t.classList.toggle('is-active', t === tab);
				t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
			});
			panels.forEach(function (panel) {
				panel.classList.toggle('is-active', panel.getAttribute('data-sb-login-panel') === key);
			});
			setIndicator(tab);
			page.dispatchEvent(new CustomEvent('sb-login-tab', { detail: { tab: key } }));
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () { activate(tab); });
		});

		var active = page.querySelector('[data-sb-login-tab].is-active') || tabs[0];
		if (active) { setIndicator(active); }
	}

	/* ============ Method toggle (SMS <-> password) ============ */
	function initMethodToggle(page, smsEnabled) {
		var loginPanel = page.querySelector('[data-sb-login-panel="login"]');
		if (!loginPanel || !smsEnabled) { return; }

		var passwordForm = loginPanel.querySelector('[data-sb-login-method="password"]');
		var smsBox = loginPanel.querySelector('[data-sb-login-method="sms"]');
		var toggleBtn = loginPanel.querySelector('[data-sb-method-toggle]');
		if (!passwordForm || !smsBox || !toggleBtn) { return; }

		var method = 'sms';

		function render() {
			var showSms = method === 'sms';
			smsBox.hidden = !showSms;
			passwordForm.hidden = showSms;
			toggleBtn.textContent = showSms ? 'ورود با رمز عبور' : 'ورود با کد پیامکی';
			if (showSms) {
				var mobile = smsBox.querySelector('[data-sb-sms-mobile]');
				var stepPhone = smsBox.querySelector('[data-sb-sms-step="phone"]');
				if (mobile && stepPhone && !stepPhone.hidden) {
					mobile.focus();
				}
			}
		}

		toggleBtn.addEventListener('click', function () {
			method = method === 'sms' ? 'password' : 'sms';
			render();
		});

		render();
	}

	/* ============ SMS OTP flow ============ */
	function initSms(page, cfg) {
		var box = page.querySelector('[data-sb-sms]');
		if (!box || !cfg.smsEnabled) {
			return;
		}

		var ccEl = box.querySelector('[data-sb-sms-cc]');
		var mobileEl = box.querySelector('[data-sb-sms-mobile]');
		var otpHidden = box.querySelector('[data-sb-sms-otp]');
		var otpBoxes = Array.prototype.slice.call(box.querySelectorAll('.sb-otp-box'));
		var echoEl = box.querySelector('[data-sb-sms-echo]');
		var rememberEl = box.querySelector('[data-sb-sms-remember]');
		var messageEl = box.querySelector('[data-sb-sms-message]');
		var resendBtn = box.querySelector('[data-sb-sms-action="resend"]');
		var timerEl = box.querySelector('[data-sb-sms-timer]');
		var stepPhone = box.querySelector('[data-sb-sms-step="phone"]');
		var stepOtp = box.querySelector('[data-sb-sms-step="otp"]');

		var timer = null;

		function show(text, ok) {
			if (!messageEl) { return; }
			messageEl.hidden = !text;
			messageEl.textContent = text || '';
			messageEl.className = 'sb-login-alert ' + (ok ? 'sb-login-alert--ok' : 'sb-login-alert--error');
		}

		function busy(btn, on) {
			if (!btn) { return; }
			btn.disabled = on;
			btn.classList.toggle('is-busy', on);
		}

		function mobile() {
			return toLatinDigits(mobileEl.value).replace(/[^\d]/g, '').replace(/^0+/, '');
		}

		function cc() {
			return toLatinDigits(ccEl.textContent || '').replace(/[^\d]/g, '');
		}

		function validate() {
			if (!mobile()) { return 'شماره موبایل را وارد کنید.'; }
			if (cc() === '98' && !/^9\d{9}$/.test(mobile())) {
				return 'شماره موبایل ایران باید ۱۰ رقم و بدون صفر ابتدایی باشد. مثال: 9123456789';
			}
			if (mobile().length < 7) { return 'شماره موبایل معتبر نیست.'; }
			return null;
		}

		function stopTimer() {
			if (timer) { clearInterval(timer); timer = null; }
		}

		function startTimer(seconds) {
			stopTimer();
			var left = parseInt(seconds, 10);
			if (isNaN(left) || left < 0) { left = cfg.resendAfter || 60; }
			if (resendBtn) { resendBtn.disabled = true; }
			if (timerEl) { timerEl.textContent = faNum(left); }
			timer = setInterval(function () {
				left--;
				if (timerEl) { timerEl.textContent = faNum(Math.max(0, left)); }
				if (left <= 0) {
					stopTimer();
					if (resendBtn) { resendBtn.disabled = false; }
				}
			}, 1000);
		}

		/* ---- OTP boxes ---- */
		function otpValue() {
			return otpBoxes.map(function (b) {
				return toLatinDigits(b.value).replace(/\D/g, '').charAt(0) || '';
			}).join('');
		}

		function syncOtp() {
			var value = otpValue();
			if (otpHidden) { otpHidden.value = value; }
			otpBoxes.forEach(function (b) {
				b.classList.toggle('is-filled', toLatinDigits(b.value).replace(/\D/g, '').length > 0);
			});
			return value;
		}

		function focusOtp(index) {
			var i = Math.max(0, Math.min(otpBoxes.length - 1, index));
			if (otpBoxes[i]) { otpBoxes[i].focus(); }
		}

		function clearOtp() {
			otpBoxes.forEach(function (b) { b.value = ''; });
			syncOtp();
		}

		otpBoxes.forEach(function (input, index) {
			input.addEventListener('input', function () {
				var digits = toLatinDigits(input.value).replace(/\D/g, '');
				if (digits.length > 1) {
					for (var d = 0; d < digits.length && index + d < otpBoxes.length; d++) {
						otpBoxes[index + d].value = digits.charAt(d);
					}
					syncOtp();
					focusOtp(index + digits.length);
					return;
				}
				input.value = digits;
				syncOtp();
				if (digits && index < otpBoxes.length - 1) { focusOtp(index + 1); }
			});

			input.addEventListener('keydown', function (e) {
				if (e.key === 'Backspace') {
					if (!input.value && index > 0) {
						focusOtp(index - 1);
						otpBoxes[index - 1].value = '';
						syncOtp();
						e.preventDefault();
					}
					return;
				}
				if (e.key === 'ArrowLeft') { focusOtp(index + 1); e.preventDefault(); return; }
				if (e.key === 'ArrowRight') { focusOtp(index - 1); e.preventDefault(); return; }
				if (e.key === 'Enter') { e.preventDefault(); verify(); }
			});

			input.addEventListener('paste', function (e) {
				e.preventDefault();
				var text = toLatinDigits((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '');
				if (!text) { return; }
				for (var d = 0; d < text.length && index + d < otpBoxes.length; d++) {
					otpBoxes[index + d].value = text.charAt(d);
				}
				syncOtp();
				focusOtp(index + text.length);
			});

			input.addEventListener('focus', function () { input.select(); });
		});

		/* ---- Steps ---- */
		function enterOtpStep() {
			stepPhone.hidden = true;
			stepOtp.hidden = false;
			mobileEl.disabled = true;
			if (echoEl) { echoEl.textContent = '+' + cc() + ' ' + mobile(); }
			clearOtp();
			focusOtp(0);
		}

		function backToPhoneStep() {
			stopTimer();
			stepPhone.hidden = false;
			stepOtp.hidden = true;
			mobileEl.disabled = false;
			clearOtp();
			if (resendBtn) { resendBtn.disabled = true; }
			mobileEl.focus();
		}

		function post(action, payload) {
			var body = Object.assign({
				otp_nonce: cfg.otpNonce,
				countrycode: cc(),
				mobile: mobile()
			}, payload || {});

			return fetch(cfg.restUrl + 'login/sms/' + action, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.restNonce,
					'Accept': 'application/json'
				},
				body: JSON.stringify(body)
			}).then(function (r) {
				return r.json().then(function (json) {
					if (!r.ok || (json && json.success === false)) {
						var err = json && (json.message || (json.data && json.data.message));
						throw new Error(err || 'خطای غیرمنتظره؛ دوباره تلاش کنید.');
					}
					return json;
				});
			}).catch(function (e) {
				if (e instanceof SyntaxError) {
					throw new Error('ارتباط با سرور برقرار نشد.');
				}
				throw e;
			});
		}

		function sendOtp(isResend) {
			var err = validate();
			if (err) { show(err, false); return; }

			var btn = box.querySelector('[data-sb-sms-action="' + (isResend ? 'resend' : 'send') + '"]');
			busy(btn, true);
			post('send', {}).then(function (res) {
				busy(btn, false);
				show(res.data.debug_code ? 'کد (حالت تست): ' + res.data.debug_code : (res.data.message || 'کد تایید پیامک شد.'), true);
				if (!isResend) { enterOtpStep(); }
				startTimer(res.data.resend_after || cfg.resendAfter || 60);
			}).catch(function (e) {
				busy(btn, false);
				show(e.message, false);
			});
		}

		function verify() {
			var otp = syncOtp();
			if (otp.length < otpBoxes.length) { show('کد تایید ' + faNum(otpBoxes.length) + ' رقمی را کامل وارد کنید.', false); return; }

			// Only honor an explicit ?redirect_to= in the URL; otherwise the
			// server sends the user to their account page.
			var params = new URLSearchParams(window.location.search);
			var redirectTo = params.get('redirect_to') || '';

			var btn = box.querySelector('[data-sb-sms-action="verify"]');
			busy(btn, true);
			post('verify', {
				otp: otp,
				remember: rememberEl && rememberEl.checked ? '1' : '0',
				redirect_to: redirectTo
			}).then(function (res) {
				show(res.data.message || 'انجام شد.', true);
				setTimeout(function () {
					window.location.href = (res.data && res.data.redirect) || '/';
				}, 700);
			}).catch(function (e) {
				busy(btn, false);
				show(e.message, false);
				clearOtp();
				focusOtp(0);
			});
		}

		box.querySelectorAll('[data-sb-sms-action]').forEach(function (btn) {
			var action = btn.getAttribute('data-sb-sms-action');
			btn.addEventListener('click', function () {
				if (action === 'send') { sendOtp(false); }
				else if (action === 'resend') { if (!btn.disabled) { sendOtp(true); } }
				else if (action === 'verify') { verify(); }
				else if (action === 'edit') { backToPhoneStep(); }
			});
		});

		mobileEl.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter') { return; }
			e.preventDefault();
			if (!stepOtp.hidden) { verify(); } else { sendOtp(false); }
		});

		mobileEl.addEventListener('input', function () {
			var pos = mobileEl.selectionStart;
			mobileEl.value = toLatinDigits(mobileEl.value).replace(/[^\d]/g, '').slice(0, 13);
			try { mobileEl.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
		});

		// Reset the flow when leaving the SMS context.
		function resetFlow() {
			if (!stepOtp.hidden) { backToPhoneStep(); }
		}

		page.addEventListener('sb-login-tab', function () { resetFlow(); });
		var toggleBtn = page.querySelector('[data-sb-method-toggle]');
		if (toggleBtn) {
			toggleBtn.addEventListener('click', resetFlow);
		}
	}

	function init(page) {
		var cfg = window.salesbinLoginCfg || {};
		initMode(page, cfg);
		initTabs(page);
		initMethodToggle(page, !!cfg.smsEnabled);
		initSms(page, cfg);
		if (window.SalesbinFX) {
			SalesbinFX.enhance(page);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			var page = document.querySelector('.salesbin-login-page');
			if (page) { init(page); }
		});
	} else {
		var page = document.querySelector('.salesbin-login-page');
		if (page) { init(page); }
	}
})(window, document);
