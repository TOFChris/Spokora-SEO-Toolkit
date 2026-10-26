(function ($) {
	'use strict';

	function statusClass(status) {
		if (status === 'good') return 'is-good';
		if (status === 'ok') return 'is-ok';
		if (status === 'na') return 'is-na';
		return 'is-poor';
	}

	function updateGauge($panel, type, score, status) {
		var $gauge = $panel.find('.spokora-seo-gauge--' + type);
		$gauge.removeClass('is-good is-ok is-poor is-na').addClass(statusClass(status));
		$gauge.find('.spokora-seo-gauge__ring').css('--spokora-score', score);
		$gauge.find('.spokora-seo-gauge__value').text(score);
	}

	function renderChecklist($list, checks) {
		$list.empty();
		(checks || []).forEach(function (c) {
			var icon = 'dashicons-marker';
			if (c.status === 'pass') icon = 'dashicons-yes-alt';
			if (c.status === 'warn') icon = 'dashicons-warning';
			if (c.status === 'fail') icon = 'dashicons-dismiss';
			$list.append(
				$('<li/>').append(
					$('<span/>', { class: 'dashicons ' + icon + ' status-' + c.status }),
					$('<span/>').html('<strong>' + c.label + '</strong> — ' + c.message)
				)
			);
		});
	}

	function updateSerp($panel, serp) {
		if (!serp) return;
		$panel.find('.spokora-serp-preview__title').text(serp.title || '');
		$panel.find('.spokora-serp-preview__url').text(serp.url || '');
		$panel.find('.spokora-serp-preview__desc').text(serp.description || '');
	}

	function charCountClass(len, min, max) {
		if (len >= min && len <= max) return '';
		if (len >= min - 20 && len <= max + 20) return 'is-warn';
		return 'is-fail';
	}

	function updateCharCounts() {
		var title = $('#spokora_seo_title').val() || '';
		var desc = $('#spokora_seo_desc').val() || '';
		$('#spokora-seo-title-count')
			.text(title.length + '/60')
			.removeClass('is-warn is-fail')
			.addClass(charCountClass(title.length, 50, 60));
		$('#spokora-seo-desc-count')
			.text(desc.length + '/160')
			.removeClass('is-warn is-fail')
			.addClass(charCountClass(desc.length, 120, 160));
	}

	function gatherOverrides() {
		var overrides = {
			title: $('#spokora_seo_title').val() || '',
			description: $('#spokora_seo_desc').val() || '',
			focus_keyword: $('#spokora_focus_keyword').val() || '',
			additional_keywords: $('#spokora_additional_keywords').val() || '',
			content: (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor'))
				? wp.data.select('core/editor').getEditedPostContent()
				: '',
			excluded: $('input[name="spokora_seo_score_exclude"]').is(':checked') ? '1' : ''
		};
		var $llmsPublic = $('input[name="spokora_llms_public"]');
		if ($llmsPublic.length) {
			overrides.llms_public = $llmsPublic.is(':checked') ? '1' : '';
		}
		if ($('#spokora_llms_summary').length) {
			overrides.llms_summary = $('#spokora_llms_summary').val() || '';
		}
		if ($('#spokora_llms_md').length) {
			overrides.llms_md = $('#spokora_llms_md').val() || '';
		}
		return overrides;
	}

	var recalcTimer;
	function scheduleRecalc() {
		clearTimeout(recalcTimer);
		recalcTimer = setTimeout(recalcScores, 400);
	}

	function recalcScores() {
		var $panel = $('#spokora-seo-score-panel');
		if (!$panel.length || !window.spokoraSeoScore) return;

		updateCharCounts();

		$.post(window.spokoraSeoScore.ajaxUrl, {
			action: 'spokora_recalc_seo_scores',
			nonce: window.spokoraSeoScore.nonce,
			post_id: window.spokoraSeoScore.postId,
			overrides: gatherOverrides()
		}).done(function (res) {
			if (!res.success || !res.data) return;
			var d = res.data;
			$panel.removeClass('is-good is-ok is-poor is-na').addClass(statusClass(d.search.status));
			updateGauge($panel, 'search', d.search.score, d.search.status);
			updateGauge($panel, 'ai', d.ai.score, d.ai.status);
			renderChecklist($panel.find('.spokora-seo-checklist--search'), d.search.checks);
			renderChecklist($panel.find('.spokora-seo-checklist--ai'), d.ai.checks);
			updateSerp($panel, d.search.serp);
			if (d.search.content_source && d.search.content_source.label) {
				$panel.find('.spokora-seo-source-badge').text(
					window.spokoraSeoScore.i18n.scoringFrom + ' ' + d.search.content_source.label
				).show();
			}
		});
	}

	$(function () {
		var $panel = $('#spokora-seo-score-panel');
		if (!$panel.length) return;

		updateCharCounts();
		$(document).on(
			'input',
			'#spokora_seo_title, #spokora_seo_desc, #spokora_focus_keyword, #spokora_additional_keywords, #spokora_llms_summary, #spokora_llms_md',
			scheduleRecalc
		);
		$(document).on(
			'change',
			'input[name="spokora_llms_public"], input[name="spokora_llms_include"], input[name="spokora_seo_score_exclude"]',
			scheduleRecalc
		);

		if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
			var lastContent = '';
			wp.data.subscribe(function () {
				if (!wp.data.select('core/editor')) return;
				var c = wp.data.select('core/editor').getEditedPostContent() || '';
				if (c !== lastContent) {
					lastContent = c;
					scheduleRecalc();
				}
			});
		}
	});
})(jQuery);
