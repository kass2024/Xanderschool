<?php
$lang = ($lang ?? 'en') === 'rw' ? 'rw' : 'en';
$envelope = $envelope ?? null;
$expired = !$envelope || !empty($envelope['expired']);
$token = (string) ($envelope['token'] ?? '');
$docs = [];
if (!$expired && $envelope) {
	foreach ($envelope['docs'] as $doc) {
		$id = (int) $doc['student_id'];
		$docs[] = [
			'id' => $id,
			'student' => (string) $doc['student'],
			'parent' => (string) $doc['parent'],
			'regno' => (string) $doc['regno'],
			'signed' => !empty($doc['signed']),
			'file' => base_url('consent-sign/' . $token . '/file/' . $id),
			'sign' => base_url('consent-sign/' . $token . '/sign/' . $id),
			'download' => base_url('consent-sign/' . $token . '/download/' . $id),
		];
	}
}
$pack = [
	'lang' => $lang,
	'school' => (string) ($envelope['school'] ?? ''),
	'expires' => (string) ($envelope['expires_at'] ?? ''),
	'downloadAll' => $token !== '' ? base_url('consent-sign/' . $token . '/download-all') : '',
	'docs' => $docs,
];
$toggle = base_url('consent-sign/' . $token);
$copy = $lang === 'rw' ? [
	'brand' => 'Gusinya',
	'title' => 'Sinyisha uburenganzira',
	'intro' => 'Soma impapuro zose, usinye umurongo w\'umukono, kandi bizabikwa ako kanya.',
	'docs' => 'Impapuro',
	'sign' => 'Sinyisha',
	'signed' => 'Byasinywe',
	'signAgain' => 'Ongera usinye',
	'download' => 'Kuramo iyi',
	'downloadAll' => 'Kuramo zose',
	'draw' => 'Shushanya',
	'type' => 'Andika',
	'clear' => 'Siba',
	'adopt' => 'Emeza umukono',
	'cancel' => 'Reka',
	'hint' => 'Umukono uba ku murongo w\'umukono. Ifishi ibikwa ako kanya.',
	'saved' => 'Byasinywe kandi byabitswe.',
	'next' => 'Ifishi ikurikira',
	'done' => 'Impapuro zose zasinywe. Urashobora kuzikuramo imwe cyangwa zose.',
	'empty' => 'Shushanya umukono mbere.',
	'fail' => 'Umukono ntubashije kubikwa.',
	'loading' => 'Ifishi irimo gufunguka…',
	'previewFail' => 'Ifishi ntibashije kwerekana. Urashobora kuyikuramo, cyangwa ukande Sinyisha kugira ngo umukono ubikwe ku murongo.',
	'expired' => 'Iyi link yarangiye. Saba ishuri indi link nshya.',
	'missing' => 'Nta ifishi iri kuri iyi link.',
	'as' => 'Usinya nk\'',
	'page' => 'Paji',
] : [
	'brand' => 'Sign',
	'title' => 'Sign the consent form',
	'intro' => 'Read every form, sign on the signature line, and it saves immediately.',
	'docs' => 'Documents',
	'sign' => 'Sign',
	'signed' => 'Signed',
	'signAgain' => 'Sign again',
	'download' => 'Download this',
	'downloadAll' => 'Download all',
	'draw' => 'Draw',
	'type' => 'Type',
	'clear' => 'Clear',
	'adopt' => 'Adopt and sign',
	'cancel' => 'Cancel',
	'hint' => 'Your signature is placed on the signature line. The form saves immediately.',
	'saved' => 'Signed and saved.',
	'next' => 'Next form',
	'done' => 'Every form is signed. Download one form, or download all of them.',
	'empty' => 'Draw your signature first.',
	'fail' => 'The signature could not be saved.',
	'loading' => 'Opening the form…',
	'previewFail' => 'The preview could not load. You can still download the form, or press Sign to save a signature on the signature line.',
	'expired' => 'This link has expired. Ask the school for a new one.',
	'missing' => 'There is no form on this link.',
	'as' => 'Signing as',
	'page' => 'Page',
];
?>
<!doctype html>
<html lang="<?= $lang === 'rw' ? 'rw' : 'en'; ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?= esc($copy['title']); ?></title>
	<style>
		:root { --ink:#102033; --yellow:#f5b400; --paper:#f4f1ea; --line:#d7deE8; }
		* { box-sizing: border-box; }
		html, body { margin: 0; height: 100%; }
		body { font-family: "Segoe UI", sans-serif; color: var(--ink); background: #e7edf2; }
		button, input { font: inherit; }
		.top { height: 64px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 16px; background: #fff; border-bottom: 1px solid var(--line); }
		.brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
		.mark { width: 34px; height: 34px; border-radius: 8px; background: var(--ink); color: #fff; display: grid; place-items: center; font-weight: 700; }
		.brand h1 { margin: 0; font-size: 16px; }
		.brand p { margin: 2px 0 0; color: #64748b; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.top-actions, .lang { display: flex; gap: 8px; align-items: center; }
		.lang a { color: #64748b; text-decoration: none; font-size: 13px; }
		.lang a.on { color: var(--ink); font-weight: 700; }
		.btn { border: 1px solid #cbd5e1; background: #fff; color: var(--ink); border-radius: 8px; padding: 8px 12px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
		.btn.primary { background: var(--yellow); border-color: #e2a400; font-weight: 700; }
		.btn.dark { background: var(--ink); color: #fff; border-color: var(--ink); }
		.btn:disabled { opacity: .55; cursor: default; }
		.shell { height: calc(100% - 64px); display: grid; grid-template-columns: 280px 1fr; }
		.docs { background: #fff; border-right: 1px solid var(--line); overflow: auto; padding: 14px; }
		.docs h2 { margin: 0 0 10px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
		.doc { width: 100%; text-align: left; border: 1px solid var(--line); background: #fff; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; cursor: pointer; }
		.doc.on { border-color: var(--ink); box-shadow: inset 3px 0 0 var(--yellow); }
		.doc b { display: block; font-size: 14px; }
		.doc span { display: block; color: #64748b; font-size: 12px; margin-top: 3px; }
		.tag { display: inline-block; margin-top: 6px; border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
		.tag.wait { background: #fff4cc; color: #8a5a00; }
		.tag.ok { background: #dcfce7; color: #166534; }
		.stage-wrap { background: #5c6770; overflow: auto; position: relative; }
		.stage { width: min(860px, calc(100% - 28px)); margin: 18px auto 80px; }
		.sheet { position: relative; background: #fff; margin: 0 auto 16px; box-shadow: 0 10px 28px rgba(0,0,0,.25); }
		.sheet canvas { display: block; width: 100%; height: auto; }
		.sign-tab { position: absolute; z-index: 2; background: var(--yellow); color: #1f2937; border: 0; border-radius: 4px 4px 0 0; font-weight: 800; letter-spacing: .04em; cursor: pointer; box-shadow: 0 6px 16px rgba(0,0,0,.2); }
		.status { position: sticky; top: 0; z-index: 3; margin: 0 auto; width: min(860px, calc(100% - 28px)); }
		.toast { margin-top: 12px; background: #102033; color: #fff; border-radius: 10px; padding: 10px 12px; display: flex; justify-content: space-between; gap: 10px; align-items: center; }
		.note, .empty { max-width: 560px; margin: 48px auto; background: #fff; border-radius: 16px; padding: 28px; text-align: center; }
		.modal { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: center; justify-content: center; padding: 16px; z-index: 20; }
		.modal.show { display: flex; }
		.card { width: min(640px, 100%); background: #fff; border-radius: 16px; overflow: hidden; }
		.card header, .card footer { padding: 14px 16px; display: flex; justify-content: space-between; gap: 8px; align-items: center; }
		.card header { border-bottom: 1px solid var(--line); }
		.card footer { border-top: 1px solid var(--line); }
		.card h3 { margin: 0; font-size: 18px; }
		.modes { display: flex; gap: 6px; }
		.modes button { border: 0; background: #eef2f6; border-radius: 8px; padding: 6px 10px; cursor: pointer; }
		.modes button.on { background: var(--ink); color: #fff; }
		.pad-wrap { padding: 16px; background: var(--paper); }
		canvas#pad { width: 100%; height: 180px; background: #fff; border: 1px dashed #b7c0cc; border-radius: 12px; touch-action: none; cursor: crosshair; }
		#typed { width: 100%; margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; }
		.hint { color: #64748b; font-size: 13px; padding: 0 16px 12px; }
		@media (max-width: 800px) {
			.shell { grid-template-columns: 1fr; height: auto; }
			.docs { display: flex; gap: 8px; overflow-x: auto; border-right: 0; border-bottom: 1px solid var(--line); }
			.docs h2 { display: none; }
			.doc { min-width: 210px; margin: 0; }
			.top { height: auto; flex-wrap: wrap; padding: 10px; }
			.stage-wrap { min-height: 70vh; }
			.hide-sm { display: none; }
		}
	</style>
</head>
<body>
<?php if ($expired || $docs === []): ?>
	<div class="empty">
		<div class="mark" style="margin:0 auto 12px;">S</div>
		<h1><?= esc($copy['title']); ?></h1>
		<p><?= esc($expired ? $copy['expired'] : $copy['missing']); ?></p>
	</div>
<?php else: ?>
	<header class="top">
		<div class="brand">
			<div class="mark">S</div>
			<div>
				<h1><?= esc($copy['title']); ?></h1>
				<p><?= esc($pack['school'] !== '' ? $pack['school'] . ' · ' . $copy['intro'] : $copy['intro']); ?></p>
			</div>
		</div>
		<div class="top-actions">
			<div class="lang">
				<a class="<?= $lang === 'en' ? 'on' : ''; ?>" href="<?= esc($toggle . '?lang=en'); ?>">EN</a>
				<a class="<?= $lang === 'rw' ? 'on' : ''; ?>" href="<?= esc($toggle . '?lang=rw'); ?>">RW</a>
			</div>
			<button type="button" class="btn primary" id="signBtn"><?= esc($copy['sign']); ?></button>
			<a class="btn" id="downloadOne" href="#"><?= esc($copy['download']); ?></a>
			<a class="btn dark" id="downloadAll" href="<?= esc($pack['downloadAll']); ?>"><?= esc($copy['downloadAll']); ?></a>
		</div>
	</header>
	<div class="shell">
		<aside class="docs">
			<h2><?= esc($copy['docs']); ?></h2>
			<div id="docList"></div>
		</aside>
		<main class="stage-wrap" id="stageWrap">
			<div class="status" id="status"></div>
			<div class="stage" id="stage"><p class="note"><?= esc($copy['loading']); ?></p></div>
		</main>
	</div>
	<div class="modal" id="modal">
		<div class="card" role="dialog" aria-modal="true">
			<header>
				<h3 id="modalTitle"><?= esc($copy['sign']); ?></h3>
				<div class="modes">
					<button type="button" class="on" id="modeDraw"><?= esc($copy['draw']); ?></button>
					<button type="button" id="modeType"><?= esc($copy['type']); ?></button>
				</div>
			</header>
			<div class="pad-wrap">
				<canvas id="pad" width="640" height="180"></canvas>
				<input id="typed" type="text" maxlength="80" autocomplete="off" hidden>
			</div>
			<p class="hint" id="modalHint"></p>
			<footer>
				<button type="button" class="btn" id="clearBtn"><?= esc($copy['clear']); ?></button>
				<span style="flex:1"></span>
				<button type="button" class="btn" id="cancelBtn"><?= esc($copy['cancel']); ?></button>
				<button type="button" class="btn primary" id="adoptBtn"><?= esc($copy['adopt']); ?></button>
			</footer>
		</div>
	</div>
	<script src="<?= base_url('assets/vendor/pdfjs/pdf.min.js'); ?>"></script>
	<script>
	(function () {
		var PACK = <?= json_encode($pack, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
		var COPY = <?= json_encode($copy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
		var SIGN = { x: 78, y: 348, w: 200, h: 44 };
		var current = 0;
		var mode = 'draw';
		var drawing = false;
		var pad = document.getElementById('pad');
		var ctx = pad.getContext('2d');
		var list = document.getElementById('docList');
		var stage = document.getElementById('stage');
		var status = document.getElementById('status');
		var typed = document.getElementById('typed');

		function doc() { return PACK.docs[current]; }
		function unsignedLeft() {
			return PACK.docs.some(function (item) { return !item.signed; });
		}
		function renderList() {
			list.innerHTML = '';
			PACK.docs.forEach(function (item, index) {
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'doc' + (index === current ? ' on' : '');
				button.innerHTML = '<b></b><span></span><em class="tag"></em>';
				button.querySelector('b').textContent = item.student;
				button.querySelector('span').textContent = item.parent || item.regno;
				var tag = button.querySelector('em');
				tag.textContent = item.signed ? COPY.signed : COPY.sign;
				tag.className = 'tag ' + (item.signed ? 'ok' : 'wait');
				button.addEventListener('click', function () { openDoc(index); });
				list.appendChild(button);
			});
			document.getElementById('downloadOne').href = doc().download;
			document.getElementById('signBtn').textContent = doc().signed ? COPY.signAgain : COPY.sign;
		}
		function toast(message, next) {
			status.innerHTML = '';
			var box = document.createElement('div');
			box.className = 'toast';
			var text = document.createElement('span');
			text.textContent = message;
			box.appendChild(text);
			if (next !== null && next !== undefined) {
				var button = document.createElement('button');
				button.className = 'btn primary';
				button.type = 'button';
				button.textContent = COPY.next;
				button.addEventListener('click', function () { openDoc(next); });
				box.appendChild(button);
			}
			status.appendChild(box);
		}
		function openDoc(index) {
			current = index;
			renderList();
			stage.innerHTML = '<p class="note">' + COPY.loading + '</p>';
			if (!window.pdfjsLib) {
				stage.innerHTML = '<p class="note">' + COPY.previewFail + '</p>';
				return;
			}
			pdfjsLib.GlobalWorkerOptions.workerSrc = <?= json_encode(base_url('assets/vendor/pdfjs/pdf.worker.min.js')); ?>;
			pdfjsLib.getDocument({ url: doc().file + '?v=' + Date.now(), disableRange: true, disableStream: true }).promise.then(function (pdf) {
				stage.innerHTML = '';
				var width = Math.min(820, stage.parentElement.clientWidth - 28);
				var chain = Promise.resolve();
				for (var pageNo = 1; pageNo <= pdf.numPages; pageNo++) {
					(function (pageNo) {
						chain = chain.then(function () {
							return pdf.getPage(pageNo).then(function (page) {
								var base = page.getViewport({ scale: 1 });
								var viewport = page.getViewport({ scale: width / base.width });
								var sheet = document.createElement('div');
								sheet.className = 'sheet';
								var canvas = document.createElement('canvas');
								canvas.width = viewport.width;
								canvas.height = viewport.height;
								sheet.appendChild(canvas);
								sheet.style.width = viewport.width + 'px';
								stage.appendChild(sheet);
								return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise.then(function () {
									if (pageNo !== 2) return;
									var rect = viewport.convertToViewportRectangle([SIGN.x, SIGN.y, SIGN.x + SIGN.w, SIGN.y + SIGN.h]);
									var tab = document.createElement('button');
									tab.type = 'button';
									tab.className = 'sign-tab';
									tab.textContent = doc().signed ? COPY.signed : COPY.sign;
									tab.style.left = Math.min(rect[0], rect[2]) + 'px';
									tab.style.top = Math.min(rect[1], rect[3]) + 'px';
									tab.style.width = Math.abs(rect[2] - rect[0]) + 'px';
									tab.style.height = Math.max(28, Math.abs(rect[3] - rect[1])) + 'px';
									tab.addEventListener('click', openModal);
									sheet.appendChild(tab);
								});
							});
						});
					})(pageNo);
				}
				return chain;
			}).catch(function () {
				stage.innerHTML = '<p class="note">' + COPY.previewFail + '</p>';
			});
		}
		function inkCount() {
			var data = ctx.getImageData(0, 0, pad.width, pad.height).data;
			var ink = 0;
			for (var i = 3; i < data.length; i += 16) {
				if (data[i] > 20) ink++;
			}
			return ink;
		}
		function clearPad() {
			ctx.clearRect(0, 0, pad.width, pad.height);
			if (mode === 'type') drawTyped();
		}
		function drawTyped() {
			ctx.clearRect(0, 0, pad.width, pad.height);
			var text = typed.value.trim();
			if (!text) return;
			var size = 72;
			ctx.fillStyle = '#1e3a8a';
			ctx.textBaseline = 'middle';
			do {
				ctx.font = size + 'px "Segoe Script","Brush Script MT",cursive';
				size -= 2;
			} while (ctx.measureText(text).width > pad.width - 28 && size > 28);
			ctx.fillText(text, 16, pad.height / 2);
		}
		function point(event) {
			var rect = pad.getBoundingClientRect();
			var src = event.touches ? event.touches[0] : event;
			return {
				x: (src.clientX - rect.left) * (pad.width / rect.width),
				y: (src.clientY - rect.top) * (pad.height / rect.height)
			};
		}
		function startDraw(event) {
			if (mode !== 'draw') return;
			drawing = true;
			var p = point(event);
			ctx.strokeStyle = '#1e3a8a';
			ctx.lineWidth = 2.6;
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';
			ctx.beginPath();
			ctx.moveTo(p.x, p.y);
			event.preventDefault();
		}
		function moveDraw(event) {
			if (!drawing) return;
			var p = point(event);
			ctx.lineTo(p.x, p.y);
			ctx.stroke();
			event.preventDefault();
		}
		function endDraw() { drawing = false; }
		pad.addEventListener('mousedown', startDraw);
		pad.addEventListener('mousemove', moveDraw);
		window.addEventListener('mouseup', endDraw);
		pad.addEventListener('touchstart', startDraw, { passive: false });
		pad.addEventListener('touchmove', moveDraw, { passive: false });
		pad.addEventListener('touchend', endDraw);
		function openModal() {
			document.getElementById('modalTitle').textContent = (doc().parent ? COPY.as + ' ' + doc().parent : COPY.sign);
			document.getElementById('modalHint').textContent = COPY.hint;
			typed.value = doc().parent || '';
			typed.hidden = mode !== 'type';
			clearPad();
			document.getElementById('modal').classList.add('show');
		}
		function closeModal() { document.getElementById('modal').classList.remove('show'); }
		document.getElementById('signBtn').addEventListener('click', openModal);
		document.getElementById('cancelBtn').addEventListener('click', closeModal);
		document.getElementById('clearBtn').addEventListener('click', clearPad);
		document.getElementById('modeDraw').addEventListener('click', function () {
			mode = 'draw';
			this.classList.add('on');
			document.getElementById('modeType').classList.remove('on');
			typed.hidden = true;
			clearPad();
		});
		document.getElementById('modeType').addEventListener('click', function () {
			mode = 'type';
			this.classList.add('on');
			document.getElementById('modeDraw').classList.remove('on');
			typed.hidden = false;
			if (!typed.value) typed.value = doc().parent || '';
			drawTyped();
		});
		typed.addEventListener('input', drawTyped);
		document.getElementById('adoptBtn').addEventListener('click', function () {
			if (mode === 'type') drawTyped();
			if (inkCount() < 8) {
				document.getElementById('modalHint').textContent = COPY.empty;
				return;
			}
			var button = this;
			button.disabled = true;
			var body = new URLSearchParams();
			body.set('image', pad.toDataURL('image/png'));
			fetch(doc().sign, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
				.then(function (res) {
					button.disabled = false;
					if (!res.ok || !res.data || !res.data.ok) {
						document.getElementById('modalHint').textContent = (res.data && res.data.error) || COPY.fail;
						return;
					}
					doc().signed = true;
					closeModal();
					var next = null;
					PACK.docs.forEach(function (item, index) {
						if (next === null && index !== current && !item.signed) next = index;
					});
					toast(next === null ? COPY.done : COPY.saved, next);
					openDoc(current);
				})
				.catch(function () {
					button.disabled = false;
					document.getElementById('modalHint').textContent = COPY.fail;
				});
		});
		var start = 0;
		PACK.docs.some(function (item, index) {
			if (!item.signed) { start = index; return true; }
			return false;
		});
		openDoc(start);
	})();
	</script>
<?php endif; ?>
</body>
</html>
