<?php
$date = (string) ($clinic_date ?? date('Y-m-d'));
$error = session()->getFlashdata('clinic_error');
$outcomeLabel = static function (string $outcome): string {
	if ($outcome === 'hospital') {
		return 'Taken to hospital';
	}
	if ($outcome === 'home') {
		return 'Sent home';
	}
	return 'Treated here';
};
?>
<style>
	.cln-page { max-width: 1100px; margin: 0 auto; padding: 8px 4px 32px; }
	.cln-hero { text-align: center; margin-bottom: 16px; }
	.cln-hero h2 { margin: 0 0 4px; font-size: 1.55rem; font-weight: 800; color: #0f172a; }
	.cln-hero p { margin: 0; color: #64748b; }
	.cln-panel { background: #fff; border: 1px solid #e8edf3; border-radius: 16px; box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 14px 16px; margin-bottom: 14px; }
	.cln-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
	.cln-kpi { background: #fff; border: 1px solid #e8edf3; border-radius: 14px; padding: 12px 14px; }
	.cln-kpi span { display: block; color: #64748b; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
	.cln-kpi strong { display: block; margin-top: 4px; font-size: 1.4rem; color: #0f172a; }
	.cln-kpi.is-cost strong { color: #c2410c; }
	.cln-grid { display: grid; grid-template-columns: 1.3fr .9fr .9fr; gap: 12px; }
	.cln-grid label { display: block; font-size: .75rem; font-weight: 700; color: #64748b; margin-bottom: 4px; text-transform: uppercase; }
	.cln-hits { margin-top: 6px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
	.cln-hit { display: block; width: 100%; text-align: left; background: #fff; border: 0; border-bottom: 1px solid #f1f5f9; padding: 8px 10px; cursor: pointer; }
	.cln-hit strong { display: block; color: #0f172a; }
	.cln-hit small { color: #64748b; }
	.cln-picked { margin-top: 8px; font-weight: 700; color: #0f766e; }
	.cln-ways { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
	.cln-ways label { text-transform: none; font-size: .9rem; color: #0f172a; font-weight: 600; margin: 0; }
	.cln-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
	.cln-chip { background: #f0fdfa; color: #0f766e; border-radius: 999px; padding: 6px 12px; font-weight: 700; font-size: .82rem; }
	.cln-card { background: #fff; border: 1px solid #e8edf3; border-radius: 16px; overflow: hidden; }
	.cln-card table { width: 100%; margin: 0; }
	.cln-card th { background: #f8fafc; font-size: .75rem; text-transform: uppercase; color: #64748b; }
	.cln-mark { display: inline-block; border-radius: 999px; padding: 3px 10px; font-weight: 800; font-size: .82rem; }
	.cln-mark.here { background: #dcfce7; color: #166534; }
	.cln-mark.hospital { background: #ffedd5; color: #c2410c; }
	.cln-mark.home { background: #e0f2fe; color: #075985; }
	.cln-empty { padding: 32px 16px; text-align: center; color: #64748b; }
	.cln-error { background: #fef2f2; color: #b91c1c; border-radius: 10px; padding: 8px 12px; margin-bottom: 10px; font-weight: 700; }
	@media (max-width: 900px) { .cln-kpis, .cln-grid { grid-template-columns: 1fr; } }
	@media print { .cln-form, .app-sidebar-wrapper, .app-header, .hamburger { display: none !important; } }
</style>
<div class="cln-page">
	<div class="cln-hero">
		<h2>Clinic</h2>
		<p><?= esc(date('l j M Y', strtotime($date))); ?> · Sick students seen by the school nurse.</p>
	</div>

	<form class="cln-panel" method="get" action="<?= base_url('clinic'); ?>">
		<label for="clinicDate">Date</label>
		<input id="clinicDate" type="date" class="form-control" name="date" value="<?= esc($date, 'attr'); ?>" style="max-width:220px;display:inline-block;">
		<button class="btn btn-success" type="submit">Show</button>
		<button class="btn btn-light" type="button" onclick="window.print()">Print</button>
	</form>

	<div class="cln-kpis">
		<div class="cln-kpi"><span>Received</span><strong><?= (int) ($clinic_received ?? 0); ?></strong></div>
		<div class="cln-kpi"><span>Students</span><strong><?= (int) ($clinic_students ?? 0); ?></strong></div>
		<div class="cln-kpi"><span>Treated here</span><strong><?= (int) ($clinic_here ?? 0); ?></strong></div>
		<div class="cln-kpi"><span>Hospital</span><strong><?= (int) ($clinic_hospital ?? 0); ?></strong></div>
		<div class="cln-kpi"><span>Sent home</span><strong><?= (int) ($clinic_home ?? 0); ?></strong></div>
		<div class="cln-kpi is-cost"><span>School cost</span><strong><?= number_format((float) ($clinic_cost ?? 0), 0); ?></strong></div>
	</div>

	<?php if (!empty($clinic_diseases) || !empty($clinic_transports)) : ?>
		<div class="cln-chips">
			<?php foreach (($clinic_diseases ?? []) as $name => $count) : ?>
				<span class="cln-chip"><?= esc((string) $name); ?> · <?= (int) $count; ?></span>
			<?php endforeach; ?>
			<?php foreach (($clinic_transports ?? []) as $name => $count) : ?>
				<span class="cln-chip"><?= esc((string) $name); ?> · <?= (int) $count; ?></span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<form class="cln-panel cln-form" method="post" action="<?= base_url('clinic_save'); ?>" id="clinicForm">
		<?php if ($error) : ?><div class="cln-error"><?= esc($error); ?></div><?php endif; ?>
		<input type="hidden" name="date" value="<?= esc($date, 'attr'); ?>">
		<input type="hidden" name="student_id" id="clinicStudentId" value="">
		<div class="cln-grid">
			<div>
				<label for="clinicFind">Student</label>
				<input id="clinicFind" class="form-control" type="search" placeholder="Name or registration number" autocomplete="off">
				<div class="cln-hits" id="clinicHits" hidden></div>
				<div class="cln-picked" id="clinicPicked"></div>
			</div>
			<div>
				<label for="clinicDisease">Illness</label>
				<input id="clinicDisease" class="form-control" name="disease" list="clinicDiseases" required placeholder="Type of disease">
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
			<div>
				<label for="clinicNote">Note</label>
				<input id="clinicNote" class="form-control" name="note" maxlength="240" placeholder="Optional">
			</div>
		</div>
		<div class="cln-ways" style="margin-top:12px;">
			<label><input type="radio" name="outcome" value="here" checked> Treated here</label>
			<label><input type="radio" name="outcome" value="hospital"> Taken to hospital</label>
			<label><input type="radio" name="outcome" value="home"> Sent home</label>
		</div>
		<div id="clinicHospital" hidden style="margin-top:10px;max-width:280px;">
			<label for="clinicCost">School cost (RWF)</label>
			<input id="clinicCost" class="form-control" name="hospital_cost" type="number" min="0" step="1" placeholder="0">
		</div>
		<div id="clinicHome" hidden style="margin-top:10px;max-width:280px;">
			<label for="clinicTransport">Way of transport</label>
			<select id="clinicTransport" class="form-control" name="transport">
				<option value="">Choose</option>
				<option>Parent pickup</option>
				<option>School car</option>
				<option>Ambulance</option>
				<option>Motorcycle</option>
				<option>Public bus</option>
				<option>Walked with staff</option>
			</select>
		</div>
		<button class="btn btn-success" type="submit" style="margin-top:12px;">Save visit</button>
	</form>

	<div class="cln-card">
		<?php if (empty($rows)) : ?>
			<div class="cln-empty"><strong>No sick students on this day.</strong></div>
		<?php else : ?>
			<table class="table table-hover mb-0">
				<thead>
				<tr>
					<th>#</th>
					<th>Student</th>
					<th>Class</th>
					<th>Illness</th>
					<th>What was done</th>
					<th></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($rows as $i => $row) :
					$outcome = (string) ($row['outcome'] ?? 'here');
					$detail = $outcomeLabel($outcome);
					if ($outcome === 'hospital') {
						$detail .= ' · ' . number_format((float) ($row['hospital_cost'] ?? 0), 0) . ' RWF';
					} elseif ($outcome === 'home' && !empty($row['transport'])) {
						$detail .= ' · ' . (string) $row['transport'];
					}
					?>
					<tr>
						<td><?= $i + 1; ?></td>
						<td>
							<strong><?= esc((string) ($row['student_name'] ?? '')); ?></strong><br>
							<small><?= esc((string) ($row['regno'] ?? '')); ?></small>
						</td>
						<td><?= esc(trim((string) ($row['class_name'] ?? ''))); ?></td>
						<td><?= esc((string) ($row['disease'] ?? '')); ?><?php if (!empty($row['note'])) : ?><br><small><?= esc((string) $row['note']); ?></small><?php endif; ?></td>
						<td><span class="cln-mark <?= esc($outcome, 'attr'); ?>"><?= esc($detail); ?></span></td>
						<td>
							<form method="post" action="<?= base_url('clinic_remove'); ?>" onsubmit="return confirm('Remove this visit?');">
								<input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0); ?>">
								<input type="hidden" name="date" value="<?= esc($date, 'attr'); ?>">
								<button class="btn btn-sm btn-light" type="submit">Remove</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
<script>
(function () {
	var find = document.getElementById('clinicFind');
	var hits = document.getElementById('clinicHits');
	var picked = document.getElementById('clinicPicked');
	var studentId = document.getElementById('clinicStudentId');
	var form = document.getElementById('clinicForm');
	var timer = null;
	function showExtra() {
		var outcome = (form.querySelector('input[name="outcome"]:checked') || {}).value;
		document.getElementById('clinicHospital').hidden = outcome !== 'hospital';
		document.getElementById('clinicHome').hidden = outcome !== 'home';
	}
	form.querySelectorAll('input[name="outcome"]').forEach(function (el) {
		el.addEventListener('change', showExtra);
	});
	showExtra();
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
})();
</script>
