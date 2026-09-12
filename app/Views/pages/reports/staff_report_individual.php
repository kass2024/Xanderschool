<link rel="stylesheet" href="<?= base_url('assets/css/inout-report.css'); ?>?v=5">
<?php
if ($show_header) {
	$defaultStart = $default_start ?? date('Y-m-01');
	$defaultEnd = $default_end ?? date('Y-m-d');
	$defaultMonth = $default_month ?? date('Y-m');
	$ayBounds = $ay_bounds ?? ['start' => $defaultStart, 'end' => $defaultEnd, 'label' => ($academic_year_title ?? '')];
	$ayMonths = $ay_months ?? [];
	?>
	<div class="io-page">
		<div class="io-filters">
			<form id="frm_report" method="get" target="_blank" action="<?= base_url('staff_individual_report_data/true'); ?>">
				<div class="io-ay-banner">
					<strong>Active academic year:</strong> <?= esc($ayBounds['label'] ?? ($academic_year_title ?? '')); ?>
					<span>· Reports limited to <?= esc($ayBounds['start'] ?? ''); ?> → <?= esc($ayBounds['end'] ?? ''); ?></span>
				</div>
				<div class="io-filters-row">
					<div class="io-field" style="max-width:220px;">
						<label>Report type</label>
						<select class="form-control" id="report_type" name="report_type">
							<option value="overall" selected>Overall report (by shift)</option>
							<option value="absent">Absent staff</option>
							<option value="individual">Individual detail</option>
						</select>
					</div>
					<div class="io-field" style="max-width:180px;">
						<label>Period</label>
						<select class="form-control" id="period_mode" name="period_mode">
							<option value="month" selected>Month</option>
							<option value="range">Date range</option>
						</select>
					</div>
					<div class="io-field io-period-month" style="max-width:220px;">
						<label>Month</label>
						<select class="form-control" id="month_key" name="month_key">
							<?php foreach ($ayMonths as $m): ?>
								<option value="<?= esc($m['value']); ?>" <?= ($m['value'] === $defaultMonth) ? 'selected' : ''; ?>><?= esc($m['label']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="io-field io-period-range" style="display:none;">
						<label><?= lang("app.startDate"); ?></label>
						<input type="date" class="form-control" id="date1" name="date1"
							   min="<?= esc($ayBounds['start'] ?? ''); ?>"
							   max="<?= esc(min($ayBounds['end'] ?? date('Y-m-d'), date('Y-m-d'))); ?>"
							   value="<?= esc($defaultStart); ?>">
					</div>
					<div class="io-field io-period-range" style="display:none;">
						<label><?= lang("app.endDate"); ?></label>
						<input type="date" class="form-control" id="date2" name="date2"
							   min="<?= esc($ayBounds['start'] ?? ''); ?>"
							   max="<?= esc(min($ayBounds['end'] ?? date('Y-m-d'), date('Y-m-d'))); ?>"
							   value="<?= esc($defaultEnd); ?>">
					</div>
					<div class="io-field io-staff-field" style="max-width:280px;display:none;">
						<label><?= lang("app.selectStaff"); ?></label>
						<select class="select2 form-control" id="select_staff" name="staff">
							<option value="0" selected><?= lang("app.allStaffs"); ?></option>
							<?php foreach ($staffs as $staff): ?>
								<option value="<?= (int) $staff['id']; ?>"><?= esc($staff['id'] . ': ' . $staff['name']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="io-actions">
						<button class="btn btn-success" id="btn_generate" type="button"><i class="fa fa-chart-bar"></i> <?= lang("app.generate"); ?></button>
						<button class="btn btn-outline-primary" id="btn_print" type="button"><i class="fa fa-print"></i> <?= lang("app.print"); ?></button>
						<button class="btn btn-primary" type="submit"><i class="fa fa-file-pdf"></i> <?= lang("app.export"); ?></button>
					</div>
				</div>
			</form>
		</div>
		<div id="report_content">
			<div class="io-empty">
				Choose <strong>Overall</strong> for all staff summaries by shift, <strong>Absent staff</strong> for absentees in the period,
				or <strong>Individual</strong> for day-by-day detail (one staff, or <strong>View all staffs</strong> for everyone). Period is limited to the active academic year.
			</div>
		</div>
	</div>
	<script>
		$(function () {
			function syncPeriodUi() {
				var month = $("#period_mode").val() === "month";
				$(".io-period-month").toggle(month);
				$(".io-period-range").toggle(!month);
			}
			function syncReportUi() {
				var individual = $("#report_type").val() === "individual";
				$(".io-staff-field").toggle(individual);
			}
			$("#period_mode").on("change", syncPeriodUi);
			$("#report_type").on("change", syncReportUi);
			syncPeriodUi();
			syncReportUi();

			$("#btn_generate").on("click", function (e) {
				e.preventDefault();
				if ($("#period_mode").val() === "month") {
					if (!$("#month_key").val()) {
						toastada.warning("<?= lang("app.pleaseSelectMonth"); ?>");
						return;
					}
				} else {
					if (!$("#date1").val()) {
						toastada.warning("<?= lang("app.strtDateErr"); ?>");
						return;
					}
					if (!$("#date2").val()) {
						toastada.warning("<?= lang("app.endDateErr"); ?>");
						return;
					}
				}
				$("#btn_generate").text("<?= lang("app.pleaseWait"); ?>").prop("disabled", true);
				$("#report_content").load("<?= base_url('staff_individual_report_data'); ?>", $("#frm_report").serialize(), function () {
					$("#btn_generate").html('<i class="fa fa-chart-bar"></i> <?= lang("app.generate"); ?>').prop("disabled", false);
				});
			});
			$("#btn_print").on("click", function () {
				if (!$("#printable").length) {
					toastada.warning("Generate the report first.");
					return;
				}
				window.print();
			});
			$("#report_content").on("click", ".io-tab", function () {
				var pane = $(this).data("pane");
				$(".io-tab").removeClass("active");
				$(this).addClass("active");
				$(".io-pane").attr("hidden", true);
				$("#" + pane).removeAttr("hidden");
			});
		});
	</script>
	<?php
	return;
}

$staffs = $staffs ?? [];
$reportType = $report_type ?? 'overall';
$ayBounds = $ay_bounds ?? [];
$date1_unix = strtotime($date1);
$date2_unix = strtotime($date2) + 86399;
$staffIds = array_map(static function ($s) {
	return (int) $s['id'];
}, $staffs);
$clocksByStaff = \App\Libraries\StaffAttendanceReport::loadClocksByStaff($staffIds, $date1_unix, $date2_unix);
$summaries = [];
foreach ($staffs as $staff) {
	$clocks = $clocksByStaff[(int) $staff['id']] ?? [];
	$sum = \App\Libraries\StaffAttendanceReport::summarize($staff, $date1, $date2, $clocks);
	if ($reportType === 'individual') {
		$sum['days'] = \App\Libraries\StaffAttendanceReport::calendarDays($staff, $date1, $date2, $sum);
	}
	$summaries[] = $sum;
}
if ($reportType === 'absent') {
	$summaries = array_values(array_filter($summaries, static function ($r) {
		return (int) $r['absent'] > 0;
	}));
}
$org = \App\Libraries\StaffAttendanceReport::orgKpis($summaries);
$byShift = \App\Libraries\StaffAttendanceReport::groupByShift($summaries);
$stClass = static function ($code) {
	$map = ['present' => 'present', 'late' => 'late', 'early' => 'early', 'nco' => 'nco', 'absent' => 'absent', 'leave' => 'leave'];
	return $map[$code] ?? 'open';
};
$rateClass = static function ($pct) {
	if ($pct >= 90) {
		return 'ok';
	}
	if ($pct >= 80) {
		return 'warn';
	}
	return 'bad';
};
$reportTitles = [
	'overall' => 'Overall attendance report',
	'absent' => 'Absent staff report',
	'individual' => count($summaries) > 1 ? 'Individual attendance report — all staff' : 'Individual attendance report',
];
$reportTitle = $reportTitles[$reportType] ?? 'Attendance report';
$renderSummaryTable = static function (array $list, $rateClass) {
	if (count($list) === 0) {
		echo '<div class="io-empty">No staff in this group.</div>';
		return;
	}
	?>
	<table class="io-log">
		<thead>
		<tr>
			<th>#</th>
			<th>Staff</th>
			<th>Post</th>
			<th>Scheduled</th>
			<th>Present</th>
			<th>Absent</th>
			<th>Leave</th>
			<th>Late</th>
			<th>Hours</th>
			<th>Attendance</th>
			<th>Punctuality</th>
		</tr>
		</thead>
		<tbody>
		<?php $n = 1; foreach ($list as $r) : ?>
			<tr class="<?= (int) $r['absent'] > 0 || (int) $r['nco'] > 0 ? 'is-miss' : ''; ?>">
				<td><?= $n++; ?></td>
				<td>
					<strong><?= esc($r['name']); ?></strong>
					<div class="io-reg">ID <?= (int) $r['id']; ?></div>
				</td>
				<td><?= esc($r['post'] ?: '—'); ?></td>
				<td><?= (int) $r['scheduled']; ?></td>
				<td><?= (int) $r['present']; ?></td>
				<td><?= (int) $r['absent']; ?></td>
				<td><?= (int) $r['leave']; ?></td>
				<td><?= (int) $r['late_count']; ?><?php if ((int) $r['late_min'] > 0) : ?> <span class="io-reg">(<?= (int) $r['late_min']; ?> min)</span><?php endif; ?></td>
				<td><?= esc($r['hours_worked']); ?></td>
				<td><span class="io-rate <?= $rateClass((int) $r['attendance_rate']); ?>"><?= (int) $r['attendance_rate']; ?>%</span></td>
				<td><span class="io-rate <?= $rateClass((int) $r['punctuality']); ?>"><?= (int) $r['punctuality']; ?>%</span></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
};
?>
<div id="printable">
	<div class="io-kpi-grid">
		<div class="io-kpi"><div class="lbl">Staff</div><div class="val"><?= (int) $org['staff']; ?></div></div>
		<div class="io-kpi in"><div class="lbl">Attendance rate</div><div class="val"><?= (int) $org['attendance_rate']; ?>%</div></div>
		<div class="io-kpi warn"><div class="lbl">Absenteeism</div><div class="val"><?= (int) $org['absenteeism']; ?>%</div></div>
		<div class="io-kpi blue"><div class="lbl">Punctuality</div><div class="val"><?= (int) $org['punctuality']; ?>%</div></div>
		<div class="io-kpi warn"><div class="lbl">Late arrivals</div><div class="val"><?= (int) $org['late']; ?></div></div>
		<div class="io-kpi out"><div class="lbl">Absent days</div><div class="val"><?= (int) $org['absent']; ?></div></div>
		<div class="io-kpi out"><div class="lbl">No checkout</div><div class="val"><?= (int) $org['nco']; ?></div></div>
		<div class="io-kpi blue"><div class="lbl">Hours worked</div><div class="val"><?= esc((string) $org['hours']); ?>h</div></div>
	</div>

	<div class="io-letter" style="margin-bottom:14px;">
		<div class="io-meta">
			<div>
				<strong><?= esc($school_name ?? ''); ?></strong><br>
				Academic year: <?= esc($ayBounds['label'] ?? ($academic_year_title ?? '')); ?><br>
				Period: <?= esc($date1); ?> → <?= esc($date2); ?>
			</div>
			<div>
				<?= lang("app.printedOn"); ?>: <?= date("Y-m-d H:i"); ?><br>
				Report: <?= esc($reportTitle); ?>
			</div>
		</div>
		<h4><?= esc($reportTitle); ?></h4>
	</div>

	<?php if ($reportType === 'overall' || $reportType === 'absent') : ?>
		<?php if (count($summaries) === 0) : ?>
			<div class="io-empty"><?= $reportType === 'absent' ? 'No absent staff in this period.' : 'No staff found for this period.'; ?></div>
		<?php else : ?>
			<?php if ($reportType === 'overall') : ?>
				<div class="io-tabs">
					<button type="button" class="io-tab active" data-pane="stPaneAll">All shifts (<?= count($summaries); ?>)</button>
					<?php $si = 0; foreach ($byShift as $shiftName => $rows) : $si++; ?>
						<button type="button" class="io-tab" data-pane="stPaneShift<?= $si; ?>"><?= esc($shiftName); ?> (<?= count($rows); ?>)</button>
					<?php endforeach; ?>
				</div>
				<div class="io-pane" id="stPaneAll">
					<?php $si = 0; foreach ($byShift as $shiftName => $rows) : $si++; ?>
						<div class="io-shift-block">
							<div class="io-shift-head">
								<strong><?= esc($shiftName); ?></strong>
								<span><?= count($rows); ?> staff · Attendance <?= (int) \App\Libraries\StaffAttendanceReport::orgKpis($rows)['attendance_rate']; ?>%</span>
							</div>
							<?php $renderSummaryTable($rows, $rateClass); ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php $si = 0; foreach ($byShift as $shiftName => $rows) : $si++; ?>
					<div class="io-pane" id="stPaneShift<?= $si; ?>" hidden>
						<div class="io-shift-block">
							<div class="io-shift-head">
								<strong><?= esc($shiftName); ?></strong>
								<span><?= count($rows); ?> staff</span>
							</div>
							<?php $renderSummaryTable($rows, $rateClass); ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="io-shift-block">
					<div class="io-shift-head">
						<strong>Staff with absence</strong>
						<span><?= count($summaries); ?> staff · <?= (int) $org['absent']; ?> absent days</span>
					</div>
					<?php $renderSummaryTable($summaries, $rateClass); ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	<?php else : ?>
		<?php foreach ($summaries as $si => $sum) : ?>
			<div class="io-letter io-staff-detail<?= $si < count($summaries) - 1 ? ' io-staff-break' : ''; ?>" style="margin-bottom:18px;">
				<div class="io-profile">
					<h3><?= esc($sum['name']); ?></h3>
					<div class="meta">
						<?= esc($sum['post'] ?: 'Staff'); ?>
						<?php if ($sum['shift'] !== '') : ?> · Shift: <?= esc($sum['shift']); ?><?php endif; ?>
						<?php if ($sum['email'] !== '') : ?> · <?= esc($sum['email']); ?><?php endif; ?>
						<br>
						<?= lang("app.mFrom"); ?> <?= esc($date1); ?>
						<?= lang("app.mTo"); ?> <?= esc($date2); ?>
					</div>
				</div>
				<div class="io-kpi-grid">
					<div class="io-kpi in"><div class="lbl">Attendance</div><div class="val"><?= (int) $sum['attendance_rate']; ?>%</div></div>
					<div class="io-kpi"><div class="lbl">Present</div><div class="val"><?= (int) $sum['present']; ?></div></div>
					<div class="io-kpi out"><div class="lbl">Absent</div><div class="val"><?= (int) $sum['absent']; ?></div></div>
					<div class="io-kpi blue"><div class="lbl">Leave</div><div class="val"><?= (int) $sum['leave']; ?></div></div>
					<div class="io-kpi warn"><div class="lbl">Late</div><div class="val"><?= (int) $sum['late_count']; ?></div></div>
					<div class="io-kpi blue"><div class="lbl">Punctuality</div><div class="val"><?= (int) $sum['punctuality']; ?>%</div></div>
				</div>
				<div class="io-day-kpis">
					<div><span class="lbl">Scheduled</span> <strong><?= (int) $sum['scheduled']; ?></strong></div>
					<div class="io-dk"><span class="in">Hours</span> <b><?= esc($sum['hours_worked']); ?></b></div>
					<div class="io-dk"><span class="miss">Late min</span> <b><?= (int) $sum['late_min']; ?></b></div>
					<div class="io-dk"><span class="out">Early leave min</span> <b><?= (int) $sum['early_min']; ?></b></div>
					<div class="io-dk"><span class="miss">No checkout</span> <b><?= (int) $sum['nco']; ?></b></div>
				</div>
				<h4><?= lang("app.individualReportAtt"); ?></h4>
				<?php if (count($sum['days']) === 0) : ?>
					<div class="io-empty">No scheduled working days in this period.</div>
				<?php else : ?>
					<table class="io-log">
						<thead>
						<tr>
							<th>#</th>
							<th>Date</th>
							<th>Shift</th>
							<th>IN</th>
							<th>OUT / checkout</th>
							<th>Duration</th>
							<th>Late</th>
							<th>Early</th>
							<th>Status</th>
						</tr>
						</thead>
						<tbody>
						<?php $n = 1; foreach ($sum['days'] as $d) :
							$code = $d['code'] ?? 'present';
							?>
							<tr class="<?= in_array($code, ['absent', 'nco'], true) ? 'is-miss' : ''; ?>">
								<td><?= $n++; ?></td>
								<td><?= esc($d['label']); ?></td>
								<td><?= esc($d['shift'] ?: '—'); ?></td>
								<td>
									<?php if ($d['in'] !== '') : ?>
										<span class="io-time in"><?= esc($d['in']); ?></span>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ($d['out'] !== '') : ?>
										<span class="io-time out"><?= esc($d['out']); ?></span>
									<?php elseif ($code === 'nco') : ?>
										<span class="io-time none">No checkout</span>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td><?= esc($d['duration']); ?></td>
								<td><?= (int) $d['late_min'] > 0 ? (int) $d['late_min'] . ' min' : '—'; ?></td>
								<td><?= (int) $d['early_min'] > 0 ? (int) $d['early_min'] . ' min' : '—'; ?></td>
								<td><span class="io-st <?= $stClass($code); ?>"><?= esc($d['label_status']); ?></span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<div style="margin-top:10px;font-size:.8rem;color:#64748b;">
		Attendance rate = (present + approved leave) / scheduled shift days.
		Punctuality = on-time clock-in / present days. Scoped to active academic year <?= esc($ayBounds['label'] ?? ''); ?>.
	</div>
	<div style="text-align:right;color:#94a3b8;margin-top:8px;font-size:.8rem;"><?= lang("app.generatedbySomanet"); ?></div>
</div>
