(function ($) {
	'use strict';

	if (typeof inlineEditPost === 'undefined') {
		return;
	}

	var wpEdit = inlineEditPost.edit;

	inlineEditPost.edit = function (id) {
		wpEdit.apply(this, arguments);
		var postId = 0;
		if (typeof id === 'object') {
			postId = parseInt(this.getId(id), 10);
		} else {
			postId = parseInt(id, 10);
		}
		if (!postId) {
			return;
		}
		var $row = $('#post-' + postId);
		var $edit = $('#edit-' + postId);
		var raw = $row.find('.spokora-inline-json').val() || $row.find('.spokora-inline-json').text();
		if (!raw) {
			return;
		}
		var data;
		try {
			data = JSON.parse(raw);
		} catch (e) {
			return;
		}
		$edit.find('input[name="spokora_seo_title"]').val(data.title || '');
		$edit.find('textarea[name="spokora_seo_desc"]').val(data.desc || '');
		$edit.find('input[name="spokora_focus_keyword"]').val(data.keyword || '');
		$edit.find('textarea[name="spokora_additional_keywords"]').val(data.keywords || '');
		$edit.find('select[name="spokora_content_kind"]').val(data.kind || '');
		$edit.find('input[name="spokora_seo_score_exclude"]').prop('checked', data.exclude === '1');
		$edit.find('input[name="spokora_llms_public"]').prop('checked', data.public === '1');
		$edit.find('input[name="spokora_llms_include"]').prop('checked', data.include === '1');
		$edit.find('input[name="spokora_llms_summary"]').val(data.summary || '');
		$edit.find('textarea[name="spokora_llms_md"]').val(data.md || '');
	};
})(jQuery);
