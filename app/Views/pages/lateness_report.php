<?php
$mode = (string) ($mode ?? 'day');
$periodLabel = (string) ($period_label ?? '');
?>
<style>
	.late-page { max-width: 1180px; margin: 0 auto; padding: 8px 4px 28px; }
	.late-hero { text-align: center; margin-bottom: 18px; }
	.late-hero h2 { margin: 0 0 4px; font-size: 1.55rem; font-weight: 800; color: #0f172a; }
	.late-hero p { margin: 0; color: #64748b; }
	.late-panel {
		background: #fff; border: 1px solid #e8edf3; border-radius: 16px;
		box-shadow: 0 1px 3px rgba(15, 23, 42, .06); padding: 14px 16px; margin-bottom: 14px;
	}
	.late-modes { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
	.late-mode {
		border: 1px solid #e2e8f0; background: #f8fafc; color: #334155;
		border-radius: 999px; padding: 7px 14px; font-weight: 700; cursor: pointer;
	}
	.late-mode.is-on { background: #0f766e; border-color: #0f766e; color: #fff; }
	.late-filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
	.late-filters label { display: block; font-size: .75rem; font-weight: 700; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .03em; }
	.late-filters .form-control { min-width: 160px; }
	.late-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
	.late-kpi { background: #fff; border: 1px solid #e8edf3; border-radius: 14px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(15, 23, 42, .05); }
	.late-kpi span { display: block; color: #64748b; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
	.late-kpi strong { display: block; margin-top: 4px; font-size: 1.55rem; color: #0f172a; line-height: 1.1; }
	.late-kpi.is-marks strong { color: #c2410c; }
	.late-places { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
	.late-place { background: #f0fdfa; color: #0f766e; border-radius: 999px; padding: 6px 12px; font-weight: 700; font-size: .82rem; }
	.late-card { background: #fff; border: 1px solid #e8edf3; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(15, 23, 42, .05); }
	.late-card table { width: 100%; margin: 0; }
	.late-card th { background: #f8fafc; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; border-top: 0; }
	.late-card td { vertical-align: middle; }
	.late-who strong { display: block; color: #0f172a; }
	.late-who small { color: #64748b; }
	.late-mark { display: inline-block; background: #ffedd5; color: #c2410c; border-radius: 999px; padding: 3px 10px; font-weight: 800; }
	.late-early { display: inline-block; background: #dcfce7; color: #166534; border-radius: 999px; padding: 3px 10px; font-weight: 800; }
	.late-kpi.is-early strong { color: #166534; }
	.late-place.is-early { background: #dcfce7; color: #166534; }
	.late-section { margin: 18px 0 8px; font-size: 1.05rem; font-weight: 800; color: #0f172a; }
	.late-time { font-weight: 800; color: #0f172a; }
	.late-cut { color: #64748b; font-size: .82rem; }
	.late-empty { padding: 36px 16px; text-align: center; color: #64748b; }
	.late-empty strong { display: block; color: #0f172a; margin-bottom: 4px; }
	@media (max-width: 800px) { .late-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
	@media print {
		.app-sidebar-wrapper, .app-header, .app-footer, .late-panel, .hamburger { display: none !important; }
		.late-card, .late-kpi { box-shadow: none; }
	}
</style>
<div class="late-page">
	<div class="late-hero">
		<h2>Lateness</h2>
		<p><?= esc($periodLabel); ?> · Each late arrival, and each student who does not tap by the consider-lateness time, deducts 5 discipline marks. Parents are not notified. Students who tapped earlier are listed with that time.</p>
	</div>

	<form class="late-panel" id="lateForm" method="get" action="<?= base_url('lateness_report'); ?>">
		<input type="hidden" name="mode" id="lateMode" value="<?= esc($mode, 'attr'); ?>">
		<div class="late-modes">
			<button type="button" class="late-mode<?= $mode === 'day' ? ' is-on' : ''; ?>" data-mode="day">Day</button>
			<button type="button" class="late-mode<?= $mode === 'week' ? ' is-on' : ''; ?>" data-mode="week">Weekly</button>
			<button type="button" class="late-mode<?= $mode === 'month' ? ' is-on' : ''; ?>" data-mode="month">Monthly</button>
			<button type="button" class="late-mode<?= $mode === 'range' ? ' is-on' : ''; ?>" data-mode="range">Date range</button>
		</div>
		<div class="late-filters">
			<div data-pane="day"<?= $mode === 'day' ? '' : ' hidden'; ?>>
				<label>Date</label>
				<input type="date" class="form-control" name="date" value="<?= esc($report_date ?? date('Y-m-d'), 'attr'); ?>">
			</div>
			<div data-pane="week"<?= $mode === 'week' ? '' : ' hidden'; ?>>
				<label>Any day in the week</label>
				<input type="date" class="form-control" name="week" value="<?= esc($week_of ?? date('Y-m-d'), 'attr'); ?>">
			</div>
			<div data-pane="month"<?= $mode === 'month' ? '' : ' hidden'; ?>>
				<label>Month</label>
				<input type="month" class="form-control" name="month" value="<?= esc($month_value ?? date('Y-m'), 'attr'); ?>">
			</div>
			<div data-pane="range"<?= $mode === 'range' ? '' : ' hidden'; ?>>
				<label>From</label>
				<input type="date" class="form-control" name="from" value="<?= esc($from_date ?? date('Y-m-01'), 'attr'); ?>">
			</div>
			<div data-pane="range"<?= $mode === 'range' ? '' : ' hidden'; ?>>
				<label>To</label>
				<input type="date" class="form-control" name="to" value="<?= esc($to_date ?? date('Y-m-d'), 'attr'); ?>">
			</div>
			<div style="min-width:220px;">
				<label><?= lang('app.sClass'); ?></label>
				<select class="form-control" name="class">
					<option value="0">All classes</option>
					<?php foreach (($classes ?? []) as $class) : ?>
						<option value="<?= (int) $class['id']; ?>" <?= (int) ($class_id ?? 0) === (int) $class['id'] ? 'selected' : ''; ?>>
							<?= esc(trim(($class['level_name'] ?? '') . ' ' . ($class['code'] ?? '') . ' ' . ($class['title'] ?? ''))); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<button type="submit" class="btn btn-success">Show</button>
			<button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>
		</div>
	</form>

	<div class="late-kpis">
		<div class="late-kpi"><span>Period</span><strong style="font-size:1.05rem;padding-top:6px;"><?= esc($periodLabel); ?></strong></div>
		<div class="late-kpi"><span>Students</span><strong><?= (int) ($late_students ?? 0); ?></strong></div>
		<div class="late-kpi"><span>Late arrivals</span><strong><?= (int) ($late_arrivals ?? 0); ?></strong></div>
		<div class="late-kpi is-early"><span>Came early</span><strong><?= (int) ($early_arrivals ?? 0); ?></strong></div>
		<div class="late-kpi is-marks"><span>Marks deducted</span><strong><?= (int) ($late_marks ?? 0); ?></strong></div>
	</div>

	<?php if (!empty($late_places)) : ?>
		<div class="late-places">
			<?php foreach ($late_places as $place => $count) : ?>
				<span class="late-place"><?= esc((string) $place); ?> · <?= (int) $count; ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="late-card">
		<?php if (empty($rows)) : ?>
			<div class="late-empty">
				<strong>No lateness in this period</strong>
				A tap at or after the consider-lateness time, or no tap once that time has passed, appears here.
			</div>
		<?php else : ?>
			<table class="table table-hover mb-0">
				<thead>
				<tr>
					<th>#</th>
					<th>Date</th>
					<th>Student</th>
					<th>Class</th>
					<th>Location</th>
					<th>Arrived</th>
					<th>Late from</th>
					<th>Marks</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($rows as $i => $row) :
					$when = (string) ($row['late_day'] ?? '');
					$whenLabel = $when !== '' ? date('D j M', strtotime($when)) : '';
					?>
					<tr>
						<td><?= $i + 1; ?></td>
						<td><?= esc($whenLabel); ?></td>
						<td class="late-who">
							<strong><?= esc(trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''))); ?></strong>
							<small><?= esc((string) ($row['regno'] ?? '')); ?></small>
						</td>
						<td><?= esc(trim((string) ($row['class_name'] ?? ''))); ?></td>
						<td><?= esc((string) ($row['place'] ?? '')); ?></td>
						<td class="late-time"><?= esc((string) ($row['arrived'] ?? '')); ?></td>
						<td class="late-cut"><?= esc((string) ($row['cutoff'] ?? '')); ?></td>
						<td><span class="late-mark">-<?= (int) ($row['marks'] ?? 0); ?></span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<h3 class="late-section">Came early</h3>
	<?php if (!empty($early_places)) : ?>
		<div class="late-places">
			<?php foreach ($early_places as $place => $count) : ?>
				<span class="late-place is-early"><?= esc((string) $place); ?> · <?= (int) $count; ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<div class="late-card">
		<?php if (empty($early_rows)) : ?>
			<div class="late-empty">
				<strong>No early taps in this period</strong>
				A tap before the consider-lateness time appears here with the clock time.
			</div>
		<?php else : ?>
			<table class="table table-hover mb-0">
				<thead>
				<tr>
					<th>#</th>
					<th>Date</th>
					<th>Student</th>
					<th>Class</th>
					<th>Location</th>
					<th>Tapped</th>
					<th>Before</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($early_rows as $i => $row) :
					$when = (string) ($row['late_day'] ?? '');
					$whenLabel = $when !== '' ? date('D j M', strtotime($when)) : '';
					?>
					<tr>
						<td><?= $i + 1; ?></td>
						<td><?= esc($whenLabel); ?></td>
						<td class="late-who">
							<strong><?= esc(trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''))); ?></strong>
							<small><?= esc((string) ($row['regno'] ?? '')); ?></small>
						</td>
						<td><?= esc(trim((string) ($row['class_name'] ?? ''))); ?></td>
						<td><?= esc((string) ($row['place'] ?? '')); ?></td>
						<td><span class="late-early"><?= esc((string) ($row['arrived'] ?? '')); ?></span></td>
						<td class="late-cut"><?= esc((string) ($row['cutoff'] ?? '')); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
<script>
(function () {
	var form = document.getElementById('lateForm');
	var modeInput = document.getElementById('lateMode');
	if (!form || !modeInput) return;
	function showMode(mode) {
		modeInput.value = mode;
		form.querySelectorAll('[data-pane]').forEach(function (el) {
			el.hidden = el.getAttribute('data-pane') !== mode;
		});
		form.querySelectorAll('.late-mode').forEach(function (btn) {
			btn.classList.toggle('is-on', btn.getAttribute('data-mode') === mode);
		});
	}
	form.querySelectorAll('.late-mode').forEach(function (btn) {
		btn.addEventListener('click', function () {
			showMode(btn.getAttribute('data-mode'));
			form.submit();
		});
	});
})();
</script>
