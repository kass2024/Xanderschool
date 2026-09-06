<?php
/** @var array $hostels */
/** @var array $hostel_settings */
$hostels = $hostels ?? [];
$hostelSettings = $hostel_settings ?? ['separate_by_level' => false];
$separateByLevel = !empty($hostelSettings['separate_by_level']);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/hostels.css'); ?>?v=3">

<div class="hst-settings" id="hstSettings">
	<p class="text-muted hst-intro">
		Create hostels with a name, maximum beds, and gender (Male or Female). Only boarding students can later be allocated to these hostels.
	</p>

	<div class="hst-card mb-3">
		<h6><i class="fa fa-shield"></i> Allocation rules</h6>
		<p class="text-muted small mb-3">
			Control whether students from different school groups may share the same hostel. Nursery stays separate, Primary stays separate, and every other level is treated as High School.
		</p>
		<label class="hst-rule-toggle <?= $separateByLevel ? 'is-on' : ''; ?>" id="hstLevelRuleLabel">
			<input type="checkbox" id="hstSeparateByLevel" <?= $separateByLevel ? 'checked' : ''; ?>>
			<span class="hst-rule-switch" aria-hidden="true"></span>
			<span class="hst-rule-copy">
				<strong>Keep levels separate in each hostel</strong>
				<small>When ON, Nursery, Primary, and High School students cannot mix in the same hostel. For WISDOM SCHOOL RWANDA, O Level, A Level, ANP, and all other non-primary/non-nursery levels are treated as High School.</small>
			</span>
		</label>
		<div id="hstRuleSaveMsg" class="hst-rule-msg" style="display:none;"></div>
	</div>

	<div class="hst-card">
		<h6><i class="fa fa-bed"></i> Hostel catalog</h6>
		<p class="text-muted small mb-3">
			Create hostels by gender and level group, for example <strong>Primary Girls Hostel</strong> or <strong>High School Boys Hostel</strong>.
		</p>
		<form id="hstAddForm" class="hst-add-form">
			<div class="form-row align-items-end">
				<div class="col-md-3">
					<label class="small font-weight-bold">Hostel name</label>
					<input type="text" class="form-control form-control-sm" id="hstName" placeholder="e.g. Primary Girls Hostel" required maxlength="160">
				</div>
				<div class="col-md-2">
					<label class="small font-weight-bold">Max beds</label>
					<input type="number" class="form-control form-control-sm" id="hstBeds" min="1" max="9999" value="40" required>
				</div>
				<div class="col-md-3">
					<label class="small font-weight-bold">Level group</label>
					<select class="form-control form-control-sm" id="hstLevelGroup" required>
						<option value="nursery">Nursery</option>
						<option value="primary">Primary</option>
						<option value="high_school" selected>High School</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="small font-weight-bold">Gender</label>
					<select class="form-control form-control-sm" id="hstGender" required>
						<option value="M">Male</option>
						<option value="F">Female</option>
					</select>
				</div>
				<div class="col-md-2">
					<button type="submit" class="btn btn-success btn-sm btn-block">
						<i class="fa fa-plus"></i> Add hostel
					</button>
				</div>
			</div>
		</form>

		<div class="table-responsive">
			<table class="table table-sm table-bordered mb-0" id="hstTable">
				<thead>
				<tr>
					<th>Name</th>
					<th style="width:130px">Level group</th>
					<th style="width:110px">Max beds</th>
					<th style="width:110px">Gender</th>
					<th style="width:70px"></th>
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
							data-level-group="<?= esc($h['level_group'] ?? ''); ?>"
							data-max-beds="<?= (int) $h['max_beds']; ?>"
							data-gender="<?= esc($h['gender']); ?>"
						>
							<td><strong><?= esc($h['name']); ?></strong></td>
							<td><?= esc($h['level_group_label'] ?? ''); ?></td>
							<td><?= (int) $h['max_beds']; ?></td>
							<td>
								<span class="hst-gender-badge hst-gender-<?= strtoupper((string) $h['gender']) === 'F' ? 'f' : 'm'; ?>">
									<?= strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male'; ?>
								</span>
							</td>
							<td class="text-center">
								<button type="button" class="btn btn-link btn-sm text-primary hst-edit" data-id="<?= (int) $h['id']; ?>" title="Edit">
									<i class="fa fa-pencil"></i>
								</button>
								<button type="button" class="btn btn-link btn-sm text-danger hst-del" data-id="<?= (int) $h['id']; ?>" title="Remove">
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

	function levelGroupLabel(group) {
		group = String(group || '').toLowerCase();
		if (group === 'nursery') {
			return 'Nursery';
		}
		if (group === 'primary') {
			return 'Primary';
		}
		return 'High School';
	}

	function syncHostelNameSuggestion() {
		var $name = $('#hstName');
		if ($.trim($name.val()).length) {
			return;
		}
		var gender = ($('#hstGender').val() || 'M') === 'F' ? 'Girls' : 'Boys';
		var group = levelGroupLabel($('#hstLevelGroup').val() || 'high_school');
		$name.attr('placeholder', group + ' ' + gender + ' Hostel');
	}

	function ensureTableBody() {
		var $tb = $('#hstTbody');
		$tb.find('.hst-empty-row').remove();
		return $tb;
	}

	function actionButtons(id) {
		return '' +
			'<button type="button" class="btn btn-link btn-sm text-primary hst-edit" data-id="' + id + '" title="Edit"><i class="fa fa-pencil"></i></button>' +
			'<button type="button" class="btn btn-link btn-sm text-danger hst-del" data-id="' + id + '" title="Remove"><i class="fa fa-trash"></i></button>';
	}

	function rowHtml(h) {
		var name = h.name || '';
		var levelGroup = h.level_group || '';
		var levelGroupLabelText = h.level_group_label || levelGroupLabel(levelGroup);
		var beds = parseInt(h.max_beds, 10) || 0;
		var gender = h.gender || 'M';
		var id = parseInt(h.id, 10) || 0;
		return '' +
			'<tr data-id="' + id + '" data-name="' + $('<div>').text(name).html() + '" data-level-group="' + $('<div>').text(levelGroup).html() + '" data-max-beds="' + beds + '" data-gender="' + $('<div>').text(gender).html() + '">' +
			'<td><strong>' + $('<div>').text(name).html() + '</strong></td>' +
			'<td>' + $('<div>').text(levelGroupLabelText).html() + '</td>' +
			'<td>' + beds + '</td>' +
			'<td>' + genderBadge(gender) + '</td>' +
			'<td class="text-center">' + actionButtons(id) + '</td>' +
			'</tr>';
	}

	function editRowHtml(id, name, levelGroup, beds, gender) {
		return '' +
			'<td><input type="text" class="form-control form-control-sm hst-edit-name" value="' + $('<div>').text(name).html() + '" maxlength="160"></td>' +
			'<td>' +
				'<select class="form-control form-control-sm hst-edit-level">' +
					'<option value="nursery"' + (levelGroup === 'nursery' ? ' selected' : '') + '>Nursery</option>' +
					'<option value="primary"' + (levelGroup === 'primary' ? ' selected' : '') + '>Primary</option>' +
					'<option value="high_school"' + (levelGroup === 'high_school' ? ' selected' : '') + '>High School</option>' +
				'</select>' +
			'</td>' +
			'<td><input type="number" class="form-control form-control-sm hst-edit-beds" value="' + beds + '" min="1" max="9999"></td>' +
			'<td>' +
				'<select class="form-control form-control-sm hst-edit-gender">' +
					'<option value="M"' + (String(gender).toUpperCase() === 'M' ? ' selected' : '') + '>Male</option>' +
					'<option value="F"' + (String(gender).toUpperCase() === 'F' ? ' selected' : '') + '>Female</option>' +
				'</select>' +
			'</td>' +
			'<td class="text-center">' +
				'<button type="button" class="btn btn-link btn-sm text-success hst-save" data-id="' + id + '" title="Save"><i class="fa fa-save"></i></button>' +
				'<button type="button" class="btn btn-link btn-sm text-muted hst-cancel" data-id="' + id + '" title="Cancel"><i class="fa fa-times"></i></button>' +
			'</td>';
	}

	function saveLevelRule() {
		var on = $('#hstSeparateByLevel').is(':checked');
		$('#hstLevelRuleLabel').toggleClass('is-on', on);
		var $msg = $('#hstRuleSaveMsg');
		$msg.text('Saving…').removeClass('is-ok is-err').show();
		$.post('<?= base_url('manipulate_hostel'); ?>', {
			action: 'save_settings',
			separate_by_level: on ? 1 : 0
		}).done(function (res) {
			if (res.error) {
				$msg.text(res.error).addClass('is-err');
				toast(res.error, false);
				return;
			}
			$msg.text(res.success || 'Rule saved.').addClass('is-ok');
			toast(res.success || 'Rule saved.', true);
		}).fail(function () {
			$msg.text('Could not save rule.').addClass('is-err');
			toast('Could not save rule.', false);
		});
	}

	$('#hstSeparateByLevel').on('change', saveLevelRule);

	$('#hstAddForm').on('submit', function (e) {
		e.preventDefault();
		var name = $.trim($('#hstName').val() || '');
		var beds = parseInt($('#hstBeds').val(), 10) || 0;
		var levelGroup = $('#hstLevelGroup').val() || 'high_school';
		var gender = $('#hstGender').val() || 'M';
		if (!name || beds < 1) {
			toast('Enter hostel name and max beds.', false);
			return;
		}
		$.post('<?= base_url('manipulate_hostel'); ?>', {
			action: 'add',
			name: name,
			max_beds: beds,
			level_group: levelGroup,
			gender: gender
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
				level_group: h.level_group || levelGroup,
				level_group_label: h.level_group_label || levelGroupLabel(h.level_group || levelGroup),
				max_beds: h.max_beds || beds,
				gender: h.gender || gender
			}));
			$('#hstName').val('');
			$('#hstLevelGroup').val('high_school');
			$('#hstGender').val('M');
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
		var levelGroup = String($row.data('level-group') || 'high_school');
		var beds = parseInt($row.data('max-beds'), 10) || 1;
		var gender = String($row.data('gender') || 'M');
		$row.html(editRowHtml(id, name, levelGroup, beds, gender));
	});

	$(document).on('click', '.hst-cancel', function () {
		var $row = $(this).closest('tr');
		$row.replaceWith(rowHtml({
			id: parseInt($row.data('id'), 10) || 0,
			name: String($row.data('name') || ''),
			level_group: String($row.data('level-group') || 'high_school'),
			level_group_label: levelGroupLabel(String($row.data('level-group') || 'high_school')),
			max_beds: parseInt($row.data('max-beds'), 10) || 1,
			gender: String($row.data('gender') || 'M')
		}));
	});

	$(document).on('click', '.hst-save', function () {
		var $row = $(this).closest('tr');
		var id = parseInt($(this).data('id'), 10) || 0;
		var name = $.trim($row.find('.hst-edit-name').val() || '');
		var levelGroup = $row.find('.hst-edit-level').val() || 'high_school';
		var beds = parseInt($row.find('.hst-edit-beds').val(), 10) || 0;
		var gender = $row.find('.hst-edit-gender').val() || 'M';
		if (!id || !name || beds < 1) {
			toast('Enter hostel name and valid max beds.', false);
			return;
		}
		$.post('<?= base_url('manipulate_hostel'); ?>', {
			action: 'update',
			id: id,
			name: name,
			level_group: levelGroup,
			max_beds: beds,
			gender: gender
		}).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			var h = res.hostel || {};
			$row.replaceWith(rowHtml({
				id: h.id || id,
				name: h.name || name,
				level_group: h.level_group || levelGroup,
				level_group_label: h.level_group_label || levelGroupLabel(h.level_group || levelGroup),
				max_beds: h.max_beds || beds,
				gender: h.gender || gender
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

	$('#hstLevelGroup, #hstGender').on('change', syncHostelNameSuggestion);
	syncHostelNameSuggestion();
})(jQuery);
</script>
