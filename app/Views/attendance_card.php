<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student Attendance Scanner</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<style>
:root {
	--bg: #e8eef6;
	--card: #ffffff;
	--text: #0f172a;
	--muted: #64748b;
	--line: #e2e8f0;
	--in: #059669;
	--in-bg: #ecfdf5;
	--out: #dc2626;
	--out-bg: #fef2f2;
	--brand: #0a66b7;
	--brand-dark: #074a86;
	--shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}

* { box-sizing: border-box; }

body {
	margin: 0;
	min-height: 100vh;
	font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
	background:
		radial-gradient(circle at 12% -10%, #dbeafe 0%, transparent 42%),
		radial-gradient(circle at 100% 0%, #e0f2fe 0%, transparent 36%),
		var(--bg);
	color: var(--text);
	padding: 18px 20px 24px;
}

.shell {
	width: min(1180px, 100%);
	margin: 0 auto;
}

.topbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 16px;
}

.brand-block .eyebrow {
	font-size: .72rem;
	font-weight: 700;
	letter-spacing: .08em;
	text-transform: uppercase;
	color: #64748b;
}

.title {
	font-size: clamp(1.35rem, 2.4vw, 1.85rem);
	font-weight: 800;
	color: var(--brand);
	letter-spacing: -0.03em;
	line-height: 1.15;
}

.school-name {
	margin-top: 2px;
	color: var(--muted);
	font-size: .92rem;
}

.area-badge {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin-top: 8px;
	padding: 5px 12px;
	border-radius: 999px;
	background: #e0f2fe;
	color: var(--brand);
	font-size: 0.86rem;
	font-weight: 700;
}

.area-badge[hidden] { display: none; }

.area-badge button {
	border: 0;
	background: transparent;
	color: #0369a1;
	cursor: pointer;
	font-size: 0.8rem;
	text-decoration: underline;
	padding: 0;
	font-weight: 650;
}

.meta {
	text-align: right;
	background: #fff;
	border: 1px solid var(--line);
	border-radius: 16px;
	padding: 12px 16px;
	min-width: 168px;
	box-shadow: var(--shadow);
}

#clock {
	font-size: 1.35rem;
	font-weight: 800;
	color: var(--text);
	font-variant-numeric: tabular-nums;
}

#readyLabel { color: var(--muted); font-size: .85rem; margin-top: 2px; }

.kpi-grid {
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	gap: 12px;
	margin-bottom: 16px;
}

.kpi-grid[hidden] { display: none; }

.kpi {
	background: #fff;
	border: 1px solid var(--line);
	border-radius: 16px;
	padding: 14px 16px 13px;
	box-shadow: var(--shadow);
	min-width: 0;
}

.kpi .lbl {
	font-size: .72rem;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
	color: var(--muted);
}

.kpi .val {
	margin-top: 4px;
	font-size: clamp(1.55rem, 2.6vw, 2.05rem);
	font-weight: 800;
	line-height: 1.1;
	font-variant-numeric: tabular-nums;
}

