(function ($) {
	'use strict';

	function boot(cfg) {
		if (!cfg || !cfg.selectId || !cfg.textareaId || !cfg.presets) {
			return;
		}
		var $select = $('#' + cfg.selectId);
		var $textarea = $('#' + cfg.textareaId);
		var $desc = $select.closest('p').find('.spokora-schema-json-preset-desc');
		if (!$select.length || !$textarea.length) {
			return;
		}

		function updateDesc() {
			var key = $select.val() || '';
			var preset = cfg.presets[key];
			$desc.text(preset && preset.description ? preset.description : '');
		}

		$select.on('change', function () {
			var key = $select.val() || '';
			var preset = cfg.presets[key];
			if (!preset || !preset.json) {
				updateDesc();
				return;
			}
			var current = ($textarea.val() || '').trim();
			if (current !== '' && !window.confirm(cfg.confirmText || 'Replace current JSON?')) {
				$select.val('');
				updateDesc();
				return;
			}
			$textarea.val(preset.json);
			$textarea.trigger('change');
			updateDesc();
		});

		updateDesc();
	}

	$(function () {
		var root = window.spokoraSchemaJsonPresets;
		if (!root || !root.instances) {
			return;
		}
		root.instances.forEach(boot);
	});
})(jQuery);
