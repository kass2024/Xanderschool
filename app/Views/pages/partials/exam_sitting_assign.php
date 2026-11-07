<?php
$plans = $exam_sittings ?? [];
$sitClasses = $classes ?? [];
$openPlan = (int) ($open_plan ?? 0);
?>
<style>
	.sit-assign { width: 100%; max-width: 100%; min-width: 0; }
	.sit-assign * { box-sizing: border-box; }
	.sit-intro { color: #64748b; margin: 0 0 14px; line-height: 1.45; }
	.sit-plan {
		border: 1px solid #e2e8f0;
		border-radius: 12px;
		padding: 12px;
		margin-bottom: 10px;
		background: #fff;
		min-width: 0;
	}
	.sit-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; align-items: center; }
	.sit-head h6 { margin: 0; min-width: 0; overflow-wrap: anywhere; }
	.sit-meta { color: #64748b; font-size: .85rem; margin: 6px 0 0; }
	.sit-assign label { display: block; font-size: .78rem; font-weight: 650; color: #475569; margin-bottom: 4px; }
	.sit-assign .form-control { width: 100%; max-width: 100%; }
	.sit-classes {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
		gap: 8px;
		max-height: 280px;
		overflow: auto;
		margin: 8px 0 12px;
		padding-right: 2px;
	}
	.sit-classes label {
		display: flex;
		align-items: flex-start;
		gap: 6px;
		margin: 0;
		font-size: .84rem;
		font-weight: 500;
		color: #0f172a;
		border: 1px solid #e2e8f0;
		border-radius: 8px;
		padding: 7px 8px;
		background: #f8fafc;
		overflow-wrap: anywhere;
	}
	.sit-all { margin: 8px 0; font-weight: 650; color: #0f172a; }
	.sit-bands { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 12px; }
	.sit-bands label, .sit-band-h {
		display: flex; align-items: center; gap: 8px; margin: 0;
		border: 1px solid #dbe3ee; border-radius: 10px; background: #fff;
		padding: 8px 12px; font-weight: 700; color: #0f172a;
	}
	.sit-band-h { margin: 14px 0 8px; background: #f8fafc; }
	.sit-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
	.sit-rooms { margin: 8px 0 0; padding: 0; list-style: none; }
	.sit-rooms li { padding: 6px 0; border-bottom: 1px solid #f1f5f9; overflow-wrap: anywhere; }
	.sit-board { width: 100%; max-width: 100%; overflow-x: auto; margin-top: 8px; }
	.sit-grid {
		display: grid;
		gap: 14px;
		grid-template-columns: repeat(var(--sit-cols, 2), minmax(280px, 1fr));
		align-items: start;
		min-width: calc(var(--sit-cols, 2) * 280px);
	}
	.sit-col { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
	.sit-col-h { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #0369a1; border-bottom: 2px solid #0284c7; padding-bottom: 4px; }
	.sit-table { border: 1px solid #dbe3ee; border-radius: 14px; background: #fff; overflow: hidden; box-shadow: 0 8px 20px rgba(15,23,42,.05); min-width: 0; }
	.sit-table-h { background: linear-gradient(180deg, #012F6B 0%, #0b3f86 100%); color: #fff; font-size: .78rem; font-weight: 700; letter-spacing: .03em; padding: 8px 12px; }
	.sit-seats { display: grid; grid-template-columns: repeat(var(--places, 2), minmax(0, 1fr)); }
	.sit-seat { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 6px; padding: 12px 10px; min-width: 0; border-left: 1px solid #e8eef5; }
	.sit-seat:first-child { border-left: 0; }
	.sit-seat code { font-weight: 700; color: #012F6B; font-size: .78rem; overflow-wrap: anywhere; }
	.sit-seat .sit-name { font-weight: 650; color: #0f172a; overflow-wrap: anywhere; line-height: 1.25; }
	.sit-seat .sit-class { display: inline-block; background: #e0f2fe; color: #0369a1; border-radius: 999px; padding: 2px 8px; font-size: .72rem; font-weight: 700; }
	.sit-seat.is-empty .sit-name { color: #94a3b8; font-weight: 500; }
	.sit-warn { color: #9a3412; }
	.sit-unseated { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
	.sit-unseated span { background: #fff7ed; border-radius: 8px; padding: 4px 8px; font-size: .8rem; }
	.sit-pair { border: 1px solid #dbe3ee; border-radius: 12px; padding: 10px; margin: 8px 0; background: #f8fafc; }
	.sit-pair-h { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-weight: 700; margin-bottom: 8px; }
	.sit-pair .sit-classes { max-height: 180px; }
</style>
<div class="sit-assign">
	<p class="sit-intro">Choose Nursery, Primary, or High school. Each level is seated on its own. The high school plans already saved stay on High school. A table only mixes classes from that level, and never two classes from the same year. Rooms are set in <a href="<?= base_url('settings#exam-sitting'); ?>">Settings</a>.</p>
	<div class="mb-3">
		<div class="btn-group" role="group" aria-label="School level">
			<button type="button" class="btn btn-outline-primary sit-level-btn" data-band="nursery">Nursery</button>
			<button type="button" class="btn btn-outline-primary sit-level-btn" data-band="primary">Primary</button>
			<button type="button" class="btn btn-primary sit-level-btn is-on" data-band="high_school">High school</button>
		</div>
	</div>
	<p class="sit-meta" id="sitLevelEmpty" style="display:none">No exam plan for this level yet. Create the rooms in Settings while this level is selected.</p>
	<?php if ($plans === []) { ?>
		<p class="sit-meta">No exam plan yet. Create the rooms in Settings, then come back here.</p>
	<?php } ?>
	<div id="sitPlans">
		<?php foreach ($plans as $plan) {
			$roomCount = count($plan['rooms'] ?? []);
			?>
			<?php $planBand = \App\Models\ExamSittingModel::normalizeBand($plan['level_band'] ?? 'high_school'); ?>
			<div class="sit-plan" data-plan="<?= (int) $plan['id']; ?>" data-band="<?= esc($planBand); ?>"<?= $planBand === 'high_school' ? '' : ' style="display:none"'; ?>>
				<div class="sit-head">
					<h6><?= esc($plan['title'] ?? 'Exam'); ?> <small class="text-muted"><?= esc(\App\Models\ExamSittingModel::bandLabel($planBand)); ?></small></h6>
					<button type="button" class="btn btn-sm btn-outline-primary sit-open">Open</button>
				</div>
				<p class="sit-meta"><?= $roomCount; ?> room<?= $roomCount === 1 ? '' : 's'; ?> · <?= (int) ($plan['capacity'] ?? 0); ?> seats<?php if ((int) ($plan['filled_count'] ?? 0) > 0) { ?> · <?= (int) $plan['filled_count']; ?> students placed<?php } ?></p>
			</div>
		<?php } ?>
	</div>
	<div id="sitEditor" style="display:none"></div>
</div>
<script>
(function () {
	var classes = <?= json_encode(array_map(static function ($class) {
		$label = trim(preg_replace('/\s+/', ' ', ($class['level_name'] ?? '') . ' ' . ($class['dept_code'] ?? '') . ' ' . ($class['title'] ?? '')) ?? '');
		return ['id' => (int) $class['id'], 'label' => $label, 'band' => (string) ($class['band'] ?? 'high_school')];
	}, $sitClasses), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
	var openPlan = <?= $openPlan; ?>;

	function esc(value) {
		return $("<div>").text(value == null ? "" : value).html();
	}
	function post(url, data) {
		return $.ajax({ url: url, type: "POST", data: data, dataType: "json" });
	}
	var currentBand = "high_school";
	var activeClasses = classes.filter(function (row) { return (row.band || "high_school") === "high_school"; });
	function bandLabel(id) {
		if (id === "nursery") return "Nursery";
		if (id === "primary") return "Primary";
		return "High school";
	}
	function applyBand() {
		var shown = 0;
		$("#sitPlans .sit-plan").each(function () {
			var on = ($(this).attr("data-band") || "high_school") === currentBand;
			$(this).toggle(on);
			if (on) shown++;
		});
		$("#sitLevelEmpty").toggle(shown === 0);
		if (($("#sitEditor").data("band") || "") && $("#sitEditor").data("band") !== currentBand) {
			$("#sitEditor").hide().empty();
		}
	}
	function classChecks(selected) {
		var rows = activeClasses;
		var all = !selected || !selected.length;
		var html = '<label class="sit-all"><input type="checkbox" id="sitAllClasses"' + (all ? ' checked' : '') + '> All ' + esc(bandLabel(currentBand)) + ' classes</label>';
		html += '<div id="sitClassList"' + (all ? ' style="display:none"' : '') + '><div class="sit-classes">';
		rows.forEach(function (row) {
			var on = !all && selected.indexOf(row.id) !== -1;
			html += '<label><input type="checkbox" class="sit-class" data-band="' + (row.band || "high_school") + '" name="class_id[]" value="' + row.id + '"' + (on ? ' checked' : '') + '> ' + esc(row.label) + '</label>';
		});
		html += '</div></div>';
		return html;
	}
	function syncBand(band) {
		var boxes = $('.sit-class[data-band="' + band + '"]');
		var on = boxes.filter(':checked').length;
		$('.sit-band[data-band="' + band + '"]').prop('checked', boxes.length > 0 && on === boxes.length);
	}
	function pairClassPool() {
		if (!$("#sitAllClasses").length || $("#sitAllClasses").is(":checked")) {
			return activeClasses.slice();
		}
		var picked = {};
		$("#sitClassList .sit-class:checked").each(function () { picked[String($(this).val())] = true; });
		return activeClasses.filter(function (row) { return picked[String(row.id)]; });
	}
	function mixPairsHtml(saved) {
		var rows = saved && saved.length ? saved : [[]];
		var html = '<h6 style="margin-top:14px">How to mix</h6>';
		html += '<p class="sit-meta">Create the pairs first. Pair 1 can be S1 A, S3 and S6 ACC. Those classes share a table. S1 A and S1 B are the same level, so they are never on one table. Another pair uses the next tables.</p>';
		html += '<div id="sitPairs">';
		rows.forEach(function (ids, index) { html += pairBlock(index, ids); });
		html += '</div>';
		html += '<button type="button" class="btn btn-outline-primary btn-sm" id="sitAddPair">Add pair</button> ';
		html += '<button type="button" class="btn btn-outline-secondary btn-sm" id="sitClearPairs">Clear pairing</button>';
		return html;
	}
	function pairBlock(index, ids) {
		var chosen = {};
		(ids || []).forEach(function (id) { chosen[String(id)] = true; });
		var html = '<div class="sit-pair"><div class="sit-pair-h"><span>Pair ' + (index + 1) + '</span>';
		html += '<button type="button" class="btn btn-sm btn-outline-danger sit-pair-remove">Remove</button></div><div class="sit-classes">';
		pairClassPool().forEach(function (row) {
			var on = chosen[String(row.id)] ? ' checked' : '';
			html += '<label><input type="checkbox" class="sit-pair-class" value="' + row.id + '"' + on + '> ' + esc(row.label) + '</label>';
		});
		html += '</div></div>';
		return html;
	}
	function readPairs() {
		var pairs = [];
		$("#sitPairs .sit-pair").each(function () {
			var ids = $(this).find(".sit-pair-class:checked").map(function () { return parseInt($(this).val(), 10); }).get();
			if (ids.length) pairs.push(ids);
		});
		return pairs;
	}
	function redrawPairs(saved) {
		var html = '';
		(saved && saved.length ? saved : [[]]).forEach(function (ids, index) { html += pairBlock(index, ids); });
		$("#sitPairs").html(html);
	}
	function seatCard(seat) {
		var empty = !(seat.student_id > 0);
		var name = empty ? "Empty" : esc(seat.student_name);
		var klass = empty ? "" : esc(seat.class_label || "");
		return '<div class="sit-seat' + (empty ? " is-empty" : "") + '"><code>' + esc(seat.code) + '</code><span class="sit-name">' + name + '</span>' + (klass ? '<span class="sit-class">' + klass + '</span>' : '') + '</div>';
	}
	function renderPlan(plan, unseated, studentCount) {
		if (!plan) return;
		currentBand = plan.level_band || "high_school";
		activeClasses = classes.filter(function (row) { return (row.band || "high_school") === currentBand; });
		var rooms = plan.rooms || [];
		var html = '<div class="sit-plan"><h6>' + esc(plan.title) + ' <small class="text-muted">' + esc(bandLabel(currentBand)) + '</small></h6>';
		html += '<p class="sit-meta">Only ' + esc(bandLabel(currentBand)) + ' classes are listed. Choose who sits, then assign.</p><ul class="sit-rooms">';
		rooms.forEach(function (room) {
			var cap = (parseInt(room.tables_count, 10) || 0) * (parseInt(room.students_per_table, 10) || 0);
			html += '<li><b>' + esc(room.room_name) + '</b> · ' + cap + ' seats';
			if ((room.seats || []).length) {
				html += ' · <a href="<?= base_url('settings/exam-sitting/excel/'); ?>' + plan.id + '/' + room.id + '">Excel</a>';
			}
			html += '</li>';
		});
		html += '</ul>';
		if (!rooms.length) {
			html += '<p class="sit-warn">This plan has no rooms yet. Add them in Settings.</p>';
		}
		html += '<h6 style="margin-top:14px">Students to seat</h6>' + classChecks(plan.class_ids || []);
		html += mixPairsHtml(plan.mix_pairs || [[]]);
		html += '<div class="sit-actions">';
		html += '<button type="button" class="btn btn-success" id="sitAssign">Randomly assign seats</button>';
		html += '<a class="btn btn-outline-secondary" target="_blank" href="<?= base_url('settings/exam-sitting/print/'); ?>' + plan.id + '">Print</a>';
		var anySeats = rooms.some(function (room) { return (room.seats || []).length > 0; });
		if (anySeats) {
			html += '<a class="btn btn-outline-primary" href="<?= base_url('settings/exam-sitting/excel/'); ?>' + plan.id + '">Download all rooms Excel</a>';
		}
		html += '</div>';
		if (studentCount) {
			html += '<p class="sit-meta">' + studentCount + ' students placed into the rooms.</p>';
		}
		if (unseated && unseated.length) {
			html += '<p class="sit-warn">' + unseated.length + ' students were not seated. Their pair ran out of tables. Each table takes one student from each level in the pair, so S1 A and S1 B are not together.</p>';
			html += '<div class="sit-unseated">';
			unseated.forEach(function (row) {
				html += '<span>' + esc(row.name) + (row.class_label ? ' · ' + esc(row.class_label) : '') + '</span>';
			});
			html += '</div>';
		}
		rooms.forEach(function (room) {
			var seats = room.seats || [];
			if (!seats.length) return;
			var cols = Math.max(1, parseInt(room.columns_count, 10) || 1);
			var byCol = {};
			seats.forEach(function (seat) {
				var tableNo = parseInt(seat.table_no, 10) || 1;
				var col = parseInt(seat.column_no, 10) || (((tableNo - 1) % cols) + 1);
				if (!byCol[col]) byCol[col] = {};
				if (!byCol[col][tableNo]) byCol[col][tableNo] = [];
				byCol[col][tableNo].push(seat);
			});
			html += '<div style="margin-top:16px"><strong>' + esc(room.room_name) + '</strong>';
			html += '<div class="sit-board"><div class="sit-grid" style="--sit-cols:' + cols + '">';
			for (var col = 1; col <= cols; col++) {
				html += '<div class="sit-col"><div class="sit-col-h">Column ' + col + '</div>';
				var tables = byCol[col] || {};
				Object.keys(tables).sort(function (a, b) { return a - b; }).forEach(function (key) {
					var people = tables[key].slice().sort(function (a, b) {
						return (parseInt(a.place_on_table, 10) || 0) - (parseInt(b.place_on_table, 10) || 0);
					});
					html += '<div class="sit-table"><div class="sit-table-h">Table ' + esc(key) + '</div>';
					html += '<div class="sit-seats" style="--places:' + Math.max(1, people.length) + '">';
					people.forEach(function (seat) { html += seatCard(seat); });
					html += '</div></div>';
				});
				html += '</div>';
			}
			html += '</div></div></div>';
		});
		html += '</div>';
		$("#sitEditor").html(html).show();
		$("#sitEditor").data("plan", plan.id);
		$("#sitEditor").data("band", currentBand);
		$(".sit-level-btn").removeClass("btn-primary is-on").addClass("btn-outline-primary");
		$('.sit-level-btn[data-band="' + currentBand + '"]').removeClass("btn-outline-primary").addClass("btn-primary is-on");
		applyBand();
	}
	$(".sit-level-btn").on("click", function () {
		currentBand = $(this).attr("data-band") || "high_school";
		activeClasses = classes.filter(function (row) { return (row.band || "high_school") === currentBand; });
		$(".sit-level-btn").removeClass("btn-primary is-on").addClass("btn-outline-primary");
		$(this).removeClass("btn-outline-primary").addClass("btn-primary is-on");
		applyBand();
	});
	function openPlanById(id) {
		$.getJSON("<?= base_url('settings/exam-sitting/'); ?>" + id, function (res) {
			if (!res || res.error) {
				toastada.error(res && res.error ? res.error : "Could not open the plan.");
				return;
			}
			renderPlan(res.plan);
		});
	}
	$(document).on("click", ".sit-open", function () {
		openPlanById($(this).closest("[data-plan]").data("plan"));
	});
	$(document).on("change", "#sitAllClasses, .sit-band, .sit-class", function () {
		if ($(this).is("#sitAllClasses")) {
			$("#sitClassList").toggle(!$(this).is(":checked"));
		}
		if ($(this).is(".sit-band")) {
			var band = $(this).data("band");
			$('.sit-class[data-band="' + band + '"]').prop("checked", $(this).is(":checked"));
		}
		if ($(this).is(".sit-class")) {
			syncBand($(this).data("band"));
		}
		if ($("#sitPairs").length) {
			var current = readPairs();
			redrawPairs(current.length ? current : [[]]);
		}
	});
	$(document).on("change", ".sit-pair-class", function () {
		var box = this;
		if (!box.checked) return;
		$(".sit-pair-class").each(function () {
			if (this !== box && String($(this).val()) === String($(box).val())) {
				$(this).prop("checked", false);
			}
		});
	});
	$(document).on("click", "#sitAddPair", function () {
		var current = readPairs();
		current.push([]);
		redrawPairs(current);
	});
	$(document).on("click", "#sitClearPairs", function () {
		var planId = $("#sitEditor").data("plan");
		if (!planId) return;
		if (!window.confirm("Clear the pairs and the current seats so you can generate a new sitting?")) return;
		var btn = $(this);
		btn.prop("disabled", true);
		post("<?= base_url('settings/exam-sitting/assign'); ?>", { plan_id: planId, clear: "1" }).done(function (res) {
			btn.prop("disabled", false);
			if (!res || res.error) {
				toastada.error(res && res.error ? res.error : "Could not clear the pairing.");
				return;
			}
			renderPlan(res.plan);
			toastada.success("Pairing cleared. Choose a new pair, then assign seats.");
		}).fail(function () {
			btn.prop("disabled", false);
			toastada.error("Could not clear the pairing.");
		});
	});
	$(document).on("click", ".sit-pair-remove", function () {
		var current = readPairs();
		var index = $(this).closest(".sit-pair").index();
		current.splice(index, 1);
		redrawPairs(current.length ? current : [[]]);
	});
	$(document).on("click", "#sitAssign", function () {
		var pairs = readPairs().filter(function (ids) { return ids.length >= 2; });
		if (!pairs.length) {
			toastada.error("Create a mix pair first. Pair 1 can be S1 A, S3 and S6 ACC.");
			return;
		}
		var data = { plan_id: $("#sitEditor").data("plan"), all_classes: $("#sitAllClasses").is(":checked") ? "1" : "0", pairs: JSON.stringify(pairs) };
		if (data.all_classes !== "1") {
			data["class_id"] = $("#sitClassList .sit-class:checked").map(function () { return $(this).val(); }).get();
			if (!data["class_id"].length) {
				toastada.error("Choose the classes, or seat every class in this level.");
				return;
			}
		}
		var btn = $(this);
		btn.prop("disabled", true).text("Assigning…");
		post("<?= base_url('settings/exam-sitting/assign'); ?>", data).done(function (res) {
			btn.prop("disabled", false).text("Randomly assign seats");
			if (!res || res.error) {
				toastada.error(res && res.error ? res.error : "Could not assign seats.");
				return;
			}
			renderPlan(res.plan, res.unseated || [], res.student_count || 0);
			toastada.success("Seats assigned.");
		}).fail(function () {
			btn.prop("disabled", false).text("Randomly assign seats");
			toastada.error("Could not assign seats.");
		});
	});
	if (openPlan > 0) {
		openPlanById(openPlan);
	}
})();
</script>
