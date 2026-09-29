<?php
/** @var array<string,mixed> $prep_timetable */
/** @var list<array{id:int,name:string,post:string}> $prep_staff */
$rota = $prep_timetable ?? [];
$staff = $prep_staff ?? [];
$days = \App\Models\PrepTimetableModel::days();
$duties = $rota['duties'] ?? [];
$morningStart = (string) ($rota['morning_start'] ?? '05:30');
$morningEnd = (string) ($rota['morning_end'] ?? '06:30');
$eveningStart = (string) ($rota['evening_start'] ?? '19:00');
$eveningEnd = (string) ($rota['evening_end'] ?? '21:00');
$isChosen = static function (array $duties, int $day, string $slot, int $staffId): bool {
	$ids = $duties[$day][$slot] ?? [];
	return in_array($staffId, array_map('intval', $ids), true);
};
?>
<style>
	.prep-rota-table { width: 100%; border-collapse: collapse; background: #fff; }
	.prep-rota-table th, .prep-rota-table td { border: 1px solid #cbd5e1; vertical-align: top; padding: 8px; }
	.prep-rota-table th { background: #f8fafc; font-size: 13px; text-align: center; }
	.prep-rota-table td.prep-day { width: 72px; font-weight: 700; text-align: center; background: #f8fafc; }
	.prep-times { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 14px; }
	.prep-times label { font-size: 12px; font-weight: 700; display: block; margin-bottom: 4px; }
	.prep-times .prep-time-pair { display: flex; gap: 8px; align-items: center; }
	.prep-picker { background: #fff; }
	.prep-picker > summary {
		list-style: none;
		cursor: pointer;
		border: 1px solid #94a3b8;
		border-radius: 4px;
		background: #fff;
		min-height: 36px;
		padding: 7px 28px 7px 8px;
		font-size: 13px;
		line-height: 1.35;
		position: relative;
	}
	.prep-picker > summary::-webkit-details-marker { display: none; }
	.prep-picker > summary::after {
		content: "";
		position: absolute;
		right: 10px;
		top: 14px;
		border: 5px solid transparent;
		border-top-color: #334155;
	}
	.prep-picker[open] > summary::after { top: 8px; border-top-color: transparent; border-bottom-color: #334155; }
	.prep-picker-summary.is-empty { color: #64748b; }
	.prep-filter {
		display: block;
		width: 100%;
		margin-top: 6px;
		border: 1px solid #cbd5e1;
		border-radius: 4px;
		padding: 6px 8px;
		font-size: 13px;
	}
	.prep-picker-list {
		max-height: 240px;
		overflow: auto;
		margin-top: 4px;
		border: 1px solid #cbd5e1;
		border-radius: 4px;
		background: #fff;
	}
	.prep-picker-list label {
		display: flex;
		align-items: center;
		gap: 8px;
		margin: 0;
		padding: 6px 8px;
		font-size: 13px;
		font-weight: 500;
		cursor: pointer;
	}
	.prep-picker-list label:hover { background: #f1f5f9; }
	.prep-none { display: none; padding: 8px; color: #64748b; font-size: 13px; }
</style>

<p class="text-muted mb-3">Choose who invigilates each morning prep and evening prep. All active staff can be chosen except <strong>Cooker</strong>, <strong>Cleaner</strong>, and <strong>Security</strong>. Click a cell, tick the names, and you can pick more than one person. On the day they are on duty they must tap their staff card at the Morning prep or Evening prep reader.<?php if ($staff !== []): ?> <strong><?= count($staff); ?> staff available.</strong><?php endif; ?></p>

<?php if ($staff === []): ?>
	<div class="alert alert-warning">No active staff available for prep invigilation.</div>
<?php endif; ?>

<form id="prepTimetableForm">
	<div class="prep-times">
		<div>
			<label>Morning prep</label>
			<div class="prep-time-pair">
				<input type="time" class="form-control form-control-sm" name="morning_start" id="prepMorningStart" value="<?= esc($morningStart); ?>" required>
				<span>to</span>
				<input type="time" class="form-control form-control-sm" name="morning_end" id="prepMorningEnd" value="<?= esc($morningEnd); ?>" required>
			</div>
		</div>
		<div>
			<label>Evening prep</label>
			<div class="prep-time-pair">
				<input type="time" class="form-control form-control-sm" name="evening_start" id="prepEveningStart" value="<?= esc($eveningStart); ?>" required>
				<span>to</span>
				<input type="time" class="form-control form-control-sm" name="evening_end" id="prepEveningEnd" value="<?= esc($eveningEnd); ?>" required>
			</div>
		</div>
	</div>

	<div class="table-responsive">
		<table class="prep-rota-table">
			<thead>
			<tr>
				<th>Day</th>
				<th id="prepMorningHead">Morning (<?= esc($morningStart); ?>–<?= esc($morningEnd); ?>)</th>
				<th id="prepEveningHead">Evening (<?= esc($eveningStart); ?>–<?= esc($eveningEnd); ?>)</th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ($days as $day => $label): ?>
				<tr>
					<td class="prep-day"><?= esc($label); ?></td>
					<?php foreach (['morning', 'evening'] as $slot): ?>
						<?php
						$chosenNames = [];
						foreach ($staff as $person) {
							if ($isChosen($duties, (int) $day, $slot, (int) $person['id'])) {
								$chosenNames[] = (string) $person['name'];
							}
						}
						$summary = $chosenNames === [] ? 'Click to choose staff' : implode(', ', $chosenNames);
						?>
						<td>
							<details class="prep-picker" data-prep-day="<?= (int) $day; ?>" data-prep-slot="<?= esc($slot); ?>">
								<summary class="prep-picker-summary<?= $chosenNames === [] ? ' is-empty' : ''; ?>"><?= esc($summary); ?></summary>
								<input type="search" class="prep-filter" placeholder="Type a name" autocomplete="off">
								<div class="prep-picker-list">
									<?php foreach ($staff as $person): ?>
										<?php $labelText = trim((string) $person['name'] . ' (' . (string) $person['post'] . ')'); ?>
										<label>
											<input type="checkbox" value="<?= (int) $person['id']; ?>" data-name="<?= esc((string) $person['name']); ?>" <?= $isChosen($duties, (int) $day, $slot, (int) $person['id']) ? 'checked' : ''; ?>>
											<span><?= esc($labelText); ?></span>
										</label>
									<?php endforeach; ?>
									<div class="prep-none">No matching staff</div>
								</div>
							</details>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<button type="submit" class="btn btn-primary mt-3" id="prepSaveBtn">Save preps invigilation</button>
</form>

<script>
(function () {
	function clockLabel(value) {
		if (!value || value.length < 4) return '';
		var parts = value.split(':');
		var h = parseInt(parts[0], 10);
		var m = parts[1] || '00';
		var ap = h >= 12 ? 'PM' : 'AM';
		var h12 = h % 12;
		if (h12 === 0) h12 = 12;
		return h12 + ':' + m + ' ' + ap;
	}
	function paintHeads() {
		var ms = document.getElementById('prepMorningStart').value;
		var me = document.getElementById('prepMorningEnd').value;
		var es = document.getElementById('prepEveningStart').value;
		var ee = document.getElementById('prepEveningEnd').value;
		document.getElementById('prepMorningHead').textContent = 'Morning (' + clockLabel(ms) + '–' + clockLabel(me) + ')';
		document.getElementById('prepEveningHead').textContent = 'Evening (' + clockLabel(es) + '–' + clockLabel(ee) + ')';
	}
	['prepMorningStart', 'prepMorningEnd', 'prepEveningStart', 'prepEveningEnd'].forEach(function (id) {
		var el = document.getElementById(id);
		if (el) el.addEventListener('change', paintHeads);
	});

	function paintSummary(picker) {
		var names = [];
		picker.querySelectorAll('input[type="checkbox"]:checked').forEach(function (box) {
			names.push(box.getAttribute('data-name') || '');
		});
		var summary = picker.querySelector('summary');
		summary.textContent = names.length ? names.join(', ') : 'Click to choose staff';
		summary.classList.toggle('is-empty', names.length === 0);
	}

	document.querySelectorAll('#prepTimetableForm .prep-picker').forEach(function (picker) {
		var filter = picker.querySelector('.prep-filter');
		var empty = picker.querySelector('.prep-none');
		if (filter) {
			filter.addEventListener('input', function () {
				var term = filter.value.replace(/^\s+|\s+$/g, '').toLowerCase();
				var shown = 0;
				picker.querySelectorAll('.prep-picker-list label').forEach(function (row) {
					var text = (row.textContent || '').toLowerCase();
					var match = term === '' || text.indexOf(term) !== -1;
					row.style.display = match ? '' : 'none';
					if (match) shown += 1;
				});
				if (empty) empty.style.display = shown === 0 ? 'block' : 'none';
			});
			filter.addEventListener('click', function (event) { event.stopPropagation(); });
		}
		picker.addEventListener('change', function () { paintSummary(picker); });
	});

	var form = document.getElementById('prepTimetableForm');
	if (!form || !window.jQuery) return;
	jQuery(form).on('submit', function (e) {
		e.preventDefault();
		var assignments = {};
		document.querySelectorAll('#prepTimetableForm .prep-picker').forEach(function (picker) {
			var day = String(picker.getAttribute('data-prep-day'));
			var slot = String(picker.getAttribute('data-prep-slot'));
			if (!assignments[day]) assignments[day] = { morning: [], evening: [] };
			var ids = [];
			picker.querySelectorAll('input[type="checkbox"]:checked').forEach(function (box) {
				ids.push(box.value);
			});
			assignments[day][slot] = ids;
		});
		var $btn = jQuery('#prepSaveBtn').prop('disabled', true);
		jQuery.post("<?= base_url('save_prep_timetable'); ?>", {
			morning_start: jQuery('#prepMorningStart').val(),
			morning_end: jQuery('#prepMorningEnd').val(),
			evening_start: jQuery('#prepEveningStart').val(),
			evening_end: jQuery('#prepEveningEnd').val(),
			assignments: JSON.stringify(assignments)
		}).done(function (res) {
			var msg = (res && (res.success || res.message)) || 'Saved';
			if (window.toastada && res && res.success) toastada.success(res.success);
			else if (window.toastada && res && res.error) toastada.error(res.error);
			else alert(msg);
		}).fail(function () {
			if (window.toastada) toastada.error('Could not save the preps timetable');
			else alert('Could not save the preps timetable');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});
})();
</script>
