<?php
/** @var array $required_materials */
/** @var array $classes */
/** @var int $academic_year_id */
/** @var array $years */

$materials = $required_materials ?? [];
$classes = $classes ?? [];
$yearId = (int) ($academic_year_id ?? 0);
$years = $years ?? [];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/student-materials.css'); ?>">

<div class="srm-settings" id="srmSettings">
	<p class="text-muted srm-intro">
		Define materials students must bring (e.g. reams of paper, notebooks). Then assign required quantities to one or more classes for the academic year.
	</p>

	<div class="row">
		<div class="col-lg-5">
			<div class="srm-card">
				<h6><i class="fa fa-list"></i> Material catalog</h6>
				<form id="srmAddMaterialForm" class="srm-add-form">
					<div class="form-row">
						<div class="col-7">
							<input type="text" class="form-control form-control-sm" id="srmMatName" placeholder="Material name (e.g. Ream of paper)" required maxlength="200">
						</div>
						<div class="col-3">
							<input type="text" class="form-control form-control-sm" id="srmMatUnit" placeholder="Unit" value="pcs" maxlength="60">
						</div>
						<div class="col-2">
							<button type="submit" class="btn btn-success btn-sm btn-block"><i class="fa fa-plus"></i></button>
						</div>
					</div>
				</form>
				<div class="srm-mat-list" id="srmMatList">
					<?php if (empty($materials)) : ?>
						<p class="text-muted srm-empty">No materials yet. Add your first item above.</p>
					<?php else : ?>
						<?php foreach ($materials as $m) : ?>
							<div class="srm-mat-item" data-id="<?= (int) $m['id']; ?>">
								<div class="srm-mat-info">
									<strong><?= esc($m['name']); ?></strong>
									<span class="text-muted">(<?= esc($m['unit']); ?>)</span>
								</div>
								<button type="button" class="btn btn-link btn-sm text-danger srm-del-mat" data-id="<?= (int) $m['id']; ?>" title="Remove">
									<i class="fa fa-trash"></i>
								</button>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="col-lg-7">
			<div class="srm-card">
				<h6><i class="fa fa-users"></i> Assign quantities per class</h6>
				<div class="form-row align-items-end srm-class-pick">
					<div class="col-md-5">
						<label class="small font-weight-bold">Classes <span class="text-muted font-weight-normal">(multi-select)</span></label>
						<select class="form-control form-control-sm select2" id="srmClassSelect" multiple="multiple" data-placeholder="Choose one or more classes…">
							<?php foreach ($classes as $cl) : ?>
								<option value="<?= (int) $cl['id']; ?>">
									<?= esc(trim(($cl['level_name'] ?? '') . ' ' . ($cl['dept_code'] ?? '') . ' ' . ($cl['title'] ?? ''))); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label class="small font-weight-bold">Academic year</label>
						<select class="form-control form-control-sm" id="srmYearSelect">
							<?php foreach ($years as $yr) : ?>
								<option value="<?= (int) $yr['id']; ?>" <?= (int) $yr['id'] === $yearId ? 'selected' : ''; ?>>
									<?= esc($yr['title']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3">
						<button type="button" class="btn btn-primary btn-sm btn-block" id="srmLoadClass" disabled>
							<i class="fa fa-sync"></i> Load
						</button>
					</div>
				</div>

				<div id="srmAssignWrap" style="display:none;">
					<p class="text-muted small mt-2 mb-1" id="srmAssignHint">
						Enter required quantity for each material. Leave <strong>0 or blank</strong> to skip.
					</p>
					<p class="small mb-2" id="srmSelectedClassesLabel"></p>
					<div class="table-responsive">
						<table class="table table-sm table-bordered mb-2" id="srmAssignTable">
							<thead>
							<tr>
								<th>Material</th>
								<th style="width:120px">Unit</th>
								<th style="width:120px">Qty required</th>
							</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
					<button type="button" class="btn btn-success btn-sm" id="srmSaveAssign">
						<i class="fa fa-save"></i> Save assignment to selected classes
					</button>
				</div>
				<div id="srmAssignEmpty" class="srm-empty text-muted mt-3">
					Select one or more classes and click Load to assign the same materials to all of them.
				</div>
			</div>
		</div>
	</div>
</div>

<script>
$(function () {
	const materials = <?= json_encode(array_map(static function ($m) {
		return ['id' => (int) $m['id'], 'name' => $m['name'], 'unit' => $m['unit']];
	}, $materials)); ?>;

	const $classSelect = $('#srmClassSelect');
	if ($classSelect.data('select2')) {
		$classSelect.select2('destroy');
	}
	$classSelect.select2({
		width: '100%',
		placeholder: 'Choose one or more classes…',
		allowClear: true,
		closeOnSelect: false
	});

	function srmSelectedClassIds() {
		const vals = $classSelect.val();
		if (!vals) return [];
		return (Array.isArray(vals) ? vals : [vals]).map(function (v) { return parseInt(v, 10); }).filter(function (v) { return v > 0; });
	}

	function srmSelectedClassLabels() {
		const labels = [];
		$classSelect.find('option:selected').each(function () {
			labels.push($.trim($(this).text()));
		});
		return labels;
	}

	function srmRenderMatList(list) {
		const $el = $('#srmMatList');
		if (!list.length) {
			$el.html('<p class="text-muted srm-empty">No materials yet. Add your first item above.</p>');
			return;
		}
		let html = '';
		list.forEach(function (m) {
			html += '<div class="srm-mat-item" data-id="' + m.id + '">' +
				'<div class="srm-mat-info"><strong>' + $('<span>').text(m.name).html() + '</strong> ' +
				'<span class="text-muted">(' + $('<span>').text(m.unit).html() + ')</span></div>' +
				'<button type="button" class="btn btn-link btn-sm text-danger srm-del-mat" data-id="' + m.id + '"><i class="fa fa-trash"></i></button></div>';
		});
		$el.html(html);
	}

	function srmBuildAssignRows(assignments) {
		const byId = {};
		(assignments || []).forEach(function (a) { byId[a.material_id] = a.quantity; });
		const $tb = $('#srmAssignTable tbody').empty();
		if (!materials.length) {
			$tb.html('<tr><td colspan="3" class="text-muted">Add materials in the catalog first.</td></tr>');
			return;
		}
		materials.forEach(function (m) {
			const qty = byId[m.id] != null ? byId[m.id] : '';
			$tb.append(
				'<tr data-material-id="' + m.id + '">' +
				'<td>' + $('<span>').text(m.name).html() + '</td>' +
				'<td>' + $('<span>').text(m.unit).html() + '</td>' +
				'<td><input type="number" min="0" step="0.01" class="form-control form-control-sm srm-qty" value="' + qty + '"></td></tr>'
			);
		});
	}

	function srmUpdateLoadBtn() {
		$('#srmLoadClass').prop('disabled', srmSelectedClassIds().length === 0);
	}

	$classSelect.on('change', srmUpdateLoadBtn);
	srmUpdateLoadBtn();

	$('#srmLoadClass').on('click', function () {
		const classIds = srmSelectedClassIds();
		const yearId = $('#srmYearSelect').val();
		if (!classIds.length) return;
		$.getJSON('<?= base_url('get_class_required_materials'); ?>', {
			class_ids: JSON.stringify(classIds),
			year: yearId
		}, function (res) {
			if (!res.success) {
				toastada.error(res.error || 'Could not load assignments.');
				return;
			}
			srmBuildAssignRows(res.assignments);
			const labels = srmSelectedClassLabels();
			const n = labels.length;
			$('#srmSelectedClassesLabel').html(
				'<strong>Applying to ' + n + ' class' + (n === 1 ? '' : 'es') + ':</strong> ' +
				$('<span>').text(labels.join(', ')).html() +
				(n > 1 ? ' <span class="text-muted">(quantities loaded from the first selected class as a template)</span>' : '')
			);
			$('#srmAssignWrap').show();
			$('#srmAssignEmpty').hide();
		});
	});

	$('#srmSaveAssign').on('click', function () {
		const classIds = srmSelectedClassIds();
		const yearId = $('#srmYearSelect').val();
		if (!classIds.length) {
			toastada.error('Select at least one class.');
			return;
		}
		const rows = [];
		$('#srmAssignTable tbody tr').each(function () {
			const mid = $(this).data('material-id');
			const qty = parseFloat($(this).find('.srm-qty').val()) || 0;
			if (mid) rows.push({ material_id: mid, quantity: qty });
		});
		$.post('<?= base_url('save_class_required_materials'); ?>', {
			class_ids: JSON.stringify(classIds),
			year: yearId,
			items: JSON.stringify(rows)
		}, function (res) {
			if (res.success) toastada.success(res.success);
			else toastada.error(res.error || 'Save failed.');
		}, 'json');
	});

	$('#srmAddMaterialForm').on('submit', function (e) {
		e.preventDefault();
		const name = $('#srmMatName').val().trim();
		const unit = $('#srmMatUnit').val().trim() || 'pcs';
		if (!name) return;
		$.post('<?= base_url('manipulate_required_material'); ?>', { action: 'add', name: name, unit: unit }, function (res) {
			if (res.success) {
				materials.push(res.material);
				srmRenderMatList(materials);
				$('#srmMatName').val('');
				toastada.success(res.success);
			} else toastada.error(res.error || 'Failed.');
		}, 'json');
	});

	$(document).on('click', '.srm-del-mat', function () {
		const id = $(this).data('id');
		if (!confirm('Remove this material from the catalog?')) return;
		$.post('<?= base_url('manipulate_required_material'); ?>', { action: 'delete', id: id }, function (res) {
			if (res.success) {
				const idx = materials.findIndex(function (m) { return m.id === id; });
				if (idx >= 0) materials.splice(idx, 1);
				srmRenderMatList(materials);
				toastada.success(res.success);
			} else toastada.error(res.error || 'Failed.');
		}, 'json');
	});
});
</script>
