<?php
/** @var array $school */
/** @var list<array<string,mixed>> $staffs */
/** @var string $year_title */
/** @var string $term_label */
/** @var string $printed_at */

$staffs = $staffs ?? [];
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
$totalCourses = 0;
$totalPeriods = 0;
foreach ($staffs as $s) {
	$totalCourses += (int) ($s['courses_count'] ?? 0);
	$totalPeriods += (int) ($s['periods'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Staff courses — <?= esc($schoolName) ?></title>
	<style>
		@page { size: A4 portrait; margin: 10mm; }
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
		.kpi { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
		.kpi td { border: 1px solid #cbd5e1; text-align: center; padding: 6px 4px; background: #E8F0FA; font-size: 8.5pt; }
		.kpi .val { display: block; font-size: 13pt; font-weight: bold; color: #012F6B; }
		.section { font-size: 10.5pt; font-weight: bold; color: #012F6B; margin: 12px 0 6px; border-bottom: 1px solid #012F6B; padding-bottom: 3px; }
		table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
		table.data th, table.data td { border: 1px solid #94a3b8; padding: 4px 5px; vertical-align: middle; font-size: 8.5pt; }
		table.data th { background: #012F6B; color: #fff; font-weight: bold; text-align: center; }
		table.data tr:nth-child(even) td { background: #F7FAFD; }
		.num { text-align: center; }
		.periods { text-align: center; font-weight: bold; color: #c2410c; }
		.staff-card { border: 1px solid #94a3b8; margin: 0 0 10px; page-break-inside: avoid; }
		.staff-head { background: #012F6B; color: #fff; padding: 6px 8px; overflow: hidden; }
		.staff-head .who { font-weight: bold; font-size: 10.5pt; }
		.staff-head .meta { font-size: 8pt; color: #dbeafe; margin-top: 2px; }
		.staff-head .load { float: right; text-align: right; }
		.staff-head .load span { display: inline-block; background: #fff; color: #012F6B; border-radius: 10px; padding: 2px 8px; margin-left: 4px; font-size: 8pt; font-weight: bold; }
		.staff-head .load .per { color: #c2410c; }
		.staff-card table { margin: 0; }
		.note { color: #0369a1; font-size: 8pt; }
		.foot { margin-top: 12px; font-size: 8pt; color: #64748B; text-align: center; }
		.empty { text-align: center; color: #64748b; padding: 16px; }
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

<div class="title">STAFF COURSES AND WEEKLY PERIODS</div>
<div class="subtitle">
	<?= esc($year_title ?? '') ?>
	<?php if (!empty($term_label)) : ?> · <?= esc($term_label) ?><?php endif; ?>
	· Printed <?= esc($printed_at ?? date('d M Y H:i')) ?>
</div>

<table class="kpi">
	<tr>
		<td><span class="val"><?= count($staffs) ?></span>Teachers with courses</td>
		<td><span class="val"><?= (int) $totalCourses ?></span>Course assignments</td>
		<td><span class="val"><?= (int) $totalPeriods ?></span>Weekly periods</td>
	</tr>
</table>

<div class="section">1. Overview — all assigned staff</div>
<table class="data">
	<thead>
	<tr>
		<th style="width:28px">#</th>
		<th>Staff name</th>
		<th>Post</th>
		<th>Courses</th>
		<th>Periods / week</th>
	</tr>
	</thead>
	<tbody>
	<?php if (!$staffs) : ?>
		<tr><td colspan="5" class="empty">No staff have assigned courses for this year and term.</td></tr>
	<?php else :
		$n = 0;
		foreach ($staffs as $staff) :
			$n++;
			?>
			<tr>
				<td class="num"><?= $n ?></td>
				<td><?= esc((string) ($staff['name'] ?? '')) ?></td>
				<td><?= esc((string) ($staff['post_title'] ?? '') ?: '—') ?></td>
				<td class="num"><?= (int) ($staff['courses_count'] ?? 0) ?></td>
				<td class="periods"><?= (int) ($staff['periods'] ?? 0) ?></td>
			</tr>
		<?php endforeach;
	endif; ?>
	</tbody>
</table>

<div class="section">2. Detailed courses and weekly periods per staff</div>
<?php if (!$staffs) : ?>
	<div class="empty">No detailed course list to print.</div>
<?php else :
	$n = 0;
	foreach ($staffs as $staff) :
		$n++;
		$metaBits = array_filter([
			trim((string) ($staff['post_title'] ?? '')) ?: null,
			trim((string) ($staff['phone'] ?? '')) ?: null,
		]);
		$courses = $staff['courses'] ?? [];
		?>
		<div class="staff-card">
			<div class="staff-head">
				<div class="load">
					<span><?= (int) ($staff['courses_count'] ?? 0) ?> course<?= ((int) ($staff['courses_count'] ?? 0)) === 1 ? '' : 's' ?></span>
					<span class="per"><?= (int) ($staff['periods'] ?? 0) ?> periods / week</span>
				</div>
				<div class="who"><?= $n ?>. <?= esc((string) ($staff['name'] ?? '')) ?></div>
				<?php if ($metaBits) : ?>
					<div class="meta"><?= esc(implode('  •  ', $metaBits)) ?></div>
				<?php endif; ?>
			</div>
			<table class="data">
				<thead>
				<tr>
					<th style="width:28px">#</th>
					<th>Class</th>
					<th>Course</th>
					<th style="width:90px">Periods / week</th>
					<th style="width:110px">Note</th>
				</tr>
				</thead>
				<tbody>
				<?php
				$c = 0;
				foreach ($courses as $course) :
					$c++;
					?>
					<tr>
						<td class="num"><?= $c ?></td>
						<td><?= esc((string) ($course['class'] ?? '')) ?></td>
						<td><?= esc((string) ($course['title'] ?? '')) ?></td>
						<td class="periods"><?= (int) ($course['periods'] ?? 0) ?></td>
						<td class="note"><?= esc((string) ($course['note'] ?? '') ?: '—') ?></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td colspan="3" style="text-align:right;font-weight:bold;">Total weekly periods</td>
					<td class="periods"><?= (int) ($staff['periods'] ?? 0) ?></td>
					<td></td>
				</tr>
				</tbody>
			</table>
		</div>
	<?php endforeach;
endif; ?>

<div class="foot">Generated by <?= esc($schoolName) ?> · Combined lessons count once in weekly periods</div>
</body>
</html>
