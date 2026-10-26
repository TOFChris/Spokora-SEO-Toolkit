(function () {
	'use strict';

	var cfg = window.spokoraEditorMetaboxUi || {};
	var brand = cfg.brandLabel || 'Spokora';
	var metaLabels = cfg.metaLabels || ['Meta boxes', 'Metaboxes'];
	var labelSet = {};

	metaLabels.forEach(function (label) {
		labelSet[String(label).trim().toLowerCase()] = true;
	});
	labelSet['meta boxes'] = true;
	labelSet.metaboxes = true;

	function shouldRename(text) {
		var t = (text || '').trim();
		if (!t || t === brand) {
			return false;
		}
		if (labelSet[t.toLowerCase()]) {
			return true;
		}
		return /^meta\s*boxes$/i.test(t);
	}

	function applyBrand(el) {
		if (!el || el.getAttribute('data-spokora-brand') === '1') {
			return false;
		}
		if (el.closest && el.closest('.spokora-seo-score-panel, .spokora-fold')) {
			return false;
		}
		var changed = false;
		var label = el.getAttribute('aria-label');
		if (label && shouldRename(label)) {
			el.setAttribute('aria-label', brand);
			changed = true;
		}
		var node;
		var walk = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
		while ((node = walk.nextNode())) {
			if (shouldRename(node.nodeValue)) {
				node.nodeValue = brand;
				changed = true;
			}
		}
		if (changed) {
			el.setAttribute('data-spokora-brand', '1');
		}
		return changed;
	}

	function renameMetaBoxesArea() {
		var selector =
			'.edit-post-meta-boxes-area > details > summary, ' +
			'.edit-post-meta-boxes-main > details > summary, ' +
			'.edit-post-layout__metaboxes > details > summary, ' +
			'.metabox-location-normal > details > summary';
		document.querySelectorAll(selector).forEach(applyBrand);
	}

	function reorderPostboxes() {
		var wanted = cfg.boxOrder || ['spokora_seo_meta_box', 'spokora_llms_meta_box'];
		var order = wanted.filter(function (id) {
			return !!document.getElementById(id);
		});
		if (!order.length) {
			return;
		}
		document.querySelectorAll('.metabox-location-normal .meta-box-sortables, #normal-sortables').forEach(function (container) {
			for (var i = order.length - 1; i >= 0; i--) {
				var box = document.getElementById(order[i]);
				if (box && box.parentNode === container && container.firstChild !== box) {
					container.insertBefore(box, container.firstChild);
				}
			}
		});
	}

	function isFoldSummary(summary) {
		if (!summary || summary.tagName !== 'SUMMARY') {
			return false;
		}
		if (summary.closest('.spokora-seo-score-panel, .spokora-fold')) {
			return true;
		}
		var details = summary.parentElement;
		if (details && details.tagName === 'DETAILS' && details.classList.contains('postbox')) {
			return true;
		}
		if (summary.classList.contains('hndle') || summary.classList.contains('handlediv')) {
			return true;
		}
		return false;
	}

	document.addEventListener(
		'click',
		function (event) {
			var summary = event.target && event.target.closest ? event.target.closest('summary') : null;
			if (!isFoldSummary(summary)) {
				return;
			}
			event.stopPropagation();
		},
		true
	);

	function boot() {
		renameMetaBoxesArea();
		reorderPostboxes();
		window.setTimeout(function () {
			renameMetaBoxesArea();
			reorderPostboxes();
		}, 400);
		window.setTimeout(renameMetaBoxesArea, 1200);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
