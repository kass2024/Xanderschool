<?php
/** @var array $school */
/** @var list<array<string,mixed>> $students */
/** @var string $year_title */
/** @var string $term_label */
/** @var string $filter_label */
/** @var string $printed_at */

$students = $students ?? [];
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
	<title>Students without card UID — <?= esc($schoolName) ?></title>
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
		table.data { width: 100%; border-collapse: collapse; }
		table.data th, table.data td { border: 1px solid #94a3b8; padding: 6px 7px; vertical-align: middle; font-size: 9.5pt; }
		table.data th { background: #012F6B; color: #fff; font-weight: bold; text-align: center; }
		table.data tr:nth-child(even) td { background: #F7FAFD; }
		.num { text-align: center; width: 28px; }
		.sign { height: 28px; }
		.muted { color: #94a3b8; font-style: italic; text-align: center; }
		.foot { margin-top: 14px; font-size: 8.5pt; color: #334155; }
		.kpi { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
		.kpi td { border: 1px solid #cbd5e1; text-align: center; padding: 6px 4px; background: #E8F0FA; font-size: 8.5pt; }
		.kpi .val { display: block; font-size: 13pt; font-weight: bold; color: #012F6B; }
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

<div class="title">STUDENTS WITHOUT CARD UID</div>
<div class="subtitle">
	<?= esc($year_title ?? '') ?>
	<?php if (!empty($term_label)) : ?> · <?= esc($term_label) ?><?php endif; ?>
	<?php if (!empty($filter_label)) : ?> · <?= esc($filter_label) ?><?php endif; ?>
	· Printed <?= esc($printed_at ?? date('d M Y H:i')) ?>
</div>

<table class="kpi">
	<tr>
		<td><span class="val"><?= count($students) ?></span>Students missing a card UID</td>
	</tr>
</table>

<table class="data">
	<thead>
	<tr>
		<th class="num">#</th>
		<th>Registration No</th>
		<th>Student's Name</th>
		<th>Class</th>
		<th>Studying mode</th>
		<th>Card UID</th>
		<th>Date received</th>
		<th>Student signature</th>
	</tr>
	</thead>
	<tbody>
	<?php if ($students === []) : ?>
		<tr>
			<td colspan="8" class="muted">Every student in this selection already has a card UID.</td>
		</tr>
	<?php else : ?>
		<?php $n = 1; foreach ($students as $student) : ?>
			<tr>
				<td class="num"><?= $n ?></td>
				<td><?= esc($student['regno'] ?? '') ?></td>
				<td><?= esc($student['name'] ?? '') ?></td>
				<td><?= esc($student['class'] ?? '') ?></td>
				<td><?= esc($student['mode_label'] ?? '') ?></td>
				<td>—</td>
				<td class="sign"></td>
				<td class="sign"></td>
			</tr>
			<?php $n++; endforeach; ?>
	<?php endif; ?>
	</tbody>
</table>

<div class="foot">
	Distributed by: ______________________________
	&nbsp;&nbsp;&nbsp; Signature: ____________________
	&nbsp;&nbsp;&nbsp; Date: ____________________
</div>
</body>
</html>
