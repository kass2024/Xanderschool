<style>
	.nr-slip {
		width: 100%;
		max-width: 820px;
		margin: 0 auto 18px;
		font-family: "Times New Roman", Times, serif;
		color: #111;
		page-break-inside: avoid;
		page-break-after: always;
	}
	.nr-sheet {
		border: 3px solid #000;
		padding: 14px 16px 16px;
		background: #fff;
		box-sizing: border-box;
	}
	.nr-head { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
	.nr-head td { border: 0; vertical-align: top; padding: 0; }
	.nr-logo { width: 92px; height: auto; display: block; }
	.nr-school {
		font-size: 11px;
		font-weight: bold;
		text-align: center;
		margin-top: 4px;
		letter-spacing: .2px;
	}
	.nr-line { font-size: 15px; margin: 0 0 7px; line-height: 1.35; }
	.nr-lab { font-weight: normal; }
	.nr-fill {
		border-bottom: 1px dotted #222;
		display: inline-block;
		min-width: 180px;
		padding: 0 8px 1px;
		font-weight: bold;
	}
	.nr-fill.wide { min-width: 280px; }
	.nr-fill.mid { min-width: 140px; }
	.nr-table { width: 100%; border-collapse: collapse; margin: 8px 0 10px; }
	.nr-table th, .nr-table td {
		border: 1px solid #111;
		padding: 6px 8px;
		font-size: 14px;
	}
	.nr-table th {
		text-align: center;
		font-weight: bold;
		text-transform: uppercase;
		letter-spacing: .3px;
	}
	.nr-table td.sub { text-transform: uppercase; }
	.nr-table td.ctr { text-align: center; }
	.nr-table tr.total td { font-weight: bold; }
	.nr-block { font-size: 15px; margin: 8px 0 4px; line-height: 1.7; }
	.nr-dots {
		border-bottom: 1px dotted #222;
		display: inline-block;
		min-width: 70%;
		height: 1.1em;
	}
	.nr-sign { float: right; min-width: 180px; }
	@media print {
		.nr-slip { max-width: none; margin: 0; }
		.nr-sheet { border-width: 2px; }
	}
</style>
<?php
$grades = $grades ?? [];
$pupilCount = (int) ($nursery_pupil_count ?? 0);
$classTeacher = trim((string) ($nursery_class_teacher ?? ''));
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
$printed = 0;
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
	$printed++;
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
	$totalComment = $mentionFor($totalPct);

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
			$conductText .= ' — ' . $conductMention;
		}
	}
	?>
	<div class="nr-slip" id="printable">
		<div class="nr-sheet">
			<table class="nr-head">
				<tr>
					<td style="width:110px;">
						<?php if (!empty($school_logo)): ?>
							<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" class="nr-logo" alt="">
						<?php endif; ?>
						<div class="nr-school"><?= esc(strtoupper((string) ($school_name ?? ''))); ?></div>
					</td>
					<td>
						<div class="nr-line"><span class="nr-lab">Names:</span> <span class="nr-fill wide"><?= esc($pupil); ?></span></div>
						<div class="nr-line">
							<span class="nr-lab">Results for Term:</span> <span class="nr-fill mid"><?= esc($termLabel); ?></span>
							&nbsp;&nbsp; <span class="nr-lab">Year:</span> <span class="nr-fill mid"><?= esc($yearLabel); ?></span>
						</div>
						<div class="nr-line">
							<span class="nr-lab">Class:</span> <span class="nr-fill mid"><?= esc($classLabel); ?></span>
							&nbsp;&nbsp; <span class="nr-lab">Number of Pupils:</span> <span class="nr-fill" style="min-width:70px;"><?= $pupilCount > 0 ? (int) $pupilCount : ''; ?></span>
						</div>
					</td>
				</tr>
			</table>

			<table class="nr-table">
				<thead>
				<tr>
					<th style="width:34%;">Subject</th>
					<th style="width:16%;">Full marks</th>
					<th style="width:16%;">Score</th>
					<th style="width:22%;">Comment</th>
					<th style="width:12%;">Initials</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($rows as $row): ?>
					<tr>
						<td class="sub"><?= esc($row['title']); ?></td>
						<td class="ctr"><?= $fmt($row['full']); ?></td>
						<td class="ctr"><?= $row['score'] === null ? '' : $fmt($row['score']); ?></td>
						<td class="ctr"><?= esc($row['comment']); ?></td>
						<td class="ctr"><?= esc($row['initials']); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ($rows === []): ?>
					<tr><td colspan="5" class="ctr">No subjects are assigned to this class for this term.</td></tr>
				<?php endif; ?>
				<tr class="total">
					<td>TOTAL</td>
					<td class="ctr"><?= $maxTotal > 0 ? $fmt($maxTotal) : ''; ?></td>
					<td class="ctr"><?= $scored > 0 ? $fmt($scoreTotal) : ''; ?></td>
					<td class="ctr"><?= esc($totalComment); ?></td>
					<td></td>
				</tr>
				</tbody>
			</table>

			<div class="nr-block">Conduct: <span class="nr-dots" style="min-width:78%;"><?= esc($conductText); ?></span></div>

			<div class="nr-block">
				Class teacher's comment:<span class="nr-dots" style="min-width:58%;"></span>
				<span class="nr-sign">sign: <span class="nr-dots" style="min-width:120px;"><?= esc($classTeacher); ?></span></span>
			</div>
			<div class="nr-block"><span class="nr-dots" style="min-width:100%;"></span></div>

			<div class="nr-block">
				Head teacher's comment:<span class="nr-dots" style="min-width:55%;"></span>
				<span class="nr-sign">sign:
					<?php if (!empty($headmaster_signature) && strlen((string) $headmaster_signature) > 5): ?>
						<img src="<?= base_url('assets/images/signatures/' . $headmaster_signature); ?>" alt="" style="max-height:42px;vertical-align:middle;">
					<?php else: ?>
						<span class="nr-dots" style="min-width:120px;"><?= esc((string) ($head_master ?? '')); ?></span>
					<?php endif; ?>
				</span>
			</div>
			<div class="nr-block"><span class="nr-dots" style="min-width:100%;"></span></div>

			<div class="nr-block">
				Parent's comment:<span class="nr-dots" style="min-width:62%;"></span>
				<span class="nr-sign">sign: <span class="nr-dots" style="min-width:120px;"></span></span>
			</div>
			<div class="nr-block"><span class="nr-dots" style="min-width:100%;"></span></div>

			<div class="nr-block">Next term begins on: <span class="nr-dots" style="min-width:62%;"></span></div>
			<div class="nr-block">Next term ends on: <span class="nr-dots" style="min-width:64%;"></span></div>
		</div>
	</div>
	<?php
}
if ($printed === 0 && isset($students) && count($students) > 0) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
?>
