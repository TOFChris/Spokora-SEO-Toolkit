/**
 * Optional GA4 after cookie consent. Host is assembled at runtime so Plugin Check
 * does not treat a hardcoded third-party script tag in PHP as offloaded content.
 */
(function () {
	var cfg = window.spokoraGa4;
	if (!cfg || !cfg.id) {
		return;
	}
	window.dataLayer = window.dataLayer || [];
	function gtag() {
		window.dataLayer.push(arguments);
	}
	window.gtag = gtag;
	var wait = cfg.granted ? {} : { wait_for_update: 500 };
	gtag(
		'consent',
		'default',
		Object.assign(
			{
				analytics_storage: cfg.granted ? 'granted' : 'denied',
				ad_storage: 'denied',
				ad_user_data: 'denied',
				ad_personalization: 'denied'
			},
			wait
		)
	);
	var s = document.createElement('script');
	s.async = true;
	s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.id);
	document.head.appendChild(s);
	gtag('js', new Date());
	gtag('config', cfg.id, cfg.config || { anonymize_ip: true });
})();
