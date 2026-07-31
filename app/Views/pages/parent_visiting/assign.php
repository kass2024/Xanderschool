<?php
$settings = $settings ?? ['card_sharing' => 1, 'min_visitors' => 2, 'max_per_card' => 2];
$groups = $students_by_class ?? [];
$minVisitors = (int) ($settings['min_visitors'] ?? 2);
$totalStudents = count($students ?? []);
$readyCount = 0;
$needCount = 0;
foreach ($students ?? [] as $s) {
	if ((int) ($s['visitor_count'] ?? 0) >= $minVisitors) {
		$readyCount++;
	} else {
		$needCount++;
	}
}
$fallbackAvatar = base_url('assets/images/fallback-avatar.png');
?>
<style>
.pv-page { --pv-navy:#0b1f4a; --pv-gold:#d4af37; --pv-bg:#f4f1ea; }
.pv-hero {
	background: linear-gradient(135deg, var(--pv-navy) 0%, #132a5c 100%);
	color: #fff; border-radius: 16px; padding: 22px 24px; margin-bottom: 20px;
	box-shadow: 0 12px 32px rgba(11,31,74,.18);
}
.pv-hero h4 { margin: 0 0 6px; font-weight: 700; }
.pv-stats { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 14px; }
.pv-stat {
	background: rgba(255,255,255,.1); border: 1px solid rgba(212,175,55,.35);
	border-radius: 12px; padding: 10px 16px; min-width: 120px;
}
.pv-stat b { display: block; font-size: 1.35rem; color: var(--pv-gold); }
.pv-stat span { font-size: .78rem; opacity: .85; }
.pv-toolbar {
	background: #fff; border-radius: 14px; padding: 16px 18px; margin-bottom: 18px;
	box-shadow: 0 4px 18px rgba(0,0,0,.06); display: flex; flex-wrap: wrap; gap: 14px; align-items: center;
}
.pv-search-wrap { flex: 1; min-width: 220px; position: relative; }
.pv-search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--pv-navy); }
.pv-search-wrap input {
	padding-left: 40px; border-radius: 12px; border: 1.5px solid #e2e8f0; height: 44px;
}
.pv-settings { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.pv-settings label { font-size: .82rem; font-weight: 600; color: #475569; margin: 0; }
.pv-settings select { border-radius: 10px; border: 1.5px solid #e2e8f0; height: 38px; font-size: .85rem; }

.pv-class-block {
	background: #fff; border-radius: 14px; margin-bottom: 14px; overflow: hidden;
	box-shadow: 0 4px 16px rgba(0,0,0,.05); border: 1px solid #eef2f7;
}
.pv-class-head {
	background: #f8fafc; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;
	border-bottom: 1px solid #eef2f7; cursor: pointer; user-select: none;
}
.pv-class-head h5 { margin: 0; font-size: .95rem; font-weight: 700; color: var(--pv-navy); }
.pv-class-head .meta { font-size: .78rem; color: #64748b; }
.pv-class-body { padding: 0; }
.pv-student-row {
	display: grid; grid-template-columns: 36px 1fr 100px 90px 120px; gap: 10px; align-items: center;
	padding: 12px 18px; border-bottom: 1px solid #f1f5f9; transition: background .15s;
}
.pv-student-row:last-child { border-bottom: none; }
.pv-student-row:hover { background: #faf8f4; }
.pv-student-row .name { font-weight: 600; color: #1e293b; }
.pv-student-row .reg { font-size: .78rem; color: #64748b; }
.pv-badge-ready { background: #dcfce7; color: #166534; border-radius: 999px; padding: 4px 10px; font-size: .75rem; font-weight: 700; }
.pv-badge-warn { background: #fef3c7; color: #92400e; border-radius: 999px; padding: 4px 10px; font-size: .75rem; font-weight: 700; }
.pv-btn-manage {
	background: var(--pv-navy); color: #fff; border: none; border-radius: 10px;
	padding: 7px 14px; font-size: .78rem; font-weight: 600; cursor: pointer;
}
.pv-btn-manage:hover { background: #132a5c; color: #fff; }

.pv-overlay {
	position: fixed; inset: 0; background: rgba(11,31,74,.45); z-index: 9990;
	display: none; align-items: stretch; justify-content: flex-end;
}
.pv-overlay.open { display: flex; }
.pv-drawer {
	width: min(720px, 100vw); background: #fff; height: 100%; overflow: auto;
	box-shadow: -8px 0 40px rgba(0,0,0,.15); animation: pvSlide .25s ease;
}
@keyframes pvSlide { from { transform: translateX(100%); } to { transform: translateX(0); } }
.pv-drawer-head {
	background: linear-gradient(135deg, var(--pv-navy), #132a5c); color: #fff;
	padding: 20px 22px; position: sticky; top: 0; z-index: 2;
}
.pv-drawer-head h5 { margin: 0 0 4px; font-weight: 700; }
.pv-drawer-head small { opacity: .85; }
.pv-drawer-close {
	position: absolute; right: 16px; top: 16px; background: rgba(255,255,255,.15);
	border: none; color: #fff; width: 36px; height: 36px; border-radius: 50%; font-size: 1.2rem; cursor: pointer;
}
.pv-drawer-body { padding: 20px 22px 32px; }

.pv-alert { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; font-size: .88rem; }

.pv-visitor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; }
.pv-visitor-card {
	border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 14px; background: #fafafa;
	position: relative; transition: border-color .2s, box-shadow .2s;
}
.pv-visitor-card.has-card { border-color: rgba(212,175,55,.5); }
.pv-visitor-card img {
	width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid #fff;
	box-shadow: 0 4px 12px rgba(0,0,0,.1); display: block; margin: 0 auto 10px;
}
.pv-visitor-card .v-name { font-weight: 700; text-align: center; font-size: .9rem; color: var(--pv-navy); }
.pv-visitor-card .v-meta { text-align: center; font-size: .75rem; color: #64748b; margin-top: 4px; }
.pv-visitor-card .v-card-tag {
	display: inline-block; margin: 8px auto 0; font-size: .68rem; font-weight: 700;
	padding: 3px 8px; border-radius: 999px; background: #dcfce7; color: #166534;
}
.pv-visitor-card .v-card-tag.none { background: #f1f5f9; color: #64748b; }
.pv-visitor-card .v-card-tag code { font-size: .72rem; letter-spacing: .04em; background: transparent; color: inherit; }
.pv-btn-card { background: #fef3c7; color: #92400e; }
.pv-btn-view-card { background: #ecfdf5; color: #065f46; }
.pv-visitor-actions { display: flex; gap: 6px; justify-content: center; margin-top: 10px; flex-wrap: wrap; }
.pv-visitor-actions button {
	border: none; border-radius: 8px; padding: 5px 10px; font-size: .72rem; font-weight: 600; cursor: pointer;
}
.pv-btn-edit { background: #e0e7ff; color: #3730a3; }
.pv-btn-del { background: #fee2e2; color: #991b1b; }

.pv-form-card {
	background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 18px;
}
.pv-form-card h6 { font-weight: 700; color: var(--pv-navy); margin: 0 0 14px; }
.pv-form-grid { display: grid; grid-template-columns: 100px 1fr; gap: 16px; }
.pv-photo-box {
	width: 100px; height: 100px; border-radius: 50%; border: 2px dashed #cbd5e1;
	display: flex; align-items: center; justify-content: center; overflow: hidden;
	cursor: pointer; background: #fff; position: relative;
}
.pv-photo-box img { width: 100%; height: 100%; object-fit: cover; }
#pvPhotoInput { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.pv-fields { display: grid; gap: 10px; }
.pv-fields input, .pv-fields select {
	border-radius: 10px; border: 1.5px solid #e2e8f0; padding: 10px 12px; font-size: .88rem;
}
.pv-card-row { display: flex; gap: 8px; align-items: stretch; }
.pv-card-row { display: flex; gap: 8px; align-items: stretch; }
.pv-card-row input:not([type=hidden]) { flex: 1; font-family: monospace; letter-spacing: .06em; text-transform: uppercase; }
.pv-card-row input[readonly] {
	background: #f1f5f9; cursor: default; color: var(--pv-navy);
}
.pv-scan-btn {
	background: var(--pv-gold); color: var(--pv-navy); border: none; border-radius: 10px;
	padding: 0 14px; font-weight: 700; font-size: .78rem; white-space: nowrap; cursor: pointer;
}
.pv-scan-btn.active { background: #166534; color: #fff; animation: pvPulse 1s infinite; }
@keyframes pvPulse { 0%,100%{opacity:1} 50%{opacity:.7} }
.pv-card-info {
	background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 10px 12px;
	font-size: .82rem; color: #1e40af; margin-top: 8px;
}
.pv-card-info.blocked { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.pv-card-info.shared { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.pv-assigned-cards {
	background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; margin-bottom: 16px;
}
.pv-assigned-cards h6 { font-size: .82rem; font-weight: 700; color: var(--pv-navy); margin: 0 0 10px; }
.pv-card-chip {
	display: inline-flex; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #e2e8f0;
	border-radius: 10px; padding: 8px 12px; margin: 0 8px 8px 0; font-size: .78rem; cursor: pointer;
}
.pv-card-chip:hover { border-color: var(--pv-gold); background: #fffbeb; }
.pv-card-chip code { font-weight: 700; color: var(--pv-navy); }
.pv-keep-card { display: flex; align-items: center; gap: 8px; font-size: .82rem; margin-top: 6px; }
.pv-card-section.disabled { opacity: .55; pointer-events: none; }
.pv-form-actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
.pv-btn-save-details {
	background: #fff; color: var(--pv-navy); border: 2px solid var(--pv-navy); border-radius: 12px;
	padding: 11px 18px; font-weight: 700; cursor: pointer;
}
.pv-btn-save {
	background: var(--pv-navy); color: #fff; border: none; border-radius: 12px;
	padding: 11px 22px; font-weight: 700; cursor: pointer;
}
.pv-btn-save:disabled { opacity: .6; cursor: not-allowed; }
.pv-btn-cancel { background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 11px 18px; cursor: pointer; }

@media (max-width: 768px) {
	.pv-student-row { grid-template-columns: 1fr; gap: 6px; }
	.pv-form-grid { grid-template-columns: 1fr; }
	.pv-photo-box { margin: 0 auto; }
}
</style>

<div class="container-fluid mt-3 pv-page">
	<div class="pv-hero">
		<h4><i class="fa fa-users"></i> Assign Parent Visitors</h4>
		<p class="mb-0 opacity-75" style="font-size:.9rem;">Register guardians, upload photos, assign RFID cards — grouped by class.</p>
		<div class="pv-stats">
			<div class="pv-stat"><b><?= $totalStudents ?></b><span>Students</span></div>
			<div class="pv-stat"><b><?= $readyCount ?></b><span>Ready (<?= $minVisitors ?>+ visitors)</span></div>
			<div class="pv-stat"><b><?= $needCount ?></b><span>Need visitors</span></div>
			<div class="pv-stat"><b><?= count($groups) ?></b><span>Classes</span></div>
		</div>
	</div>

	<div class="pv-toolbar">
		<div class="pv-search-wrap">
			<i class="fa fa-search"></i>
			<input type="text" id="pvSearch" class="form-control" placeholder="Search student by name, class or reg no...">
		</div>
		<div class="pv-settings">
			<label for="pvCardSharing"><i class="fa fa-id-card"></i> Card policy</label>
			<select id="pvCardSharing" title="Control whether visitors can share RFID cards">
				<option value="0" <?= (int)$settings['card_sharing'] === 0 ? 'selected' : '' ?>>One card per visitor (exclusive)</option>
				<option value="1" <?= (int)$settings['card_sharing'] === 1 ? 'selected' : '' ?>>Share card — same student only</option>
				<option value="2" <?= (int)$settings['card_sharing'] === 2 ? 'selected' : '' ?>>Share card — school-wide</option>
			</select>
			<label for="pvMinVisitors">Min visitors</label>
			<select id="pvMinVisitors">
				<?php for ($m = 1; $m <= 5; $m++): ?>
					<option value="<?= $m ?>" <?= $minVisitors === $m ? 'selected' : '' ?>><?= $m ?></option>
				<?php endfor; ?>
			</select>
		</div>
	</div>

	<div id="pvClassList">
		<?php if (empty($groups)): ?>
			<div class="alert alert-info">No active students found for the current academic year.</div>
		<?php else: ?>
			<?php foreach ($groups as $className => $classStudents): ?>
				<?php
				$classReady = 0;
				foreach ($classStudents as $cs) {
					if ((int)($cs['visitor_count'] ?? 0) >= $minVisitors) $classReady++;
				}
				?>
				<div class="pv-class-block" data-class="<?= esc(strtolower($className)) ?>">
					<div class="pv-class-head" data-toggle-class>
						<div>
							<h5><i class="fa fa-layer-group text-warning"></i> <?= esc($className) ?></h5>
							<span class="meta"><?= count($classStudents) ?> students · <?= $classReady ?> ready</span>
						</div>
						<i class="fa fa-chevron-down text-muted"></i>
					</div>
					<div class="pv-class-body">
						<?php $rowNum = 0; foreach ($classStudents as $s):
							$rowNum++;
							$vc = (int)($s['visitor_count'] ?? 0);
							$ready = $vc >= $minVisitors;
						?>
							<div class="pv-student-row" data-id="<?= (int)$s['id'] ?>"
								 data-search="<?= esc(strtolower($s['name'].' '.$s['regno'].' '.$className)) ?>">
								<div class="text-muted small"><?= $rowNum ?></div>
								<div>
									<div class="name"><?= esc($s['name']) ?></div>
									<div class="reg"><?= esc($s['regno'] ?? '') ?></div>
								</div>
								<div class="reg d-none d-md-block"><?= esc($s['regno'] ?? '') ?></div>
								<div>
									<?php if ($ready): ?>
										<span class="pv-badge-ready"><?= $vc ?> ready</span>
									<?php else: ?>
										<span class="pv-badge-warn"><?= $vc ?> / <?= $minVisitors ?></span>
									<?php endif; ?>
								</div>
								<div class="text-end">
									<button type="button" class="pv-btn-manage manageBtn"
											data-id="<?= (int)$s['id'] ?>"
											data-name="<?= esc($s['name']) ?>"
											data-class="<?= esc($className) ?>"
											data-regno="<?= esc($s['regno'] ?? '') ?>">
										<i class="fa fa-user-friends"></i> Manage
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>

<!-- Visitor drawer -->
<div class="pv-overlay" id="pvOverlay">
	<div class="pv-drawer">
		<div class="pv-drawer-head">
			<button type="button" class="pv-drawer-close" id="pvCloseDrawer">&times;</button>
			<h5 id="pvDrawerTitle">Visitors</h5>
			<small id="pvDrawerSub"></small>
		</div>
		<div class="pv-drawer-body">
			<div id="pvVisitorAlert" class="pv-alert d-none"></div>
			<div id="pvAssignedCards" class="pv-assigned-cards d-none"></div>
			<div id="pvVisitorsList" class="pv-visitor-grid"></div>

			<div class="pv-form-card">
				<h6 id="pvFormTitle"><i class="fa fa-user-plus"></i> Add visitor</h6>
				<form id="pvVisitorForm" novalidate>
					<input type="hidden" id="pvStudentId" name="student_id" value="">
					<input type="hidden" id="pvVisitorId" name="visitor_id" value="">
					<input type="hidden" id="pvClearCard" name="clear_card" value="0">
					<input type="hidden" id="pvSkipCard" name="skip_card" value="0">
					<div class="pv-form-grid">
						<label class="pv-photo-box" for="pvPhotoInput" title="Upload visitor photo">
							<img id="pvPhotoPreview" src="<?= $fallbackAvatar ?>" alt="">
							<input type="file" id="pvPhotoInput" name="photo" accept="image/jpeg,image/png,image/jpg">
						</label>
						<div class="pv-fields">
							<input type="text" id="pvNames" name="names" placeholder="Full names *" maxlength="150">
							<input type="text" id="pvPhone" name="phone" placeholder="Phone number" maxlength="50">
							<select id="pvRel" name="relationship">
								<option value="">Relationship</option>
								<option value="Mother">Mother</option>
								<option value="Father">Father</option>
								<option value="Guardian">Guardian</option>
								<option value="Sibling">Sibling</option>
								<option value="Relative">Relative</option>
								<option value="Other">Other</option>
							</select>

							<label class="pv-keep-card d-none" id="pvKeepCardWrap">
								<input type="checkbox" id="pvKeepCard">
								<span>Keep existing card (edit visitor details only)</span>
							</label>

							<div id="pvCardSection" class="pv-card-section">
								<div class="pv-card-row">
									<input type="text" id="pvCard" placeholder="Scan card — UID appears here" autocomplete="off" readonly>
									<input type="hidden" id="pvCardHidden" name="card" value="">
									<button type="button" class="pv-scan-btn" id="pvScanBtn"><i class="fa fa-wifi"></i> Scan</button>
								</div>
								<small class="text-muted">Click Scan, then swipe the card on your USB reader. Card UID is filled automatically.</small>
								<div id="pvCardInfo" class="pv-card-info d-none"></div>
								<select id="pvExistingCard" class="form-control mt-2" style="border-radius:10px;height:38px;font-size:.85rem;">
									<option value="">— Or pick an already assigned card —</option>
								</select>
							</div>
						</div>
					</div>
					<div class="pv-form-actions">
						<button type="button" class="pv-btn-save-details" id="pvSaveDetailsBtn"><i class="fa fa-user-check"></i> Save visitor only</button>
						<button type="button" class="pv-btn-save" id="pvSaveBtn"><i class="fa fa-save"></i> Save visitor &amp; card</button>
						<button type="button" class="pv-btn-cancel" id="pvResetForm">Clear form</button>
						<button type="button" class="pv-btn-cancel d-none" id="pvClearCardBtn">Remove card</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<script src="<?= base_url('assets/js/card-uid.js') ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
	const FALLBACK_AVATAR = <?= json_encode($fallbackAvatar) ?>;
	const MIN_VISITORS = <?= (int)$minVisitors ?>;
	let cardSharing = <?= (int)$settings['card_sharing'] ?>;
	let currentStudentId = 0;
	let scanMode = false;
	let assignCardOnlyMode = false;
	let assignCardVisitorId = 0;
	let cardBuffer = "";
	let saving = false;
	let loadedVisitors = [];

	function notify(type, msg) {
		if (typeof toastada !== "undefined") {
			if (type === "success") toastada.success(msg);
			else if (type === "warn") toastada.warning(msg);
			else toastada.error(msg);
		} else {
			alert(msg);
		}
	}

	function parseJsonResponse(r) {
		return r.text().then(function (text) {
			try { return JSON.parse(text); }
			catch (e) { throw new Error(text && text.indexOf("<") === 0 ? "Session expired — please refresh and log in again." : "Invalid server response."); }
		});
	}

	function escHtml(s) {
		return String(s == null ? "" : s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
	}

	// Search
	document.getElementById("pvSearch").addEventListener("input", function () {
		const term = this.value.toLowerCase().trim();
		document.querySelectorAll(".pv-student-row").forEach(function (row) {
			const match = !term || (row.getAttribute("data-search") || "").indexOf(term) !== -1;
			row.style.display = match ? "" : "none";
		});
		document.querySelectorAll(".pv-class-block").forEach(function (block) {
			const visible = block.querySelectorAll('.pv-student-row:not([style*="none"])').length;
			block.style.display = visible ? "" : "none";
		});
	});

	// Collapse class blocks
	document.querySelectorAll("[data-toggle-class]").forEach(function (head) {
		head.addEventListener("click", function () {
			const body = head.nextElementSibling;
			const open = body.style.display !== "none";
			body.style.display = open ? "none" : "";
			head.querySelector(".fa-chevron-down").classList.toggle("fa-rotate-180", !open);
		});
	});

	// Settings save
	function saveSettings() {
		const body = new URLSearchParams({
			card_sharing: document.getElementById("pvCardSharing").value,
			min_visitors: document.getElementById("pvMinVisitors").value
		});
		fetch("<?= base_url('parent_visiting/save_settings') ?>", {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: body.toString()
		}).then(parseJsonResponse).then(function (res) {
			if (res.success && res.settings) {
				cardSharing = parseInt(res.settings.card_sharing, 10) || 0;
				notify("success", "Visitor settings saved.");
			}
		}).catch(function (e) { notify("error", e.message); });
	}
	document.getElementById("pvCardSharing").addEventListener("change", saveSettings);
	document.getElementById("pvMinVisitors").addEventListener("change", saveSettings);

	// Drawer
	document.querySelectorAll(".manageBtn").forEach(function (btn) {
		btn.addEventListener("click", function () {
			openDrawer(btn.dataset.id, btn.dataset.name, btn.dataset.class, btn.dataset.regno);
		});
	});
	document.getElementById("pvCloseDrawer").addEventListener("click", closeDrawer);
	document.getElementById("pvOverlay").addEventListener("click", function (e) {
		if (e.target === this) closeDrawer();
	});

	function openDrawer(studentId, name, className, regno) {
		currentStudentId = studentId;
		document.getElementById("pvStudentId").value = studentId;
		document.getElementById("pvDrawerTitle").textContent = name;
		document.getElementById("pvDrawerSub").textContent = (className || "") + (regno ? " · Reg " + regno : "");
		document.getElementById("pvOverlay").classList.add("open");
		resetForm();
		loadVisitors(studentId);
	}
	function closeDrawer() {
		document.getElementById("pvOverlay").classList.remove("open");
		scanMode = false;
		updateScanBtn();
	}

	let cardGroups = [];
	let editingOriginalCard = "";
	let cardFromPicker = false;
	let cardReady = false;

	function setCardSectionEnabled(enabled) {
		document.getElementById("pvCardSection").classList.toggle("disabled", !enabled);
	}

	function updateSaveButtons() {
		const cardVal = document.getElementById("pvCardHidden").value.trim();
		const keepCard = document.getElementById("pvKeepCard").checked;
		const btnSave = document.getElementById("pvSaveBtn");
		const btnDetails = document.getElementById("pvSaveDetailsBtn");
		const hasScannedCard = !!(cardVal && cardReady && !(keepCard && editingOriginalCard));
		btnSave.classList.toggle("d-none", !hasScannedCard && !cardVal);
		btnDetails.textContent = hasScannedCard ? "Save without card" : "Save visitor only";
		btnSave.innerHTML = hasScannedCard
			? '<i class="fa fa-save"></i> Save visitor &amp; card'
			: '<i class="fa fa-save"></i> Save visitor &amp; card';
	}

	function setCardValue(card, isCanonical) {
		const val = (card || "").trim().toUpperCase();
		document.getElementById("pvCard").value = val;
		document.getElementById("pvCardHidden").value = val;
		cardFromPicker = !!isCanonical;
		if (!val) cardReady = false;
		updateSaveButtons();
	}

	function resetForm() {
		document.getElementById("pvVisitorForm").reset();
		document.getElementById("pvStudentId").value = currentStudentId;
		document.getElementById("pvVisitorId").value = "";
		document.getElementById("pvClearCard").value = "0";
		document.getElementById("pvSkipCard").value = "0";
		setCardValue("", false);
		document.getElementById("pvPhotoPreview").src = FALLBACK_AVATAR;
		document.getElementById("pvFormTitle").innerHTML = '<i class="fa fa-user-plus"></i> Add visitor';
		document.getElementById("pvClearCardBtn").classList.add("d-none");
		document.getElementById("pvKeepCardWrap").classList.add("d-none");
		document.getElementById("pvKeepCard").checked = false;
		document.getElementById("pvCardInfo").classList.add("d-none");
		editingOriginalCard = "";
		assignCardOnlyMode = false;
		assignCardVisitorId = 0;
		cardFromPicker = false;
		cardReady = false;
		setCardSectionEnabled(true);
		scanMode = false;
		updateScanBtn();
	}
	document.getElementById("pvResetForm").addEventListener("click", resetForm);

	document.getElementById("pvKeepCard").addEventListener("change", function () {
		setCardSectionEnabled(!this.checked);
		updateSaveButtons();
	});

	document.getElementById("pvPhotoInput").addEventListener("change", function () {
		const f = this.files && this.files[0];
		if (!f) return;
		if (f.size > 5 * 1024 * 1024) { notify("error", "Photo must be 5 MB or less."); this.value = ""; return; }
		const reader = new FileReader();
		reader.onload = function (e) { document.getElementById("pvPhotoPreview").src = e.target.result; };
		reader.readAsDataURL(f);
	});
	document.querySelector(".pv-photo-box").addEventListener("click", function () {
		document.getElementById("pvPhotoInput").click();
	});

	document.getElementById("pvScanBtn").addEventListener("click", function () {
		scanMode = !scanMode;
		cardBuffer = "";
		updateScanBtn();
		if (scanMode) {
			document.getElementById("pvCard").focus();
			notify("warn", "Scan mode ON — swipe card on reader.");
		}
	});
	function updateScanBtn() {
		const btn = document.getElementById("pvScanBtn");
		btn.classList.toggle("active", scanMode);
		btn.innerHTML = scanMode ? '<i class="fa fa-stop"></i> Stop' : '<i class="fa fa-wifi"></i> Scan';
	}

	function normalizeUID(uid) {
		return (window.CardUid && CardUid.toStorage) ? CardUid.toStorage(uid) : uid.trim().toUpperCase();
	}

	function copyCardUid(card) {
		if (!card) return;
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(card).then(function () {
				notify("success", "Card UID copied: " + card);
			}).catch(function () { notify("success", "Card UID: " + card); });
		} else {
			notify("success", "Card UID: " + card);
		}
	}

	function startAssignCard(v) {
		assignCardOnlyMode = true;
		assignCardVisitorId = parseInt(v.id, 10);
		document.getElementById("pvVisitorId").value = v.id;
		document.getElementById("pvNames").value = v.names || "";
		document.getElementById("pvFormTitle").innerHTML = '<i class="fa fa-id-card"></i> Assign card to ' + escHtml(v.names);
		setCardValue("", false);
		setCardSectionEnabled(true);
		scanMode = true;
		cardBuffer = "";
		updateScanBtn();
		document.getElementById("pvCardSection").scrollIntoView({ behavior: "smooth", block: "center" });
		notify("warn", "Scan mode ON — swipe card for " + (v.names || "visitor") + ".");
	}

	function assignCardOnly(cardRaw) {
		const body = new URLSearchParams({
			visitor_id: String(assignCardVisitorId),
			card: cardRaw,
			card_picked: "1"
		});
		return fetch("<?= base_url('parent_visiting/assign_card') ?>", {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: body.toString()
		}).then(parseJsonResponse);
	}

	function handleCardCapture(uid) {
		if (!uid || uid.length < 4) return;
		const normalized = normalizeUID(uid);
		setCardValue(normalized, true);
		scanMode = false;
		updateScanBtn();

		if (assignCardOnlyMode && assignCardVisitorId > 0) {
			assignCardOnly(normalized).then(function (res) {
				assignCardOnlyMode = false;
				assignCardVisitorId = 0;
				if (res.success) {
					const saved = res.card || raw;
					setCardValue(saved, true);
					notify("success", "Card assigned: " + saved);
					resetForm();
					loadVisitors(currentStudentId);
				} else {
					notify("error", res.error || "Could not assign card.");
					lookupCard(normalized, true);
				}
			}).catch(function (e) { notify("error", e.message); });
			return;
		}

		lookupCard(normalized, true);
	}

	document.addEventListener("keypress", function (e) {
		if (!scanMode && !assignCardOnlyMode) return;
		const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : "";
		if ((tag === "input" || tag === "textarea" || tag === "select") && e.target.id !== "pvCard") {
			if (!e.target.readOnly) return;
		}
		if (e.key === "Enter") {
			const uid = cardBuffer.trim();
			cardBuffer = "";
			if (uid.length >= 4) handleCardCapture(uid);
		} else if (e.key.length === 1) {
			cardBuffer += e.key;
		}
	});

	document.getElementById("pvExistingCard").addEventListener("change", function () {
		if (this.value) {
			setCardValue(this.value, true);
			document.getElementById("pvKeepCard").checked = false;
			setCardSectionEnabled(true);
			lookupCard(this.value, true);
		}
	});

	function lookupCard(cardRaw, fromPicker) {
		const info = document.getElementById("pvCardInfo");
		if (!cardRaw || cardRaw.length < 4) {
			info.classList.add("d-none");
			cardReady = false;
			updateSaveButtons();
			return;
		}
		const body = new URLSearchParams({
			card: cardRaw,
			card_picked: fromPicker ? "1" : "0",
			student_id: currentStudentId,
			visitor_id: document.getElementById("pvVisitorId").value || "0"
		});
		fetch("<?= base_url('parent_visiting/lookup_card') ?>", {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: body.toString()
		})
			.then(parseJsonResponse)
			.then(function (res) {
				if (!res.success) {
					cardReady = false;
					updateSaveButtons();
					info.className = "pv-card-info blocked";
					info.textContent = res.error || "Invalid card.";
					info.classList.remove("d-none");
					return;
				}
				if (res.card) setCardValue(res.card, true);
				const holders = res.holders || [];
				const maxPer = (res.settings && res.settings.max_per_card) ? res.settings.max_per_card : 2;
				cardReady = res.allowed !== false;
				if (holders.length === 0) {
					info.className = "pv-card-info";
					info.textContent = "Card is free — can be assigned to this visitor.";
				} else if (res.allowed) {
					info.className = "pv-card-info shared";
					info.textContent = "Shared card (" + holders.length + "/" + maxPer + "): " +
						holders.map(function (h) { return h.names; }).join(", ") +
						" — you can add another visitor on this card.";
				} else {
					info.className = "pv-card-info blocked";
					info.textContent = res.blocked_reason || "This card cannot be used.";
				}
				info.classList.remove("d-none");
				updateSaveButtons();
				if (res.card && res.allowed !== false) {
					notify("success", "Card ready: " + res.card);
				}
			})
			.catch(function () {
				cardReady = false;
				updateSaveButtons();
				info.classList.add("d-none");
			});
	}

	document.getElementById("pvClearCardBtn").addEventListener("click", function () {
		setCardValue("", false);
		document.getElementById("pvClearCard").value = "1";
		document.getElementById("pvKeepCard").checked = false;
		setCardSectionEnabled(true);
		document.getElementById("pvCardInfo").classList.add("d-none");
		cardReady = false;
		updateSaveButtons();
	});

	function renderAssignedCards(groups) {
		cardGroups = groups || [];
		const box = document.getElementById("pvAssignedCards");
		const select = document.getElementById("pvExistingCard");
		select.innerHTML = '<option value="">— Or pick an already assigned card —</option>';

		if (!cardGroups.length) {
			box.classList.add("d-none");
			box.innerHTML = "";
			return;
		}

		let html = '<h6><i class="fa fa-id-card"></i> Cards already assigned for this student</h6>';
		cardGroups.forEach(function (g) {
			const names = (g.visitors || []).map(function (v) { return v.names; }).join(" & ");
			html += '<span class="pv-card-chip" data-card="' + escHtml(g.card) + '">' +
				'<code>' + escHtml(g.card) + '</code> · ' + escHtml(names) +
				' <small>(' + (g.visitors || []).length + ')</small></span>';
			select.innerHTML += '<option value="' + escHtml(g.card) + '">' + escHtml(g.card) + ' — ' + escHtml(names) + '</option>';
		});
		box.innerHTML = html;
		box.classList.remove("d-none");
		box.querySelectorAll(".pv-card-chip").forEach(function (chip) {
			chip.addEventListener("click", function () {
				const c = chip.getAttribute("data-card");
				setCardValue(c, true);
				document.getElementById("pvKeepCard").checked = false;
				setCardSectionEnabled(true);
				lookupCard(c, true);
			});
		});
	}

	function loadVisitors(studentId) {
		const list = document.getElementById("pvVisitorsList");
		const alertBox = document.getElementById("pvVisitorAlert");
		list.innerHTML = '<div class="text-muted p-3">Loading...</div>';

		fetch("<?= base_url('parent_visiting/student_visitors') ?>/" + studentId)
			.then(parseJsonResponse)
			.then(function (res) {
				if (!res.success) {
					list.innerHTML = '<div class="text-danger">' + escHtml(res.error || "Failed") + '</div>';
					return;
				}
				const visitors = res.visitors || [];
				loadedVisitors = visitors;
				const minV = res.min_visitors || MIN_VISITORS;
				if (visitors.length < minV) {
					alertBox.classList.remove("d-none");
					alertBox.textContent = "This student has fewer than " + minV + " visitors (" + visitors.length + "/" + minV + "). Please add more.";
				} else {
					alertBox.classList.add("d-none");
				}
				if (res.settings) cardSharing = parseInt(res.settings.card_sharing, 10) || 1;
				renderAssignedCards(res.card_groups || []);
				updateRowBadge(studentId, visitors.length, minV);

				if (!visitors.length) {
					list.innerHTML = '<div class="text-muted p-2">No visitors yet — use the form below to register the first guardian.</div>';
					return;
				}

				let html = "";
				visitors.forEach(function (v) {
					const hasCard = !!(v.card && v.card.length);
					html += '<div class="pv-visitor-card' + (hasCard ? ' has-card' : '') + '">' +
						'<img src="' + escHtml(v.photo_url || FALLBACK_AVATAR) + '" alt="">' +
						'<div class="v-name">' + escHtml(v.names) + '</div>' +
						'<div class="v-meta">' + escHtml(v.relationship || "—") + '<br>' + escHtml(v.phone || "") + '</div>' +
						'<div class="text-center"><span class="v-card-tag' + (hasCard ? '' : ' none') + '">' +
						(hasCard ? '<i class="fa fa-id-card"></i> <code>' + escHtml(v.card) + '</code>' : 'No card assigned') + '</span></div>' +
						'<div class="pv-visitor-actions">' +
						(hasCard
							? '<button type="button" class="pv-btn-view-card viewCardBtn" data-card="' + escHtml(v.card) + '"><i class="fa fa-eye"></i> View card</button>'
							: '<button type="button" class="pv-btn-card assignCardBtn" data-id="' + v.id + '"><i class="fa fa-wifi"></i> Assign card</button>') +
						'<button type="button" class="pv-btn-edit editVisitorBtn" data-id="' + v.id + '"><i class="fa fa-edit"></i> Edit</button>' +
						'<button type="button" class="pv-btn-del delVisitorBtn" data-id="' + v.id + '"><i class="fa fa-trash"></i></button>' +
						'</div></div>';
				});
				list.innerHTML = html;

				list.querySelectorAll(".editVisitorBtn").forEach(function (b) {
					b.addEventListener("click", function () {
						const vid = parseInt(b.dataset.id, 10);
						const v = loadedVisitors.find(function (x) { return parseInt(x.id, 10) === vid; });
						if (v) editVisitor(v);
					});
				});
				list.querySelectorAll(".assignCardBtn").forEach(function (b) {
					b.addEventListener("click", function () {
						const vid = parseInt(b.dataset.id, 10);
						const v = loadedVisitors.find(function (x) { return parseInt(x.id, 10) === vid; });
						if (v) startAssignCard(v);
					});
				});
				list.querySelectorAll(".viewCardBtn").forEach(function (b) {
					b.addEventListener("click", function () {
						copyCardUid(b.dataset.card || "");
					});
				});
				list.querySelectorAll(".delVisitorBtn").forEach(function (b) {
					b.addEventListener("click", function () { deleteVisitor(b.dataset.id, studentId); });
				});
			})
			.catch(function (e) {
				list.innerHTML = '<div class="text-danger">' + escHtml(e.message) + '</div>';
			});
	}

	function editVisitor(v) {
		document.getElementById("pvVisitorId").value = v.id;
		document.getElementById("pvNames").value = v.names || "";
		document.getElementById("pvPhone").value = v.phone || "";
		document.getElementById("pvRel").value = v.relationship || "";
		setCardValue(v.card || "", true);
		document.getElementById("pvClearCard").value = "0";
		document.getElementById("pvPhotoPreview").src = v.photo_url || FALLBACK_AVATAR;
		document.getElementById("pvFormTitle").innerHTML = '<i class="fa fa-edit"></i> Edit visitor';
		editingOriginalCard = v.card || "";
		const hasCard = !!(v.card && v.card.length);
		document.getElementById("pvClearCardBtn").classList.toggle("d-none", !hasCard);
		document.getElementById("pvKeepCardWrap").classList.remove("d-none");
		document.getElementById("pvKeepCard").checked = hasCard;
		setCardSectionEnabled(!hasCard);
		if (hasCard) lookupCard(v.card, true);
		document.getElementById("pvVisitorForm").scrollIntoView({ behavior: "smooth", block: "start" });
	}

	function updateRowBadge(studentId, count, minV) {
		const row = document.querySelector('.pv-student-row[data-id="' + studentId + '"]');
		if (!row) return;
		const cell = row.children[3];
		if (!cell) return;
		if (count >= minV) {
			cell.innerHTML = '<span class="pv-badge-ready">' + count + ' ready</span>';
		} else {
			cell.innerHTML = '<span class="pv-badge-warn">' + count + ' / ' + minV + '</span>';
		}
	}

	function saveVisitor(withCard) {
		if (saving) return;
		const names = document.getElementById("pvNames").value.trim();
		if (!names) { notify("error", "Visitor name is required."); return; }

		const cardVal = document.getElementById("pvCardHidden").value.trim();
		if (withCard && !cardVal) {
			notify("error", "Scan a card first, or use “Save visitor only”.");
			scanMode = true;
			updateScanBtn();
			return;
		}

		scanMode = false;
		updateScanBtn();

		const keepCard = document.getElementById("pvKeepCard").checked;
		// When a card is scanned and validated, always save it unless editing details-only.
		if (cardVal && cardReady && !(keepCard && editingOriginalCard)) {
			withCard = true;
		}
		if (withCard && keepCard && editingOriginalCard) {
			notify("error", "Uncheck “Keep existing card” to assign a new card, or use Save visitor only.");
			return;
		}

		saving = true;
		const btnSave = document.getElementById("pvSaveBtn");
		const btnDetails = document.getElementById("pvSaveDetailsBtn");
		const activeBtn = withCard ? btnSave : btnDetails;
		const oldTxt = activeBtn.innerHTML;
		btnSave.disabled = true;
		btnDetails.disabled = true;
		activeBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

		const fd = new FormData(document.getElementById("pvVisitorForm"));
		fd.set("names", names);
		fd.set("save_with_card", withCard ? "1" : "0");
		fd.set("skip_card", withCard ? "0" : "1");
		if (withCard && cardVal) {
			fd.set("card", cardVal);
			fd.set("card_picked", cardFromPicker ? "1" : "0");
		} else {
			fd.delete("card");
			fd.delete("card_picked");
		}
		if (!document.getElementById("pvPhotoInput").files.length) {
			fd.delete("photo");
		}

		const controller = new AbortController();
		const timeoutId = setTimeout(function () { controller.abort(); }, 45000);

		fetch("<?= base_url('parent_visiting/save_visitor') ?>", {
			method: "POST",
			body: fd,
			signal: controller.signal
		})
			.then(function (r) {
				if (!r.ok) {
					throw new Error("Server error (" + r.status + "). Please try again.");
				}
				return parseJsonResponse(r);
			})
			.then(function (res) {
				if (res.success) {
					const savedCard = res.visitor && res.visitor.card;
					if (withCard && cardVal && !savedCard) {
						notify("error", "Visitor saved but card was not stored. Please click Assign card and scan again.");
					} else {
						notify("success", res.warning || (savedCard
							? "Visitor saved with card " + savedCard + "."
							: "Visitor saved successfully."));
					}
					resetForm();
					loadVisitors(currentStudentId);
				} else {
					notify("error", res.error || "Could not save visitor.");
					if (res.holders && res.holders.length) {
						const info = document.getElementById("pvCardInfo");
						info.className = "pv-card-info blocked";
						info.textContent = (res.error || "Card blocked") + " — Currently: " +
							res.holders.map(function (h) { return h.names; }).join(", ");
						info.classList.remove("d-none");
					}
				}
			})
			.catch(function (err) {
				const msg = (err && err.name === "AbortError")
					? "Save timed out. Please try again."
					: (err.message || "Save failed.");
				notify("error", msg);
			})
			.finally(function () {
				clearTimeout(timeoutId);
				saving = false;
				btnSave.disabled = false;
				btnDetails.disabled = false;
				activeBtn.innerHTML = oldTxt;
			});
	}

	document.getElementById("pvSaveDetailsBtn").addEventListener("click", function () { saveVisitor(false); });
	document.getElementById("pvSaveBtn").addEventListener("click", function () { saveVisitor(true); });

	document.getElementById("pvVisitorForm").addEventListener("submit", function (e) {
		e.preventDefault();
		saveVisitor(true);
	});

	function deleteVisitor(id, studentId) {
		if (!confirm("Remove this visitor? They will be deactivated.")) return;
		fetch("<?= base_url('parent_visiting/delete_visitor') ?>", {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: "visitor_id=" + encodeURIComponent(id)
		})
			.then(parseJsonResponse)
			.then(function (res) {
				if (res.success) {
					notify("success", "Visitor removed.");
					loadVisitors(studentId);
				} else {
					notify("error", res.error || "Failed.");
				}
			})
			.catch(function (e) { notify("error", e.message); });
	}

	updateSaveButtons();
});
</script>
