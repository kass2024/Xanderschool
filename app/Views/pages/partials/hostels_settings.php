<?php
/** @var array $hostels */
/** @var list<array{id:int,name:string,post_title:string}> $hostel_charge_staff */
$hostels = $hostels ?? [];
$hostelChargeStaff = $hostel_charge_staff ?? [];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/hostels.css'); ?>?v=5">

<div class="hst-settings" id="hstSettings">
	<p class="text-muted hst-intro">
		Create dormitories with a name, maximum beds, and gender (Male or Female). Students are assigned to these dormitories from the Dormitories page.
	</p>

	<div class="hst-card">
		<div class="hst-catalog-head">
			<h6 class="mb-0"><i class="fa fa-bed"></i> Dormitory catalog</h6>
			<a href="<?= base_url('export_hostel_assignment_excel'); ?>" class="btn btn-success btn-sm" id="hstExportExcel">
				<i class="fa fa-file-excel"></i> Export to Excel
			</a>
		</div>
		<p class="text-muted small mb-3">
			Create dormitories by gender, for example <strong>Girls Dormitory</strong> or <strong>Boys Dormitory</strong>.
			The person in charge is a matron, patron, customer care officer, or another school leader. Teachers are not on that list.
			Export makes one sheet per class and gender (P1 Girls, P1 Boys) with student name, class, and a hostel dropdown.
		</p>
		<form id="hstAddForm" class="hst-add-form">
			<div class="form-row align-items-end">
				<div class="col-md-3">
					<label class="small font-weight-bold">Hostel name</label>
					<input type="text" class="form-control form-control-sm" id="hstName" placeholder="e.g. Girls Dormitory" required maxlength="160">
				</div>
				<div class="col-md-2">
					<label class="small font-weight-bold">Max beds</label>
					<input type="number" class="form-control form-control-sm" id="hstBeds" min="1" max="9999" value="40" required>
				</div>
				<div class="col-md-2">
					<label class="small font-weight-bold">Gender</label>
					<select class="form-control form-control-sm" id="hstGender" required>
						<option value="M">Male</option>
						<option value="F">Female</option>
					</select>
				</div>
				<div class="col-md-3">
					<label class="small font-weight-bold">Person in charge</label>
					<select class="form-control form-control-sm" id="hstCharge">
						<option value="">— None —</option>
						<?php foreach ($hostelChargeStaff as $staff) : ?>
							<option value="<?= (int) $staff['id']; ?>"><?= esc($staff['name']); ?><?= $staff['post_title'] !== '' ? ' — ' . esc($staff['post_title']) : ''; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2">
					<button type="submit" class="btn btn-success btn-sm btn-block">
						<i class="fa fa-plus"></i> Add dormitory
					</button>
				</div>
			</div>
		</form>

		<div class="table-responsive">
			<table class="table table-sm table-bordered mb-0" id="hstTable">
				<thead>
				<tr>
					<th>Name</th>
					<th style="width:110px">Max beds</th>
					<th style="width:110px">Gender</th>
					<th>Person in charge</th>
					<th style="width:150px">Actions</th>
				</tr>
				</thead>
				<tbody id="hstTbody">
				<?php if (empty($hostels)) : ?>
					<tr class="hst-empty-row"><td colspan="5" class="text-muted text-center">No hostels yet. Add the first one above.</td></tr>
				<?php else : ?>
					<?php foreach ($hostels as $h) : ?>
						<tr
							data-id="<?= (int) $h['id']; ?>"
							data-name="<?= esc($h['name']); ?>"
							data-max-beds="<?= (int) $h['max_beds']; ?>"
							data-gender="<?= esc($h['gender']); ?>"
							data-charge-id="<?= (int) ($h['in_charge_staff_id'] ?? 0); ?>"
							data-charge-name="<?= esc((string) ($h['in_charge_name'] ?? '')); ?>"
						>
							<td><strong><?= esc($h['name']); ?></strong></td>
							<td><?= (int) $h['max_beds']; ?></td>
							<td>
								<span class="hst-gender-badge hst-gender-<?= strtoupper((string) $h['gender']) === 'F' ? 'f' : 'm'; ?>">
									<?= strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male'; ?>
								</span>
							</td>
							<td><?= esc((string) ($h['in_charge_name'] ?? '')) !== '' ? esc((string) $h['in_charge_name']) : '—'; ?></td>
							<td class="text-center">
								<button type="button" class="btn btn-outline-primary btn-sm hst-edit" data-id="<?= (int) $h['id']; ?>" title="Edit">
									<i class="fa fa-edit"></i> Edit
								</button>
								<button type="button" class="btn btn-outline-danger btn-sm hst-del" data-id="<?= (int) $h['id']; ?>" title="Remove">
									<i class="fa fa-trash"></i>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script>
