<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Staff Attendance Scanner</title>
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
	--late: #c2410c;
	--late-bg: #fff7ed;
	--ok: #0369a1;
	--ok-bg: #e0f2fe;
	--brand: #0a66b7;
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
.shell { width: min(1180px, 100%); margin: 0 auto; }
.topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.eyebrow { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
.title { font-size: clamp(1.35rem, 2.4vw, 1.85rem); font-weight: 800; color: var(--brand); letter-spacing: -0.03em; line-height: 1.15; }
.school-name { margin-top: 2px; color: var(--muted); font-size: .92rem; }
.shift-hint { margin-top: 8px; font-size: .82rem; color: #0369a1; }
.shift-hint a { color: #0369a1; }
.meta {
	text-align: right; background: #fff; border: 1px solid var(--line); border-radius: 16px;
	padding: 12px 16px; min-width: 168px; box-shadow: var(--shadow);
}
#clock { font-size: 1.35rem; font-weight: 800; font-variant-numeric: tabular-nums; }
#readyLabel { color: var(--muted); font-size: .85rem; margin-top: 2px; }
.kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 16px; }
.kpi { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px 13px; box-shadow: var(--shadow); min-width: 0; }
.kpi .lbl { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); }
.kpi .val { margin-top: 4px; font-size: clamp(1.55rem, 2.6vw, 2.05rem); font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
.kpi .sub { margin-top: 2px; font-size: .75rem; color: var(--muted); }
.kpi.inside .val { color: var(--brand); }
.kpi.in .val { color: var(--in); }
.kpi.out .val { color: var(--out); }
.kpi.late .val { color: var(--late); }
.kpi.ok .val { color: var(--in); }
.board { display: grid; grid-template-columns: 1.15fr .85fr; gap: 16px; }
@media (max-width: 860px) {
	.kpi-grid { grid-template-columns: 1fr 1fr; }
	.board { grid-template-columns: 1fr; }
}
.scan-card, .recent-card {
	background: #fff; border: 1px solid var(--line); border-radius: 20px; box-shadow: var(--shadow);
}
.scan-card { padding: 22px; min-height: 340px; }
.layout { display: grid; grid-template-columns: 168px 1fr; gap: 22px; align-items: start; }
@media (max-width: 640px) { .layout { grid-template-columns: 1fr; } }
.photo { width: 168px; height: 168px; object-fit: cover; border-radius: 18px; background: #e2e8f0; display: block; }
.name { font-size: 1.55rem; font-weight: 800; line-height: 1.2; }
.post { margin-top: 4px; color: var(--muted); font-weight: 650; }
.shift-line { margin-top: 8px; font-size: .92rem; color: #0369a1; font-weight: 700; }
.status {
	margin-top: 14px; display: inline-flex; align-items: center; justify-content: center;
	min-width: 88px; padding: 10px 18px; border-radius: 14px; font-size: 1.4rem; font-weight: 800; letter-spacing: .04em;
}
.status.in { background: var(--in-bg); color: var(--in); }
.status.out { background: var(--out-bg); color: var(--out); }
.verdict { margin-top: 10px; display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: .8rem; font-weight: 800; }
.verdict.ontime, .verdict.early { background: var(--in-bg); color: var(--in); }
.verdict.late, .verdict.early_leave { background: var(--late-bg); color: var(--late); }
.verdict.overtime { background: var(--ok-bg); color: var(--ok); }
.verdict.none { background: #f1f5f9; color: var(--muted); }
.hint { margin-top: 16px; display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: .9rem; }
.ready-dot { width: 10px; height: 10px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45); animation: pulse 1.6s infinite; }
.ready-dot.busy { background: #f59e0b; animation: none; }
.ready-dot.error { background: #dc2626; animation: none; }
@keyframes pulse {
	0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45); }
	70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
	100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}
.scan-time { margin-top: 8px; font-size: .9rem; color: #94a3b8; min-height: 1.2em; }
.flash-green { box-shadow: 0 0 0 4px rgba(5, 150, 105, .25); }
.flash-red { box-shadow: 0 0 0 4px rgba(220, 38, 38, .22); }
.recent-card { padding: 16px 16px 10px; display: flex; flex-direction: column; min-height: 340px; }
.recent-card h3 { margin: 0 0 12px; font-size: .95rem; font-weight: 800; }
.recent-list { display: flex; flex-direction: column; gap: 8px; overflow: auto; max-height: 360px; }
.recent-empty { color: var(--muted); font-size: .9rem; padding: 18px 4px; }
.recent-row {
	display: grid; grid-template-columns: 40px 1fr auto; gap: 10px; align-items: center;
	padding: 8px; border-radius: 12px; background: #f8fafc; border: 1px solid #eef2f7;
}
.recent-row img { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; background: #e2e8f0; }
.recent-row .who { font-weight: 700; font-size: .88rem; line-height: 1.2; }
.recent-row .meta-s { color: var(--muted); font-size: .75rem; }
.recent-row .times { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.pill { font-size: .72rem; font-weight: 800; padding: 4px 8px; border-radius: 999px; white-space: nowrap; }
.pill.in { background: var(--in-bg); color: var(--in); }
.pill.out { background: var(--out-bg); color: var(--out); }
.pill.wait { background: var(--late-bg); color: var(--late); }
.pill.late { background: var(--late-bg); color: var(--late); }
.pill.ontime { background: var(--in-bg); color: var(--in); }
</style>
</head>
<body>
<?php
$schoolName = $school_name ?? '';
$settingsUrl = $settings_url ?? base_url('settings');
$fallbackPhoto = profile_photo_url(null);
$kpi = $kpi ?? [];
$recent = $recent ?? [];
?>
<div class="shell">
	<div class="topbar">
		<div>
			<div class="eyebrow">NFC kiosk</div>
			<div class="title">Staff Attendance Scanner</div>
			<?php if ($schoolName !== '') : ?>
				<div class="school-name"><?= esc($schoolName); ?></div>
			<?php endif; ?>
			<div class="shift-hint">IN/OUT follows each staff member’s shift hours from <a href="<?= esc($settingsUrl, 'attr'); ?>">School settings</a>.</div>
		</div>
		<div class="meta">
			<div id="clock">--:--:--</div>
			<div id="readyLabel">Ready for card tap</div>
		</div>
	</div>

	<div class="kpi-grid">
		<div class="kpi inside"><div class="lbl">Inside now</div><div class="val" id="kpiInside"><?= (int) ($kpi['inside'] ?? 0); ?></div><div class="sub">Still on site</div></div>
		<div class="kpi in"><div class="lbl">Checked IN</div><div class="val" id="kpiIn"><?= (int) ($kpi['checked_in'] ?? 0); ?></div><div class="sub">Entries today</div></div>
		<div class="kpi out"><div class="lbl">Checked OUT</div><div class="val" id="kpiOut"><?= (int) ($kpi['checked_out'] ?? 0); ?></div><div class="sub">Exits today</div></div>
		<div class="kpi late"><div class="lbl">Late today</div><div class="val" id="kpiLate"><?= (int) ($kpi['late'] ?? 0); ?></div><div class="sub">After shift start</div></div>
		<div class="kpi ok"><div class="lbl">On time</div><div class="val" id="kpiOn"><?= (int) ($kpi['ontime'] ?? 0); ?></div><div class="sub">On or before start</div></div>
	</div>

	<div class="board">
		<div class="scan-card" id="container">
			<div class="layout">
				<div><img id="photo" class="photo" src="<?= esc($fallbackPhoto, 'attr'); ?>" alt="Staff photo"></div>
				<div>
					<div id="name" class="name">Waiting for card…</div>
					<div id="post" class="post"></div>
					<div id="shiftLine" class="shift-line"></div>
					<div id="status"></div>
					<div id="verdict" class="verdict" hidden></div>
					<div class="hint">
						<span id="readyDot" class="ready-dot" aria-hidden="true"></span>
						<span id="hintText">Tap staff card on the reader</span>
					</div>
					<div id="scanTime" class="scan-time"></div>
				</div>
			</div>
		</div>
		<div class="recent-card">
			<h3>Today’s activity</h3>
			<div class="recent-list" id="recentList">
				<div class="recent-empty">No staff scans yet today.</div>
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
	const container = document.getElementById("container");
	const readyDot = document.getElementById("readyDot");
	const hintText = document.getElementById("hintText");
	const clockEl = document.getElementById("clock");
	const photoEl = document.getElementById("photo");
	const recentList = document.getElementById("recentList");
	const fallbackPhoto = <?= json_encode($fallbackPhoto) ?>;
	const apiURL = <?= json_encode(base_url('scan-staff-card')) ?>;
	const statsURL = <?= json_encode($stats_url ?? base_url('staff-attendance-card/stats')) ?>;
	const initialRecent = <?= json_encode($recent, JSON_UNESCAPED_UNICODE) ?>;

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
		readyDot.classList.remove("busy", "error");
		if (state === "busy") readyDot.classList.add("busy");
		if (state === "error") readyDot.classList.add("error");
		if (message) hintText.textContent = message;
	}

	function escapeHtml(s) {
		const d = document.createElement("div");
		d.textContent = s == null ? "" : String(s);
		return d.innerHTML;
	}

	function renderKpi(kpi) {
		kpi = kpi || {};
		document.getElementById("kpiInside").textContent = kpi.inside || 0;
		document.getElementById("kpiIn").textContent = kpi.checked_in || 0;
		document.getElementById("kpiOut").textContent = kpi.checked_out || 0;
		document.getElementById("kpiLate").textContent = kpi.late || 0;
		document.getElementById("kpiOn").textContent = kpi.ontime || 0;
	}

	function renderRecent(rows) {
		if (!rows || !rows.length) {
			recentList.innerHTML = '<div class="recent-empty">No staff scans yet today.</div>';
			return;
		}
		recentList.innerHTML = rows.map(function (r) {
			const inTime = r.time_in ? escapeHtml(r.time_in) : "—";
			const outPill = r.time_out
				? '<span class="pill out">OUT ' + escapeHtml(r.time_out) + '</span>'
				: '<span class="pill wait">OUT —</span>';
			const lateCls = r.in_code === "late" ? " late" : (r.in_code === "ontime" || r.in_code === "early" ? " ontime" : "");
			const meta = [r.post, r.shift].filter(Boolean).join(" · ");
			return '<div class="recent-row">' +
				'<img src="' + escapeHtml(r.photo || fallbackPhoto) + '" alt="">' +
				'<div><div class="who">' + escapeHtml(r.name) + '</div>' +
				'<div class="meta-s">' + escapeHtml(meta) + '</div></div>' +
				'<div class="times">' +
					'<span class="pill in' + lateCls + '">IN ' + inTime + (r.in_label && r.in_code === "late" ? " · Late" : "") + '</span>' +
					outPill +
				'</div></div>';
		}).join("");
	}

	function applyDashboard(data) {
		if (data && data.kpi) renderKpi(data.kpi);
		if (data && data.recent) renderRecent(data.recent);
	}

	renderRecent(initialRecent);
	setInterval(function () {
		fetch(statsURL, { cache: "no-store", headers: { "Accept": "application/json" }, credentials: "same-origin" })
			.then(function (res) { return res.json(); })
			.then(applyDashboard)
			.catch(function () {});
	}, 15000);

	document.addEventListener("keydown", function (e) {
		if (e.target && (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA")) return;
		if (e.key === "Enter") {
			const raw = buffer.trim();
			buffer = "";
			if (raw.length > 3) scan(normalizeUID(raw));
			return;
		}
		if (e.key.length === 1) buffer += e.key;
	});

	function scan(uid) {
		if (!uid || scanning) return;
		const now = Date.now();
		if (uid === lastUID && now - lastScanAt < 1200) return;
		scanning = true;
		lastUID = uid;
		lastScanAt = now;
		setReadyState("busy", "Processing card…");
		fetch(apiURL + "?card=" + encodeURIComponent(uid), {
			method: "GET",
			cache: "no-store",
			headers: { "Accept": "application/json" },
			credentials: "same-origin"
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				applyDashboard(data);
				if (data.success) showStaff(data);
				else showError(data.message || "Card not registered");
			})
			.catch(function () { showError("Connection error — try again"); })
			.finally(function () {
				setTimeout(function () {
					scanning = false;
					setReadyState("ready", "Tap staff card on the reader");
				}, 900);
			});
	}

	function showStaff(data) {
		document.getElementById("name").textContent = (data.staff && data.staff.name) || "Unknown";
		document.getElementById("post").textContent = (data.staff && data.staff.post) || "";
		const shiftTitle = (data.shift && data.shift.title) || "";
		const shiftHours = (data.shift && data.shift.hours) || "";
		document.getElementById("shiftLine").textContent = [shiftTitle, shiftHours].filter(Boolean).join(" · ");
		document.getElementById("scanTime").textContent = data.time ? ("Recorded at " + data.time) : "";
		delete photoEl.dataset.fallbackApplied;
		photoEl.src = ((data.staff && data.staff.photo) || fallbackPhoto) + "?r=" + Date.now();
		const statusEl = document.getElementById("status");
		statusEl.textContent = data.status || "";
		statusEl.className = data.status === "IN" ? "status in" : "status out";
		const v = data.verdict || {};
		const vEl = document.getElementById("verdict");
		vEl.hidden = !v.label;
		vEl.className = "verdict " + (v.code || "none");
		vEl.textContent = v.label ? (v.label + (v.detail ? " — " + v.detail : "")) : "";
		container.classList.add(data.status === "IN" ? "flash-green" : "flash-red");
		setTimeout(function () { container.classList.remove("flash-green", "flash-red"); }, 900);
		setReadyState("ready", "Scan complete — ready for next card");
	}

	function showError(msg) {
		document.getElementById("name").textContent = msg;
		document.getElementById("post").textContent = "";
		document.getElementById("shiftLine").textContent = "";
		document.getElementById("status").textContent = "";
		document.getElementById("status").className = "";
		document.getElementById("verdict").hidden = true;
		document.getElementById("scanTime").textContent = "";
		delete photoEl.dataset.fallbackApplied;
		photoEl.src = fallbackPhoto;
		container.classList.add("flash-red");
		setTimeout(function () { container.classList.remove("flash-red"); }, 900);
		setReadyState("error", "Card not recognized — try again");
	}
});
</script>
</body>
</html>
