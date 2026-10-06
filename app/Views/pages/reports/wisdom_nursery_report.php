<style>
	body { margin: 0; }
	.nr-sheet { }
	.nr-sheet {
		font-family: "Century Gothic", CenturyGothic, dejavusans, sans-serif;
		font-weight: 700;
		color: #231f20;
		background: #fff;
	}
	table.nr-page {
		width: 100%;
		border-collapse: collapse;
		margin: 0;
	}
	table.nr-page td.nr-slot { width: 48%; vertical-align: top; }
	table.nr-page td.nr-gap { width: 4%; }
	table.nr-slip {
		width: 100%;
		border-collapse: collapse;
		background: #fff;
	}
	table.nr-slip td.nr-body {
		border: 1.5pt solid #231f20;
		padding: 5pt 6pt 6pt;
		vertical-align: top;
	}
	table.nr-head { width: 100%; border-collapse: collapse; margin: 0 0 3pt; }
	table.nr-head td { border: 0; vertical-align: top; padding: 0; }
	.nr-logo { width: 42pt; height: 46pt; }
	table.nr-line { width: 100%; border-collapse: collapse; margin: 0; }
	table.nr-line td {
		border: 0;
		font-size: 9.5pt;
		font-weight: 700;
		padding: 0 1pt;
		height: 13.5pt;
		vertical-align: bottom;
		font-family: "Century Gothic", CenturyGothic, dejavusans, sans-serif;
	}
	table.nr-line td.fill { border-bottom: 0.8pt dotted #231f20; }
	table.nr-line td.lab { white-space: nowrap; padding-right: 3pt; }
	table.nr-line td.sign { white-space: nowrap; padding-left: 3pt; }
	table.nr-marks {
		width: 100%;
		border-collapse: collapse;
		margin: 2pt 0 4pt;
	}
	table.nr-marks th, table.nr-marks td {
		border: 0.8pt solid #231f20;
		padding: 1pt 2pt;
		font-weight: 700;
		vertical-align: middle;
		font-family: "Century Gothic", CenturyGothic, dejavusans, sans-serif;
		line-height: 1.05;
	}
	table.nr-marks th {
		font-size: 8pt;
		text-align: center;
		text-transform: uppercase;
		height: 16pt;
	}
	table.nr-marks td {
		font-size: 8.2pt;
		height: 13.2pt;
	}
	table.nr-marks td.sub { text-transform: uppercase; }
	table.nr-marks td.num {
		font-family: Arial, Helvetica, dejavusans, sans-serif;
		font-size: 9pt;
		text-align: center;
	}
	table.nr-marks td.ctr { text-align: center; }
	table.nr-marks tr.total td { font-size: 9pt; }
	@media print {
		.app-sidebar-wrapper, .app-sidebar, .app-sidebar-overlay,
		.header-mobile-wrapper, .app-header, .app-footer, .app-page-title,
		.ui-theme-settings, .fixed-header { display: none !important; }
		.app-container, .app-main, .app-main__outer, .app-main__inner {
			margin: 0 !important; padding: 0 !important; width: auto !important; background: #fff !important;
		}
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
$logoSrc = '';
if (!empty($school_logo)) {
	$logoFile = FCPATH . 'assets/images/logo/' . $school_logo;
	if (!empty($pdf) && is_file($logoFile)) {
		$logoSrc = str_replace('\\', '/', $logoFile);
	} else {
		$logoSrc = base_url('assets/images/logo/' . $school_logo);
	}
}
$lineH = !empty($pdf) ? ' style="height:7.4mm;"' : '';
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
$signLine = static function (string $name) use ($lineH) {
	echo '<table class="nr-line" width="100%"><tr>';
	echo '<td class="fill"' . $lineH . '>&nbsp;</td>';
	echo '<td class="lab"' . $lineH . '>sign:</td>';
	echo '<td class="sign"' . $lineH . '>' . ($name !== '' ? esc($name) : '&nbsp;') . '</td>';
	echo '<td class="fill" style="width:16%;' . (!empty($lineH) ? 'height:7.4mm;' : '') . '">&nbsp;</td>';
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
							<?php if ($nurseryPeriodic && $periodNo > 0): ?>
								<?php
								$examTitle = function_exists('nursery_exam_title') ? nursery_exam_title($periodNo) : '';
								if ($examTitle === '') {
									$examTitle = 'Exams';
								}
								?>
								<table width="100%"><tr><td style="text-align:center;font-weight:bold;font-size:11pt;padding:0 0 3pt;"><?= esc($examTitle); ?></td></tr></table>
							<?php endif; ?>
							<?php $line('Names:', $pupil); ?>
							<?php $line('Results for Term:', $termLabel, 'Year:', $yearLabel); ?>
							<?php $line('Class:', $classLabel, 'Number of Pupils:', $pupilCount > 0 ? (string) $pupilCount : ''); ?>
						</td>
					</tr>
				</table>
				<?php
				$markMm = 0;
				if (!empty($pdf)) {
					$markMm = max(4.4, min(8.2, 78 / max(8, count($rows) + 2)));
				}
				$markH = $markMm > 0 ? ' style="height:' . $markMm . 'mm;"' : '';
				?>
				<table class="nr-marks" width="100%">
					<thead>
					<tr>
						<th style="width:24%;<?= $markMm > 0 ? 'height:' . $markMm . 'mm;' : ''; ?>">Subject</th>
						<th style="width:12%;<?= $markMm > 0 ? 'height:' . $markMm . 'mm;' : ''; ?>">Full<br>marks</th>
						<th style="width:14%;<?= $markMm > 0 ? 'height:' . $markMm . 'mm;' : ''; ?>">Score</th>
						<th style="width:36%;<?= $markMm > 0 ? 'height:' . $markMm . 'mm;' : ''; ?>">Comment</th>
						<th style="width:14%;<?= $markMm > 0 ? 'height:' . $markMm . 'mm;' : ''; ?>">Initials</th>
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
						<td<?= $markH; ?>>TOTAL</td>
						<td class="num"><?= $maxTotal > 0 ? $fmt($maxTotal) : ''; ?></td>
						<td class="num"><?= $scored > 0 ? $fmt($scoreTotal) : ''; ?></td>
						<td></td>
						<td></td>
					</tr>
					</tbody>
				</table>
				<?php $line('Conduct:', $conductText); ?>
				<?php $line("Class teacher's comment:"); ?>
				<?php $signLine($classTeacher); ?>
				<?php $line("Head teacher's comment:"); ?>
				<?php $signLine($headTeacher); ?>
				<?php $line("Parent's comment:"); ?>
				<?php $signLine(''); ?>
				<?php $line('Next term begins on:'); ?>
				<?php $line('Next term ends on:'); ?>
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
	$last = count($pairs) - 1;
	foreach ($pairs as $i => $pair) {
		if (!empty($pdf)) {
			if ($i > 0) {
				echo '<pagebreak />';
			}
			$cell = 'width="125.2mm" height="179.9mm" style="width:125.2mm;height:179.9mm;border:1.35pt solid #231f20;vertical-align:top;padding:2mm 2.2mm;"';
			echo '<table width="263mm" style="width:263mm;border-collapse:collapse;"><tr>';
			echo '<td ' . $cell . '>' . $pair[0] . '</td>';
			echo '<td width="12.6mm" style="width:12.6mm;border:0;">&nbsp;</td>';
			echo '<td ' . $cell . '>' . ($pair[1] ?? '') . '</td>';
			echo '</tr></table>';
			continue;
		}
		echo '<table class="nr-page" width="100%"><tr>';
		echo '<td class="nr-slot">' . $pair[0] . '</td>';
		echo '<td class="nr-gap"></td>';
		echo '<td class="nr-slot">' . ($pair[1] ?? '') . '</td>';
		echo '</tr></table>';
	}
	echo '</div>';
}
