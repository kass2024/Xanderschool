<style>
	.wsp-wrap { background: #fff; color: #111; }
	.wsp-page { page-break-after: always; margin: 0 0 12mm; }
	.wsp-page:last-child { page-break-after: auto; }
	.wsp-title {
		margin: 0 0 4mm;
		text-align: center;
		font-size: 16pt;
		font-weight: 700;
		letter-spacing: 0.4px;
	}
	.wsp-id { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
	.wsp-id td { border: none; font-size: 11pt; padding: 0.6mm 1mm; vertical-align: middle; }
	.wsp-id .lab { width: 38mm; white-space: nowrap; }
	.wsp-name {
		display: inline-block;
		min-width: 70mm;
		border: 0.8pt solid #111;
		padding: 0.4mm 2mm;
		font-weight: 700;
	}
	.wsp-year { text-align: right; white-space: nowrap; font-weight: 700; }
	.wsp-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
	.wsp-table th, .wsp-table td {
		border: 0.6pt solid #111;
		padding: 0.5mm 0.4mm;
		text-align: center;
		font-size: 7.5pt;
		font-weight: 700;
		line-height: 1.15;
	}
	.wsp-table th { background: #fff; }
	.wsp-sub { text-align: left; padding-left: 1.2mm; font-size: 7.5pt; }
	.wsp-sit { border-left: 1.4pt solid #111; }
	.wsp-total td { background: #f3f3f3; }
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
	<div class="wsp-wrap wsp-page">
		<h1 class="wsp-title">PROGRESSIVE SCHOOL REPORT</h1>
		<table class="wsp-id">
			<tr>
				<td class="lab">Student's name:</td>
				<td><span class="wsp-name"><?= esc($name); ?></span></td>
				<td class="wsp-year">School Year: <?= esc($yearLabel); ?></td>
			</tr>
			<tr>
				<td class="lab">Student's number:</td>
				<td colspan="2"><?= esc((string) ($student['regno'] ?? '')); ?></td>
			</tr>
			<tr>
				<td class="lab">Class:</td>
				<td colspan="2"><?= esc($classLabel); ?></td>
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
			<?php foreach ($lines as $line):
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
				<tr>
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
