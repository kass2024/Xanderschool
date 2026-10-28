<?php
$school = trim((string) ($school_name ?? 'School'));
$period = trim((string) ($period_label ?? ''));
$classLabel = trim((string) ($class_label ?? 'All classes'));
$yearTitle = trim((string) ($academic_year_title ?? ''));
$termNo = trim((string) ($term ?? ''));
$termLine = $yearTitle;
if ($termNo !== '') {
	$termLine = trim($termLine . '  ·  Term ' . $termNo);
}
$lateCount = count($rows ?? []);
$earlyCount = count($early_rows ?? []);
?>
<style>
	body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #0f172a; }
	.school { text-align: center; font-size: 14pt; font-weight: bold; letter-spacing: 0.4pt; margin: 0; color: #0f766e; }
	.title { text-align: center; font-size: 18pt; font-weight: bold; margin: 2px 0 0; }
	.meta { text-align: center; color: #475569; font-size: 9pt; margin: 2px 0 10px; }
	table.kpi { width: 100%; border-collapse: collapse; margin: 0 0 12px; }
	table.kpi td { width: 20%; border: 0.4pt solid #dbe3ee; background: #f8fafc; padding: 6px 8px; }
	table.kpi .lbl { display: block; font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.4pt; }
	table.kpi .num { display: block; font-size: 14pt; font-weight: bold; margin-top: 1px; }
	table.kpi .early { color: #166534; }
	table.kpi .marks { color: #c2410c; }
	h2 { font-size: 12pt; margin: 0 0 4px; }
	h2.early { color: #166534; }
	.note { color: #64748b; font-size: 8pt; margin: 0 0 6px; }
	table.list { width: 100%; border-collapse: collapse; }
	table.list th { background: #0f766e; color: #ffffff; font-size: 8pt; font-weight: bold; text-align: left; padding: 5px 4px; }
	table.list.early th { background: #166534; }
	table.list td { border-bottom: 0.3pt solid #e2e8f0; padding: 4px; font-size: 8.5pt; vertical-align: middle; }
	table.list tr.alt td { background: #f8fafc; }
	.name { font-weight: bold; }
	.reg { color: #64748b; font-size: 7.5pt; }
	.bad { color: #c2410c; font-weight: bold; }
	.good { color: #166534; font-weight: bold; }
	.empty { padding: 10px 4px; color: #64748b; }
</style>
<div class="school"><?= esc($school); ?></div>
<div class="title">Lateness</div>
<div class="meta"><?= esc($period); ?><?= $termLine !== '' ? '  ·  ' . esc($termLine) : ''; ?>  ·  <?= esc($classLabel); ?></div>
<table class="kpi">
	<tr>
		<td><span class="lbl">Period</span><span class="num" style="font-size:11pt;"><?= esc($period); ?></span></td>
		<td><span class="lbl">Late students</span><span class="num"><?= (int) ($late_students ?? 0); ?></span></td>
		<td><span class="lbl">Late arrivals</span><span class="num"><?= (int) ($late_arrivals ?? 0); ?></span></td>
		<td><span class="lbl">Came early</span><span class="num early"><?= (int) ($early_arrivals ?? 0); ?></span></td>
		<td><span class="lbl">Marks deducted</span><span class="num marks"><?= (int) ($late_marks ?? 0); ?></span></td>
	</tr>
</table>

<h2>Late arrivals</h2>
<p class="note">A tap at or after the lateness time, or no tap once that time has passed. Each one deducts 5 discipline marks. Parents are not notified.</p>
<?php if ($lateCount === 0) : ?>
	<p class="empty">No lateness in this period.</p>
<?php else : ?>
	<table class="list">
		<thead>
		<tr>
			<th style="width:28px;">#</th>
			<th style="width:78px;">Date</th>
			<th>Student</th>
			<th style="width:90px;">Class</th>
			<th style="width:100px;">Location</th>
			<th style="width:62px;">Arrived</th>
			<th style="width:62px;">Late from</th>
			<th style="width:52px;">Marks</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($rows as $i => $row) :
			$when = (string) ($row['late_day'] ?? '');
			$whenLabel = $when !== '' ? date('D j M', strtotime($when)) : '';
			$name = trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''));
			?>
			<tr class="<?= $i % 2 === 1 ? 'alt' : ''; ?>">
				<td><?= $i + 1; ?></td>
				<td><?= esc($whenLabel); ?></td>
				<td><span class="name"><?= esc($name); ?></span><br><span class="reg"><?= esc((string) ($row['regno'] ?? '')); ?></span></td>
				<td><?= esc(trim((string) ($row['class_name'] ?? ''))); ?></td>
				<td><?= esc((string) ($row['place'] ?? '')); ?></td>
				<td><?= esc((string) ($row['arrived'] ?? '')); ?></td>
				<td><?= esc((string) ($row['cutoff'] ?? '')); ?></td>
				<td class="bad">-<?= (int) ($row['marks'] ?? 0); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<pagebreak />

<h2 class="early">Came early</h2>
<p class="note">Students who tapped before the lateness time. The clock time is the first tap at that location. No marks are deducted.</p>
<?php if ($earlyCount === 0) : ?>
	<p class="empty">No student tapped before the lateness time in this period.</p>
<?php else : ?>
	<table class="list early">
		<thead>
		<tr>
			<th style="width:28px;">#</th>
			<th style="width:78px;">Date</th>
			<th>Student</th>
			<th style="width:90px;">Class</th>
			<th style="width:110px;">Location</th>
			<th style="width:70px;">Tapped</th>
			<th style="width:70px;">Before</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($early_rows as $i => $row) :
			$when = (string) ($row['late_day'] ?? '');
			$whenLabel = $when !== '' ? date('D j M', strtotime($when)) : '';
			$name = trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''));
			?>
			<tr class="<?= $i % 2 === 1 ? 'alt' : ''; ?>">
				<td><?= $i + 1; ?></td>
				<td><?= esc($whenLabel); ?></td>
				<td><span class="name"><?= esc($name); ?></span><br><span class="reg"><?= esc((string) ($row['regno'] ?? '')); ?></span></td>
				<td><?= esc(trim((string) ($row['class_name'] ?? ''))); ?></td>
				<td><?= esc((string) ($row['place'] ?? '')); ?></td>
				<td class="good"><?= esc((string) ($row['arrived'] ?? '')); ?></td>
				<td><?= esc((string) ($row['cutoff'] ?? '')); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
