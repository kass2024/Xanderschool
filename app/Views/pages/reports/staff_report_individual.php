<link rel="stylesheet" href="<?= base_url('assets/css/inout-report.css'); ?>?v=3">
<?php
if ($show_header) {
	$defaultStart = $default_start ?? date('Y-m-01');
	$defaultEnd = $default_end ?? date('Y-m-d');
	?>
	<div class="io-page">
		<div class="io-filters">
			<form id="frm_report" method="get" target="_blank" action="<?= base_url('staff_individual_report_data/true'); ?>">
				<div class="io-filters-row">
					<div class="io-field" style="max-width:280px;">
						<label><?= lang("app.selectStaff"); ?></label>
						<select class="select2 form-control" id="select_staff" name="staff">
							<option value="0" selected><?= lang("app.allStaffs"); ?></option>
							<?php foreach ($staffs as $staff): ?>
								<option value="<?= (int) $staff['id']; ?>"><?= esc($staff['id'] . ': ' . $staff['name']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="io-field">
						<label><?= lang("app.startDate"); ?></label>
						<input type="date" class="form-control" id="date1" name="date1" value="<?= esc($defaultStart); ?>">
					</div>
					<div class="io-field">
						<label><?= lang("app.endDate"); ?></label>
						<input type="date" class="form-control" id="date2" name="date2" value="<?= esc($defaultEnd); ?>">
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
			<div class="io-empty">Select a staff member (or all), pick dates, then Generate. Daily IN/OUT is compared with that person’s shift. KPIs: attendance rate, absenteeism, punctuality, late minutes, and check-out completeness.</div>
		</div>
	</div>
	<script>
		$(function () {
			$("#btn_generate").on("click", function (e) {
				e.preventDefault();
				if ($("#select_staff").val() == null) {
					toastada.warning("<?= lang("app.pleaseSelectStaff"); ?>");
					return;
				}
				if (!$("#date1").val()) {
					toastada.warning("<?= lang("app.strtDateErr"); ?>");
					return;
				}
				if (!$("#date2").val()) {
					toastada.warning("<?= lang("app.endDateErr"); ?>");
					return;
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
		});
	</script>
	<?php
	return;
}

$staffs = $staffs ?? [];
$summaries = [];
$attMdl = new \App\Models\AttendanceRecordsModel();
$date1_unix = strtotime($date1);
$date2_unix = strtotime($date2) + 86399;
foreach ($staffs as $staff) {
	$records = $attMdl->select("time_in, coalesce(time_out,0) as time_out")
		->where("user_id", $staff['id'])
		->where("user_type", 1)
		->where("time_in>='$date1_unix' and time_in<='$date2_unix'")
		->groupBy("user_id")
		->groupBy("date_format(from_unixtime(time_in),'%d-%m-%Y')")
		->orderBy("time_in", "ASC")
		->get()->getResultArray();
	$clocks = [];
	foreach ($records as $rec) {
		$clocks[] = ['in' => (int) $rec['time_in'], 'out' => (int) $rec['time_out']];
	}
	$sum = \App\Libraries\StaffAttendanceReport::summarize($staff, $date1, $date2, $clocks);
	$sum['days'] = \App\Libraries\StaffAttendanceReport::calendarDays($staff, $date1, $date2, $sum);
	$summaries[] = $sum;
}
$org = \App\Libraries\StaffAttendanceReport::orgKpis($summaries);
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
?>
<div id="printable">
	<?php if (count($summaries) > 1) : ?>
		<div class="io-kpi-grid">
			<div class="io-kpi"><div class="lbl">Staff</div><div class="val"><?= (int) $org['staff']; ?></div></div>
			<div class="io-kpi in"><div class="lbl">Attendance rate</div><div class="val"><?= (int) $org['attendance_rate']; ?>%</div></div>
			<div class="io-kpi warn"><div class="lbl">Absenteeism</div><div class="val"><?= (int) $org['absenteeism']; ?>%</div></div>
			<div class="io-kpi blue"><div class="lbl">Punctuality</div><div class="val"><?= (int) $org['punctuality']; ?>%</div></div>
			<div class="io-kpi warn"><div class="lbl">Late arrivals</div><div class="val"><?= (int) $org['late']; ?></div></div>
			<div class="io-kpi out"><div class="lbl">No checkout</div><div class="val"><?= (int) $org['nco']; ?></div></div>
		</div>
	<?php endif; ?>

	<?php foreach ($summaries as $si => $sum) : ?>
		<div class="io-letter" style="margin-bottom:18px;">
			<?php if (!empty($pdf) && $si === 0) : ?>
				<div class="io-meta">
					<div>
						<strong><?= lang("app.republic"); ?></strong><br>
						<strong><?= esc($school_name ?? ''); ?></strong>
					</div>
				</div>
			<?php endif; ?>
			<div class="io-profile">
				<h3><?= esc($sum['name']); ?></h3>
				<div class="meta">
					<?= esc($sum['post'] ?: 'Staff'); ?>
					<?php if ($sum['shift'] !== '') : ?> · Shift: <?= esc($sum['shift']); ?><?php endif; ?>
					<?php if ($sum['email'] !== '') : ?> · <?= esc($sum['email']); ?><?php endif; ?>
					<br>
					<?= lang("app.mFrom"); ?> <?= esc($date1); ?>
					<?= lang("app.mTo"); ?> <?= esc($date2); ?>
					· <?= lang("app.printedOn"); ?> <?= date("Y-m-d H:i"); ?>
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
			<div style="margin-top:10px;font-size:.8rem;color:#64748b;">
				Attendance rate = (present + approved leave) / scheduled shift days.
				Punctuality = on-time clock-in / present days. Late / early leave use this staff member’s shift.
			</div>
			<div style="text-align:right;color:#94a3b8;margin-top:8px;font-size:.8rem;"><?= lang("app.generatedbySomanet"); ?></div>
		</div>
	<?php endforeach; ?>
</div>
