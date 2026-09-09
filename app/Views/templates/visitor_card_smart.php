<?php
/**
 * Landscape visitor pass — full-bleed visit.png template (CR80 85.6×54mm).
 * Keep artwork as-is (including barcode). Overlay only:
 * - visited student name below "VISITED STUDENT:"
 * - registration number after "CODE:"
 */
helper('qonics');

$cardW = 85.6;
$cardH = 54.0; // 1011×638 template

$useCaps = !empty($capitalize);
$fmt = static function ($v) use ($useCaps) {
	$v = trim((string) $v);
	return $useCaps ? mb_strtoupper($v, 'UTF-8') : $v;
};

// Always use the fixed visitor pass artwork from visit.png.
$bgFile = 'visitor_pass_template.png';
$bgSrc = asset_card_img_src(
	'assets/images/background/' . $bgFile,
	'assets/images/background/visitor_pass_template.png',
	2022,
	1276
);

$fitLine = static function (string $text, float $boxWmm, float $boxHmm, float $maxMm, float $minMm = 1.2): float {
	$text = trim($text);
	if ($text === '') {
		return $maxMm;
	}
	$len = max(1, mb_strlen($text, 'UTF-8'));
	$longestWord = 1;
	foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
		$longestWord = max($longestWord, mb_strlen($word, 'UTF-8'));
	}
	$fromW = ($boxWmm * 1.86) / max(1.0, $len);
	$fromWord = ($boxWmm * 0.94) / max(1.0, $longestWord * 0.56);
	return max($minMm, min($maxMm, min($fromW, $fromWord, $boxHmm * 0.44)));
};

/*
 * Measured on landscape visit.png (1011×638 → 85.6×54.0mm):
 * - "VISITED STUDENT:" ~y=157–182; student name on the line below.
 * - Template barcode left untouched.
 * - "CODE:" ~x=243–323, y=378–400; regno starts immediately after the colon.
 */
$nameX = 5.5;
$nameY = 15.8;
$nameW = 74.6;
$nameH = 8.4;

$codeX = 27.8;
$codeY = 32.0;
$codeW = 42.0;
$codeH = 3.4;
?>
<style>
	@page { size: <?= number_format($cardW, 1, '.', ''); ?>mm <?= number_format($cardH, 1, '.', ''); ?>mm; margin: 0; }
	html, body {
		margin: 0; padding: 0;
		width: <?= number_format($cardW, 1, '.', ''); ?>mm;
		height: <?= number_format($cardH, 1, '.', ''); ?>mm;
		background: #000;
		overflow: hidden;
	}
	.page-break { page-break-after: always; height: 0; margin: 0; padding: 0; }
	.card {
		position: relative;
		width: <?= number_format($cardW, 1, '.', ''); ?>mm;
		height: <?= number_format($cardH, 1, '.', ''); ?>mm;
		overflow: hidden;
		box-sizing: border-box;
		font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
		background: #000;
	}
	.card * { box-sizing: border-box; margin: 0; padding: 0; }
	.card-bg {
		position: absolute; left: 0; top: 0;
		width: <?= number_format($cardW, 1, '.', ''); ?>mm;
		height: <?= number_format($cardH, 1, '.', ''); ?>mm;
		display: block; z-index: 0;
		border: 0;
	}
	.abs {
		position: absolute; z-index: 2; overflow: hidden;
		white-space: nowrap;
	}
	.name-block {
		display: block;
		text-align: center;
		white-space: normal;
		overflow-wrap: anywhere;
		word-break: break-word;
		line-height: 1.08;
		padding: 0 .6mm;
	}
</style>

<?php
$cards = $visitors ?? [];
foreach ($cards as $i => $visitor):
	$studentName = $fmt($visitor['student_name'] ?? '—');
	// CODE = visited student's registration number (never visitor card UID).
	$studentReg = $fmt($visitor['student_regno'] ?? '—');
	if ($studentReg === '') {
		$studentReg = '—';
	}
	$fsName = $fitLine($studentName, $nameW, $nameH, 3.4, 1.35);
	$fsCode = $fitLine($studentReg, $codeW, $codeH, 3.0, 1.6);
?>
<div class="card">
	<?php if ($bgSrc): ?>
	<img class="card-bg" src="<?= $bgSrc; ?>" width="<?= number_format($cardW, 1, '.', ''); ?>mm" height="<?= number_format($cardH, 1, '.', ''); ?>mm" alt="">
	<?php endif; ?>

	<!-- Visited student name (below VISITED STUDENT: title) -->
	<p class="abs name-block" style="left:<?= number_format($nameX, 1, '.', ''); ?>mm;top:<?= number_format($nameY, 1, '.', ''); ?>mm;
		width:<?= number_format($nameW, 1, '.', ''); ?>mm;height:<?= number_format($nameH, 1, '.', ''); ?>mm;
		font-size:<?= number_format($fsName, 2, '.', ''); ?>mm;
		font-weight:800;color:#f8fafc;"><?= esc($studentName); ?></p>

	<!-- CODE value only — "CODE:" label + barcode stay on the visit.png artwork -->
	<p class="abs" style="left:<?= number_format($codeX, 1, '.', ''); ?>mm;top:<?= number_format($codeY, 1, '.', ''); ?>mm;
		width:<?= number_format($codeW, 1, '.', ''); ?>mm;height:<?= number_format($codeH, 1, '.', ''); ?>mm;
		font-size:<?= number_format($fsCode, 2, '.', ''); ?>mm;line-height:<?= number_format($codeH, 1, '.', ''); ?>mm;
		font-weight:800;color:#f59e0b;letter-spacing:0.04em;text-align:left;"><?= esc($studentReg); ?></p>
</div>
<?php if ($i < count($cards) - 1): ?>
<div class="page-break"></div>
<?php endif; ?>
<?php endforeach; ?>
