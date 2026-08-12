<link rel="stylesheet" href="<?= base_url('assets/css/timetable.css'); ?>">

<?php
$hasSchedule = !empty($schedule);
$assignCount = (int) ($assignment_count ?? 0);
$testCount = (int) ($test_assignment_count ?? 0);
$stepPeriods = ($period_count ?? 0) > 0;
$stepAssignments = $assignCount > 0;
$stepGenerated = $hasSchedule;
$progressPct = (int) round((($stepPeriods ? 1 : 0) + ($stepAssignments ? 1 : 0) + ($stepGenerated ? 1 : 0)) / 3 * 100);
?>

<div class="tt-dashboard">
	<div class="tt-dash-hero mb-4">
		<div class="tt-dash-hero-text">
			<h4 class="mb-1">Smart Timetable Workspace</h4>
			<p class="mb-0 text-muted">
				<?= esc($academic_year_title ?? ''); ?> · Term <?= (int) ($term ?? 1); ?>
				— full-week grids, special blocks, and teacher schedules
			</p>
		</div>
		<div class="tt-dash-hero-actions">
			<a href="<?= esc($settings_url); ?>" class="btn btn-light btn-sm">
				<i class="fa fa-clock-o"></i> Periods &amp; special times
			</a>
		</div>
	</div>

	<div class="row tt-stat-row mb-4">
		<div class="col-6 col-md-3 mb-3 mb-md-0">
			<div class="tt-stat-card">
				<div class="tt-stat-val"><?= (int) ($class_count ?? 0); ?></div>
				<div class="tt-stat-lbl">Classes</div>
			</div>
		</div>
		<div class="col-6 col-md-3 mb-3 mb-md-0">
			<div class="tt-stat-card">
				<div class="tt-stat-val"><?= (int) ($staff_with_timetable ?? 0); ?><span class="tt-stat-of">/<?= (int) ($staff_count ?? 0); ?></span></div>
				<div class="tt-stat-lbl">Staff on timetable</div>
			</div>
		</div>
		<div class="col-6 col-md-3 mb-3 mb-md-0">
			<div class="tt-stat-card">
				<div class="tt-stat-val"><?= $assignCount; ?></div>
				<div class="tt-stat-lbl">Course assignments</div>
			</div>
		</div>
		<div class="col-6 col-md-3">
			<div class="tt-stat-card <?= $hasSchedule ? 'is-ok' : 'is-warn'; ?>">
				<div class="tt-stat-val"><?= $hasSchedule ? (int) ($entry_count ?? 0) : '—'; ?></div>
				<div class="tt-stat-lbl"><?= $hasSchedule ? 'Lesson slots' : 'Not generated'; ?></div>
			</div>
		</div>
	</div>

	<div class="tt-steps card mb-4">
		<div class="card-body py-3">
			<div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
				<strong>Setup progress</strong>
				<span class="badge badge-<?= $progressPct >= 100 ? 'success' : 'primary'; ?>"><?= $progressPct; ?>%</span>
			</div>
			<div class="progress mb-3" style="height:6px;">
				<div class="progress-bar bg-primary" style="width:<?= $progressPct; ?>%;"></div>
			</div>
			<div class="row tt-step-list">
				<div class="col-md-4 tt-step-item <?= $stepPeriods ? 'done' : ''; ?>">
					<span class="tt-step-num">1</span>
					<div>
						<strong>Day structure</strong>
						<div class="small text-muted"><?= (int) ($period_count ?? 0); ?> periods · <?= (int) ($special_count ?? 0); ?> special times</div>
					</div>
				</div>
				<div class="col-md-4 tt-step-item <?= $stepAssignments ? 'done' : ''; ?>">
					<span class="tt-step-num">2</span>
					<div>
						<strong>Course assignments</strong>
						<div class="small text-muted"><?= $assignCount; ?> linked to classes &amp; staff</div>
					</div>
				</div>
				<div class="col-md-4 tt-step-item <?= $stepGenerated ? 'done' : ''; ?>">
					<span class="tt-step-num">3</span>
					<div>
						<strong>Generate &amp; preview</strong>
						<div class="small text-muted"><?= $hasSchedule ? esc($schedule['generated_at'] ?? 'Ready') : 'Waiting'; ?></div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row mb-4">
		<div class="col-lg-4 mb-3 mb-lg-0">
			<div class="card tt-gen-card h-100">
				<div class="card-header bg-primary text-white"><strong><i class="fa fa-magic"></i> Generate timetable</strong></div>
				<div class="card-body">
					<?php if (!$stepAssignments): ?>
						<div class="alert alert-warning py-2 small mb-3">
							No course assignments for this term. Assign courses under <strong>Manage Course</strong>, or ask admin to run the test seed script.
						</div>
					<?php endif; ?>
					<div class="form-check mb-3">
						<input type="checkbox" class="form-check-input" id="useAiTips" checked>
						<label class="form-check-label" for="useAiTips">AI tips if conflicts occur</label>
					</div>
					<button type="button" class="btn btn-primary btn-lg btn-block" id="btnGenerateTimetable" <?= !$stepAssignments ? 'disabled' : ''; ?>>
						Generate smart timetable
					</button>
					<div id="generateResult" class="mt-3 small"></div>
				</div>
			</div>
		</div>
		<div class="col-lg-8">
			<div class="card h-100">
				<div class="card-header d-flex justify-content-between align-items-center">
					<strong><i class="fa fa-calendar"></i> School day structure</strong>
					<span class="badge badge-secondary"><?= (int) ($period_count ?? 0); ?> teaching periods</span>
				</div>
				<div class="card-body">
					<div class="tt-day-timeline tt-day-timeline-lg">
						<?php foreach ($slots as $slot):
							$isBreak = !empty($slot['is_break']);
							$w = max(8, (int) round((strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 60 / 8));
							?>
							<div class="tt-tl-seg <?= $isBreak ? 'is-break' : 'is-period'; ?>" style="flex:<?= $w; ?>;" title="<?= esc(substr($slot['start_time'], 0, 5) . ' – ' . substr($slot['end_time'], 0, 5)); ?>">
								<span><?= esc($slot['break_label'] ?: $slot['label']); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="row mt-3">
						<div class="col-md-6">
							<div class="small text-muted mb-1">Week columns</div>
							<div class="tt-day-pills">
								<?php foreach ($day_labels ?? ['Mon','Tue','Wed','Thu','Fri'] as $dl): ?>
									<span class="tt-day-pill"><?= esc($dl); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="col-md-6">
							<?php if (!empty($special_times)): ?>
								<div class="small text-muted mb-1">Special times</div>
								<?php foreach ($special_times as $st): ?>
									<span class="tt-legend-chip tt-special-<?= esc($st['color'] ?? 'yellow'); ?> mr-1 mb-1">
										<?= esc(['Mon','Tue','Wed','Thu','Fri','','Sun'][(int) $st['day_of_week']] ?? '?'); ?>
										<?= esc($st['slot_label'] ?? ''); ?> — <?= esc($st['label']); ?>
									</span>
								<?php endforeach; ?>
							<?php else: ?>
								<div class="small text-muted">No special times yet — add Chapel, Sabbath, etc. in settings.</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php if ($hasSchedule): ?>
	<div class="card mb-4 tt-export-card">
		<div class="card-header"><strong><i class="fa fa-file-pdf-o"></i> Export all timetables (PDF)</strong></div>
		<div class="card-body d-flex flex-wrap align-items-center" style="gap:10px;">
			<a href="<?= site_url('timetable/pdf_all_classes'); ?>" class="btn btn-primary">
				<i class="fa fa-download"></i> All class timetables (<?= (int) ($class_count ?? 0); ?>)
			</a>
			<a href="<?= site_url('timetable/pdf_all_teachers'); ?>" class="btn btn-info">
				<i class="fa fa-download"></i> All teacher / staff timetables (<?= (int) ($staff_count ?? 0); ?>)
			</a>
			<span class="text-muted small">One PDF per export — landscape A4, full week grid with department/combination labels.</span>
		</div>
	</div>
	<?php endif; ?>

	<div class="tt-preview-card">
		<div class="tt-preview-head">
			<div>
				<strong><i class="fa fa-table"></i> Weekly timetable preview</strong>
				<span class="text-muted small ml-1">All days visible</span>
			</div>
			<div class="d-flex flex-wrap align-items-center tt-preview-controls">
				<select id="previewMode" class="form-control form-control-sm">
					<option value="class">Class</option>
					<option value="teacher">Teacher</option>
				</select>
				<select id="previewClass" class="form-control form-control-sm tt-preview-entity">
					<?php foreach ($classes as $c): ?>
						<option value="<?= (int) $c['id']; ?>"><?= esc($c['class_label'] ?? (($c['level_name'] ?? '') . ' ' . $c['title'])); ?></option>
					<?php endforeach; ?>
				</select>
				<select id="previewTeacher" class="form-control form-control-sm tt-preview-entity d-none">
					<?php foreach ($staffs as $s): ?>
						<option value="<?= (int) $s['id']; ?>">
							<?= esc($s['fname'] . ' ' . $s['lname']); ?><?= !empty($s['post_title']) ? ' · ' . esc($s['post_title']) : ''; ?>
						</option>
					<?php endforeach; ?>
				</select>
				<a href="#" id="previewOpenFull" class="btn btn-sm btn-info">Full page</a>
				<a href="#" id="previewPrint" class="btn btn-sm btn-outline-secondary">Print PDF</a>
			</div>
		</div>
		<div class="tt-preview-body" id="ttPreviewBody">
			<?php if (!empty($preview_data)): ?>
				<?= view('pages/timetable/_grid_body', $preview_data); ?>
			<?php elseif ($hasSchedule): ?>
				<div class="tt-empty-grid p-4 text-center text-muted">Select a class or teacher above to preview.</div>
			<?php else: ?>
				<div class="tt-empty-state">
					<div class="tt-empty-icon"><i class="fa fa-calendar-o"></i></div>
					<h5>No timetable generated yet</h5>
					<p class="text-muted mb-3">Assign courses to classes, then click <strong>Generate smart timetable</strong> to fill this full-week grid.</p>
					<div class="tt-skeleton-grid">
						<div class="tt-skel-head">
							<?php foreach ($day_labels ?? ['Mon','Tue','Wed','Thu','Fri'] as $dl): ?>
								<span><?= esc($dl); ?></span>
							<?php endforeach; ?>
						</div>
						<?php for ($r = 0; $r < 5; $r++): ?>
							<div class="tt-skel-row"><span></span><span></span><span></span><span></span><span></span></div>
						<?php endfor; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script src="<?= base_url('assets/js/timetable-live-edit.js'); ?>"></script>
<script>
(function () {
	var classBase = '<?= site_url('timetable/class'); ?>';
	var teacherBase = '<?= site_url('timetable/teacher'); ?>';
	var printClassBase = '<?= site_url('timetable/print_class'); ?>';
	var printTeacherBase = '<?= site_url('timetable/print_teacher'); ?>';
	var hasSchedule = <?= $hasSchedule ? 'true' : 'false'; ?>;

	function currentMode() { return $('#previewMode').val(); }
	function currentId() {
		return currentMode() === 'teacher' ? $('#previewTeacher').val() : $('#previewClass').val();
	}

	function syncEntityOptions() {
		var mode = currentMode();
		if (mode === 'teacher') {
			$('#previewClass').addClass('d-none');
			$('#previewTeacher').removeClass('d-none');
		} else {
			$('#previewTeacher').addClass('d-none');
			$('#previewClass').removeClass('d-none');
		}
	}

	function loadPreview() {
		if (!hasSchedule) return;
		var mode = currentMode();
		var id = currentId();
		if (!id) return;
		$('#ttPreviewBody').html('<div class="p-5 text-center"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><div class="mt-2 text-muted">Loading timetable…</div></div>');
		$.getJSON('<?= site_url('timetable/preview'); ?>/' + id + '?mode=' + mode, function (r) {
			if (r.error) {
				$('#ttPreviewBody').html('<div class="alert alert-warning m-3">' + r.error + '</div>');
				return;
			}
			$('#ttPreviewBody').html(r.html || '<div class="alert alert-warning m-3">No preview data</div>');
			if (window.TtLiveEdit) TtLiveEdit.init($('#ttPreviewBody'));
		}).fail(function () {
			$('#ttPreviewBody').html('<div class="alert alert-danger m-3">Could not load preview</div>');
		});
	}

	$('#previewMode').on('change', function () { syncEntityOptions(); loadPreview(); });
	$('#previewClass, #previewTeacher').on('change', loadPreview);

	$('#previewOpenFull').on('click', function (e) {
		e.preventDefault();
		if (!hasSchedule) return;
		window.location = (currentMode() === 'class' ? classBase : teacherBase) + '/' + currentId();
	});
	$('#previewPrint').on('click', function (e) {
		e.preventDefault();
		if (!hasSchedule) return;
		var mode = currentMode(), id = currentId();
		var url = (mode === 'class' ? printClassBase : printTeacherBase) + '/' + id;
		window.location = url;
	});

	$('#btnGenerateTimetable').on('click', function () {
		var $btn = $(this).prop('disabled', true);
		$('#generateResult').html('<span class="text-primary"><i class="fa fa-spinner fa-spin"></i> Generating…</span>');
		$.post('<?= site_url('timetable/generate'); ?>', {
			academic_year: <?= (int) ($academic_year ?? 0); ?>,
			term: <?= (int) ($term ?? 1); ?>,
			use_gemini: $('#useAiTips').is(':checked') ? 1 : 0
		}, function (r) {
			$btn.prop('disabled', false);
			if (r.error) {
				$('#generateResult').html('<div class="alert alert-danger py-2 mb-0">' + r.error + '</div>');
				return;
			}
			var html = '<div class="alert alert-success py-2 mb-0">' + r.success + '</div>';
			if (r.warnings && r.warnings.length) {
				html += '<ul class="text-warning mt-2 mb-0 pl-3 small">' + r.warnings.slice(0, 5).map(function (w) { return '<li>' + w + '</li>'; }).join('') + '</ul>';
			}
			if (r.ai_tip) html += '<div class="alert alert-info mt-2 py-2 small mb-0"><strong>AI tip:</strong> ' + r.ai_tip.replace(/\n/g, '<br>') + '</div>';
			$('#generateResult').html(html);
			setTimeout(function () { location.reload(); }, 1500);
		}, 'json').fail(function () {
			$btn.prop('disabled', false);
			$('#generateResult').html('<div class="alert alert-danger py-2 mb-0">Generation failed — check course assignments.</div>');
		});
	});

	syncEntityOptions();
	if (hasSchedule) loadPreview();
})();
</script>
