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
	<?php if (!empty($timetable_stale) && (empty($active_generation_job) || !in_array((string) ($active_generation_job['status'] ?? ''), ['queued', 'running'], true))): ?>
	<div class="alert alert-warning mb-3">
		<strong>Timetable out of date.</strong>
		Manage Course assignments (teachers, classes, or credits) changed.
		Click a level below (Nursery, Primary, or High school) to rebuild from Manage Course.
	</div>
	<?php elseif (!empty($active_generation_job) && in_array((string) ($active_generation_job['status'] ?? ''), ['queued', 'running'], true)): ?>
	<div class="alert alert-info mb-3">
		<strong>Generating timetable…</strong>
		Nursery, primary, and secondary stages are running. Progress updates below.
	</div>
	<?php endif; ?>
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
		<div class="col-lg-5 mb-3 mb-lg-0">
			<div class="card tt-gen-card tt-gen-card-pro h-100">
				<div class="card-header tt-gen-head">
					<div>
						<strong><i class="fa fa-magic"></i> Generate by level</strong>
						<div class="tt-gen-sub">Nursery · Primary · High school — each builds alone</div>
					</div>
				</div>
				<div class="card-body">
					<?php if (!$stepAssignments): ?>
						<div class="alert alert-warning py-2 small mb-3">
							No course assignments for this term. Assign courses under <strong>Manage Course</strong> first.
						</div>
					<?php endif; ?>

					<div class="tt-level-grid mb-3">
						<?php foreach (($generation_levels ?? []) as $lvl):
							$status = (string) ($lvl['status'] ?? 'pending');
							$disabled = !$stepAssignments || (int) ($lvl['assignments'] ?? 0) <= 0;
							$statusLabel = [
								'generated' => 'Generated',
								'stale' => 'Needs update',
								'pending' => 'Ready',
								'empty' => 'No courses',
							][$status] ?? 'Ready';
							$icon = (string) ($lvl['icon'] ?? 'fa-calendar');
							?>
							<button type="button"
								class="tt-level-tile status-<?= esc($status); ?> btn-generate-level"
								data-phase="<?= esc($lvl['key']); ?>"
								<?= $disabled ? 'disabled' : ''; ?>>
								<span class="tt-level-icon"><i class="fa <?= esc($icon); ?>"></i></span>
								<span class="tt-level-body">
									<span class="tt-level-tile-top">
										<span class="tt-level-name"><?= esc($lvl['label']); ?></span>
										<span class="tt-level-badge"><?= esc($statusLabel); ?></span>
									</span>
									<span class="tt-level-hint"><?= esc($lvl['hint'] ?? ''); ?></span>
									<span class="tt-level-meta">
										<?= (int) ($lvl['classes'] ?? 0); ?> classes · <?= (int) ($lvl['assignments'] ?? 0); ?> courses
										<?php if (!empty($lvl['generated_at']) && $status === 'generated'): ?>
											· <?= esc(date('M j, H:i', strtotime((string) $lvl['generated_at']))); ?>
										<?php endif; ?>
									</span>
								</span>
								<span class="tt-level-cta"><?= $status === 'generated' || $status === 'stale' ? 'Regenerate' : 'Generate'; ?> <i class="fa fa-arrow-right"></i></span>
							</button>
						<?php endforeach; ?>
					</div>

					<div class="tt-gen-options mb-3">
						<label class="tt-ai-toggle mb-0">
							<input type="checkbox" id="useAiTips" checked>
							<span>Gemini collision fix</span>
						</label>
						<button type="button" class="btn btn-primary btn-sm" id="btnGenerateAll" <?= !$stepAssignments ? 'disabled' : ''; ?>>
							Generate all
						</button>
					</div>

					<div id="generateResult" class="tt-gen-result small"></div>
				</div>
			</div>
		</div>
		<div class="col-lg-7">
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
	var jobStatusBase = '<?= site_url('timetable/generate_status'); ?>';
	var hasSchedule = <?= $hasSchedule ? 'true' : 'false'; ?>;
	var activeJob = <?= json_encode($active_generation_job ?? null, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	var pollTimer = null;

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
		$('#ttPreviewBody').html('<div class="p-5 text-center"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i><div class="mt-2 text-muted">Loading timetable...</div></div>');
		$.ajax({
			url: '<?= site_url('timetable/preview'); ?>/' + id + '?mode=' + mode,
			dataType: 'json',
			timeout: 90000
		}).done(function (r) {
			if (!r || r.error) {
				$('#ttPreviewBody').html('<div class="alert alert-warning m-3">' + ((r && r.error) ? r.error : 'No preview data') + '</div>');
				return;
			}
			$('#ttPreviewBody').html(r.html || '<div class="alert alert-warning m-3">No preview data</div>');
			if (r.editable && window.TtLiveEdit) TtLiveEdit.init($('#ttPreviewBody'));
		}).fail(function (xhr) {
			var detail = '';
			if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
				detail = xhr.responseJSON.error;
			} else if (xhr && xhr.status) {
				detail = 'HTTP ' + xhr.status + (xhr.statusText ? ' ' + xhr.statusText : '');
			}
			$('#ttPreviewBody').html('<div class="alert alert-danger m-3">Could not load preview' + (detail ? ': ' + detail : '') + '. Try <strong>Generate smart timetable</strong> again.</div>');
		});
	}

	function stopJobPolling() {
		if (pollTimer) {
			clearTimeout(pollTimer);
			pollTimer = null;
		}
	}

	function setGenerating(busy) {
		$('.btn-generate-level, #btnGenerateAll').prop('disabled', !!busy);
		if (!busy) {
			$('.btn-generate-level').each(function () {
				if ($(this).hasClass('status-empty')) $(this).prop('disabled', true);
			});
		}
	}

	function renderJobState(job) {
		if (!job) return;
		var status = job.status || '';
		var pct = Math.max(0, Math.min(100, parseInt(job.progress, 10) || 0));
		var html = '';
		if (status === 'queued' || status === 'running') {
			setGenerating(true);
			if (status === 'queued' && pct < 1) pct = 1;
			html = '<div class="tt-gen-progress">'
				+ '<div class="d-flex justify-content-between align-items-center mb-1">'
				+ '<span><i class="fa fa-spinner fa-spin text-primary"></i> '
				+ (job.message || 'Generating…') + '</span>'
				+ '<strong>' + pct + '%</strong></div>'
				+ '<div class="progress mb-2" style="height:10px;">'
				+ '<div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" '
				+ 'style="width:' + pct + '%;"></div></div>';
			if (job.stages && job.stages.length) {
				html += '<ul class="list-unstyled mb-0 small tt-gen-stages">';
				job.stages.forEach(function (s) {
					var icon = 'fa-circle-o text-muted';
					var cls = 'text-muted';
					if (s.status === 'running') { icon = 'fa-spinner fa-spin text-primary'; cls = 'text-primary font-weight-bold'; }
					else if (s.status === 'done') { icon = 'fa-check-circle text-success'; cls = 'text-success'; }
					html += '<li class="' + cls + '"><i class="fa ' + icon + '"></i> ' + (s.label || s.key) + '</li>';
				});
				html += '</ul>';
			}
			html += '</div>';
		} else if (status === 'done') {
			setGenerating(false);
			html = '<div class="progress mb-2" style="height:8px;"><div class="progress-bar bg-success" style="width:100%;"></div></div>'
				+ '<div class="alert alert-success py-2 mb-0">' + (job.message || 'Timetable generated.') + '</div>';
			if (job.warnings && job.warnings.length) {
				html += '<ul class="text-warning mt-2 mb-0 pl-3 small">' + job.warnings.slice(0, 5).map(function (w) { return '<li>' + w + '</li>'; }).join('') + '</ul>';
			}
			if (job.ai_tip) html += '<div class="alert alert-info mt-2 py-2 small mb-0"><strong>AI:</strong> ' + String(job.ai_tip).replace(/\n/g, '<br>') + '</div>';
		} else if (status === 'failed') {
			setGenerating(false);
			html = '<div class="alert alert-danger py-2 mb-0">' + (job.message || 'Generation failed.') + '</div>';
		}
		if (html) $('#generateResult').html(html);
	}

	function pollJob(jobId) {
		if (!jobId) return;
		stopJobPolling();
		$.getJSON(jobStatusBase + '/' + encodeURIComponent(jobId), function (job) {
			activeJob = job || null;
			renderJobState(activeJob);
			if (job && (job.status === 'queued' || job.status === 'running')) {
				pollTimer = setTimeout(function () { pollJob(jobId); }, 900);
				return;
			}
			if (job && job.status === 'done') {
				setTimeout(function () { location.reload(); }, 1000);
			}
		}).fail(function () {
			setGenerating(false);
			$('#generateResult').html('<div class="alert alert-danger py-2 mb-0">Could not read timetable job status.</div>');
		});
	}

	function startGenerate(phase) {
		setGenerating(true);
		var label = phase === 'nursery' ? 'Nursery' : (phase === 'primary' ? 'Primary' : (phase === 'high_school' || phase === 'secondary' ? 'High school' : 'All levels'));
		$('#generateResult').html(
			'<div class="tt-gen-progress">'
			+ '<div class="d-flex justify-content-between align-items-center mb-1">'
			+ '<span><i class="fa fa-spinner fa-spin text-primary"></i> Queuing ' + label + '…</span>'
			+ '<strong>3%</strong></div>'
			+ '<div class="progress mb-0" style="height:10px;">'
			+ '<div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:3%;"></div></div></div>'
		);
		$.ajax({
			url: '<?= site_url('timetable/generate'); ?>',
			method: 'POST',
			dataType: 'json',
			timeout: 30000,
			data: {
				academic_year: <?= (int) ($academic_year ?? 0); ?>,
				term: <?= (int) ($term ?? 1); ?>,
				use_gemini: $('#useAiTips').is(':checked') ? 1 : 0,
				phase: phase || 'all'
			}
		}).done(function (r) {
			if (r && r.error) {
				setGenerating(false);
				$('#generateResult').html('<div class="alert alert-danger py-2 mb-0">' + r.error + '</div>');
				return;
			}
			activeJob = r || null;
			if (activeJob && !activeJob.status) activeJob.status = 'queued';
			if (activeJob && !activeJob.progress) activeJob.progress = 3;
			if (activeJob && !activeJob.message) activeJob.message = 'Generating ' + label + '…';
			renderJobState(activeJob);
			if (r && r.job_id) {
				pollJob(r.job_id);
			} else {
				setGenerating(false);
				$('#generateResult').html('<div class="alert alert-danger py-2 mb-0">No generation job was created.</div>');
			}
		}).fail(function (xhr) {
			setGenerating(false);
			var msg = 'Generation failed — check course assignments.';
			if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
				msg = xhr.responseJSON.error;
			} else if (xhr && xhr.statusText === 'timeout') {
				msg = 'Server took too long to queue the job. Refresh and try again.';
			} else if (xhr && xhr.status) {
				msg = 'Generation failed (HTTP ' + xhr.status + '). Please try again.';
			}
			$('#generateResult').html('<div class="alert alert-danger py-2 mb-0">' + msg + '</div>');
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

	$(document).on('click', '.btn-generate-level', function () {
		startGenerate($(this).data('phase') || 'all');
	});
	$('#btnGenerateAll').on('click', function () {
		startGenerate('all');
	});

	syncEntityOptions();
	if (hasSchedule) loadPreview();
	if (activeJob && activeJob.id && (activeJob.status === 'queued' || activeJob.status === 'running')) {
		renderJobState(activeJob);
		pollJob(activeJob.id);
	}
})();
</script>
