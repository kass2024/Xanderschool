<style>
	.wsp-sheet { background: <?= !empty($pdf) ? '#fff' : '#eef1f4'; ?>; color: #111; }
	.wsp-page { page-break-after: always; background: #fff; }
	.wsp-page:last-child { page-break-after: auto; }
	.wsp-head, .wsp-id, .wsp-table { border-collapse: collapse; width: 100%; }
	.wsp-crest, .wsp-logo { width: 16mm; height: 16mm; display: block; }
	.wsp-republic { font-size: 8.5pt; font-weight: 700; letter-spacing: 0.3px; text-align: center; line-height: 1.15; }
	.wsp-school { font-size: 11pt; font-weight: 700; text-align: center; line-height: 1.15; margin-top: 0.4mm; }
	.wsp-moto { font-size: 8pt; text-align: center; line-height: 1.15; }
	.wsp-title { font-size: 10.5pt; font-weight: 700; text-align: center; margin-top: 1.2mm; }
	.wsp-id { margin-top: 1.6mm; margin-bottom: 1.6mm; }
	.wsp-id td { border: 0.6pt solid #1e3a5f; padding: 0.5mm 1.2mm; vertical-align: middle; }
	.wsp-id .k { font-size: 7.5pt; font-weight: 700; width: 32mm; background: #f4f7fb; }
	.wsp-id .v { font-size: 8pt; font-weight: 700; }
	.wsp-table { table-layout: fixed; }
	.wsp-table th, .wsp-table td {
		border: 0.6pt solid #1e3a5f;
		padding: 0.5mm 0.4mm;
		text-align: center;
		font-size: 7.5pt;
		font-weight: 700;
		line-height: 1.15;
	}
	.wsp-table th { background: #1e3a5f; color: #fff; }
	.wsp-table tr.alt td { background: #f3f7fb; }
	.wsp-sub { text-align: left; padding-left: 1.2mm; font-size: 7.5pt; }
	.wsp-sit { border-left: 1.4pt solid #1e3a5f; }
	.wsp-total td { background: #e8eef5; }
<?php if (empty($pdf)): ?>
	.wsp-scroll { overflow-x: auto; }
	@media print {
		.app-sidebar-wrapper, .app-sidebar, .app-sidebar-overlay,
		.header-mobile-wrapper, .app-header, .app-footer, .app-page-title,
		.ui-theme-settings, .fixed-header { display: none !important; }
		.app-container, .app-main, .app-main__outer, .app-main__inner {
			margin: 0 !important; padding: 0 !important; width: auto !important; background: #fff !important;
		}
		.wsp-page { page-break-after: always; }
	}
	@page { size: A4 landscape; margin: 6mm; }
<?php endif; ?>
</style>
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
$termNo = (int) ($term ?? 0);
$roman = [1 => 'I', 2 => 'II', 3 => 'III'][$termNo] ?? (string) $termNo;
$cardTitle = $heading . ' PROGRESSIVE SCHOOL REPORT';
if ($roman !== '') {
	$cardTitle .= ' · TERM ' . $roman;
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
$termTotal = static function (?float $cat, ?float $exam) {
	if ($cat === null && $exam === null) {
		return null;
	}
	return (float) ($cat ?? 0) + (float) ($exam ?? 0);
};
if ($sheets === []) {
	echo '<h1>' . lang('app.noStudentFound') . '</h1>';
}
$studentReg = isset($_GET['student']) ? (string) $_GET['student'] : '';
$shown = 0;
foreach ($sheets as $student) {
	if ($studentReg !== '' && (string) ($student['id'] ?? '') !== $studentReg) {
		continue;
	}
	$name = trim((string) ($student['fname'] ?? '') . ' ' . mb_strtoupper((string) ($student['lname'] ?? '')));
	$classLabel = trim((string) ($student['level_name'] ?? '') . ' ' . (string) (($student['code'] ?? '') !== '' ? $student['code'] : ($student['title'] ?? '')));
	$lines = $student['progress_courses'] ?? [];
	$sumMaxCat = 0.0;
	$sumMaxEx = 0.0;
	$sumTerm = [1 => ['cat' => 0.0, 'exam' => 0.0, 'any' => false], 2 => ['cat' => 0.0, 'exam' => 0.0, 'any' => false], 3 => ['cat' => 0.0, 'exam' => 0.0, 'any' => false]];
	$sumAnnualMax = 0.0;
	$sumAnnualOp = 0.0;
	$annualAny = false;
	if (!empty($pdf) && $shown > 0) {
		echo '<pagebreak />';
	}
	$shown++;
	?>
	<div class="wsp-sheet wsp-page">
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
				<td class="k">Class</td>
				<td class="v"><?= esc($classLabel); ?></td>
				<td class="k">School Year</td>
				<td class="v"><?= esc($yearLabel); ?></td>
			</tr>
		</table>
		<div class="<?= empty($pdf) ? 'wsp-scroll' : ''; ?>">
		<table class="wsp-table">
			<colgroup>
				<col style="width:18%;">
				<?php for ($c = 0; $c < 17; $c++): ?><col style="width:4.82%;"><?php endfor; ?>
			</colgroup>
			<thead>
				<tr>
					<th rowspan="3">Subjects</th>
					<th colspan="3">MAX POINTS</th>
					<th colspan="3">1<sup>st</sup> TERM</th>
					<th colspan="3">2<sup>nd</sup> TERM</th>
					<th colspan="3">3<sup>rd</sup> TERM</th>
					<th colspan="3">ANNUAL POINTS</th>
					<th class="wsp-sit" colspan="2" rowspan="2">2<sup>nd</sup> SITTING</th>
				</tr>
				<tr>
					<th colspan="3"></th>
					<th colspan="3">O.P.</th>
					<th colspan="3">O.P.</th>
					<th colspan="3">O.P.</th>
					<th colspan="3">TOT</th>
				</tr>
				<tr>
					<th>CAT</th><th>EX</th><th>TOT</th>
					<th>CAT</th><th>EX</th><th>TOT</th>
					<th>CAT</th><th>EX</th><th>TOT</th>
					<th>CAT</th><th>EX</th><th>TOT</th>
					<th>MAX</th><th>O.P.</th><th>%</th>
					<th class="wsp-sit">O.P.</th><th>%</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($lines as $lineIndex => $line):
				$max = (float) ($line['max'] ?? 0);
				$sumMaxCat += $max;
				$sumMaxEx += $max;
				$terms = $line['terms'] ?? [];
				if ($line['annual_op'] !== null) {
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
					<?php for ($termNo = 1; $termNo <= 3; $termNo++):
						$cat = $terms[$termNo]['cat'] ?? null;
						$exam = $terms[$termNo]['exam'] ?? null;
						$tot = $termTotal($cat === null ? null : (float) $cat, $exam === null ? null : (float) $exam);
						if ($cat !== null || $exam !== null) {
							$sumTerm[$termNo]['any'] = true;
							$sumTerm[$termNo]['cat'] += (float) ($cat ?? 0);
							$sumTerm[$termNo]['exam'] += (float) ($exam ?? 0);
						}
						?>
						<td><?= esc($markText($cat)); ?></td>
						<td><?= esc($markText($exam)); ?></td>
						<td><?= esc($markText($tot)); ?></td>
					<?php endfor; ?>
					<td><?= esc($markText($line['annual_max'] ?? null)); ?></td>
					<td><?= esc($markText($line['annual_op'] ?? null)); ?></td>
					<td><?= esc($pctText($line['annual_pct'] ?? null)); ?></td>
					<td class="wsp-sit"><?= esc($markText($line['sitting_op'] ?? null)); ?></td>
					<td><?= esc($pctText($line['sitting_pct'] ?? null)); ?></td>
				</tr>
			<?php endforeach; ?>
				<tr class="wsp-total">
					<td class="wsp-sub">TOTAL</td>
					<td><?= esc($markText($sumMaxCat)); ?></td>
					<td><?= esc($markText($sumMaxEx)); ?></td>
					<td><?= esc($markText($sumMaxCat + $sumMaxEx)); ?></td>
					<?php for ($termNo = 1; $termNo <= 3; $termNo++):
						$any = $sumTerm[$termNo]['any'];
						$cat = $any ? $sumTerm[$termNo]['cat'] : null;
						$exam = $any ? $sumTerm[$termNo]['exam'] : null;
						$tot = $any ? ($sumTerm[$termNo]['cat'] + $sumTerm[$termNo]['exam']) : null;
						?>
						<td><?= esc($markText($cat)); ?></td>
						<td><?= esc($markText($exam)); ?></td>
						<td><?= esc($markText($tot)); ?></td>
					<?php endfor; ?>
					<td><?= esc($markText($annualAny ? $sumAnnualMax : null)); ?></td>
					<td><?= esc($markText($annualAny ? $sumAnnualOp : null)); ?></td>
					<td><?= esc($pctText($annualAny && $sumAnnualMax > 0 ? ($sumAnnualOp * 100 / $sumAnnualMax) : null)); ?></td>
					<td class="wsp-sit">-</td>
					<td>-</td>
				</tr>
			</tbody>
		</table>
		</div>
	</div>
	<?php
}
?>
