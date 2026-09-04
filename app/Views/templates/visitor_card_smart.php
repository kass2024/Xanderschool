<?php
/**
 * Portrait visitor pass — full-bleed VISIT.png template.
 * Dynamic fields only: visited student name + registration number (CODE).
 * No photo.
 */
helper('qonics');

$cardW = 54.0;
$cardH = 91.7; // 591×1004 template

$useCaps = !empty($capitalize);
$fmt = static function ($v) use ($useCaps) {
	$v = trim((string) $v);
	return $useCaps ? mb_strtoupper($v, 'UTF-8') : $v;
};

// Always use the fixed visitor pass artwork.
$bgFile = 'visitor_pass_template.png';
$bgSrc = asset_card_img_src(
	'assets/images/background/' . $bgFile,
	'assets/images/background/visitor_pass_template.png',
	1200,
	2000
);

$fitLine = static function (string $text, float $boxWmm, float $boxHmm, float $maxMm, float $minMm = 1.4): float {
	$text = trim($text);
	if ($text === '') {
		return $maxMm;
	}
	$len = max(1, mb_strlen($text, 'UTF-8'));
	$fromW = ($boxWmm * 0.95) / max(1.0, $len * 0.52);
	return max($minMm, min($maxMm, min($fromW, $boxHmm * 0.72)));
};

/*
 * Measured on VISIT.png (591×1004 → 54×91.7mm):
 * - "VISITED STUDENT:" centered ~y=320–344; value sits on the line below.
 * - "CODE:" ends ~x=175, y≈565–592; regno starts immediately after the colon.
 */
$nameX = 5.5;
$nameY = 33.5;
$nameW = 43.0;
$nameH = 7.5;

$codeX = 17.5;
$codeY = 51.0;
$codeW = 33.0;
$codeH = 4.5;
?>
<style>
	@page { size: <?= number_format($cardW, 1, '.', ''); ?>mm <?= number_format($cardH, 1, '.', ''); ?>mm; margin: 0; }
	html, body {
		margin: 0; padding: 0;
		width: <?= number_format($cardW, 1, '.', ''); ?>mm;
		height: <?= number_format($cardH, 1, '.', ''); ?>mm;
		background: #111;
	}
	.page-break { page-break-after: always; height: 0; margin: 0; padding: 0; }
	.card {
		position: relative;
		width: <?= number_format($cardW, 1, '.', ''); ?>mm;
		height: <?= number_format($cardH, 1, '.', ''); ?>mm;
		overflow: hidden;
		box-sizing: border-box;
		font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
		background: #111;
	}
	.card * { box-sizing: border-box; margin: 0; padding: 0; }
	.card-bg {
		position: absolute; left: 0; top: 0; width: 100%; height: 100%;
		object-fit: fill; display: block; z-index: 0;
	}
	.abs {
		position: absolute; z-index: 2; overflow: hidden;
		white-space: nowrap;
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
	$fsName = $fitLine($studentName, $nameW, $nameH, 3.6, 1.5);
	$fsCode = $fitLine($studentReg, $codeW, $codeH, 3.2, 1.6);
?>
<div class="card">
	<?php if ($bgSrc): ?>
	<img class="card-bg" src="<?= $bgSrc; ?>" alt="">
	<?php endif; ?>

	<!-- Visited student name (below VISITED STUDENT: label) -->
	<p class="abs" style="left:<?= number_format($nameX, 1, '.', ''); ?>mm;top:<?= number_format($nameY, 1, '.', ''); ?>mm;
		width:<?= number_format($nameW, 1, '.', ''); ?>mm;height:<?= number_format($nameH, 1, '.', ''); ?>mm;
		font-size:<?= number_format($fsName, 2, '.', ''); ?>mm;line-height:<?= number_format($nameH, 1, '.', ''); ?>mm;
		font-weight:800;color:#f8fafc;text-align:center;"><?= esc($studentName); ?></p>

	<!-- CODE = student registration number -->
	<p class="abs" style="left:<?= number_format($codeX, 1, '.', ''); ?>mm;top:<?= number_format($codeY, 1, '.', ''); ?>mm;
		width:<?= number_format($codeW, 1, '.', ''); ?>mm;height:<?= number_format($codeH, 1, '.', ''); ?>mm;
		font-size:<?= number_format($fsCode, 2, '.', ''); ?>mm;line-height:<?= number_format($codeH, 1, '.', ''); ?>mm;
		font-weight:800;color:#facc15;letter-spacing:0.04em;text-align:left;"><?= esc($studentReg); ?></p>
</div>
<?php if ($i < count($cards) - 1): ?>
<div class="page-break"></div>
<?php endif; ?>
<?php endforeach; ?>
