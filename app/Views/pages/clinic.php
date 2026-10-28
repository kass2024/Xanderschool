<?php
$date = (string) ($clinic_date ?? date('Y-m-d'));
$pretty = date('D j M Y', strtotime($date));
$error = session()->getFlashdata('clinic_error');
$canRecord = !empty($clinic_can_record);
$outcomeLabel = static function (string $outcome): string {
	if ($outcome === 'hospital') {
		return 'Taken to hospital';
	}
	if ($outcome === 'home') {
		return 'Sent home';
	}
	return 'Treated here';
};
$visitTime = static function ($value): string {
	$value = trim((string) $value);
	if ($value === '') {
		return '—';
	}
	try {
		$dt = new DateTimeImmutable($value, new DateTimeZone('Africa/Cairo'));
		return $dt->setTimezone(new DateTimeZone('Africa/Kigali'))->format('H:i');
	} catch (Exception $e) {
		return '—';
	}
};
?>
<style>
	.cln { --blue:#2563eb; --ink:#0f172a; --muted:#64748b; --line:#e6edf5; --card:#fff; color:var(--ink); }
	.cln * { box-sizing: border-box; }
	.cln-crumb { color:#94a3b8; font-size:13px; margin:0 0 12px; }
	.cln-crumb strong { color:#334155; font-weight:600; }
	.cln-top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px; flex-wrap:wrap; }
	.cln-title { display:flex; gap:14px; align-items:flex-start; }
	.cln-title h2 { margin:0; font-size:28px; font-weight:800; letter-spacing:-.02em; }
	.cln-title p { margin:2px 0 0; color:var(--muted); font-size:14px; }
	.cln-badge-ico { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
	.cln-badge-ico svg { width:22px; height:22px; }
	.cln-badge-ico.blue { background:#dbeafe; color:#2563eb; }
	.cln-badge-ico.green { background:#dcfce7; color:#16a34a; }
	.cln-badge-ico.violet { background:#ede9fe; color:#7c3aed; }
	.cln-badge-ico.rose { background:#ffe4e6; color:#e11d48; }
	.cln-badge-ico.amber { background:#ffedd5; color:#ea580c; }
	.cln-badge-ico.pink { background:#fce7f3; color:#db2777; }
	.cln-kpi .cln-badge-ico { width:36px; height:36px; border-radius:10px; }
	.cln-kpi .cln-badge-ico svg { width:18px; height:18px; }
	.cln-datebar { display:flex; align-items:flex-end; gap:8px; flex-wrap:wrap; }
	.cln-datelabel { display:block; font-size:12px; color:#94a3b8; font-weight:600; margin-bottom:4px; }
	.cln-datebox { position:relative; display:flex; align-items:center; gap:8px; min-width:210px; height:42px; padding:0 12px; background:#fff; border:1px solid var(--line); border-radius:10px; color:#334155; font-weight:600; }
	.cln-datebox svg { width:16px; height:16px; color:#64748b; flex:0 0 auto; }
	.cln-datebox input { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; }
	.cln-btn { height:42px; border-radius:10px; border:1px solid var(--line); background:#fff; color:#334155; font-weight:700; padding:0 16px; display:inline-flex; align-items:center; gap:8px; cursor:pointer; }
	.cln-btn svg { width:16px; height:16px; }
	.cln-btn.primary { background:var(--blue); border-color:var(--blue); color:#fff; }
	.cln-btn.primary:hover { background:#1d4ed8; }
	.cln-btn.ghost:hover { background:#f8fafc; }
	.cln-kpis { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
	.cln-kpi { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:14px 14px 12px; box-shadow:0 1px 2px rgba(15,23,42,.04); min-width:0; }
	.cln-kpi__top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
	.cln-kpi__top > span:first-child { font-size:11px; font-weight:800; letter-spacing:.04em; color:#94a3b8; text-transform:uppercase; }
	.cln-kpi strong { display:block; margin-top:8px; font-size:26px; line-height:1; font-weight:800; }
	.cln-kpi small { display:block; margin-top:6px; color:#94a3b8; font-size:12px; }
	.cln-card { background:var(--card); border:1px solid var(--line); border-radius:16px; box-shadow:0 1px 2px rgba(15,23,42,.04); padding:18px 18px 16px; margin-bottom:14px; }
	.cln-card h3 { margin:0; font-size:16px; font-weight:800; }
	.cln-card .cln-sub { margin:2px 0 0; color:var(--muted); font-size:13px; }
	.cln-head { display:flex; gap:10px; align-items:flex-start; margin-bottom:16px; }
	.cln-error { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; border-radius:10px; padding:10px 12px; margin-bottom:12px; font-weight:700; }
	.cln-grid { display:grid; grid-template-columns:1.2fr 1fr 1fr; gap:14px; }
	.cln-field label { display:block; font-size:13px; font-weight:700; color:#334155; margin-bottom:6px; }
	.cln-field label.cln-split { display:flex; justify-content:space-between; align-items:baseline; gap:8px; }
	.cln-field label em { color:#ef4444; font-style:normal; }
	.cln-count { color:#94a3b8; font-weight:600; font-size:12px; }
	.cln-input { position:relative; }
	.cln-input > svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:#94a3b8; pointer-events:none; }
	.cln-input input, .cln-input select, .cln-extra input, .cln-extra select { width:100%; height:42px; border:1px solid var(--line); border-radius:10px; padding:0 12px 0 36px; background:#fff; color:var(--ink); outline:none; }
	.cln-input input:focus, .cln-extra input:focus, .cln-extra select:focus { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
	.cln-hits { position:absolute; z-index:20; left:0; right:0; top:46px; background:#fff; border:1px solid var(--line); border-radius:10px; box-shadow:0 8px 24px rgba(15,23,42,.08); overflow:hidden; }
	.cln-hit { display:block; width:100%; text-align:left; background:#fff; border:0; border-bottom:1px solid #f1f5f9; padding:8px 12px; cursor:pointer; }
	.cln-hit:hover { background:#f8fafc; }
	.cln-hit strong { display:block; color:var(--ink); }
	.cln-hit small { color:var(--muted); }
	.cln-picked { margin-top:6px; font-size:13px; font-weight:700; color:#0f766e; min-height:18px; }
	.cln-ask { margin:16px 0 8px; font-size:13px; font-weight:700; }
	.cln-ask em { color:#ef4444; font-style:normal; }
	.cln-outcomes { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
	.cln-outcome { display:flex; align-items:center; gap:10px; border:1px solid var(--line); border-radius:12px; padding:12px; margin:0; cursor:pointer; background:#fff; min-height:72px; }
	.cln-outcome.is-on { border-color:#93c5fd; background:#f8fbff; box-shadow:0 0 0 1px #93c5fd; }
	.cln-outcome input { margin:0; accent-color:#2563eb; width:16px; height:16px; flex:0 0 auto; }
	.cln-outcome strong { display:block; font-size:14px; }
	.cln-outcome small { display:block; color:var(--muted); font-size:12px; line-height:1.3; }
	.cln-outcome__ico { width:28px; height:28px; border-radius:8px; background:#eff6ff; color:#2563eb; display:flex; align-items:center; justify-content:center; flex:0 0 auto; }
	.cln-outcome__ico svg { width:16px; height:16px; }
	.cln-extra { margin-top:12px; max-width:320px; }
	.cln-extra label { display:block; font-size:13px; font-weight:700; margin-bottom:6px; }
	.cln-extra input, .cln-extra select { padding-left:12px; }
	.cln-actions { display:flex; gap:8px; margin-top:16px; }
	.cln-tablecard { padding:0; overflow:hidden; }
	.cln-tablehead { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 18px; flex-wrap:wrap; }
	.cln-search { position:relative; min-width:240px; }
	.cln-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:#94a3b8; }
	.cln-search input { width:100%; height:40px; border:1px solid var(--line); border-radius:10px; padding:0 12px 0 36px; outline:none; }
	.cln-search input:focus { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
	.cln-tablewrap { overflow-x:auto; }
	.cln table { width:100%; border-collapse:collapse; margin:0; }
	.cln th { text-align:left; font-size:11px; letter-spacing:.04em; text-transform:uppercase; color:#94a3b8; font-weight:800; background:#f8fafc; padding:12px 14px; border-top:1px solid #f1f5f9; white-space:nowrap; }
	.cln td { padding:14px; border-top:1px solid #f1f5f9; vertical-align:middle; font-size:14px; }
	.cln tbody tr:hover { background:#fafcff; }
	.cln-name { font-weight:800; }
	.cln-reg { display:block; color:#94a3b8; font-size:12px; font-weight:600; margin-top:2px; }
	.cln-mark { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:4px 10px; font-weight:700; font-size:12px; white-space:nowrap; }
	.cln-mark i { width:7px; height:7px; border-radius:50%; background:currentColor; display:inline-block; }
	.cln-mark.here { background:#dcfce7; color:#15803d; }
	.cln-mark.hospital { background:#ffedd5; color:#c2410c; }
	.cln-mark.home { background:#e0f2fe; color:#0369a1; }
	.cln-remove { display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 10px; border-radius:8px; border:1px solid #fecaca; background:#fff; color:#ef4444; font-weight:700; font-size:13px; cursor:pointer; }
	.cln-remove svg { width:14px; height:14px; }
	.cln-remove:hover { background:#fef2f2; }
	.cln-empty { padding:36px 16px; text-align:center; color:var(--muted); }
	.cln-none { display:none; }
	@media (max-width: 1100px) {
		.cln-kpis { grid-template-columns:repeat(3,minmax(0,1fr)); }
		.cln-grid, .cln-outcomes { grid-template-columns:1fr; }
	}
	@media (max-width: 640px) { .cln-kpis { grid-template-columns:1fr 1fr; } }
	@media print {
		.cln-form, .cln-btn, .cln-search, .cln-act, .app-sidebar-wrapper, .app-header, .hamburger, .app-footer, .header-mobile-wrapper { display:none !important; }
		.cln-card, .cln-kpi { box-shadow:none; }
	}
</style>
<div class="cln">
	<div class="cln-crumb">Health &nbsp;›&nbsp; <strong>Clinic</strong></div>
	<?php if ($error) : ?><div class="cln-error"><?= esc($error); ?></div><?php endif; ?>
	<div class="cln-top">
		<div class="cln-title">
			<span class="cln-badge-ico blue">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.5 12.5l2-2a3 3 0 014.2 0l.3.3 2.2-2.2a3 3 0 014.2 0l2 2"/><path d="M8 14.5v3a2 2 0 002 2h1"/><path d="M14 9.5V7a2 2 0 012-2h1"/><circle cx="6" cy="17" r="2"/><circle cx="18" cy="7" r="2"/></svg>
			</span>
			<div>
				<h2>Clinic</h2>
				<p>Record and manage sick students seen by the school nurse.</p>
			</div>
		</div>
		<form class="cln-datebar" method="get" action="<?= base_url('clinic'); ?>">
			<div>
				<span class="cln-datelabel">Date</span>
				<label class="cln-datebox">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
					<span><?= esc($pretty); ?></span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
					<input type="date" name="date" value="<?= esc($date, 'attr'); ?>" max="<?= esc(date('Y-m-d'), 'attr'); ?>">
				</label>
			</div>
			<button class="cln-btn primary" type="submit">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
				Show
			</button>
			<button class="cln-btn ghost" type="button" onclick="window.print()">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="7"/></svg>
				Print
			</button>
		</form>
	</div>

	<div class="cln-kpis">
		<div class="cln-kpi">
			<div class="cln-kpi__top"><span>Received</span><span class="cln-badge-ico blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></span></div>
			<strong><?= (int) ($clinic_received ?? 0); ?></strong>
			<small>Students seen today</small>
		</div>
		<div class="cln-kpi">
			<div class="cln-kpi__top"><span>Students</span><span class="cln-badge-ico green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10l-10-5L2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg></span></div>
			<strong><?= (int) ($clinic_students ?? 0); ?></strong>
			<small>Unique students</small>
		</div>
		<div class="cln-kpi">
			<div class="cln-kpi__top"><span>Treated here</span><span class="cln-badge-ico violet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-6 9 6v11a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1z"/></svg></span></div>
			<strong><?= (int) ($clinic_here ?? 0); ?></strong>
			<small>At school clinic</small>
		</div>
		<div class="cln-kpi">
			<div class="cln-kpi__top"><span>Hospital</span><span class="cln-badge-ico rose"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6M12 8v4M10 10h4"/></svg></span></div>
			<strong><?= (int) ($clinic_hospital ?? 0); ?></strong>
			<small>Taken to hospital</small>
		</div>
		<div class="cln-kpi">
			<div class="cln-kpi__top"><span>Sent home</span><span class="cln-badge-ico amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg></span></div>
			<strong><?= (int) ($clinic_home ?? 0); ?></strong>
			<small>Sent back home</small>
		</div>
		<div class="cln-kpi">
			<div class="cln-kpi__top"><span>School cost</span><span class="cln-badge-ico pink"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span></div>
			<strong><?= number_format((float) ($clinic_cost ?? 0), 0); ?> RWF</strong>
			<small>Total cost today</small>
		</div>
	</div>

	<?php if ($canRecord) : ?>
	<form class="cln-card cln-form" method="post" action="<?= base_url('clinic_save'); ?>" id="clinicForm">
		<div class="cln-head">
			<span class="cln-badge-ico blue" style="width:36px;height:36px;border-radius:999px;">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
			</span>
			<div>
				<h3>Add Clinic Visit</h3>
				<p class="cln-sub">Fill in the student information and visit details.</p>
			</div>
		</div>
		<input type="hidden" name="date" value="<?= esc($date, 'attr'); ?>">
		<input type="hidden" name="student_id" id="clinicStudentId" value="">
		<div class="cln-grid">
			<div class="cln-field">
				<label for="clinicFind">Student name or registration number <em>*</em></label>
				<div class="cln-input">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
					<input id="clinicFind" type="search" placeholder="Search student name or registration number..." autocomplete="off">
					<div class="cln-hits" id="clinicHits" hidden></div>
				</div>
				<div class="cln-picked" id="clinicPicked"></div>
			</div>
			<div class="cln-field">
				<label for="clinicDisease">Illness <em>*</em></label>
				<div class="cln-input">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.5 12.5l2-2a3 3 0 014.2 0l.3.3 2.2-2.2"/><circle cx="18" cy="7" r="2"/></svg>
					<input id="clinicDisease" name="disease" list="clinicDiseases" required placeholder="Enter type of disease or symptoms...">
					<datalist id="clinicDiseases">
						<option value="Malaria"></option>
						<option value="Fever"></option>
						<option value="Headache"></option>
						<option value="Stomach ache"></option>
						<option value="Cold and cough"></option>
						<option value="Diarrhoea"></option>
						<option value="Injury"></option>
						<option value="Toothache"></option>
						<option value="Eye problem"></option>
						<option value="Skin rash"></option>
						<option value="Asthma"></option>
					</datalist>
				</div>
			</div>
			<div class="cln-field">
				<label class="cln-split" for="clinicNote">Note (optional) <span class="cln-count" id="clinicNoteCount">0/200</span></label>
				<div class="cln-input">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9z"/><path d="M14 3v6h6"/></svg>
					<input id="clinicNote" name="note" maxlength="200" placeholder="Additional notes...">
				</div>
			</div>
		</div>
		<div class="cln-ask">What was done? <em>*</em></div>
		<div class="cln-outcomes">
			<label class="cln-outcome is-on">
				<input type="radio" name="outcome" value="here" checked>
				<span class="cln-outcome__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg></span>
				<span><strong>Treated here</strong><small>Student was treated at school clinic.</small></span>
			</label>
			<label class="cln-outcome">
				<input type="radio" name="outcome" value="hospital">
				<span class="cln-outcome__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M6 21V5h12v16"/><path d="M10 9h4M12 7v4"/></svg></span>
				<span><strong>Taken to hospital</strong><small>Student was taken to hospital.</small></span>
			</label>
			<label class="cln-outcome">
				<input type="radio" name="outcome" value="home">
				<span class="cln-outcome__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg></span>
				<span><strong>Sent home</strong><small>Student was sent home.</small></span>
			</label>
		</div>
		<div class="cln-extra" id="clinicHospital" hidden>
			<label for="clinicCost">School cost (RWF)</label>
			<input id="clinicCost" name="hospital_cost" type="number" min="0" step="1" placeholder="0">
		</div>
		<div class="cln-extra" id="clinicHome" hidden>
			<label for="clinicTransport">Way of transport</label>
			<select id="clinicTransport" name="transport">
				<option value="">Choose</option>
				<option>Parent pickup</option>
				<option>School car</option>
				<option>Ambulance</option>
				<option>Motorcycle</option>
				<option>Public bus</option>
				<option>Walked with staff</option>
			</select>
		</div>
		<div class="cln-actions">
			<button class="cln-btn primary" type="submit">Save visit</button>
			<button class="cln-btn ghost" type="button" id="clinicClear">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 101.5-5"/><path d="M3 4v5h5"/></svg>
				Clear
			</button>
		</div>
	</form>
	<?php endif; ?>

	<div class="cln-card cln-tablecard">
		<div class="cln-tablehead">
			<div class="cln-head" style="margin:0;">
				<span class="cln-badge-ico blue" style="width:36px;height:36px;">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3h8a2 2 0 012 2v16l-6-3-6 3V5a2 2 0 012-2z"/></svg>
				</span>
				<div>
					<h3>Clinic Visits</h3>
					<p class="cln-sub">Students seen on <?= esc($pretty); ?></p>
				</div>
			</div>
			<label class="cln-search">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
				<input id="clinicFilter" type="search" placeholder="Search student, illness...">
			</label>
		</div>
		<?php if (empty($rows)) : ?>
			<div class="cln-empty"><strong>No sick students on this day.</strong></div>
		<?php else : ?>
			<div class="cln-tablewrap">
			<table>
				<thead>
				<tr>
					<th>#</th>
					<th>Student</th>
					<th>Class</th>
					<th>Illness</th>
					<th>What was done</th>
					<th>Note</th>
					<th>Cost (RWF)</th>
					<th>Time</th>
					<?php if ($canRecord) : ?><th class="cln-act">Action</th><?php endif; ?>
				</tr>
				</thead>
				<tbody id="clinicRows">
				<?php foreach ($rows as $i => $row) :
					$outcome = (string) ($row['outcome'] ?? 'here');
					$note = trim((string) ($row['note'] ?? ''));
					$cost = $outcome === 'hospital' ? number_format((float) ($row['hospital_cost'] ?? 0), 0) : '0';
					$hay = strtolower(trim(
						(string) ($row['student_name'] ?? '') . ' ' .
						(string) ($row['regno'] ?? '') . ' ' .
						(string) ($row['class_name'] ?? '') . ' ' .
						(string) ($row['disease'] ?? '') . ' ' .
						$outcomeLabel($outcome) . ' ' .
						$note . ' ' .
						(string) ($row['transport'] ?? '')
					));
					?>
					<tr data-find="<?= esc($hay, 'attr'); ?>">
						<td><?= $i + 1; ?></td>
						<td>
							<span class="cln-name"><?= esc((string) ($row['student_name'] ?? '')); ?></span>
							<span class="cln-reg"><?= esc((string) ($row['regno'] ?? '')); ?></span>
						</td>
						<td><?= esc(trim((string) ($row['class_name'] ?? ''))); ?></td>
						<td><?= esc((string) ($row['disease'] ?? '')); ?></td>
						<td><span class="cln-mark <?= esc($outcome, 'attr'); ?>"><i></i><?= esc($outcomeLabel($outcome)); ?></span></td>
						<td><?= $note !== '' ? esc($note) : '—'; ?></td>
						<td><?= esc($cost); ?></td>
						<td><?= esc($visitTime($row['created_at'] ?? '')); ?></td>
						<?php if ($canRecord) : ?>
						<td class="cln-act">
							<form method="post" action="<?= base_url('clinic_remove'); ?>" onsubmit="return confirm('Remove this visit?');">
								<input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0); ?>">
								<input type="hidden" name="date" value="<?= esc($date, 'attr'); ?>">
								<button class="cln-remove" type="submit">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
									Remove
								</button>
							</form>
						</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				<tr id="clinicNoMatch" class="cln-none"><td colspan="<?= $canRecord ? 9 : 8; ?>" class="cln-empty">No visits match that search.</td></tr>
				</tbody>
			</table>
			</div>
		<?php endif; ?>
	</div>
</div>
<script>
(function () {
	var filter = document.getElementById('clinicFilter');
	if (filter) {
		filter.addEventListener('input', function () {
			var q = filter.value.trim().toLowerCase();
			var rows = document.querySelectorAll('#clinicRows tr[data-find]');
			var shown = 0;
			rows.forEach(function (row) {
				var ok = q === '' || (row.getAttribute('data-find') || '').indexOf(q) !== -1;
				row.style.display = ok ? '' : 'none';
				if (ok) shown++;
			});
			var none = document.getElementById('clinicNoMatch');
			if (none) none.className = shown === 0 ? '' : 'cln-none';
		});
	}
	var find = document.getElementById('clinicFind');
	var hits = document.getElementById('clinicHits');
	var picked = document.getElementById('clinicPicked');
	var studentId = document.getElementById('clinicStudentId');
	var form = document.getElementById('clinicForm');
	var note = document.getElementById('clinicNote');
	var noteCount = document.getElementById('clinicNoteCount');
	if (!form || !find) return;
	function paintOutcome() {
		var chosen = (form.querySelector('input[name="outcome"]:checked') || {}).value;
		form.querySelectorAll('.cln-outcome').forEach(function (card) {
			var input = card.querySelector('input');
			card.classList.toggle('is-on', input && input.value === chosen);
		});
		document.getElementById('clinicHospital').hidden = chosen !== 'hospital';
		document.getElementById('clinicHome').hidden = chosen !== 'home';
	}
	form.querySelectorAll('input[name="outcome"]').forEach(function (el) {
		el.addEventListener('change', paintOutcome);
	});
	paintOutcome();
	if (note && noteCount) {
		note.addEventListener('input', function () {
			noteCount.textContent = note.value.length + '/200';
		});
	}
	var timer = null;
	find.addEventListener('input', function () {
		studentId.value = '';
		picked.textContent = '';
		clearTimeout(timer);
		var q = find.value.trim();
		if (q.length < 2) { hits.hidden = true; hits.innerHTML = ''; return; }
		timer = setTimeout(function () {
			fetch('<?= base_url('clinic_student_search'); ?>?q=' + encodeURIComponent(q), {credentials: 'same-origin'})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					hits.innerHTML = '';
					(data.students || []).forEach(function (st) {
						var btn = document.createElement('button');
						btn.type = 'button';
						btn.className = 'cln-hit';
						btn.innerHTML = '<strong></strong><small></small>';
						btn.querySelector('strong').textContent = st.name;
						btn.querySelector('small').textContent = (st.regno || '') + (st.class ? ' · ' + st.class : '');
						btn.addEventListener('click', function () {
							studentId.value = st.id;
							picked.textContent = st.name + (st.class ? ' · ' + st.class : '');
							find.value = st.name;
							hits.hidden = true;
						});
						hits.appendChild(btn);
					});
					hits.hidden = hits.childNodes.length === 0;
				});
		}, 250);
	});
	form.addEventListener('submit', function (e) {
		if (!studentId.value) {
			e.preventDefault();
			alert('Choose a student from the list.');
		}
	});
	var clearBtn = document.getElementById('clinicClear');
	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			find.value = '';
			studentId.value = '';
			picked.textContent = '';
			hits.hidden = true;
			hits.innerHTML = '';
			document.getElementById('clinicDisease').value = '';
			if (note) { note.value = ''; if (noteCount) noteCount.textContent = '0/200'; }
			var here = form.querySelector('input[value="here"]');
			if (here) here.checked = true;
			var cost = document.getElementById('clinicCost');
			var transport = document.getElementById('clinicTransport');
			if (cost) cost.value = '';
			if (transport) transport.value = '';
			paintOutcome();
		});
	}
})();
</script>
