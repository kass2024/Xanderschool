<?php
/** @var array $hostels */
$hostels = $hostels ?? [];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/hostels.css'); ?>">

<div class="hst-settings" id="hstSettings">
	<p class="text-muted hst-intro">
		Create hostels with a name, maximum beds, and gender (Male or Female). Only boarding students can later be allocated to these hostels.
	</p>

	<div class="hst-card">
		<h6><i class="fa fa-bed"></i> Hostel catalog</h6>
		<form id="hstAddForm" class="hst-add-form">
			<div class="form-row align-items-end">
				<div class="col-md-4">
					<label class="small font-weight-bold">Hostel name</label>
					<input type="text" class="form-control form-control-sm" id="hstName" placeholder="e.g. Hope Hostel" required maxlength="160">
				</div>
				<div class="col-md-2">
					<label class="small font-weight-bold">Max beds</label>
					<input type="number" class="form-control form-control-sm" id="hstBeds" min="1" max="9999" value="40" required>
				</div>
				<div class="col-md-3">
					<label class="small font-weight-bold">Gender</label>
					<select class="form-control form-control-sm" id="hstGender" required>
						<option value="M">Male</option>
						<option value="F">Female</option>
					</select>
				</div>
				<div class="col-md-3">
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
					<th style="width:110px">Max beds</th>
					<th style="width:110px">Gender</th>
					<th style="width:70px"></th>
				</tr>
				</thead>
				<tbody id="hstTbody">
				<?php if (empty($hostels)) : ?>
					<tr class="hst-empty-row"><td colspan="4" class="text-muted text-center">No hostels yet. Add the first one above.</td></tr>
				<?php else : ?>
					<?php foreach ($hostels as $h) : ?>
						<tr data-id="<?= (int) $h['id']; ?>">
							<td><strong><?= esc($h['name']); ?></strong></td>
							<td><?= (int) $h['max_beds']; ?></td>
							<td>
								<span class="hst-gender-badge hst-gender-<?= strtoupper((string) $h['gender']) === 'F' ? 'f' : 'm'; ?>">
									<?= strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male'; ?>
								</span>
							</td>
							<td class="text-center">
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

	function ensureTableBody() {
		var $tb = $('#hstTbody');
		$tb.find('.hst-empty-row').remove();
		return $tb;
	}

	$('#hstAddForm').on('submit', function (e) {
		e.preventDefault();
		var name = $.trim($('#hstName').val() || '');
		var beds = parseInt($('#hstBeds').val(), 10) || 0;
		var gender = $('#hstGender').val() || 'M';
		if (!name || beds < 1) {
			toast('Enter hostel name and max beds.', false);
			return;
		}
		$.post('<?= base_url('manipulate_hostel'); ?>', {
			action: 'add',
			name: name,
			max_beds: beds,
			gender: gender
		}).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			var h = res.hostel || {};
			var $tb = ensureTableBody();
			$tb.append(
				'<tr data-id="' + h.id + '">' +
				'<td><strong>' + $('<div>').text(h.name || name).html() + '</strong></td>' +
				'<td>' + (h.max_beds || beds) + '</td>' +
				'<td>' + genderBadge(h.gender || gender) + '</td>' +
				'<td class="text-center"><button type="button" class="btn btn-link btn-sm text-danger hst-del" data-id="' + h.id + '"><i class="fa fa-trash"></i></button></td>' +
				'</tr>'
			);
			$('#hstName').val('');
			toast(res.success || 'Hostel added.', true);
		}).fail(function () {
			toast('Could not add hostel.', false);
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
				$('#hstTbody').append('<tr class="hst-empty-row"><td colspan="4" class="text-muted text-center">No hostels yet. Add the first one above.</td></tr>');
			}
			toast(res.success || 'Hostel removed.', true);
		}).fail(function () {
			toast('Could not remove hostel.', false);
		});
	});
})(jQuery);
</script>
