(function (wp) {
	'use strict';
	if (!wp.plugins || !wp.editPost || !wp.element || !spokoraSeoScoreSidebar) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;

	function ScoreRing(props) {
		var score = props.score || 0;
		var label = props.label || '';
		var color = score >= 80 ? '#00a32a' : score >= 50 ? '#dba617' : '#d63638';
		return el(
			'div',
			{ style: { display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '10px' } },
			el('div', {
				style: {
					width: '44px',
					height: '44px',
					borderRadius: '50%',
					background: 'conic-gradient(' + color + ' ' + score + '%, #e0e0e0 0)',
					display: 'grid',
					placeItems: 'center',
					position: 'relative',
				},
			}, el('span', {
				style: {
					width: '32px',
					height: '32px',
					borderRadius: '50%',
					background: '#fff',
					display: 'grid',
					placeItems: 'center',
					fontWeight: '700',
					fontSize: '12px',
				},
			}, String(score))),
			el('div', null, el('strong', null, label), el('div', { style: { fontSize: '11px', color: '#646970' } }, '/100'))
		);
	}

	function Panel() {
		var _useState = useState(spokoraSeoScoreSidebar.initial || {});
		var scores = _useState[0];
		var setScores = _useState[1];

		useEffect(function () {
			if (!spokoraSeoScoreSidebar.postId) return;
			var timer;
			var lastSig = '';
			function buildSignature() {
				var sel = wp.data.select('core/editor');
				if (!sel) return '';
				var titleEl = document.getElementById('spokora_seo_title');
				var descEl = document.getElementById('spokora_seo_desc');
				return [
					sel.getEditedPostContent(),
					sel.getEditedPostAttribute('title') || '',
					titleEl ? titleEl.value : '',
					descEl ? descEl.value : '',
					document.getElementById('spokora_focus_keyword')?.value || '',
					document.getElementById('spokora_additional_keywords')?.value || '',
					document.querySelector('input[name="spokora_llms_public"]')?.checked ? '1' : '',
					document.getElementById('spokora_llms_summary')?.value || '',
					document.getElementById('spokora_llms_md')?.value || '',
				].join('\u0001');
			}
			function recalc() {
				clearTimeout(timer);
				timer = setTimeout(function () {
					var sig = buildSignature();
					if (sig === lastSig) return;
					lastSig = sig;
					var titleEl = document.getElementById('spokora_seo_title');
					var descEl = document.getElementById('spokora_seo_desc');
					jQuery.post(spokoraSeoScoreSidebar.ajaxUrl, {
						action: 'spokora_recalc_seo_scores',
						nonce: spokoraSeoScoreSidebar.nonce,
						post_id: spokoraSeoScoreSidebar.postId,
						overrides: {
							title: titleEl ? titleEl.value : '',
							description: descEl ? descEl.value : '',
							focus_keyword: document.getElementById('spokora_focus_keyword')?.value || '',
							additional_keywords: document.getElementById('spokora_additional_keywords')?.value || '',
							content: wp.data.select('core/editor').getEditedPostContent(),
							llms_public: document.querySelector('input[name="spokora_llms_public"]')?.checked ? '1' : '',
							llms_summary: document.getElementById('spokora_llms_summary')?.value || '',
							llms_md: document.getElementById('spokora_llms_md')?.value || '',
						},
					}).done(function (res) {
						if (res.success) setScores(res.data);
					});
				}, 1200);
			}
			var unsub = wp.data.subscribe(function () {
				recalc();
			});
			return function () {
				unsub();
				clearTimeout(timer);
			};
		}, []);

		return el(
			PluginDocumentSettingPanel,
			{ name: 'spokora-seo-scores', title: 'Scores', className: 'spokora-seo-score-sidebar-panel' },
			el(ScoreRing, { score: scores.search?.score || 0, label: 'Search SEO' }),
			el(ScoreRing, { score: scores.ai?.score || 0, label: 'AI readiness' }),
			el('p', { style: { fontSize: '12px', color: '#646970' } }, 'Open the SEO box in Spokora below for keyphrases, checklists, and SERP preview.')
		);
	}

	wp.plugins.registerPlugin('spokora-seo-scores', { render: Panel, icon: 'chart-bar' });
})(window.wp);
