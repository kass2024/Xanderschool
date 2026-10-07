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
	.wp-sheet { background: <?= !empty($pdf) ? '#fff' : '#eef1f4'; ?>; }
	.wp-fit { width: 100%; overflow: hidden; }
	.wp-paper {
		width: 297mm;
		height: 210mm;
		box-sizing: border-box;
		background: #fff;
		padding: 12mm 13.7mm 8mm 20.2mm;
	}
	.wp-card { width: 125.3mm; vertical-align: top; }
	.wp-id, .wp-grid { border-collapse: collapse; width: 125.3mm; table-layout: fixed; }
	.wp-id td {
		border: 0;
		padding: 0;
		vertical-align: bottom;
		color: #231f20;
		white-space: nowrap;
		overflow: hidden;
		height: 5.4mm;
		line-height: 5.4mm;
	}
	.wp-logo { width: 14mm; height: 16mm; display: block; }
	.wp-id .lab {
		font-family: <?= !empty($pdf) ? 'nurserygothic' : '"Century Gothic", CenturyGothic, nurserygothic, sans-serif'; ?>;
		font-weight: 700;
		font-size: 10.5pt;
		white-space: nowrap;
		padding-right: 1mm;
	}
	.wp-id .val {
		border-bottom: 0.9pt solid #231f20;
		font-family: <?= !empty($pdf) ? 'nurserygothic' : '"Century Gothic", CenturyGothic, nurserygothic, sans-serif'; ?>;
		font-weight: 700;
		font-size: 10pt;
		padding: 0 1mm 0.3mm;
	}
	.wp-grid { margin-top: 2.2mm; }
	.wp-grid td, .wp-grid th {
		border: 0.7pt solid #231f20;
		padding: 0 0.6mm;
		color: #231f20;
		font-weight: 700;
		vertical-align: middle;
		line-height: 1.05;
	}
	.wp-grid { border: 1.6pt solid #231f20; }
	.wp-sec {
		background: #1487bc;
		text-align: center;
		font-family: <?= !empty($pdf) ? 'nurserytimes, nurserygothic' : '"Times New Roman", Times, serif'; ?>;
		font-size: 11pt;
		height: 5.6mm;
	}
	.wp-head {
		background: #c2b59b;
		text-align: center;
		font-family: <?= !empty($pdf) ? 'nurserytimes, nurserygothic' : '"Times New Roman", Times, serif'; ?>;
		font-size: 8pt;
		height: 5.2mm;
	}
	.wp-sub, .wp-num, .wp-ctr, .wp-total td {
		font-family: <?= !empty($pdf) ? 'nurserygothic' : '"Century Gothic", CenturyGothic, nurserygothic, sans-serif'; ?>;
		font-size: 8.5pt;
		height: 4.8mm;
	}
	.wp-sub { text-transform: uppercase; padding-left: 1mm; }
	.wp-num, .wp-ctr { text-align: center; }
	.wp-total td { background: #c2b59b; height: 5.2mm; }
	.wp-foot {
		font-family: <?= !empty($pdf) ? 'nurserytimes, nurserygothic' : '"Times New Roman", Times, serif'; ?>;
		font-size: 10pt;
		height: 5.4mm;
		border-left: 0 !important;
		border-right: 0 !important;
		padding-left: 1.2mm !important;
		white-space: nowrap;
	}
<?php if (empty($pdf)): ?>
	@media print {
		.app-sidebar-wrapper, .app-sidebar, .app-sidebar-overlay,
		.header-mobile-wrapper, .app-header, .app-footer, .app-page-title,
		.ui-theme-settings, .fixed-header { display: none !important; }
		.app-container, .app-main, .app-main__outer, .app-main__inner {
			margin: 0 !important; padding: 0 !important; width: auto !important; background: #fff !important;
		}
		.wp-sheet { background: #fff; }
		.wp-fit { height: auto !important; overflow: visible !important; }
		.wp-paper { transform: none !important; margin: 0; page-break-after: always; }
		.wp-fit:last-child .wp-paper { page-break-after: auto; }
	}
	@page { size: 297mm 210mm; margin: 12mm 13.7mm 8mm 20.2mm; }
<?php endif; ?>
</style>
<?php
$grades = $grades ?? [];
$courseInitials = $primary_course_initials ?? [];
$termNo = (int) ($term ?? 0);
$termLabel = (string) \App\Controllers\Home::TermToStr($termNo);
$yearLabel = (string) ($academic_year_title ?? '');
$discMax = (float) ($discipline_max ?? 0);
$logoSrc = '';
if (!empty($school_logo)) {
	$logoFile = FCPATH . 'assets/images/logo/' . $school_logo;
	if (!empty($pdf) && is_file($logoFile)) {
		$logoSrc = str_replace('\\', '/', $logoFile);
	} else {
		$logoSrc = base_url('assets/images/logo/' . $school_logo);
	}
}
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
$bucketFor = static function (array $core): string {
	$category = strtolower(trim((string) ($core['category'] ?? '')));
	$title = strtolower(trim((string) ($core['title'] ?? '')));
	if (preg_match('/co-?\s*curricul|extra-?\s*curricul/', $category . ' ' . $title)) {
		return 'cocu';
	}
	if (preg_match('/non[-\s]?examin/', $category)) {
		return 'non';
	}
	if (preg_match('/physical education|bible|creative art|crea art|art\s*&\s*craft/', $title)
		&& !preg_match('/examin/', $category)) {
		return 'cocu';
	}
	return 'exam';
};
$isBehaviour = static function (array $core): bool {
	$code = strtoupper((string) ($core['code'] ?? ''));
	$title = strtolower((string) ($core['title'] ?? ''));
	return $code === 'P4-BEH' || strpos($title, 'behav') !== false;
};
$num = static function ($value) use ($fmt) {
	if ($value === null || $value === '') {
		return '';
	}
	return $fmt($value);
};
$periodic = !empty($primary_periodic);
$cols = $periodic
	? [42.0, 16.0, 16.0, 14.0, 22.0, 15.3]
	: [20.25, 8.61, 10.83, 9.52, 9.42, 9.84, 8.71, 11.43, 17.46, 19.26];
$span = $periodic ? 6 : 10;
$periodicRank = [];
if ($periodic && isset($students)) {
	$rankTotals = [];
	foreach ($students as $rankStudent) {
		if (!isset($rankStudent['id'])) {
			continue;
		}
		$sum = 0.0;
		$has = false;
		foreach ($rankStudent['courses'] ?? [] as $rankCore) {
			if ($isBehaviour($rankCore)) {
				continue;
			}
			$mark = $rankCore['result']['marks'] ?? null;
			if ($mark !== null && $mark !== '') {
				$sum += (float) $mark;
				$has = true;
			}
		}
		$rankTotals[(int) $rankStudent['id']] = $has ? $sum : null;
	}
	$ordered = $rankTotals;
	arsort($ordered, SORT_NUMERIC);
	$place = 0;
	$seen = 0;
	$prev = null;
	foreach ($ordered as $sid => $sum) {
		$seen++;
		if ($sum === null) {
			$periodicRank[$sid] = '';
			continue;
		}
		if ($prev === null || abs($sum - $prev) > 0.001) {
			$place = $seen;
			$prev = $sum;
		}
		$periodicRank[$sid] = $place;
	}
}
$col = static function (int $i) use ($cols) {
	return 'width:' . $cols[$i] . 'mm;';
};
$h = static function (string $mm) {
	return 'height:' . $mm . 'mm;line-height:' . $mm . 'mm;';
};

$studentReg = isset($_GET['student']) ? $_GET['student'] : false;
$cards = [];
if (!isset($students) || count($students) === 0) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
foreach ($students ?? [] as $student) {
	if (!isset($student['id'])) {
		break;
	}
	if ($studentReg !== false && (string) $student['id'] !== (string) $studentReg) {
		continue;
	}
	$groups = ['exam' => [], 'non' => [], 'cocu' => []];
	foreach ($student['courses'] ?? [] as $core) {
		if ($isBehaviour($core)) {
			continue;
		}
		$groups[$bucketFor($core)][] = $core;
	}
	$scoreOf = static function (array $core) {
		$result = $core['result'] ?? [];
		$mid = $result['marks'] ?? null;
		$ex = $result['exam_marks'] ?? null;
		$mid = ($mid === null || $mid === '') ? null : (float) $mid;
		$ex = ($ex === null || $ex === '') ? null : (float) $ex;
		$tot = ($mid === null && $ex === null) ? null : (float) $mid + (float) $ex;
		return [$mid, $ex, $tot];
	};
	ob_start();
	$name = trim((string) ($student['fname'] ?? '') . ' ' . (string) ($student['lname'] ?? ''));
	$classLabel = trim((string) ($student['level_name'] ?? '') . ' ' . (string) ($student['title'] ?? ''));
	$streamLabel = trim((string) ($student['code'] ?? ''));
	if ($streamLabel === '') {
		$streamLabel = trim((string) ($student['department_name'] ?? ''));
	}
	$fitPt = static function (string $text, float $widthMm, float $maxPt = 10.3): string {
		$len = max(1, mb_strlen($text));
		$pt = min($maxPt, ($widthMm / $len) / 0.20);
		return number_format(max(7.2, $pt), 2, '.', '');
	};
	$namePt = $fitPt($name, 46);
	$yearPt = $fitPt($yearLabel, 22);
	$termPt = $fitPt($termLabel, 40);
	$streamPt = $fitPt($streamLabel, 28);
	$classPt = $fitPt($classLabel, 78);
	$idCell = 'height:5.4mm;line-height:5.4mm;white-space:nowrap;overflow:hidden;vertical-align:bottom;';
	?>
	<table class="wp-id">
		<tr>
			<td rowspan="3" style="width:16mm;vertical-align:middle;height:16.2mm;line-height:normal;white-space:normal;">
				<?php if ($logoSrc !== ''): ?>
					<img class="wp-logo" src="<?= esc($logoSrc); ?>" alt="">
				<?php endif; ?>
			</td>
			<td class="lab" style="width:18mm;<?= $idCell; ?>">Names:</td>
			<td class="val" style="width:48mm;<?= $idCell; ?>font-size:<?= $namePt; ?>pt;"><?= esc($name); ?></td>
			<td class="lab" style="width:28mm;<?= $idCell; ?>">Academic Year</td>
			<td class="val" style="<?= $idCell; ?>font-size:<?= $yearPt; ?>pt;"><?= esc($yearLabel); ?></td>
		</tr>
		<tr>
			<td class="lab" style="<?= $idCell; ?>">Term:</td>
			<td class="val" style="<?= $idCell; ?>font-size:<?= $termPt; ?>pt;"><?= esc($termLabel); ?></td>
			<td class="lab" style="<?= $idCell; ?>">Stream:</td>
			<td class="val" style="<?= $idCell; ?>font-size:<?= $streamPt; ?>pt;"><?= esc($streamLabel); ?></td>
		</tr>
		<tr>
			<td class="lab" style="<?= $idCell; ?>">Class:</td>
			<td class="val" colspan="3" style="<?= $idCell; ?>font-size:<?= $classPt; ?>pt;"><?= esc($classLabel); ?></td>
		</tr>
	</table>
	<table class="wp-grid">
		<?php
		$markHead = function () use ($col, $h, $periodic) {
			if ($periodic) {
				echo '<tr>';
				echo '<td class="wp-head" style="' . $col(0) . $h('6.4') . '"></td>';
				echo '<td class="wp-head" style="' . $col(1) . '">Full Marks</td>';
				echo '<td class="wp-head" style="' . $col(2) . '">Score</td>';
				echo '<td class="wp-head" style="' . $col(3) . '">Grade</td>';
				echo '<td class="wp-head" style="' . $col(4) . '">Comment</td>';
				echo '<td class="wp-head" style="' . $col(5) . '">INITIALS</td>';
				echo '</tr>';
				return;
			}
			echo '<tr>';
			echo '<td class="wp-head" style="' . $col(0) . $h('6.4') . '"></td>';
			echo '<td class="wp-head" colspan="3">Maximum<br>per term</td>';
			echo '<td class="wp-head" colspan="3">SCORE</td>';
			echo '<td class="wp-head" rowspan="2" style="' . $col(7) . '">Grade</td>';
			echo '<td class="wp-head" rowspan="2" style="' . $col(8) . '">Comment</td>';
			echo '<td class="wp-head" rowspan="2" style="' . $col(9) . '">INITIALS</td>';
			echo '</tr><tr>';
			echo '<td class="wp-head" style="' . $h('4.6') . '"></td>';
			foreach (['Mid', 'Ex.', 'Tot', 'Mid', 'Ex.', 'Tot'] as $i => $label) {
				echo '<td class="wp-head" style="' . $col($i + 1) . '">' . $label . '</td>';
			}
			echo '</tr>';
		};
		$printSection = function (string $title, array $rows, bool $withHead) use ($col, $h, $fmt, $num, $scoreOf, $mentionFor, $courseInitials, $markHead, $periodic, $span) {
			echo '<tr><td class="wp-sec" colspan="' . $span . '" style="' . $h('5.6') . '">' . esc($title) . '</td></tr>';
			if ($withHead) {
				$markHead();
			}
			if ($periodic) {
				$maxFull = 0.0;
				$scoreSum = 0.0;
				$anyScore = false;
				foreach ($rows as $core) {
					$full = (float) ($core['marks'] ?? 0);
					$raw = $core['result']['marks'] ?? null;
					$score = ($raw === null || $raw === '') ? null : (float) $raw;
					$maxFull += $full;
					if ($score !== null) {
						$scoreSum += $score;
						$anyScore = true;
					}
					$pct = ($full > 0 && $score !== null) ? ($score * 100 / $full) : null;
					$cid = (int) ($core['id'] ?? 0);
					echo '<tr>';
					echo '<td class="wp-sub" style="' . $col(0) . $h('4.8') . '">' . esc((string) ($core['title'] ?? '')) . '</td>';
					echo '<td class="wp-num" style="' . $col(1) . '">' . $fmt($full) . '</td>';
					echo '<td class="wp-num" style="' . $col(2) . '">' . $num($score) . '</td>';
					echo '<td class="wp-ctr" style="' . $col(3) . '"></td>';
					echo '<td class="wp-ctr" style="' . $col(4) . '">' . esc($mentionFor($pct)) . '</td>';
					echo '<td class="wp-ctr" style="' . $col(5) . '">' . esc((string) ($courseInitials[$cid] ?? '')) . '</td>';
					echo '</tr>';
				}
				echo '<tr class="wp-total">';
				echo '<td class="wp-sub" style="' . $h('5.2') . '">TOTAL</td>';
				echo '<td class="wp-num">' . ($rows === [] ? '' : $fmt($maxFull)) . '</td>';
				echo '<td class="wp-num">' . ($anyScore ? $fmt($scoreSum) : '') . '</td>';
				echo '<td></td><td></td><td></td>';
				echo '</tr>';
				return [$maxFull, $anyScore ? $scoreSum : null];
			}
			$maxMid = $maxEx = $maxTot = 0.0;
			$scoreMid = $scoreEx = $scoreTot = 0.0;
			$anyScore = false;
			foreach ($rows as $core) {
				$full = (float) ($core['marks'] ?? 0);
				[$mid, $ex, $tot] = $scoreOf($core);
				$maxMid += $full;
				$maxEx += $full;
				$maxTot += $full * 2;
				if ($mid !== null) {
					$scoreMid += $mid;
					$anyScore = true;
				}
				if ($ex !== null) {
					$scoreEx += $ex;
					$anyScore = true;
				}
				if ($tot !== null) {
					$scoreTot += $tot;
				}
				$pct = ($full > 0 && $tot !== null) ? ($tot * 100 / ($full * 2)) : null;
				$cid = (int) ($core['id'] ?? 0);
				echo '<tr>';
				echo '<td class="wp-sub" style="' . $col(0) . $h('4.8') . '">' . esc((string) ($core['title'] ?? '')) . '</td>';
				echo '<td class="wp-num" style="' . $col(1) . '">' . $fmt($full) . '</td>';
				echo '<td class="wp-num" style="' . $col(2) . '">' . $fmt($full) . '</td>';
				echo '<td class="wp-num" style="' . $col(3) . '">' . $fmt($full * 2) . '</td>';
				echo '<td class="wp-num" style="' . $col(4) . '">' . $num($mid) . '</td>';
				echo '<td class="wp-num" style="' . $col(5) . '">' . $num($ex) . '</td>';
				echo '<td class="wp-num" style="' . $col(6) . '">' . $num($tot) . '</td>';
				echo '<td class="wp-ctr" style="' . $col(7) . '"></td>';
				echo '<td class="wp-ctr" style="' . $col(8) . '">' . esc($mentionFor($pct)) . '</td>';
				echo '<td class="wp-ctr" style="' . $col(9) . '">' . esc((string) ($courseInitials[$cid] ?? '')) . '</td>';
				echo '</tr>';
			}
			echo '<tr class="wp-total">';
			echo '<td class="wp-sub" style="' . $h('5.2') . '">TOTAL</td>';
			echo '<td class="wp-num">' . ($rows === [] ? '' : $fmt($maxMid)) . '</td>';
			echo '<td class="wp-num">' . ($rows === [] ? '' : $fmt($maxEx)) . '</td>';
			echo '<td class="wp-num">' . ($rows === [] ? '' : $fmt($maxTot)) . '</td>';
			echo '<td class="wp-num">' . ($anyScore ? $fmt($scoreMid) : '') . '</td>';
			echo '<td class="wp-num">' . ($anyScore ? $fmt($scoreEx) : '') . '</td>';
			echo '<td class="wp-num">' . ($anyScore ? $fmt($scoreTot) : '') . '</td>';
			echo '<td></td><td></td><td></td>';
			echo '</tr>';
			return [$maxTot, $anyScore ? $scoreTot : null];
		};
		[$examMax, $examScore] = $printSection('Core subjects/ Examinable subjects', $groups['exam'], true);
		[$nonMax, $nonScore] = $printSection('Non – Examinable Subjects:', $groups['non'], false);
		[$cocuMax, $cocuScore] = $printSection('Co-curricula activities', $groups['cocu'], false);
		$allMax = $examMax + $nonMax + $cocuMax;
		$allScore = 0.0;
		$scored = false;
		foreach ([$examScore, $nonScore, $cocuScore] as $part) {
			if ($part !== null) {
				$allScore += $part;
				$scored = true;
			}
		}
		$pctText = ($scored && $allMax > 0) ? $fmt($allScore * 100 / $allMax) . '%' : '';
		if ($periodic) {
			$position = $periodicRank[(int) $student['id']] ?? '';
			$outOf = 0;
			foreach ($periodicRank as $rankPlace) {
				if ($rankPlace !== '') {
					$outOf++;
				}
			}
		} else {
			$termKey = termToStr($termNo);
			$position = $my_position[$termKey]['total'][$student['id']] ?? '';
			$outOf = isset($my_position[$termKey]['total']) ? count($my_position[$termKey]['total']) : 0;
		}
		$positionText = ($position !== '' && $outOf > 0) ? $position . ' Out of ' . $outOf : '';
		$conductTot = null;
		if ($discMax > 0) {
			if ($periodic) {
				$deduct = (float) ($student['displine_marks'] ?? 0);
			} else {
				$deduct = (float) extractDisciplineMarks($student['displine_marks'] ?? '', $termNo);
			}
			$conductTot = max(0, $discMax - $deduct);
		}
		$half = $discMax > 0 ? $discMax / 2 : null;
		$rest = $span - 1;
		?>
		<tr>
			<td class="wp-sub" style="<?= $h('5.0'); ?>">Percentage</td>
			<td colspan="<?= $rest; ?>" class="wp-ctr"><?= esc($pctText); ?></td>
		</tr>
		<tr>
			<td class="wp-sub" style="<?= $h('5.0'); ?>">POSITION</td>
			<td colspan="<?= $rest; ?>" class="wp-ctr"><?= esc($positionText); ?></td>
		</tr>
		<tr>
			<td class="wp-sub" style="<?= $h('5.0'); ?>">CONDUCT</td>
			<?php if ($periodic): ?>
			<td class="wp-num"><?= $discMax > 0 ? $fmt($discMax) : ''; ?></td>
			<td class="wp-num"><?= $num($conductTot); ?></td>
			<td></td><td></td><td></td>
			<?php else: ?>
			<td class="wp-num"><?= $num($half); ?></td>
			<td class="wp-num"><?= $num($half); ?></td>
			<td class="wp-num"><?= $discMax > 0 ? $fmt($discMax) : ''; ?></td>
			<td class="wp-num"></td>
			<td class="wp-num"></td>
			<td class="wp-num"><?= $num($conductTot); ?></td>
			<td></td><td></td><td></td>
			<?php endif; ?>
		</tr>
		<tr>
			<td class="wp-sub" style="<?= $h('5.0'); ?>">DECISION</td>
			<td colspan="<?= $rest; ?>" class="wp-ctr"><?= esc((string) ($student['decision'] ?? '')); ?><?= trim((string) ($student['decision'] ?? '')) === '' ? str_repeat('.', 48) : ''; ?></td>
		</tr>
		<tr><td class="wp-foot" colspan="<?= $span; ?>" style="<?= $h('5.6'); ?>">Class teacher's comment:<?= str_repeat('.', 42); ?></td></tr>
		<tr><td class="wp-foot" colspan="<?= $span; ?>" style="<?= $h('5.6'); ?>"><?= str_repeat('.', 36); ?> Sign: <?= str_repeat('.', 16); ?></td></tr>
		<tr><td class="wp-foot" colspan="<?= $span; ?>" style="<?= $h('5.6'); ?>">Head teacher's Comment: <?= str_repeat('.', 38); ?></td></tr>
		<tr><td class="wp-foot" colspan="<?= $span; ?>" style="<?= $h('5.6'); ?>"><?= str_repeat('.', 38); ?> Sign: <?= str_repeat('.', 14); ?></td></tr>
		<?php if (!$periodic): ?>
		<tr><td class="wp-foot" colspan="<?= $span; ?>" style="<?= $h('5.6'); ?>">Next term begins on: <?= str_repeat('.', 14); ?> and ends on: <?= str_repeat('.', 16); ?></td></tr>
		<?php endif; ?>
	</table>
	<?php
	$cards[] = ob_get_clean();
}
if ($cards !== []) {
	echo '<div class="wp-sheet">';
	$pairs = array_chunk($cards, 2);
	foreach ($pairs as $i => $pair) {
		$right = $pair[1] ?? '';
		$pairTable = '<table style="width:263mm;border-collapse:collapse;"><tr>'
			. '<td class="wp-card" style="width:125.3mm;vertical-align:top;">' . $pair[0] . '</td>'
			. '<td style="width:12.4mm;border:0;">&nbsp;</td>'
			. '<td class="wp-card" style="width:125.3mm;vertical-align:top;">' . ($right !== '' ? $right : '&nbsp;') . '</td>'
			. '</tr></table>';
		if (!empty($pdf)) {
			if ($i > 0) {
				echo '<pagebreak />';
			}
			echo $pairTable;
			continue;
		}
		echo '<div class="wp-fit"><div class="wp-paper">' . $pairTable . '</div></div>';
	}
	echo '</div>';
	if (empty($pdf)) {
		echo '<script>(function(){function fit(){document.querySelectorAll(".wp-fit").forEach(function(box){var paper=box.querySelector(".wp-paper");if(!paper){return;}paper.style.transform="none";var avail=box.clientWidth||paper.offsetWidth;var scale=Math.min(1,avail/paper.offsetWidth);paper.style.transformOrigin="top left";paper.style.transform="scale("+scale+")";box.style.height=(paper.offsetHeight*scale)+"px";});}fit();window.addEventListener("resize",fit);})();</script>';
	}
}
