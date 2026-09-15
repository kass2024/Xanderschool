<?php
/**
 * CR80 staff cards — WYSIWYG from School Settings CardLayout (% boxes).
 * Full-bleed background, photo cover-fit, auto-scaled single-line text.
 */
helper('qonics');
use App\Libraries\CardLayout;

$tplKey = CardLayout::normalizeTemplate($card_template ?? 'ocean');
$orientation = CardLayout::normalizeOrientation($orientation ?? CardLayout::preferredOrientation($tplKey));
if (!empty($wisdom_staff_art)) {
	$orientation = 'portrait';
}
$layout = CardLayout::resolveStaff($card_layout ?? null, $tplKey, $orientation);
$fields = $layout['fields'];
$tplKey = $layout['template'];
$orientation = $layout['orientation'];
$isPortrait = $orientation === 'portrait';
$cardWmm = $isPortrait ? CardLayout::CR80_H_MM : CardLayout::CR80_W_MM;
$cardHmm = $isPortrait ? CardLayout::CR80_W_MM : CardLayout::CR80_H_MM;

$yearLabel = trim((string) ($theyear ?? ($year ?? date('Y'))));
$validityShort = preg_replace('/\s+/', '', $yearLabel);
if (stripos($validityShort, 'A.Y') === 0) {
	$validityShort = trim(substr($validityShort, 3));
}
$useCaps = !empty($capitalize);
$fmt = static function ($v) use ($useCaps) {
	$v = trim((string) $v);
	return $useCaps ? mb_strtoupper($v, 'UTF-8') : $v;
};

$main = !empty($main_color) ? $main_color : CardLayout::defaultAccent($tplKey);
$headerC = !empty($header_color) ? $header_color : $main;
$footerC = !empty($footer_color) ? $footer_color : $main;
$text = '#0f172a';

$isWisdomArt = !empty($wisdom_staff_art);
$isPainted = !$isWisdomArt && CardLayout::isPainted($tplKey);
$paint = !empty($paint_color) ? $paint_color : $main;
$tintLight = CardLayout::tint($paint, 0.82);
$tintMid = CardLayout::tint($paint, 0.55);

$logoSrc = !empty($logo)
	? asset_card_img_src('assets/images/logo/' . $logo, 'assets/images/fallback-logo.png', 480, 320)
	: asset_card_img_src(null, 'assets/images/fallback-logo.png', 480, 320);
$sigSrc = !empty($headmaster_signature)
	? asset_card_img_src('assets/images/signatures/' . $headmaster_signature, null, 320, 120)
	: '';

// Painted templates ship their own design — uploaded/AI backgrounds are never applied.
$bgFile = $isPainted ? '' : trim((string) ($background ?? ''));
$bgSrc = '';
if (empty($isWisdomArt) && strlen($bgFile) > 4) {
	$rel = 'assets/images/background/' . basename($bgFile);
	$abs = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
	if (is_file($abs)) {
		$bgSrc = asset_card_img_src($rel, null, $isPortrait ? 900 : 1400, $isPortrait ? 1400 : 900);
	}
}

$schoolName = $fmt($school_name ?? '');
$header1Val = $fmt(trim((string) ($header1 ?: ($moto ?? ''))));
$header2Val = $fmt(trim((string) ($header2 ?? '')));
$cardTitle = !empty($card_badge) ? $fmt($card_badge) : 'STAFF CARD';
$motoVal = $fmt(trim((string) ($moto ?: $schoolName)));
$headMaster = $fmt(trim((string) ($head_master ?? 'Headmaster')));

$labels = CardLayout::STAFF_FIELDS;

$icoPhone = '<svg viewBox="0 0 24 24" fill="#082060" xmlns="http://www.w3.org/2000/svg"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.2 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.3 1.1l-2.2 2.2z"/></svg>';
$icoEmail = '<svg viewBox="0 0 24 24" fill="none" stroke="#082060" stroke-width="2.2" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 7 9-7"/></svg>';
$icoId = '<svg viewBox="0 0 24 24" fill="none" stroke="#082060" stroke-width="2" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.2" cy="12" r="2.1" fill="#082060" stroke="none"/><path d="M13 10h6M13 13h5M13 16h3.5"/></svg>';

