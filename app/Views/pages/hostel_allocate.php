<link rel="stylesheet" href="<?= base_url('assets/css/hostels.css'); ?>">

<?php
$hostels = $hostels ?? [];
$classes = $classes ?? [];
$departments = $departments ?? [];
$years = $years ?? [];
$yearId = (int) ($academic_year_id ?? 0);
?>

<div class="hst-alloc-page" id="hstAllocPage">
	<div class="hst-alloc-head">
		<div>
			<h4 class="mb-1"><i class="fa fa-bed"></i> Hostel allocation</h4>
			<p class="text-muted mb-0">Allocate boarding students only. Day scholars cannot be assigned to a hostel.</p>
		</div>
		<div class="hst-year-pick">
			<label class="small font-weight-bold mb-0">Academic year</label>
			<select class="form-control form-control-sm" id="hstYear">
				<?php foreach ($years as $yr) : ?>
					<option value="<?= (int) $yr['id']; ?>" <?= (int) $yr['id'] === $yearId ? 'selected' : ''; ?>>
						<?= esc($yr['title']); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-4">
			<div class="hst-card">
				<h6><i class="fa fa-building"></i> Hostels occupancy</h6>
				<div id="hstOccList" class="hst-occ-list">
					<?php if (empty($hostels)) : ?>
						<p class="text-muted mb-0">No hostels configured. Add them in <strong>Settings → Hostels</strong>.</p>
					<?php else : ?>
						<?php foreach ($hostels as $h) : ?>
							<button type="button" class="hst-occ-item" data-id="<?= (int) $h['id']; ?>">
								<span class="hst-occ-name"><?= esc($h['name']); ?></span>
								<span class="hst-gender-badge hst-gender-<?= strtoupper((string) $h['gender']) === 'F' ? 'f' : 'm'; ?>">
									<?= strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male'; ?>
								</span>
								<span class="hst-occ-beds"><?= (int) ($h['occupied'] ?? 0); ?> / <?= (int) $h['max_beds']; ?></span>
							</button>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<div id="hstResidents" class="hst-residents" style="display:none;">
					<h6 class="mt-3">Residents</h6>
					<div id="hstResidentsBody" class="hst-residents-body"></div>
				</div>
			</div>
		</div>

		<div class="col-lg-8">
			<div class="hst-card mb-3">
				<h6><i class="fa fa-user-plus"></i> Allocate one student</h6>
				<div class="form-row align-items-end">
					<div class="col-md-5">
						<label class="small font-weight-bold">Student (boarding only)</label>
						<select class="form-control form-control-sm select2" id="hstOneStudent" data-placeholder="Search boarding student…">
							<option value=""></option>
						</select>
					</div>
					<div class="col-md-4">
						<label class="small font-weight-bold">Hostel</label>
						<select class="form-control form-control-sm" id="hstOneHostel">
							<option value="">Select hostel…</option>
							<?php foreach ($hostels as $h) : ?>
								<option value="<?= (int) $h['id']; ?>" data-gender="<?= esc($h['gender']); ?>">
									<?= esc($h['name']); ?> (<?= strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male'; ?>, <?= (int) ($h['free_beds'] ?? 0); ?> free)
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3">
						<button type="button" class="btn btn-primary btn-sm btn-block" id="hstOneAssign">
							<i class="fa fa-check"></i> Allocate
						</button>
					</div>
				</div>
			</div>

			<div class="hst-card mb-3">
				<h6><i class="fa fa-users"></i> Allocate by class</h6>
				<div class="form-row align-items-end">
					<div class="col-md-5">
						<label class="small font-weight-bold">Class</label>
						<select class="form-control form-control-sm select2" id="hstClassSelect">
							<option value="">Select class…</option>
							<?php foreach ($classes as $cl) : ?>
								<option value="<?= (int) $cl['id']; ?>">
									<?= esc(trim(($cl['level_name'] ?? '') . ' ' . ($cl['dept_code'] ?? '') . ' ' . ($cl['title'] ?? ''))); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label class="small font-weight-bold">Hostel</label>
						<select class="form-control form-control-sm" id="hstClassHostel">
							<option value="">Select hostel…</option>
							<?php foreach ($hostels as $h) : ?>
								<option value="<?= (int) $h['id']; ?>">
									<?= esc($h['name']); ?> (<?= strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male'; ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3">
						<button type="button" class="btn btn-primary btn-sm btn-block" id="hstClassAssign">
							<i class="fa fa-check"></i> Allocate class
						</button>
					</div>
				</div>
				<p class="text-muted small mt-2 mb-0">Only boarding students in the class are allocated; day scholars are skipped.</p>
			</div>

			<div class="hst-card">
				<h6><i class="fa fa-magic"></i> Auto-allocate boarding students</h6>
				<p class="text-muted small">Fills free beds by gender. Optionally limit by department or class. Already allocated students are left unchanged.</p>
				<div class="form-row align-items-end">
					<div class="col-md-4">
						<label class="small font-weight-bold">Department (optional)</label>
						<select class="form-control form-control-sm" id="hstAutoDept">
							<option value="0">All departments</option>
							<?php foreach ($departments as $d) : ?>
								<option value="<?= (int) $d['id']; ?>">
									<?= esc(trim(($d['code'] ?? '') . ' ' . ($d['title'] ?? ''))); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label class="small font-weight-bold">Class (optional)</label>
						<select class="form-control form-control-sm select2" id="hstAutoClass">
							<option value="0">All classes</option>
							<?php foreach ($classes as $cl) : ?>
								<option value="<?= (int) $cl['id']; ?>" data-dept="<?= (int) ($cl['department_id'] ?? 0); ?>">
									<?= esc(trim(($cl['level_name'] ?? '') . ' ' . ($cl['dept_code'] ?? '') . ' ' . ($cl['title'] ?? ''))); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<button type="button" class="btn btn-success btn-sm btn-block" id="hstAutoRun">
							<i class="fa fa-bolt"></i> Auto allocate
						</button>
					</div>
				</div>
				<div id="hstAutoResult" class="hst-auto-result" style="display:none;"></div>
			</div>
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

	function yearId() {
		return parseInt($('#hstYear').val(), 10) || 0;
	}

	function reloadPage() {
		var y = yearId();
		window.location = '<?= base_url('hostel_allocate'); ?>?year=' + y;
	}

	$('#hstYear').on('change', reloadPage);

	if ($.fn.select2) {
		$('#hstOneStudent, #hstClassSelect, #hstAutoClass').select2({ width: '100%', allowClear: true, placeholder: 'Select…' });
	}

	function loadCandidates() {
		$.getJSON('<?= base_url('hostel_candidates'); ?>', { year: yearId() }).done(function (res) {
			var $sel = $('#hstOneStudent');
			$sel.empty().append('<option value=""></option>');
			(res.students || []).forEach(function (s) {
				var label = (s.regno ? s.regno + ' — ' : '') + (s.fname || '') + ' ' + (s.lname || '') +
					' (' + (s.level_name || '') + ' ' + (s.class_title || '') + ')' +
					(s.hostel_name ? ' · ' + s.hostel_name : '');
				$sel.append($('<option>').val(s.id).text(label).attr('data-sex', s.sex || ''));
			});
			$sel.trigger('change');
		});
	}
	loadCandidates();

	$('#hstOneAssign').on('click', function () {
		var sid = parseInt($('#hstOneStudent').val(), 10) || 0;
		var hid = parseInt($('#hstOneHostel').val(), 10) || 0;
		if (!sid || !hid) {
			toast('Select student and hostel.', false);
			return;
		}
		$.post('<?= base_url('hostel_assign'); ?>', {
			student_id: sid,
			hostel_id: hid,
			year: yearId()
		}).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			toast(res.success || 'Allocated.', true);
			reloadPage();
		}).fail(function () {
			toast('Allocation failed.', false);
		});
	});

	$('#hstClassAssign').on('click', function () {
		var cid = parseInt($('#hstClassSelect').val(), 10) || 0;
		var hid = parseInt($('#hstClassHostel').val(), 10) || 0;
		if (!cid || !hid) {
			toast('Select class and hostel.', false);
			return;
		}
		$.post('<?= base_url('hostel_assign_class'); ?>', {
			class_id: cid,
			hostel_id: hid,
			year: yearId()
		}).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			toast(res.success || 'Class allocated.', true);
			reloadPage();
		}).fail(function () {
			toast('Class allocation failed.', false);
		});
	});

	$('#hstAutoRun').on('click', function () {
		if (!confirm('Auto-allocate unassigned boarding students into free hostel beds?')) {
			return;
		}
		$.post('<?= base_url('hostel_auto_allocate'); ?>', {
			year: yearId(),
			department_id: parseInt($('#hstAutoDept').val(), 10) || 0,
			class_id: parseInt($('#hstAutoClass').val(), 10) || 0
		}).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			var html = '<strong>' + (res.success || 'Done') + '</strong>';
			if (res.errors && res.errors.length) {
				html += '<ul class="mb-0 mt-2">' + res.errors.map(function (e) {
					return '<li>' + $('<div>').text(e).html() + '</li>';
				}).join('') + '</ul>';
			}
			$('#hstAutoResult').html(html).show();
			toast(res.success || 'Auto allocation finished.', true);
			setTimeout(reloadPage, 1200);
		}).fail(function () {
			toast('Auto allocation failed.', false);
		});
	});

	$(document).on('click', '.hst-occ-item', function () {
		var id = $(this).data('id');
		$('.hst-occ-item').removeClass('is-active');
		$(this).addClass('is-active');
		$.getJSON('<?= base_url('hostel_residents'); ?>', { hostel_id: id, year: yearId() }).done(function (res) {
			var rows = res.students || [];
			var html = '';
			if (!rows.length) {
				html = '<p class="text-muted mb-0">No residents yet.</p>';
			} else {
				html = '<ul class="hst-resident-list">';
				rows.forEach(function (s) {
					html += '<li><span>' + $('<div>').text((s.regno || '') + ' ' + (s.fname || '') + ' ' + (s.lname || '')).html() +
						'</span><button type="button" class="btn btn-link btn-sm text-danger hst-unassign" data-student="' + s.id + '">Remove</button></li>';
				});
				html += '</ul>';
			}
			$('#hstResidentsBody').html(html);
			$('#hstResidents').show();
		});
	});

	$(document).on('click', '.hst-unassign', function () {
		var sid = $(this).data('student');
		if (!sid || !confirm('Remove this student from the hostel?')) {
			return;
		}
		$.post('<?= base_url('hostel_unassign'); ?>', { student_id: sid, year: yearId() }).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				return;
			}
			toast(res.success || 'Removed.', true);
			reloadPage();
		});
	});
})(jQuery);
</script>
