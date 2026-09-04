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

	<div class="hst-card mb-3 hst-search-card">
		<h6><i class="fa fa-search"></i> Find student (class &amp; hostel)</h6>
		<p class="text-muted small mb-2">Search any student in the school by name or registration number for the selected academic year.</p>
		<div class="form-row align-items-end">
			<div class="col-md-9">
				<label class="small font-weight-bold">Student</label>
				<input type="search" class="form-control form-control-sm" id="hstFindQ"
					placeholder="Type name or regno…" autocomplete="off">
			</div>
			<div class="col-md-3">
				<button type="button" class="btn btn-primary btn-sm btn-block" id="hstFindBtn">
					<i class="fa fa-search"></i> Search
				</button>
			</div>
		</div>
		<div id="hstFindResult" class="hst-find-result" style="display:none;"></div>
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
	var HST_HOSTELS = <?= json_encode(array_map(static function ($h) {
		return [
			'id' => (int) $h['id'],
			'name' => (string) ($h['name'] ?? ''),
			'gender' => strtoupper((string) ($h['gender'] ?? 'M')) === 'F' ? 'F' : 'M',
			'free_beds' => (int) ($h['free_beds'] ?? 0),
		];
	}, $hostels), JSON_UNESCAPED_UNICODE); ?>;
	var currentHostelId = 0;

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

	function esc(s) {
		return $('<div>').text(s == null ? '' : String(s)).html();
	}

	function reloadPage() {
		var y = yearId();
		window.location = '<?= base_url('hostel_allocate'); ?>?year=' + y;
	}

	function sameGenderHostels(fromId) {
		var from = null;
		HST_HOSTELS.forEach(function (h) {
			if (h.id === fromId) {
				from = h;
			}
		});
		if (!from) {
			return [];
		}
		return HST_HOSTELS.filter(function (h) {
			return h.id !== fromId && h.gender === from.gender;
		});
	}

	function classLabel(s) {
		if (s.class_label) {
			return String(s.class_label);
		}
		return $.trim([s.level_name, s.dept_code, s.class_title].filter(Boolean).join(' '));
	}

	$('#hstYear').on('change', reloadPage);

	if ($.fn.select2) {
		$('#hstOneStudent, #hstClassSelect, #hstAutoClass').select2({ width: '100%', allowClear: true, placeholder: 'Select…' });
	}

	var findTimer = null;
	function runStudentFind() {
		var q = $.trim($('#hstFindQ').val() || '');
		var $box = $('#hstFindResult');
		if (q.length < 2) {
			$box.html('<p class="text-muted mb-0">Type at least 2 characters.</p>').show();
			return;
		}
		$box.html('<p class="text-muted mb-0">Searching…</p>').show();
		$.getJSON('<?= base_url('hostel_student_search'); ?>', { year: yearId(), q: q }).done(function (res) {
			if (res.error) {
				$box.html('<p class="text-danger mb-0">' + esc(res.error) + '</p>');
				return;
			}
			var rows = res.students || [];
			if (!rows.length) {
				$box.html('<p class="text-muted mb-0">No students found.</p>');
				return;
			}
			var html = '<ul class="hst-find-list">';
			rows.forEach(function (s) {
				var name = $.trim((s.regno ? s.regno + ' — ' : '') + (s.fname || '') + ' ' + (s.lname || ''));
				var cls = s.class_label || 'No class';
				var hostel = s.hostel_name
					? s.hostel_name
					: (parseInt(s.studying_mode, 10) === 0 ? 'Not allocated' : 'No hostel (day)');
				html += '<li>' +
					'<div class="hst-find-main">' +
						'<span class="hst-find-name">' + esc(name) + '</span>' +
						'<span class="hst-find-meta">Class: <strong>' + esc(cls) + '</strong></span>' +
						'<span class="hst-find-meta">Hostel: <strong>' + esc(hostel) + '</strong>' +
							(s.mode_label ? ' · ' + esc(s.mode_label) : '') + '</span>' +
					'</div>' +
					(s.hostel_id
						? '<button type="button" class="btn btn-link btn-sm hst-find-goto" data-hostel="' + s.hostel_id + '">Open</button>'
						: '') +
				'</li>';
			});
			html += '</ul>';
			$box.html(html);
		}).fail(function () {
			$box.html('<p class="text-danger mb-0">Search failed.</p>');
		});
	}

	$('#hstFindBtn').on('click', runStudentFind);
	$('#hstFindQ').on('keydown', function (e) {
		if (e.key === 'Enter' || e.keyCode === 13) {
			e.preventDefault();
			runStudentFind();
		}
	}).on('input', function () {
		clearTimeout(findTimer);
		findTimer = setTimeout(function () {
			var q = $.trim($('#hstFindQ').val() || '');
			if (q.length >= 2) {
				runStudentFind();
			}
		}, 350);
	});

	$(document).on('click', '.hst-find-goto', function () {
		var hid = parseInt($(this).data('hostel'), 10) || 0;
		if (!hid) {
			return;
		}
		var $item = $('.hst-occ-item[data-id="' + hid + '"]');
		if ($item.length) {
			$item.trigger('click');
			$('html, body').animate({ scrollTop: $item.offset().top - 80 }, 250);
		}
	});

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
		var id = parseInt($(this).data('id'), 10) || 0;
		currentHostelId = id;
		$('.hst-occ-item').removeClass('is-active');
		$(this).addClass('is-active');
		$.getJSON('<?= base_url('hostel_residents'); ?>', { hostel_id: id, year: yearId() }).done(function (res) {
			var rows = res.students || [];
			var moves = sameGenderHostels(id);
			var html = '';
			if (!rows.length) {
				html = '<p class="text-muted mb-0">No residents yet.</p>';
			} else {
				html = '<ul class="hst-resident-list">';
				rows.forEach(function (s) {
					var name = $.trim((s.regno || '') + ' ' + (s.fname || '') + ' ' + (s.lname || ''));
					var cls = classLabel(s);
					var moveHtml = '';
					if (moves.length) {
						moveHtml = '<select class="form-control form-control-sm hst-move-sel" data-student="' + s.id + '" title="Move to another hostel">' +
							'<option value="">Move…</option>';
						moves.forEach(function (h) {
							moveHtml += '<option value="' + h.id + '">' + esc(h.name) +
								(h.free_beds > 0 ? ' (' + h.free_beds + ' free)' : ' (full)') +
								'</option>';
						});
						moveHtml += '</select>';
					}
					html += '<li>' +
						'<div class="hst-resident-main">' +
							'<span class="hst-resident-name">' + esc(name) + '</span>' +
							(cls ? '<span class="hst-resident-class">' + esc(cls) + '</span>' : '') +
						'</div>' +
						'<div class="hst-resident-actions">' +
							moveHtml +
							'<button type="button" class="btn btn-link btn-sm text-danger hst-unassign" data-student="' + s.id + '">Remove</button>' +
						'</div>' +
					'</li>';
				});
				html += '</ul>';
			}
			$('#hstResidentsBody').html(html);
			$('#hstResidents').show();
		});
	});

	$(document).on('change', '.hst-move-sel', function () {
		var $sel = $(this);
		var sid = parseInt($sel.data('student'), 10) || 0;
		var hid = parseInt($sel.val(), 10) || 0;
		if (!sid || !hid) {
			return;
		}
		var label = $sel.find('option:selected').text();
		if (!confirm('Move this student to ' + label + '?')) {
			$sel.val('');
			return;
		}
		$.post('<?= base_url('hostel_assign'); ?>', {
			student_id: sid,
			hostel_id: hid,
			year: yearId()
		}).done(function (res) {
			if (res.error) {
				toast(res.error, false);
				$sel.val('');
				return;
			}
			toast(res.success || 'Student moved.', true);
			reloadPage();
		}).fail(function () {
			toast('Move failed.', false);
			$sel.val('');
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
