(function () {
	var cfg = window.spokoraAdminMenu;
	if (!cfg || !cfg.menuId) {
		return;
	}

	function pageOf(href) {
		var match = String(href || "").match(/[?&]page=([^&#]+)/);
		return match ? decodeURIComponent(match[1]) : "";
	}

	function groupMenu() {
		var root = document.getElementById(cfg.menuId);
		if (!root) {
			return;
		}
		var list = root.querySelector(".wp-submenu");
		if (!list || list.getAttribute("data-spokora-grouped") === "1") {
			return;
		}
		list.setAttribute("data-spokora-grouped", "1");
		root.classList.add("spokora-menu-ready");

		var lookup = {};
		cfg.areas.forEach(function (area) {
			(area.pages || []).forEach(function (page) {
				lookup[page] = area.id;
			});
		});

		function areaFor(href) {
			var page = pageOf(href);
			if (page && cfg.pinned && cfg.pinned.indexOf(page) !== -1) {
				return "";
			}
			if (page && lookup[page]) {
				return lookup[page];
			}
			var raw = String(href || "");
			for (var i = 0; i < cfg.areas.length; i++) {
				var needles = cfg.areas[i].needles || [];
				for (var n = 0; n < needles.length; n++) {
					if (raw.indexOf(needles[n]) !== -1) {
						return cfg.areas[i].id;
					}
				}
			}
			if (!page || page === cfg.home) {
				return "";
			}
			return "more";
		}

		var head = list.querySelector(".wp-submenu-head");
		var home = [];
		var buckets = {};
		cfg.areas.forEach(function (area) {
			buckets[area.id] = [];
		});

		Array.prototype.slice.call(list.children).forEach(function (li) {
			if (li === head) {
				return;
			}
			var link = li.querySelector("a");
			var href = link ? link.getAttribute("href") : "";
			var id = areaFor(href);
			if (!id) {
				home.push(li);
				return;
			}
			if (!buckets[id]) {
				buckets[id] = [];
			}
			li.classList.add("spokora-menu-child");
			li.setAttribute("data-spokora-area", id);
			buckets[id].push(li);
		});

		var current = "";
		home.concat.apply(home, Object.keys(buckets).map(function (id) {
			return buckets[id];
		})).forEach(function (li) {
			if (li.classList.contains("current")) {
				current = li.getAttribute("data-spokora-area") || "";
			}
		});

		Array.prototype.slice.call(list.children).forEach(function (li) {
			if (li !== head) {
				li.remove();
			}
		});
		home.forEach(function (li) {
			list.appendChild(li);
		});

		var canHover = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
		var openTimer = null;
		var closeTimer = null;

		function placeFlyout(row) {
			var flyout = row.querySelector(".spokora-menu-flyout");
			if (!flyout) {
				return;
			}
			flyout.style.top = "0px";
			if (!row.classList.contains("is-open")) {
				return;
			}
			var rect = flyout.getBoundingClientRect();
			var overflow = rect.bottom - (window.innerHeight - 8);
			if (overflow > 0) {
				flyout.style.top = (-overflow) + "px";
			}
		}

		function setOpen(exceptId) {
			list.querySelectorAll(".spokora-menu-label").forEach(function (row) {
				var on = row.getAttribute("data-spokora-area") === exceptId;
				row.classList.toggle("is-open", on);
				var button = row.querySelector(".spokora-menu-toggle");
				if (button) {
					button.setAttribute("aria-expanded", on ? "true" : "false");
				}
				if (!on) {
					placeFlyout(row);
				}
			});
			window.requestAnimationFrame(function () {
				var openRow = list.querySelector(".spokora-menu-label.is-open");
				if (openRow) {
					placeFlyout(openRow);
				}
			});
		}

		function scheduleOpen(id) {
			clearTimeout(closeTimer);
			clearTimeout(openTimer);
			openTimer = setTimeout(function () {
				setOpen(id);
			}, 280);
		}

		function scheduleClose() {
			clearTimeout(openTimer);
			clearTimeout(closeTimer);
			closeTimer = setTimeout(function () {
				setOpen("");
			}, 420);
		}

		function sortKidsForArea(areaId, kids) {
			var area = cfg.areas.filter(function (a) {
				return a.id === areaId;
			})[0];
			if (!area || !area.pages || !area.pages.length) {
				return kids;
			}
			var order = area.pages;
			return kids.slice().sort(function (a, b) {
				var la = a.querySelector("a");
				var lb = b.querySelector("a");
				var pa = la ? pageOf(la.getAttribute("href")) : "";
				var pb = lb ? pageOf(lb.getAttribute("href")) : "";
				var ia = order.indexOf(pa);
				var ib = order.indexOf(pb);
				if (ia < 0) {
					ia = 999;
				}
				if (ib < 0) {
					ib = 999;
				}
				return ia - ib;
			});
		}

		cfg.areas.forEach(function (area) {
			var kids = sortKidsForArea(area.id, buckets[area.id] || []);
			if (!kids.length) {
				return;
			}
			var item = document.createElement("li");
			item.className = "spokora-menu-label";
			item.setAttribute("data-spokora-area", area.id);
			if (current === area.id) {
				item.classList.add("is-current");
			}
			var button = document.createElement("button");
			button.type = "button";
			button.className = "spokora-menu-toggle";
			button.setAttribute("aria-expanded", "false");
			button.setAttribute("aria-haspopup", "true");
			button.setAttribute("aria-controls", "spokora-flyout-" + area.id);
			var name = document.createElement("span");
			name.textContent = area.label;
			var chevron = document.createElement("span");
			chevron.className = "spokora-menu-chevron";
			chevron.setAttribute("aria-hidden", "true");
			button.appendChild(name);
			button.appendChild(chevron);
			var flyout = document.createElement("ul");
			flyout.className = "spokora-menu-flyout";
			flyout.id = "spokora-flyout-" + area.id;
			kids.forEach(function (li) {
				flyout.appendChild(li);
			});
			item.appendChild(button);
			item.appendChild(flyout);
			list.appendChild(item);

			var ignoreFocusOpen = false;
			item.addEventListener("mouseenter", function () {
				if (canHover) {
					scheduleOpen(area.id);
				}
			});
			item.addEventListener("mouseleave", function () {
				if (canHover) {
					scheduleClose();
				}
			});
			button.addEventListener("pointerdown", function () {
				ignoreFocusOpen = true;
			});
			button.addEventListener("focus", function () {
				if (ignoreFocusOpen) {
					ignoreFocusOpen = false;
					return;
				}
				scheduleOpen(area.id);
			});
			item.addEventListener("focusout", function (event) {
				if (!item.contains(event.relatedTarget)) {
					scheduleClose();
				}
			});
			button.addEventListener("click", function (event) {
				event.preventDefault();
				event.stopPropagation();
				var open = item.classList.contains("is-open");
				clearTimeout(openTimer);
				clearTimeout(closeTimer);
				setOpen(open ? "" : area.id);
			});
		});

		document.addEventListener("keydown", function (event) {
			if (event.key === "Escape") {
				clearTimeout(openTimer);
				clearTimeout(closeTimer);
				setOpen("");
			}
		});
	}

	function groupSubnav() {
		var canHover = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
		document.querySelectorAll(".spokora-subnav").forEach(function (nav) {
			nav.querySelectorAll(".spokora-subnav__group-btn").forEach(function (button) {
				var group = button.parentElement;
				function openGroup(toggle) {
					var willOpen = toggle ? !group.classList.contains("is-open") : true;
					nav.querySelectorAll(".spokora-subnav__group").forEach(function (el) {
						var on = el === group && willOpen;
						el.classList.toggle("is-open", on);
						var control = el.querySelector(".spokora-subnav__group-btn");
						if (control) {
							control.setAttribute("aria-expanded", on ? "true" : "false");
						}
					});
				}
				button.addEventListener("click", function () {
					openGroup(true);
				});
				if (canHover) {
					button.addEventListener("mouseenter", function () {
						openGroup(false);
					});
				}
			});
		});
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			groupMenu();
			groupSubnav();
		});
	} else {
		groupMenu();
		groupSubnav();
	}
})();