.kpi .sub { margin-top: 3px; font-size: .78rem; color: #94a3b8; }

.kpi.inside .val { color: #0369a1; }
.kpi.in .val { color: var(--in); }
.kpi.out .val { color: var(--out); }
.kpi.scans .val { color: #7c3aed; }
.kpi.pct .val { color: #c2410c; }

.board {
	display: grid;
	grid-template-columns: 1.45fr .9fr;
	gap: 16px;
	align-items: stretch;
}

.board[hidden] { display: none; }

.scan-card, .recent-card, .picker-card {
	background: #fff;
	border: 1px solid var(--line);
	border-radius: 20px;
	box-shadow: var(--shadow);
}

.scan-card {
	padding: 22px 24px 20px;
	transition: background-color .35s ease, border-color .35s ease;
	min-height: 340px;
}

.scan-card.flash-green { background: var(--in-bg); border-color: #a7f3d0; }
.scan-card.flash-red { background: var(--out-bg); border-color: #fecaca; }

.layout {
	display: grid;
	grid-template-columns: 200px 1fr;
	gap: 24px;
	align-items: center;
}

.photo-wrap {
	position: relative;
	width: 200px;
	height: 200px;
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
	font-size: clamp(1.45rem, 2.6vw, 2.05rem);
	font-weight: 800;
	line-height: 1.15;
}

.regno { margin-top: 6px; font-size: 1rem; color: var(--muted); }
.class-name { margin-top: 4px; font-size: .95rem; color: #475569; }

.status {
	margin-top: 14px;
	font-size: clamp(1.7rem, 3.2vw, 2.4rem);
	font-weight: 800;
	padding: 8px 18px;
	border-radius: 14px;
	display: inline-block;
	letter-spacing: 0.04em;
}

.status.in { background: var(--in-bg); color: var(--in); }
.status.out { background: var(--out-bg); color: var(--out); }

.hint {
	margin-top: 16px;
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: .98rem;
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

.ready-dot.busy { background: #f59e0b; animation: none; box-shadow: none; }
.ready-dot.error { background: #ef4444; animation: none; box-shadow: none; }

@keyframes pulse {
	0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45); }
	70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
	100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.scan-time { margin-top: 8px; font-size: .9rem; color: #94a3b8; min-height: 1.2em; }

.recent-card { padding: 16px 16px 10px; display: flex; flex-direction: column; min-height: 340px; }
.recent-card h3 {
	margin: 0 0 12px;
	font-size: .95rem;
	font-weight: 800;
	color: var(--text);
}
.recent-list { display: flex; flex-direction: column; gap: 8px; overflow: auto; max-height: 360px; }
.recent-empty { color: var(--muted); font-size: .9rem; padding: 18px 4px; }

.recent-row {
	display: grid;
	grid-template-columns: 40px 1fr auto;
	gap: 10px;
	align-items: center;
	padding: 8px;
	border-radius: 12px;
	background: #f8fafc;
	border: 1px solid #eef2f7;
}

.recent-row img {
	width: 40px; height: 40px; border-radius: 10px; object-fit: cover; background: #e2e8f0;
}

.recent-row .who { font-weight: 700; font-size: .88rem; line-height: 1.2; }
.recent-row .meta-s { color: var(--muted); font-size: .75rem; }
.recent-row .times { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }

.pill {
	font-size: .72rem;
	font-weight: 800;
	padding: 4px 8px;
	border-radius: 999px;
	letter-spacing: .03em;
	white-space: nowrap;
}
.pill.in { background: var(--in-bg); color: var(--in); }
.pill.out { background: var(--out-bg); color: var(--out); }
.pill.wait { background: #fff7ed; color: #c2410c; }

.picker-card { padding: 22px 24px 24px; }
.picker-card h2 { margin: 0 0 6px; font-size: 1.35rem; color: var(--brand); }
.picker-card p { margin: 0 0 18px; color: var(--muted); }

.area-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
	gap: 12px;
}

.area-tile {
	border: 1px solid var(--line);
	background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
	border-radius: 16px;
	padding: 16px 14px 14px;
	cursor: pointer;
	text-align: left;
	transition: border-color .15s, box-shadow .15s, transform .15s;
}

.area-tile:hover, .area-tile:focus {
	border-color: var(--brand);
	box-shadow: 0 0 0 3px rgba(10,102,183,.12);
	transform: translateY(-1px);
	outline: none;
}

.area-tile .aname { display: block; font-weight: 800; font-size: 1.02rem; color: var(--text); }
.area-tile .astats { display: flex; gap: 10px; margin-top: 10px; color: var(--muted); font-size: .78rem; font-weight: 650; }
.area-tile .astats b { color: var(--brand); font-size: 1rem; }

.area-empty { color: var(--muted); }
.area-empty a { color: var(--brand); }

@media (max-width: 980px) {
	.kpi-grid { grid-template-columns: repeat(3, 1fr); }
	.board { grid-template-columns: 1fr; }
}
@media (max-width: 760px) {
	.topbar { flex-direction: column; align-items: flex-start; }
	.meta { text-align: left; width: 100%; }
	.kpi-grid { grid-template-columns: 1fr 1fr; }
	.layout { grid-template-columns: 1fr; justify-items: center; text-align: center; }
	.hint { justify-content: center; }
}
</style>
</head>
<body>
<?php
$areas = $attendance_areas ?? [];
$schoolName = $school_name ?? '';
$settingsUrl = $settings_url ?? base_url('settings');
$fallbackPhoto = profile_photo_url(null);
?>
<div class="shell">
	<div class="topbar">
		<div class="brand-block">
			<div class="eyebrow">NFC kiosk</div>
			<div class="title">Student Attendance Scanner</div>
			<?php if ($schoolName !== '') : ?>
				<div class="school-name"><?= esc($schoolName); ?></div>
			<?php endif; ?>
			<div class="area-badge" id="areaBadge" hidden>
				<span id="areaBadgeName"></span>
				<button type="button" id="changeAreaBtn"><?= lang('app.changeArea'); ?></button>
			</div>
		</div>
		<div class="meta">
			<div id="clock">--:--:--</div>
			<div id="readyLabel">Select a location first</div>
		</div>
	</div>

	<div class="kpi-grid" id="kpiGrid" hidden>
		<div class="kpi inside"><div class="lbl">Inside now</div><div class="val" id="kpiInside">0</div><div class="sub">Still in this location</div></div>
		<div class="kpi in"><div class="lbl">Checked IN</div><div class="val" id="kpiIn">0</div><div class="sub">Entries today</div></div>
		<div class="kpi out"><div class="lbl">Checked OUT</div><div class="val" id="kpiOut">0</div><div class="sub">Exits today</div></div>
		<div class="kpi scans"><div class="lbl">Card taps</div><div class="val" id="kpiScans">0</div><div class="sub">IN + OUT today</div></div>
		<div class="kpi pct"><div class="lbl">Still inside</div><div class="val" id="kpiPct">0%</div><div class="sub">Of today’s entries</div></div>
	</div>

	<div class="picker-card" id="areaGate">
		<h2><?= lang('app.selectAttendanceArea'); ?></h2>
		<p>Choose the location, then tap student NFC cards. KPIs update live for that location.</p>
		<?php if (empty($areas)) : ?>
			<p class="area-empty">
				No attendance locations are set.
				<a href="<?= esc($settingsUrl, 'attr'); ?>">Add them in School settings</a>
				(Library, Cafeteria, School gate, …).
			</p>
		<?php else : ?>
			<div class="area-grid" id="areaGrid">
				<?php foreach ($areas as $area) : ?>
					<button type="button" class="area-tile"
						data-id="<?= (int) $area['id']; ?>"
						data-name="<?= esc($area['name'], 'attr'); ?>">
						<span class="aname"><?= esc($area['name']); ?></span>
						<span class="astats">
							<span><b><?= (int) ($area['inside'] ?? 0); ?></b> inside</span>
							<span><b><?= (int) ($area['checked_in'] ?? 0); ?></b> in today</span>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="board" id="scanLayout" hidden>
		<div class="scan-card" id="container">
			<div class="layout">
				<div class="photo-wrap">
					<img id="photo" class="photo" src="<?= esc($fallbackPhoto, 'attr'); ?>" alt="Student photo">
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
		</div>
		<div class="recent-card">
			<h3>Today’s activity</h3>
			<div class="recent-list" id="recentList">
				<div class="recent-empty">No scans yet in this location today.</div>
			</div>
		</div>
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
	const kpiGrid = document.getElementById("kpiGrid");
	const areaBadge = document.getElementById("areaBadge");
	const areaBadgeName = document.getElementById("areaBadgeName");
	const readyLabel = document.getElementById("readyLabel");
	const recentList = document.getElementById("recentList");

	const fallbackPhoto = <?= json_encode($fallbackPhoto) ?>;
	const apiURL = <?= json_encode(base_url('scan-card')) ?>;
	const statsURL = <?= json_encode($stats_url ?? base_url('attendance-card/stats')) ?>;

	function tickClock() {
		clockEl.textContent = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" });
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
		if (/^\d+$/.test(uid)) uid = BigInt(uid).toString(16).toUpperCase();
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

	function renderKpi(kpi) {
		kpi = kpi || {};
		document.getElementById("kpiInside").textContent = kpi.inside || 0;
		document.getElementById("kpiIn").textContent = kpi.checked_in || 0;
		document.getElementById("kpiOut").textContent = kpi.checked_out || 0;
		document.getElementById("kpiScans").textContent = kpi.scans || 0;
		document.getElementById("kpiPct").textContent = (kpi.still_in_pct || 0) + "%";
	}

	function escapeHtml(s) {
		const d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function renderRecent(rows) {
		if (!rows || !rows.length) {
			recentList.innerHTML = '<div class="recent-empty">No scans yet in this location today.</div>';
			return;
		}
		recentList.innerHTML = rows.map(function (r) {
			const inTime = r.time_in ? escapeHtml(r.time_in) : "—";
			const outPill = r.time_out
				? '<span class="pill out">OUT ' + escapeHtml(r.time_out) + '</span>'
				: '<span class="pill wait">OUT —</span>';
			return '<div class="recent-row">' +
				'<img src="' + escapeHtml(r.photo || fallbackPhoto) + '" alt="">' +
				'<div><div class="who">' + escapeHtml(r.name) + '</div>' +
				'<div class="meta-s">' + escapeHtml(r.regno ? ("Reg " + r.regno) : "") + '</div></div>' +
				'<div class="times">' +
					'<span class="pill in">IN ' + inTime + '</span>' +
					outPill +
				'</div></div>';
		}).join("");
	}

	function applyDashboard(data) {
		if (data && data.kpi) renderKpi(data.kpi);
		if (data && data.recent) renderRecent(data.recent);
	}

	function loadStats() {
		if (!selectedAreaId) return;
		fetch(statsURL + "?area=" + encodeURIComponent(selectedAreaId), {
			cache: "no-store",
			headers: { "Accept": "application/json" },
			credentials: "same-origin"
		})
			.then(function (res) { return res.json(); })
			.then(applyDashboard)
			.catch(function () {});
	}

	function lockArea(id, name) {
		selectedAreaId = parseInt(id, 10) || 0;
		selectedAreaName = name || "";
		if (!selectedAreaId) return;
		areaGate.hidden = true;
		scanLayout.hidden = false;
		kpiGrid.hidden = false;
		areaBadge.hidden = false;
		areaBadgeName.textContent = selectedAreaName;
		readyLabel.textContent = "Ready for card tap";
		buffer = "";
		listening = true;
		resetDisplay();
		loadStats();
	}

	function unlockArea() {
		listening = false;
		selectedAreaId = 0;
		selectedAreaName = "";
		buffer = "";
		scanning = false;
		areaGate.hidden = false;
		scanLayout.hidden = true;
		kpiGrid.hidden = true;
		areaBadge.hidden = true;
		readyLabel.textContent = "Select a location first";
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
			if (raw.length > 3) scan(normalizeUID(raw));
			return;
		}
		buffer += e.key;
	});

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
				applyDashboard(data);
				if (data.success) showStudent(data);
				else showError(data.message || "Card not registered");
			})
			.catch(function () { showError("Connection error — try again"); })
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
		photoEl.src = ((data.student && data.student.photo) || fallbackPhoto) + (String((data.student && data.student.photo) || "").indexOf("?") >= 0 ? "&" : "?") + "r=" + Date.now();
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
