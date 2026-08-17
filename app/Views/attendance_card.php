<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Attendance Scanner</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<style>
:root {
	--bg: #eef1f6;
	--card: #ffffff;
	--text: #1e293b;
	--muted: #64748b;
	--line: #e2e8f0;
	--in: #059669;
	--in-bg: #ecfdf5;
	--out: #dc2626;
	--out-bg: #fef2f2;
	--brand: #0a66b7;
	--shadow: 0 24px 48px rgba(15, 23, 42, 0.08);
}

* { box-sizing: border-box; }

body {
	margin: 0;
	min-height: 100vh;
	font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
	background: radial-gradient(circle at top, #f8fbff 0%, var(--bg) 55%);
	color: var(--text);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 24px;
}

.kiosk {
	position: relative;
	width: min(960px, 100%);
	background: var(--card);
	border: 1px solid var(--line);
	border-radius: 20px;
	box-shadow: var(--shadow);
	padding: 32px 36px 28px;
	transition: background-color 0.35s ease, border-color 0.35s ease;
	min-height: 420px;
}

.kiosk.flash-green { background: var(--in-bg); border-color: #a7f3d0; }
.kiosk.flash-red { background: var(--out-bg); border-color: #fecaca; }

.topbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 28px;
	padding-bottom: 18px;
	border-bottom: 1px solid var(--line);
}

.title {
	font-size: clamp(1.4rem, 2.5vw, 2rem);
	font-weight: 700;
	color: var(--brand);
	letter-spacing: -0.02em;
}

.meta {
	text-align: right;
	font-size: 0.92rem;
	color: var(--muted);
	line-height: 1.45;
}

.layout {
	display: grid;
	grid-template-columns: 240px 1fr;
	gap: 36px;
	align-items: center;
}

.layout[hidden] { display: none; }

.photo-wrap {
	position: relative;
	width: 240px;
	height: 240px;
	border-radius: 18px;
	overflow: hidden;
	background: linear-gradient(145deg, #f8fafc, #e2e8f0);
	border: 1px solid var(--line);
}

.photo {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
	background: #f1f5f9;
}

.name {
	font-size: clamp(1.6rem, 3vw, 2.25rem);
	font-weight: 700;
	line-height: 1.15;
	color: var(--text);
}

.regno {
	margin-top: 8px;
	font-size: 1.05rem;
	color: var(--muted);
}

.class-name {
	margin-top: 6px;
	font-size: 0.98rem;
	color: #475569;
}

.status {
	margin-top: 18px;
	font-size: clamp(2rem, 4vw, 3rem);
	font-weight: 800;
	padding: 10px 22px;
	border-radius: 14px;
	display: inline-block;
	letter-spacing: 0.04em;
}

.status.in { background: var(--in-bg); color: var(--in); }
.status.out { background: var(--out-bg); color: var(--out); }

.hint {
	margin-top: 18px;
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 1rem;
	color: var(--muted);
}

.ready-dot {
	width: 12px;
	height: 12px;
	border-radius: 50%;
	background: #22c55e;
	box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45);
	animation: pulse 2s infinite;
	flex: 0 0 12px;
}

.ready-dot.busy {
	background: #f59e0b;
	animation: none;
	box-shadow: none;
}

.ready-dot.error {
	background: #ef4444;
	animation: none;
	box-shadow: none;
}

@keyframes pulse {
	0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45); }
	70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
	100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.scan-time {
	margin-top: 10px;
	font-size: 0.92rem;
	color: #94a3b8;
	min-height: 1.2em;
}

.area-badge {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin-top: 6px;
	padding: 4px 10px;
	border-radius: 999px;
	background: #e0f2fe;
	color: var(--brand);
	font-size: 0.82rem;
	font-weight: 650;
}

.area-badge[hidden] { display: none; }

.area-badge button {
	border: 0;
	background: transparent;
	color: #0369a1;
	cursor: pointer;
	font-size: 0.78rem;
	text-decoration: underline;
	padding: 0;
}

.area-gate {
	padding: 8px 0 4px;
}

.area-gate[hidden] { display: none; }

.area-gate h2 {
	margin: 0 0 8px;
	font-size: 1.45rem;
	color: var(--brand);
}

.area-gate p {
	margin: 0 0 20px;
	color: var(--muted);
}

.area-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
	gap: 12px;
}

.area-tile {
	border: 1px solid var(--line);
	background: #f8fafc;
	border-radius: 14px;
	padding: 18px 12px;
	cursor: pointer;
	font-weight: 700;
	font-size: 1rem;
	color: var(--text);
	text-align: center;
	transition: border-color .15s, box-shadow .15s, background .15s;
}