(function ($) {
	var chargeStaff = <?= json_encode(array_values($hostelChargeStaff), JSON_UNESCAPED_UNICODE); ?>;

	function toast(msg, ok) {
		if (typeof toastr !== 'undefined') {
			ok ? toastr.success(msg) : toastr.error(msg);
			return;
		}
		alert(msg);
	}

	function genderBadge(g) {
		var isF = String(g).toUpperCase() === 'F';
		return '<span class="hst-gender-badge hst-gender-' + (isF ? 'f' : 'm') + '">' + (isF ? 'Female' : 'Male') + '</span>';
	}

	function escHtml(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	function chargeName(id) {
		id = parseInt(id, 10) || 0;
		for (var i = 0; i < chargeStaff.length; i++) {
			if (parseInt(chargeStaff[i].id, 10) === id) {
				return chargeStaff[i].name || '';
			}
		}
		return '';
	}

	function chargeSelect(selected) {
		selected = parseInt(selected, 10) || 0;
		var html = '<select class="form-control form-control-sm hst-edit-charge"><option value="">— None —</option>';
		chargeStaff.forEach(function (staff) {
			var id = parseInt(staff.id, 10) || 0;
			var label = staff.name || '';
			if (staff.post_title) {
				label += ' — ' + staff.post_title;
			}
			html += '<option value="' + id + '"' + (id === selected ? ' selected' : '') + '>' + escHtml(label) + '</option>';
		});
		return html + '</select>';
	}

	function syncHostelNameSuggestion() {
		var $name = $('#hstName');
		if ($.trim($name.val()).length) {
			return;
		}
		var gender = ($('#hstGender').val() || 'M') === 'F' ? 'Girls' : 'Boys';
		$name.attr('placeholder', gender + ' Dormitory');
	}

	function ensureTableBody() {
		var $tb = $('#hstTbody');
		$tb.find('.hst-empty-row').remove();
		return $tb;
	}

	function actionButtons(id) {
		return '' +
			'<button type="button" class="btn btn-outline-primary btn-sm hst-edit mr-1" data-id="' + id + '" title="Edit"><i class="fa fa-edit"></i> Edit</button>' +
			'<button type="button" class="btn btn-outline-danger btn-sm hst-del" data-id="' + id + '" title="Remove"><i class="fa fa-trash"></i></button>';
	}

	function rowHtml(h) {
		var name = h.name || '';
		var beds = parseInt(h.max_beds, 10) || 0;
		var gender = h.gender || 'M';
		var id = parseInt(h.id, 10) || 0;
		var chargeId = parseInt(h.in_charge_staff_id, 10) || 0;
		var charge = h.in_charge_name || chargeName(chargeId);
		return '' +
			'<tr data-id="' + id + '" data-name="' + escHtml(name) + '" data-max-beds="' + beds + '" data-gender="' + escHtml(gender) + '" data-charge-id="' + chargeId + '" data-charge-name="' + escHtml(charge) + '">' +
			'<td><strong>' + escHtml(name) + '</strong></td>' +
			'<td>' + beds + '</td>' +
			'<td>' + genderBadge(gender) + '</td>' +
			'<td>' + (charge ? escHtml(charge) : '—') + '</td>' +
			'<td class="text-center">' + actionButtons(id) + '</td>' +
			'</tr>';
	}

	function editRowHtml(id, name, beds, gender, chargeId) {
		return '' +
			'<td><input type="text" class="form-control form-control-sm hst-edit-name" value="' + escHtml(name) + '" maxlength="160"></td>' +
			'<td><input type="number" class="form-control form-control-sm hst-edit-beds" value="' + beds + '" min="1" max="9999"></td>' +
			'<td>' +
				'<select class="form-control form-control-sm hst-edit-gender">' +
					'<option value="M"' + (String(gender).toUpperCase() === 'M' ? ' selected' : '') + '>Male</option>' +
					'<option value="F"' + (String(gender).toUpperCase() === 'F' ? ' selected' : '') + '>Female</option>' +
				'</select>' +
			'</td>' +
			'<td>' + chargeSelect(chargeId) + '</td>' +
			'<td class="text-center">' +
				'<button type="button" class="btn btn-success btn-sm hst-save mr-1" data-id="' + id + '" title="Save"><i class="fa fa-save"></i> Save</button>' +
				'<button type="button" class="btn btn-secondary btn-sm hst-cancel" data-id="' + id + '" title="Cancel"><i class="fa fa-times"></i></button>' +
			'</td>';
	}

	$('#hstAddForm').on('submit', function (e) {
		e.preventDefault();
		var name = $.trim($('#hstName').val() || '');
		var beds = parseInt($('#hstBeds').val(), 10) || 0;
		var gender = $('#hstGender').val() || 'M';
		var chargeId = parseInt($('#hstCharge').val(), 10) || 0;
		if (!name || beds < 1) {
			toast('Enter hostel name and max beds.', false);
			return;
		}
		$.post('<?= base_url('manipulate_hostel'); ?>', {
			action: 'add',
			name: name,
			max_beds: beds,
			gender: gender,
			in_charge_staff_id: chargeId
		}).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			var h = res.hostel || {};
			var $tb = ensureTableBody();
			$tb.append(rowHtml({
				id: h.id,
				name: h.name || name,
				max_beds: h.max_beds || beds,
				gender: h.gender || gender,
				in_charge_staff_id: h.in_charge_staff_id || chargeId,
				in_charge_name: h.in_charge_name || chargeName(chargeId)
			}));
			$('#hstName').val('');
			$('#hstGender').val('M');
			$('#hstCharge').val('');
			syncHostelNameSuggestion();
			toast(res.success || 'Hostel added.', true);
		}).fail(function () {
			toast('Could not add hostel.', false);
		});
	});

	$(document).on('click', '.hst-edit', function () {
		var $row = $(this).closest('tr');
		var id = parseInt($row.data('id'), 10) || 0;
		if (!id) {
			return;
		}
		var name = String($row.data('name') || '');
		var beds = parseInt($row.data('max-beds'), 10) || 1;
		var gender = String($row.data('gender') || 'M');
		var chargeId = parseInt($row.data('charge-id'), 10) || 0;
		$row.html(editRowHtml(id, name, beds, gender, chargeId));
	});

	$(document).on('click', '.hst-cancel', function () {
		var $row = $(this).closest('tr');
		$row.replaceWith(rowHtml({
			id: parseInt($row.data('id'), 10) || 0,
			name: String($row.data('name') || ''),
			max_beds: parseInt($row.data('max-beds'), 10) || 1,
			gender: String($row.data('gender') || 'M'),
			in_charge_staff_id: parseInt($row.data('charge-id'), 10) || 0,
			in_charge_name: String($row.data('charge-name') || '')
		}));
	});

	$(document).on('click', '.hst-save', function () {
		var $row = $(this).closest('tr');
		var id = parseInt($(this).data('id'), 10) || 0;
		var name = $.trim($row.find('.hst-edit-name').val() || '');
		var beds = parseInt($row.find('.hst-edit-beds').val(), 10) || 0;
		var gender = $row.find('.hst-edit-gender').val() || 'M';
		var chargeId = parseInt($row.find('.hst-edit-charge').val(), 10) || 0;
		if (!id || !name || beds < 1) {
			toast('Enter hostel name and valid max beds.', false);
			return;
		}
		$.post('<?= base_url('manipulate_hostel'); ?>', {
			action: 'update',
			id: id,
			name: name,
			max_beds: beds,
			gender: gender,
			in_charge_staff_id: chargeId
		}).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			var h = res.hostel || {};
			$row.replaceWith(rowHtml({
				id: h.id || id,
				name: h.name || name,
				max_beds: h.max_beds || beds,
				gender: h.gender || gender,
				in_charge_staff_id: h.in_charge_staff_id || chargeId,
				in_charge_name: h.in_charge_name || chargeName(chargeId)
			}));
			toast(res.success || 'Hostel updated.', true);
		}).fail(function () {
			toast('Could not update hostel.', false);
		});
	});

	$(document).on('click', '.hst-del', function () {
		var id = $(this).data('id');
		var $row = $(this).closest('tr');
		if (!id || !confirm('Remove this hostel? Allocations for the current year will also be cleared.')) {
			return;
		}
		$.post('<?= base_url('manipulate_hostel'); ?>', { action: 'delete', id: id }).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			$row.remove();
			if (!$('#hstTbody tr').length) {
				$('#hstTbody').append('<tr class="hst-empty-row"><td colspan="5" class="text-muted text-center">No hostels yet. Add the first one above.</td></tr>');
			}
			toast(res.success || 'Hostel removed.', true);
		}).fail(function () {
			toast('Could not remove hostel.', false);
		});
	});

	$('#hstGender').on('change', syncHostelNameSuggestion);
	syncHostelNameSuggestion();
})(jQuery);
</script>
