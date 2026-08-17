<link rel="stylesheet" href="<?= base_url('assets/css/inout-report.css'); ?>?v=3">
<?php
if ($show_header) {
	$months = get_months();
	$defaultMonth = (int) ($default_month ?? date('n'));
	$defaultYear = (int) ($default_year ?? date('Y'));
	$years = $years ?? [$defaultYear];
	?>
	<div class="io-page">
		<div class="io-filters">
			<form id="frm_report" method="get" target="_blank" action="<?= base_url('staff_monthly_report_data/true'); ?>">
				<div class="io-filters-row">
					<div class="io-field">
						<label><?= lang("app.selectYear"); ?></label>
						<select class="select2 form-control" id="select_year" name="year">
							<?php foreach ($years as $year): ?>
								<option value="<?= (int) $year; ?>" <?= (int) $year === $defaultYear ? 'selected' : ''; ?>><?= (int) $year; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="io-field">
						<label><?= lang("app.selectMonth"); ?></label>
						<select class="select2 form-control" id="choose_month" name="month">
							<?php for ($a = 0; $a < count($months); $a++): ?>
								<option value="<?= $a + 1; ?>" <?= ($a + 1) === $defaultMonth ? 'selected' : ''; ?>><?= esc($months[$a]); ?></option>
							<?php endfor; ?>
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
			<div class="io-empty">Choose year and month, then Generate. KPIs follow ILO attendance standards: attendance rate, absenteeism, punctuality, late arrivals, and incomplete check-out — using each staff member’s shift hours.</div>
		</div>
	</div>
	<script>
		$(function () {
			$("#btn_generate").on("click", function (e) {
				e.preventDefault();
				if (!$("#select_year").val()) {
					toastada.warning("<?= lang("app.pleaseSelectYear"); ?>");
					return;
				}
				if (!$("#choose_month").val()) {
					toastada.warning("<?= lang("app.pleaseSelectMonth"); ?>");
					return;
				}
				$("#btn_generate").text("<?= lang("app.pleaseWait"); ?>").prop("disabled", true);
				$("#report_content").load("<?= base_url('staff_monthly_report_data'); ?>", $("#frm_report").serialize(), function () {
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

$first_day = $month . "-01";
$last_day = $month . "-" . date("t", strtotime($first_day));
$monthLabel = date("F Y", strtotime($first_day));
$rows = [];
foreach ($staffs as $item) {
	$rows[] = \App\Libraries\StaffAttendanceReport::summarize(
		$item,
		$first_day,
		$last_day,
		\App\Libraries\StaffAttendanceReport::parseConcatRecords((string) ($item['records'] ?? ''))
	);
}
$kpi = \App\Libraries\StaffAttendanceReport::orgKpis($rows);
$atRisk = array_values(array_filter($rows, static function ($r) {
	return (int) $r['attendance_rate'] < 80;
}));
$lateStaff = array_values(array_filter($rows, static function ($r) {
	return (int) $r['late_count'] > 0;
}));
$ncoStaff = array_values(array_filter($rows, static function ($r) {
	return (int) $r['nco'] > 0;
}));
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
	<div class="io-kpi-grid">
		<div class="io-kpi"><div class="lbl">Staff</div><div class="val"><?= (int) $kpi['staff']; ?></div></div>
		<div class="io-kpi in"><div class="lbl">Attendance rate</div><div class="val"><?= (int) $kpi['attendance_rate']; ?>%</div></div>
		<div class="io-kpi warn"><div class="lbl">Absenteeism</div><div class="val"><?= (int) $kpi['absenteeism']; ?>%</div></div>
		<div class="io-kpi blue"><div class="lbl">Punctuality</div><div class="val"><?= (int) $kpi['punctuality']; ?>%</div></div>
		<div class="io-kpi warn"><div class="lbl">Late arrivals</div><div class="val"><?= (int) $kpi['late']; ?></div></div>
		<div class="io-kpi out"><div class="lbl">No checkout</div><div class="val"><?= (int) $kpi['nco']; ?></div></div>
	</div>

	<div class="io-tabs">
		<button type="button" class="io-tab active" data-pane="stPaneAll">All staff (<?= count($rows); ?>)</button>
		<button type="button" class="io-tab" data-pane="stPaneRisk">Below 80% (<?= count($atRisk); ?>)</button>
		<button type="button" class="io-tab" data-pane="stPaneLate">Late (<?= count($lateStaff); ?>)</button>
		<button type="button" class="io-tab" data-pane="stPaneNco">No checkout (<?= count($ncoStaff); ?>)</button>
	</div>

	<?php
	$renderTable = static function (array $list, $rateClass) {
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
				<th>Shift</th>
				<th>Scheduled</th>
				<th>Present</th>
				<th>Absent</th>
				<th>Leave</th>
				<th>Late</th>
				<th>Early leave</th>
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
					<td><?= esc($r['shift'] ?: '—'); ?></td>
					<td><?= (int) $r['scheduled']; ?></td>
					<td><?= (int) $r['present']; ?></td>
					<td><?= (int) $r['absent']; ?></td>
					<td><?= (int) $r['leave']; ?></td>
					<td><?= (int) $r['late_count']; ?><?php if ((int) $r['late_min'] > 0) : ?> <span class="io-reg">(<?= (int) $r['late_min']; ?> min)</span><?php endif; ?></td>
					<td><?= (int) $r['early_count']; ?><?php if ((int) $r['early_min'] > 0) : ?> <span class="io-reg">(<?= (int) $r['early_min']; ?> min)</span><?php endif; ?></td>
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

	<div class="io-pane" id="stPaneAll">
		<div class="io-letter">
			<?php if (!empty($pdf)) : ?>
				<div class="io-meta">
					<div>
						<strong><?= lang("app.republic"); ?></strong><br>
						<strong><?= lang("app.ministry"); ?></strong><br>
						<strong><?= esc($school_name ?? ''); ?></strong>
					</div>
					<div>
						<?php if (!empty($school_logo)) : ?>
							<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" style="width:90px" alt="">
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			<div class="io-meta">
				<div>
					<strong><?= esc($school_name ?? ''); ?></strong><br>
					<?= lang("app.month"); ?>: <?= esc($monthLabel); ?><br>
					Staff: <?= count($rows); ?>
				</div>
				<div>
					<?= lang("app.printedOn"); ?>: <?= date("Y-m-d H:i"); ?><br>
					Hours worked: <?= esc((string) $kpi['hours']); ?>h
				</div>
			</div>
			<h4><?= lang("app.employeesMonthlReport"); ?> — <?= esc($monthLabel); ?></h4>
			<?php $renderTable($rows, $rateClass); ?>
			<div style="margin-top:10px;font-size:.8rem;color:#64748b;">
				Attendance rate = (present + approved leave) / scheduled working days.
				Punctuality = on-time arrivals / present days. Late uses each staff shift start time.
			</div>
			<div style="text-align:right;color:#94a3b8;margin-top:8px;font-size:.8rem;"><?= lang("app.generatedbySomanet"); ?></div>
		</div>
	</div>
	<div class="io-pane" id="stPaneRisk" hidden>
		<div class="io-letter">
			<h4>Attendance below 80%</h4>
			<?php $renderTable($atRisk, $rateClass); ?>
		</div>
	</div>
	<div class="io-pane" id="stPaneLate" hidden>
		<div class="io-letter">
			<h4>Late arrivals</h4>
			<?php $renderTable($lateStaff, $rateClass); ?>
		</div>
	</div>
	<div class="io-pane" id="stPaneNco" hidden>
		<div class="io-letter">
			<h4>Incomplete check-out</h4>
			<?php $renderTable($ncoStaff, $rateClass); ?>
		</div>
	</div>
</div>
