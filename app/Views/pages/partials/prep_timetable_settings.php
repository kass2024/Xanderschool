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
$selected = static function (array $duties, int $day, string $slot, int $staffId): string {
	$ids = $duties[$day][$slot] ?? [];
	return in_array($staffId, array_map('intval', $ids), true) ? 'selected' : '';
};
?>
<style>
	.prep-rota-table { width: 100%; border-collapse: collapse; background: #fff; }
	.prep-rota-table th, .prep-rota-table td { border: 1px solid #cbd5e1; vertical-align: top; padding: 8px; }
	.prep-rota-table th { background: #f8fafc; font-size: 13px; text-align: center; }
	.prep-rota-table td.prep-day { width: 72px; font-weight: 700; text-align: center; background: #f8fafc; }
	.prep-rota-table .select2-container { width: 100% !important; }
	.prep-times { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 14px; }
	.prep-times label { font-size: 12px; font-weight: 700; display: block; margin-bottom: 4px; }
	.prep-times .prep-time-pair { display: flex; gap: 8px; align-items: center; }
</style>

<p class="text-muted mb-3">Choose who invigilates each morning prep and evening prep. Only active staff whose post is <strong>Teacher</strong>, <strong>Patron</strong>, or <strong>Matron</strong> can be chosen. You can pick more than one person in a cell.<?php if ($staff !== []): ?> <strong><?= count($staff); ?> staff available.</strong><?php endif; ?></p>

<?php if ($staff === []): ?>
	<div class="alert alert-warning">No active staff with a Teacher, Patron, or Matron post. Set that post on the staff record, then come back here.</div>
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
						<td>
							<select class="prep-staff-select" multiple data-prep-day="<?= (int) $day; ?>" data-prep-slot="<?= esc($slot); ?>">
								<?php foreach ($staff as $person): ?>
									<option value="<?= (int) $person['id']; ?>" <?= $selected($duties, (int) $day, $slot, (int) $person['id']); ?>><?= esc($person['name'] . ' (' . $person['post'] . ')'); ?></option>
								<?php endforeach; ?>
							</select>
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
	var prepStaff = <?= json_encode(array_values($staff), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?> || [];
	function prepMatcher(params, data) {
		var term = jQuery.trim(params.term || '').toUpperCase();
		if (term === '') return data;
		var text = jQuery.trim(data.text || '').toUpperCase();
		return text.indexOf(term) > -1 ? data : null;
	}
	function fillSelect($el) {
		var chosen = {};
		($el.val() || []).forEach(function (id) { chosen[String(id)] = true; });
		$el.empty();
		$el.append(new Option('', '', false, false));
		prepStaff.forEach(function (person) {
			var id = String(person.id);
			var label = jQuery.trim((person.name || '') + (person.post ? ' (' + person.post + ')' : ''));
			var option = new Option(label, id, false, !!chosen[id]);
			$el.append(option);
		});
	}
	function initSelects() {
		if (!window.jQuery || !jQuery.fn.select2) return;
		jQuery('#prepInvigilation .prep-staff-select').each(function () {
			var $el = jQuery(this);
			if ($el.data('select2')) $el.select2('destroy');
			fillSelect($el);
			$el.select2({
				width: '100%',
				multiple: true,
				placeholder: 'Select teacher, patron, or matron',
				closeOnSelect: false,
				dropdownParent: jQuery(document.body),
				matcher: prepMatcher
			});
		});
	}
	jQuery(function () {
		function whenSelect2(tries) {
			if (window.jQuery && jQuery.fn.select2) {
				initSelects();
				return;
			}
			if (tries < 50) setTimeout(function () { whenSelect2(tries + 1); }, 40);
		}
		whenSelect2(0);
		jQuery('#prepTimetableForm').on('submit', function (e) {
			e.preventDefault();
			var assignments = {};
			jQuery('#prepTimetableForm .prep-staff-select').each(function () {
				var day = String(jQuery(this).attr('data-prep-day'));
				var slot = String(jQuery(this).attr('data-prep-slot'));
				if (!assignments[day]) assignments[day] = { morning: [], evening: [] };
				var values = jQuery(this).val() || [];
				assignments[day][slot] = values;
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
	});
})();
</script>
