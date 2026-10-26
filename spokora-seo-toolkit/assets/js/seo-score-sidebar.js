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
			function llmsOverridesFromDom() {
				var out = {};
				var publicEl = document.querySelector('input[name="spokora_llms_public"]');
				if (publicEl) {
					out.llms_public = publicEl.checked ? '1' : '';
				}
				var summaryEl = document.getElementById('spokora_llms_summary');
				if (summaryEl) {
					out.llms_summary = summaryEl.value || '';
				}
				var mdEl = document.getElementById('spokora_llms_md');
				if (mdEl) {
					out.llms_md = mdEl.value || '';
				}
				return out;
			}
			function buildSignature() {
				var sel = wp.data.select('core/editor');
				if (!sel) return '';
				var titleEl = document.getElementById('spokora_seo_title');
				var descEl = document.getElementById('spokora_seo_desc');
				var llms = llmsOverridesFromDom();
				return [
					sel.getEditedPostContent(),
					sel.getEditedPostAttribute('title') || '',
					titleEl ? titleEl.value : '',
					descEl ? descEl.value : '',
					document.getElementById('spokora_focus_keyword')?.value || '',
					document.getElementById('spokora_additional_keywords')?.value || '',
					llms.llms_public !== undefined ? llms.llms_public : '\u0002',
					llms.llms_summary !== undefined ? llms.llms_summary : '\u0002',
					llms.llms_md !== undefined ? llms.llms_md : '\u0002',
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
					var overrides = {
						title: titleEl ? titleEl.value : '',
						description: descEl ? descEl.value : '',
						focus_keyword: document.getElementById('spokora_focus_keyword')?.value || '',
						additional_keywords: document.getElementById('spokora_additional_keywords')?.value || '',
						content: wp.data.select('core/editor').getEditedPostContent(),
					};
					Object.assign(overrides, llmsOverridesFromDom());
					jQuery.post(spokoraSeoScoreSidebar.ajaxUrl, {
						action: 'spokora_recalc_seo_scores',
						nonce: spokoraSeoScoreSidebar.nonce,
						post_id: spokoraSeoScoreSidebar.postId,
						overrides: overrides,
					}).done(function (res) {
						if (res.success) setScores(res.data);
					});
				}, 1200);
			}
			var wasSaving = false;
			function refreshFromServer() {
				jQuery.post(spokoraSeoScoreSidebar.ajaxUrl, {
					action: 'spokora_recalc_seo_scores',
					nonce: spokoraSeoScoreSidebar.nonce,
					post_id: spokoraSeoScoreSidebar.postId,
					overrides: {},
				}).done(function (res) {
					if (res.success) setScores(res.data);
				});
			}
			var unsub = wp.data.subscribe(function () {
				recalc();
				var sel = wp.data.select('core/editor');
				if (!sel) return;
				var saving = sel.isSavingPost();
				if (wasSaving && !saving && sel.didPostSaveRequestSucceed && sel.didPostSaveRequestSucceed()) {
					lastSig = '';
					refreshFromServer();
				}
				wasSaving = saving;
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
