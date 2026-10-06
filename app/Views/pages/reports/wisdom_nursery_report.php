<style>
	.nr-sheet, .nr-sheet * { box-sizing: border-box; }
	.nr-sheet {
		font-family: "Century Gothic", CenturyGothic, "Tw Cen MT", sans-serif;
		font-weight: 700;
		color: #231f20;
		background: #fff;
	}
	.nr-page {
		width: 746pt;
		height: 511pt;
		margin: 0 auto 16px;
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		page-break-after: always;
		break-after: page;
	}
	.nr-slip {
		width: 355pt;
		height: 510pt;
		flex: 0 0 355pt;
		border: 1.9pt solid #231f20;
		padding: 5pt 10pt 6pt 5pt;
		background: #fff;
		overflow: hidden;
		display: flex;
		flex-direction: column;
	}
	.nr-slip.periodic .nr-top { min-height: 0; margin-bottom: 1pt; }
	.nr-slip.periodic .nr-table td { height: 18.4pt; }
	.nr-slip.periodic .nr-row { height: 17.6pt; }
	.nr-period { letter-spacing: .3pt; }
	.nr-top {
		display: flex;
		align-items: flex-start;
		min-height: 62pt;
		margin-bottom: 4pt;
	}
	.nr-logo-wrap {
		width: 58pt;
		flex: 0 0 58pt;
		padding-top: 4pt;
		text-align: center;
	}
	.nr-logo {
		width: 46pt;
		height: 52pt;
		object-fit: contain;
		display: block;
		margin: 0 auto;
	}
	.nr-meta { flex: 1; min-width: 0; padding-top: 1pt; }
	.nr-h {
		display: flex;
		align-items: flex-end;
		font-size: 10.86pt;
		line-height: 1;
		height: 16.5pt;
		white-space: nowrap;
	}
	.nr-h .k { font-weight: 700; }
	.nr-ul {
		border-bottom: 0.9pt solid #231f20;
		flex: 1;
		min-width: 18pt;
		margin: 0 6pt 1pt 3pt;
		padding: 0 3pt;
		font-size: 10.86pt;
		font-weight: 700;
		line-height: 1;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.nr-ul.short { flex: 0 1 78pt; }
	.nr-ul.mid { flex: 0 1 92pt; }
	.nr-ul.tiny { flex: 0 0 36pt; }
	.nr-table {
		width: 331pt;
		border-collapse: collapse;
		margin: 0 0 8pt 2pt;
		table-layout: fixed;
	}
	.nr-table th, .nr-table td {
		border: 0.9pt solid #231f20 !important;
		padding: 0 3pt !important;
		font-weight: 700 !important;
		vertical-align: middle !important;
		background: #fff !important;
		line-height: 1.05 !important;
	}
	.nr-table th {
		font-family: "Century Gothic", CenturyGothic, "Tw Cen MT", sans-serif !important;
		font-size: 9.73pt !important;
		text-align: center !important;
		text-transform: uppercase;
		height: 26pt;
	}
	.nr-table td {
		font-family: "Century Gothic", CenturyGothic, "Tw Cen MT", sans-serif !important;
		font-size: 10.98pt !important;
		height: 20pt;
		text-transform: none;
		overflow: hidden;
	}
	.nr-table td.sub {
		font-size: 8.6pt !important;
		line-height: 1.05 !important;
		text-transform: uppercase;
		white-space: normal !important;
		word-break: break-word;
	}
	.nr-table td.num {
		font-family: Arial, Helvetica, sans-serif !important;
		font-size: 10.98pt !important;
		font-weight: 700 !important;
		text-align: center !important;
		text-transform: none;
	}
	.nr-table td.ctr { text-align: center !important; }
	.nr-table tr.total td { font-size: 11pt !important; }
	.nr-foot { width: 331pt; margin-left: 2pt; }
	.nr-row {
		display: flex;
		align-items: flex-end;
		height: 19.6pt;
		font-size: 11pt;
		font-weight: 700;
		line-height: 1;
		white-space: nowrap;
	}
	.nr-dots {
		flex: 1 1 auto;
		border-bottom: 1pt dotted #231f20;
		height: 0;
		margin: 0 2pt 2pt;
		min-width: 8pt;
	}
	.nr-dots.tail { flex: 0 0 28pt; }
	.nr-signlab { margin-left: 4pt; }
	.nr-name {
		max-width: 120pt;
		overflow: hidden;
		text-overflow: ellipsis;
		padding: 0 2pt;
		font-weight: 700;
	}
	@media screen {
		.nr-sheet { overflow-x: auto; padding-bottom: 8px; }
	}
	@media print {
		@page { size: A4 landscape; margin: 34pt 39pt 50pt 57pt; }
		.app-sidebar-wrapper,
		.app-sidebar,
		.app-sidebar-overlay,
		.header-mobile-wrapper,
		.app-header,
		.app-footer,
		.app-page-title,
		.ui-theme-settings,
		.fixed-header { display: none !important; }
		.app-container, .app-main, .app-main__outer, .app-main__inner {
			margin: 0 !important;
			padding: 0 !important;
			width: auto !important;
			background: #fff !important;
			display: block !important;
		}
		.nr-sheet { overflow: visible; }
		.nr-page { width: auto; height: auto; margin: 0; }
		.nr-slip { break-inside: avoid; page-break-inside: avoid; }
	}
</style>
<?php
$grades = $grades ?? [];
$pupilCount = (int) ($nursery_pupil_count ?? 0);
$classTeacher = trim((string) ($nursery_class_teacher ?? ''));
$headTeacher = trim((string) ($nursery_head_teacher ?? ''));
$nurseryPeriodic = !empty($nursery_periodic);
$periodNo = (int) ($period ?? 0);
$courseInitials = $nursery_course_initials ?? [];
$termNo = (int) ($term ?? 0);
$termLabel = $termNo === 4 ? 'Annual' : (string) \App\Controllers\Home::TermToStr($termNo);
$yearLabel = (string) ($academic_year_title ?? '');
$discMax = (float) ($discipline_max ?? 0);

$fmt = static function ($n) {
	if ($n === null || $n === '') {
		return '';
	}
	$s = number_format((float) $n, 1, '.', '');
	return rtrim(rtrim($s, '0'), '.');
};
$mentionFor = static function ($pct) use ($grades) {
	if ($pct === null || $grades === []) {
		return '';
	}
	foreach ($grades as $grade) {
		$min = (float) ($grade['min_point'] ?? 0);
		$max = (float) ($grade['max_point'] ?? 0);
		if ($pct + 0.001 >= $min && $pct - 0.001 <= $max) {
			return (string) ($grade['color_title'] ?? '');
		}
	}
	return '';
};
$catOnly = static function ($cat) {
	if ($cat !== null && $cat !== '' && is_numeric($cat)) {
		return (float) $cat;
	}
	return null;
};
// Score is the class CAT: quizzes, tests, and homework combined the same way as other classes.
// Full marks are the course maxima (courses.marks). Exam is not included.
$subjectScore = static function (array $core) use ($termNo, $catOnly) {
	$result = $core['result'] ?? [];
	if ($termNo === 4) {
		$parts = [];
		for ($t = 1; $t <= 3; $t++) {
			$s = $catOnly($result['cat'][$t] ?? null);
			if ($s !== null) {
				$parts[] = $s;
			}
		}
		if ($parts === []) {
			return null;
		}
		return array_sum($parts) / count($parts);
	}
	return $catOnly($result['marks'] ?? null);
};

$studentReg = isset($_GET['student']) ? $_GET['student'] : false;
$cards = [];
if (!isset($students) || count($students) === 0) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
foreach ($students as $student) {
	if (!isset($student['id'])) {
		continue;
	}
	if ($studentReg !== false && (string) $student['id'] !== (string) $studentReg) {
		continue;
	}
	$pupil = trim((string) ($student['fname'] ?? '') . ' ' . (string) ($student['lname'] ?? ''));
	$classLabel = trim((string) ($student['level_name'] ?? '') . ' ' . (string) ($student['title'] ?? '') . ' ' . (string) ($student['code'] ?? ''));
	$courses = $student['courses'] ?? [];
	$maxTotal = 0.0;
	$scoreTotal = 0.0;
	$scored = 0;
	$rows = [];
	foreach ($courses as $core) {
		$full = (float) ($core['marks'] ?? 0);
		$score = $subjectScore($core);
		$maxTotal += $full;
		if ($score !== null) {
			$scoreTotal += $score;
			$scored++;
		}
		$pct = ($full > 0 && $score !== null) ? ($score * 100 / $full) : null;
		$cid = (int) ($core['id'] ?? 0);
		$rows[] = [
			'title' => (string) ($core['title'] ?? ''),
			'full' => $full,
			'score' => $score,
			'comment' => $mentionFor($pct),
			'initials' => (string) ($courseInitials[$cid] ?? ''),
		];
	}
	$totalPct = ($maxTotal > 0 && $scored > 0) ? ($scoreTotal * 100 / $maxTotal) : null;
	$conductText = '';
	if ($discMax > 0) {
		if ($termNo === 4) {
			$sum = 0.0;
			$n = 0;
			for ($t = 1; $t <= 3; $t++) {
				$deduct = (float) extractDisciplineMarks($student['displine_marks'] ?? '', $t);
				$sum += max(0, $discMax - $deduct);
				$n++;
			}
			$conduct = $n > 0 ? $sum / $n : $discMax;
		} else {
			$deduct = (float) extractDisciplineMarks($student['displine_marks'] ?? '', $termNo);
			$conduct = max(0, $discMax - $deduct);
		}
		$conductPct = $conduct * 100 / $discMax;
		$conductMention = $mentionFor($conductPct);
		$conductText = $fmt($conduct) . '/' . $fmt($discMax);
		if ($conductMention !== '') {
			$conductText .= ' - ' . $conductMention;
		}
	}
	ob_start();
	?>
	<div class="nr-slip<?= $nurseryPeriodic ? ' periodic' : ''; ?>">
		<div class="nr-top">
			<div class="nr-logo-wrap">
				<?php if (!empty($school_logo)): ?>
					<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" class="nr-logo" alt="">
				<?php endif; ?>
			</div>
			<div class="nr-meta">
				<?php if ($nurseryPeriodic): ?>
					<div class="nr-h"><span class="k nr-period">PERIODIC REPORT</span><span class="nr-ul mid">Period <?= $periodNo > 0 ? $periodNo : ''; ?></span></div>
				<?php endif; ?>
				<div class="nr-h"><span class="k">Names:</span><span class="nr-ul"><?= esc($pupil); ?></span></div>
				<div class="nr-h">
					<span class="k">Results for Term:</span><span class="nr-ul mid"><?= esc($termLabel); ?></span>
					<span class="k">Year:</span><span class="nr-ul short"><?= esc($yearLabel); ?></span>
				</div>
				<div class="nr-h">
					<span class="k">Class:</span><span class="nr-ul mid"><?= esc($classLabel); ?></span>
					<span class="k">Number of Pupils:</span><span class="nr-ul tiny"><?= $pupilCount > 0 ? (int) $pupilCount : ''; ?></span>
				</div>
			</div>
		</div>
		<table class="nr-table">
			<thead>
			<tr>
				<th style="width:23%;">Subject</th>
				<th style="width:12.3%;">Full<br>marks</th>
				<th style="width:15%;">Score</th>
				<th style="width:36.7%;">Comment</th>
				<th style="width:13%;">Initials</th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ($rows as $row): ?>
				<tr>
					<td class="sub"><?= esc($row['title']); ?></td>
					<td class="num"><?= $fmt($row['full']); ?></td>
					<td class="num"><?= $row['score'] === null ? '' : $fmt($row['score']); ?></td>
					<td class="ctr"><?= esc($row['comment']); ?></td>
					<td class="ctr"><?= esc($row['initials']); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ($rows === []): ?>
				<tr><td colspan="5" class="ctr">No subjects are assigned to this class for this term.</td></tr>
			<?php endif; ?>
			<tr class="total">
				<td>TOTAL</td>
				<td class="num"><?= $maxTotal > 0 ? $fmt($maxTotal) : ''; ?></td>
				<td class="num"><?= $scored > 0 ? $fmt($scoreTotal) : ''; ?></td>
				<td></td>
				<td></td>
			</tr>
			</tbody>
		</table>
		<div class="nr-foot">
			<div class="nr-row"><span>Conduct:</span><?php if ($conductText !== ''): ?><span class="nr-name" style="max-width:220pt;"><?= esc($conductText); ?></span><?php endif; ?><span class="nr-dots"></span></div>
			<div class="nr-row"><span>Class teacher's comment:</span><span class="nr-dots"></span></div>
			<div class="nr-row"><span class="nr-dots"></span><span class="nr-signlab">sign:</span><?php if ($classTeacher !== ''): ?><span class="nr-name"><?= esc($classTeacher); ?></span><?php endif; ?><span class="nr-dots tail"></span></div>
			<div class="nr-row"><span>Head teacher's comment:</span><span class="nr-dots"></span></div>
			<div class="nr-row"><span class="nr-dots"></span><span class="nr-signlab">sign:</span><?php if ($headTeacher !== ''): ?><span class="nr-name"><?= esc($headTeacher); ?></span><?php endif; ?><span class="nr-dots tail"></span></div>
			<div class="nr-row"><span>Parent's comment:</span><span class="nr-dots"></span></div>
			<div class="nr-row"><span class="nr-dots"></span><span class="nr-signlab">sign:</span><span class="nr-dots tail"></span></div>
			<div class="nr-row"><span>Next term begins on:</span><span class="nr-dots"></span></div>
			<div class="nr-row"><span>Next term ends on:</span><span class="nr-dots"></span></div>
		</div>
	</div>
	<?php
	$cards[] = ob_get_clean();
}
if ($cards === [] && isset($students) && count($students) > 0) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
if ($cards !== []) {
	echo '<div class="nr-sheet">';
	foreach (array_chunk($cards, 2) as $pair) {
		echo '<div class="nr-page">';
		echo $pair[0];
		if (isset($pair[1])) {
			echo $pair[1];
		}
		echo '</div>';
	}
	echo '</div>';
}
