<link rel="stylesheet" href="<?= base_url('assets/css/inout-report.css'); ?>?v=6">
<?php
if ($show_header) {
	$inoutSchoolId = (new \App\Models\AttendanceAreaModel())->inoutSchoolId((int) session('soma_school_id'));
	if ($inoutSchoolId > 0 && $inoutSchoolId !== (int) session('soma_school_id')) {
		$classes = (new \App\Models\ClassesModel())->get_classes($inoutSchoolId);
		$attendance_areas = (new \App\Models\AttendanceAreaModel())->listAreas($inoutSchoolId, false);
	}
	$months = get_months();
	$defaultMonth = (int) ($default_month ?? date('n'));
	$defaultYear = (int) ($default_year ?? date('Y'));
	$years = $report_years ?? [$defaultYear];
	echo view('pages/reports/_attendance_report_type');
	?>
	<div class="io-page">
		<div class="io-filters">
			<div class="io-filters-row">
				<div class="io-field">
					<label><?= lang("app.sClass"); ?></label>
					<select class="select2 form-control" id="select_class" name="class">
						<option value="0" selected>All classes</option>
						<?php foreach ($classes as $classe): ?>
							<option value="<?= (int) $classe['id']; ?>">
								<?= esc(($classe['level_name'] ?? '') . ' ' . ($classe['code'] ?? '') . ' ' . ($classe['title'] ?? '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php if (!empty($show_locations)): ?>
				<div class="io-field">
					<label><?= lang("app.attendanceArea"); ?></label>
					<select class="select2 form-control" id="select_area" name="area">
						<option value="0" selected>All locations</option>
						<?php foreach (($attendance_areas ?? []) as $area):
							$label = $area['name'];
							if ((int) ($area['active'] ?? 1) !== 1) {
								$label .= ' (inactive)';
							}
							?>
							<option value="<?= (int) $area['id']; ?>"><?= esc($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>
				<div class="io-field">
					<label><?= lang("app.month"); ?></label>
					<select class="select2 form-control" id="choose_month" name="months">
						<?php for ($a = 0; $a < count($months); $a++): ?>
							<option value="<?= $a + 1; ?>" <?= ($a + 1) === $defaultMonth ? 'selected' : ''; ?>><?= esc($months[$a]); ?></option>
						<?php endfor; ?>
					</select>
				</div>
				<div class="io-field" style="max-width:120px;">
					<label>Year</label>
					<select class="form-control" id="choose_year" name="year">
						<?php foreach ($years as $yr): ?>
							<option value="<?= (int) $yr; ?>" <?= (int) $yr === $defaultYear ? 'selected' : ''; ?>><?= (int) $yr; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="io-actions">
					<button class="btn btn-success" id="btn_generate"><i class="fa fa-chart-bar"></i> <?= lang("app.generate"); ?></button>
					<button class="btn btn-outline-primary" id="btn_print" type="button"><i class="fa fa-print"></i> <?= lang("app.print"); ?></button>
				</div>
			</div>
		</div>
		<div id="report_content">
			<div class="io-empty"><?= !empty($show_locations) ? 'Choose class, location, month and year, then Generate. Use <strong>All classes</strong> and <strong>All locations</strong> to track every NFC IN/OUT scan.' : 'Choose class, month and year, then Generate. Use <strong>All classes</strong> to track every NFC IN/OUT scan.'; ?></div>
		</div>
	</div>
	<script>
		$(function () {
			function params() {
				return "class=" + ($("#select_class").val() || 0)
					+ "&area=" + ($("#select_area").val() || 0)
					+ "&month=" + ($("#choose_month").val() || "")
					+ "&year=" + ($("#choose_year").val() || "");
			}
			$("#btn_generate").on("click", function () {
				if (!$("#choose_month").val()) {
					toastada.warning('<?= lang("app.pleaseSelectMonth"); ?>');
					return;
				}
				$("#btn_generate").text('<?= lang("app.pleaseWait"); ?>').prop("disabled", true);
				$("#report_content").load("<?= base_url('student_inout_monthly_report_data/false'); ?>?" + params(), function () {
					$("#btn_generate").html('<i class="fa fa-chart-bar"></i> <?= lang("app.generate"); ?>').prop("disabled", false);
					ioResetFromDom();
				});
			});
			$("#btn_print").on("click", function () {
				if (!$("#printable").length) {
					toastada.warning('Generate the report first.');
					return;
				}
				window.print();
			});
			var ioFilter = { sec: "", day: "all", tap: "", area: "" };
			function ioResetFromDom() {
				ioFilter = { sec: "", day: String($("#report_content .io-day.active").data("day") || "all"), tap: "", area: "" };
			}
			function ioTapAttr(tap) {
				if (tap === "lunch") return "lunch";
				if (tap === "back") return "back";
				if (tap === "evening") return "out";
				if (tap === "morning") return "morning";
				return "";
			}
			function ioUnique($rows) {
				var seen = {}, n = 0;
				$rows.each(function () {
					var id = String($(this).data("student") || "");
					if (!id || seen[id]) return;
					seen[id] = 1;
					n++;
				});
				return n;
			}
			function ioRenumber($rows) {
				var i = 1;
				$rows.each(function () { $(this).find(".io-n").text(i++); });
			}
			function ioShowPane(pane) {
				$(".io-tab").removeClass("active");
				$(".io-tab[data-pane='" + pane + "']").addClass("active");
				$(".io-pane").attr("hidden", true);
				$("#" + pane).removeAttr("hidden");
			}
			function ioSyncSections() {
				$(".io-sec-head").each(function () {
					var s = String($(this).data("section"));
					if (ioFilter.sec && ioFilter.sec !== s) {
						$(this).hide();
						return;
					}
					$(this).toggle($(".io-visit-row[data-section='" + s + "']:visible").length > 0);
				});
			}
			function ioMark() {
				$(".io-kpi[data-tap], .io-metric, .io-dk-card, .io-sec-metric").removeClass("is-on");
				$(".io-section").removeClass("is-on");
				if (ioFilter.tap) {
					$(".io-kpi[data-tap='" + ioFilter.tap + "']").addClass("is-on");
					$(".io-metric[data-tap='" + ioFilter.tap + "']").filter(function () {
						var a = String($(this).data("area") || "");
						return !ioFilter.area || a === String(ioFilter.area);
					}).addClass("is-on");
					if (ioFilter.day !== "all") {
						$(".io-dk-card[data-daytap='" + ioFilter.tap + "']").addClass("is-on");
					}
				}
				if (ioFilter.sec) {
					$(".io-section[data-section='" + ioFilter.sec + "']").addClass("is-on");
					$(".io-sec-metric[data-section='" + ioFilter.sec + "'][data-tap='" + ioFilter.tap + "']").addClass("is-on");
				}
			}
			function ioFillDayCards() {
				var day = String(ioFilter.day);
				var sec = ioFilter.sec;
				var $rows = $(".io-visit-row").filter(function () {
					if (day !== "all" && String($(this).data("day")) !== day) return false;
					if (sec && String($(this).data("section")) !== sec) return false;
					return true;
				});
				function count(attr) {
					var $hit = $rows.filter("[data-" + attr + "='1']");
					return day === "all" ? ioUnique($hit) : $hit.length;
				}
				$("#ioDayIn").text(count("morning"));
				$("#ioDayOut").text(count("out"));
				if ($("#ioDayLunch").length) {
					$("#ioDayLunch").text(count("lunch"));
					$("#ioDayBack").text(count("back"));
				}
				if ($("#ioDayMiss").length) {
					$("#ioDayMiss").text(Math.max(0, count("morning") - count("out")));
				}
			}
			function ioApply() {
				var monthList = !ioFilter.area && String(ioFilter.day) === "all" && (ioFilter.tap === "roster" || ioFilter.tap === "scanned" || ioFilter.tap === "morning" || ioFilter.tap === "lunch" || ioFilter.tap === "back" || ioFilter.tap === "evening" || ioFilter.tap === "miss");
				var labels = { morning: "Morning", lunch: "Lunch out", back: "Back", evening: "Evening", scanned: "Scanned", roster: "Day scholars", miss: "No checkout" };
				if (monthList) {
					$("#ioDayTools, #ioLogWrap").hide();
					$("#ioRosterWrap").show();
					$(".io-roster-row").each(function () {
						var $row = $(this);
						var show = true;
						if (ioFilter.sec && String($row.data("section")) !== ioFilter.sec) show = false;
						if (show && ioFilter.tap === "scanned" && String($row.data("scanned")) !== "1") show = false;
						if (show && ioFilter.tap === "morning" && String($row.data("morning")) !== "1") show = false;
						if (show && ioFilter.tap === "lunch" && String($row.data("lunch")) !== "1") show = false;
						if (show && ioFilter.tap === "back" && String($row.data("back")) !== "1") show = false;
						if (show && ioFilter.tap === "evening" && String($row.data("out")) !== "1") show = false;
						if (show && ioFilter.tap === "miss" && !(String($row.data("morning")) === "1" && String($row.data("out")) !== "1")) show = false;
						$row.toggle(show);
					});
					var $vis = $(".io-roster-row:visible");
					ioRenumber($vis);
					var title = labels[ioFilter.tap] || "Students";
					if (ioFilter.sec) title = ($(".io-section[data-section='" + ioFilter.sec + "'] h5").text() || title) + " · " + title;
					$("#ioRosterCaption").text(title + " · " + $vis.length + " students");
					$("#ioRosterEmpty").toggle($vis.length === 0);
					return;
				}
				$("#ioRosterWrap").hide();
				$("#ioDayTools, #ioLogWrap").show();
				var attr = ioTapAttr(ioFilter.tap);
				var day = String(ioFilter.day);
				$(".io-visit-row").each(function () {
					var $row = $(this);
					var show = true;
					if (ioFilter.sec && String($row.data("section")) !== ioFilter.sec) show = false;
					if (show && day !== "all" && String($row.data("day")) !== day) show = false;
					if (show && ioFilter.tap === "miss") {
						show = String($row.data("morning")) === "1" && String($row.data("out")) !== "1";
					} else if (show && attr && String($row.data(attr)) !== "1") {
						show = false;
					}
					if (show && ioFilter.area && String($row.data("area")) !== String(ioFilter.area)) show = false;
					$row.toggle(show);
				});
				if (day === "all") {
					$(".io-inv-block").show();
				} else {
					$(".io-inv-block").hide();
					$(".io-inv-block[data-day='" + day + "']").show();
				}
				var $vis = $(".io-visit-row:visible");
				ioRenumber($vis);
				var cap = day === "all" ? "All days" : ("Day " + day);
				if (ioFilter.tap && labels[ioFilter.tap]) cap += " · " + labels[ioFilter.tap];
				cap += " · " + ioUnique($vis) + " students";
				$("#ioDayCaption").text(cap);
				$("#ioDayEmpty").toggle($vis.length === 0 && $(".io-visit-row").length > 0);
				ioSyncSections();
			}
			function ioPickList(tap, area) {
				ioFilter.tap = tap || "";
				ioFilter.area = area || "";
				if (!ioFilter.area) {
					ioFilter.day = "all";
					$(".io-day").removeClass("active");
					$(".io-day[data-day='all']").addClass("active");
				}
				ioFillDayCards();
				ioMark();
				ioApply();
				ioShowPane("ioPaneGrid");
				var el = document.getElementById("ioPaneGrid");
				if (el && el.scrollIntoView) el.scrollIntoView({ behavior: "smooth", block: "start" });
			}
			$("#report_content").on("click", ".io-tab", function () {
				var pane = String($(this).data("pane") || "");
				if (pane === "ioPaneGrid") {
					ioFilter.tap = "";
					ioFilter.area = "";
					ioFilter.sec = "";
					ioMark();
					ioApply();
				}
				ioShowPane(pane);
			});
			$("#report_content").on("click", ".io-kpi[data-tap], .io-metric", function () {
				ioPickList(String($(this).data("tap") || ""), String($(this).data("area") || ""));
			});
			$("#report_content").on("click", ".io-dk-card", function () {
				ioFilter.tap = String($(this).data("daytap") || "");
				ioFilter.area = "";
				ioMark();
				ioApply();
				ioShowPane("ioPaneGrid");
			});
			$("#report_content").on("click", ".io-day", function () {
				ioFilter.day = String($(this).data("day") || "all");
				ioFilter.tap = "";
				ioFilter.area = "";
				$(".io-day").removeClass("active");
				$(this).addClass("active");
				ioFillDayCards();
				ioMark();
				ioApply();
			});
			$("#report_content").on("click", ".io-sec-metric", function () {
				var sec = String($(this).data("section") || "");
				var tap = String($(this).data("tap") || "");
				if (ioFilter.sec === sec && ioFilter.tap === tap && $(this).hasClass("is-on")) {
					ioFilter.sec = "";
					ioFilter.tap = "";
				} else {
				ioFilter.sec = sec;
				ioFilter.tap = tap;
				ioFilter.area = "";
				ioFilter.day = "all";
				$(".io-day").removeClass("active");
				$(".io-day[data-day='all']").addClass("active");
				}
				ioFillDayCards();
				ioMark();
				ioApply();
				ioShowPane("ioPaneGrid");
				var el = document.getElementById("ioPaneGrid");
				if (el && el.scrollIntoView) el.scrollIntoView({ behavior: "smooth", block: "start" });
			});
		});
	</script>
	<?php
	return;
}

$kpi = $kpi ?? [];
$students = $students ?? [];
$visits = $visits ?? [];
$activeDays = $active_days ?? [];
$defaultDay = (int) ($default_day ?? 0);
$areaStats = $area_stats ?? [];
$singleArea = !empty($single_area);
$showClassCol = (($classe ?? '') === 'All classes');
$never = $never_scanned ?? [];
$missing = $missing_out ?? [];
$gateTaps = !empty($gate_taps);
$dayKpi = ['in' => 0, 'out' => 0, 'miss' => 0, 'lunch' => 0, 'back' => 0];
foreach ($visits as $vv) {
	if ($defaultDay > 0 && (int) $vv['day'] !== $defaultDay) {
		continue;
	}
	$dayKpi['in']++;
	if (($vv['in'] ?? '') === '') {
		$dayKpi['in']--;
	}
	if (!empty($vv['lunch'])) {
		$dayKpi['lunch']++;
	}
	if (!empty($vv['back'])) {
		$dayKpi['back']++;
	}
	if (!empty($vv['complete'])) {
		$dayKpi['out']++;
	} else {
		$dayKpi['miss']++;
	}
}
?>
<div id="printable">
	<div class="io-kpi-grid">
		<button type="button" class="io-kpi" data-tap="roster"><div class="lbl"><?= $gateTaps ? 'Day scholars' : 'Students'; ?></div><div class="val"><?= (int) ($kpi['students'] ?? 0); ?></div></button>
		<button type="button" class="io-kpi blue" data-tap="scanned"><div class="lbl">Scanned</div><div class="val"><?= (int) ($kpi['scanned'] ?? 0); ?></div></button>
		<?php if ($gateTaps): ?>
		<button type="button" class="io-kpi in" data-tap="morning"><div class="lbl">1 · Morning</div><div class="val"><?= (int) ($kpi['in_count'] ?? 0); ?></div></button>
		<button type="button" class="io-kpi lunch" data-tap="lunch"><div class="lbl">2 · Lunch out</div><div class="val"><?= (int) ($kpi['lunch_count'] ?? 0); ?></div></button>
		<button type="button" class="io-kpi back" data-tap="back"><div class="lbl">3 · Back</div><div class="val"><?= (int) ($kpi['back_count'] ?? 0); ?></div></button>
		<button type="button" class="io-kpi leave" data-tap="evening"><div class="lbl">4 · Evening</div><div class="val"><?= (int) ($kpi['out_count'] ?? 0); ?></div></button>
		<?php else: ?>
		<button type="button" class="io-kpi in" data-tap="morning"><div class="lbl">IN taps</div><div class="val"><?= (int) ($kpi['in_count'] ?? 0); ?></div></button>
		<button type="button" class="io-kpi out" data-tap="evening"><div class="lbl">OUT / checkout</div><div class="val"><?= (int) ($kpi['out_count'] ?? 0); ?></div></button>
		<button type="button" class="io-kpi warn" data-tap="miss"><div class="lbl">Missing OUT</div><div class="val"><?= (int) ($kpi['missing_out'] ?? 0); ?></div></button>
		<?php endif; ?>
		<div class="io-kpi"><div class="lbl">Coverage</div><div class="val"><?= (int) ($kpi['coverage'] ?? 0); ?>%</div></div>
	</div>
	<?php if ($gateTaps && !empty($sections)): ?>
	<p class="io-gate-note">School gate counts day scholars only. Boarding students are not included. Click a number to list those students.</p>
	<div class="io-sections">
		<?php foreach ($sections as $sec): ?>
		<div class="io-section is-<?= esc($sec['key']); ?>" data-section="<?= esc($sec['key']); ?>">
			<h5><?= esc($sec['label']); ?></h5>
			<div class="sec-line">
				<button type="button" class="io-sec-metric" data-section="<?= esc($sec['key']); ?>" data-tap="roster"><b><?= (int) $sec['students']; ?></b> students</button>
				<button type="button" class="io-sec-metric" data-section="<?= esc($sec['key']); ?>" data-tap="scanned"><b><?= (int) $sec['scanned']; ?></b> scanned</button>
				<button type="button" class="io-sec-metric m-in" data-section="<?= esc($sec['key']); ?>" data-tap="morning"><b><?= (int) $sec['morning']; ?></b> morning</button>
				<button type="button" class="io-sec-metric m-lunch" data-section="<?= esc($sec['key']); ?>" data-tap="lunch"><b><?= (int) $sec['lunch']; ?></b> lunch</button>
				<button type="button" class="io-sec-metric m-back" data-section="<?= esc($sec['key']); ?>" data-tap="back"><b><?= (int) $sec['back']; ?></b> back</button>
				<button type="button" class="io-sec-metric m-eve" data-section="<?= esc($sec['key']); ?>" data-tap="evening"><b><?= (int) $sec['evening']; ?></b> evening</button>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if (!empty($show_locations) && count($areaStats) > 0) : ?>
		<div class="io-area-map">
			<?php foreach ($areaStats as $as) : ?>
				<div class="io-area-board">
					<div class="an"><?= esc($as['name']); ?></div>
					<div class="io-metric-grid">
						<button type="button" class="io-metric" data-tap="scanned" data-area="<?= $singleArea ? '' : (int) $as['id']; ?>">
							<span class="lbl">Students</span><span class="val"><?= (int) $as['students']; ?></span>
						</button>
						<?php if ($gateTaps): ?>
						<button type="button" class="io-metric in" data-tap="morning" data-area="<?= $singleArea ? '' : (int) $as['id']; ?>">
							<span class="lbl">Morning</span><span class="val"><?= (int) $as['in_count']; ?></span>
						</button>
						<button type="button" class="io-metric lunch" data-tap="lunch" data-area="<?= $singleArea ? '' : (int) $as['id']; ?>">
							<span class="lbl">Lunch out</span><span class="val"><?= (int) ($as['lunch_count'] ?? 0); ?></span>
						</button>
						<button type="button" class="io-metric back" data-tap="back" data-area="<?= $singleArea ? '' : (int) $as['id']; ?>">
							<span class="lbl">Back</span><span class="val"><?= (int) ($as['back_count'] ?? 0); ?></span>
						</button>
						<button type="button" class="io-metric leave" data-tap="evening" data-area="<?= $singleArea ? '' : (int) $as['id']; ?>">
							<span class="lbl">Evening</span><span class="val"><?= (int) $as['out_count']; ?></span>
						</button>
						<?php else: ?>
						<button type="button" class="io-metric in" data-tap="morning" data-area="<?= $singleArea ? '' : (int) $as['id']; ?>">
							<span class="lbl">IN</span><span class="val"><?= (int) $as['in_count']; ?></span>
						</button>
						<button type="button" class="io-metric leave" data-tap="evening" data-area="<?= $singleArea ? '' : (int) $as['id']; ?>">
							<span class="lbl">OUT</span><span class="val"><?= (int) $as['out_count']; ?></span>
						</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="io-list-kpis">
		<button type="button" class="io-tab io-list-card active" data-pane="ioPaneGrid"><span class="lbl"><?= $gateTaps ? 'Daily 4 taps' : 'Daily IN / OUT'; ?></span><span class="val"><?= (int) ($kpi['scanned'] ?? 0); ?></span></button>
		<button type="button" class="io-tab io-list-card warn" data-pane="ioPaneMissing"><span class="lbl"><?= $gateTaps ? 'No evening leave' : 'Missing OUT'; ?></span><span class="val"><?= count($missing); ?></span></button>
		<button type="button" class="io-tab io-list-card" data-pane="ioPaneNever"><span class="lbl">Never scanned</span><span class="val"><?= count($never); ?></span></button>
	</div>

	<div class="io-pane" id="ioPaneGrid">
		<div class="io-letter">
			<div class="io-meta">
				<div>
					<strong><?= esc($school_name ?? ''); ?></strong><br>
					<?= lang("app.sClass"); ?>: <?= esc($classe ?? ''); ?><br>
					<?php if (!empty($show_locations)): ?><?= lang("app.attendanceArea"); ?>: <?= esc($attendance_area ?? ''); ?><br><?php endif; ?>
					<?= lang("app.month"); ?>: <?= esc($month_label ?? $month ?? ''); ?>
				</div>
				<div>
					<?= lang("app.printedOn"); ?>: <?= date("Y-m-d H:i"); ?><br>
					<?= lang("app.totalStudents"); ?>: <?= count($students); ?>
				</div>
			</div>
			<h4><?= lang("app.StudentInOutmonthlyReport"); ?><?= !empty($attendance_area) ? ' — ' . esc($attendance_area) : ''; ?></h4>

			<?= view('pages/reports/_prep_invigilators', [
				'prep_days' => $prep_days ?? [],
				'default_day' => $defaultDay,
			]); ?>

			<?php if (count($activeDays) === 0) : ?>
				<div class="io-empty">No NFC IN/OUT scans for this filter in <?= esc($month_label ?? ''); ?>.</div>
			<?php else : ?>
				<div id="ioDayTools">
				<div class="io-daybar">
					<button type="button" class="io-day<?= $defaultDay === 0 ? ' active' : ''; ?>" data-day="all">All days</button>
					<?php foreach ($activeDays as $ds) :
						$d = (int) $ds['day'];
						?>
						<button type="button" class="io-day<?= $d === $defaultDay ? ' active' : ''; ?>" data-day="<?= $d; ?>">
							<?= sprintf('%02d', $d); ?>
							<small><?= (int) $ds['in_count']; ?> in<?php if ($gateTaps): ?> · <?= (int) $ds['out_count']; ?> eve<?php endif; ?></small>
						</button>
					<?php endforeach; ?>
				</div>
				<p class="io-day-caption" id="ioDayCaption"><?= $defaultDay > 0 ? 'Day ' . $defaultDay : 'All activity days'; ?></p>
				<div class="io-day-kpis io-day-cards">
					<?php if ($gateTaps): ?>
					<button type="button" class="io-dk-card in" data-daytap="morning"><span class="lbl">Morning</span><span class="val" id="ioDayIn"><?= (int) $dayKpi['in']; ?></span></button>
					<button type="button" class="io-dk-card lunch" data-daytap="lunch"><span class="lbl">Lunch out</span><span class="val" id="ioDayLunch"><?= (int) $dayKpi['lunch']; ?></span></button>
					<button type="button" class="io-dk-card back" data-daytap="back"><span class="lbl">Back</span><span class="val" id="ioDayBack"><?= (int) $dayKpi['back']; ?></span></button>
					<button type="button" class="io-dk-card leave" data-daytap="evening"><span class="lbl">Evening</span><span class="val" id="ioDayOut"><?= (int) $dayKpi['out']; ?></span></button>
					<?php else: ?>
					<button type="button" class="io-dk-card in" data-daytap="morning"><span class="lbl">IN</span><span class="val" id="ioDayIn"><?= (int) $dayKpi['in']; ?></span></button>
					<button type="button" class="io-dk-card leave" data-daytap="evening"><span class="lbl">OUT</span><span class="val" id="ioDayOut"><?= (int) $dayKpi['out']; ?></span></button>
					<button type="button" class="io-dk-card warn" data-daytap="miss"><span class="lbl">No checkout</span><span class="val" id="ioDayMiss"><?= (int) $dayKpi['miss']; ?></span></button>
					<?php endif; ?>
				</div>
				</div>
				<div id="ioRosterWrap" hidden>
					<p class="io-day-caption" id="ioRosterCaption"></p>
					<table class="io-log">
						<thead>
						<tr>
							<th>#</th>
							<th>Student</th>
							<th>Class</th>
							<th>Section</th>
						</tr>
						</thead>
						<tbody>
						<?php
						$sectionTitles = ['nursery' => 'Nursery', 'primary' => 'Primary', 'high_school' => 'High School'];
						$tapFlags = [];
						foreach ($visits as $flagVisit) {
							$flagId = (int) ($flagVisit['student_id'] ?? 0);
							if ($flagId < 1) {
								continue;
							}
							if (!isset($tapFlags[$flagId])) {
								$tapFlags[$flagId] = ['morning' => 0, 'lunch' => 0, 'back' => 0, 'out' => 0];
							}
							if (($flagVisit['in'] ?? '') !== '') {
								$tapFlags[$flagId]['morning'] = 1;
							}
							if (!empty($flagVisit['lunch'])) {
								$tapFlags[$flagId]['lunch'] = 1;
							}
							if (!empty($flagVisit['back'])) {
								$tapFlags[$flagId]['back'] = 1;
							}
							if (($flagVisit['out'] ?? '') !== '') {
								$tapFlags[$flagId]['out'] = 1;
							}
						}
						foreach ($students as $rosterStudent) :
							$rosterSection = (string) ($rosterStudent['section'] ?? 'high_school');
							$rosterId = (int) ($rosterStudent['id'] ?? 0);
							$flags = $tapFlags[$rosterId] ?? ['morning' => 0, 'lunch' => 0, 'back' => 0, 'out' => 0];
							?>
						<tr class="io-roster-row" data-section="<?= esc($rosterSection); ?>" data-student="<?= $rosterId; ?>" data-scanned="<?= !empty($rosterStudent['scanned']) ? '1' : '0'; ?>" data-morning="<?= (int) $flags['morning']; ?>" data-lunch="<?= (int) $flags['lunch']; ?>" data-back="<?= (int) $flags['back']; ?>" data-out="<?= (int) $flags['out']; ?>">
							<td class="io-n"></td>
							<td>
								<strong><?= esc(trim(($rosterStudent['fname'] ?? '') . ' ' . ($rosterStudent['lname'] ?? ''))); ?></strong>
								<div class="io-reg"><?= esc($rosterStudent['regno'] ?? ''); ?></div>
							</td>
							<td><?= esc($rosterStudent['class_name'] ?? ''); ?></td>
							<td><?= esc($sectionTitles[$rosterSection] ?? 'High School'); ?></td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="io-empty" id="ioRosterEmpty" hidden>No students in this list.</div>
				</div>
				<div id="ioLogWrap">
				<table class="io-log">
					<thead>
					<tr>
						<th>#</th>
						<th>Student</th>
						<?php if ($showClassCol) : ?><th>Class</th><?php endif; ?>
						<th>Day</th>
						<?php if (!$singleArea) : ?><th>Location</th><?php endif; ?>
						<?php if ($gateTaps): ?>
						<th>Morning</th>
						<th>Lunch out</th>
						<th>Back</th>
						<th>Evening</th>
						<?php else: ?>
						<th>IN</th>
						<th>OUT / checkout</th>
						<th>Duration</th>
						<?php endif; ?>
						<th>Status</th>
					</tr>
					</thead>
					<tbody>
					<?php
					$n = 1;
					$visitGroups = ['nursery' => [], 'primary' => [], 'high_school' => []];
					if ($gateTaps) {
						foreach ($visits as $groupedVisit) {
							$groupKey = (string) ($groupedVisit['section'] ?? 'high_school');
							if (!isset($visitGroups[$groupKey])) {
								$groupKey = 'high_school';
							}
							$visitGroups[$groupKey][] = $groupedVisit;
						}
					} else {
						$visitGroups = ['all' => $visits];
					}
					$sectionTitles = ['nursery' => 'Nursery', 'primary' => 'Primary', 'high_school' => 'High School', 'all' => ''];
					foreach ($visitGroups as $groupKey => $groupVisits) :
						if ($groupVisits === []) {
							continue;
						}
						if ($gateTaps) :
							$colspan = 8 + ($showClassCol ? 1 : 0) + ($singleArea ? 0 : 1);
							$sectionVisible = false;
							foreach ($groupVisits as $checkVisit) {
								if ($defaultDay < 1 || (int) ($checkVisit['day'] ?? 0) === $defaultDay) {
									$sectionVisible = true;
									break;
								}
							}
							?>
						<tr class="io-sec-head" data-section="<?= esc($groupKey); ?>"<?= $sectionVisible ? '' : ' style="display:none"'; ?>><td colspan="<?= (int) $colspan; ?>"><?= esc($sectionTitles[$groupKey] ?? 'High School'); ?></td></tr>
						<?php endif;
					foreach ($groupVisits as $v) :
						$day = (int) $v['day'];
						$hide = ($defaultDay > 0 && $day !== $defaultDay);
						$complete = !empty($v['complete']);
						$rowSection = (string) ($v['section'] ?? $groupKey);
						?>
						<tr class="io-visit-row<?= $complete ? '' : ' is-miss'; ?>" data-student="<?= (int) ($v['student_id'] ?? 0); ?>" data-area="<?= (int) ($v['area_id'] ?? 0); ?>" data-section="<?= esc($rowSection); ?>" data-day="<?= $day; ?>" data-out="<?= ($v['out'] ?? '') !== '' ? '1' : '0'; ?>" data-morning="<?= ($v['in'] ?? '') !== '' ? '1' : '0'; ?>" data-lunch="<?= !empty($v['lunch']) ? '1' : '0'; ?>" data-back="<?= !empty($v['back']) ? '1' : '0'; ?>"<?= $hide ? ' style="display:none"' : ''; ?>>
							<td class="io-n"><?= $n++; ?></td>
							<td>
								<strong><?= esc(trim(($v['fname'] ?? '') . ' ' . ($v['lname'] ?? ''))); ?></strong>
								<div class="io-reg"><?= esc($v['regno'] ?? ''); ?></div>
							</td>
							<?php if ($showClassCol) : ?><td><?= esc($v['class_name'] ?? ''); ?></td><?php endif; ?>
							<td><?= sprintf('%02d', $day); ?></td>
							<?php if (!$singleArea) : ?><td><?= esc($v['area'] ?? ''); ?></td><?php endif; ?>
							<td><?= ($v['in'] ?? '') !== '' ? '<span class="io-time in">' . esc($v['in']) . '</span>' : '<span class="io-time dash">—</span>'; ?></td>
							<?php if ($gateTaps): ?>
							<td><?= !empty($v['lunch']) ? '<span class="io-time lunch">' . esc($v['lunch']) . '</span>' : '<span class="io-time dash">—</span>'; ?></td>
							<td><?= !empty($v['back']) ? '<span class="io-time back">' . esc($v['back']) . '</span>' : '<span class="io-time dash">—</span>'; ?></td>
							<td><?= ($v['out'] ?? '') !== '' ? '<span class="io-time leave">' . esc($v['out']) . '</span>' : '<span class="io-time dash">—</span>'; ?></td>
							<td>
								<?php if ($complete) : ?>
									<span class="io-st done">Left school</span>
								<?php elseif (!empty($v['back'])) : ?>
									<span class="io-st open">Back from lunch</span>
								<?php elseif (!empty($v['lunch'])) : ?>
									<span class="io-st open">Out for lunch</span>
								<?php else : ?>
									<span class="io-st open">Arrived</span>
								<?php endif; ?>
							</td>
							<?php else: ?>
							<td>
								<?php if ($complete) : ?>
									<span class="io-time out"><?= esc($v['out']); ?></span>
								<?php else : ?>
									<span class="io-time none">No checkout</span>
								<?php endif; ?>
							</td>
							<td><?= $complete ? esc($v['duration'] ?? '') : '—'; ?></td>
							<td>
								<?php if ($complete) : ?>
									<span class="io-st done">Checked out</span>
								<?php else : ?>
									<span class="io-st open">Still inside</span>
								<?php endif; ?>
							</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="io-empty" id="ioDayEmpty" hidden>No scans on this day.</div>
				</div>
			<?php endif; ?>
			<div style="text-align:right;color:#94a3b8;margin-top:8px;font-size:.8rem;"><?= lang("app.generatedbySomanet"); ?></div>
		</div>
	</div>

	<div class="io-pane" id="ioPaneMissing" hidden>
		<?php if (count($missing) === 0) : ?>
			<div class="io-empty">No missing check-outs for this filter.</div>
		<?php else : ?>
			<table class="io-ex-table">
				<thead>
				<tr>
					<th>#</th>
					<th>Student</th>
					<th>Reg</th>
					<th>Class</th>
					<th>Location</th>
					<th>Day</th>
					<th><?= $gateTaps ? 'Morning' : 'IN'; ?></th>
					<?php if ($gateTaps): ?>
					<th>Lunch out</th>
					<th>Back</th>
					<?php endif; ?>
					<th><?= $gateTaps ? 'Evening' : 'OUT / checkout'; ?></th>
				</tr>
				</thead>
				<tbody>
				<?php $i = 1; foreach ($missing as $row) : ?>
					<tr>
						<td><?= $i++; ?></td>
						<td><?= esc(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')); ?></td>
						<td><?= esc($row['regno'] ?? ''); ?></td>
						<td><?= esc($row['class_name'] ?? ''); ?></td>
						<td><?= esc($row['area'] ?? ''); ?></td>
						<td><?= (int) ($row['day'] ?? 0); ?></td>
						<td><span class="io-time in"><?= esc($row['in'] ?? ''); ?></span></td>
						<?php if ($gateTaps): ?>
						<td><?= !empty($row['lunch']) ? '<span class="io-time lunch">' . esc($row['lunch']) . '</span>' : '—'; ?></td>
						<td><?= !empty($row['back']) ? '<span class="io-time back">' . esc($row['back']) . '</span>' : '—'; ?></td>
						<?php endif; ?>
						<td><?php if ($gateTaps): ?><span class="io-time dash">—</span><?php else: ?><span class="io-time none">No checkout</span><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="io-pane" id="ioPaneNever" hidden>
		<?php if (count($never) === 0) : ?>
			<div class="io-empty">Every student in this filter has at least one scan.</div>
		<?php else : ?>
			<table class="io-ex-table">
				<thead>
				<tr>
					<th>#</th>
					<th>Student</th>
					<th>Reg</th>
					<th>Class</th>
				</tr>
				</thead>
				<tbody>
				<?php $i = 1; foreach ($never as $row) : ?>
					<tr>
						<td><?= $i++; ?></td>
						<td><?= esc(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')); ?></td>
						<td><?= esc($row['regno'] ?? ''); ?></td>
						<td><?= esc($row['class_name'] ?? ''); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
