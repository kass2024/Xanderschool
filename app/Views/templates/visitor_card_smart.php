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

$barcodeSvg = static function (string $value): string {
	$value = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value)));
	if ($value === '') {
		$value = '000000000';
	}
	$x = 6;
	$bars = [];
	$bars[] = '<rect x="2" y="2" width="2" height="22" fill="#111827"/>';
	$bars[] = '<rect x="4" y="2" width="1" height="22" fill="#111827"/>';
	foreach (str_split($value) as $ch) {
		$n = ord($ch);
		$pattern = [1 + ($n % 3), 1, 2 + ($n % 2), 1, 1 + (($n >> 1) % 3), 2];
		foreach ($pattern as $i => $w) {
			if ($i % 2 === 0) {
				$bars[] = '<rect x="' . $x . '" y="2" width="' . $w . '" height="22" fill="#111827"/>';
			}
			$x += $w;
		}
		$x += 1;
	}
	$bars[] = '<rect x="' . $x . '" y="2" width="1" height="22" fill="#111827"/>';
	$bars[] = '<rect x="' . ($x + 2) . '" y="2" width="2" height="22" fill="#111827"/>';
	$width = $x + 6;
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' 26" preserveAspectRatio="none">'
		. '<rect width="' . $width . '" height="26" rx="2" ry="2" fill="rgba(255,255,255,0.92)"/>'
		. implode('', $bars)
		. '</svg>';
};

/*
 * Measured on VISIT.png (591×1004 → 54×91.7mm):
 * - "VISITED STUDENT:" centered ~y=320–344; value sits on the line below.
 * - "CODE:" ends ~x=175, y≈565–592; regno starts immediately after the colon.
 */
$nameX = 4.6;
$nameY = 31.8;
$nameW = 44.8;
$nameH = 13.2;

$codeX = 12.6;
$codeY = 61.2;
$codeW = 37.8;
$codeH = 4.5;

$barcodeX = 5.5;
$barcodeY = 50.6;
$barcodeW = 43.2;
$barcodeH = 13.2;
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
	.name-block {
		display: flex;
		align-items: center;
		justify-content: center;
		text-align: center;
		white-space: normal;
		overflow-wrap: anywhere;
		word-break: break-word;
		line-height: 1.05;
		padding: 0 .8mm;
	}
	.barcode-shell {
		background: rgba(255,255,255,0.10);
		border-radius: 2mm;
		padding: .6mm .8mm 1.4mm;
	}
	.barcode-svg {
		display: block;
		width: 100%;
		height: 10.6mm;
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
	$fsName = $fitLine($studentName, $nameW, $nameH, 3.35, 1.15);
	$codeLabel = 'CODE: ' . $studentReg;
	$fsCode = $fitLine($codeLabel, $codeW, $codeH, 2.9, 1.45);
	$barcodeMarkup = $barcodeSvg($studentReg);
?>
<div class="card">
	<?php if ($bgSrc): ?>
	<img class="card-bg" src="<?= $bgSrc; ?>" alt="">
	<?php endif; ?>

	<!-- Visited student name (below VISITED STUDENT: label) -->
	<p class="abs name-block" style="left:<?= number_format($nameX, 1, '.', ''); ?>mm;top:<?= number_format($nameY, 1, '.', ''); ?>mm;
		width:<?= number_format($nameW, 1, '.', ''); ?>mm;height:<?= number_format($nameH, 1, '.', ''); ?>mm;
		font-size:<?= number_format($fsName, 2, '.', ''); ?>mm;
		font-weight:800;color:#f8fafc;text-align:center;"><?= esc($studentName); ?></p>

	<!-- CODE = current student code -->
	<p class="abs" style="left:<?= number_format($codeX, 1, '.', ''); ?>mm;top:<?= number_format($codeY, 1, '.', ''); ?>mm;
		width:<?= number_format($codeW, 1, '.', ''); ?>mm;height:<?= number_format($codeH, 1, '.', ''); ?>mm;
		font-size:<?= number_format($fsCode, 2, '.', ''); ?>mm;line-height:<?= number_format($codeH, 1, '.', ''); ?>mm;
		font-weight:800;color:#facc15;letter-spacing:0.03em;text-align:left;"><?= esc($codeLabel); ?></p>

	<!-- Visual barcode only: uses the same student code, but does not activate scanning logic. -->
	<div class="abs barcode-shell" style="left:<?= number_format($barcodeX, 1, '.', ''); ?>mm;top:<?= number_format($barcodeY, 1, '.', ''); ?>mm;
		width:<?= number_format($barcodeW, 1, '.', ''); ?>mm;height:<?= number_format($barcodeH, 1, '.', ''); ?>mm;">
		<div class="barcode-svg"><?= $barcodeMarkup; ?></div>
	</div>
</div>
<?php if ($i < count($cards) - 1): ?>
<div class="page-break"></div>
<?php endif; ?>
<?php endforeach; ?>
