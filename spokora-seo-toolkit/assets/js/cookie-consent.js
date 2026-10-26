(function () {
	'use strict';

	var cfg = window.spokoraConsent || {};
	var storageKey = 'spokora_consent';
	var cookieName = 'spokora_consent';
	var strings = cfg.strings || {};

	function readCookie() {
		var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + cookieName + '=([^;]+)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function readConsent() {
		var fromCookie = readCookie();
		if (fromCookie === 'analytics' || fromCookie === 'essential') {
			try {
				localStorage.setItem(storageKey, fromCookie);
			} catch (e) {
				/* ignore */
			}
			return fromCookie;
		}
		try {
			return localStorage.getItem(storageKey) || '';
		} catch (e2) {
			return '';
		}
	}

	function writeConsent(value) {
		var normalized = value === 'analytics' ? 'analytics' : 'essential';
		try {
			localStorage.setItem(storageKey, normalized);
		} catch (e) {
			/* ignore */
		}
		var maxAge = 60 * 60 * 24 * 180;
		var secure = window.location.protocol === 'https:' ? ';Secure' : '';
		document.cookie =
			cookieName +
			'=' +
			encodeURIComponent(normalized) +
			';path=/;max-age=' +
			maxAge +
			';SameSite=Lax' +
			secure;
	}

	function gtagAlreadyInPage() {
		return !!document.querySelector('script[src*="googletagmanager.com/gtag/js"]');
	}

	function loadAnalytics() {
		var id = cfg.measurementId;
		if (!id || window.__spokoraGaLoaded || gtagAlreadyInPage()) {
			return;
		}
		window.__spokoraGaLoaded = true;
		window.dataLayer = window.dataLayer || [];
		window.gtag = function () {
			window.dataLayer.push(arguments);
		};
		window.gtag('consent', 'default', {
			analytics_storage: 'denied',
			ad_storage: 'denied',
			ad_user_data: 'denied',
			ad_personalization: 'denied'
		});
		window.gtag('consent', 'update', {
			analytics_storage: 'granted'
		});
		var s = document.createElement('script');
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
		document.head.appendChild(s);
		window.gtag('js', new Date());
		window.gtag('config', id, { anonymize_ip: true });
	}

	function removeBanner() {
		var el = document.getElementById('spk-consent');
		if (el) {
			el.remove();
		}
	}

	function showBanner() {
		if (document.getElementById('spk-consent')) {
			return;
		}
		var wrap = document.createElement('div');
		wrap.id = 'spk-consent';
		wrap.className = 'spk-consent';
		wrap.setAttribute('role', 'dialog');
		wrap.setAttribute('aria-modal', 'true');
		wrap.setAttribute('aria-label', strings.dialogLabel || 'Cookie choices');

		var inner = document.createElement('div');
		inner.className = 'spk-consent__inner';

		var p = document.createElement('p');
		p.id = 'spk-consent-desc';
		p.appendChild(document.createTextNode(strings.body || 'This site uses cookies.'));
		p.appendChild(document.createTextNode(' '));
		var a = document.createElement('a');
		a.href = cfg.privacyUrl || '/privacy-policy/';
		a.textContent = strings.privacyLink || 'Privacy Policy';
		p.appendChild(a);
		p.appendChild(document.createTextNode('.'));
		wrap.setAttribute('aria-describedby', 'spk-consent-desc');

		var actions = document.createElement('div');
		actions.className = 'spk-consent__actions';

		var rejectBtn = document.createElement('button');
		rejectBtn.type = 'button';
		rejectBtn.className = 'spk-btn spk-btn--consent spk-btn--reject';
		rejectBtn.setAttribute('data-spk-consent', 'essential');
		rejectBtn.textContent = strings.reject || 'Reject non-essential cookies';

		var acceptBtn = document.createElement('button');
		acceptBtn.type = 'button';
		acceptBtn.className = 'spk-btn spk-btn--consent spk-btn--accept';
		acceptBtn.setAttribute('data-spk-consent', 'analytics');
		acceptBtn.textContent = strings.accept || 'Accept all cookies';

		actions.appendChild(rejectBtn);
		actions.appendChild(acceptBtn);
		inner.appendChild(p);
		inner.appendChild(actions);
		wrap.appendChild(inner);
		document.body.appendChild(wrap);
		if (typeof rejectBtn.focus === 'function') {
			rejectBtn.focus();
		}

		wrap.addEventListener('click', function (ev) {
			var btn = ev.target.closest('[data-spk-consent]');
			if (!btn) {
				return;
			}
			var choice = btn.getAttribute('data-spk-consent');
			writeConsent(choice === 'analytics' ? 'analytics' : 'essential');
			removeBanner();
			if (choice === 'analytics') {
				loadAnalytics();
			}
		});
	}

	var existing = readConsent();
	if (existing === 'analytics') {
		if (!gtagAlreadyInPage()) {
			loadAnalytics();
		}
		return;
	}
	if (existing === 'essential') {
		return;
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', showBanner);
	} else {
		showBanner();
	}
})();
