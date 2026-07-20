<?php
/**
 * Analyse Curriculum & Chronogram
 * @var array $classes
 * @var array $pedagogical_docs
 * @var array $analysis_cache
 * @var bool $gemini_ready
 */
$byClassDocs = [];
foreach ($pedagogical_docs ?? [] as $d) {
	$cid = (int) $d['class_id'];
	if (!isset($byClassDocs[$cid])) {
		$byClassDocs[$cid] = ['curriculum' => false, 'chronogram' => false];
	}
	$byClassDocs[$cid][$d['doc_type']] = true;
}
include __DIR__ . '/_nav.php';
?>
<style>
	.aiplan-wrap { max-width: 1180px; }
	.aiplan-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.15rem; margin-bottom:1rem; }
	.aiplan-card h5 { font-weight:700; margin:0 0 .75rem; }
	.aiplan-status { font-size:.85rem; color:#64748b; }
	.aiplan-mod { border:1px solid #e2e8f0; border-radius:10px; padding:.75rem .9rem; margin-bottom:.55rem; }
	.aiplan-mod.is-matched { border-color:#86efac; background:#f0fdf4; }
	.aiplan-mod.is-unmatched { border-color:#fcd34d; background:#fffbeb; }
	.aiplan-mod.is-extracting { border-color:#14b8a6; background:#f0fdfa; box-shadow:0 0 0 2px rgba(20,184,166,.25); }
	.aiplan-mod.is-pending { opacity:.72; }
	.aiplan-badge { display:inline-block; font-size:.72rem; font-weight:700; padding:.15rem .45rem; border-radius:999px; background:#e0f2fe; color:#0369a1; margin-right:.25rem; }
	.aiplan-badge.ok { background:#dcfce7; color:#15803d; }
	.aiplan-badge.warn { background:#fef3c7; color:#b45309; }
	#aiplanModules { max-height:460px; overflow:auto; }
	.aiplan-progress {
		display:none; margin-top:1rem; padding:1rem 1.1rem; border-radius:12px;
		background:linear-gradient(135deg,#f0fdfa 0%,#ecfeff 100%);
		border:1px solid #99f6e4;
	}
	.aiplan-progress.is-on { display:block; }
	.aiplan-progress-head {
		display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap;
		margin-bottom:.65rem;
	}
	.aiplan-progress-title { font-weight:700; color:#0f766e; font-size:.95rem; }
	.aiplan-progress-pct {
		font-weight:800; font-size:1.35rem; color:#0f766e; font-variant-numeric:tabular-nums;
		min-width:3.5rem; text-align:right;
	}
	.aiplan-bar {
		height:12px; background:#ccfbf1; border-radius:999px; overflow:hidden; position:relative;
	}
	.aiplan-bar > span {
		display:block; height:100%; width:0%; border-radius:999px;
		background:linear-gradient(90deg,#14b8a6,#0ea5e9);
		transition:width .45s ease;
		position:relative;
	}
	.aiplan-bar > span::after {
		content:""; position:absolute; inset:0;
		background:linear-gradient(90deg,transparent,rgba(255,255,255,.35),transparent);
		animation:aiplan-shine 1.4s linear infinite;
	}
	@keyframes aiplan-shine { from { transform:translateX(-100%); } to { transform:translateX(100%); } }
	.aiplan-action {
		margin-top:.7rem; font-size:.88rem; color:#134e4a; font-weight:600;
		display:flex; align-items:flex-start; gap:.45rem; min-height:1.4em;
	}
	.aiplan-action .fa-spinner { color:#0d9488; margin-top:.15rem; }
	.aiplan-steps {
		display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.75rem;
	}
	.aiplan-step {
		font-size:.72rem; font-weight:700; padding:.2rem .5rem; border-radius:999px;
		background:#fff; border:1px solid #99f6e4; color:#5eead4;
	}
	.aiplan-step.is-done { background:#dcfce7; border-color:#86efac; color:#15803d; }
	.aiplan-step.is-active { background:#ccfbf1; border-color:#14b8a6; color:#0f766e; }
	.aiplan-step.is-todo { color:#94a3b8; border-color:#e2e8f0; background:#f8fafc; }
	.aiplan-class-list {
		max-height: 280px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 10px;
		background: #f8fafc; padding: .4rem .55rem;
	}
	.aiplan-class-row {
		display: flex; align-items: flex-start; gap: .55rem; padding: .4rem .35rem;
		border-radius: 8px; margin-bottom: .15rem; cursor: pointer;
	}
	.aiplan-class-row:hover { background: #fff; }
	.aiplan-class-row.is-disabled { opacity: .55; cursor: not-allowed; }
	.aiplan-class-row input { margin-top: .2rem; }
	.aiplan-class-meta { font-size: .78rem; color: #64748b; }
	.aiplan-job-list { margin-top: .75rem; font-size: .84rem; }
	.aiplan-job-row {
		display: flex; justify-content: space-between; gap: .5rem; flex-wrap: wrap;
		padding: .35rem .5rem; border-bottom: 1px solid #e2e8f0;
	}
	.aiplan-job-row .st-pending { color: #64748b; }
	.aiplan-job-row .st-running { color: #0f766e; font-weight: 700; }
	.aiplan-job-row .st-done { color: #15803d; }
	.aiplan-job-row .st-skipped { color: #b45309; }
	.aiplan-job-row .st-error { color: #b91c1c; }
</style>

<div class="aiplan-wrap">
	<?php if (empty($gemini_ready)): ?>
		<div class="alert alert-warning">Gemini API key is not configured (<code>GOOGLE_AI_API_KEY</code>).</div>
	<?php endif; ?>

	<div class="aiplan-card">
		<h5>Select class(es) &amp; analyse</h5>
		<p class="text-muted" style="font-size:.88rem;">
			Uses <b>all</b> curriculum + chronogram files from School Settings.
			Upload the full RTB package: <b>General Information</b> + <b>Specific Modules</b> + <b>General Modules</b> + <b>CCM Modules</b>
			(or one ZIP of that folder), plus chronogram.
			Select <b>multiple classes</b> — analysis runs <b>one-by-one in the background</b>; classes missing uploads are skipped automatically.
		</p>
		<div class="row">
			<div class="col-md-8">
				<div class="d-flex justify-content-between align-items-center mb-1" style="gap:.5rem;flex-wrap:wrap;">
					<label class="mb-0" style="font-weight:600;">Classes</label>
					<span>
						<button type="button" class="btn btn-link btn-sm p-0" id="btnSelectReady">Select ready</button>
						·
						<button type="button" class="btn btn-link btn-sm p-0" id="btnClearClasses">Clear</button>
					</span>
				</div>
				<div class="aiplan-class-list" id="aiplanClassList">
					<?php foreach ($classes as $c):
						$cid = (int) $c['id'];
						$docs = $byClassDocs[$cid] ?? ['curriculum' => false, 'chronogram' => false];
						$prog = ((int)($c['faculty_type'] ?? 1) === 2) ? 'REB' : 'TVET';
						$label = trim(($c['level_name'] ?? '') . ' ' . ($c['dept_code'] ?? '') . ' ' . ($c['title'] ?? ''));
						$ready = !empty($docs['curriculum']) && !empty($docs['chronogram']);
						$cache = $analysis_cache[$cid] ?? null;
						$cacheFlag = !empty($cache['has_cache']) ? ('Cached ' . (int)($cache['module_count'] ?? 0) . ' modules') : 'No cache';
						$flags = ($docs['curriculum'] ? 'Curriculum✓' : 'Curriculum✗') . ' · ' . ($docs['chronogram'] ? 'Chronogram✓' : 'Chronogram✗');
						?>
						<label class="aiplan-class-row<?= $ready ? '' : ' is-disabled'; ?>"
							   data-class-id="<?= $cid; ?>"
							   data-ready="<?= $ready ? '1' : '0'; ?>"
							   data-has-cache="<?= !empty($cache['has_cache']) ? '1' : '0'; ?>"
							   data-label="<?= esc($label); ?>">
							<input type="checkbox"
								   class="aiplan-class-check"
								   value="<?= $cid; ?>">
							<span>
								<strong><?= esc($label); ?></strong> <span class="text-muted">(<?= $prog; ?>)</span>
								<div class="aiplan-class-meta"><?= esc($flags); ?> · <?= esc($cacheFlag); ?><?= $ready ? '' : ' — will be skipped if queued'; ?></div>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="col-md-4">
				<button type="button" class="btn btn-primary btn-block" id="btnAnalyzeCurriculum" <?= empty($gemini_ready) ? 'disabled' : ''; ?>>
					<i class="fa fa-database"></i> Queue Load / Analyse
				</button>
				<button type="button" class="btn btn-outline-secondary btn-sm btn-block mt-1" id="btnForceAnalyze" <?= empty($gemini_ready) ? 'disabled' : ''; ?>>
					Queue Re-analyse with AI
				</button>
				<p class="text-muted mt-2 mb-0" style="font-size:.78rem;">
					Jobs run in the background — you can leave this page and keep working.
				</p>
			</div>
		</div>
		<div id="aiplanJobBox" class="aiplan-job-list" style="display:none;"></div>
		<p class="aiplan-status mt-2 mb-0" id="aiplanStatus">If analysis was already done, it loads from DB without calling Gemini.</p>

		<div class="aiplan-progress" id="aiplanProgress" aria-live="polite">
			<div class="aiplan-progress-head">
				<div class="aiplan-progress-title"><i class="fa fa-magic"></i> Smart analysis in progress</div>
				<div class="aiplan-progress-pct" id="aiplanPct">0%</div>
			</div>
			<div class="aiplan-bar"><span id="aiplanBar"></span></div>
			<div class="aiplan-action">
				<i class="fa fa-spinner fa-spin" id="aiplanSpinIcon"></i>
				<span id="aiplanAction">Starting…</span>
			</div>
			<div class="aiplan-steps" id="aiplanSteps">
				<span class="aiplan-step is-todo" data-step="read">1. Read files</span>
				<span class="aiplan-step is-todo" data-step="inventory">2. Inventory</span>
				<span class="aiplan-step is-todo" data-step="loic">3. LO / IC</span>
				<span class="aiplan-step is-todo" data-step="chrono">4. Chronogram hours</span>
				<span class="aiplan-step is-todo" data-step="save">5. Save</span>
			</div>
		</div>
	</div>

	<div class="aiplan-card" id="aiplanResultCard" style="display:none;">
		<h5>Extracted courses / modules <span id="aiplanProgBadge" class="aiplan-badge"></span></h5>
		<div id="aiplanMeta" class="text-muted mb-2" style="font-size:.88rem;"></div>
		<div id="aiplanModules"></div>
		<a class="btn btn-info mt-2" href="<?= base_url('ped_scheme_of_work'); ?>"><i class="fa fa-arrow-right"></i> Continue to Scheme of Work</a>
	</div>
</div>

<script>
(function ($) {
	var analysis = null;
	var progressTimer = null;
	var analyzing = false;
	var liveCurrentModule = '';
	var resumeAttempts = 0;

	function status(msg, err) { $('#aiplanStatus').css('color', err ? '#b91c1c' : '#64748b').text(msg || ''); }
	var batchId = null;
	var batchTimer = null;
	var classLabels = {};
	$('.aiplan-class-row').each(function () {
		classLabels[String($(this).data('class-id'))] = $(this).data('label') || ('Class ' + $(this).data('class-id'));
	});

	function selectedClassIds() {
		var ids = [];
		$('.aiplan-class-check:checked').each(function () {
			var id = parseInt($(this).val(), 10) || 0;
			if (id) ids.push(id);
		});
		return ids;
	}

	function applyProgressPoll(p) {
		if (!p) return;
		setProgress(p.pct || 0, p.action || 'Working…');
		if (p.status === 'error' && p.action) {
			status(p.action, true);
		}
		if (p.current_module) {
			liveCurrentModule = String(p.current_module).toUpperCase();
		}
		if (p.partial_analysis && p.partial_analysis.modules && p.partial_analysis.modules.length) {
			analysis = p.partial_analysis;
			renderModules(true);
		}
	}

	function setProgress(pct, action) {
		pct = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
		$('#aiplanPct').text(pct + '%');
		$('#aiplanBar').css('width', pct + '%');
		if (action) $('#aiplanAction').text(action);
		var step = 'read';
		if (pct >= 96) step = 'save';
		else if (pct >= 78) step = 'chrono';
		else if (pct >= 28) step = 'loic';
		else if (pct >= 18) step = 'inventory';
		else if (pct <= 0) step = 'read';
		var order = ['read', 'inventory', 'loic', 'chrono', 'save'];
		var idx = order.indexOf(step);
		$('#aiplanSteps .aiplan-step').each(function () {
			var s = $(this).data('step');
			var si = order.indexOf(s);
			$(this).removeClass('is-todo is-active is-done');
			if (si < idx) $(this).addClass('is-done');
			else if (si === idx) $(this).addClass('is-active');
			else $(this).addClass('is-todo');
		});
		if (pct >= 100) {
			$('#aiplanSteps .aiplan-step').removeClass('is-todo is-active').addClass('is-done');
			$('#aiplanSpinIcon').removeClass('fa-spinner fa-spin').addClass('fa-check');
		} else {
			$('#aiplanSpinIcon').removeClass('fa-check').addClass('fa-spinner fa-spin');
		}
	}

	function showProgress(on) {
		$('#aiplanProgress').toggleClass('is-on', !!on);
		var geminiOff = <?= empty($gemini_ready) ? 'true' : 'false'; ?>;
		$('#btnAnalyzeCurriculum,#btnForceAnalyze').prop('disabled', !!on || geminiOff);
		if (!on) {
			stopProgressPoll();
		}
	}

	function stopProgressPoll() {
		if (progressTimer) { clearInterval(progressTimer); progressTimer = null; }
	}

	function stopBatchPoll() {
		if (batchTimer) { clearInterval(batchTimer); batchTimer = null; }
	}

	function startProgressPoll(cid) {
		stopProgressPoll();
		liveCurrentModule = '';
		$.getJSON('<?= base_url('ai_analyze_progress'); ?>', { class_id: cid })
			.done(function (p) {
				if (!p) return;
				if (p.status === 'idle' && !(p.pct > 0)) return;
				applyProgressPoll(p);
			});
		progressTimer = setInterval(function () {
			$.getJSON('<?= base_url('ai_analyze_progress'); ?>', { class_id: cid })
				.done(function (p) {
					applyProgressPoll(p);
				});
		}, 800);
	}

	function renderJobBox(jobs, counts, done) {
		var $box = $('#aiplanJobBox').show().empty();
		var head = 'Batch: ' + (counts.done || 0) + ' done · '
			+ (counts.running || 0) + ' running · '
			+ (counts.pending || 0) + ' pending · '
			+ (counts.skipped || 0) + ' skipped · '
			+ (counts.error || 0) + ' error';
		$box.append($('<div style="font-weight:700;margin-bottom:.35rem;"></div>').text(head));
		(jobs || []).forEach(function (j) {
			var label = classLabels[String(j.class_id)] || ('Class ' + j.class_id);
			var st = j.status || '';
			var extra = '';
			if (st === 'skipped' && j.skip_reason) extra = ' — ' + j.skip_reason;
			if (st === 'error' && j.error_text) extra = ' — ' + j.error_text;
			if (st === 'done' && j.result_meta) {
				try {
					var m = typeof j.result_meta === 'string' ? JSON.parse(j.result_meta) : j.result_meta;
					if (m && m.module_count != null) extra = ' — ' + m.module_count + ' modules';
				} catch (e) {}
			}
			$box.append(
				$('<div class="aiplan-job-row"></div>')
					.append($('<span></span>').text(label))
					.append($('<span class="st-' + st + '"></span>').text(st.toUpperCase() + extra))
			);
		});
		if (done) {
			$box.append($('<div class="text-success mt-2" style="font-weight:700;"></div>').text('Batch complete — you can keep working.'));
		}
	}

	function pollBatch() {
		if (!batchId) return;
		$.getJSON('<?= base_url('ai_analyze_queue_status'); ?>', { batch_id: batchId })
			.done(function (res) {
				if (!res || res.error) return;
				renderJobBox(res.jobs || [], res.counts || {}, !!res.done);
				var cur = res.current;
				var prog = res.progress;
				if (cur && cur.class_id) {
					var label = classLabels[String(cur.class_id)] || ('Class ' + cur.class_id);
					showProgress(true);
					if (prog && (prog.pct > 0 || prog.status === 'running')) {
						applyProgressPoll(prog);
						setProgress(prog.pct || 0, (prog.action || 'Working…') + ' — ' + label);
					} else {
						setProgress(5, 'Background job running: ' + label);
					}
					if (prog && prog.partial_analysis && prog.partial_analysis.modules) {
						analysis = prog.partial_analysis;
						renderModules(true);
					}
				}
				var counts = res.counts || {};
				status('Background queue: ' + (counts.done || 0) + '/' + (res.total || 0) + ' finished'
					+ ((counts.skipped || 0) ? (', ' + counts.skipped + ' skipped') : '')
					+ ((counts.error || 0) ? (', ' + counts.error + ' failed') : '') + '.');
				if (res.done) {
					analyzing = false;
					stopBatchPoll();
					stopProgressPoll();
					setProgress(100, 'All queued classes finished');
					status('Batch complete. Open a class cache / Scheme of Work when ready.');
					setTimeout(function () { showProgress(false); }, 1200);
					// Mark caches for done jobs
					(res.jobs || []).forEach(function (j) {
						if (j.status === 'done') {
							$('.aiplan-class-row[data-class-id="' + j.class_id + '"]').attr('data-has-cache', '1').data('has-cache', 1);
						}
					});
				}
			});
	}

	function queueAnalyze(force) {
		var ids = selectedClassIds();
		if (!ids.length) {
			// Also allow selecting disabled? No — user must pick ready ones, or we queue all checked including none
			status('Select at least one class with Curriculum✓ and Chronogram✓.', true);
			return;
		}
		if (analyzing) return;
		analyzing = true;
		analysis = { modules: [], program_type: 'tvet', _partial: true };
		renderModules(true);
		showProgress(true);
		setProgress(2, force ? 'Queuing re-analyse jobs…' : 'Queuing analyse jobs…');
		status('Queuing ' + ids.length + ' class(es) for background analysis…');

		$.ajax({
			url: '<?= base_url('ai_analyze_queue'); ?>',
			type: 'POST',
			dataType: 'json',
			data: { class_ids: JSON.stringify(ids), force: force ? 1 : 0 }
		}).done(function (res) {
			if (res && res.error) {
				analyzing = false;
				showProgress(false);
				status(res.error, true);
				return;
			}
			batchId = res.batch_id;
			renderJobBox(res.jobs || [], {
				pending: res.queued || 0,
				skipped: res.skipped || 0,
				running: 0, done: 0, error: 0
			}, false);
			status(res.success || 'Queued. Running in background…');
			setProgress(5, 'Background worker started — processing one class at a time');
			stopBatchPoll();
			pollBatch();
			batchTimer = setInterval(pollBatch, 2000);
		}).fail(function (xhr) {
			analyzing = false;
			showProgress(false);
			var msg = (xhr.responseJSON && xhr.responseJSON.error) || ('Queue failed (HTTP ' + (xhr.status || '?') + ')');
			status(msg, true);
		});
	}

	function renderModules(live) {
		live = !!live;
		var $box = $('#aiplanModules').empty();
		if (!analysis || !analysis.modules || !analysis.modules.length) {
			$box.html('<p class="text-muted">' + (live ? 'Waiting for modules…' : 'No modules extracted.') + '</p>');
			$('#aiplanResultCard').show();
			return;
		}
		$('#aiplanProgBadge').text((analysis.program_type || '').toUpperCase());
		var hd = analysis.hours_distribution || {};
		var weeks = (analysis.chronogram && analysis.chronogram.weeks) ? analysis.chronogram.weeks.length : (hd.total_weeks || 0);
		var slotsMods = hd.modules_with_slots != null ? hd.modules_with_slots : null;
		var metaParts = [
			analysis.qualification_title || '',
			analysis.sector ? ('Sector: ' + analysis.sector) : '',
			analysis.trade ? ('Trade: ' + analysis.trade) : '',
			(analysis.source_files && analysis.source_files.length) ? (analysis.source_files.length + ' source file(s)') : '',
			weeks ? ('<span class="aiplan-badge ok">Chronogram: ' + weeks + ' week(s)</span>') : '',
			slotsMods != null ? ('<span class="aiplan-badge ok">' + slotsMods + ' module(s) with weekly hours</span>') : '',
			live ? ('<span class="aiplan-badge ok">Live extract: ' + analysis.modules.length + ' module(s)</span>') : ''
		].filter(Boolean);
		$('#aiplanMeta').html(metaParts.join(' · '));
		analysis.modules.forEach(function (m) {
			var code = String(m.code || '').toUpperCase();
			var matched = !!m.matched_course_id;
			var los = (m.learning_outcomes || []);
			var icCount = 0;
			los.forEach(function (lo) { icCount += (lo.indicative_contents || []).length; });
			var isExtracting = live && liveCurrentModule && code === liveCurrentModule;
			var isPending = live && !isExtracting && los.length === 0 && icCount === 0 && !m.hours_per_week && !(m.chronogram_slots || []).length;
			var $row = $('<div class="aiplan-mod"></div>');
			if (isExtracting) $row.addClass('is-extracting');
			else if (isPending) $row.addClass('is-pending');
			else $row.addClass(matched ? 'is-matched' : 'is-unmatched');
			var badges = '';
			if (m.code) badges += '<span class="aiplan-badge">' + $('<div>').text(m.code).html() + '</span>';
			if (m.rqf_level) badges += '<span class="aiplan-badge">RQF ' + m.rqf_level + '</span>';
			badges += matched ? '<span class="aiplan-badge ok">DB matched</span>' : '<span class="aiplan-badge warn">No DB match</span>';
			if (m.teacher_name) badges += '<span class="aiplan-badge ok">Teacher: ' + $('<div>').text(m.teacher_name).html() + '</span>';
			var slots = m.chronogram_slots || [];
			if (slots.length) {
				var wh = m.weekly_hours_total != null ? m.weekly_hours_total : slots.reduce(function (a, s) {
					return a + (parseFloat(s.hours || s.periods || 0) || 0);
				}, 0);
				wh = Math.round((parseFloat(wh) || 0) * 10) / 10;
				badges += '<span class="aiplan-badge ok">' + slots.length + ' weeks · ' + wh + 'h</span>';
			} else if (m.hours_per_week) {
				badges += '<span class="aiplan-badge ok">' + m.hours_per_week + 'h / week</span>';
			} else if (m.learning_hours) {
				badges += '<span class="aiplan-badge ok">' + m.learning_hours + 'h total</span>';
			} else {
				badges += '<span class="aiplan-badge warn">No chronogram hours</span>';
			}
			if (isExtracting) badges += '<span class="aiplan-badge ok">Extracting…</span>';
			var hoursLabel = m.learning_hours != null ? (Math.round((parseFloat(m.learning_hours) || 0) * 10) / 10) : '';
			$row.append('<div style="font-weight:700;">' + $('<div>').text(m.title || m.code || 'Untitled').html() + '</div>');
			$row.append('<div style="margin-top:.35rem;">' + badges + '</div>');
			$row.append('<div class="text-muted" style="font-size:.8rem;margin-top:.25rem;">LO: ' + los.length + ' · IC: ' + icCount + (hoursLabel !== '' ? (' · Hours: ' + hoursLabel) : '') + (m.credits ? (' · Credits: ' + m.credits) : '') + '</div>');
			if (slots.length) {
				var $chr = $('<details style="margin-top:.35rem;font-size:.8rem;"></details>');
				$chr.append('<summary style="cursor:pointer;color:#0f766e;">Weekly hours from chronogram</summary>');
				var $tbl = $('<table class="table table-sm table-bordered" style="margin:.4rem 0 0;font-size:.78rem;"><thead><tr><th>Week</th><th>Term</th><th>Dates</th><th>Hours</th></tr></thead><tbody></tbody></table>');
				slots.forEach(function (s) {
					var dates = [(s.date_from || ''), (s.date_to || '')].filter(Boolean).join(' – ');
					$tbl.find('tbody').append(
						'<tr><td>' + (s.week || '') + '</td><td>' + (s.term || '') + '</td><td>' + $('<div>').text(dates).html() + '</td><td>' + (s.hours != null ? s.hours : (s.periods || '')) + '</td></tr>'
					);
				});
				$chr.append($tbl);
				$row.append($chr);
			}
			if (los.length) {
				var $details = $('<details style="margin-top:.45rem;font-size:.82rem;"></details>');
				$details.append('<summary style="cursor:pointer;color:#0369a1;">Show curriculum content (LO / IC)</summary>');
				var $ul = $('<ul style="margin:.4rem 0 0 1rem;padding:0;"></ul>');
				los.forEach(function (lo) {
					var loLabel = ((lo.code || '') + ' ' + (lo.title || '')).trim();
					var $li = $('<li style="margin-bottom:.35rem;"></li>').append($('<strong></strong>').text(loLabel || 'Learning outcome'));
					var ics = lo.indicative_contents || [];
					if (ics.length) {
						var $icu = $('<ul style="margin:.2rem 0 0 .9rem;"></ul>');
						ics.forEach(function (ic) {
							var t = ((ic.code || '') + ' ' + (ic.title || '')).trim();
							if (ic.hours) t += ' (' + ic.hours + 'h)';
							$icu.append($('<li></li>').text(t));
						});
						$li.append($icu);
					}
					$ul.append($li);
				});
				$details.append($ul);
				$row.append($details);
			} else if (!isPending) {
				$row.append('<div class="text-warning" style="font-size:.8rem;margin-top:.35rem;">No LO/IC detail yet — upload the module curriculum PDFs (General + Specific) in School Settings and Re-analyse.</div>');
			}
			$box.append($row);
		});
		$('#aiplanResultCard').show();
	}

	$('#btnSelectReady').on('click', function () {
		$('.aiplan-class-check').each(function () {
			var $row = $(this).closest('.aiplan-class-row');
			$(this).prop('checked', $row.data('ready') == '1');
		});
	});
	$('#btnClearClasses').on('click', function () {
		$('.aiplan-class-check').prop('checked', false);
	});
	$('#btnAnalyzeCurriculum').on('click', function () { queueAnalyze(false); });
	$('#btnForceAnalyze').on('click', function () {
		if (!confirm('Queue re-analyse for selected classes? This runs in the background one-by-one and may take a while.')) return;
		queueAnalyze(true);
	});
})(jQuery);
</script>
