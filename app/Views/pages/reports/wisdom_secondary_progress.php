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
	.wsp-total td { background: #e8eef5; }
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
$cards = [];
foreach ($sheets as $student) {
	if (empty($student['id'])) {
		continue;
	}
	if ($studentReg !== '' && (string) $student['id'] !== $studentReg) {
		continue;
	}
	$name = trim((string) ($student['fname'] ?? '') . ' ' . mb_strtoupper((string) ($student['lname'] ?? '')));
	$classLabel = trim((string) ($student['level_name'] ?? '') . ' ' . (string) (($student['code'] ?? '') !== '' ? $student['code'] : ($student['title'] ?? '')));
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
		$sumTerm[$colTerm] = ['cat' => 0.0, 'exam' => 0.0, 'any' => false];
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
		</tbody>
	</table>
	<?php
	$cards[] = ob_get_clean();
}
if ($cards !== []) {
	if (!empty($pdf)) {
		foreach ($cards as $i => $card) {
			if ($i > 0) {
				echo '<pagebreak />';
			}
			echo '<div class="wsp-card">' . $card . '</div>';
		}
	} else {
		echo '<div class="wsp-sheet">';
		foreach ($cards as $card) {
			echo '<div class="wsp-fit"><div class="wsp-paper"><div class="wsp-card">' . $card . '</div></div></div>';
		}
		echo '</div>';
		echo '<script>(function(){function fit(){document.querySelectorAll(".wsp-fit").forEach(function(box){var paper=box.querySelector(".wsp-paper");if(!paper){return;}paper.style.transform="none";var avail=box.clientWidth||paper.offsetWidth;var scale=Math.min(1,avail/paper.offsetWidth);paper.style.transformOrigin="top left";paper.style.transform="scale("+scale+")";box.style.height=(paper.offsetHeight*scale)+"px";});}fit();window.addEventListener("resize",fit);})();</script>';
	}
}
?>
