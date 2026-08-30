<link rel="stylesheet" type="text/css" href="<?= base_url('assets/plugins/dropzone/dropzone.min.css'); ?>">
<style>
	.sp-wrap { padding: 8px 16px 24px; }
	.sp-tabs {
		display: flex;
		gap: 8px;
		margin: 0 0 16px;
		border-bottom: 1px solid #e6e8ee;
		padding-bottom: 8px;
	}
	.sp-tab {
		border: 0;
		background: transparent;
		color: #5a6270;
		font-weight: 600;
		padding: 10px 16px;
		border-radius: 10px 10px 0 0;
		cursor: pointer;
	}
	.sp-tab.active {
		background: #111827;
		color: #fff;
	}
	.sp-pane { display: none; }
	.sp-pane.active { display: block; }
	.dropzone {
		border: 2px dashed rgba(0,0,0,0.3);
		margin: 0;
	}
	.dropzone .dz-preview .dz-error-message::after { border-bottom: 6px solid #ec2e51; }
	.dropzone .dz-preview .dz-error-message {
		opacity: 1;
		top: 93px;
		left: -10px;
		width: 140px;
		background: linear-gradient(to bottom, #f7587d, #5b0707);
		border-radius: 4px;
		text-align: center;
	}
	.dropzone .dz-preview .dz-image {
		border-radius: 4px;
		border: 1px solid;
	}
	.sp-studio {
		display: grid;
		grid-template-columns: 340px minmax(0, 1fr);
		gap: 16px;
		min-height: 640px;
	}
	.sp-list-card, .sp-cam-card {
		background: #fff;
		border: 1px solid #e5e7eb;
		border-radius: 16px;
		box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
		overflow: hidden;
	}
	.sp-list-head, .sp-cam-toolbar {
		padding: 14px 16px;
		border-bottom: 1px solid #eef0f4;
		background: linear-gradient(180deg, #fbfcfe, #f4f6fa);
	}
	.sp-list-head h3, .sp-cam-toolbar h3 {
		margin: 0 0 10px;
		font-size: 15px;
		font-weight: 700;
		color: #111827;
	}
	.sp-filters { display: grid; gap: 8px; }
	.sp-filters input, .sp-filters select, .sp-cam-toolbar select {
		width: 100%;
		border: 1px solid #d1d5db;
		border-radius: 10px;
		padding: 8px 10px;
		font-size: 13px;
		background: #fff;
	}
	.sp-filter-row { display: flex; gap: 8px; align-items: center; }
	.sp-chip {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 12px;
		color: #374151;
		white-space: nowrap;
	}
	.sp-students {
		max-height: 560px;
		overflow: auto;
	}
	.sp-student {
		display: flex;
		gap: 10px;
		align-items: center;
		width: 100%;
		border: 0;
		background: #fff;
		text-align: left;
		padding: 10px 14px;
		cursor: pointer;
		border-bottom: 1px solid #f1f5f9;
	}
	.sp-student:hover { background: #f8fafc; }
	.sp-student.selected {
		background: #111827;
		color: #fff;
	}
	.sp-student.selected .meta { color: #cbd5e1; }
	.sp-av {
		width: 42px;
		height: 42px;
		border-radius: 50%;
		object-fit: cover;
		background: #e5e7eb;
		border: 2px solid #fff;
		flex: 0 0 42px;
	}
	.sp-student .who { font-weight: 700; font-size: 13px; line-height: 1.2; }
	.sp-student .meta { font-size: 11px; color: #6b7280; }
	.sp-badge {
		margin-left: auto;
		font-size: 10px;
		font-weight: 700;
		padding: 3px 7px;
		border-radius: 999px;
		background: #fef3c7;
		color: #92400e;
	}
	.sp-student.has-photo .sp-badge {
		background: #dcfce7;
		color: #166534;
	}
	.sp-student.selected .sp-badge { background: rgba(255,255,255,.16); color: #fff; }
	.sp-empty { padding: 28px 16px; text-align: center; color: #6b7280; }
	.sp-cam-toolbar {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		align-items: center;
		justify-content: space-between;
	}
	.sp-cam-toolbar h3 { margin: 0; }
	.sp-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
	.sp-tools select { min-width: 220px; width: auto; }
	.sp-status {
		font-size: 12px;
		font-weight: 600;
		padding: 6px 10px;
		border-radius: 999px;
		background: #eef2ff;
		color: #3730a3;
	}
	.sp-status.ok { background: #dcfce7; color: #166534; }
	.sp-status.warn { background: #fef3c7; color: #92400e; }
	.sp-status.err { background: #fee2e2; color: #991b1b; }
	.sp-stage {
		display: grid;
		grid-template-columns: minmax(0, 1.15fr) 320px;
		gap: 16px;
		padding: 16px;
	}
	.sp-live-box, .sp-edit-box {
		position: relative;
		border-radius: 18px;
		overflow: hidden;
		min-height: 460px;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.sp-live-box {
		flex-direction: column;
		justify-content: center;
		align-items: center;
		padding: 16px 16px 48px;
		background: #0f172a;
	}
	.sp-square-stage {
		position: relative;
		width: 100%;
		max-width: 720px;
		aspect-ratio: 4 / 3;
		border-radius: 14px;
		overflow: hidden;
		background: #000;
		border: 1px solid rgba(255, 255, 255, 0.12);
		box-shadow: 0 18px 40px rgba(2, 6, 23, 0.35);
	}
	.sp-hd-badge {
		position: absolute;
		top: 12px;
		left: 12px;
		z-index: 2;
		font-size: 10px;
		font-weight: 800;
		letter-spacing: .08em;
		padding: 4px 8px;
		border-radius: 6px;
		background: rgba(15, 23, 42, 0.72);
		color: #fff;
	}
	#spVideo {
		transform: scaleX(-1);
		width: 100%;
		height: 100%;
		object-fit: cover;
		object-position: center center;
		display: block;
		background: #000;
	}
	#spEditCanvas {
		display: block;
		max-width: 100%;
	}
	.sp-live-hint {
		position: absolute;
		left: 0; right: 0; bottom: 14px;
		text-align: center;
		color: #cbd5e1;
		font-size: 12px;
		font-weight: 600;
		z-index: 2;
	}
	.sp-edit-box {
		background: #f8fafc;
	}
	.sp-edit-frame {
		position: relative;
		width: 280px;
		height: 280px;
		border-radius: 8px;
		overflow: hidden;
		box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
		background: #fff;
		border: 1px solid #e5e7eb;
		cursor: grab;
	}
	.sp-edit-frame:active { cursor: grabbing; }
	#spEditCanvas { width: 280px; height: 280px; }
	.sp-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		padding: 0 16px 8px;
	}
	.sp-btn {
		border: 0;
		border-radius: 10px;
		padding: 10px 14px;
		font-weight: 700;
		font-size: 13px;
		cursor: pointer;
	}
	.sp-btn-primary { background: #111827; color: #fff; }
	.sp-btn-capture { background: #059669; color: #fff; min-width: 140px; }
	.sp-btn-ghost { background: #e5e7eb; color: #111827; }
	.sp-btn:disabled { opacity: .45; cursor: not-allowed; }
	.sp-sliders {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
		gap: 10px 16px;
		padding: 8px 16px 16px;
	}
	.sp-sliders label {
		display: block;
		font-size: 11px;
		font-weight: 700;
		color: #4b5563;
		margin-bottom: 4px;
	}
	.sp-sliders input[type=range] { width: 100%; }
	.sp-selected {
		padding: 0 16px 12px;
		font-size: 13px;
		color: #374151;
	}
	.sp-selected strong { color: #111827; }
	@media (max-width: 1100px) {
		.sp-studio, .sp-stage { grid-template-columns: 1fr; }
		.sp-students { max-height: 280px; }
	}
</style>
<div class="sp-wrap">
	<div class="sp-tabs">
		<button type="button" class="sp-tab active" data-pane="live"><i class="fa fa-video"></i> Live camera</button>
		<button type="button" class="sp-tab" data-pane="upload"><i class="fa fa-cloud-upload-alt"></i> Upload files</button>
	</div>

	<div class="sp-pane active" id="spPaneLive">
		<div class="sp-studio">
			<div class="sp-list-card">
				<div class="sp-list-head">
					<h3>School students</h3>
					<div class="sp-filters">
						<input type="search" id="spSearch" placeholder="Search name or registration number">
						<div class="sp-filter-row">
							<select id="spClass">
								<option value="">All classes</option>
								<?php foreach (($photo_classes ?? []) as $class): ?>
									<option value="<?= (int) $class['id']; ?>"><?= esc($class['label']); ?></option>
								<?php endforeach; ?>
							</select>
							<label class="sp-chip"><input type="checkbox" id="spMissing"> Missing photo</label>
						</div>
					</div>
				</div>
				<div class="sp-students" id="spStudents"></div>
			</div>
			<div class="sp-cam-card">
				<div class="sp-cam-toolbar">
					<h3>USB webcam studio</h3>
					<div class="sp-tools">
						<select id="spCamera" title="Choose USB camera"></select>
						<button type="button" class="sp-btn sp-btn-primary" id="spStartCam"><i class="fa fa-play"></i> Start camera</button>
						<button type="button" class="sp-btn sp-btn-ghost" id="spStopCam">Stop</button>
						<span class="sp-status warn" id="spCamStatus">Camera idle</span>
					</div>
				</div>
				<div class="sp-selected" id="spPicked">Pick a student on the left, then capture a live portrait.</div>
				<div class="sp-stage">
					<div class="sp-live-box">
						<div class="sp-square-stage">
							<video id="spVideo" autoplay playsinline muted></video>
							<span class="sp-hd-badge">HD</span>
						</div>
						<div class="sp-live-hint">HD live preview — click Capture when ready</div>
					</div>
					<div class="sp-edit-box">
						<div class="sp-edit-frame" id="spFrame">
							<canvas id="spEditCanvas" width="1600" height="1600"></canvas>
						</div>
					</div>
				</div>
				<div class="sp-actions">
					<button type="button" class="sp-btn sp-btn-capture" id="spCapture" disabled><i class="fa fa-camera"></i> Capture</button>
					<button type="button" class="sp-btn sp-btn-ghost" id="spRetake" disabled>Retake</button>
					<button type="button" class="sp-btn sp-btn-ghost" id="spRotate" disabled>Rotate</button>
					<button type="button" class="sp-btn sp-btn-primary" id="spSave" disabled><i class="fa fa-save"></i> Save photo</button>
					<button type="button" class="sp-btn sp-btn-ghost" id="spAuto">Auto enhance</button>
					<button type="button" class="sp-btn sp-btn-ghost" id="spResetEdit">Reset edits</button>
				</div>
				<div class="sp-sliders">
					<div><label>Zoom</label><input type="range" id="spZoom" min="100" max="180" value="100"></div>
					<div><label>Brightness</label><input type="range" id="spBright" min="90" max="120" value="102"></div>
					<div><label>Contrast</label><input type="range" id="spContrast" min="90" max="120" value="104"></div>
					<div><label>Saturation</label><input type="range" id="spSaturate" min="90" max="120" value="100"></div>
					<div><label>Warmth</label><input type="range" id="spWarmth" min="-20" max="20" value="0"></div>
					<div><label>Smoothness</label><input type="range" id="spSmooth" min="0" max="12" value="0"></div>
				</div>
			</div>
		</div>
	</div>

	<div class="sp-pane" id="spPaneUpload">
		<form action="<?= base_url('upload_pictures'); ?>" class="dropzone" id="myDropzone">
			<div class="fallback">
				<input name="file" type="file" multiple/>
			</div>
		</form>
	</div>
</div>
<script src="<?= base_url('assets/plugins/dropzone/dropzone.min.js'); ?>"></script>
<script>
	window.PHOTO_STUDIO = <?= json_encode([
		'students' => $photo_students ?? [],
		'placeholder' => $photo_placeholder ?? '',
		'saveUrl' => base_url('save_live_student_photo'),
	], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

	Dropzone.autoDiscover = false;
	var myDropzone = new Dropzone("#myDropzone", {
		dictDefaultMessage: '<i class="fa fa-mouse-pointer"></i> Select or drag students picture <strong>named as registration number</strong>',
	});

	(function () {
		var studio = window.PHOTO_STUDIO || { students: [], placeholder: '', saveUrl: '' };
		var students = studio.students || [];
		var stream = null;
		var selected = null;
		var captured = null;
		var rotation = 0;
		var pan = { x: 0, y: 0 };
		var dragging = false;
		var dragStart = { x: 0, y: 0 };
		var STORAGE_KEY = 'xander_student_photo_camera';
		var PHOTO_SIZE = 1600;
		var video = document.getElementById('spVideo');
		var canvas = document.getElementById('spEditCanvas');
		var ctx = canvas.getContext('2d');
		var listEl = document.getElementById('spStudents');
		var cameraSel = document.getElementById('spCamera');
		var statusEl = document.getElementById('spCamStatus');

		function setStatus(text, kind) {
			statusEl.textContent = text;
			statusEl.className = 'sp-status' + (kind ? ' ' + kind : '');
		}
		function toastOk(msg) { if (window.toastada) toastada.success(msg); }
		function toastErr(msg) { if (window.toastada) toastada.error(msg); else alert(msg); }

		function cameraScore(label) {
			var l = (label || '').toLowerCase();
			if (/osmo|dji|usb|logitech|lifecam|external|c920|c922|hd pro/.test(l)) return 100;
			if (/integrated|internal|facetime|ir camera|infrared|metadata/.test(l)) return 1;
			return 50;
		}

		function filteredStudents() {
			var q = ($('#spSearch').val() || '').toLowerCase().trim();
			var cls = $('#spClass').val() || '';
			var missing = $('#spMissing').is(':checked');
			return students.filter(function (s) {
				if (cls && String(s.class_id) !== String(cls)) return false;
				if (missing && s.has_photo) return false;
				if (!q) return true;
				return (s.name || '').toLowerCase().indexOf(q) >= 0
					|| (s.regno || '').toLowerCase().indexOf(q) >= 0
					|| (s.class || '').toLowerCase().indexOf(q) >= 0;
			});
		}

		function renderList() {
			var rows = filteredStudents();
			if (!rows.length) {
				listEl.innerHTML = '<div class="sp-empty">No students match this filter.</div>';
				return;
			}
			listEl.innerHTML = rows.map(function (s) {
				var sel = selected && selected.id === s.id ? ' selected' : '';
				var has = s.has_photo ? ' has-photo' : '';
				var src = s.photo || studio.placeholder;
				return '<button type="button" class="sp-student' + sel + has + '" data-id="' + s.id + '">'
					+ '<img class="sp-av" src="' + src + '" alt="">'
					+ '<span><div class="who">' + $('<div>').text(s.name).html() + '</div>'
					+ '<div class="meta">' + $('<div>').text((s.regno || '') + ' · ' + (s.class || '')).html() + '</div></span>'
					+ '<span class="sp-badge">' + (s.has_photo ? 'Has photo' : 'No photo') + '</span>'
					+ '</button>';
			}).join('');
		}

		function pickStudent(id) {
			selected = students.find(function (s) { return String(s.id) === String(id); }) || null;
			renderList();
			if (selected) {
				$('#spPicked').html('Selected: <strong>' + $('<div>').text(selected.name).html()
					+ '</strong> &nbsp; ' + $('<div>').text(selected.regno + ' · ' + selected.class).html());
				$('#spCapture').prop('disabled', !stream);
			}
		}

		function stopCamera() {
			if (stream) {
				stream.getTracks().forEach(function (t) { t.stop(); });
				stream = null;
			}
			video.srcObject = null;
			$('#spCapture').prop('disabled', true);
			setStatus('Camera idle', 'warn');
		}

		function fillCameras(preferredId) {
			if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
				return Promise.resolve([]);
			}
			return navigator.mediaDevices.enumerateDevices().then(function (devices) {
				var cams = devices.filter(function (d) { return d.kind === 'videoinput'; });
				cams.sort(function (a, b) { return cameraScore(b.label) - cameraScore(a.label); });
				var html = cams.map(function (d, i) {
					var label = d.label || ('Camera ' + (i + 1));
					return '<option value="' + d.deviceId + '">' + $('<div>').text(label).html() + '</option>';
				}).join('');
				cameraSel.innerHTML = html || '<option value="">No camera found</option>';
				var saved = preferredId || localStorage.getItem(STORAGE_KEY) || '';
				if (saved && cams.some(function (c) { return c.deviceId === saved; })) {
					cameraSel.value = saved;
				} else if (cams[0]) {
					cameraSel.value = cams[0].deviceId;
				}
				return cams;
			});
		}

		var preferredSwitchDone = false;
		function startCamera() {
			if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
				setStatus('Browser cannot access a camera', 'err');
				toastErr('This browser cannot access a USB webcam.');
				return;
			}
			stopCamera();
			var deviceId = cameraSel.value;
			var videoConstraints = deviceId
				? { deviceId: { exact: deviceId }, width: { ideal: 1920, min: 1280 }, height: { ideal: 1080, min: 720 } }
				: { facingMode: 'user', width: { ideal: 1920, min: 1280 }, height: { ideal: 1080, min: 720 } };
			setStatus('Starting camera…', 'warn');
			navigator.mediaDevices.getUserMedia({ audio: false, video: videoConstraints }).then(function (s) {
				stream = s;
				video.srcObject = stream;
				var track = s.getVideoTracks()[0];
				var currentId = (track && track.getSettings && track.getSettings().deviceId) || deviceId || '';
				localStorage.setItem(STORAGE_KEY, currentId);
				var label = (track && track.label) ? track.label : 'USB camera';
				setStatus(label + ' ready', 'ok');
				$('#spCapture').prop('disabled', !selected);
				return fillCameras(currentId).then(function (cams) {
					var preferred = cameraSel.value;
					if (!preferredSwitchDone && preferred && currentId && preferred !== currentId) {
						preferredSwitchDone = true;
						startCamera();
					}
					return cams;
				});
			}).catch(function (err) {
				setStatus('Camera blocked or not found', 'err');
				var msg = 'Could not start the USB camera. Allow camera permission, then pick OsmoPocket3 / USB webcam.';
				if (err && err.name === 'NotAllowedError') msg = 'Camera permission denied. Allow camera access for this site.';
				if (err && err.name === 'NotFoundError') msg = 'No webcam found. Connect the USB camera and click Start camera.';
				if (err && err.name === 'OverconstrainedError' && deviceId) {
					preferredSwitchDone = true;
					cameraSel.value = '';
					startCamera();
					return;
				}
				toastErr(msg);
			});
		}

		function captureFullFrame() {
			var vw = video.videoWidth || PHOTO_SIZE;
			var vh = video.videoHeight || PHOTO_SIZE;
			var tmp = document.createElement('canvas');
			tmp.width = vw;
			tmp.height = vh;
			var tctx = tmp.getContext('2d');
			tctx.fillStyle = '#111827';
			tctx.fillRect(0, 0, vw, vh);
			tctx.save();
			tctx.translate(vw, 0);
			tctx.scale(-1, 1);
			tctx.imageSmoothingEnabled = true;
			tctx.imageSmoothingQuality = 'high';
			tctx.drawImage(video, 0, 0, vw, vh);
			tctx.restore();
			return tmp;
		}

		function squareCrop(srcCanvas) {
			var W = srcCanvas.width;
			var H = srcCanvas.height;
			var crop = Math.min(W, H);
			var cropX = (W - crop) / 2;
			var cropY = (H - crop) / 2;
			var out = document.createElement('canvas');
			out.width = PHOTO_SIZE;
			out.height = PHOTO_SIZE;
			var o = out.getContext('2d');
			o.fillStyle = '#ffffff';
			o.fillRect(0, 0, PHOTO_SIZE, PHOTO_SIZE);
			o.imageSmoothingEnabled = true;
			o.imageSmoothingQuality = 'high';
			o.drawImage(srcCanvas, cropX, cropY, crop, crop, 0, 0, PHOTO_SIZE, PHOTO_SIZE);
			return out;
		}

		function useCaptured(imgCanvas) {
			captured = new Image();
			captured.onload = function () {
				rotation = 0;
				pan = { x: 0, y: 0 };
				$('#spZoom').val(100);
				drawEdit();
				$('#spRetake, #spRotate, #spSave').prop('disabled', false);
				$('#spCapture').prop('disabled', !stream);
			};
			captured.src = imgCanvas.toDataURL('image/jpeg', 0.95);
		}

		function captureFrame() {
			if (!stream || !selected) return;
			$('#spCapture').prop('disabled', true);
			try {
				useCaptured(squareCrop(captureFullFrame()));
				setStatus('Photo captured', 'ok');
			} catch (e) {
				setStatus('Capture failed', 'err');
				toastErr('Could not capture the photo. Try Start camera again.');
				$('#spCapture').prop('disabled', !stream);
			}
		}

		function sliderVals() {
			return {
				zoom: parseInt($('#spZoom').val(), 10) / 100,
				bright: parseInt($('#spBright').val(), 10),
				contrast: parseInt($('#spContrast').val(), 10),
				saturate: parseInt($('#spSaturate').val(), 10),
				warmth: parseInt($('#spWarmth').val(), 10),
				smooth: parseInt($('#spSmooth').val(), 10)
			};
		}

		function drawEdit() {
			ctx.fillStyle = '#ffffff';
			ctx.fillRect(0, 0, canvas.width, canvas.height);
			if (!captured) {
				ctx.fillStyle = '#94a3b8';
				ctx.font = '28px sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText('Capture a student photo', canvas.width / 2, canvas.height / 2);
				return;
			}
			var v = sliderVals();
			ctx.save();
			ctx.filter = 'brightness(' + v.bright + '%) contrast(' + v.contrast + '%) saturate(' + v.saturate + '%) blur(' + (v.smooth / 22) + 'px)';
			ctx.translate(canvas.width / 2 + pan.x, canvas.height / 2 + pan.y);
			ctx.rotate(rotation * Math.PI / 180);
			var base = Math.max(canvas.width / captured.width, canvas.height / captured.height) * v.zoom;
			var dw = captured.width * base;
			var dh = captured.height * base;
			ctx.drawImage(captured, -dw / 2, -dh / 2, dw, dh);
			ctx.restore();
			if (v.warmth !== 0) {
				ctx.save();
				ctx.globalCompositeOperation = 'soft-light';
				ctx.fillStyle = v.warmth > 0
					? 'rgba(255,196,120,' + (Math.abs(v.warmth) / 160) + ')'
					: 'rgba(160,196,255,' + (Math.abs(v.warmth) / 180) + ')';
				ctx.fillRect(0, 0, canvas.width, canvas.height);
				ctx.restore();
			}
		}

		function exportPhoto() {
			return canvas.toDataURL('image/jpeg', 0.95);
		}

		function savePhoto() {
			if (!selected || !captured) return;
			$('#spSave').prop('disabled', true).text('Saving…');
			$.post(studio.saveUrl, { student: selected.id, photo: exportPhoto() }, function (data) {
				$('#spSave').prop('disabled', false).html('<i class="fa fa-save"></i> Save photo');
				if (data && data.error) {
					toastErr(data.error);
					return;
				}
				if (data && data.success) {
					toastOk(data.success);
					students = students.map(function (s) {
						if (s.id === selected.id) {
							s.has_photo = true;
							s.photo = data.url || s.photo;
						}
						return s;
					});
					var next = students.find(function (s) { return !s.has_photo && s.class_id === selected.class_id; })
						|| students.find(function (s) { return !s.has_photo; });
					if (next) pickStudent(next.id);
					else { selected.has_photo = true; renderList(); }
					captured = null;
					drawEdit();
					$('#spRetake, #spRotate, #spSave').prop('disabled', true);
					$('#spCapture').prop('disabled', !stream);
				} else {
					toastErr('Could not save the photo.');
				}
			}, 'json').fail(function () {
				$('#spSave').prop('disabled', false).html('<i class="fa fa-save"></i> Save photo');
				toastErr('System error while saving the photo.');
			});
		}

		$('.sp-tab').on('click', function () {
			$('.sp-tab, .sp-pane').removeClass('active');
			$(this).addClass('active');
			$('#spPane' + ($(this).data('pane') === 'live' ? 'Live' : 'Upload')).addClass('active');
		});
		$('#spSearch, #spClass, #spMissing').on('input change', renderList);
		$(listEl).on('click', '.sp-student', function () { pickStudent($(this).data('id')); });
		$('#spStartCam').on('click', startCamera);
		$('#spStopCam').on('click', stopCamera);
		$('#spCamera').on('change', function () {
			localStorage.setItem(STORAGE_KEY, this.value);
			if (stream) startCamera();
		});
		$('#spCapture').on('click', captureFrame);
		$('#spRetake').on('click', function () {
			captured = null;
			drawEdit();
			$('#spRetake, #spRotate, #spSave').prop('disabled', true);
		});
		$('#spRotate').on('click', function () { rotation = (rotation + 90) % 360; drawEdit(); });
		$('#spSave').on('click', savePhoto);
		$('#spAuto').on('click', function () {
			$('#spBright').val(103);
			$('#spContrast').val(106);
			$('#spSaturate').val(100);
			$('#spWarmth').val(0);
			$('#spSmooth').val(0);
			drawEdit();
		});
		$('#spResetEdit').on('click', function () {
			$('#spZoom').val(100);
			$('#spBright').val(102);
			$('#spContrast').val(104);
			$('#spSaturate').val(100);
			$('#spWarmth').val(0);
			$('#spSmooth').val(0);
			pan = { x: 0, y: 0 };
			rotation = 0;
			drawEdit();
		});
		$('.sp-sliders input').on('input', drawEdit);

		var frame = document.getElementById('spFrame');
		frame.addEventListener('mousedown', function (e) {
			if (!captured) return;
			dragging = true;
			dragStart = { x: e.clientX - pan.x, y: e.clientY - pan.y };
		});
		window.addEventListener('mousemove', function (e) {
			if (!dragging) return;
			pan.x = e.clientX - dragStart.x;
			pan.y = e.clientY - dragStart.y;
			drawEdit();
		});
		window.addEventListener('mouseup', function () { dragging = false; });
		frame.addEventListener('wheel', function (e) {
			if (!captured) return;
			e.preventDefault();
			var z = parseInt($('#spZoom').val(), 10) + (e.deltaY > 0 ? -8 : 8);
			$('#spZoom').val(Math.max(100, Math.min(180, z)));
			drawEdit();
		}, { passive: false });
		window.addEventListener('beforeunload', stopCamera);

		renderList();
		drawEdit();
		if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
			fillCameras().then(function (cams) {
				if (!cams.length) setStatus('No camera listed yet — click Start camera', 'warn');
				else setStatus(cams.length + ' camera(s) found', 'ok');
				startCamera();
			});
		} else {
			startCamera();
		}
	})();
</script>
