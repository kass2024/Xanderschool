<?php
/** @var array $sheet */
$columns = $sheet['columns'] ?? [];
$students = $sheet['students'] ?? [];
$maxTotal = (int) ($sheet['max_total'] ?? 0);
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title><?= esc($sheet['title'] ?? 'Mark sheet'); ?></title>
	<style>
		body { font-family: "Segoe UI", Arial, sans-serif; color:#1e293b; margin:18px; }
		.ms-toolbar { margin-bottom:12px; }
		.ms-title { text-align:center; color:#0f2744; font-size:22px; font-weight:800; letter-spacing:.02em; margin:0; }
		.ms-sub { text-align:center; color:#64748b; font-size:12px; margin:4px 0 12px; }
		.ms-meta { width:100%; border-collapse:collapse; margin-bottom:14px; font-size:13px; }
		.ms-meta td { border:1px solid #cbd5e1; padding:7px 8px; }
		.ms-meta b { color:#334155; }
		h3 { font-size:14px; margin:14px 0 6px; color:#0f2744; }
		table.ms { width:100%; border-collapse:collapse; font-size:11px; }
		table.ms th { background:#12315c; color:#fff; border:1px solid #0f2744; padding:6px 4px; text-align:center; vertical-align:bottom; }
		table.ms td { border:1px solid #cbd5e1; padding:5px 4px; text-align:center; }
		table.ms td.left { text-align:left; }
		table.ms td.name { text-align:left; font-weight:600; }
		table.ms td.reg { text-align:left; color:#64748b; font-size:10px; }
		table.ms td.total { background:#e8f6ee; font-weight:700; }
		table.ms td.remark { background:#fff8e1; font-weight:700; }
		.r-excellent { color:#15803d; }
		.r-very { color:#166534; }
		.r-good { color:#a16207; }
		.r-fair { color:#b45309; }
		.r-low { color:#b91c1c; }
		.ms-sign { margin-top:28px; display:flex; justify-content:space-between; gap:24px; font-size:13px; }
		.ms-sign div { flex:1; }
		.ms-line { display:inline-block; min-width:220px; border-bottom:1px solid #334155; }
		@media print {
			.ms-toolbar { display:none; }
			body { margin:8px; }
			table.ms th { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
			table.ms td.total, table.ms td.remark { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
		}
	</style>
</head>
<body>
	<div class="ms-toolbar">
		<button onclick="window.print()">Print / PDF</button>
	</div>
	<h1 class="ms-title">CONTINUOUS ASSESSMENT (CAT) - CLASS MARK SHEET</h1>
	<p class="ms-sub">Student list, then every quiz, homework and test saved for this term</p>
	<table class="ms-meta">
		<tr>
			<td><b>School:</b> <?= esc($sheet['school'] ?? ''); ?></td>
			<td><b>Subject:</b> <?= esc($sheet['subject'] ?? ''); ?></td>
			<td><b>Class:</b> <?= esc($sheet['class_label'] ?? ''); ?></td>
			<td><b>Term:</b> <?= esc($sheet['term_label'] ?? ''); ?></td>
			<td><b>Academic Year:</b> <?= esc($sheet['year_title'] ?? ''); ?></td>
			<td><b>Teacher:</b> <?= esc($sheet['teacher'] ?? ''); ?></td>
		</tr>
	</table>

	<h3>A. Student CAT Record</h3>
	<table class="ms">
		<thead>
			<tr>
				<th>No.</th>
				<th>Student Name / ID</th>
				<?php foreach ($columns as $col): ?>
					<th><?= esc($col['label']); ?><br><?= esc($col['date_short']); ?><br>/<?= (int) $col['max']; ?></th>
				<?php endforeach; ?>
				<th>Total<?= $maxTotal > 0 ? '<br>/' . $maxTotal : ''; ?></th>
				<th>CAT %</th>
				<th>Remark</th>
			</tr>
		</thead>
		<tbody>
			<?php if ($students === []): ?>
				<tr><td class="left" colspan="<?= 5 + count($columns); ?>">No active students in this class for the selected year.</td></tr>
			<?php endif; ?>
			<?php foreach ($students as $i => $student): ?>
				<tr>
					<td><?= $i + 1; ?></td>
					<td class="name"><?= esc($student['name']); ?><div class="reg"><?= esc($student['regno']); ?></div></td>
					<?php foreach ($columns as $col): ?>
						<td><?= esc($student['cells'][$col['key']] ?? ''); ?></td>
					<?php endforeach; ?>
					<td class="total"><?= $columns === [] ? '' : esc($student['total']); ?></td>
					<td class="total"><?= $columns === [] ? '' : esc($student['percent']); ?></td>
					<td class="remark <?= esc($student['remark_class']); ?>"><?= esc($student['remark']); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ($columns === []): ?>
		<p>No quiz, homework, or test has been saved for this course in the selected term.</p>
	<?php endif; ?>

	<h3>B. Assessment Register</h3>
	<table class="ms">
		<thead>
			<tr>
				<th>Assessment</th>
				<th>Type</th>
				<th>Date Given</th>
				<th>Due / Test Date</th>
				<th>Max Mark</th>
				<th>Topic / Coverage</th>
			</tr>
		</thead>
		<tbody>
			<?php if ($columns === []): ?>
				<tr><td class="left" colspan="6">No assessments recorded.</td></tr>
			<?php endif; ?>
			<?php foreach ($columns as $col): ?>
				<tr>
					<td class="left"><?= esc($col['label']); ?></td>
					<td><?= esc($col['kind']); ?></td>
					<td><?= esc($col['date_long']); ?></td>
					<td><?= esc($col['date_long']); ?></td>
					<td><?= (int) $col['max']; ?></td>
					<td class="left"><?= esc($col['topic'] !== '' ? $col['topic'] : '—'); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p style="font-size:12px;color:#64748b;">CAT % is the total of marks obtained across the assessments above<?= $maxTotal > 0 ? ' / ' . $maxTotal : ''; ?> &times; 100. A blank cell means the student has no saved mark for that assessment.</p>
	<div class="ms-sign">
		<div>Teacher Signature: <span class="ms-line"></span></div>
		<div>Head of Department: <span class="ms-line"></span></div>
		<div>Date: <span class="ms-line"></span></div>
	</div>
</body>
</html>
