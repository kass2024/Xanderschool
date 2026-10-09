<?php
$sheets = $progress_students ?? [];
$yearLabel = trim((string) ($academic_year_title ?? ''));
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
$band = (string) ($secondary_band ?? 'o_level');
$heading = [
	'o_level' => 'O-LEVEL',
	'a_level' => 'A-LEVEL',
	'rtb' => 'RTB',
	'special' => 'ANP A-LEVEL',
][$band] ?? 'A-LEVEL';
$selectedTerm = (int) ($term ?? 1);
$annualSheet = $selectedTerm === 4;
$showTerms = $annualSheet ? [1, 2, 3] : [max(1, min(3, $selectedTerm))];
$roman = [1 => 'I', 2 => 'II', 3 => 'III'][$selectedTerm] ?? '';
$cardTitle = $heading . ' PROGRESSIVE SCHOOL REPORT · ' . ($annualSheet ? 'ANNUAL' : ('TERM ' . $roman));
$termHeads = [1 => '1<sup>st</sup> TERM', 2 => '2<sup>nd</sup> TERM', 3 => '3<sup>rd</sup> TERM'];
$paperW = $annualSheet ? '297mm' : '210mm';
$paperH = $annualSheet ? '210mm' : '297mm';
$cardW = $annualSheet ? '285mm' : '186mm';
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
$markText = static function ($n): string {
	if ($n === null || $n === '') {
		return '-';
	}
	$s = number_format((float) $n, 1, '.', '');
	return rtrim(rtrim($s, '0'), '.');
};
$pctText = static function ($n): string {
	if ($n === null || $n === '') {
		return '-';
	}
	return number_format((float) $n, 1) . '%';
};
$termTotal = static function ($cat, $exam) {
	if ($cat === null && $exam === null) {
		return null;
	}
	return (float) ($cat ?? 0) + (float) ($exam ?? 0);
};
?>
<style>
	.wsp-sheet { background: <?= !empty($pdf) ? '#fff' : '#eef1f4'; ?>; }
	.wsp-fit { width: 100%; overflow: hidden; }
	.wsp-paper {
		width: <?= $paperW; ?>;
		height: <?= $paperH; ?>;
		box-sizing: border-box;
		background: #fff;
		padding: <?= $annualSheet ? '5mm 6mm' : '8mm 12mm'; ?>;
		overflow: hidden;
	}
	.wsp-card { width: <?= $cardW; ?>; color: #111; }
	.wsp-head, .wsp-id, .wsp-table { border-collapse: collapse; width: <?= $cardW; ?>; table-layout: fixed; }
	.wsp-crest, .wsp-logo { width: 16mm; height: 16mm; display: block; }
	.wsp-republic { font-size: 8.5pt; font-weight: 700; letter-spacing: 0.3px; text-align: center; line-height: 1.15; }
	.wsp-school { font-size: 11pt; font-weight: 700; text-align: center; line-height: 1.15; margin-top: 0.4mm; }
	.wsp-moto { font-size: 8pt; text-align: center; line-height: 1.15; }
	.wsp-title { font-size: 10.5pt; font-weight: 700; text-align: center; margin-top: 1.2mm; }
	.wsp-id { margin-top: 1.6mm; }
	.wsp-id td { border: 0.6pt solid #1e3a5f; padding: 0.6mm 1.2mm; vertical-align: middle; }
	.wsp-id .k { font-size: 8pt; font-weight: 700; width: 34mm; background: #f4f7fb; }
	.wsp-id .v { font-size: 8.5pt; font-weight: 700; }
	.wsp-table { margin-top: 1.6mm; }
	.wsp-table th, .wsp-table td {
		border: 0.6pt solid #1e3a5f;
		padding: 0.7mm 0.6mm;
		text-align: center;
		font-size: <?= $annualSheet ? '7pt' : '8.5pt'; ?>;
		font-weight: 700;
		line-height: 1.15;
	}
	.wsp-table th { background: #1e3a5f; color: #fff; font-size: <?= $annualSheet ? '6.5pt' : '8pt'; ?>; }
	.wsp-table tr.alt td { background: #f3f7fb; }
	.wsp-sub { text-align: left; padding-left: 1.4mm; }
	.wsp-conduct td { background: #fff6e0; }
	.wsp-total td { background: #e8eef5; }
	.wsp-extra { margin-top: 1.6mm; width: <?= $cardW; ?>; border-collapse: collapse; }
	.wsp-extra td { border: 0.6pt solid #1e3a5f; vertical-align: top; font-size: <?= $annualSheet ? '7.5pt' : '8.5pt'; ?>; font-weight: 700; padding: 1.2mm 1.6mm; }
	.wsp-sign { height: <?= $annualSheet ? '7mm' : '9mm'; ?>; }
	.wsp-jury { margin: 0; padding-left: 4mm; }
	.wsp-jury li { list-style: disc; }
<?php if (empty($pdf)): ?>
	@media print {
		.app-sidebar-wrapper, .app-sidebar, .app-sidebar-overlay,
		.header-mobile-wrapper, .app-header, .app-footer, .app-page-title,
		.ui-theme-settings, .fixed-header { display: none !important; }
		.app-container, .app-main, .app-main__outer, .app-main__inner {
			margin: 0 !important; padding: 0 !important; width: auto !important; background: #fff !important;
		}
		.wsp-sheet { background: #fff; }
		.wsp-fit { height: auto !important; overflow: visible !important; }
		.wsp-paper { transform: none !important; margin: 0; page-break-after: always; }
		.wsp-fit:last-child .wsp-paper { page-break-after: auto; }
	}
	@page { size: A4 <?= $annualSheet ? 'landscape' : 'portrait'; ?>; margin: 0; }
<?php endif; ?>
</style>
<?php
if ($sheets === []) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
$studentReg = isset($_GET['student']) ? (string) $_GET['student'] : '';
$placeFor = static function (array $scores): array {
	$ranked = [];
	foreach ($scores as $sid => $score) {
		if ($score !== null) {
			$ranked[(int) $sid] = (float) $score;
		}
	}
	arsort($ranked, SORT_NUMERIC);
	$places = [];
	$place = 0;
	$seen = 0;
	$prev = null;
	foreach ($ranked as $sid => $score) {
		$seen++;
		if ($prev === null || abs($score - $prev) > 0.001) {
			$place = $seen;
			$prev = $score;
		}
		$places[(int) $sid] = $place;
	}
	return $places;
};
$scoreBag = [1 => [], 2 => [], 3 => [], 4 => []];
$classSize = 0;
foreach ($sheets as $rankStudent) {
	if (empty($rankStudent['id'])) {
		continue;
	}
	$classSize++;
	$sid = (int) $rankStudent['id'];
	$termGot = [1 => null, 2 => null, 3 => null];
	$annualGot = null;
	foreach ($rankStudent['progress_courses'] ?? [] as $line) {
		$terms = $line['terms'] ?? [];
		foreach ([1, 2, 3] as $colTerm) {
			$got = $termTotal($terms[$colTerm]['cat'] ?? null, $terms[$colTerm]['exam'] ?? null);
			if ($got === null) {
				continue;
			}
			$termGot[$colTerm] = ($termGot[$colTerm] ?? 0) + $got;
		}
		if (($line['annual_op'] ?? null) !== null) {
			$annualGot = ($annualGot ?? 0) + (float) $line['annual_op'];
		}
	}
	foreach ([1, 2, 3] as $colTerm) {
		$scoreBag[$colTerm][$sid] = $termGot[$colTerm];
	}
	$scoreBag[4][$sid] = $annualGot;
}
$places = [];
foreach ([1, 2, 3, 4] as $colTerm) {
	$places[$colTerm] = $placeFor($scoreBag[$colTerm]);
}
$placeText = static function (int $sid, int $colTerm) use ($places, $classSize): string {
	$place = $places[$colTerm][$sid] ?? null;
	if ($classSize < 1) {
		return '-';
	}
	return ($place === null ? '' : (string) $place) . ' / ' . $classSize;
};
$discMax = (float) ($discipline_max ?? 0);
$conductText = static function ($student, int $colTerm) use ($discMax, $markText): string {
	$max = $discMax;
	$penalty = (float) extractDisciplineMarks($student['displine_marks'] ?? '', $colTerm);
	if ($max <= 0) {
		return '-';
	}
	return $markText($max - $penalty) . ' / ' . $markText($max);
};
$cards = [];
foreach ($sheets as $student) {
	if (empty($student['id'])) {
		continue;
	}
	if ($studentReg !== '' && (string) $student['id'] !== $studentReg) {
		continue;
	}
	$sid = (int) $student['id'];
	$name = trim((string) ($student['fname'] ?? '') . ' ' . mb_strtoupper((string) ($student['lname'] ?? '')));
	$streamName = trim((string) ($report_stream_label ?? ''));
	$streamOlevel = $band === 'o_level' && $streamName !== '';
	$classLabel = $streamOlevel
		? $streamName
		: trim((string) ($student['level_name'] ?? '') . ' ' . (string) (($student['code'] ?? '') !== '' ? $student['code'] : ($student['title'] ?? '')));
	$progressClassId = (int) ($student['class'] ?? $student['class_id'] ?? 0);
	$progressSignFile = $streamOlevel
		? (string) ($report_class_signature ?? '')
		: (string) (($report_class_signatures ?? [])[$progressClassId] ?? ($report_class_signature ?? ''));
	$progressSignImg = report_signature_img($progressSignFile, !empty($pdf), $annualSheet ? 6.2 : 7.2, 36);
	$progressHeadImg = report_signature_img($report_head_signature ?? '', !empty($pdf), 8.0, 36);
	$lines = [];
	foreach ($student['progress_courses'] ?? [] as $line) {
		$offered = $line['offered'] ?? [];
		if (!$annualSheet && $offered !== [] && !in_array($showTerms[0], $offered, true)) {
			continue;
		}
		$lines[] = $line;
	}
	$sumMaxCat = 0.0;
	$sumMaxEx = 0.0;
	$sumTerm = [];
	foreach ($showTerms as $colTerm) {
		$sumTerm[$colTerm] = ['cat' => 0.0, 'exam' => 0.0, 'base' => 0.0, 'any' => false];
	}
	$sumPctGot = 0.0;
	$sumPctMax = 0.0;
	$sumAnnualMax = 0.0;
	$sumAnnualOp = 0.0;
	$annualAny = false;
	ob_start();
	?>
	<table class="wsp-head">
		<tr>
			<td style="width:22mm;border:0;vertical-align:middle;">
				<?php if ($crestSrc !== ''): ?><img class="wsp-crest" src="<?= esc($crestSrc); ?>" alt=""><?php endif; ?>
			</td>
			<td style="border:0;text-align:center;vertical-align:middle;">
				<div class="wsp-republic">REPUBLIC OF RWANDA<br>MINISTRY OF EDUCATION</div>
				<div class="wsp-school"><?= esc($schoolName); ?></div>
				<div class="wsp-moto"><?= esc($boxLine); ?><br>School Motto: <?= esc($moto); ?></div>
				<div class="wsp-title"><?= esc($cardTitle); ?></div>
			</td>
			<td style="width:22mm;border:0;vertical-align:middle;text-align:right;">
				<?php if ($logoSrc !== ''): ?><img class="wsp-logo" src="<?= esc($logoSrc); ?>" alt="" style="margin-left:auto;"><?php endif; ?>
			</td>
		</tr>
	</table>
	<table class="wsp-id">
		<tr>
			<td class="k">Student's name</td>
			<td class="v"><?= esc($name); ?></td>
			<td class="k">Student's number</td>
			<td class="v"><?= esc((string) ($student['regno'] ?? '')); ?></td>
		</tr>
		<tr>
			<td class="k">Class</td>
			<td class="v"><?= esc($classLabel); ?></td>
			<td class="k">School Year</td>
			<td class="v"><?= esc($yearLabel); ?></td>
		</tr>
	</table>
	<table class="wsp-table">
		<thead>
			<tr>
				<th rowspan="3" style="width:<?= $annualSheet ? '18%' : '34%'; ?>;">Subjects</th>
				<th colspan="3">MAX POINTS</th>
				<?php foreach ($showTerms as $colTerm): ?>
					<th colspan="3"><?= $termHeads[$colTerm]; ?></th>
				<?php endforeach; ?>
				<?php if ($annualSheet): ?>
					<th colspan="3">ANNUAL POINTS</th>
					<th colspan="2" rowspan="2">2<sup>nd</sup> SITTING</th>
				<?php else: ?>
					<th rowspan="3">%</th>
				<?php endif; ?>
			</tr>
			<tr>
				<th colspan="3"></th>
				<?php foreach ($showTerms as $colTerm): ?>
					<th colspan="3">O.P.</th>
				<?php endforeach; ?>
				<?php if ($annualSheet): ?>
					<th colspan="3">TOT</th>
				<?php endif; ?>
			</tr>
			<tr>
				<th>CAT</th><th>EX</th><th>TOT</th>
				<?php foreach ($showTerms as $colTerm): ?>
					<th>CAT</th><th>EX</th><th>TOT</th>
				<?php endforeach; ?>
				<?php if ($annualSheet): ?>
					<th>MAX</th><th>O.P.</th><th>%</th>
					<th>O.P.</th><th>%</th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($lines as $lineIndex => $line):
			$max = (float) ($line['max'] ?? 0);
			$sumMaxCat += $max;
			$sumMaxEx += $max;
			$terms = $line['terms'] ?? [];
			$termPct = null;
			if (!$annualSheet) {
				$one = $showTerms[0];
				$oneTot = $termTotal($terms[$one]['cat'] ?? null, $terms[$one]['exam'] ?? null);
				if ($oneTot !== null && $max > 0) {
					$termPct = $oneTot * 100 / ($max * 2);
					$sumPctGot += $oneTot;
					$sumPctMax += $max * 2;
				}
			} elseif ($line['annual_op'] !== null) {
				$annualAny = true;
				$sumAnnualMax += (float) ($line['annual_max'] ?? 0);
				$sumAnnualOp += (float) $line['annual_op'];
			}
			?>
			<tr class="<?= $lineIndex % 2 === 1 ? 'alt' : ''; ?>">
				<td class="wsp-sub"><?= esc(mb_strtoupper((string) ($line['title'] ?? ''))); ?></td>
				<td><?= esc($markText($max)); ?></td>
				<td><?= esc($markText($max)); ?></td>
				<td><?= esc($markText($max * 2)); ?></td>
				<?php foreach ($showTerms as $colTerm):
					$cat = $terms[$colTerm]['cat'] ?? null;
					$exam = $terms[$colTerm]['exam'] ?? null;
					$tot = $termTotal($cat, $exam);
					if ($cat !== null || $exam !== null) {
						$sumTerm[$colTerm]['any'] = true;
						$sumTerm[$colTerm]['cat'] += (float) ($cat ?? 0);
						$sumTerm[$colTerm]['exam'] += (float) ($exam ?? 0);
						$sumTerm[$colTerm]['base'] += $max * 2;
					}
					?>
					<td><?= esc($markText($cat)); ?></td>
					<td><?= esc($markText($exam)); ?></td>
					<td><?= esc($markText($tot)); ?></td>
				<?php endforeach; ?>
				<?php if ($annualSheet): ?>
					<td><?= esc($markText($line['annual_max'] ?? null)); ?></td>
					<td><?= esc($markText($line['annual_op'] ?? null)); ?></td>
					<td><?= esc($pctText($line['annual_pct'] ?? null)); ?></td>
					<td><?= esc($markText($line['sitting_op'] ?? null)); ?></td>
					<td><?= esc($pctText($line['sitting_pct'] ?? null)); ?></td>
				<?php else: ?>
					<td><?= esc($pctText($termPct)); ?></td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
			<tr class="wsp-conduct">
				<td class="wsp-sub">CONDUCT</td>
				<?php if ($annualSheet): ?>
					<td colspan="3"></td>
					<?php foreach ($showTerms as $colTerm): ?>
						<td colspan="3"><?= esc($conductText($student, $colTerm)); ?></td>
					<?php endforeach; ?>
					<td colspan="3"></td>
					<td colspan="2"></td>
				<?php else: ?>
					<td colspan="7"><?= esc($conductText($student, $showTerms[0])); ?></td>
				<?php endif; ?>
			</tr>
			<tr class="wsp-total">
				<td class="wsp-sub">TOTAL</td>
				<td><?= esc($markText($sumMaxCat)); ?></td>
				<td><?= esc($markText($sumMaxEx)); ?></td>
				<td><?= esc($markText(($sumMaxCat + $sumMaxEx))); ?></td>
				<?php foreach ($showTerms as $colTerm):
					$any = $sumTerm[$colTerm]['any'];
					?>
					<td><?= esc($markText($any ? $sumTerm[$colTerm]['cat'] : null)); ?></td>
					<td><?= esc($markText($any ? $sumTerm[$colTerm]['exam'] : null)); ?></td>
					<td><?= esc($markText($any ? ($sumTerm[$colTerm]['cat'] + $sumTerm[$colTerm]['exam']) : null)); ?></td>
				<?php endforeach; ?>
				<?php if ($annualSheet): ?>
					<td><?= esc($markText($annualAny ? $sumAnnualMax : null)); ?></td>
					<td><?= esc($markText($annualAny ? $sumAnnualOp : null)); ?></td>
					<td><?= esc($pctText($annualAny && $sumAnnualMax > 0 ? ($sumAnnualOp * 100 / $sumAnnualMax) : null)); ?></td>
					<td>-</td>
					<td>-</td>
				<?php else: ?>
					<td><?= esc($pctText($sumPctMax > 0 ? ($sumPctGot * 100 / $sumPctMax) : null)); ?></td>
				<?php endif; ?>
			</tr>
			<tr>
				<td class="wsp-sub" colspan="4">%</td>
				<?php if ($annualSheet):
					foreach ($showTerms as $colTerm):
						$got = $sumTerm[$colTerm]['any'] ? ($sumTerm[$colTerm]['cat'] + $sumTerm[$colTerm]['exam']) : null;
						$base = (float) $sumTerm[$colTerm]['base'];
						?>
						<td colspan="3"><?= esc($pctText($base > 0 && $got !== null ? ($got * 100 / $base) : null)); ?></td>
					<?php endforeach; ?>
					<td colspan="3"><?= esc($pctText($annualAny && $sumAnnualMax > 0 ? ($sumAnnualOp * 100 / $sumAnnualMax) : null)); ?></td>
					<td colspan="2">-</td>
				<?php else:
					$onePct = $sumPctMax > 0 ? ($sumPctGot * 100 / $sumPctMax) : null;
					?>
					<td colspan="3"><?= esc($pctText($onePct)); ?></td>
					<td><?= esc($pctText($onePct)); ?></td>
				<?php endif; ?>
			</tr>
			<tr>
				<td class="wsp-sub" colspan="4">Position</td>
				<?php if ($annualSheet):
					foreach ($showTerms as $colTerm): ?>
						<td colspan="3"><?= esc($placeText($sid, $colTerm)); ?></td>
					<?php endforeach; ?>
					<td colspan="3"><?= esc($placeText($sid, 4)); ?></td>
					<td colspan="2"></td>
				<?php else: ?>
					<td colspan="4"><?= esc($placeText($sid, $showTerms[0])); ?></td>
				<?php endif; ?>
			</tr>
			<tr>
				<td class="wsp-sub" colspan="4">Teacher's signature</td>
				<td class="wsp-sign" colspan="<?= $annualSheet ? 14 : 4; ?>"><?= $progressSignImg; ?></td>
			</tr>
			<tr>
				<td class="wsp-sub" colspan="4">Parent's signature</td>
				<td class="wsp-sign" colspan="<?= $annualSheet ? 14 : 4; ?>"></td>
			</tr>
		</tbody>
	</table>
	<?php
	$verdictOptions = [
		'1' => 'Promoted',
		'2' => 'Advised to repeat',
		'4' => 'Discontinued',
		'3' => 'Proposed to resit',
	];
	$chosenVerdict = (string) ($student['decision'] ?? '');
	$conductTerms = $annualSheet ? [1, 2, 3] : $showTerms;
	?>
	<table class="wsp-extra">
		<tr>
			<td style="width:58%;">
				<?php if ($annualSheet): ?>
					<div>VERDICT OF THE JURY</div>
					<ul class="wsp-jury">
						<?php foreach ($verdictOptions as $code => $label): ?>
							<li><?= $chosenVerdict === (string) $code ? '&#9632; ' : ''; ?><?= esc($label); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<div style="margin-top:1.2mm;">OBSERVATIONS</div>
				<?php foreach ($conductTerms as $colTerm): ?>
					<div><?= $termHeads[$colTerm]; ?>: <?= str_repeat('.', $annualSheet ? 28 : 36); ?></div>
				<?php endforeach; ?>
			</td>
			<td>
				<div>Headmaster</div>
				<div><?= esc((string) ($head_master ?? '')); ?></div>
				<div style="margin-top:1.2mm;"><?= $progressHeadImg !== '' ? $progressHeadImg : 'Signature and stamp'; ?></div>
			</td>
		</tr>
	</table>
	<?php
	$rankTerm = $annualSheet ? 4 : (int) $showTerms[0];
	$cards[] = [
		'html' => ob_get_clean(),
		'pos' => $places[$rankTerm][$sid] ?? '',
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
			echo '<div class="wsp-card">' . $card['html'] . '</div>';
		}
	} else {
		echo '<div class="wsp-sheet">';
		foreach ($cards as $card) {
			echo '<div class="wsp-fit"><div class="wsp-paper"><div class="wsp-card">' . $card['html'] . '</div></div></div>';
		}
		echo '</div>';
		echo '<script>(function(){function fit(){document.querySelectorAll(".wsp-fit").forEach(function(box){var paper=box.querySelector(".wsp-paper");if(!paper){return;}paper.style.transform="none";var avail=box.clientWidth||paper.offsetWidth;var scale=Math.min(1,avail/paper.offsetWidth);paper.style.transformOrigin="top left";paper.style.transform="scale("+scale+")";box.style.height=(paper.offsetHeight*scale)+"px";});}fit();window.addEventListener("resize",fit);})();</script>';
	}
}
?>
