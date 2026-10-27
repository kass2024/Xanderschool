<style>
	.late-page { max-width: 1100px; }
	.late-note { color: #64748b; margin: 0 0 14px; }
	.late-filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 14px; }
	.late-filters label { display: block; font-size: .78rem; font-weight: 700; color: #64748b; margin-bottom: 4px; }
	.late-kpis { display: flex; gap: 10px; margin-bottom: 14px; }
	.late-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; min-width: 140px; }
	.late-kpi span { display: block; color: #64748b; font-size: .75rem; font-weight: 700; text-transform: uppercase; }
	.late-kpi strong { font-size: 1.4rem; color: #9a3412; }
	.late-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
	.late-card table { width: 100%; margin: 0; }
	.late-card th { background: #f8fafc; font-size: .78rem; text-transform: uppercase; color: #64748b; }
	.late-empty { padding: 28px 16px; text-align: center; color: #64748b; }
	@media print {
		.app-sidebar-wrapper, .app-header, .app-footer, .late-filters, .hamburger { display: none !important; }
		.late-card { border: 0; }
	}
</style>
<div class="late-page">
	<p class="late-note">Students who arrived at or after a location's consider-lateness time. Each late arrival deducts 5 discipline marks. Parents are not notified.</p>
	<form class="late-filters" method="get" action="<?= base_url('lateness_report'); ?>">
		<div>
			<label>Date</label>
			<input type="date" class="form-control" name="date" value="<?= esc($report_date ?? date('Y-m-d'), 'attr'); ?>">
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
	</form>
	<div class="late-kpis">
		<div class="late-kpi"><span>Students</span><strong><?= (int) ($late_students ?? 0); ?></strong></div>
		<div class="late-kpi"><span>Marks deducted</span><strong><?= (int) ($late_marks ?? 0); ?></strong></div>
	</div>
	<div class="late-card">
		<?php if (empty($rows)) : ?>
			<div class="late-empty">No lateness on this day.</div>
		<?php else : ?>
			<table class="table table-hover mb-0">
				<thead>
				<tr>
					<th>#</th>
					<th>Student</th>
					<th>Reg no.</th>
					<th>Class</th>
					<th>Record</th>
					<th>Marks</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($rows as $i => $row) : ?>
					<tr>
						<td><?= $i + 1; ?></td>
						<td><?= esc(trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''))); ?></td>
						<td><?= esc((string) ($row['regno'] ?? '')); ?></td>
						<td><?= esc(trim((string) ($row['class_name'] ?? ''))); ?></td>
						<td><?= esc((string) ($row['comment'] ?? '')); ?></td>
						<td><strong>-<?= (int) ($row['marks'] ?? 0); ?></strong></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
