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
	.nr-sheet {
		font-family: nurserygothic, "Century Gothic", CenturyGothic, sans-serif;
		font-weight: 700;
		color: #231f20;
		background: #eef1f4;
	}
	.nr-fit { width: 100%; overflow: hidden; }
	.nr-paper {
		width: 297mm;
		height: 210mm;
		box-sizing: border-box;
		background: #fff;
		padding: 12.15mm 12.5mm 17.85mm 20.26mm;
	}
	table.nr-slip {
		width: 116.9mm;
		border-collapse: collapse;
		background: #fff;
	}
	table.nr-slip td.nr-body {
		border: 0;
		padding: 0;
		vertical-align: top;
	}
	table.nr-head { width: 100%; border-collapse: collapse; margin: 0; }
	table.nr-head td { border: 0; vertical-align: top; padding: 0; margin: 0; }
	.nr-logo { width: 16.1mm; height: 18.3mm; }
	table.nr-line { width: 100%; border-collapse: collapse; margin: 0; }
	table.nr-line td {
		border: 0;
		font-size: 10.9pt;
		font-weight: 700;
		padding: 0 0.4mm;
		height: 7.1mm;
		vertical-align: bottom;
		font-family: nurserygothic, "Century Gothic", CenturyGothic, sans-serif;
	}
	table.nr-line td.fill { border-bottom: 0.8pt dotted #231f20; }
	table.nr-line td.lab { white-space: nowrap; padding-right: 3pt; }
	table.nr-line td.sign { white-space: nowrap; padding-left: 3pt; }
	table.nr-marks {
		width: 116.9mm;
		border-collapse: collapse;
		margin: 0;
	}
	table.nr-marks th, table.nr-marks td {
		border: 0.68pt solid #231f20;
		padding: 0 0.6mm;
		font-weight: 700;
		vertical-align: middle;
		font-family: nurserygothic, "Century Gothic", CenturyGothic, sans-serif;
		line-height: 1.05;
	}
	table.nr-marks th {
		font-size: 9.7pt;
		text-align: center;
		text-transform: uppercase;
	}
	table.nr-marks td {
		font-size: 11pt;
	}
	table.nr-marks td.sub { text-transform: uppercase; }
	table.nr-marks td.num {
		font-family: nurserygothic, "Century Gothic", CenturyGothic, sans-serif;
		font-size: 11pt;
		text-align: center;
	}
	table.nr-marks td.ctr { text-align: center; }
	table.nr-marks tr.total td { font-size: 11pt; }
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
	@page { size: A4 landscape; margin: 0; }
<?php endif; ?>
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
$logoSrc = '';
if (!empty($school_logo)) {
	$logoFile = FCPATH . 'assets/images/logo/' . $school_logo;
	if (!empty($pdf) && is_file($logoFile)) {
		$logoSrc = str_replace('\\', '/', $logoFile);
	} else {
		$logoSrc = base_url('assets/images/logo/' . $school_logo);
	}
}
$lineH = ' style="height:7.1mm;vertical-align:middle;"';
$footH = ' style="height:7.6mm;"';
$line = static function (string $label, string $value = '', string $tailLabel = '', string $tailValue = '') use ($lineH) {
	echo '<table class="nr-line" width="100%"><tr>';
	if ($label !== '') {
		echo '<td class="lab"' . $lineH . '>' . $label . '</td>';
	}
	echo '<td class="fill"' . $lineH . '>' . ($value !== '' ? esc($value) : '&nbsp;') . '</td>';
	if ($tailLabel !== '') {
		echo '<td class="lab"' . $lineH . '>' . $tailLabel . '</td>';
		echo '<td class="fill"' . $lineH . '>' . ($tailValue !== '' ? esc($tailValue) : '&nbsp;') . '</td>';
	}
	echo '</tr></table>';
};
$signLine = static function (string $name) use ($footH) {
	echo '<table class="nr-line" width="100%"><tr>';
	echo '<td class="fill"' . $footH . '>&nbsp;</td>';
	echo '<td class="lab"' . $footH . '>sign:</td>';
	echo '<td class="sign"' . $footH . '>' . ($name !== '' ? esc($name) : '&nbsp;') . '</td>';
	echo '<td class="fill" style="width:16%;height:7.6mm;">&nbsp;</td>';
	echo '</tr></table>';
};
$footLine = static function (string $label, string $value = '') use ($footH) {
	echo '<table class="nr-line" width="100%"><tr>';
	echo '<td class="lab"' . $footH . '>' . $label . '</td>';
	echo '<td class="fill"' . $footH . '>' . ($value !== '' ? esc($value) : '&nbsp;') . '</td>';
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
	<table class="nr-slip" width="100%">
		<tr>
			<td class="nr-body"<?= !empty($pdf) ? ' style="border:0;padding:0;"' : ''; ?>>
				<table class="nr-head" width="100%">
					<tr>
						<td style="width:48pt;">
							<?php if ($logoSrc !== ''): ?>
								<img src="<?= esc($logoSrc); ?>" class="nr-logo" alt="">
							<?php endif; ?>
						</td>
						<td>
							<?php $line('Names:', $pupil); ?>
							<?php $line('Results for Term:', $termLabel, 'Year:', $yearLabel); ?>
							<?php $line('Class:', $classLabel, 'Number of Pupils:', $pupilCount > 0 ? (string) $pupilCount : ''); ?>
						</td>
					</tr>
				</table>
				<?php
				$headMm = 9.2;
				$totalMm = 9.8;
				$boxMm = 80.1;
				$rowMm = ($boxMm - $headMm - $totalMm) / max(1, count($rows));
				$rowMm = max(4.2, $rowMm);
				$markH = ' style="height:' . round($rowMm, 2) . 'mm;"';
				$headS = 'height:' . $headMm . 'mm;';
				?>
				<table class="nr-marks" width="116.9mm">
					<thead>
					<tr>
						<th width="26.9mm" style="width:26.9mm;<?= $headS; ?>">Subject</th>
						<th width="14.4mm" style="width:14.4mm;<?= $headS; ?>">Full<br>marks</th>
						<th width="17.6mm" style="width:17.6mm;<?= $headS; ?>">Score</th>
						<th width="42.9mm" style="width:42.9mm;<?= $headS; ?>">Comment</th>
						<th width="15.1mm" style="width:15.1mm;<?= $headS; ?>">Initials</th>
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
					</tbody>
				</table>
				<?php $footLine('Conduct:', $conductText); ?>
				<?php $footLine("Class teacher's comment:"); ?>
				<?php $signLine(''); ?>
				<?php $footLine("Head teacher's comment:"); ?>
				<?php $signLine(''); ?>
				<?php $footLine("Parent's comment:"); ?>
				<?php $signLine(''); ?>
				<?php $footLine('Next term begins on:'); ?>
				<?php $footLine('Next term ends on:'); ?>
			</td>
		</tr>
	</table>
	<?php
	$cards[] = ob_get_clean();
}
if ($cards === [] && isset($students) && count($students) > 0) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
if ($cards !== []) {
	echo '<div class="nr-sheet">';
	$pairs = array_chunk($cards, 2);
	$cell = 'width="125.27mm" height="180mm" style="width:125.27mm;height:180mm;border:1.93pt solid #231f20;vertical-align:top;padding:0.2mm 4.2mm 0.4mm 1.8mm;"';
	foreach ($pairs as $i => $pair) {
		$pairTable = '<table width="263.05mm" style="width:263.05mm;border-collapse:collapse;"><tr>'
			. '<td ' . $cell . '>' . $pair[0] . '</td>'
			. '<td width="12.51mm" style="width:12.51mm;border:0;">&nbsp;</td>'
			. '<td ' . $cell . '>' . ($pair[1] ?? '') . '</td>'
			. '</tr></table>';
		if (!empty($pdf)) {
			if ($i > 0) {
				echo '<pagebreak />';
			}
			echo $pairTable;
			continue;
		}
		echo '<div class="nr-fit"><div class="nr-paper">' . $pairTable . '</div></div>';
	}
	echo '</div>';
	if (empty($pdf)) {
		echo '<script>(function(){function fit(){document.querySelectorAll(".nr-fit").forEach(function(box){var paper=box.querySelector(".nr-paper");if(!paper){return;}paper.style.transform="none";var avail=box.clientWidth||paper.offsetWidth;var scale=Math.min(1,avail/paper.offsetWidth);paper.style.transformOrigin="top left";paper.style.transform="scale("+scale+")";box.style.height=(paper.offsetHeight*scale)+"px";});}fit();window.addEventListener("resize",fit);})();</script>';
	}
}
