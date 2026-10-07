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
		padding: 12mm 13.7mm 13mm 20.2mm;
	}
	.wp-card { width: 125.3mm; vertical-align: top; }
	.wp-id, .wp-grid { border-collapse: collapse; width: 125.3mm; table-layout: fixed; }
	.wp-id td {
		border: 0;
		padding: 0 0.4mm;
		vertical-align: bottom;
		color: #231f20;
		white-space: normal;
		overflow: hidden;
	}
	.wp-logo { width: 14mm; height: 16mm; display: block; }
	.wp-id .lab {
		font-family: <?= !empty($pdf) ? 'nurserygothic' : '"Century Gothic", CenturyGothic, nurserygothic, sans-serif'; ?>;
		font-weight: 700;
		font-size: 9.5pt;
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
	}
	.wp-sub { text-transform: uppercase; padding-left: 1mm; white-space: normal; overflow: hidden; vertical-align: middle; }
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
	@page { size: 297mm 210mm; margin: 12mm 13.7mm 13mm 20.2mm; }
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
$scale = 1.0;
$h = static function (string $mm) use (&$scale) {
	$v = (float) $mm * $scale;
	return 'height:' . number_format($v, 2, '.', '') . 'mm;line-height:' . number_format($v, 2, '.', '') . 'mm;';
};
$splitWords = static function (array $words, int $lines): array {
	$n = count($words);
	if ($lines <= 1 || $n <= 1) {
		return [implode(' ', $words)];
	}
	$lines = min($lines, $n);
	$total = 0;
	foreach ($words as $word) {
		$total += mb_strlen($word);
	}
	$groups = [];
	$index = 0;
	for ($line = 0; $line < $lines; $line++) {
		$remainLines = $lines - $line;
		$remainWords = $n - $index;
		if ($remainLines <= 1) {
			$groups[] = implode(' ', array_slice($words, $index));
			break;
		}
		$target = $total / $lines;
		$take = 1;
		$len = mb_strlen($words[$index]);
		while ($index + $take < $n && ($remainWords - $take) > ($remainLines - 1) && $len < $target) {
			$take++;
			$len += mb_strlen($words[$index + $take - 1]);
		}
		$groups[] = implode(' ', array_slice($words, $index, $take));
		$index += $take;
	}
	return $groups;
};
$packText = static function (string $text, float $widthMm, int $maxLines, float $maxPt, float $minPt) use ($splitWords): array {
	$text = trim((string) preg_replace('/\s+/u', ' ', $text));
	$widthMm = max(8.0, $widthMm);
	if ($text === '') {
		return ['html' => '', 'pt' => $maxPt, 'lines' => 1];
	}
	$k = 0.23;
	$words = preg_split('/\s+/u', $text) ?: [$text];
	$lines = 1;
	$groups = [$text];
	$pt = ($widthMm * 0.96) / (max(1, mb_strlen($text)) * $k);
	if ($pt < $minPt && count($words) > 1) {
		$limit = min($maxLines, count($words));
		for ($try = 2; $try <= $limit; $try++) {
			$groups = $splitWords($words, $try);
			$longest = 1;
			foreach ($groups as $group) {
				$longest = max($longest, mb_strlen($group));
			}
			$pt = ($widthMm * 0.96) / ($longest * $k);
			$lines = $try;
			if ($pt >= $minPt) {
				break;
			}
		}
	}
	$pt = min($maxPt, max($minPt, $pt));
	$html = implode('<br>', array_map(static function ($line) {
		return esc($line);
	}, $groups));
	return ['html' => $html, 'pt' => $pt, 'lines' => $lines];
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
	$namePack = $packText($name, 46, 2, 10.0, 7.0);
	$yearPack = $packText($yearLabel, 18, 2, 10.0, 7.0);
	$termPack = $packText($termLabel, 46, 2, 10.5, 7.0);
	$streamPack = $packText($streamLabel, 22, 2, 10.5, 7.0);
	$classPack = $packText($classLabel, 94, 2, 10.5, 7.0);
	$idLine = 4.6;
	$nameLines = max($namePack['lines'], $yearPack['lines']);
	$termLines = max($termPack['lines'], $streamPack['lines']);
	$classLines = $classPack['lines'];
	$idRow = static function (int $lines, float $pt = 0) use ($idLine): string {
		$mm = $lines * $idLine;
		$font = $pt > 0 ? 'font-size:' . number_format($pt, 2, '.', '') . 'pt;' : '';
		return 'height:' . number_format($mm, 2, '.', '') . 'mm;line-height:' . number_format($idLine, 2, '.', '') . 'mm;vertical-align:bottom;overflow:hidden;' . $font;
	};
	$subWidth = max(10.0, $cols[0] - 2.2);
	$subjectUnits = 0.0;
	$visibleSections = 0;
	foreach (['exam', 'non', 'cocu'] as $bucket) {
		if ($groups[$bucket] === []) {
			continue;
		}
		$visibleSections++;
		foreach ($groups[$bucket] as $core) {
			$packed = $packText(mb_strtoupper((string) ($core['title'] ?? '')), $subWidth, 3, 8.5, 6.4);
			$subjectUnits += max(4.8, $packed['lines'] * 4.15);
		}
	}
	$headMm = $visibleSections > 0 ? ($periodic ? 6.4 : 11.0) : 0.0;
	$footLines = 4 + ($periodic ? 0 : 1);
	$natural = ($visibleSections * 5.6) + $headMm + $subjectUnits + ($visibleSections * 5.2) + (4 * 5.0) + ($footLines * 5.6);
	$extraId = max(0, (($nameLines + $termLines + $classLines) - 3) * $idLine);
	$tableTarget = 164.9 - $extraId;
	$scale = $natural > 0 ? ($tableTarget / $natural) : 1.0;
	$subjectCell = static function (string $title) use ($packText, $subWidth, $col, &$scale): string {
		$packed = $packText(mb_strtoupper($title), $subWidth, 3, 8.5, 6.4);
		$base = max(4.8, $packed['lines'] * 4.15);
		$mm = $base * $scale;
		$line = $mm / max(1, $packed['lines']);
		$pt = $packed['pt'];
		if ($scale < 1) {
			$pt = max(6.0, $pt * $scale);
		}
		$need = $pt * 0.38;
		if ($line < $need && $need > 0) {
			$pt = $line / 0.38;
		}
		return '<td class="wp-sub" style="' . $col(0) . 'height:' . number_format($mm, 2, '.', '') . 'mm;line-height:' . number_format($line, 2, '.', '') . 'mm;font-size:' . number_format($pt, 2, '.', '') . 'pt;">' . $packed['html'] . '</td>';
	};
	?>
	<table class="wp-id">
		<colgroup>
			<col style="width:13mm">
			<col style="width:14.5mm">
			<col style="width:48mm">
			<col style="width:30mm">
			<col style="width:19.8mm">
		</colgroup>
		<tr>
			<td rowspan="3" style="width:13mm;vertical-align:middle;white-space:normal;overflow:visible;height:<?= number_format(($nameLines + $termLines + $classLines) * $idLine, 2, '.', ''); ?>mm;line-height:normal;">
				<?php if ($logoSrc !== ''): ?>
					<img class="wp-logo" src="<?= esc($logoSrc); ?>" alt="">
				<?php endif; ?>
			</td>
			<td class="lab" style="width:14.5mm;<?= $idRow($nameLines); ?>">Names:</td>
			<td class="val" style="width:48mm;<?= $idRow($nameLines, $namePack['pt']); ?>"><?= $namePack['html']; ?></td>
			<td class="lab" style="width:30mm;font-size:9pt;<?= $idRow($nameLines); ?>">Academic Year</td>
			<td class="val" style="width:19.8mm;<?= $idRow($nameLines, $yearPack['pt']); ?>"><?= $yearPack['html']; ?></td>
		</tr>
		<tr>
			<td class="lab" style="<?= $idRow($termLines); ?>">Term:</td>
			<td class="val" style="<?= $idRow($termLines, $termPack['pt']); ?>"><?= $termPack['html']; ?></td>
			<td class="lab" style="<?= $idRow($termLines); ?>">Stream:</td>
			<td class="val" style="<?= $idRow($termLines, $streamPack['pt']); ?>"><?= $streamPack['html']; ?></td>
		</tr>
		<tr>
			<td class="lab" style="<?= $idRow($classLines); ?>">Class:</td>
			<td class="val" colspan="3" style="<?= $idRow($classLines, $classPack['pt']); ?>"><?= $classPack['html']; ?></td>
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
		$printSection = function (string $title, array $rows, bool $withHead) use ($col, $h, $fmt, $num, $scoreOf, $mentionFor, $courseInitials, $markHead, $periodic, $span, $subjectCell) {
			if ($rows === []) {
				return [0.0, null];
			}
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
					echo $subjectCell((string) ($core['title'] ?? ''));
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
				echo $subjectCell((string) ($core['title'] ?? ''));
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
		$wantHead = true;
		$openSection = function (string $title, array $rows) use (&$wantHead, $printSection) {
			$head = $wantHead && $rows !== [];
			if ($rows !== []) {
				$wantHead = false;
			}
			return $printSection($title, $rows, $head);
		};
		[$examMax, $examScore] = $openSection('Core subjects/ Examinable subjects', $groups['exam']);
		[$nonMax, $nonScore] = $openSection('Non – Examinable Subjects:', $groups['non']);
		[$cocuMax, $cocuScore] = $openSection('Co-curricula activities', $groups['cocu']);
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
