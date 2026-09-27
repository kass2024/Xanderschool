<?php
/** @var array $sheet */
$columns = $sheet['columns'] ?? [];
$students = $sheet['students'] ?? [];
$maxTotal = (int) ($sheet['max_total'] ?? 0);
$lh = $sheet['letterhead'] ?? [];
$colCount = count($columns);
$density = $colCount >= 16 ? 'dense' : ($colCount >= 9 ? 'tight' : 'comfortable');
$schoolName = trim((string) ($lh['name'] ?? $sheet['school'] ?? ''));
$contacts = [];
if (!empty($lh['address'])) {
	$contacts[] = (string) $lh['address'];
}
if (!empty($lh['pobox'])) {
	$contacts[] = 'P.O. Box ' . $lh['pobox'];
}
if (!empty($lh['phone'])) {
	$contacts[] = 'Tel: ' . $lh['phone'];
}
if (!empty($lh['email'])) {
	$contacts[] = (string) $lh['email'];
}
if (!empty($lh['website'])) {
	$contacts[] = (string) $lh['website'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= esc($sheet['title'] ?? 'Marks sheet'); ?></title>
	<style>
		:root {
			--navy: #0f2744;
			--navy-2: #16365f;
			--gold: #c4a35a;
			--ink: #1e293b;
			--muted: #64748b;
			--line: #d6deea;
			--paper: #ffffff;
			--page: #eef2f7;
		}
		* { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; }
		body {
			font-family: "Segoe UI", Arial, sans-serif;
			color: var(--ink);
			background: var(--page);
		}
		.ms-toolbar {
			position: sticky;
			top: 0;
			z-index: 20;
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: space-between;
			gap: 10px;
			padding: 10px 16px;
			background: var(--navy);
			color: #fff;
		}
		.ms-toolbar p { margin: 0; font-size: 13px; color: #d6e2f2; }
		.ms-toolbar button {
			border: 0;
			background: #fff;
			color: var(--navy);
			font-weight: 700;
			border-radius: 8px;
			padding: 8px 14px;
			cursor: pointer;
		}
		.ms-sheet {
			width: min(1400px, calc(100% - 24px));
			margin: 16px auto 28px;
			background: var(--paper);
			border: 1px solid var(--line);
			border-radius: 14px;
			box-shadow: 0 10px 30px rgba(15, 39, 68, .08);
			padding: 18px 18px 22px;
		}
		.ms-brand {
			display: flex;
			gap: 14px;
			align-items: center;
			padding-bottom: 10px;
			border-bottom: 3px solid var(--navy);
			position: relative;
		}
		.ms-brand:after {
			content: "";
			position: absolute;
			left: 0; right: 0; bottom: -7px;
			border-bottom: 1px solid var(--gold);
		}
		.ms-logo {
			width: 72px;
			height: 72px;
			object-fit: contain;
			flex: 0 0 auto;
		}
		.ms-brand-copy { min-width: 0; flex: 1; }
		.ms-school {
			margin: 0;
			color: var(--navy);
			font-size: 22px;
			line-height: 1.15;
			font-weight: 800;
			letter-spacing: .04em;
			text-transform: uppercase;
		}
		.ms-slogan { margin: 2px 0 0; color: var(--gold); font-size: 12px; font-weight: 700; letter-spacing: .04em; }
		.ms-contacts { margin: 4px 0 0; color: var(--muted); font-size: 12px; line-height: 1.45; }
		.ms-dochead { text-align: center; margin: 16px 0 12px; }
		.ms-kicker {
			display: inline-block;
			margin: 0 0 4px;
			padding: 3px 10px;
			border-radius: 999px;
			background: #e8eef8;
			color: var(--navy-2);
			font-size: 11px;
			font-weight: 800;
			letter-spacing: .12em;
		}
		.ms-title { margin: 0; color: var(--navy); font-size: 26px; font-weight: 800; letter-spacing: .06em; }
		.ms-sub { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
		.ms-facts {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
			gap: 8px;
			margin: 0 0 14px;
		}
		.ms-fact {
			border: 1px solid var(--line);
			border-radius: 10px;
			padding: 8px 10px;
			background: #f8fafc;
			min-width: 0;
		}
		.ms-fact span { display: block; color: var(--muted); font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
		.ms-fact strong { display: block; margin-top: 2px; color: var(--navy); font-size: 13px; word-break: break-word; }
		.ms-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid var(--line); border-radius: 10px; }
		table.ms { width: 100%; border-collapse: collapse; font-size: 12px; }
		table.ms th {
			background: var(--navy);
			color: #fff;
			border: 1px solid #0c2038;
			padding: 7px 5px;
			text-align: center;
			vertical-align: bottom;
			font-weight: 700;
		}
		table.ms td { border: 1px solid var(--line); padding: 6px 5px; text-align: center; background: #fff; }
		table.ms tbody tr:nth-child(even) td { background: #f8fafc; }
		table.ms td.num, table.ms th.num { width: 36px; }
		table.ms td.name, table.ms th.name { text-align: left; min-width: 170px; }
		table.ms td.name { font-weight: 700; color: var(--navy); }
		table.ms .reg { display: block; margin-top: 1px; color: var(--muted); font-size: 10px; font-weight: 600; }
		table.ms tr.ms-sub th { background: #1c4d86; font-weight: 600; font-size: .92em; padding: 3px 2px; }
		table.ms tr.ms-sub th span { display: block; color: #d6e4f5; font-size: .78em; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
		table.ms td.total { background: #e7f6ee !important; font-weight: 800; }
		table.ms td.pct { background: #eef6ff !important; font-weight: 800; }
		table.ms td.remark { font-weight: 800; }
		table.ms th.sticky, table.ms td.sticky { position: sticky; left: 0; z-index: 2; }
		table.ms th.sticky-2, table.ms td.sticky-2 { position: sticky; left: 36px; z-index: 2; }
		table.ms th.sticky, table.ms th.sticky-2 { background: var(--navy); z-index: 3; }
		table.ms td.sticky, table.ms td.sticky-2 { background: #fff; }
		table.ms tbody tr:nth-child(even) td.sticky,
		table.ms tbody tr:nth-child(even) td.sticky-2 { background: #f8fafc; }
		.r-excellent { color: #15803d; }
		.r-very { color: #166534; }
		.r-good { color: #a16207; }
		.r-fair { color: #c2410c; }
		.r-low { color: #b91c1c; }
		.ms-section { margin: 16px 0 8px; color: var(--navy); font-size: 15px; font-weight: 800; }
		.ms-note { margin: 8px 0 0; color: var(--muted); font-size: 12px; line-height: 1.45; }
		.ms-sign {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 18px;
			margin-top: 26px;
			font-size: 13px;
		}
		.ms-sign span { display: block; margin-bottom: 16px; color: var(--navy); font-weight: 700; font-style: normal; }
		.ms-line { display: block; border-bottom: 1px solid #334155; height: 22px; font-weight: 400; }
		.ms-sheet.tight table.ms { font-size: 10px; }
		.ms-sheet.dense table.ms { font-size: 9px; }
		.ms-sheet.tight table.ms td.name, .ms-sheet.dense table.ms td.name { min-width: 140px; }
		@media (max-width: 720px) {
			.ms-sheet { width: calc(100% - 12px); padding: 12px; border-radius: 10px; }
			.ms-school { font-size: 16px; }
			.ms-logo { width: 52px; height: 52px; }
			.ms-title { font-size: 20px; }
			.ms-sign { grid-template-columns: 1fr; gap: 12px; }
			table.ms th.sticky, table.ms td.sticky,
			table.ms th.sticky-2, table.ms td.sticky-2 { position: static; }
		}
		@page { size: A4 landscape; margin: 8mm; }
		@media print {
			body { background: #fff; }
			.ms-toolbar { display: none !important; }
			.ms-sheet {
				width: auto;
				margin: 0;
				border: 0;
				border-radius: 0;
				box-shadow: none;
				padding: 0;
			}
			html, body, .ms-sheet, .ms-scroll { width: 100%; max-width: 100%; }
			.ms-facts { display: flex; flex-wrap: wrap; gap: 0; }
			.ms-fact {
				flex: 1 1 18%;
				border-radius: 0;
				background: #fff;
				padding: 3px 5px;
				min-width: 0;
			}
			.ms-fact strong { font-size: 11px; }
			.ms-scroll { overflow: hidden; border: 0; border-radius: 0; }
			table.ms th.sticky, table.ms td.sticky,
			table.ms th.sticky-2, table.ms td.sticky-2 { position: static; }
			table.ms { width: 100%; max-width: 100%; table-layout: fixed; font-size: 8.5px; }
			.ms-sheet.tight table.ms { font-size: 7.5px; }
			.ms-sheet.dense table.ms { font-size: 6.5px; }
			table.ms th, table.ms td {
				padding: 2px 1px;
				min-width: 0 !important;
				white-space: normal;
				overflow: hidden;
				word-wrap: break-word;
			}
			table.ms td.name, table.ms th.name { width: 18%; }
			table.ms th, table.ms td.total, table.ms td.pct, table.ms td.remark {
				-webkit-print-color-adjust: exact;
				print-color-adjust: exact;
			}
			table.ms thead { display: table-header-group; }
			table.ms tr, .ms-brand, .ms-sign { break-inside: avoid; }
			.ms-logo { width: 58px; height: 58px; }
			.ms-school { font-size: 18px; }
			.ms-title { font-size: 18px; }
			.ms-sign { margin-top: 16px; }
			.ms-sign span { margin-bottom: 10px; }
		}
	</style>
</head>
<body>
	<div class="ms-toolbar">
		<p>Landscape PDF. Extra quizzes stay in the table and scroll on screen.</p>
		<button type="button" onclick="window.print()">Print / Save PDF</button>
	</div>
	<article class="ms-sheet <?= esc($density); ?>">
		<header class="ms-brand">
			<?php if (!empty($lh['logo'])): ?>
				<img class="ms-logo" src="<?= esc($lh['logo']); ?>" alt="">
			<?php endif; ?>
			<div class="ms-brand-copy">
				<h1 class="ms-school"><?= esc($schoolName !== '' ? $schoolName : 'School'); ?></h1>
				<?php if (!empty($lh['slogan'])): ?>
					<p class="ms-slogan"><?= esc($lh['slogan']); ?></p>
				<?php endif; ?>
				<?php if ($contacts !== []): ?>
					<p class="ms-contacts"><?= esc(implode('  ·  ', $contacts)); ?></p>
				<?php endif; ?>
			</div>
		</header>

		<div class="ms-dochead">
			<p class="ms-kicker">Continuous assessment</p>
			<h2 class="ms-title">MARKS SHEET</h2>
			<p class="ms-sub">Marks already saved for this class and course<?= !empty($sheet['generated_at']) ? ' · ' . esc($sheet['generated_at']) : ''; ?></p>
		</div>

		<div class="ms-facts">
			<div class="ms-fact"><span>Subject</span><strong><?= esc($sheet['subject'] ?? ''); ?></strong></div>
			<div class="ms-fact"><span>Class</span><strong><?= esc($sheet['class_label'] ?? ''); ?></strong></div>
			<div class="ms-fact"><span>Term</span><strong><?= esc($sheet['term_label'] ?? ''); ?></strong></div>
			<div class="ms-fact"><span>Academic year</span><strong><?= esc($sheet['year_title'] ?? ''); ?></strong></div>
			<div class="ms-fact"><span>Teacher</span><strong><?= esc($sheet['teacher'] ?? ''); ?></strong></div>
		</div>

		<?php
		$hasTopic = false;
		foreach ($columns as $col) {
			if (trim((string) ($col['topic'] ?? '')) !== '') {
				$hasTopic = true;
				break;
			}
		}
		$headRows = $columns === [] ? 1 : (3 + ($hasTopic ? 1 : 0));
		?>
		<div class="ms-scroll">
			<table class="ms">
				<thead>
					<tr>
						<th class="num sticky" rowspan="<?= $headRows; ?>">No.</th>
						<th class="name sticky-2" rowspan="<?= $headRows; ?>">Student</th>
						<?php foreach ($columns as $col): ?>
							<th><?= esc($col['label']); ?></th>
						<?php endforeach; ?>
						<th rowspan="<?= $headRows; ?>">Total<?= $maxTotal > 0 ? '<br>/' . $maxTotal : ''; ?></th>
						<th rowspan="<?= $headRows; ?>">CAT %</th>
						<th rowspan="<?= $headRows; ?>">Remark</th>
					</tr>
					<?php if ($columns !== []): ?>
					<tr class="ms-sub">
						<?php foreach ($columns as $col): ?>
							<th><span>Date</span><?= esc($col['date_long'] !== '' ? $col['date_long'] : '—'); ?></th>
						<?php endforeach; ?>
					</tr>
					<tr class="ms-sub">
						<?php foreach ($columns as $col): ?>
							<th><span>Max</span>/<?= (int) $col['max']; ?></th>
						<?php endforeach; ?>
					</tr>
					<?php if ($hasTopic): ?>
					<tr class="ms-sub">
						<?php foreach ($columns as $col): ?>
							<th><span>Topic</span><?= esc(trim((string) $col['topic']) !== '' ? $col['topic'] : '—'); ?></th>
						<?php endforeach; ?>
					</tr>
					<?php endif; ?>
					<?php endif; ?>
				</thead>
				<tbody>
					<?php if ($students === []): ?>
						<tr><td class="name" colspan="<?= 5 + $colCount; ?>">No active students in this class for the selected year.</td></tr>
					<?php endif; ?>
					<?php foreach ($students as $i => $student): ?>
						<tr>
							<td class="num sticky"><?= $i + 1; ?></td>
							<td class="name sticky-2"><?= esc($student['name']); ?><span class="reg"><?= esc($student['regno']); ?></span></td>
							<?php foreach ($columns as $col): ?>
								<td><?= esc($student['cells'][$col['key']] ?? ''); ?></td>
							<?php endforeach; ?>
							<td class="total"><?= $columns === [] ? '' : esc($student['total']); ?></td>
							<td class="pct"><?= $columns === [] ? '' : esc($student['percent']); ?></td>
							<td class="remark <?= esc($student['remark_class']); ?>"><?= esc($student['remark']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ($columns === []): ?>
			<p class="ms-note">No marks have been saved for this course in the selected term.</p>
		<?php else: ?>
			<p class="ms-note">CAT % is the total of marks obtained across these assessments<?= $maxTotal > 0 ? ' / ' . $maxTotal : ''; ?> &times; 100. A blank cell means the student has no saved mark for that assessment.</p>
		<?php endif; ?>

		<div class="ms-sign">
			<div><span>Teacher signature</span><b class="ms-line"></b></div>
			<div><span>Head of Department</span><b class="ms-line"></b></div>
			<div><span>Date</span><b class="ms-line"></b></div>
		</div>
	</article>
</body>
</html>
