<style>
	body { margin: 0; }
	.ws-sheet { background: <?= !empty($pdf) ? '#fff' : '#eef1f4'; ?>; }
	.ws-fit { width: 100%; overflow: hidden; }
	.ws-paper {
		width: 210mm;
		height: 297mm;
		box-sizing: border-box;
		background: #fff;
		padding: 6mm 12mm;
	}
	.ws-card { width: 186mm; vertical-align: top; color: #111; }
	.ws-head, .ws-id, .ws-grid, .ws-scale, .ws-sign { border-collapse: collapse; width: 186mm; table-layout: fixed; }
	.ws-crest, .ws-logo { width: 16mm; height: 16mm; display: block; }
	.ws-republic { font-size: 8.5pt; font-weight: 700; letter-spacing: 0.3px; text-align: center; line-height: 1.15; }
	.ws-school { font-size: 11pt; font-weight: 700; text-align: center; line-height: 1.15; margin-top: 0.4mm; }
	.ws-moto { font-size: 8pt; text-align: center; line-height: 1.15; }
	.ws-title { font-size: 10.5pt; font-weight: 700; text-align: center; margin-top: 1.2mm; }
	.ws-sub { font-size: 8pt; font-weight: 700; text-align: center; }
	.ws-id td, .ws-grid td, .ws-scale td, .ws-sign td { border: 0.6pt solid #1e3a5f; padding: 0.4mm 1mm; vertical-align: middle; }
	.ws-id { margin-top: 1.6mm; }
	.ws-id .k { font-size: 7.5pt; font-weight: 700; width: 28mm; background: #f4f7fb; }
	.ws-id .v { font-size: 8pt; font-weight: 700; }
	.ws-grid { margin-top: 1.6mm; }
	.ws-grid th {
		background: #1e3a5f;
		color: #fff;
		font-size: 7.5pt;
		font-weight: 700;
		text-align: center;
		border: 0.6pt solid #1e3a5f;
		padding: 0.6mm 0.8mm;
	}
	.ws-grid td { font-size: 8pt; }
	.ws-grid tr.alt td { background: #f3f7fb; }
	.ws-name { text-align: left; font-weight: 700; }
	.ws-ctr { text-align: center; }
	.ws-total td { background: #e8eef5; font-weight: 700; }
	.ws-sum { font-size: 8pt; font-weight: 700; margin: 1.2mm 0; }
	.ws-scale { margin-top: 0.6mm; }
	.ws-scale td { text-align: center; font-size: 7pt; font-weight: 700; color: #fff; line-height: 1.15; padding: 0.6mm 0.4mm; }
	.ws-comment {
		font-size: 8.5pt;
		font-weight: 700;
		padding: 0.4mm 0;
	}
	.ws-on {
		color: #1d4ed8;
		border-bottom: 0.8pt dotted #1d4ed8;
		white-space: nowrap;
	}
	.ws-sign { margin-top: 1mm; }
	.ws-sign td { font-size: 8pt; font-weight: 700; border: 0.6pt solid #1e3a5f; height: 6.2mm; }
	.ws-note { font-size: 7pt; margin-top: 1mm; }
<?php if (empty($pdf)): ?>
	@media print {
		.app-sidebar-wrapper, .app-sidebar, .app-sidebar-overlay,
		.header-mobile-wrapper, .app-header, .app-footer, .app-page-title,
		.ui-theme-settings, .fixed-header { display: none !important; }
		.app-container, .app-main, .app-main__outer, .app-main__inner {
			margin: 0 !important; padding: 0 !important; width: auto !important; background: #fff !important;
		}
		.ws-sheet { background: #fff; }
		.ws-fit { height: auto !important; overflow: visible !important; }
		.ws-paper { transform: none !important; margin: 0; page-break-after: always; }
		.ws-fit:last-child .ws-paper { page-break-after: auto; }
	}
	@page { size: A4 portrait; margin: 0; }
<?php endif; ?>
</style>
<?php
$band = (string) ($secondary_band ?? 'o_level');
$periodic = !empty($secondary_periodic);
$termNo = (int) ($term ?? 0);
$roman = [1 => 'I', 2 => 'II', 3 => 'III'][$termNo] ?? (string) $termNo;
$yearLabel = (string) ($academic_year_title ?? '');
$schoolName = trim((string) ($school_name ?? ''));
$pobox = trim((string) ($school_pobox ?? ''));
$address = trim((string) ($school_address ?? ''));
$moto = trim((string) ($school_moto ?? ''));
if ($moto === '') {
	$moto = 'FEARING GOD IS KNOWLEDGE';
}
$boxLine = 'P.O. BOX: ' . ($pobox !== '' ? $pobox : '—');
if ($address !== '') {
	$boxLine .= ', ' . $address;
}
$anp = $band === 'special';
$heading = [
	'o_level' => 'O-LEVEL',
	'a_level' => 'A-LEVEL',
	'rtb' => 'RTB',
	'special' => 'ANP A-LEVEL',
][$band] ?? 'A-LEVEL';
$periodNo = (int) ($period ?? 0);
$periodLabel = $periodNo > 0 ? ('PERIOD ' . $periodNo) : '';
if ($periodic) {
	$cardTitle = $heading . ' PERIODIC REPORT';
	$subtitle = $periodLabel;
	$reportKind = $periodLabel !== '' ? $periodLabel : 'PERIODIC REPORT';
} else {
	$cardTitle = $heading . ' END OF TERM ' . $roman . ' REPORT';
	$subtitle = '';
	$reportKind = 'End of term';
}

$assetSrc = static function (string $relative) use ($pdf) {
	$file = FCPATH . ltrim($relative, '/');
	if (!is_file($file)) {
		return '';
	}
	if (!empty($pdf)) {
		return str_replace('\\', '/', $file);
	}
	return base_url($relative);
};
$crestSrc = $assetSrc('assets/images/holiday_coaching/rwanda_coat_of_arms.jpeg');
$logoSrc = '';
if (!empty($school_logo)) {
	$logoSrc = $assetSrc('assets/images/logo/' . $school_logo);
}
$initials = $secondary_course_initials ?? [];
$comboRank = $secondary_combo_rank ?? [];
$remarks = $report_remarks ?? [];
$classTeacher = trim((string) ($secondary_class_teacher ?? ''));
$classPhone = trim((string) ($secondary_class_teacher_phone ?? ''));
$headTeacher = trim((string) ($secondary_head_teacher ?? ''));
$headPhone = trim((string) ($secondary_head_teacher_phone ?? ''));

$fmt = static function ($n) {
	if ($n === null || $n === '') {
		return '';
	}
	$s = number_format((float) $n, 1, '.', '');
	return rtrim(rtrim($s, '0'), '.');
};
$bandOf = static function ($pct): array {
	if ($pct === null) {
		return ['letter' => '', 'descriptor' => '', 'note' => '', 'decision' => ''];
	}
	if ($pct >= 95) {
		return ['letter' => 'A', 'descriptor' => 'Excellent', 'note' => 'Excellent', 'decision' => 'Excellent'];
	}
	if ($pct >= 86) {
		return ['letter' => 'B', 'descriptor' => 'Very good', 'note' => 'Very good', 'decision' => 'Very good'];
	}
	if ($pct >= 80) {
		return ['letter' => 'D', 'descriptor' => 'Satisfactory', 'note' => 'Satisfactory', 'decision' => 'Satisfactory'];
	}
	if ($pct >= 70) {
		return ['letter' => 'F', 'descriptor' => 'Below Expectation', 'note' => 'Revise more', 'decision' => 'Needs serious revision'];
	}
	if ($pct >= 60) {
		return ['letter' => 'F', 'descriptor' => 'Below Expectation', 'note' => 'Review more', 'decision' => 'Needs serious revision'];
	}
	if ($pct >= 50) {
		return ['letter' => 'F', 'descriptor' => 'Below Expectation', 'note' => 'Ask teacher', 'decision' => 'Needs serious revision'];
	}
	return ['letter' => 'S', 'descriptor' => 'Below Average', 'note' => 'Ask teacher', 'decision' => 'Below average'];
};
$score100 = static function (array $core) use ($periodic): ?float {
	$full = (float) ($core['marks'] ?? 0);
	if ($full <= 0) {
		return null;
	}
	$result = is_array($core['result'] ?? null) ? $core['result'] : [];
	$mid = $result['marks'] ?? null;
	$exam = $result['exam_marks'] ?? null;
	$mid = ($mid === null || $mid === '') ? null : (float) $mid;
	$exam = ($exam === null || $exam === '') ? null : (float) $exam;
	if ($periodic || $exam === null) {
		if ($mid === null) {
			return null;
		}
		return ($mid * 100) / $full;
	}
	if ($mid === null) {
		return ($exam * 100) / $full;
	}
	return (($mid + $exam) * 100) / ($full * 2);
};
$wrap = static function (string $text, int $maxChars): array {
	$text = trim((string) preg_replace('/\s+/u', ' ', $text));
	if ($text === '') {
		return [''];
	}
	$words = preg_split('/\s+/u', $text) ?: [];
	$lines = [];
	$cur = '';
	foreach ($words as $word) {
		$try = $cur === '' ? $word : ($cur . ' ' . $word);
		if ($cur !== '' && mb_strlen($try) > $maxChars) {
			$lines[] = $cur;
			$cur = $word;
			continue;
		}
		$cur = $try;
	}
	if ($cur !== '') {
		$lines[] = $cur;
	}
	return $lines === [] ? [''] : $lines;
};

$studentReg = isset($_GET['student']) ? $_GET['student'] : false;
$cards = [];
if (!isset($students) || count($students) === 0) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
$classRanks = [];
if (isset($students)) {
	$totals = [];
	foreach ($students as $rankStudent) {
		if (!isset($rankStudent['id'])) {
			continue;
		}
		$sum = 0.0;
		$n = 0;
		foreach ($rankStudent['courses'] ?? [] as $core) {
			$score = $score100($core);
			if ($score === null) {
				continue;
			}
			$sum += $score;
			$n++;
		}
		$totals[(int) $rankStudent['id']] = $n > 0 ? $sum : null;
	}
	$ordered = $totals;
	arsort($ordered, SORT_NUMERIC);
	$place = 0;
	$seen = 0;
	$prev = null;
	foreach ($ordered as $sid => $sum) {
		$seen++;
		if ($sum === null) {
			$classRanks[$sid] = '';
			continue;
		}
		if ($prev === null || abs($sum - $prev) > 0.001) {
			$place = $seen;
			$prev = $sum;
		}
		$classRanks[$sid] = $place;
	}
}

foreach ($students ?? [] as $student) {
	if (!isset($student['id'])) {
		break;
	}
	if ($studentReg !== false && (string) $student['id'] !== (string) $studentReg) {
		continue;
	}
	$name = trim((string) ($student['fname'] ?? '') . ' ' . (string) ($student['lname'] ?? ''));
	$levelName = trim((string) ($student['level_name'] ?? ''));
	$classTitle = trim((string) ($student['title'] ?? ''));
	$deptCode = trim((string) ($student['code'] ?? ''));
	$deptName = trim((string) ($student['department_name'] ?? ''));
	$facTitle = trim((string) ($student['fac_title'] ?? ''));
	$classLabel = trim($levelName . ' ' . ($deptCode !== '' ? $deptCode : $classTitle));
	$program = $deptName !== '' ? $deptName : $facTitle;
	if ($anp && (strcasecmp($program, 'Nursing ANP') === 0 || strcasecmp($program, 'ANP') === 0 || $program === '')) {
		$program = 'Associated Nursing Program';
	}
	$sid = (int) $student['id'];
	$classPos = $classRanks[$sid] ?? '';
	$say = $remarks[$sid] ?? [];
	$classComment = trim((string) ($say['class_teacher'] ?? ''));
	$headComment = trim((string) ($say['head_teacher'] ?? ''));

	$rows = [];
	$sumScore = 0.0;
	$sumFull = 0.0;
	$scored = 0;
	$best = null;
	$weak = null;
	foreach ($student['courses'] ?? [] as $core) {
		$score = $score100($core);
		$meta = $bandOf($score);
		$title = trim((string) ($core['title'] ?? ''));
		$rows[] = [
			'title' => $title,
			'score' => $score,
			'meta' => $meta,
			'initials' => (string) ($initials[(int) ($core['id'] ?? 0)] ?? ''),
		];
		$sumFull += 100;
		if ($score !== null) {
			$sumScore += $score;
			$scored++;
			if ($best === null || $score > $best['score']) {
				$best = ['title' => $title, 'score' => $score];
			}
			if ($weak === null || $score < $weak['score']) {
				$weak = ['title' => $title, 'score' => $score];
			}
		}
	}
	$average = $scored > 0 ? ($sumScore / $scored) : null;
	$overall = $bandOf($average);
	$called = $name !== '' ? $name : 'this learner';
	if ($average === null) {
		$inspiration = 'God bless you, ' . $called . '.';
	} elseif ($average >= 80) {
		$inspiration = 'God bless you, ' . $called . '. Hold the standard'
			. ($best ? ' in ' . $best['title'] : '')
			. ($weak && $best && $weak['title'] !== $best['title'] ? ', and keep ' . $weak['title'] . ' close' : '')
			. '.';
	} elseif ($average >= 50) {
		$inspiration = 'God bless you, ' . $called . '. Your result can improve; take revision seriously'
			. ($weak ? ' and ask questions in ' . $weak['title'] : '')
			. '.';
	} else {
		$inspiration = 'God bless you, ' . $called . '. Start again'
			. ($weak ? ' with ' . $weak['title'] : '')
			. ', ask your teachers, and prepare with courage.';
	}

	$rowCount = max(1, count($rows));
	$rowMm = $rowCount > 14 ? 4.4 : ($rowCount > 10 ? 5.0 : 5.8);
	ob_start();
	?>
	<table class="ws-head">
		<tr>
			<td style="width:22mm;border:0;vertical-align:middle;">
				<?php if ($crestSrc !== ''): ?><img class="ws-crest" src="<?= esc($crestSrc); ?>" alt=""><?php endif; ?>
			</td>
			<td style="border:0;text-align:center;vertical-align:middle;">
				<div class="ws-republic">REPUBLIC OF RWANDA<br>MINISTRY OF EDUCATION</div>
				<div class="ws-school"><?= esc($schoolName); ?></div>
				<div class="ws-moto"><?= esc($boxLine); ?><br>School Motto: <?= esc($moto); ?></div>
				<div class="ws-title"><?= esc($cardTitle); ?></div>
				<?php if ($subtitle !== ''): ?><div class="ws-sub"><?= esc($subtitle); ?></div><?php endif; ?>
			</td>
			<td style="width:22mm;border:0;vertical-align:middle;text-align:right;">
				<?php if ($logoSrc !== ''): ?><img class="ws-logo" src="<?= esc($logoSrc); ?>" alt="" style="margin-left:auto;"><?php endif; ?>
			</td>
		</tr>
	</table>
	<table class="ws-id">
		<tr>
			<td class="k">Student Name</td>
			<td class="v" style="width:48mm;"><?= esc($name); ?></td>
			<td class="k">Class</td>
			<td class="v"><?= esc($classLabel); ?></td>
			<td class="k">Academic Year</td>
			<td class="v"><?= esc($yearLabel); ?></td>
		</tr>
		<tr>
			<td class="k">Term</td>
			<td class="v"><?= esc((string) $termNo); ?></td>
			<td class="k">Program</td>
			<td class="v"><?= esc($program); ?></td>
			<td class="k">Class position</td>
			<td class="v"><?= esc((string) $classPos); ?></td>
		</tr>
		<tr>
			<td class="k">Report</td>
			<td class="v" colspan="5"><?= esc($reportKind); ?></td>
		</tr>
	</table>
	<table class="ws-grid">
		<thead>
			<tr>
				<th style="width:52mm;">Subject</th>
				<th style="width:18mm;">Full Marks</th>
				<th style="width:16mm;">Score</th>
				<th style="width:34mm;">Descriptor</th>
				<th style="width:46mm;">Teacher Comment</th>
				<th style="width:20mm;">Initials</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($rows as $i => $row): ?>
			<tr class="<?= $i % 2 === 1 ? 'alt' : ''; ?>" style="height:<?= number_format($rowMm, 2, '.', ''); ?>mm;">
				<td class="ws-name"><?= esc($row['title']); ?></td>
				<td class="ws-ctr">100</td>
				<td class="ws-ctr"><?= $row['score'] === null ? '' : esc($fmt($row['score'])); ?></td>
				<td class="ws-ctr"><?= esc($row['meta']['descriptor']); ?></td>
				<td class="ws-ctr"><?= esc($row['meta']['note']); ?></td>
				<td class="ws-ctr"><?= esc($row['initials']); ?></td>
			</tr>
		<?php endforeach; ?>
			<tr class="ws-total">
				<td>TOTAL (<?= (int) $sumFull; ?>)</td>
				<td class="ws-ctr"><?= $sumFull > 0 ? (int) $sumFull : ''; ?></td>
				<td class="ws-ctr"><?= $scored > 0 ? esc($fmt($sumScore)) : ''; ?></td>
				<td class="ws-ctr" colspan="3">
					Average: <?= $average === null ? '' : esc($fmt($average) . '%'); ?>
					&nbsp; Grade: <?= esc($overall['letter']); ?>
					&nbsp; Decision: <?= esc($overall['decision']); ?>
				</td>
			</tr>
		</tbody>
	</table>
	<table class="ws-scale">
		<tr>
			<td style="background:#1d4ed8;">A: 95-100<br>Excellent</td>
			<td style="background:#2563eb;">B: 86-94<br>Very Good</td>
			<td style="background:#ca8a04;color:#111;">D: 80-84<br>Satisfactory</td>
			<td style="background:#ea580c;">F: 50-79<br>Below Expectation, can improve</td>
			<td style="background:#dc2626;">S: 0-49<br>Below Average</td>
		</tr>
	</table>
	<?php
	$commentRow = static function (string $label, string $text) use ($wrap) {
		$lines = $wrap($text, 62);
		echo '<div class="ws-comment"><b>' . esc($label) . '</b></div>';
		$last = count($lines) - 1;
		foreach ($lines as $i => $line) {
			echo '<table style="width:186mm;border-collapse:collapse;table-layout:fixed;"><tr>';
			echo '<td style="width:146mm;border:0;height:4.6mm;line-height:4.6mm;vertical-align:bottom;padding:0;">';
			if ($line === '') {
				echo '<span style="color:#94a3b8;">' . str_repeat('.', 78) . '</span>';
			} else {
				echo '<span class="ws-on">' . esc($line) . '</span>';
			}
			echo '</td>';
			echo '<td style="width:40mm;border:0;height:4.6mm;font-size:8pt;font-weight:700;vertical-align:bottom;">';
			echo $i === $last ? ('Sign: ' . str_repeat('.', 12)) : '';
			echo '</td></tr></table>';
		}
	};
	$commentRow("Class teacher's comment", $classComment);
	$commentRow("Head teacher's comment", $headComment);
	$commentRow('Candidate inspiration', $inspiration);
	?>
	<table class="ws-sign">
		<tr>
			<td style="width:70mm;">Class teacher: <?= esc($classTeacher); ?></td>
			<td style="width:52mm;">Tel: <?= esc($classPhone); ?></td>
			<td>Signature: ............</td>
		</tr>
		<tr>
			<td>Head teacher: <?= esc($headTeacher); ?></td>
			<td>Tel: <?= esc($headPhone); ?></td>
			<td>Signature: ............</td>
		</tr>
		<tr>
			<td colspan="3">Parent/Guardian's comment: <?= str_repeat('.', 70); ?></td>
		</tr>
	</table>
	<div class="ws-note">This report card is valid only when signed by the Head Teacher.</div>
	<?php
	$cards[] = [
		'html' => ob_get_clean(),
		'pos' => $classPos,
		'name' => $name,
	];
}
if ($cards !== []) {
	sort_report_cards_by_position($cards);
	if (!empty($pdf)) {
		$firstSheet = true;
		foreach ($cards as $card) {
			if (!$firstSheet) {
				echo '<!--REPORT_PAGE-->';
			}
			$firstSheet = false;
			echo '<table style="width:186mm;border-collapse:collapse;"><tr><td class="ws-card" style="width:186mm;vertical-align:top;">' . $card['html'] . '</td></tr></table>';
		}
	} else {
	echo '<div class="ws-sheet">';
	foreach ($cards as $card) {
		$page = '<table style="width:186mm;border-collapse:collapse;"><tr><td class="ws-card" style="width:186mm;vertical-align:top;">' . $card['html'] . '</td></tr></table>';
		echo '<div class="ws-fit"><div class="ws-paper">' . $page . '</div></div>';
	}
	echo '</div>';
	}
	if (empty($pdf)) {
		echo '<script>(function(){function fit(){document.querySelectorAll(".ws-fit").forEach(function(box){var paper=box.querySelector(".ws-paper");if(!paper){return;}paper.style.transform="none";var avail=box.clientWidth||paper.offsetWidth;var scale=Math.min(1,avail/paper.offsetWidth);paper.style.transformOrigin="top left";paper.style.transform="scale("+scale+")";box.style.height=(paper.offsetHeight*scale)+"px";});}fit();window.addEventListener("resize",fit);})();</script>';
	}
}
