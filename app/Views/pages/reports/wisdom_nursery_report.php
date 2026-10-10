<style>
	body { margin: 0; }
<?php if (empty($pdf)): ?>
	@font-face {
		font-family: nurserygothic;
		src: url("<?= base_url('assets/fonts/texgyreadventor-bold.ttf'); ?>") format("truetype");
		font-weight: 700;
		font-style: normal;
	}
<?php endif; ?>
	.nr-sheet, .nr-slip, .nr-id, .nr-marks, .nr-foot, .nr-sign {
		font-family: <?= !empty($pdf) ? 'nurserygothic' : '"Century Gothic", CenturyGothic, nurserygothic, sans-serif'; ?>;
		font-weight: 700;
		color: #231f20;
	}
	.nr-sheet { background: <?= !empty($pdf) ? '#fff' : '#eef1f4'; ?>; }
	.nr-fit { width: 100%; overflow: hidden; }
	.nr-paper {
		width: 210mm;
		height: 297mm;
		box-sizing: border-box;
		background: #fff;
		padding: 10mm 12mm;
	}
	.nr-slip { width: 186mm; border-collapse: collapse; background: #fff; }
	.nr-slip td.nr-body { border: 0; padding: 0 0 0 0.72mm; vertical-align: top; }
	.nr-id { border-collapse: collapse; }
	.nr-id td { border: 0; padding: 0; vertical-align: bottom; font-size: 10.86pt; font-weight: 700; line-height: 1; white-space: nowrap; }
	.nr-id td.fill { border-bottom: 0.96pt solid #231f20; padding: 0 0.4mm 0.2mm 0.5mm; }
	.nr-id td.gap { border: 0; }
	.nr-id td.exam {
		vertical-align: middle;
		font-size: 10pt;
		padding: 0 0 0 0.2mm;
		white-space: nowrap;
	}
	.nr-logo { width: 16.14mm; height: 19.13mm; margin: 1.56mm 0 0 2.38mm; display: block; }
	.nr-marks { width: 186mm; border-collapse: collapse; }
	.nr-marks th, .nr-marks td {
		border: 0.96pt solid #231f20;
		padding: 0 0.7mm;
		font-weight: 700;
		vertical-align: middle;
		font-family: <?= !empty($pdf) ? 'nurserygothic' : '"Century Gothic", CenturyGothic, nurserygothic, sans-serif'; ?>;
		line-height: 1.05;
	}
	.nr-marks th {
		font-size: 9.73pt;
		text-align: center;
		text-transform: uppercase;
		padding: 0 0.4mm;
	}
	.nr-marks td { font-size: 10.98pt; }
	.nr-marks td.sub { text-transform: uppercase; padding-left: 1.1mm; }
	.nr-marks td.num {
		font-family: <?= !empty($pdf) ? 'nurseryarial, nurserygothic' : 'Arial, Helvetica, sans-serif'; ?>;
		font-size: 10.98pt;
		font-weight: 700;
		text-align: center;
	}
	.nr-marks td.ctr { text-align: center; }
	.nr-footwrap { width: 184mm; margin-left: 1mm; padding-top: 3mm; }
	.nr-foot, .nr-sign { width: 184mm; border-collapse: collapse; table-layout: fixed; }
	.nr-foot td.nr-on-line {
		font-family: <?= !empty($pdf) ? 'nurserygothic' : '"Century Gothic", CenturyGothic, nurserygothic, sans-serif'; ?>;
		color: #1d4ed8;
		font-weight: 700;
		font-size: 11pt;
		border-bottom: 0.9pt dotted #1d4ed8;
		white-space: nowrap;
		overflow: visible;
		line-height: 1.15;
		vertical-align: bottom;
	}
	.nr-foot td, .nr-sign td {
		border: 0;
		padding: 0;
		margin: 0;
		font-size: 11pt;
		font-weight: 700;
		line-height: 11pt;
		vertical-align: top;
		white-space: nowrap;
		font-family: <?= !empty($pdf) ? 'nurserygothic' : '"Century Gothic", CenturyGothic, nurserygothic, sans-serif'; ?>;
	}
	.nr-sign td.sig { font-size: 11pt; }
<?php if (empty($pdf)): ?>
	@media print {
		.app-sidebar-wrapper, .app-sidebar, .app-sidebar-overlay,
		.header-mobile-wrapper, .app-header, .app-footer, .app-page-title,
		.ui-theme-settings, .fixed-header { display: none !important; }
		.app-container, .app-main, .app-main__outer, .app-main__inner {
			margin: 0 !important; padding: 0 !important; width: auto !important; background: #fff !important;
		}
		.nr-sheet { background: #fff; }
		.nr-fit { height: auto !important; overflow: visible !important; }
		.nr-paper { transform: none !important; margin: 0; page-break-after: always; }
		.nr-fit:last-child .nr-paper { page-break-after: auto; }
	}
		@page { size: A4 portrait; margin: 0; }
<?php endif; ?>
</style>
<?php
$grades = $grades ?? [];
$pupilCount = (int) ($nursery_pupil_count ?? 0);
$periodNo = (int) ($period ?? 0);
$courseInitials = $nursery_course_initials ?? [];
$termNo = (int) ($term ?? 0);
$termLabel = $termNo === 4 ? 'Annual' : (string) \App\Controllers\Home::TermToStr($termNo);
$yearLabel = (string) ($academic_year_title ?? '');
$examTitle = trim((string) ($nursery_exam_title ?? ''));
if ($examTitle === '' && !empty($nursery_periodic)) {
	$examTitle = nursery_exam_title($periodNo);
}
$endOfTermSheet = strcasecmp($examTitle, 'End of Term Exam') === 0;
$resultsValue = $endOfTermSheet ? $termLabel : $examTitle;
$logoSrc = '';
if (!empty($school_logo)) {
	$logoFile = FCPATH . 'assets/images/logo/' . $school_logo;
	if (!empty($pdf) && is_file($logoFile)) {
		$logoSrc = str_replace('\\', '/', $logoFile);
	} else {
		$logoSrc = base_url('assets/images/logo/' . $school_logo);
	}
}
// Century Gothic Bold / TeX Gyre Adventor advances at 11pt, used to fill dotted rules.
$glyphPt = [
	32 => 3.08, 39 => 2.42, 45 => 4.62, 46 => 3.08, 47 => 6.27, 58 => 3.08,
	48 => 6.16, 49 => 6.16, 50 => 6.16, 51 => 6.16, 52 => 6.16, 53 => 6.16, 54 => 6.16, 55 => 6.16, 56 => 6.16, 57 => 6.16,
	65 => 8.14, 66 => 6.38, 67 => 8.58, 68 => 7.70, 69 => 5.72, 70 => 5.28, 71 => 9.24, 72 => 7.48, 73 => 3.08, 74 => 5.28,
	75 => 6.82, 76 => 4.84, 77 => 9.90, 78 => 8.14, 79 => 9.24, 80 => 6.16, 81 => 9.24, 82 => 6.38, 83 => 5.72, 84 => 4.62,
	85 => 7.04, 86 => 7.70, 87 => 9.90, 88 => 7.48, 89 => 6.82, 90 => 5.50,
	97 => 7.26, 98 => 7.26, 99 => 7.04, 100 => 7.26, 101 => 7.04, 102 => 3.08, 103 => 7.26, 104 => 6.60, 105 => 2.64,
	106 => 2.86, 107 => 6.38, 108 => 2.64, 109 => 10.34, 110 => 6.60, 111 => 7.04, 112 => 7.26, 113 => 7.26, 114 => 3.52,
	115 => 4.84, 116 => 3.30, 117 => 6.60, 118 => 6.16, 119 => 8.80, 120 => 6.16, 121 => 6.38, 122 => 5.06,
];
$advPt = static function (string $text) use ($glyphPt): float {
	$w = 0.0;
	$len = strlen($text);
	for ($i = 0; $i < $len; $i++) {
		$o = ord($text[$i]);
		if ($o < 128) {
			$w += $glyphPt[$o] ?? 6.6;
			continue;
		}
		if (($o & 0xC0) !== 0x80) {
			$w += 6.6;
		}
	}
	return $w;
};
$mm = static function (float $pt): string {
	return rtrim(rtrim(number_format($pt / 2.83465, 2, '.', ''), '0'), '.');
};
$linePt = 184 * 2.83465;
$dotPt = 3.08;
$footLine = static function (string $label, string $value, float $hMm, bool $dots = true) use ($advPt, $mm, $linePt, $dotPt) {
	$prefix = $label;
	if ($value !== '') {
		$prefix .= ' ' . $value;
	}
	$used = $advPt($prefix);
	$remain = max(0, $linePt - $used - 1.2);
	$n = $dots ? (int) floor($remain / $dotPt) : 0;
	$labelMm = $mm($used + 0.6);
	$cell = 'height:' . $hMm . 'mm;line-height:' . $hMm . 'mm;vertical-align:top;';
	echo '<table class="nr-foot"><tr style="height:' . $hMm . 'mm">';
	echo '<td style="width:' . $labelMm . 'mm;' . $cell . '">' . esc($prefix) . '</td>';
	echo '<td style="' . $cell . '">' . ($n > 0 ? str_repeat('.', $n) : '') . '</td>';
	echo '</tr></table>';
};
$wrapWords = static function (string $text, float $maxPt) use ($advPt): array {
	$text = trim((string) preg_replace('/\s+/u', ' ', $text));
	if ($text === '') {
		return [];
	}
	$words = preg_split('/\s+/u', $text) ?: [];
	$lines = [];
	$cur = '';
	foreach ($words as $word) {
		$try = $cur === '' ? $word : ($cur . ' ' . $word);
		if ($cur !== '' && $advPt($try) > $maxPt) {
			$lines[] = $cur;
			$cur = $word;
			continue;
		}
		$cur = $try;
	}
	if ($cur !== '') {
		$lines[] = $cur;
	}
	return $lines;
};
$commentLine = static function (string $label, string $text, float $hMm, string $signHtml = '') use ($advPt, $mm, $linePt, $dotPt, $wrapWords) {
	$text = trim($text);
	$sign = 'sign:';
	$tailDots = 18;
	$labelPt = $advPt($label) + 2.2;
	$signPt = $advPt($sign) + 2.2;
	$tailPt = $tailDots * $dotPt;
	$midPt = max(48.0, $linePt - $labelPt - $signPt - $tailPt - 3.0);
	$lines = $wrapWords($text, $midPt * 0.94);
	if ($lines === []) {
		$lines = [''];
	}
	$lineMm = count($lines) > 1 ? 5.2 : max(5.2, $hMm);
	$last = count($lines) - 1;
	echo '<table class="nr-foot">';
	foreach ($lines as $i => $line) {
		$cell = 'height:' . $lineMm . 'mm;line-height:' . $lineMm . 'mm;vertical-align:bottom;';
		echo '<tr style="height:' . $lineMm . 'mm">';
		echo '<td style="width:' . $mm($labelPt) . 'mm;' . $cell . 'white-space:nowrap;">' . ($i === 0 ? esc($label) : '') . '</td>';
		if ($line === '') {
			$n = (int) floor(($midPt * 0.94) / $dotPt);
			echo '<td style="width:' . $mm($midPt) . 'mm;' . $cell . '">' . str_repeat('.', max(8, $n)) . '</td>';
		} else {
			echo '<td class="nr-on-line" style="width:' . $mm($midPt) . 'mm;' . $cell . 'white-space:nowrap;">' . esc($line) . '</td>';
		}
		if ($i === $last && $signHtml !== '') {
			echo '<td colspan="2" style="width:' . $mm($signPt + $tailPt) . 'mm;' . $cell . 'white-space:nowrap;">'
				. '<span style="white-space:nowrap;vertical-align:bottom;">sign: ' . $signHtml . '</span></td>';
		} elseif ($i === $last) {
			echo '<td style="width:' . $mm($signPt) . 'mm;' . $cell . 'white-space:nowrap;">' . $sign . '</td>';
			echo '<td style="width:' . $mm($tailPt) . 'mm;' . $cell . 'white-space:nowrap;">' . str_repeat('.', $tailDots) . '</td>';
		} else {
			echo '<td style="width:' . $mm($signPt) . 'mm;' . $cell . '"></td>';
			echo '<td style="width:' . $mm($tailPt) . 'mm;' . $cell . '"></td>';
		}
		echo '</tr>';
	}
	echo '</table>';
};
$signLine = static function (float $hMm) {
	$lead = str_repeat('.', 118);
	$tail = str_repeat('.', 34);
	$cell = 'height:' . $hMm . 'mm;line-height:' . $hMm . 'mm;vertical-align:top;';
	echo '<table class="nr-sign"><tr style="height:' . $hMm . 'mm">';
	echo '<td style="width:132mm;' . $cell . '">' . $lead . '</td>';
	echo '<td class="sig" style="width:12mm;' . $cell . '">sign:</td>';
	echo '<td style="width:40mm;' . $cell . '">' . $tail . '</td>';
	echo '</tr></table>';
};
$idLine = static function (array $parts, float $hMm) {
	$sum = 0.0;
	foreach ($parts as $part) {
		$sum += (float) $part[1];
	}
	$w = rtrim(rtrim(number_format($sum, 2, '.', ''), '0'), '.');
	echo '<table class="nr-id" width="' . $w . 'mm" style="width:' . $w . 'mm;height:' . $hMm . 'mm;table-layout:fixed;"><tr style="height:' . $hMm . 'mm">';
	foreach ($parts as $part) {
		$kind = $part[0];
		$w = $part[1];
		$text = $part[2] ?? '';
		echo '<td class="' . $kind . '" style="width:' . $w . 'mm;height:' . $hMm . 'mm">';
		echo $text !== '' ? esc($text) : '&nbsp;';
		echo '</td>';
	}
	echo '</tr></table>';
};
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
	$streamClassName = trim((string) ($report_stream_label ?? ''));
	$classLabel = $streamClassName !== ''
		? $streamClassName
		: trim((string) ($student['level_name'] ?? '') . ' ' . (string) ($student['title'] ?? '') . ' ' . (string) ($student['code'] ?? ''));
	$courses = $student['courses'] ?? [];
	$maxTotal = 0.0;
	$scoreTotal = 0.0;
	$scored = 0;
	$rows = [];
	foreach ($courses as $core) {
		$full = (float) ($core['marks'] ?? 0);
		$score = $subjectScore($core);
		if ($score !== null) {
			$maxTotal += $full;
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
		$conductText = $fmt($conduct) . '/' . $fmt($discMax);
	}
	ob_start();
	?>
	<?php
	$say = $report_remarks[(int) ($student['id'] ?? 0)] ?? [];
	$classComment = trim((string) ($say['class_teacher'] ?? ''));
	$headComment = trim((string) ($say['head_teacher'] ?? ''));
	?>
	<table class="nr-slip" width="186mm">
		<tr>
			<td class="nr-body">
				<table class="nr-id" width="186mm">
					<tr>
						<td rowspan="4" style="width:28mm;vertical-align:top;border:0;padding:0;">
							<?php if ($logoSrc !== ''): ?>
								<img src="<?= esc($logoSrc); ?>" class="nr-logo" alt="">
							<?php endif; ?>
						</td>
						<td style="border:0;padding:0;vertical-align:top;">
							<?php $idLine([['lab', 18, 'Names:'], ['fill', 140, $pupil]], 7); ?>
							<?php
							$resultsParts = $endOfTermSheet
								? [
									['lab', 36, 'Results for Term:'],
									['fill', 48, $termLabel],
									['gap', 4, ''],
									['lab', 14, 'Year:'],
									['fill', 56, $yearLabel],
								]
								: [
									['lab', 26, 'Results for:'],
									['fill', 70, $resultsValue],
									['gap', 4, ''],
									['lab', 14, 'Year:'],
									['fill', 44, $yearLabel],
								];
							$idLine($resultsParts, 8);
							?>
							<?php $idLine([
								['lab', 16, 'Class:'],
								['fill', 52, $classLabel],
								['gap', 4, ''],
								['lab', 42, 'Number of Pupils:'],
								['fill', 44, $pupilCount > 0 ? (string) $pupilCount : ''],
							], 8); ?>
							<table class="nr-id"><tr style="height:4.32mm">
								<td class="exam" style="height:4.32mm">&nbsp;</td>
							</tr></table>
						</td>
					</tr>
				</table>
				<?php
				$headMm = 10;
				$totalMm = 10;
				$showPercentage = !empty($nursery_periodic);
				$pctMm = $showPercentage ? 8 : 0;
				$boxMm = $endOfTermSheet ? 145 : 162;
				$rowMm = ($boxMm - $headMm - $totalMm - $pctMm) / max(1, count($rows));
				$rowMm = max(4.2, $rowMm);
				$markH = ' style="height:' . round($rowMm, 2) . 'mm;"';
				$headS = 'height:' . $headMm . 'mm;';
				?>
				<table class="nr-marks" width="186mm">
					<thead>
					<tr>
						<th style="width:48mm;<?= $headS; ?>">Subject</th>
						<th style="width:24mm;<?= $headS; ?>">Full<br>marks</th>
						<th style="width:24mm;<?= $headS; ?>">Score</th>
						<th style="width:66mm;<?= $headS; ?>">Comment</th>
						<th style="width:24mm;<?= $headS; ?>">Initials</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($rows as $row): ?>
						<tr>
							<td class="sub"<?= $markH; ?>><?= esc($row['title']); ?></td>
							<td class="num"<?= $markH; ?>><?= $fmt($row['full']); ?></td>
							<td class="num"<?= $markH; ?>><?= $row['score'] === null ? '' : $fmt($row['score']); ?></td>
							<td class="ctr"<?= $markH; ?>><?= esc($row['comment']); ?></td>
							<td class="ctr"<?= $markH; ?>><?= esc($row['initials']); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ($rows === []): ?>
						<tr><td colspan="5" class="ctr">No subjects are assigned to this class for this term.</td></tr>
					<?php endif; ?>
					<tr class="total">
						<td style="height:<?= $totalMm; ?>mm;">TOTAL</td>
						<td class="num"><?= $maxTotal > 0 ? $fmt($maxTotal) : ''; ?></td>
						<td class="num"><?= $scored > 0 ? $fmt($scoreTotal) : ''; ?></td>
						<td></td>
						<td></td>
					</tr>
					<?php if ($showPercentage): ?>
					<tr class="total">
						<td style="height:<?= $pctMm; ?>mm;">PERCENTAGE</td>
						<td class="num" colspan="2"><?= $totalPct !== null ? $fmt($totalPct) . '%' : ''; ?></td>
						<td></td>
						<td></td>
					</tr>
					<?php endif; ?>
					</tbody>
				</table>
				<div class="nr-footwrap">
					<?php $footLine('Conduct:', $conductText, 8, false); ?>
					<?php
					$classSignFile = trim((string) ($report_stream_label ?? '')) !== ''
						? (string) ($report_class_signature ?? '')
						: (string) (($report_class_signatures ?? [])[(int) ($student['class'] ?? 0)] ?? ($report_class_signature ?? ''));
					$commentLine("Class teacher's comment:", $classComment, 8.4, report_signature_img($classSignFile, !empty($pdf), 6.4, 28));
					?>
					<?php $commentLine("Head teacher's comment:", $headComment, 8.4, report_signature_img($report_head_signature ?? '', !empty($pdf), 6.4, 28)); ?>
					<?php $footLine("Parent's comment:", '', 8); ?>
					<?php $signLine(8); ?>
					<?php if ($endOfTermSheet): ?>
					<?php $footLine('Next term begins on:', '', 8.54); ?>
					<?php $footLine('Next term ends on:', '', 5.50); ?>
					<?php endif; ?>
				</div>
			</td>
		</tr>
	</table>
	<?php
	$cards[] = [
		'html' => ob_get_clean(),
		'score' => $totalPct,
		'name' => $pupil,
	];
}
if ($cards === [] && isset($students) && count($students) > 0) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
if ($cards !== []) {
	$ranked = [];
	foreach ($cards as $i => $card) {
		if (($card['score'] ?? null) !== null && $card['score'] !== '') {
			$ranked[$i] = (float) $card['score'];
		}
	}
	arsort($ranked, SORT_NUMERIC);
	$place = 0;
	$seen = 0;
	$prev = null;
	foreach ($ranked as $i => $score) {
		$seen++;
		if ($prev === null || abs($score - $prev) > 0.001) {
			$place = $seen;
			$prev = $score;
		}
		$cards[$i]['pos'] = $place;
	}
	foreach ($cards as $i => $card) {
		if (!isset($card['pos'])) {
			$cards[$i]['pos'] = '';
		}
	}
	sort_report_cards_by_position($cards);
	$cell = 'width="186mm" style="width:186mm;border:1.4pt solid #231f20;vertical-align:top;padding:1.5mm;"';
	if (!empty($pdf)) {
		$firstSheet = true;
		foreach ($cards as $card) {
			if (!$firstSheet) {
				echo '<!--REPORT_PAGE-->';
			}
			$firstSheet = false;
			echo '<table width="186mm" style="width:186mm;border-collapse:collapse;"><tr><td ' . $cell . '>' . $card['html'] . '</td></tr></table>';
		}
	} else {
	echo '<div class="nr-sheet">';
	foreach ($cards as $card) {
		$page = '<table width="186mm" style="width:186mm;border-collapse:collapse;"><tr><td ' . $cell . '>' . $card['html'] . '</td></tr></table>';
		echo '<div class="nr-fit"><div class="nr-paper">' . $page . '</div></div>';
	}
	echo '</div>';
	}
	if (empty($pdf)) {
		echo '<script>(function(){function fit(){document.querySelectorAll(".nr-fit").forEach(function(box){var paper=box.querySelector(".nr-paper");if(!paper){return;}paper.style.transform="none";var avail=box.clientWidth||paper.offsetWidth;var scale=Math.min(1,avail/paper.offsetWidth);paper.style.transformOrigin="top left";paper.style.transform="scale("+scale+")";box.style.height=(paper.offsetHeight*scale)+"px";});}fit();window.addEventListener("resize",fit);})();</script>';
	}
}
