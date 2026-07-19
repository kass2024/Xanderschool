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
</style>

<div class="aiplan-wrap">
	<?php if (empty($gemini_ready)): ?>
		<div class="alert alert-warning">Gemini API key is not configured (<code>GOOGLE_AI_API_KEY</code>).</div>
	<?php endif; ?>

	<div class="aiplan-card">
		<h5>Select class &amp; analyse</h5>
		<p class="text-muted" style="font-size:.88rem;">
			Uses all curriculum + chronogram files from School Settings. Upload multiple PDFs per class
			(General + Specific module files, plus chronogram). After uploading, click <b>Re-analyse</b>.
		</p>
		<div class="row">
			<div class="col-md-8">
				<select id="aiplanClass" class="form-control">
					<option value="">— Choose class —</option>
					<?php foreach ($classes as $c):
						$cid = (int) $c['id'];
						$docs = $byClassDocs[$cid] ?? ['curriculum' => false, 'chronogram' => false];
						$prog = ((int)($c['faculty_type'] ?? 1) === 2) ? 'REB' : 'TVET';
						$label = trim(($c['level_name'] ?? '') . ' ' . ($c['dept_code'] ?? '') . ' ' . ($c['title'] ?? ''));
						$flags = ($docs['curriculum'] ? 'Curriculum✓' : 'Curriculum✗') . ' · ' . ($docs['chronogram'] ? 'Chronogram✓' : 'Chronogram✗');
						$cache = $analysis_cache[$cid] ?? null;
						$cacheFlag = !empty($cache['has_cache']) ? (' · Cached ' . (int)($cache['module_count'] ?? 0) . ' modules') : '';
						?>
						<option value="<?= $cid; ?>"
								data-has-cur="<?= $docs['curriculum'] ? '1' : '0'; ?>"
								data-has-chr="<?= $docs['chronogram'] ? '1' : '0'; ?>"
								data-has-cache="<?= !empty($cache['has_cache']) ? '1' : '0'; ?>">
							<?= esc($label); ?> (<?= $prog; ?>) — <?= $flags; ?><?= esc($cacheFlag); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-4">
				<button type="button" class="btn btn-primary btn-block" id="btnAnalyzeCurriculum" <?= empty($gemini_ready) ? 'disabled' : ''; ?>>
					<i class="fa fa-database"></i> Load / Analyse (DB cache)
				</button>
				<button type="button" class="btn btn-outline-secondary btn-sm btn-block mt-1" id="btnForceAnalyze" <?= empty($gemini_ready) ? 'disabled' : ''; ?>>
					Re-analyse with AI
				</button>
			</div>
		</div>
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

	function status(msg, err) { $('#aiplanStatus').css('color', err ? '#b91c1c' : '#64748b').text(msg || ''); }
	function currentClassId() { return parseInt($('#aiplanClass').val() || '0', 10) || 0; }

	function setProgress(pct, action) {
		pct = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
		$('#aiplanPct').text(pct + '%');
		$('#aiplanBar').css('width', pct + '%');
		if (action) $('#aiplanAction').text(action);
		var step = 'read';
		if (pct >= 96) step = 'save';
		else if (pct >= 78) step = 'chrono';
		else if (pct >= 35) step = 'loic';
		else if (pct >= 22) step = 'inventory';
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
		$('#btnAnalyzeCurriculum,#btnForceAnalyze').prop('disabled', !!on || <?= empty($gemini_ready) ? 'true' : 'false'; ?>);
		if (!on) {
			stopProgressPoll();
		}
	}

	function stopProgressPoll() {
		if (progressTimer) { clearInterval(progressTimer); progressTimer = null; }
	}

	function startProgressPoll(cid) {
		stopProgressPoll();
		progressTimer = setInterval(function () {
			$.getJSON('<?= base_url('ai_analyze_progress'); ?>', { class_id: cid })
				.done(function (p) {
					if (!p) return;
					setProgress(p.pct || 0, p.action || 'Working…');
					if (p.status === 'error' && p.action) {
						status(p.action, true);
					}
				});
		}, 1200);
	}

	function renderModules() {
		var $box = $('#aiplanModules').empty();
		if (!analysis || !analysis.modules || !analysis.modules.length) {
			$box.html('<p class="text-muted">No modules extracted.</p>');
			$('#aiplanResultCard').show();
			return;
		}
		$('#aiplanProgBadge').text((analysis.program_type || '').toUpperCase());
		var hd = analysis.hours_distribution || {};
		var weeks = (analysis.chronogram && analysis.chronogram.weeks) ? analysis.chronogram.weeks.length : (hd.total_weeks || 0);
		var slotsMods = hd.modules_with_slots != null ? hd.modules_with_slots : null;
		$('#aiplanMeta').html([
			analysis.qualification_title || '',
			analysis.sector ? ('Sector: ' + analysis.sector) : '',
			analysis.trade ? ('Trade: ' + analysis.trade) : '',
			(analysis.source_files && analysis.source_files.length) ? (analysis.source_files.length + ' source file(s)') : '',
			weeks ? ('<span class="aiplan-badge ok">Chronogram: ' + weeks + ' week(s)</span>') : '<span class="aiplan-badge warn">Chronogram weeks missing — Re-analyse</span>',
			slotsMods != null ? ('<span class="aiplan-badge ok">' + slotsMods + ' module(s) with weekly hours</span>') : ''
		].filter(Boolean).join(' · '));
		analysis.modules.forEach(function (m) {
			var matched = !!m.matched_course_id;
			var $row = $('<div class="aiplan-mod"></div>').addClass(matched ? 'is-matched' : 'is-unmatched');
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
				badges += '<span class="aiplan-badge ok">' + slots.length + ' weeks · ' + wh + 'h</span>';
			} else {
				badges += '<span class="aiplan-badge warn">No chronogram hours</span>';
			}
			var los = (m.learning_outcomes || []);
			var icCount = 0;
			los.forEach(function (lo) { icCount += (lo.indicative_contents || []).length; });
			$row.append('<div style="font-weight:700;">' + $('<div>').text(m.title || 'Untitled').html() + '</div>');
			$row.append('<div style="margin-top:.35rem;">' + badges + '</div>');
			$row.append('<div class="text-muted" style="font-size:.8rem;margin-top:.25rem;">LO: ' + los.length + ' · IC: ' + icCount + (m.learning_hours ? (' · Hours: ' + m.learning_hours) : '') + (m.credits ? (' · Credits: ' + m.credits) : '') + '</div>');
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
			} else {
				$row.append('<div class="text-warning" style="font-size:.8rem;margin-top:.35rem;">No LO/IC detail yet — upload the module curriculum PDFs (General + Specific) in School Settings and Re-analyse.</div>');
			}
			$box.append($row);
		});
		$('#aiplanResultCard').show();
	}

	function analyze(force) {
		var cid = currentClassId();
		if (!cid) { status('Select a class first', true); return; }
		if (analyzing) return;
		var $opt = $('#aiplanClass option:selected');
		if ($opt.data('has-cur') != '1') { status('Upload curriculum in School Settings first.', true); return; }
		if ($opt.data('has-chr') != '1') { status('Upload chronogram in School Settings first.', true); return; }

		analyzing = true;
		showProgress(true);
		setProgress(force ? 2 : 5, force ? 'Re-analysing with AI…' : 'Loading cache / preparing analysis…');
		status(force ? 'Smart analysis running — please keep this page open.' : 'Loading…');
		startProgressPoll(cid);

		$.ajax({
			url: '<?= base_url('ai_analyze_curriculum'); ?>',
			type: 'POST',
			dataType: 'json',
			timeout: 1200000,
			data: { class_id: cid, force: force ? 1 : 0 }
		}).done(function (res) {
			analyzing = false;
			stopProgressPoll();
			if (res && res.error) {
				setProgress(0, res.error);
				showProgress(false);
				status(res.error, true);
				return;
			}
			setProgress(100, res.cached ? 'Loaded from database cache' : 'Analysis saved successfully');
			analysis = res.analysis || null;
			var n = ((analysis && analysis.modules) || []).length;
			var lo = res.lo_count != null ? res.lo_count : null;
			var ic = res.ic_count != null ? res.ic_count : null;
			var weeks = res.chronogram_weeks != null ? res.chronogram_weeks : null;
			var slots = res.chronogram_slots != null ? res.chronogram_slots : null;
			var extra = '';
			if (lo != null) extra += ' · LO ' + lo + ' · IC ' + ic;
			if (weeks != null) extra += ' · Chronogram ' + weeks + ' week(s)';
			if (slots != null) extra += ' · ' + slots + ' week-slots';
			status((res.cached ? 'Loaded from DB cache — ' : 'Full analysis saved to DB — ') + n + ' module(s)' + extra + '.');
			$opt.attr('data-has-cache', '1').data('has-cache', 1);
			renderModules();
			setTimeout(function () { showProgress(false); }, 900);
		}).fail(function (xhr) {
			analyzing = false;
			stopProgressPoll();
			var msg = (xhr.responseJSON && xhr.responseJSON.error)
				|| (xhr.status === 504 || xhr.status === 502 ? 'Server timed out while analysing. Click Re-analyse again — progress is saved where possible.' : null)
				|| (xhr.status === 0 ? 'Connection lost during analysis. Try Re-analyse again.' : null)
				|| ('Analysis failed (HTTP ' + (xhr.status || '?') + ')');
			setProgress(0, msg);
			showProgress(false);
			status(msg, true);
		});
	}

	$('#aiplanClass').on('change', function () {
		$('#aiplanResultCard').hide();
		var $opt = $('#aiplanClass option:selected');
		if (($opt.data('has-cache') == '1') && $opt.val()) analyze(false);
	});
	$('#btnAnalyzeCurriculum').on('click', function () { analyze(false); });
	$('#btnForceAnalyze').on('click', function () {
		if (!confirm('Re-run AI and overwrite cache? This can take several minutes.')) return;
		analyze(true);
	});
})(jQuery);
</script>