.area-tile:hover, .area-tile:focus {
	border-color: var(--brand);
	background: #eff6ff;
	box-shadow: 0 0 0 3px rgba(10,102,183,.12);
	outline: none;
}

.area-empty {
	color: var(--muted);
	font-size: .95rem;
}

.area-empty a { color: var(--brand); }

@media (max-width: 760px) {
	.layout { grid-template-columns: 1fr; justify-items: center; text-align: center; }
	.topbar { flex-direction: column; align-items: flex-start; }
	.meta { text-align: left; }
	.hint { justify-content: center; }
}
</style>
</head>
<body>

<div class="kiosk" id="container">
	<div class="topbar">
		<div>
			<div class="title">Student Attendance Scanner</div>
			<div class="area-badge" id="areaBadge" hidden>
				<span id="areaBadgeName"></span>
				<button type="button" id="changeAreaBtn"><?= lang('app.changeArea'); ?></button>
			</div>
		</div>
		<div class="meta">
			<div id="clock">--:--:--</div>
			<div id="readyLabel">Select an area first</div>
		</div>
	</div>

	<div class="layout" id="scanLayout" hidden>
		<div class="photo-wrap">
			<img id="photo" class="photo" src="<?= esc(profile_photo_url(null), 'attr'); ?>" alt="Student photo">
		</div>

		<div class="info">
			<div id="name" class="name">Waiting for card…</div>
			<div id="regno" class="regno"></div>
			<div id="className" class="class-name"></div>
			<div id="status"></div>
			<div class="hint">
				<span id="readyDot" class="ready-dot" aria-hidden="true"></span>
				<span id="hintText">Tap student card on the reader</span>
			</div>
			<div id="scanTime" class="scan-time"></div>
		</div>
	</div>

	<div class="area-gate" id="areaGate">
		<h2><?= lang('app.selectAttendanceArea'); ?></h2>
		<p>Choose where you are recording IN/OUT, then tap student NFC cards.</p>
		<?php $areas = $attendance_areas ?? []; ?>
		<?php if (empty($areas)) : ?>
			<p class="area-empty">
				No attendance areas are set.
				<a href="<?= esc($settings_url ?? base_url('settings'), 'attr'); ?>">Add them in School settings</a>
				(Library, Cafeteria, School gate, …).
			</p>
		<?php else : ?>
			<div class="area-grid" id="areaGrid">
				<?php foreach ($areas as $area) : ?>
					<button type="button" class="area-tile" data-id="<?= (int) $area['id']; ?>" data-name="<?= esc($area['name'], 'attr'); ?>">
						<?= esc($area['name']); ?>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
	let buffer = "";
	let scanning = false;
	let lastUID = null;
	let lastScanAt = 0;
	let selectedAreaId = 0;
	let selectedAreaName = "";
	let listening = false;

	const container = document.getElementById("container");
	const readyDot = document.getElementById("readyDot");
	const hintText = document.getElementById("hintText");
	const clockEl = document.getElementById("clock");
	const photoEl = document.getElementById("photo");
	const areaGate = document.getElementById("areaGate");
	const scanLayout = document.getElementById("scanLayout");
	const areaBadge = document.getElementById("areaBadge");
	const areaBadgeName = document.getElementById("areaBadgeName");
	const readyLabel = document.getElementById("readyLabel");

	const fallbackPhoto = <?= json_encode(profile_photo_url(null)) ?>;
	const apiURL = <?= json_encode(base_url('scan-card')) ?>;

	function tickClock() {
		const now = new Date();
		clockEl.textContent = now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" });
	}
	tickClock();
	setInterval(tickClock, 1000);

	if (photoEl) {
		photoEl.addEventListener("error", function onPhotoError() {
			if (photoEl.dataset.fallbackApplied === "1") {
				photoEl.removeEventListener("error", onPhotoError);
				photoEl.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='240' viewBox='0 0 240 240'%3E%3Crect fill='%23e2e8f0' width='240' height='240'/%3E%3Ccircle cx='120' cy='92' r='42' fill='%23cbd5e1'/%3E%3Cpath d='M40 210c16-44 52-66 80-66s64 22 80 66' fill='%23cbd5e1'/%3E%3C/svg%3E";
				return;
			}
			photoEl.dataset.fallbackApplied = "1";
			photoEl.src = fallbackPhoto;
		});
	}

	function normalizeUID(uid) {
		uid = (uid || "").trim();
		if (!uid) return "";
		if (/^\d+$/.test(uid)) {
			uid = BigInt(uid).toString(16).toUpperCase();
		}
		return uid.replace(/[^A-Fa-f0-9]/g, "").toUpperCase();
	}

	function setReadyState(state, message) {
		if (!readyDot) return;
		readyDot.classList.remove("busy", "error");
		if (state === "busy") readyDot.classList.add("busy");
		if (state === "error") readyDot.classList.add("error");
		if (message && hintText) hintText.textContent = message;
	}

	function resetDisplay() {
		if (!selectedAreaId) return;
		document.getElementById("name").textContent = "Waiting for card…";
		document.getElementById("regno").textContent = "";
		document.getElementById("className").textContent = "";
		document.getElementById("status").textContent = "";
		document.getElementById("status").className = "";
		document.getElementById("scanTime").textContent = "";
		if (photoEl) {
			delete photoEl.dataset.fallbackApplied;
			photoEl.src = fallbackPhoto;
		}
		setReadyState("ready", "Tap student card on the reader");
	}

	function lockArea(id, name) {
		selectedAreaId = parseInt(id, 10) || 0;
		selectedAreaName = name || "";
		if (!selectedAreaId) return;
		areaGate.hidden = true;
		scanLayout.hidden = false;
		areaBadge.hidden = false;
		areaBadgeName.textContent = selectedAreaName;
		readyLabel.textContent = "Ready for card tap";
		buffer = "";
		listening = true;
		resetDisplay();
	}

	function unlockArea() {
		listening = false;
		selectedAreaId = 0;
		selectedAreaName = "";
		buffer = "";
		scanning = false;
		areaGate.hidden = false;
		scanLayout.hidden = true;
		areaBadge.hidden = true;
		readyLabel.textContent = "Select an area first";
	}

	document.querySelectorAll(".area-tile").forEach(function (btn) {
		btn.addEventListener("click", function () {
			lockArea(btn.getAttribute("data-id"), btn.getAttribute("data-name"));
		});
	});

	const changeBtn = document.getElementById("changeAreaBtn");
	if (changeBtn) changeBtn.addEventListener("click", unlockArea);

	document.addEventListener("keypress", function (e) {
		if (!listening || !selectedAreaId) {
			buffer = "";
			return;
		}
		if (e.key === "Enter") {
			const raw = buffer.trim();
			buffer = "";
			if (raw.length > 3) {
				const uid = normalizeUID(raw);
				scan(uid);
			}
			return;
		}
		buffer += e.key;
	});

	setTimeout(function () {
		const testCard = new URLSearchParams(window.location.search).get("card");
		if (testCard && selectedAreaId) scan(normalizeUID(testCard));
	}, 300);

	function scan(uid) {
		if (!uid || scanning || !selectedAreaId) return;

		const now = Date.now();
		if (uid === lastUID && now - lastScanAt < 1200) return;

		scanning = true;
		lastUID = uid;
		lastScanAt = now;
		setReadyState("busy", "Processing card…");

		fetch(apiURL + "?card=" + encodeURIComponent(uid) + "&area=" + encodeURIComponent(selectedAreaId), {
			method: "GET",
			cache: "no-store",
			headers: { "Accept": "application/json" },
			credentials: "same-origin"
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data.success) {
					showStudent(data);
				} else {
					showError(data.message || "Card not registered");
				}
			})
			.catch(function () {
				showError("Connection error — try again");
			})
			.finally(function () {
				setTimeout(function () {
					scanning = false;
					setReadyState("ready", "Tap student card on the reader");
				}, 900);
			});
	}

	function showStudent(data) {
		document.getElementById("name").textContent = (data.student && data.student.name) || "Unknown";
		document.getElementById("regno").textContent = (data.student && data.student.regno) ? ("Reg: " + data.student.regno) : "";
		document.getElementById("className").textContent = (data.student && data.student.class) || "";
		document.getElementById("scanTime").textContent = data.time ? ("Recorded at " + data.time) : "";

		delete photoEl.dataset.fallbackApplied;
		photoEl.src = ((data.student && data.student.photo) || fallbackPhoto) + "?v=" + Date.now();

		const statusEl = document.getElementById("status");
		statusEl.textContent = data.status || "";
		if (data.status === "IN") {
			statusEl.className = "status in";
			flash("flash-green");
		} else {
			statusEl.className = "status out";
			flash("flash-red");
		}
		setReadyState("ready", "Scan complete — ready for next card");
	}

	function showError(msg) {
		document.getElementById("name").textContent = msg;
		document.getElementById("regno").textContent = "";
		document.getElementById("className").textContent = "";
		document.getElementById("status").textContent = "";
		document.getElementById("scanTime").textContent = "";
		delete photoEl.dataset.fallbackApplied;
		photoEl.src = fallbackPhoto;
		flash("flash-red");
		setReadyState("error", "Card not recognized — try again");
		setTimeout(resetDisplay, 2500);
	}

	function flash(cls) {
		container.classList.add(cls);
		setTimeout(function () { container.classList.remove(cls); }, 900);
	}
});
</script>
</body>
</html>
