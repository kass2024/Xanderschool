<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= esc($doc_title ?? 'Unplaced periods'); ?></title>
<style>
body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2933; margin: 0; }
h1 { font-size: 18px; margin: 0 0 4px; }
h2 { font-size: 13px; margin: 18px 0 8px; border-bottom: 1px solid #d2d6dc; padding-bottom: 4px; }
.meta { color: #52606d; margin-bottom: 14px; }
.kpis { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
.kpis td { background: #f5f7fa; border: 1px solid #e4e7eb; padding: 8px 10px; width: 33%; }
.kpis strong { display: block; font-size: 16px; }
table.grid { width: 100%; border-collapse: collapse; }
table.grid th, table.grid td { border: 1px solid #d2d6dc; padding: 5px 6px; vertical-align: top; text-align: left; }
table.grid th { background: #e4e7eb; }
.ok { color: #0b6e4f; }
.warn { color: #b45309; }
.small { color: #52606d; font-size: 10px; }
</style>
</head>
<body>
<h1><?= esc($school_name ?? 'School'); ?></h1>
<div class="meta">
	Unplaced periods after timetable generation
	· <?= esc($phase_label ?? 'All levels'); ?>
	· <?= esc($academic_year ?? ''); ?>
	<?php if (!empty($term)): ?> · Term <?= (int) $term; ?><?php endif; ?>
	· <?= esc($generated_at ?? ''); ?>
</div>
<p class="small">
	Manage Course weekly periods are filled first. Leftover hours stay in the parking lot so no class or teacher collides.
	Listed free slots are empty for both the class and the teacher and still match special criteria.
</p>
<table class="kpis">
	<tr>
		<td><strong><?= (int) ($missed_periods ?? 0); ?></strong> Periods parked</td>
		<td><strong><?= (int) ($missed_courses ?? 0); ?></strong> Courses short of periods</td>
		<td><strong><?= (int) ($missed_teachers ?? 0); ?></strong> Teachers with parked periods</td>
	</tr>
</table>

<?php if (empty($courses)): ?>
	<p class="ok"><strong>Every Manage Course period was placed.</strong> Nothing is waiting in the parking lot.</p>
<?php else: ?>
	<h2>Courses that missed a slot</h2>
	<table class="grid">
		<thead>
			<tr>
				<th>Level</th>
				<th>Class</th>
				<th>Course</th>
				<th>Teacher</th>
				<th>Needed</th>
				<th>Placed</th>
				<th>Missed</th>
				<th>Where it can still go</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($courses as $row): ?>
			<tr>
				<td><?= esc($row['level'] ?? ''); ?></td>
				<td><?= esc($row['class'] ?? ''); ?></td>
				<td><?= esc($row['course'] ?? ''); ?></td>
				<td><?= esc($row['teacher'] ?? ''); ?></td>
				<td><?= (int) ($row['needed'] ?? 0); ?></td>
				<td><?= (int) ($row['placed'] ?? 0); ?></td>
				<td class="warn"><?= (int) ($row['missed'] ?? 0); ?></td>
				<td>
					<?php if (!empty($row['suggestions'])): ?>
						<?= esc(implode('; ', $row['suggestions'])); ?>
					<?php else: ?>
						<span class="warn">No legal empty slot — keep parked (do not overlap).</span>
					<?php endif; ?>
					<div class="small"><?= esc($row['reason'] ?? ''); ?></div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2>Teachers with parked periods</h2>
	<table class="grid">
		<thead>
			<tr>
				<th>Teacher</th>
				<th>Courses short</th>
				<th>Periods parked</th>
				<th>Classes / courses</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach (($teachers ?? []) as $teacher): ?>
			<tr>
				<td><?= esc($teacher['teacher'] ?? ''); ?></td>
				<td><?= (int) ($teacher['courses'] ?? 0); ?></td>
				<td class="warn"><?= (int) ($teacher['missed'] ?? 0); ?></td>
				<td>
					<?php foreach (($teacher['items'] ?? []) as $item): ?>
						<div><?= esc(($item['class'] ?? '') . ' — ' . ($item['course'] ?? '') . ' (' . (int) ($item['missed'] ?? 0) . ')'); ?></div>
					<?php endforeach; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
</body>
</html>
