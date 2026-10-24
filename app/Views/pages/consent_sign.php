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
			'stamp' => (string) ($doc['signed_at'] ?? ''),
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
	'intro' => 'Hitamo umunyeshuri, sinyisha ifishi, kandi izagaragara yemewe.',
	'docs' => 'Abanyeshuri',
	'find' => 'Shaka umunyeshuri',
	'signedOf' => 'byasinywe',
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
	'intro' => 'Choose a student, sign that form, and the name turns green.',
	'docs' => 'Students',
	'find' => 'Find a student',
	'signedOf' => 'signed',
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
		.top { min-height: 64px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 10px 16px; background: #fff; border-bottom: 1px solid var(--line); }
		.brand { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1 1 260px; }
		.mark { width: 34px; height: 34px; border-radius: 8px; background: var(--ink); color: #fff; display: grid; place-items: center; font-weight: 700; flex: 0 0 auto; }
		.brand h1 { margin: 0; font-size: 16px; }
		.brand p { margin: 2px 0 0; color: #64748b; font-size: 12px; }
		.top-actions, .lang { display: flex; gap: 8px; align-items: center; }
		.lang a { color: #64748b; text-decoration: none; font-size: 13px; }
		.lang a.on { color: var(--ink); font-weight: 700; }
		.btn { border: 1px solid #cbd5e1; background: #fff; color: var(--ink); border-radius: 8px; padding: 8px 12px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
		.btn.primary { background: var(--yellow); border-color: #e2a400; font-weight: 700; }
		.btn.dark { background: var(--ink); color: #fff; border-color: var(--ink); }
		.btn:disabled { opacity: .55; cursor: default; }
		.shell { height: calc(100vh - 86px); min-height: 420px; display: grid; grid-template-columns: 300px 1fr; }
		.docs { background: #fff; border-right: 1px solid var(--line); overflow: auto; padding: 14px; }
		.docs h2 { margin: 0 0 8px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
		.find { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; margin-bottom: 8px; }
		.count { margin: 0 0 10px; color: #64748b; font-size: 12px; font-weight: 700; }
		.doc { width: 100%; text-align: left; border: 1px solid var(--line); background: #fff; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; cursor: pointer; }
		.doc.on { border-color: var(--ink); box-shadow: inset 4px 0 0 var(--yellow); }
		.doc.done { background: #dcfce7; border-color: #86efac; }
		.doc.done.on { box-shadow: inset 4px 0 0 #16a34a; }
		.doc.done b { color: #14532d; }
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
		.sign-ink { position: absolute; z-index: 2; object-fit: contain; object-position: left bottom; pointer-events: none; background: transparent; }
		.status { position: sticky; top: 0; z-index: 3; margin: 0 auto; width: min(860px, calc(100% - 28px)); }
		.toast { margin-top: 12px; background: #102033; color: #fff; border-radius: 10px; padding: 10px 12px; display: flex; justify-content: space-between; gap: 10px; align-items: center; }
		.note, .empty, .stage-msg { max-width: 560px; margin: 48px auto; background: #fff; border-radius: 16px; padding: 28px; text-align: center; }
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
			<input id="docSearch" class="find" type="search" placeholder="<?= esc($copy['find']); ?>" autocomplete="off">
			<p class="count" id="docCount"></p>
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
		var query = '';
		var mode = 'draw';
		var pdfCache = {};
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
		function signedCount() {
			var n = 0;
			PACK.docs.forEach(function (item) { if (item.signed) n++; });
			return n;
		}
		function renderList() {
			list.innerHTML = '';
			var shown = 0;
			PACK.docs.forEach(function (item, index) {
				var blob = (item.student + ' ' + (item.parent || '') + ' ' + item.regno).toLowerCase();
				if (query && blob.indexOf(query) === -1) return;
				shown++;
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'doc' + (index === current ? ' on' : '') + (item.signed ? ' done' : '');
				button.innerHTML = '<b></b><span></span><em class="tag"></em>';
				button.querySelector('b').textContent = item.student;
				button.querySelector('span').textContent = item.parent || item.regno;
				var tag = button.querySelector('em');
				tag.textContent = item.signed ? COPY.signed : COPY.sign;
				tag.className = 'tag ' + (item.signed ? 'ok' : 'wait');
				button.addEventListener('click', function () { openDoc(index); });
				list.appendChild(button);
			});
			if (!shown) {
				var empty = document.createElement('p');
				empty.className = 'count';
				empty.textContent = COPY.find;
				list.appendChild(empty);
			}
			var count = document.getElementById('docCount');
			if (count) count.textContent = signedCount() + ' / ' + PACK.docs.length + ' ' + COPY.signedOf;
			document.getElementById('downloadOne').href = doc().download;
			document.getElementById('signBtn').textContent = doc().signed ? COPY.signAgain : COPY.sign;
			var chosen = list.querySelector('.doc.on');
			if (chosen && chosen.scrollIntoView) chosen.scrollIntoView({ block: 'nearest' });
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
		function pageWidth() {
			var box = document.getElementById('stageWrap');
			var w = box ? box.clientWidth : 0;
			if (!w || w < 240) w = 860;
			return Math.max(320, Math.min(820, w - 36));
		}
		function fileKey(item) {
			return item.file + '|' + (item.stamp || (item.signed ? 's' : 'u'));
		}
		function loadBytes(item) {
			var key = fileKey(item);
			if (pdfCache[key]) return pdfCache[key];
			pdfCache[key] = fetch(item.file + '?v=' + encodeURIComponent(item.stamp || (item.signed ? 's' : 'u'))).then(function (res) {
				if (!res.ok) throw new Error('pdf');
				return res.arrayBuffer();
			}).catch(function (err) {
				delete pdfCache[key];
				throw err;
			});
			return pdfCache[key];
		}
		function prefetchAround(index) {
			var ahead = 0;
			for (var i = index + 1; i < PACK.docs.length && ahead < 2; i++) {
				if (PACK.docs[i].signed) continue;
				loadBytes(PACK.docs[i]).catch(function () {});
				ahead++;
			}
		}
		function placeSign(sheet, viewport) {
			var rect = viewport.convertToViewportRectangle([SIGN.x, SIGN.y, SIGN.x + SIGN.w, SIGN.y + SIGN.h]);
			var left = Math.min(rect[0], rect[2]);
			var top = Math.min(rect[1], rect[3]);
			var width = Math.abs(rect[2] - rect[0]);
			var height = Math.max(28, Math.abs(rect[3] - rect[1]));
			if (doc().signed && !doc().ink) return;
			if (doc().ink) {
				var img = document.createElement('img');
				img.className = 'sign-ink';
				img.alt = '';
				img.src = doc().ink;
				img.style.left = left + 'px';
				img.style.top = top + 'px';
				img.style.width = width + 'px';
				img.style.height = height + 'px';
				sheet.appendChild(img);
				return;
			}
			var tab = document.createElement('button');
			tab.type = 'button';
			tab.className = 'sign-tab';
			tab.textContent = COPY.sign;
			tab.style.left = left + 'px';
			tab.style.top = top + 'px';
			tab.style.width = width + 'px';
			tab.style.height = height + 'px';
			tab.addEventListener('click', openModal);
			sheet.appendChild(tab);
		}
		function openDoc(index) {
			current = index;
			renderList();
			stage.innerHTML = '<p class="stage-msg">' + COPY.loading + '</p>';
			if (!window.pdfjsLib) {
				stage.innerHTML = '<p class="note">' + COPY.previewFail + '</p>';
				return;
			}
			var ticket = ++openDoc.ticket;
			var item = doc();
			if (item.signed) item.ink = '';
			window.clearTimeout(openDoc.timer);
			openDoc.timer = window.setTimeout(function () {
				if (ticket !== openDoc.ticket || stage.querySelector('canvas')) return;
				stage.innerHTML = '<p class="note">' + COPY.previewFail + '</p>';
			}, 20000);
			pdfjsLib.GlobalWorkerOptions.workerSrc = <?= json_encode(base_url('assets/vendor/pdfjs/pdf.worker.min.js')); ?>;
			loadBytes(item).then(function (buf) {
				if (ticket !== openDoc.ticket) return null;
				return pdfjsLib.getDocument({ data: buf.slice(0) }).promise;
			}).then(function (pdf) {
				if (!pdf || ticket !== openDoc.ticket) return;
				var width = pageWidth();
				var jobs = [];
				for (var pageNo = 1; pageNo <= pdf.numPages; pageNo++) {
					jobs.push(pdf.getPage(pageNo));
				}
				return Promise.all(jobs).then(function (pages) {
					if (ticket !== openDoc.ticket) return;
					stage.innerHTML = '';
					return Promise.all(pages.map(function (page, index) {
						var base = page.getViewport({ scale: 1 });
						var viewport = page.getViewport({ scale: width / base.width });
						var sheet = document.createElement('div');
						sheet.className = 'sheet';
						sheet.style.width = viewport.width + 'px';
						sheet.style.minHeight = viewport.height + 'px';
						var canvas = document.createElement('canvas');
						canvas.width = viewport.width;
						canvas.height = viewport.height;
						sheet.appendChild(canvas);
						stage.appendChild(sheet);
						return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise.then(function () {
							if (ticket !== openDoc.ticket || index !== 1) return;
							placeSign(sheet, viewport);
							window.clearTimeout(openDoc.timer);
						});
					}));
				});
			}).then(function () {
				if (ticket === openDoc.ticket) prefetchAround(index);
			}).catch(function () {
				if (ticket !== openDoc.ticket) return;
				stage.innerHTML = '<p class="note">' + COPY.previewFail + '</p>';
			});
		}
		openDoc.ticket = 0;
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
					doc().stamp = (res.data && res.data.signed_at) || String(Date.now());
					doc().ink = pad.toDataURL('image/png');
					closeModal();
					var old = stage.querySelector('.sign-tab');
					if (old) {
						var img = document.createElement('img');
						img.className = 'sign-ink';
						img.alt = '';
						img.src = doc().ink;
						img.style.left = old.style.left;
						img.style.top = old.style.top;
						img.style.width = old.style.width;
						img.style.height = old.style.height;
						old.replaceWith(img);
					} else {
						openDoc(current);
					}
					renderList();
					var next = null;
					PACK.docs.forEach(function (item, index) {
						if (next === null && index !== current && !item.signed) next = index;
					});
					toast(next === null ? COPY.done : COPY.saved, next);
				})
				.catch(function () {
					button.disabled = false;
					document.getElementById('modalHint').textContent = COPY.fail;
				});
		});
		document.getElementById('docSearch').addEventListener('input', function () {
			query = this.value.trim().toLowerCase();
			renderList();
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
