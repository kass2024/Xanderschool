<?php
/** @var array $school */
/** @var list<array<string,mixed>> $staffs */
/** @var string $year_title */
/** @var string $term_label */
/** @var string $printed_at */

use App\Libraries\StaffListExporter;

$staffs = StaffListExporter::filterForExport($staffs ?? []);
$faceMissing = 0;
foreach ($staffs as $s) {
	if (empty($s['face_enrolled'])) {
		$faceMissing++;
	}
}
$schoolName = trim((string) ($school['name'] ?? 'School'));
$slogan = trim((string) ($school['slogan'] ?? ''));
$logo = trim((string) ($school['logo'] ?? ''));
$contact = array_filter([
	trim((string) ($school['address'] ?? '')) ?: null,
	!empty($school['pobox']) ? 'P.O. Box ' . $school['pobox'] : null,
	!empty($school['phone']) ? 'Tel: ' . $school['phone'] : null,
	!empty($school['email']) ? 'Email: ' . $school['email'] : null,
	!empty($school['website']) ? $school['website'] : null,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Staff List — <?= esc($schoolName) ?></title>
	<style>
		@page { size: A4 landscape; margin: 10mm; }
		body { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #111; margin: 0; }
		.hdr { border: 1.5px solid #012F6B; padding: 10px 12px; margin-bottom: 10px; overflow: hidden; }
		.hdr-logo { float: left; width: 90px; }
		.hdr-logo img { max-width: 80px; max-height: 70px; }
		.hdr-text { float: left; width: calc(100% - 100px); padding-left: 8px; }
		.hdr-text .name { font-size: 16pt; font-weight: bold; color: #012F6B; margin: 0 0 2px; text-transform: uppercase; }
		.hdr-text .slogan { font-size: 9.5pt; font-style: italic; color: #475569; margin: 0 0 4px; }
		.hdr-text .contact { font-size: 8.5pt; color: #64748B; }
		.title { text-align: center; font-size: 13pt; font-weight: bold; color: #012F6B; margin: 6px 0 2px; }
		.subtitle { text-align: center; font-size: 9pt; color: #475569; margin-bottom: 10px; }
		.kpi { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
		.kpi td { border: 1px solid #cbd5e1; text-align: center; padding: 6px 4px; background: #E8F0FA; font-size: 8.5pt; }
		.kpi .val { display: block; font-size: 13pt; font-weight: bold; color: #012F6B; }
		table.data { width: 100%; border-collapse: collapse; }
		table.data th, table.data td { border: 1px solid #94a3b8; padding: 4px 5px; vertical-align: middle; font-size: 8.5pt; }
		table.data th { background: #012F6B; color: #fff; font-weight: bold; text-align: center; }
		table.data tr:nth-child(even) td { background: #F7FAFD; }
		.face-ok { color: #15803d; font-weight: bold; }
		.face-no { color: #b45309; font-weight: bold; }
		.status-ok { color: #15803d; font-weight: bold; }
		.status-locked { color: #b91c1c; font-weight: bold; }
		.foot { margin-top: 12px; font-size: 8pt; color: #64748B; text-align: center; }
	</style>
</head>
<body>
<div class="hdr">
	<?php if ($logo !== '') : ?>
		<div class="hdr-logo">
			<img src="<?= esc(base_url('assets/images/logo/' . $logo), 'attr') ?>" alt="Logo">
		</div>
	<?php endif; ?>
	<div class="hdr-text">
		<div class="name"><?= esc($schoolName) ?></div>
		<?php if ($slogan !== '') : ?>
			<div class="slogan"><?= esc($slogan) ?></div>
		<?php endif; ?>
		<?php if ($contact) : ?>
			<div class="contact"><?= esc(implode('  •  ', $contact)) ?></div>
		<?php endif; ?>
	</div>
</div>

<div class="title">STAFF LIST</div>
<div class="subtitle">
	<?= esc($year_title ?? '') ?>
	<?php if (!empty($term_label)) : ?> · <?= esc($term_label) ?><?php endif; ?>
	· Printed <?= esc($printed_at ?? date('d M Y H:i')) ?>
</div>

<table class="kpi">
	<tr>
		<td><span class="val"><?= count($staffs) ?></span>Total staff</td>
		<td><span class="val"><?= (int) (count($staffs) - $faceMissing) ?></span>Face enrolled</td>
		<td><span class="val"><?= (int) $faceMissing ?></span>No face</td>
	</tr>
</table>

<table class="data">
	<thead>
	<tr>
		<th style="width:28px">#</th>
		<th>Names</th>
		<th>Phone</th>
		<th>Email</th>
		<th>RFID card</th>
		<th>Face</th>
		<th>Shift</th>
		<th>Last login</th>
		<th>Created time</th>
		<th>Status</th>
	</tr>
	</thead>
	<tbody>
	<?php if (!$staffs) : ?>
		<tr><td colspan="10" style="text-align:center;color:#64748b;">No staff to export.</td></tr>
	<?php else :
		$n = 0;
		foreach ($staffs as $staff) :
			$n++;
			$vals = StaffListExporter::rowValues($staff, $n);
			$faceClass = ($vals[5] === 'ENROLLED') ? 'face-ok' : 'face-no';
			$statusClass = ($vals[9] === 'Active') ? 'status-ok' : 'status-locked';
			?>
			<tr>
				<td style="text-align:center"><?= (int) $vals[0] ?></td>
				<td><?= esc($vals[1]) ?></td>
				<td><?= esc($vals[2]) ?></td>
				<td><?= esc($vals[3]) ?></td>
				<td><?= esc($vals[4]) ?></td>
				<td class="<?= $faceClass ?>" style="text-align:center"><?= esc($vals[5]) ?></td>
				<td><?= esc($vals[6]) ?></td>
				<td><?= esc($vals[7]) ?></td>
				<td><?= esc($vals[8]) ?></td>
				<td class="<?= $statusClass ?>" style="text-align:center"><?= esc($vals[9]) ?></td>
			</tr>
		<?php endforeach;
	endif; ?>
	</tbody>
</table>

<div class="foot">Generated by <?= esc($schoolName) ?> · Staff list export (post / Methode staff excluded)</div>
</body>
</html>
