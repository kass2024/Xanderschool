<link rel="stylesheet" href="<?= base_url('assets/css/inout-report.css'); ?>?v=2">
<?php
if ($show_header) {
	$months = get_months();
	$defaultMonth = (int) ($default_month ?? date('n'));
	$defaultYear = (int) ($default_year ?? date('Y'));
	$years = $report_years ?? [$defaultYear];
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
			<div class="io-empty">Choose class, location, month and year, then Generate. Use <strong>All classes</strong> and <strong>All locations</strong> to track every NFC IN/OUT scan.</div>
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
				});
			});
			$("#btn_print").on("click", function () {
				if (!$("#printable").length) {
					toastada.warning('Generate the report first.');
					return;
				}
				window.print();
			});
			$("#report_content").on("click", ".io-area-card[data-area]", function () {
				var id = $(this).data("area");
				$("#select_area").val(String(id)).trigger("change");
				$("#btn_generate").click();
			});
			$("#report_content").on("click", ".io-tab", function () {
				var pane = $(this).data("pane");
				$(".io-tab").removeClass("active");
				$(this).addClass("active");
				$(".io-pane").attr("hidden", true);
				$("#" + pane).removeAttr("hidden");
			});
			$("#report_content").on("click", ".io-day", function () {
				var day = String($(this).data("day"));
				$(".io-day").removeClass("active");
				$(this).addClass("active");
				if (day === "all") {
					$(".io-visit-row").show();
					$("#ioDayCaption").text("All activity days");
				} else {
					$(".io-visit-row").hide();
					$(".io-visit-row[data-day='" + day + "']").show();
					$("#ioDayCaption").text("Day " + day);
				}
				var $vis = day === "all" ? $(".io-visit-row") : $(".io-visit-row[data-day='" + day + "']");
				var ins = $vis.length, outs = $vis.filter("[data-out='1']").length;
				$("#ioDayIn").text(ins);
				$("#ioDayOut").text(outs);
				$("#ioDayMiss").text(ins - outs);
				$("#ioDayEmpty").toggle($vis.length === 0);
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
$dayKpi = ['in' => 0, 'out' => 0, 'miss' => 0];
foreach ($visits as $vv) {
	if ($defaultDay > 0 && (int) $vv['day'] !== $defaultDay) {
		continue;
	}
	$dayKpi['in']++;
	if (!empty($vv['complete'])) {
		$dayKpi['out']++;
	} else {
		$dayKpi['miss']++;
	}
}
?>
<div id="printable">
	<div class="io-kpi-grid">
		<div class="io-kpi"><div class="lbl">Students</div><div class="val"><?= (int) ($kpi['students'] ?? 0); ?></div></div>
		<div class="io-kpi blue"><div class="lbl">Scanned</div><div class="val"><?= (int) ($kpi['scanned'] ?? 0); ?></div></div>
		<div class="io-kpi in"><div class="lbl">IN taps</div><div class="val"><?= (int) ($kpi['in_count'] ?? 0); ?></div></div>
		<div class="io-kpi out"><div class="lbl">OUT / checkout</div><div class="val"><?= (int) ($kpi['out_count'] ?? 0); ?></div></div>
		<div class="io-kpi warn"><div class="lbl">Missing OUT</div><div class="val"><?= (int) ($kpi['missing_out'] ?? 0); ?></div></div>
		<div class="io-kpi"><div class="lbl">Coverage</div><div class="val"><?= (int) ($kpi['coverage'] ?? 0); ?>%</div></div>
	</div>

	<?php if (count($areaStats) > 0) : ?>
		<div class="io-area-map">
			<?php foreach ($areaStats as $as) : ?>
				<button type="button" class="io-area-card<?= $singleArea ? ' active' : ''; ?>" data-area="<?= (int) $as['id']; ?>">
					<div class="an"><?= esc($as['name']); ?></div>
					<div class="as">
						<span><b><?= (int) $as['students']; ?></b> students</span>
						<span><b><?= (int) $as['in_count']; ?></b> IN</span>
						<span><b><?= (int) $as['out_count']; ?></b> OUT</span>
					</div>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="io-tabs">
		<button type="button" class="io-tab active" data-pane="ioPaneGrid">Daily IN / OUT</button>
		<button type="button" class="io-tab" data-pane="ioPaneMissing">Missing OUT (<?= count($missing); ?>)</button>
		<button type="button" class="io-tab" data-pane="ioPaneNever">Never scanned (<?= count($never); ?>)</button>
	</div>

	<div class="io-pane" id="ioPaneGrid">
		<div class="io-letter">
			<div class="io-meta">
				<div>
					<strong><?= esc($school_name ?? ''); ?></strong><br>
					<?= lang("app.sClass"); ?>: <?= esc($classe ?? ''); ?><br>
					<?= lang("app.attendanceArea"); ?>: <?= esc($attendance_area ?? ''); ?><br>
					<?= lang("app.month"); ?>: <?= esc($month_label ?? $month ?? ''); ?>
				</div>
				<div>
					<?= lang("app.printedOn"); ?>: <?= date("Y-m-d H:i"); ?><br>
					<?= lang("app.totalStudents"); ?>: <?= count($students); ?>
				</div>
			</div>
			<h4><?= lang("app.StudentInOutmonthlyReport"); ?><?= !empty($attendance_area) ? ' — ' . esc($attendance_area) : ''; ?></h4>

			<?php if (count($activeDays) === 0) : ?>
				<div class="io-empty">No NFC IN/OUT scans for this filter in <?= esc($month_label ?? ''); ?>.</div>
			<?php else : ?>
				<div class="io-daybar">
					<button type="button" class="io-day<?= $defaultDay === 0 ? ' active' : ''; ?>" data-day="all">All days</button>
					<?php foreach ($activeDays as $ds) :
						$d = (int) $ds['day'];
						?>
						<button type="button" class="io-day<?= $d === $defaultDay ? ' active' : ''; ?>" data-day="<?= $d; ?>">
							<?= sprintf('%02d', $d); ?>
							<small><?= (int) $ds['in_count']; ?> IN</small>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="io-day-kpis">
					<div><span class="lbl">Showing</span> <strong id="ioDayCaption"><?= $defaultDay > 0 ? 'Day ' . $defaultDay : 'All activity days'; ?></strong></div>
					<div class="io-dk"><span class="in">IN</span> <b id="ioDayIn"><?= (int) $dayKpi['in']; ?></b></div>
					<div class="io-dk"><span class="out">OUT</span> <b id="ioDayOut"><?= (int) $dayKpi['out']; ?></b></div>
					<div class="io-dk"><span class="miss">No checkout</span> <b id="ioDayMiss"><?= (int) $dayKpi['miss']; ?></b></div>
				</div>
				<table class="io-log">
					<thead>
					<tr>
						<th>#</th>
						<th>Student</th>
						<?php if ($showClassCol) : ?><th>Class</th><?php endif; ?>
						<th>Day</th>
						<?php if (!$singleArea) : ?><th>Location</th><?php endif; ?>
						<th>IN</th>
						<th>OUT / checkout</th>
						<th>Duration</th>
						<th>Status</th>
					</tr>
					</thead>
					<tbody>
					<?php
					$n = 1;
					foreach ($visits as $v) :
						$day = (int) $v['day'];
						$hide = ($defaultDay > 0 && $day !== $defaultDay);
						$complete = !empty($v['complete']);
						?>
						<tr class="io-visit-row<?= $complete ? '' : ' is-miss'; ?>" data-day="<?= $day; ?>" data-out="<?= $complete ? '1' : '0'; ?>"<?= $hide ? ' style="display:none"' : ''; ?>>
							<td><?= $n++; ?></td>
							<td>
								<strong><?= esc(trim(($v['fname'] ?? '') . ' ' . ($v['lname'] ?? ''))); ?></strong>
								<div class="io-reg"><?= esc($v['regno'] ?? ''); ?></div>
							</td>
							<?php if ($showClassCol) : ?><td><?= esc($v['class_name'] ?? ''); ?></td><?php endif; ?>
							<td><?= sprintf('%02d', $day); ?></td>
							<?php if (!$singleArea) : ?><td><?= esc($v['area'] ?? ''); ?></td><?php endif; ?>
							<td><span class="io-time in"><?= esc($v['in'] ?? '—'); ?></span></td>
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
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="io-empty" id="ioDayEmpty" hidden>No scans on this day.</div>
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
					<th>IN</th>
					<th>OUT / checkout</th>
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
						<td><span class="io-time none">No checkout</span></td>
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
