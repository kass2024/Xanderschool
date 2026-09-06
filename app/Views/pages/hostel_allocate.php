<link rel="stylesheet" href="<?= base_url('assets/css/hostels.css'); ?>?v=2">

<?php
$hostels = $hostels ?? [];
$classes = $classes ?? [];
$departments = $departments ?? [];
$years = $years ?? [];
$yearId = (int) ($academic_year_id ?? 0);
$hostelSettings = $hostel_settings ?? ['separate_by_level' => false];
$separateByLevel = !empty($hostelSettings['separate_by_level']);

$totalBeds = 0;
$totalOcc = 0;
$totalFree = 0;
foreach ($hostels as $h) {
	$totalBeds += (int) ($h['max_beds'] ?? 0);
	$totalOcc += (int) ($h['occupied'] ?? 0);
	$totalFree += (int) ($h['free_beds'] ?? 0);
}
?>

<div class="hst-alloc-page" id="hstAllocPage">
	<section class="hst-hero">
		<div class="hst-hero-top">
			<div>
				<div class="hst-hero-kicker"><i class="fa fa-bed"></i> Boarding housing</div>
				<h4>Hostel allocation</h4>
				<p class="hst-hero-sub">Live-search any student to see class and hostel. Allocate boarding students only — day scholars stay unassigned.<?= $separateByLevel ? ' <strong>Level separation is ON</strong> — Nursery, Primary, and High School students cannot share a hostel.' : ''; ?></p>
			</div>
			<div class="hst-year-pick">
				<label for="hstYear">Academic year</label>
				<select class="form-control form-control-sm" id="hstYear">
					<?php foreach ($years as $yr) : ?>
						<option value="<?= (int) $yr['id']; ?>" <?= (int) $yr['id'] === $yearId ? 'selected' : ''; ?>>
							<?= esc($yr['title']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<?php if (!empty($hostels)) : ?>
			<div class="hst-stat-row">
				<div class="hst-stat-chip"><strong><?= count($hostels); ?></strong> hostels</div>
				<div class="hst-stat-chip"><strong><?= (int) $totalOcc; ?></strong> / <?= (int) $totalBeds; ?> beds used</div>
				<div class="hst-stat-chip"><strong><?= (int) $totalFree; ?></strong> free</div>
			</div>
		<?php endif; ?>

		<?php if ($separateByLevel) : ?>
			<div class="hst-rule-banner">
				<i class="fa fa-shield"></i>
				<span>Mixing levels is blocked. Nursery stays separate, Primary stays separate, and all other levels are treated as High School. Change this in <strong>Settings → Hostels</strong>.</span>
			</div>
		<?php endif; ?>

		<div class="hst-live-search">
			<div class="hst-live-search-shell" id="hstFindShell">
				<i class="fa fa-search" aria-hidden="true"></i>
				<input type="search" id="hstFindQ" placeholder="Start typing a name or registration number…"
					autocomplete="off" spellcheck="false" aria-label="Live search students">
				<span class="hst-live-spin" id="hstFindSpin" aria-hidden="true"><i class="fa fa-circle-o-notch"></i></span>
				<button type="button" class="hst-live-clear" id="hstFindClear" title="Clear" aria-label="Clear search">
					<i class="fa fa-times"></i>
				</button>
			</div>
			<div id="hstFindResult" class="hst-find-result" aria-live="polite"></div>
		</div>
	</section>

	<div class="hst-grid">
		<aside class="hst-card">
			<div class="hst-panel-head">
				<h6 class="hst-panel-title mb-0"><i class="fa fa-building-o"></i> Hostels</h6>
			</div>
			<div id="hstOccList" class="hst-occ-list">
				<?php if (empty($hostels)) : ?>
					<p class="text-muted mb-0">No hostels configured. Add them in <strong>Settings → Hostels</strong>.</p>
				<?php else : ?>
					<?php foreach ($hostels as $h) :
						$occ = (int) ($h['occupied'] ?? 0);
						$max = max(1, (int) ($h['max_beds'] ?? 1));
						$pct = min(100, (int) round(($occ / $max) * 100));
						$gender = strtoupper((string) ($h['gender'] ?? 'M')) === 'F' ? 'F' : 'M';
						?>
						<button type="button" class="hst-occ-item<?= !empty($h['is_mixed']) ? ' is-mixed' : ''; ?>"
							data-id="<?= (int) $h['id']; ?>" data-gender="<?= $gender; ?>"
							data-mixed="<?= !empty($h['is_mixed']) ? '1' : '0'; ?>">
							<div class="hst-occ-top">
								<span class="hst-occ-name"><?= esc($h['name']); ?></span>
								<?php if (!empty($h['is_mixed'])) : ?>
									<span class="hst-mixed-badge">Mixed</span>
								<?php endif; ?>
								<span class="hst-gender-badge hst-gender-<?= $gender === 'F' ? 'f' : 'm'; ?>">
									<?= $gender === 'F' ? 'Female' : 'Male'; ?>
								</span>
								<span class="hst-occ-beds"><?= $occ; ?> / <?= (int) $h['max_beds']; ?></span>
							</div>
							<?php if (!empty($h['level_label'])) : ?>
								<div class="hst-occ-level<?= !empty($h['is_mixed']) ? ' is-mixed' : ''; ?>">
									<i class="fa fa-graduation-cap"></i> <?= esc($h['level_label']); ?>
								</div>
							<?php elseif ($separateByLevel && $occ === 0) : ?>
								<div class="hst-occ-level is-empty">Open for any single level</div>
							<?php endif; ?>
							<div class="hst-occ-meter" aria-hidden="true"><span style="width:<?= $pct; ?>%"></span></div>
						</button>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div id="hstResidents" class="hst-residents" style="display:none;">
				<div class="hst-residents-title">
					<h6>Residents</h6>
					<span id="hstResidentsCount"></span>
				</div>
				<div id="hstUnmixBar" class="hst-unmix-bar" style="display:none;">
					<p class="mb-2">This hostel mixes different levels. Relocate extras to other same-gender hostels (majority level stays here).</p>
					<button type="button" class="hst-btn hst-btn-warn" id="hstUnmixBtn">
						<i class="fa fa-exchange"></i> Relocate / Unmix levels
					</button>
				</div>
				<div id="hstResidentsBody" class="hst-residents-body"></div>
			</div>
		</aside>

		<section class="hst-card hst-tools">
			<div class="hst-panel-head">
				<h6 class="hst-panel-title mb-0"><i class="fa fa-sliders"></i> Allocate</h6>
			</div>

			<div class="hst-tabs" role="tablist">
				<button type="button" class="hst-tab is-active" data-tab="one">One student</button>
				<button type="button" class="hst-tab" data-tab="class">By class</button>
				<button type="button" class="hst-tab" data-tab="auto">Auto fill</button>
			</div>

			<div class="hst-tab-pane is-active" id="hstPaneOne" data-pane="one">
				<div class="form-row align-items-end">
					<div class="col-md-5 mb-2 mb-md-0">
						<label class="hst-field-label" for="hstOneStudent">Student (boarding)</label>
						<select class="form-control form-control-sm select2" id="hstOneStudent" data-placeholder="Search boarding student…">
							<option value=""></option>
						</select>
					</div>
					<div class="col-md-4 mb-2 mb-md-0">
						<label class="hst-field-label" for="hstOneHostel">Hostel</label>
						<select class="form-control form-control-sm" id="hstOneHostel">
							<option value="">Select hostel…</option>
							<?php foreach ($hostels as $h) : ?>
								<option value="<?= (int) $h['id']; ?>" data-gender="<?= esc($h['gender']); ?>">
									<?= esc($h['name']); ?> (<?= strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male'; ?><?= !empty($h['level_group_label']) ? ', ' . esc($h['level_group_label']) : ''; ?>, <?= (int) ($h['free_beds'] ?? 0); ?> free)
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3">
						<button type="button" class="hst-btn hst-btn-primary" id="hstOneAssign">
							<i class="fa fa-check"></i> Allocate
						</button>
					</div>
				</div>
			</div>

			<div class="hst-tab-pane" id="hstPaneClass" data-pane="class">
				<div class="form-row align-items-end">
					<div class="col-md-5 mb-2 mb-md-0">
						<label class="hst-field-label" for="hstClassSelect">Class</label>
						<select class="form-control form-control-sm select2" id="hstClassSelect">
							<option value="">Select class…</option>
							<?php foreach ($classes as $cl) : ?>
								<option value="<?= (int) $cl['id']; ?>">
									<?= esc(trim(($cl['level_name'] ?? '') . ' ' . ($cl['dept_code'] ?? '') . ' ' . ($cl['title'] ?? ''))); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4 mb-2 mb-md-0">
						<label class="hst-field-label" for="hstClassHostel">Hostel</label>
						<select class="form-control form-control-sm" id="hstClassHostel">
							<option value="">Select hostel…</option>
							<?php foreach ($hostels as $h) : ?>
								<option value="<?= (int) $h['id']; ?>">
									<?= esc($h['name']); ?> (<?= strtoupper((string) $h['gender']) === 'F' ? 'Female' : 'Male'; ?><?= !empty($h['level_group_label']) ? ' - ' . esc($h['level_group_label']) : ''; ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3">
						<button type="button" class="hst-btn hst-btn-primary" id="hstClassAssign">
							<i class="fa fa-check"></i> Allocate class
						</button>
					</div>
				</div>
				<p class="hst-tool-note">Only boarding students in the class are allocated; day scholars are skipped.</p>
			</div>

			<div class="hst-tab-pane" id="hstPaneAuto" data-pane="auto">
				<p class="hst-tool-note mt-0 mb-3">Fills free beds by gender. Optionally limit by department or class. Already allocated students stay unchanged.</p>
				<div class="form-row align-items-end">
					<div class="col-md-4 mb-2 mb-md-0">
						<label class="hst-field-label" for="hstAutoDept">Department</label>
						<select class="form-control form-control-sm" id="hstAutoDept">
							<option value="0">All departments</option>
							<?php foreach ($departments as $d) : ?>
								<option value="<?= (int) $d['id']; ?>">
									<?= esc(trim(($d['code'] ?? '') . ' ' . ($d['title'] ?? ''))); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4 mb-2 mb-md-0">
						<label class="hst-field-label" for="hstAutoClass">Class</label>
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
						<button type="button" class="hst-btn hst-btn-accent" id="hstAutoRun">
							<i class="fa fa-bolt"></i> Auto allocate
						</button>
					</div>
				</div>
				<div id="hstAutoResult" class="hst-auto-result" style="display:none;"></div>
			</div>
		</section>
	</div>
</div>

<script>
(function ($) {
	var HST_HOSTELS = <?= json_encode(array_map(static function ($h) {
		return [
			'id' => (int) $h['id'],
			'name' => (string) ($h['name'] ?? ''),
			'gender' => strtoupper((string) ($h['gender'] ?? 'M')) === 'F' ? 'F' : 'M',
			'level_group' => (string) ($h['level_group'] ?? ''),
			'free_beds' => (int) ($h['free_beds'] ?? 0),
			'is_mixed' => !empty($h['is_mixed']),
		];
	}, $hostels), JSON_UNESCAPED_UNICODE); ?>;
	var currentHostelId = 0;
	var currentHostelMixed = false;
	var findTimer = null;
	var findReq = null;
	var findSeq = 0;

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
		window.location = '<?= base_url('hostel_allocate'); ?>?year=' + yearId();
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

	function setFindBusy(on) {
		$('#hstFindSpin').toggleClass('is-on', !!on);
	}

	function syncFindClear() {
		var has = $.trim($('#hstFindQ').val() || '').length > 0;
		$('#hstFindClear').toggleClass('is-on', has);
	}

	function renderFindEmpty(msg) {
		$('#hstFindResult').html('<div class="hst-find-hint">' + esc(msg) + '</div>');
	}

	function runStudentFind(immediate) {
		var q = $.trim($('#hstFindQ').val() || '');
		var $box = $('#hstFindResult');
		syncFindClear();

		if (findReq && findReq.abort) {
			findReq.abort();
			findReq = null;
		}

		if (q.length === 0) {
			setFindBusy(false);
			$box.empty();
			return;
		}
		if (q.length < 2) {
			setFindBusy(false);
			renderFindEmpty('Keep typing… at least 2 characters.');
			return;
		}

		var seq = ++findSeq;
		setFindBusy(true);
		findReq = $.ajax({
			url: '<?= base_url('hostel_student_search'); ?>',
			dataType: 'json',
			data: { year: yearId(), q: q }
		}).done(function (res) {
			if (seq !== findSeq) {
				return;
			}
			if (res.error) {
				renderFindEmpty(res.error);
				return;
			}
			var rows = res.students || [];
			if (!rows.length) {
				renderFindEmpty('No students match “' + q + '”.');
				return;
			}
			var html = '<ul class="hst-find-list">';
			rows.forEach(function (s) {
				var name = $.trim((s.fname || '') + ' ' + (s.lname || ''));
				var regno = s.regno || '';
				var cls = s.class_label || 'No class';
				var isBoard = parseInt(s.studying_mode, 10) === 0;
				var hostelPill;
				if (s.hostel_name) {
					hostelPill = '<span class="hst-pill hst-pill-hostel"><i class="fa fa-bed"></i> ' + esc(s.hostel_name) + '</span>';
				} else if (isBoard) {
					hostelPill = '<span class="hst-pill hst-pill-empty"><i class="fa fa-exclamation-circle"></i> Not allocated</span>';
				} else {
					hostelPill = '<span class="hst-pill hst-pill-day"><i class="fa fa-sun-o"></i> Day scholar</span>';
				}
				html += '<li>' +
					'<div class="hst-find-main">' +
						'<span class="hst-find-name">' + esc(name) +
							(regno ? ' <span style="font-weight:500;color:#64748b">· ' + esc(regno) + '</span>' : '') +
						'</span>' +
						'<div class="hst-find-pills">' +
							'<span class="hst-pill"><i class="fa fa-graduation-cap"></i> ' + esc(cls) + '</span>' +
							hostelPill +
							(s.mode_label ? '<span class="hst-pill">' + esc(s.mode_label) + '</span>' : '') +
						'</div>' +
					'</div>' +
					(s.hostel_id
						? '<button type="button" class="hst-find-goto" data-hostel="' + s.hostel_id + '">Open hostel</button>'
						: '') +
				'</li>';
			});
			html += '</ul>';
			$box.html(html);
		}).fail(function (xhr, status) {
			if (status === 'abort' || seq !== findSeq) {
				return;
			}
			renderFindEmpty('Search failed. Try again.');
		}).always(function () {
			if (seq === findSeq) {
				setFindBusy(false);
				findReq = null;
			}
		});
	}

	function scheduleFind() {
		clearTimeout(findTimer);
		findTimer = setTimeout(function () {
			runStudentFind(false);
		}, 180);
	}

	$('#hstYear').on('change', reloadPage);

	$('#hstFindQ')
		.on('focus', function () { $('#hstFindShell').addClass('is-focused'); })
		.on('blur', function () { $('#hstFindShell').removeClass('is-focused'); })
		.on('input', scheduleFind)
		.on('keydown', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				$('#hstFindQ').val('');
				syncFindClear();
				$('#hstFindResult').empty();
				setFindBusy(false);
			}
		});

	$('#hstFindClear').on('click', function () {
		$('#hstFindQ').val('').focus();
		syncFindClear();
		$('#hstFindResult').empty();
		setFindBusy(false);
	});

	$(document).on('click', '.hst-find-goto', function () {
		var hid = parseInt($(this).data('hostel'), 10) || 0;
		if (!hid) {
			return;
		}
		var $item = $('.hst-occ-item[data-id="' + hid + '"]');
		if ($item.length) {
			$item.trigger('click');
			$('html, body').animate({ scrollTop: Math.max(0, $item.offset().top - 90) }, 280);
		}
	});

	$('.hst-tab').on('click', function () {
		var tab = $(this).data('tab');
		$('.hst-tab').removeClass('is-active');
		$(this).addClass('is-active');
		$('.hst-tab-pane').removeClass('is-active');
		$('.hst-tab-pane[data-pane="' + tab + '"]').addClass('is-active');
	});

	if ($.fn.select2) {
		$('#hstOneStudent, #hstClassSelect, #hstAutoClass').select2({
			width: '100%',
			allowClear: true,
			placeholder: 'Select…'
		});
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
			var html = '<strong>' + esc(res.success || 'Done') + '</strong>';
			if (res.errors && res.errors.length) {
				html += '<ul class="mb-0 mt-2">' + res.errors.map(function (e) {
					return '<li>' + esc(e) + '</li>';
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
		currentHostelMixed = String($(this).data('mixed')) === '1';
		$('.hst-occ-item').removeClass('is-active');
		$(this).addClass('is-active');
		$('#hstResidentsBody').html('<p class="text-muted mb-0">Loading…</p>');
		$('#hstUnmixBar').toggle(currentHostelMixed);
		$('#hstResidents').show();
		$.getJSON('<?= base_url('hostel_residents'); ?>', { hostel_id: id, year: yearId() }).done(function (res) {
			var rows = res.students || [];
			var moves = sameGenderHostels(id);
			if (res.is_mixed) {
				currentHostelMixed = true;
				$('#hstUnmixBar').show();
			}
			$('#hstResidentsCount').text(rows.length + (rows.length === 1 ? ' student' : ' students'));
			var html = '';
			if (!rows.length) {
				html = '<p class="text-muted mb-0">No residents yet.</p>';
				$('#hstUnmixBar').hide();
			} else {
				html = '<ul class="hst-resident-list">';
				rows.forEach(function (s) {
					var name = $.trim((s.regno || '') + ' ' + (s.fname || '') + ' ' + (s.lname || ''));
					var cls = classLabel(s);
					var moveHtml = '';
					if (moves.length) {
						moveHtml = '<select class="form-control form-control-sm hst-move-sel" data-student="' + s.id + '" title="Move to another hostel">' +
							'<option value="">Relocate…</option>';
						moves.forEach(function (h) {
							moveHtml += '<option value="' + h.id + '">' + esc(h.name) +
								(h.free_beds > 0 ? ' (' + h.free_beds + ' free)' : ' (full)') +
								'</option>';
						});
						moveHtml += '</select>';
					} else {
						moveHtml = '<span class="hst-no-move" title="Add another same-gender hostel to relocate">No other hostel</span>';
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
		});
	});

	$('#hstUnmixBtn').on('click', function () {
		if (!currentHostelId) {
			return;
		}
		if (!confirm('Relocate students from other levels out of this hostel? The largest level group stays here.')) {
			return;
		}
		var $btn = $(this).prop('disabled', true);
		$.post('<?= base_url('hostel_unmix'); ?>', {
			hostel_id: currentHostelId,
			year: yearId()
		}).done(function (res) {
			if (res.error && !(res.moved > 0)) {
				toast(res.error, false);
				$btn.prop('disabled', false);
				return;
			}
			var msg = res.success || 'Relocation finished.';
			if (res.errors && res.errors.length) {
				msg += ' ' + res.errors.slice(0, 3).join(' | ');
			}
			toast(msg, !(res.skipped > 0));
			setTimeout(reloadPage, 900);
		}).fail(function () {
			toast('Relocation failed.', false);
			$btn.prop('disabled', false);
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