$fit = static function (string $text, array $f, float $max = 3.2, float $min = 1.35, float $factor = 0.52) use ($cardWmm, $cardHmm): float {
	return CardLayout::fitFontMm($text, (float)($f['w'] ?? 50), (float)($f['h'] ?? 6), $cardWmm, $cardHmm, $max, $min, $factor);
};
?>
<style>
	html, body { margin: 0; padding: 0; }
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
		border: <?= !empty($isWisdomArt) ? '0' : ($isPainted ? '0.7' : '0.45'); ?>mm solid <?= $main; ?>;
		background: <?= !empty($isWisdomArt) ? 'transparent' : '#f1f5f9'; ?>;
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
		object-fit: cover;
		object-position: center 12%;
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
	.ws-name {
		position: absolute; left: 5%; top: 47.8%; width: 90%; height: 6.2%;
		display: -webkit-box; display: flex; -webkit-box-align: center; align-items: center;
		-webkit-box-pack: center; justify-content: center;
		color: #082060; font-weight: 700; letter-spacing: 0.02em;
		white-space: nowrap; overflow: hidden; font-size: 3.8mm;
	}
	.ws-post-wrap {
		position: absolute; left: 6%; top: 54%; width: 88%; height: 4.4%;
	}
	.ws-gold-l, .ws-gold-r {
		position: absolute; top: 48%; width: 16%; height: 0.38mm; background: #c49a30;
	}
	.ws-gold-l { left: 0; }
	.ws-gold-r { right: 0; }
	.ws-post {
		position: absolute; left: 18%; top: 0; width: 64%; height: 100%;
		display: -webkit-box; display: flex; -webkit-box-align: center; align-items: center;
		-webkit-box-pack: center; justify-content: center;
		background: #082060; color: #ffffff; font-weight: 700;
		letter-spacing: 0.06em; border-radius: 10mm;
		white-space: nowrap; overflow: hidden; font-size: 2.6mm;
	}
	.ws-info {
		position: absolute; left: 6%; top: 59.4%; width: 88%;
	}
	.ws-row {
		position: relative; left: auto; width: 100%; height: 6.2mm;
		display: -webkit-box; display: flex; -webkit-box-align: center; align-items: center;
		background: #e4ecf8;
		border-radius: 3.2mm;
		padding: 0 2.2mm 0 1.4mm;
		margin: 0 0 1.1mm;
		border-top: none;
		white-space: nowrap; overflow: hidden;
		box-sizing: border-box;
	}
	.ws-ico {
		width: 4.4mm; height: 4.4mm; border-radius: 50%;
		background: #f4f7fc; margin-right: 1.6mm; flex-shrink: 0;
		display: -webkit-box; display: flex; -webkit-box-align: center; align-items: center;
		-webkit-box-pack: center; justify-content: center;
	}
	.ws-ico svg { width: 2.5mm; height: 2.5mm; display: block; }
	.ws-lab { color: #082060; font-size: 2.05mm; letter-spacing: 0.08em; font-weight: 700; width: 16mm; flex-shrink: 0; }
	.ws-split { width: 0.28mm; height: 3.1mm; background: #b0c0d8; margin: 0 2mm; flex-shrink: 0; }
	.ws-val { color: #082060; font-size: 2.55mm; font-weight: 700; text-align: left; margin-left: 0; }
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

<?php foreach ($staffs as $staff):
	$photoField = $fields['photo'] ?? ['w' => 36, 'h' => 30];
	$photoPxW = max(180, (int) round($cardWmm * ((float)($photoField['w'] ?? 36) / 100) * 12));
	$photoPxH = max(220, (int) round($cardHmm * ((float)($photoField['h'] ?? 30) / 100) * 12));
	$photoSrc = profile_photo_card_cover_src($staff['photo'] ?? '', $photoPxW, $photoPxH);
	$fullName = $fmt(trim(($staff['fname'] ?? '') . ' ' . ($staff['lname'] ?? '')));
	$postTitle = $fmt($staff['post_title'] ?? '—');
	$phoneLabel = $fmt(!empty($staff['phone']) ? $staff['phone'] : '—');
	$emailLabel = $fmt(!empty($staff['email']) ? $staff['email'] : '—');
	$staffId = $fmt((string) ($staff['id'] ?? '—'));
	$validDate = $fmt($validityShort !== '' ? $validityShort : date('Y'));

	$values = [
		'school_name' => $schoolName,
		'header1' => $header1Val !== '' ? $header1Val : '—',
		'header2' => $header2Val !== '' ? $header2Val : '—',
		'badge' => $cardTitle,
		'names' => $fullName,
		'post' => $postTitle,
		'phone' => $phoneLabel,
		'email' => $emailLabel,
		'staff_id' => $staffId,
		'moto' => $motoVal !== '' ? $motoVal : $schoolName,
	];
	$labeled = $isWisdomArt ? [] : ['names', 'post', 'phone', 'email', 'staff_id'];
	$wsRows = [];
	if ($isWisdomArt) {
		$fullName = preg_replace('/\s+/u', ' ', trim($fullName)) ?? $fullName;
		if (!empty($staff['phone'])) {
			$wsRows[] = ['PHONE', $phoneLabel, 'phone'];
		}
		if (!empty($staff['email'])) {
			$wsRows[] = ['EMAIL', trim((string) $staff['email']), 'email'];
		}
		if (($staff['id'] ?? '') !== '') {
			$wsRows[] = ['STAFF ID', (string) $staff['id'], 'id'];
		}
	}
	$cardBgSrc = $bgSrc;
	if ($isWisdomArt) {
		$rel = \App\Libraries\WisdomStaffCardRenderer::templateForStaff($staff);
		$abs = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
		if (is_file($abs)) {
			$cardBgSrc = asset_card_img_src($rel, null, 1080, 1712);
		}
	}
?>
	<div class="card">
		<?php if ($cardBgSrc !== ''): ?>
			<img class="card-bg" src="<?= $cardBgSrc; ?>" alt="">
		<?php endif; ?>
		<?php if ($isPainted): ?>
			<div class="paint">
				<div class="paint-l-light"></div>
				<div class="paint-l-dark"></div>
				<div class="paint-b-light"></div>
				<div class="paint-b-mid"></div>
				<div class="paint-frame"></div>
			</div>
		<?php endif; ?>

		<?php if (!$isWisdomArt && CardLayout::isVisible($fields, 'logo')):
			$f = $fields['logo']; ?>
			<div class="cf-logo" style="<?= CardLayout::boxStyle($f, 3); ?>border-radius:1mm;padding:0.4mm;">
				<?php if ($logoSrc): ?><img src="<?= $logoSrc; ?>" alt=""><?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if (CardLayout::isVisible($fields, 'photo') || $isWisdomArt):
			$f = $isWisdomArt ? ['x' => 26.8, 'y' => 17.4, 'w' => 46.4, 'h' => 27.3] : $fields['photo']; ?>
			<div class="cf-photo" style="<?= CardLayout::boxStyle($f, 3); ?>border-radius:50%;">
				<?php if ($photoSrc): ?><img src="<?= $photoSrc; ?>" alt=""><?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ($isWisdomArt): ?>
			<div class="ws-name"><?= esc($fullName); ?></div>
			<?php if ($postTitle !== '' && $postTitle !== '—'): ?>
				<div class="ws-post-wrap">
					<div class="ws-gold-l"></div>
					<div class="ws-post"><?= esc($postTitle); ?></div>
					<div class="ws-gold-r"></div>
				</div>
			<?php endif; ?>
			<?php if ($wsRows !== []): ?>
			<div class="ws-info">
			<?php foreach ($wsRows as $ws):
				$kind = $ws[2] ?? 'phone';
			?>
				<div class="ws-row">
					<span class="ws-ico"><?= $kind === 'email' ? $icoEmail : ($kind === 'id' ? $icoId : $icoPhone); ?></span>
					<span class="ws-lab"><?= esc($ws[0]); ?></span>
					<span class="ws-split"></span>
					<span class="ws-val"><?= esc($ws[1]); ?></span>
				</div>
			<?php endforeach; ?>
			</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php foreach ($isWisdomArt ? [] : ['school_name', 'header1', 'header2'] as $key):
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

		<?php if (!$isWisdomArt && CardLayout::isVisible($fields, 'badge')):
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
			$max = $key === 'names' ? 3.6 : 2.9;
			$fs = $fit($line, $f, $max, 1.4, 0.50);
		?>
			<div class="cf" data-max="<?= number_format($max, 2, '.', ''); ?>" data-min="1.2" style="<?= CardLayout::boxStyle($f, 2); ?>font-size:<?= number_format($fs, 2, '.', ''); ?>mm;">
				<span class="lab"><?= esc($lab); ?></span><span class="val"><?= esc($val); ?></span>
			</div>
		<?php endforeach; ?>

		<?php if (!$isWisdomArt && CardLayout::isVisible($fields, 'moto')):
			$f = $fields['moto'];
			$val = $values['moto'];
			$fs = $fit($val, $f, 2.4, 1.3, 0.52);
		?>
			<div class="cf-center cf-moto" data-max="2.4" data-min="1.2" style="<?= CardLayout::boxStyle($f, 2); ?>font-size:<?= number_format($fs, 2, '.', ''); ?>mm;">
				<?= esc($val); ?>
			</div>
		<?php endif; ?>
	</div>
<div class="page-break"></div>
<?php endforeach; ?>
