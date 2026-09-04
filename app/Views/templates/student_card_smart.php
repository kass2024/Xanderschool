<?php
/**
 * CR80 student cards — WYSIWYG from School Settings CardLayout (% boxes).
 * Full-bleed background, photo cover-fit, auto-scaled single-line text.
 */
helper('qonics');
use App\Libraries\CardLayout;

$tplKey = CardLayout::normalizeTemplate($card_template ?? 'ocean');
$orientation = CardLayout::normalizeOrientation($orientation ?? CardLayout::preferredOrientation($tplKey));
$isPortrait = $orientation === 'portrait';
$cardWmm = $isPortrait ? CardLayout::CR80_H_MM : CardLayout::CR80_W_MM;
$cardHmm = $isPortrait ? CardLayout::CR80_W_MM : CardLayout::CR80_H_MM;

$layout = CardLayout::resolve($card_layout ?? null, $tplKey, $orientation);
$fields = $layout['fields'];
$tplKey = $layout['template'];
$orientation = $layout['orientation'];
$isPortrait = $orientation === 'portrait';
$isWisdom = CardLayout::isFixedChrome($tplKey);
$cardWmm = $isPortrait ? CardLayout::CR80_H_MM : CardLayout::CR80_W_MM;
$cardHmm = $isPortrait ? CardLayout::CR80_W_MM : CardLayout::CR80_H_MM;

$yearLabel = trim((string) ($theyear ?? ($year ?? date('Y'))));
$validityShort = preg_replace('/\s+/', '', $yearLabel);
if (stripos($validityShort, 'A.Y') === 0) {
	$validityShort = trim(substr($validityShort, 3));
}
$wisdomYear = CardLayout::formatAcademicYear($yearLabel);
$useCaps = !empty($capitalize) || $isWisdom;
$fmt = static function ($v) use ($useCaps) {
	$v = trim((string) $v);
	return $useCaps ? mb_strtoupper($v, 'UTF-8') : $v;
};

$main = !empty($main_color) ? $main_color : CardLayout::defaultAccent($tplKey);
$headerC = !empty($header_color) ? $header_color : $main;
$footerC = !empty($footer_color) ? $footer_color : $main;
$text = '#0f172a';

$isPainted = CardLayout::isPainted($tplKey);
$paint = !empty($paint_color) ? $paint_color : $main;
$tintLight = CardLayout::tint($paint, 0.82);
$tintMid = CardLayout::tint($paint, 0.55);
$wisdomTeal = CardLayout::WISDOM_TEAL;
$wisdomNavy = CardLayout::WISDOM_NAVY;
$wisdomChromeSrc = $isWisdom
	? asset_card_img_src(CardLayout::WISDOM_CHROME, null, 1800, 1200)
	: '';

$logoSrc = !empty($logo)
	? asset_card_img_src('assets/images/logo/' . $logo, 'assets/images/fallback-logo.png', $isWisdom ? 1000 : 480, $isWisdom ? 1000 : 320)
	: asset_card_img_src(null, 'assets/images/fallback-logo.png', $isWisdom ? 1000 : 480, $isWisdom ? 1000 : 320);
$sigSrc = !empty($headmaster_signature)
	? asset_card_img_src('assets/images/signatures/' . $headmaster_signature, null, 320, 120)
	: '';

// Painted templates ship their own design — uploaded/AI backgrounds are never applied.
$bgFile = $isPainted ? '' : trim((string) ($background ?? ''));
$bgSrc = '';
if (strlen($bgFile) > 4) {
	$rel = 'assets/images/background/' . basename($bgFile);
	$abs = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
	if (is_file($abs)) {
		$bgSrc = asset_card_img_src($rel, null, $isPortrait ? 900 : 1400, $isPortrait ? 1400 : 900);
	}
}

$schoolName = $fmt($school_name ?? '');
$header1Val = $fmt(trim((string) ($header1 ?: ($moto ?? ''))));
$header2Val = $fmt(trim((string) ($header2 ?? '')));
$cardTitle = !empty($card_badge) ? $fmt($card_badge) : ($isWisdom ? 'STUDENT ID CARD' : 'STUDENT CARD');
$motoVal = $fmt(trim((string) ($moto ?: $schoolName)));
$headMaster = $fmt(trim((string) ($head_master ?? 'Headmaster')));

$labels = CardLayout::FIELDS;

