<?php
$schoolId = (int) ($school_id ?? 0);
$yearId = (int) ($academic_year ?? 0);
$sitAreas = $attendance_areas ?? [];
$plans = [];
$sitClasses = [];
try {
	$model = new \App\Models\ExamSittingModel();
	$plans = $model->plansForSchool($schoolId, $yearId);
	$sitClasses = $model->regularClasses($schoolId);
} catch (\Throwable $e) {
	$plans = [];
	$sitClasses = [];
}
?>
<style>
	.sit-setup { width: 100%; max-width: 100%; min-width: 0; }
	.sit-setup * { box-sizing: border-box; }
	.sit-intro { color: #64748b; margin: 0 0 14px; line-height: 1.45; }
	.sit-create, .sit-room-form {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
		gap: 10px;
		align-items: end;
		margin: 0 0 14px;
	}
	.sit-setup label { display: block; font-size: .78rem; font-weight: 650; color: #475569; margin-bottom: 4px; }
	.sit-setup .form-control { width: 100%; max-width: 100%; }
	.sit-span { grid-column: 1 / -1; }
	@media (min-width: 760px) {
		.sit-span { grid-column: span 2; }
	}
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
	.sit-actions { display: flex; flex-wrap: wrap; gap: 6px; }
	.sit-meta { color: #64748b; font-size: .85rem; margin: 6px 0 0; }
	.sit-rooms { margin: 8px 0 0; padding: 0; list-style: none; }
	.sit-rooms li {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		gap: 8px;
		align-items: center;
		padding: 8px 0;
		border-bottom: 1px solid #f1f5f9;
	}
	.sit-rooms li span { min-width: 0; overflow-wrap: anywhere; }
	.sit-note { margin-top: 10px; color: #334155; background: #f8fafc; border-radius: 10px; padding: 10px 12px; }
	.sit-title-input { max-width: 420px; font-weight: 650; }
</style>
<div class="sit-setup">
	<p class="sit-intro">Choose Nursery, Primary, or High school. Each level keeps its own rooms. The high school rooms already saved stay on High school. Pick a class from that level, or an attendance location, then the tables, the columns, and how many students sit at one table.</p>
	<div class="sit-level mb-3">
		<label>School level</label>
		<div class="btn-group" role="group" aria-label="School level">
			<button type="button" class="btn btn-outline-primary sit-level-btn" data-band="nursery">Nursery</button>
			<button type="button" class="btn btn-outline-primary sit-level-btn" data-band="primary">Primary</button>
			<button type="button" class="btn btn-primary sit-level-btn is-on" data-band="high_school">High school</button>
		</div>
	</div>
	<form class="sit-create" id="sitCreate">
		<div class="sit-span">
			<label for="sitTitle">Exam name</label>
			<input class="form-control" id="sitTitle" name="title" maxlength="160" placeholder="End of term exam">
		</div>
		<div>
			<button class="btn btn-success" type="submit">Create plan</button>
		</div>
	</form>
	<p class="sit-meta" id="sitLevelEmpty" style="display:none">No exam plan for this level yet. Create one above. High school plans are left as they are.</p>
	<div id="sitPlans">
		<?php foreach ($plans as $plan) {
			$roomCount = count($plan['rooms'] ?? []);
			$planBand = \App\Models\ExamSittingModel::normalizeBand($plan['level_band'] ?? 'high_school');
			?>
			<div class="sit-plan" data-plan="<?= (int) $plan['id']; ?>" data-band="<?= esc($planBand); ?>"<?= $planBand === 'high_school' ? '' : ' style="display:none"'; ?>>
				<div class="sit-head">
					<h6><?= esc($plan['title'] ?? 'Exam'); ?> <small class="text-muted"><?= esc(\App\Models\ExamSittingModel::bandLabel($planBand)); ?></small></h6>
					<span class="sit-actions">
						<button type="button" class="btn btn-sm btn-outline-primary sit-open">Open</button>
						<button type="button" class="btn btn-sm btn-outline-secondary sit-edit">Edit</button>
						<button type="button" class="btn btn-sm btn-outline-danger sit-delete">Delete</button>
					</span>
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
	var areas = <?= json_encode(array_map(static function ($area) {
		return ['id' => (int) $area['id'], 'label' => (string) ($area['name'] ?? '')];
	}, $sitAreas), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

	function esc(value) {
		return $("<div>").text(value == null ? "" : value).html();
	}
	function post(url, data) {
		return $.ajax({ url: url, type: "POST", data: data, dataType: "json" });
	}
	var currentBand = "high_school";
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
	function roomOptions(band) {
		var html = '<option value="">Choose a room</option><optgroup label="' + esc(bandLabel(band)) + ' classes">';
		classes.forEach(function (row) {
			if ((row.band || "high_school") !== band) return;
			html += '<option value="class:' + row.id + '">' + esc(row.label) + '</option>';
		});
		html += '</optgroup><optgroup label="Attendance locations">';
		areas.forEach(function (row) {
			html += '<option value="area:' + row.id + '">' + esc(row.label) + '</option>';
		});
		html += '</optgroup>';
		return html;
	}
	function renderPlan(plan) {
		if (!plan) return;
		var rooms = plan.rooms || [];
		var band = plan.level_band || "high_school";
		var html = '<div class="sit-plan" data-open="' + plan.id + '"><h6>' + esc(plan.title) + ' <small class="text-muted">' + esc(bandLabel(band)) + '</small></h6>';
		html += '<p class="sit-meta">Add each exam room from ' + esc(bandLabel(band)) + '. Seats are assigned under Students → Exam sitting Plan.</p>';
		html += '<div class="sit-room-form">';
		html += '<div class="sit-span"><label>Room</label><select class="form-control" id="sitSource">' + roomOptions(band) + '</select></div>';
		html += '<div><label>Tables</label><input class="form-control" id="sitTables" type="number" min="1" max="80" value="10"></div>';
		html += '<div><label>Columns</label><input class="form-control" id="sitColumns" type="number" min="1" max="12" value="2"></div>';
		html += '<div><label>Students per table</label><input class="form-control" id="sitPer" type="number" min="1" max="8" value="2"></div>';
		html += '<div><button type="button" class="btn btn-primary" id="sitAddRoom">Add room</button></div></div>';
		html += '<ul class="sit-rooms">';
		rooms.forEach(function (room) {
			var cap = (parseInt(room.tables_count, 10) || 0) * (parseInt(room.students_per_table, 10) || 0);
			html += '<li><span><b>' + esc(room.room_name) + '</b><br>' + room.tables_count + ' tables · ' + room.columns_count + ' columns · ' + room.students_per_table + ' per table · ' + cap + ' seats</span>';
			html += '<button type="button" class="btn btn-sm btn-outline-danger sit-room-del" data-room="' + room.id + '">Remove</button></li>';
		});
		html += '</ul>';
		html += '<p class="sit-note">When the rooms are ready, open <a href="<?= base_url('exam-sitting'); ?>?plan=' + plan.id + '">Students → Exam sitting Plan</a> to mix the classes and assign seats.</p>';
		html += '</div>';
		$("#sitEditor").html(html).show();
		$("#sitEditor").data("plan", plan.id);
		$("#sitEditor").data("band", plan.level_band || "high_school");
	}
	$(".sit-level-btn").on("click", function () {
		currentBand = $(this).attr("data-band") || "high_school";
		$(".sit-level-btn").removeClass("btn-primary is-on").addClass("btn-outline-primary");
		$(this).removeClass("btn-outline-primary").addClass("btn-primary is-on");
		applyBand();
	});
	$("#sitCreate").on("submit", function (e) {
		e.preventDefault();
		post("<?= base_url('settings/exam-sitting/plan'); ?>", { title: $("#sitTitle").val(), level_band: currentBand }).done(function (res) {
			if (!res || res.error) {
				toastada.error(res && res.error ? res.error : "Could not create the plan.");
				return;
			}
			$("#sitTitle").val("");
			$("#sitPlans").prepend('<div class="sit-plan" data-plan="' + res.plan.id + '" data-band="' + esc(res.plan.level_band || currentBand) + '"><div class="sit-head"><h6>' + esc(res.plan.title) + ' <small class="text-muted">' + esc(bandLabel(res.plan.level_band || currentBand)) + '</small></h6><span class="sit-actions"><button type="button" class="btn btn-sm btn-outline-primary sit-open">Open</button><button type="button" class="btn btn-sm btn-outline-secondary sit-edit">Edit</button><button type="button" class="btn btn-sm btn-outline-danger sit-delete">Delete</button></span></div><p class="sit-meta">0 rooms · 0 seats</p></div>');
			applyBand();
			renderPlan(res.plan);
		}).fail(function () { toastada.error("Could not create the plan."); });
	});
	$(document).on("click", ".sit-open", function () {
		var id = $(this).closest("[data-plan]").data("plan");
		$.getJSON("<?= base_url('settings/exam-sitting/'); ?>" + id, function (res) {
			if (!res || res.error) {
				toastada.error(res && res.error ? res.error : "Could not open the plan.");
				return;
			}
			renderPlan(res.plan);
		});
	});
	$(document).on("click", ".sit-edit", function () {
		var box = $(this).closest("[data-plan]");
		var current = $.trim(box.find("h6").first().text());
		box.find("h6").first().replaceWith('<input class="form-control sit-title-input" maxlength="160" value="' + esc(current) + '">');
		$(this).text("Save").removeClass("btn-outline-secondary sit-edit").addClass("btn-success sit-save");
		box.find(".sit-title-input").trigger("focus");
	});
	$(document).on("click", ".sit-save", function () {
		var box = $(this).closest("[data-plan]");
		var title = $.trim(box.find(".sit-title-input").val() || "");
		if (!title) {
			toastada.error("Name the exam, up to 160 characters.");
			return;
		}
		var btn = $(this);
		btn.prop("disabled", true);
		post("<?= base_url('settings/exam-sitting/plan'); ?>", { plan_id: box.data("plan"), title: title }).done(function (res) {
			btn.prop("disabled", false);
			if (!res || res.error) {
				toastada.error(res && res.error ? res.error : "Could not save the exam name.");
				return;
			}
			box.find(".sit-title-input").replaceWith("<h6>" + esc(res.plan.title) + "</h6>");
			btn.text("Edit").removeClass("btn-success sit-save").addClass("btn-outline-secondary sit-edit");
			if (String($("#sitEditor").data("plan")) === String(box.data("plan"))) {
				$("#sitEditor h6").first().text(res.plan.title);
			}
			toastada.success("Exam name saved. It is the title on each room Excel file.");
		}).fail(function () {
			btn.prop("disabled", false);
			toastada.error("Could not save the exam name.");
		});
	});
	$(document).on("click", ".sit-delete", function () {
		var box = $(this).closest("[data-plan]");
		if (!confirm("Delete this sitting plan?")) return;
		post("<?= base_url('settings/exam-sitting/delete'); ?>", { plan_id: box.data("plan") }).done(function (res) {
			if (!res || !res.success) return;
			if (String($("#sitEditor").data("plan")) === String(box.data("plan"))) {
				$("#sitEditor").hide().empty();
			}
			box.remove();
		});
	});
	$(document).on("click", "#sitAddRoom", function () {
		post("<?= base_url('settings/exam-sitting/room'); ?>", {
			plan_id: $("#sitEditor").data("plan"),
			source: $("#sitSource").val(),
			tables: $("#sitTables").val(),
			columns: $("#sitColumns").val(),
			per_table: $("#sitPer").val()
		}).done(function (res) {
			if (!res || res.error) {
				toastada.error(res && res.error ? res.error : "Could not add the room.");
				return;
			}
			renderPlan(res.plan);
		}).fail(function () { toastada.error("Could not add the room."); });
	});
	$(document).on("click", ".sit-room-del", function () {
		post("<?= base_url('settings/exam-sitting/room-delete'); ?>", {
			plan_id: $("#sitEditor").data("plan"),
			room_id: $(this).data("room")
		}).done(function (res) {
			if (!res || res.error) {
				toastada.error(res && res.error ? res.error : "Could not remove the room.");
				return;
			}
			renderPlan(res.plan);
		});
	});
})();
</script>
