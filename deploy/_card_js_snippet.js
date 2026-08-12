		var fieldLabels = {};
		var defaultsMap = {};
		try { fieldLabels = JSON.parse($("#ssCardFieldLabels").text() || "{}"); } catch (e) {}
		try { defaultsMap = JSON.parse($("#ssCardDefaultsBoot").text() || "{}"); } catch (e) {}

		var cardScopes = {};

		function createCardScope($root) {
			var audience = $root.data("audience") || "student";
			var prefix = audience === "staff" ? "sf" : "st";
			var $live = $root.find(".card-live-preview").first();
			var $oriChoice = $root.find(".card-ori-choice").first();
			var $tplChoice = $root.find(".card-tpl-choice").first();
			var $bgModeChoice = $root.find(".card-bg-mode-choice").first();
			var $aiPanel = $root.find(".card-ai-panel").first();
			var $canvas = $root.find(".ss-editor-canvas").first();
			var $liveBg = $root.find(".ss-ed-bg").first();
			var $items = $root.find(".ss-editor-items").first();
			var $toggles = $root.find(".card-field-toggles").first();
			var $status = $root.find(".card-layout-status").first();
			var $imgBg = $root.find(".ss-bg-preview-img").first();
			var $bgFrame = $root.find("[data-bg-frame]").first();
			var $clr = $root.find(".btn-clear-bg").first();
			var oriField = audience === "staff" ? "sf_card_orientation" : "card_orientation";
			var bgModeField = audience === "staff" ? "sf_card_bg_mode" : "card_bg_mode";
			var oriInputName = prefix + "_card_orientation";
			var layoutState = { template: "ocean", fields: {} };
			try { layoutState = JSON.parse($root.find(".card-layout-boot").first().text() || "{}"); } catch (e) {}
			if (!layoutState.fields) layoutState.fields = {};

			var sampleVals = {
				logo: "",
				school_name: $live.data("school") || "School",
				header1: $live.data("header1") || "",
				header2: $live.data("header2") || "",
				badge: $live.data("badge") || (audience === "staff" ? "STAFF CARD" : "STUDENT CARD"),
				photo: "PHOTO",
				names: audience === "staff" ? "Sample Staff" : "Sample Student",
				regno: audience === "staff" ? "STF001" : "260240001",
				class: audience === "staff" ? "Teacher" : "P1",
				father: "Father name",
				phone: "0780000000",
				mode: audience === "staff" ? "FULL TIME" : "BOARDING",
				validity: "A.Y 2026-2027",
				signature: "",
				moto: $live.data("moto") || "SmartSMS"
			};

			function currentOrientation() {
				return $root.find("input[name='" + oriInputName + "']:checked").val() || "landscape";
			}
			function syncBgFrame() {
				var ori = currentOrientation();
				$bgFrame.toggleClass("is-portrait", ori === "portrait").toggleClass("is-landscape", ori !== "portrait");
			}
			function syncCanvasSize() {
				var ori = currentOrientation();
				var tpl = layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean";
				$canvas
					.toggleClass("is-portrait", ori === "portrait")
					.toggleClass("is-landscape", ori !== "portrait")
					.toggleClass("is-ocean", tpl === "ocean")
					.toggleClass("is-geo", tpl === "geo");
				syncBgFrame();
			}
			function refreshEditorBg() {
				var tpl = layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean";
				if (tpl === "ocean") {
					$liveBg.css({ "background-image": "none", "background-color": "#0B1F4A" });
					return;
				}
				var bgSrc = ($imgBg.attr("src") || "").split("?")[0];
				var isEmptyBg = $imgBg.hasClass("is-empty") || !bgSrc || bgSrc.indexOf("white_blank") !== -1 || bgSrc.indexOf("no_image") !== -1 || bgSrc.indexOf("fallback-") !== -1;
				if (isEmptyBg) {
					$liveBg.css({ "background-image": "none", "background-color": "#ffffff" });
				} else {
					$liveBg.css({
						"background-color": "transparent",
						"background-image": "url('" + ($imgBg.attr("src") || bgSrc).replace(/'/g, "%27") + "')"
					});
				}
			}
			function applyTemplateDefaults(template, orientation) {
				var pack = (defaultsMap[template] && defaultsMap[template][orientation]) || { template: template, fields: {} };
				layoutState = JSON.parse(JSON.stringify(pack));
				layoutState.template = template;
				layoutState.orientation = orientation;
				renderEditor();
			}
			function renderEditor() {
				syncCanvasSize();
				refreshEditorBg();
				$items.empty();
				$toggles.empty();
				var main = $("input[data-target='main_color']").val() || "#0EA5E9";
				var logoSrc = $("#img_logo").attr("src") || LOGO_FALLBACK;
				var sigSrc = $("#img_headmaster_signature").attr("src") || "";
				Object.keys(fieldLabels).forEach(function (key) {
					var f = layoutState.fields[key] || { x: 5, y: 5, w: 30, h: 8, visible: true };
					layoutState.fields[key] = f;
					var $item = $('<div class="ss-ed-item"></div>').attr("data-key", key);
					if (!f.visible) $item.addClass("is-hidden");
					$item.css({ left: f.x + "%", top: f.y + "%", width: f.w + "%", height: f.h + "%" });
					if (key === "logo") {
						var lsrc = (logoSrc && logoSrc.indexOf("white_blank") === -1) ? logoSrc : LOGO_FALLBACK;
						$item.html('<img src="' + lsrc + '" alt="Logo">');
					} else if (key === "photo") {
						$item.html('<img src="' + FALLBACK + '" alt="Photo">').css("border-color", main);
					} else if (key === "badge" || key === "moto") {
						$item.text(sampleVals[key] || fieldLabels[key]).css("background", main);
					} else if (key === "signature") {
						if (sigSrc && sigSrc.indexOf("white_blank") === -1 && !$("#img_headmaster_signature").hasClass("is-empty") && sigSrc.indexOf("fallback-") === -1) {
							$item.html('<img src="' + sigSrc + '" alt=""><div style="font-size:9px;border-top:1px solid #94a3b8;margin-top:2px;">' + ($live.data("head") || "Headmaster") + '</div>');
						} else {
							$item.html('<img src="' + SIG_FALLBACK + '" alt=""><div style="font-size:9px;border-top:1px solid #94a3b8;margin-top:2px;">' + ($live.data("head") || "Headmaster") + '</div>');
						}
					} else if (["names","regno","class","father","phone","mode","validity"].indexOf(key) >= 0) {
						$item.html('<span class="ss-ed-label">' + fieldLabels[key] + '</span>' + (sampleVals[key] || ""));
					} else {
						$item.html('<span class="ss-ed-label">' + fieldLabels[key] + '</span> ' + (sampleVals[key] || ""));
					}
					$items.append($item);
					var $lab = $('<label><input type="checkbox" data-toggle-field="' + key + '"> ' + fieldLabels[key] + '</label>');
					$lab.find("input").prop("checked", !!f.visible);
					$toggles.append($lab);
				});
				bindDrag();
			}
			function bindDrag() {
				var dragging = null;
				$items.find(".ss-ed-item").off(".ssdrag").on("mousedown.ssdrag touchstart.ssdrag", function (ev) {
					ev.preventDefault();
					var $el = $(this);
					$items.find(".ss-ed-item").removeClass("is-active");
					$el.addClass("is-active");
					var canvas = $canvas[0];
					var rect = canvas.getBoundingClientRect();
					var pt = ev.type.indexOf("touch") === 0 ? ev.originalEvent.touches[0] : ev;
					dragging = {
						key: $el.data("key"),
						el: $el,
						ox: pt.clientX - $el[0].getBoundingClientRect().left,
						oy: pt.clientY - $el[0].getBoundingClientRect().top,
						rect: rect
					};
				});
				$(document).off(".ssdragmove." + audience).on("mousemove.ssdragmove." + audience + " touchmove.ssdragmove." + audience, function (ev) {
					if (!dragging) return;
					var pt = ev.type.indexOf("touch") === 0 ? ev.originalEvent.touches[0] : ev;
					var x = ((pt.clientX - dragging.rect.left - dragging.ox) / dragging.rect.width) * 100;
					var y = ((pt.clientY - dragging.rect.top - dragging.oy) / dragging.rect.height) * 100;
					x = Math.max(0, Math.min(95, x));
					y = Math.max(0, Math.min(95, y));
					dragging.el.css({ left: x + "%", top: y + "%" });
					if (layoutState.fields[dragging.key]) {
						layoutState.fields[dragging.key].x = Math.round(x * 10) / 10;
						layoutState.fields[dragging.key].y = Math.round(y * 10) / 10;
					}
				}).on("mouseup.ssdragmove." + audience + " touchend.ssdragmove." + audience, function () {
					dragging = null;
				});
			}

			$root.on("change", "[data-toggle-field]", function () {
				var key = $(this).data("toggle-field");
				if (!layoutState.fields[key]) return;
				layoutState.fields[key].visible = $(this).is(":checked");
				renderEditor();
			});
			$tplChoice.on("click", ".ss-tpl-card", function () {
				var tpl = $(this).data("template");
				var ori = $(this).data("orientation") || "landscape";
				$tplChoice.find(".ss-tpl-card").removeClass("is-on");
				$(this).addClass("is-on");
				$root.find("input[name='" + oriInputName + "'][value='" + ori + "']").prop("checked", true);
				$oriChoice.find("label").removeClass("is-on");
				$root.find("input[name='" + oriInputName + "']:checked").closest("label").addClass("is-on");
				applyTemplateDefaults(tpl, ori);
				$status.text("Template “" + tpl + "” (" + ori + ") loaded — save to keep.");
			});
			$root.on("change", "input[name='" + oriInputName + "']", function () {
				var ori = $(this).val();
				var tpl = layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean";
				$oriChoice.find("label").removeClass("is-on");
				$(this).closest("label").addClass("is-on");
				applyTemplateDefaults(tpl, ori);
				$status.text("Orientation changed — preview updated. Save template to keep.");
				saveCardPreset(oriField, ori, $oriChoice);
			});
			$root.on("change", "input[name='" + prefix + "_card_bg_mode']", function () {
				var mode = $(this).val();
				saveCardPreset(bgModeField, mode, $bgModeChoice);
				$aiPanel.toggle(mode === "smart");
			});
			$root.on("click", ".btn-save-card-layout", function () {
				$status.text("Saving…");
				$.post("<?= base_url('save_card_layout'); ?>", {
					audience: audience,
					template: layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean",
					orientation: currentOrientation(),
					fields: JSON.stringify(layoutState.fields || {})
				}, function (data) {
					if (data && data.success) {
						if (data.orientation) {
							$root.find("input[name='" + oriInputName + "'][value='" + data.orientation + "']").prop("checked", true);
							$oriChoice.find("label").removeClass("is-on");
							$root.find("input[name='" + oriInputName + "']:checked").closest("label").addClass("is-on");
							syncCanvasSize();
						}
						$status.text("Template saved");
						toastada.success(data.success);
					} else {
						$status.text("");
						toastada.error((data && data.error) || "Save failed");
					}
				}, "json").fail(function (xhr) {
					$status.text("");
					var msg = "Save failed";
					try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
					toastada.error(msg);
				});
			});
			$root.on("click", ".btn-reset-card-layout", function () {
				var tpl = layoutState.template || ($tplChoice.find(".ss-tpl-card.is-on").data("template")) || "ocean";
				$.post("<?= base_url('reset_card_layout'); ?>", {
					audience: audience,
					template: tpl,
					orientation: currentOrientation()
				}, function (data) {
					if (data && data.layout) {
						layoutState = data.layout;
						if (!layoutState.fields) layoutState.fields = {};
						if (data.orientation) {
							$root.find("input[name='" + oriInputName + "'][value='" + data.orientation + "']").prop("checked", true);
							$oriChoice.find("label").removeClass("is-on");
							$root.find("input[name='" + oriInputName + "']:checked").closest("label").addClass("is-on");
						}
						renderEditor();
						$status.text("Reset to template defaults");
						toastada.success(data.success || "Reset");
					} else {
						toastada.error((data && data.error) || "Reset failed");
					}
				}, "json");
			});
			$root.on("click", ".btn-generate-card-bg", function () {
				var $btn = $(this);
				var $st = $root.find(".card-ai-status").first();
				$btn.prop("disabled", true);
				$st.text("Generating with AI…");
				$.ajax({
					url: "<?= base_url('generate_card_background'); ?>",
					method: "POST",
					data: { type: audience, orientation: currentOrientation() },
					dataType: "json",
					timeout: 130000
				}).done(function (data) {
					if (data && data.success && data.url) {
						var url = data.url + (data.url.indexOf("?") >= 0 ? "&" : "?") + "v=" + Date.now();
						setImgReal($imgBg, url);
						$clr.show();
						$liveBg.css({
							"background-image": "url('" + url.replace(/'/g, "%27") + "')",
							"background-color": "transparent"
						});
						refreshEditorBg();
						if (data.source === "ai" || data.source === "gemini") {
							$st.text("Generated with AI");
							toastada.success(data.success);
						} else {
							$st.text("White blank fallback" + (data.error ? (": " + data.error) : ""));
							toastada.error("AI generation failed — white blank saved. " + (data.error || ""));
						}
					} else {
						$st.text("");
						toastada.error((data && (data.error || data.msg)) || "<?= lang("app.fatalErr"); ?>");
					}
				}).fail(function (xhr) {
					$st.text("");
					var msg = "<?= lang("app.systemErr"); ?>";
					try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
					toastada.error(msg);
				}).always(function () {
					$btn.prop("disabled", false);
				});
			});

			renderEditor();
			return {
				audience: audience,
				render: renderEditor,
				refreshBg: refreshEditorBg,
				sync: syncCanvasSize,
				$imgBg: $imgBg,
				$clr: $clr
			};
		}

		$(".card-audience").each(function () {
			var scope = createCardScope($(this));
			cardScopes[scope.audience] = scope;
		});

		function refreshLivePreview() {
			Object.keys(cardScopes).forEach(function (k) {
				if (cardScopes[k] && cardScopes[k].render) cardScopes[k].render();
			});
		}

		$(document).on("click", ".btn-clear-bg", function () {
			var $btn = $(this);
			var field = $btn.data("target-field");
			var imgSel = $btn.data("imageview");
			if (!confirm('<?= lang("app.clearBackground"); ?>?')) return;
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=" + field + "&val=", function (data) {
				if (data.hasOwnProperty("success")) {
					setImgFallback($(imgSel), BG_FALLBACK);
					$btn.hide();
					refreshLivePreview();
					toastada.success('<?= lang("app.saveSuccess");?>');
				} else {
					toastada.error((data && data.error) || '<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});

		// After background upload, refresh matching scope
		$(document).on("change", ".in_card_backg", function () {
			setTimeout(refreshLivePreview, 800);
		});

		function saveCardPreset(target, val, $group) {
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=" + target + "&val=" + encodeURIComponent(val), function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.saveFail");?>' + (data.error || data.msg || ''));
					return;
				}
				if ($group && $group.length) {
					$group.find("label").removeClass("is-on");
					$group.find("input[value='" + val + "']").closest("label").addClass("is-on");
				}
				refreshLivePreview();
				toastada.success('<?= lang("app.saveSuccess");?>');
			}).fail(function () {
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		}

		$(document).on("focusout", ".sptxt", function () {
			setTimeout(refreshLivePreview, 200);
		});

		// Keep legacy clear handlers working if still present
		$(document).on("click","#btn-remove-signature",function () {
			if (!confirm('<?= lang("app.removalSignature"); ?>'))
				return;
			var id = $("#settings_section").data("id");
			$.post("<?=base_url('manipulate_settings/');?>text", "id=" + id + "&target=headmaster_signature&val=", function (data) {
				if (data.hasOwnProperty("error")) {
					toastada.error('<?= lang("app.removalSignatureFail");?>' + data.msg);
				} else if (data.hasOwnProperty("success")) {
					setImgFallback($("#img_headmaster_signature"), SIG_FALLBACK);
					$("#btn-remove-signature").hide();
					refreshLivePreview();
					toastada.success('<?=lang("app.removalSignatureSuccess"); ?>');
				} else {
					toastada.error('<?= lang("app.fatalErr"); ?>');
				}
			}).fail(function () {
				toastada.error('<?= lang("app.systemErr"); ?>');
			});
		});