$fit = static function (string $text, array $f, float $max = 3.2, float $min = 1.35, float $factor = 0.52) use ($cardWmm, $cardHmm): float {
	return CardLayout::fitFontMm($text, (float)($f['w'] ?? 50), (float)($f['h'] ?? 6), $cardWmm, $cardHmm, $max, $min, $factor);
};
?>
<style>
	@page { size: <?= $cardWmm; ?>mm <?= $cardHmm; ?>mm; margin: 0; }
	html, body {
		margin: 0; padding: 0;
		width: <?= $cardWmm; ?>mm;
		height: <?= $cardHmm; ?>mm;
	}
	.page-break { height: 0; page-break-after: always; margin: 0; border: 0; }
	.card {
		width: <?= $cardWmm; ?>mm;
		height: <?= $cardHmm; ?>mm;
		position: relative;
		overflow: hidden;
		box-sizing: border-box;
		font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
		background: #ffffff;
	}
	.card * { box-sizing: border-box; }
	.card-bg {
		position: absolute; left: 0; top: 0; width: 100%; height: 100%;
		object-fit: fill; z-index: 0; border: 0;
	}
	.cf {
		display: -webkit-box; display: flex;
		-webkit-box-align: center; align-items: center;
		white-space: nowrap; overflow: hidden;
		line-height: 1.15; color: <?= $text; ?>;
	}
	.cf .lab {
		font-weight: 700; color: <?= $main; ?>;
		margin-right: 0.8mm; flex-shrink: 0;
	}
	.cf .val { overflow: hidden; white-space: nowrap; }
	.cf-center {
		display: -webkit-box; display: flex;
		-webkit-box-align: center; align-items: center;
		-webkit-box-pack: center; justify-content: center;
		text-align: center; white-space: nowrap; overflow: hidden;
	}
	.cf-logo, .cf-photo {
		display: -webkit-box; display: flex;
		-webkit-box-align: center; align-items: center;
		-webkit-box-pack: center; justify-content: center;
		background: #ffffff;
		overflow: hidden;
	}
	.cf-logo img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
	.cf-photo {
		border: <?= $isPainted ? '0.7' : '0.45'; ?>mm solid <?= $main; ?>;
		background: #f1f5f9;
		overflow: hidden;
	}
	/* Classic Curve painted design — swoosh bands + rounded frame (color only) */
	.paint { position: absolute; left: 0; top: 0; width: 100%; height: 100%; z-index: 1; overflow: hidden; }
	.paint div { position: absolute; }
	.paint-frame {
		left: 0; top: 0; width: 100%; height: 100%;
		border: 1.1mm solid <?= $paint; ?>;
		border-radius: 2.6mm;
	}
	.paint-l-light { left: -52%; top: -18%; width: 74%; height: 136%; border-radius: 50%; background: <?= $tintLight; ?>; }
	.paint-l-dark { left: -56%; top: -15%; width: 70%; height: 130%; border-radius: 50%; background: <?= $tintMid; ?>; }
	.paint-b-light { left: -12%; top: 85.5%; width: 135%; height: 30%; border-radius: 50%; background: <?= $tintLight; ?>; }
	.paint-b-mid { left: -15%; top: 89.5%; width: 140%; height: 30%; border-radius: 50%; background: <?= $tintMid; ?>; }
	.cf-photo img {
		width: 100%;
		height: 100%;
		display: block;
		border: 0;
		/* Image is already center-cropped to the box aspect — do not stretch */
	}
	.cf-badge, .cf-moto {
		background: <?= $main; ?>;
		color: #ffffff;
		font-weight: 700;
		letter-spacing: 0.04em;
		border-radius: 0;
	}
	.cf-badge {
		left: 0 !important;
		width: 100% !important;
	}
	.cf-moto { background: <?= $footerC; ?>; }
	.cf-school { color: <?= $headerC; ?>; font-weight: 700; }
	.cf-header {
		color: <?= $headerC; ?>;
		font-weight: 600;
		letter-spacing: 0.02em;
		opacity: 0.95;
	}
	.cf-sig img { max-width: 100%; max-height: 70%; object-fit: contain; display: block; margin: 0 auto; }
	.cf-sig .sig-lab {
		border-top: 0.25mm solid #94a3b8;
		margin-top: 0.4mm;
		padding-top: 0.3mm;
		font-size: 1.4mm;
		text-align: center;
		white-space: nowrap;
		overflow: hidden;
	}
	.card.is-wisdom { background: #f8f8f8; }
	.card.is-wisdom .cf-logo {
		background: #ffffff;
		border-radius: 50%;
		-webkit-border-radius: 50%;
		border: 0.25mm solid <?= $wisdomTeal; ?>;
		padding: 0.3mm;
		overflow: hidden;
	}
	.card.is-wisdom .cf-logo img {
		width: 100%; height: 100%;
		max-width: 100%; max-height: 100%;
		object-fit: contain;
		-webkit-object-fit: contain;
		display: block;
		border: 0;
		image-rendering: auto;
	}
	.card.is-wisdom .cf-photo {
		background: #ffffff;
		border: 1.5mm solid <?= $wisdomTeal; ?>;
		border-radius: 50%;
		-webkit-border-radius: 50%;
		overflow: hidden;
	}
	.card.is-wisdom .cf-photo img {
		width: 100%; height: 100%;
		object-fit: cover;
		display: block;
		border: 0;
	}
	.card.is-wisdom .w-school {
		color: #ffffff;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		overflow: visible;
	}
	.card.is-wisdom .w-badge {
		color: #ffffff;
		font-weight: 700;
		letter-spacing: 0.06em;
		text-transform: uppercase;
		background: transparent;
		overflow: visible;
	}
	.card.is-wisdom .w-id {
		color: #ffffff;
		font-weight: 700;
		letter-spacing: 0.03em;
		text-transform: uppercase;
		overflow: visible;
	}
	.card.is-wisdom .w-info-wrap {
		overflow: visible;
		padding-left: 1.2mm;
	}
	.card.is-wisdom .w-info {
		border-collapse: collapse;
		color: <?= $wisdomNavy; ?>;
		font-weight: 700;
		text-transform: uppercase;
		width: 100%;
		height: 100%;
	}
	.card.is-wisdom .w-info td {
		vertical-align: middle;
		padding: 0;
		line-height: 1.2;
		white-space: nowrap;
		height: 33%;
	}
	.card.is-wisdom .w-info .k { padding-right: 1.2mm; width: 1%; white-space: nowrap; }
	.card.is-wisdom .w-info .c { padding-right: 1.6mm; }
	.card.is-wisdom .w-info .v { overflow: visible; white-space: nowrap; }
</style>
<script>
(function () {
	function fitAll() {
		var nodes = document.querySelectorAll('.cf, .cf-center, .cf-badge, .cf-moto');
		for (var i = 0; i < nodes.length; i++) {
			var el = nodes[i];
			var max = parseFloat(el.getAttribute('data-max')) || 3.2;
			var min = parseFloat(el.getAttribute('data-min')) || 1.2;
			var size = max;
			el.style.fontSize = size + 'mm';
			var guard = 0;
			while (guard < 40 && size > min && (el.scrollWidth > el.clientWidth + 1 || el.scrollHeight > el.clientHeight + 1)) {
				size -= 0.08;
				el.style.fontSize = size.toFixed(2) + 'mm';
				guard++;
			}
		}
	}
	if (document.readyState === 'complete') fitAll();
	else window.onload = fitAll;
})();
</script>

<?php foreach ($students as $student):
	$photoField = $fields['photo'] ?? ['w' => 36, 'h' => 30];
	$photoPxW = max(180, (int) round($cardWmm * ((float)($photoField['w'] ?? 36) / 100) * 12));
	$photoPxH = max(220, (int) round($cardHmm * ((float)($photoField['h'] ?? 30) / 100) * 12));
	if ($isWisdom) {
		$photoPxW = $photoPxH = max($photoPxW, $photoPxH, 480);
		$schoolName = $fmt(CardLayout::wisdomCardSchoolName($student));
	}
	$photoSrc = profile_photo_card_cover_src($student['photo'] ?? '', $photoPxW, $photoPxH);
	if ($photoSrc === '') {
		continue;
	}
	$phone = strlen(trim($student['ft_phone'] ?? '')) > 4 ? $student['ft_phone']
		: (strlen(trim($student['mt_phone'] ?? '')) > 4 ? $student['mt_phone']
			: (strlen(trim($student['gd_phone'] ?? '')) > 4 ? $student['gd_phone'] : ($student['phone'] ?? '')));
	$fullName = $fmt($student['name'] ?? '');
	$regno = $fmt($student['regno'] ?? '');
	$classLabel = $fmt($student['class'] ?? '');
	$father = $fmt($student['father'] ?? '—');
	$phoneLabel = $fmt($phone ?: '—');
	$modeRaw = $student['studying_mode'] ?? ($student['mode'] ?? ($student['study_mode'] ?? ''));
	if ($modeRaw === '' || $modeRaw === null) {
		$modeLabel = '—';
	} elseif (is_numeric($modeRaw)) {
		$modeLabel = $fmt(((int) $modeRaw === 0) ? 'Boarding' : 'Day');
	} else {
		$modeLabel = $fmt($modeRaw);
	}

	$dobLabel = CardLayout::formatDob($student['dob'] ?? '');

	$values = [
		'school_name' => $schoolName,
		'header1' => $header1Val !== '' ? $header1Val : '—',
		'header2' => $header2Val !== '' ? $header2Val : '—',
		'badge' => $cardTitle,
		'names' => $fullName,
		'regno' => $regno,
		'class' => $classLabel,
		'dob' => $dobLabel !== '' ? $fmt($dobLabel) : '—',
		'father' => $father,
		'phone' => $phoneLabel,
		'mode' => $modeLabel,
		'moto' => $motoVal !== '' ? $motoVal : $schoolName,
	];
	$labeled = ['names', 'regno', 'class', 'dob', 'father', 'phone', 'mode'];
	if ($isWisdom) {
		$idNo = trim((string) ($student['regno'] ?? ''));
		if ($idNo === '') {
			$idNo = trim((string) ($student['card'] ?? ''));
		}
		$regno = $fmt($idNo);
		$values['regno'] = $regno;
		$values['header1'] = $fmt($wisdomYear !== '' ? $wisdomYear : $yearLabel);
	}
?>
	<div class="card<?= $isWisdom ? ' is-wisdom' : ''; ?>">
		<?php if (!$isWisdom && $bgSrc !== ''): ?>
			<img class="card-bg" src="<?= $bgSrc; ?>" alt="">
		<?php endif; ?>
		<?php if ($isPainted && !$isWisdom): ?>
			<div class="paint">
				<div class="paint-l-light"></div>
				<div class="paint-l-dark"></div>
				<div class="paint-b-light"></div>
				<div class="paint-b-mid"></div>
				<div class="paint-frame"></div>
			</div>
		<?php endif; ?>
		<?php if ($isWisdom):
			$photoF = $fields['photo'] ?? ['x' => 6.6, 'y' => 33.8, 'w' => 25.2, 'h' => 40.0];
			$namesF = $fields['names'] ?? ['x' => 42.8, 'y' => 49.6, 'w' => 54.5, 'h' => 8.0];
			$yearF = $fields['header1'] ?? ['x' => 42.8, 'y' => 66.2, 'w' => 54.5, 'h' => 7.6];
			$idF = $fields['regno'] ?? ['x' => 15.6, 'y' => 85.5, 'w' => 24.7, 'h' => 6.5];
			$idText = $regno !== '' ? $regno : '—';
			$idFs = $fit($idText, $idF, 2.55, 1.6, 0.52);
			$rowFs = 2.2;
			$infoY = (float) ($namesF['y'] ?? 49.6);
			$infoH = ((float) ($yearF['y'] ?? 66.2) + (float) ($yearF['h'] ?? 7.6)) - $infoY;
			$infoF = ['x' => $namesF['x'] ?? 42.8, 'y' => $infoY, 'w' => $namesF['w'] ?? 54.5, 'h' => $infoH];
			$passTpl = CardLayout::isWisdomPrimaryStudent($student)
				? \App\Libraries\WisdomCardRenderer::TEMPLATE_PRIMARY
				: \App\Libraries\WisdomCardRenderer::TEMPLATE;
			$tplBg = asset_card_img_src($passTpl, CardLayout::WISDOM_CHROME, 1800, 1200);
		?>
			<?php if ($tplBg): ?>
			<img class="card-bg" src="<?= $tplBg; ?>" alt="">
			<?php elseif ($wisdomChromeSrc !== ''): ?>
			<img class="card-bg" src="<?= $wisdomChromeSrc; ?>" alt="">
			<?php endif; ?>
			<div class="cf-photo" style="<?= CardLayout::boxStyle($photoF, 3); ?>">
				<img src="<?= $photoSrc; ?>" alt="">
			</div>
			<div class="w-info-wrap" style="<?= CardLayout::boxStyle($infoF, 5); ?>overflow:visible;">
				<table class="w-info" style="font-size:<?= number_format($rowFs, 2, '.', ''); ?>mm;">
					<tr><td class="k">NAME</td><td class="c">:</td><td class="v"><?= esc($fullName !== '' ? $fullName : '—'); ?></td></tr>
					<tr><td class="k">CLASS</td><td class="c">:</td><td class="v"><?= esc($classLabel !== '' ? $classLabel : '—'); ?></td></tr>
					<tr><td class="k">ACADEMIC YEAR</td><td class="c">:</td><td class="v"><?= esc($values['header1']); ?></td></tr>
				</table>
			</div>
			<div class="cf-center w-id" data-max="2.70" data-min="1.5" style="<?= CardLayout::boxStyle($idF, 4); ?>overflow:visible;font-size:<?= number_format($idFs, 2, '.', ''); ?>mm;">
				<?= esc($idText); ?>
			</div>
		<?php else: ?>

		<?php if (CardLayout::isVisible($fields, 'logo')):
			$f = $fields['logo']; ?>
			<div class="cf-logo" style="<?= CardLayout::boxStyle($f, 3); ?>border-radius:1mm;padding:0.4mm;">
				<?php if ($logoSrc): ?><img src="<?= $logoSrc; ?>" alt=""><?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if (CardLayout::isVisible($fields, 'photo')):
			$f = $fields['photo']; ?>
			<div class="cf-photo" style="<?= CardLayout::boxStyle($f, 3); ?>border-radius:1mm;">
				<img src="<?= $photoSrc; ?>" alt="">
			</div>
		<?php endif; ?>

		<?php foreach (['school_name', 'header1', 'header2'] as $key):
			if (!CardLayout::isVisible($fields, $key)) continue;
			$f = $fields[$key];
			$val = $values[$key] ?? '—';
			$max = $key === 'school_name' ? ($isPainted ? 4.0 : 3.6) : 2.4;
			$fs = $fit($val, $f, $max, 1.3, 0.50);
			$base = $isPainted ? 'cf-center' : 'cf';
			$cls = $key === 'school_name' ? $base . ' cf-school' : ($key === 'header1' || $key === 'header2' ? $base . ' cf-header' : $base);
		?>
			<div class="<?= $cls; ?>" data-max="<?= number_format($max, 2, '.', ''); ?>" data-min="1.2" style="<?= CardLayout::boxStyle($f, 2); ?>font-size:<?= number_format($fs, 2, '.', ''); ?>mm;">
				<span class="val"><?= esc($val); ?></span>
			</div>
		<?php endforeach; ?>

		<?php if (CardLayout::isVisible($fields, 'badge')):
			$f = $fields['badge'];
			$val = $values['badge'];
			$fs = $fit($val, $f, 2.8, 1.4, 0.55);
		?>
			<div class="cf-center cf-badge" data-max="2.8" data-min="1.3" style="<?= CardLayout::boxStyle($f, 2); ?>font-size:<?= number_format($fs, 2, '.', ''); ?>mm;border-radius:0;left:0;width:100%;">
				<?= esc($val); ?>
			</div>
		<?php endif; ?>

		<?php foreach ($labeled as $key):
			if (!CardLayout::isVisible($fields, $key)) continue;
			$f = $fields[$key];
			$lab = $labels[$key] ?? ucfirst($key);
			$val = $values[$key] ?? '';
			$line = $lab . ' ' . $val;
			$max = $key === 'names' ? 3.0 : 2.4;
			$fs = $fit($line, $f, $max, 1.25, 0.48);
		?>
			<div class="cf" data-max="<?= number_format($max, 2, '.', ''); ?>" data-min="1.2" style="<?= CardLayout::boxStyle($f, 2); ?>font-size:<?= number_format($fs, 2, '.', ''); ?>mm;">
				<span class="lab"><?= esc($lab); ?></span><span class="val"><?= esc($val); ?></span>
			</div>
		<?php endforeach; ?>

		<?php if (CardLayout::isVisible($fields, 'moto')):
			$f = $fields['moto'];
			$val = $values['moto'];
			$fs = $fit($val, $f, 2.4, 1.3, 0.52);
		?>
			<div class="cf-center cf-moto" data-max="2.4" data-min="1.2" style="<?= CardLayout::boxStyle($f, 2); ?>font-size:<?= number_format($fs, 2, '.', ''); ?>mm;">
				<?= esc($val); ?>
			</div>
		<?php endif; ?>
		<?php endif; ?>
	</div>
<div class="page-break"></div>
<?php endforeach; ?>
