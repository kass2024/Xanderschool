<?php
/**
 * Portrait visitor pass — full-bleed template PNG.
 * Dynamic fields only: visited student name + registration code (regno).
 * No photo.
 */
helper('qonics');

$cardW = 54.0;
$cardH = 91.7; // matches template aspect ~591×1004

$useCaps = !empty($capitalize);
$fmt = static function ($v) use ($useCaps) {
	$v = trim((string) $v);
	return $useCaps ? mb_strtoupper($v, 'UTF-8') : $v;
};

$bgFile = trim((string) ($background ?? ''));
if ($bgFile === '' || !is_file(FCPATH . 'assets/images/background/' . $bgFile)) {
	$bgFile = 'visitor_pass_template.png';
}
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

// Overlay zones tuned to Black/Yellow/White visiting-card template (591×1004 → 54×91.7mm)
$nameX = 7.0;
$nameY = 34.2;
$nameW = 40.0;
$nameH = 6.8;

$codeX = 18.0;
$codeY = 52.2;
$codeW = 32.0;
$codeH = 4.2;
?>
<style>
	@page { size: <?= number_format($cardW, 1, '.', ''); ?>mm <?= number_format($cardH, 1, '.', ''); ?>mm; margin: 0; }
	html, body {
		margin: 0; padding: 0;
		width: <?= number_format($cardW, 1, '.', ''); ?>mm;
		height: <?= number_format($cardH, 1, '.', ''); ?>mm;
		background: #fff;
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
	$studentReg = $fmt($visitor['student_regno'] ?? '—');
	$fsName = $fitLine($studentName, $nameW, $nameH, 3.4, 1.6);
	$fsCode = $fitLine($studentReg, $codeW, $codeH, 2.6, 1.5);
?>
<div class="card">
	<?php if ($bgSrc): ?>
	<img class="card-bg" src="<?= $bgSrc; ?>" alt="">
	<?php endif; ?>

	<!-- Visited student name (white box area) -->
	<p class="abs" style="left:<?= number_format($nameX, 1, '.', ''); ?>mm;top:<?= number_format($nameY, 1, '.', ''); ?>mm;
		width:<?= number_format($nameW, 1, '.', ''); ?>mm;height:<?= number_format($nameH, 1, '.', ''); ?>mm;
		font-size:<?= number_format($fsName, 2, '.', ''); ?>mm;line-height:<?= number_format($nameH, 1, '.', ''); ?>mm;
		font-weight:800;color:#0f172a;text-align:center;"><?= esc($studentName); ?></p>

	<!-- Code = registration number -->
	<p class="abs" style="left:<?= number_format($codeX, 1, '.', ''); ?>mm;top:<?= number_format($codeY, 1, '.', ''); ?>mm;
		width:<?= number_format($codeW, 1, '.', ''); ?>mm;height:<?= number_format($codeH, 1, '.', ''); ?>mm;
		font-size:<?= number_format($fsCode, 2, '.', ''); ?>mm;line-height:<?= number_format($codeH, 1, '.', ''); ?>mm;
		font-weight:800;color:#facc15;letter-spacing:0.04em;"><?= esc($studentReg); ?></p>
</div>
<?php if ($i < count($cards) - 1): ?>
<div class="page-break"></div>
<?php endif; ?>
<?php endforeach; ?>
