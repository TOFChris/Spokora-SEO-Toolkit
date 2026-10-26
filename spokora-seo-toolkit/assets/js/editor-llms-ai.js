(function ($) {
	'use strict';

	var cfg = window.spokoraEditorLlmsAi || {};

	function getLiveEditorContent() {
		if (typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.select('core/editor')) {
			return wp.data.select('core/editor').getEditedPostContent() || '';
		}
		if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
			return tinyMCE.activeEditor.getContent() || '';
		}
		return '';
	}

	function runLlmsDraft(btn) {
		var postId = $('#post_ID').val();
		if (!postId) {
			window.alert(cfg.i18n.saveDraftFirst || 'Save the post as a draft first.');
			return;
		}
		var $btn = $(btn);
		var $status = $('#spokora-llms-ai-status');
		var liveContent = getLiveEditorContent();
		$btn.prop('disabled', true);
		if ($status.length) {
			$status.text(cfg.i18n.working || 'Asking the model…');
		}
		$.post(cfg.ajaxUrl || ajaxurl, {
			action: 'spokora_generate_llms',
			post_id: postId,
			post_content: liveContent,
			nonce: cfg.nonce,
		})
			.done(function (response) {
				if (response.success) {
					$('#spokora_llms_summary').val(response.data.summary || '');
					$('#spokora_llms_md').val(response.data.markdown || '');
					var $public = $('input[name="spokora_llms_public"]');
					if ($public.length && !$public.is(':checked')) {
						$public.prop('checked', true);
					}
					var $include = $('input[name="spokora_llms_include"]');
					if ($include.length && !$include.is(':checked')) {
						$include.prop('checked', true);
					}
					if ($status.length) {
						$status.text(
							response.data.source_label
								? (cfg.i18n.doneFrom || 'Draft ready (from:') + ' ' + response.data.source_label + ')'
								: cfg.i18n.done || 'Draft ready. Review, then Update or Publish.'
						);
					}
				} else {
					window.alert(response.data || cfg.i18n.noContent || 'Could not generate AI doc.');
					if ($status.length) {
						$status.text('');
					}
				}
			})
			.fail(function () {
				window.alert(cfg.i18n.failed || 'Server communication failed.');
				if ($status.length) {
					$status.text('');
				}
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	}

	$(document).on('click', '#spokora-llms-ai-trigger', function (e) {
		e.preventDefault();
		runLlmsDraft(this);
	});
})(jQuery);
