<?php
/**
 * Academic AI Plans — Scheme of Work + Session Plan (TVET) / Lesson Plan (REB)
 * @var array $classes
 * @var array $pedagogical_docs
 * @var array $saved_plans
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
$yearTitle = $academic_year_title ?? '';
?>
<style>
	.aiplan-wrap { max-width: 1180px; }
	.aiplan-hero {
		background: linear-gradient(135deg, #0f766e 0%, #0e7490 55%, #1d4ed8 100%);
		color: #fff; border-radius: 14px; padding: 1.25rem 1.4rem; margin-bottom: 1.25rem;
	}
	.aiplan-hero h3 { margin: 0 0 .35rem; font-weight: 700; }
	.aiplan-hero p { margin: 0; opacity: .92; font-size: .92rem; }
	.aiplan-card {
		background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.15rem; margin-bottom: 1rem;
	}
	.aiplan-card h5 { font-weight: 700; margin: 0 0 .75rem; color: #0f172a; }
	.aiplan-status { font-size: .85rem; color: #64748b; }
	.aiplan-mod {
		border: 1px solid #e2e8f0; border-radius: 10px; padding: .75rem .9rem; margin-bottom: .55rem;
		display: flex; gap: .75rem; align-items: flex-start; justify-content: space-between; flex-wrap: wrap;
	}
	.aiplan-mod.is-matched { border-color: #86efac; background: #f0fdf4; }
	.aiplan-mod.is-unmatched { border-color: #fcd34d; background: #fffbeb; }
	.aiplan-badge {
		display: inline-block; font-size: .72rem; font-weight: 700; padding: .15rem .45rem; border-radius: 999px;
		background: #e0f2fe; color: #0369a1; margin-right: .25rem;
	}
	.aiplan-badge.warn { background: #fef3c7; color: #b45309; }
	.aiplan-badge.ok { background: #dcfce7; color: #15803d; }
	#aiplanModules { max-height: 420px; overflow: auto; }
	#aiplanTopics { max-height: 280px; overflow: auto; }
	.aiplan-topic {
		display: block; width: 100%; text-align: left; border: 1px solid #e2e8f0; background: #fff;
		border-radius: 8px; padding: .55rem .7rem; margin-bottom: .4rem; cursor: pointer;
	}
	.aiplan-topic:hover, .aiplan-topic.is-on { border-color: #0ea5e9; background: #f0f9ff; }
	.aiplan-busy { display: none; color: #0f766e; font-weight: 600; }
</style>

<div class="aiplan-wrap">
	<div class="aiplan-hero">
		<h3><i class="fa fa-magic"></i> Academic AI Plans</h3>
		<p>
			Gemini analyzes uploaded <b>curriculum</b> + <b>chronogram</b> (any Word/PDF layout), matches modules to your DB levels &amp; teachers,
			then generates <b>Scheme of Work</b> and <b>Session Plan</b> (TVET/RTB) or <b>Lesson Plan</b> (REB).
			Year: <b><?= esc($yearTitle); ?></b>
		</p>
	</div>

	<?php if (empty($gemini_ready)): ?>
		<div class="alert alert-warning">Gemini API key is not configured on this server (<code>GOOGLE_AI_API_KEY</code>).</div>
	<?php endif; ?>

	<div class="aiplan-card">
		<h5>1. Select class</h5>
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
						?>
						<option value="<?= $cid; ?>"
								data-prog="<?= esc($prog, 'attr'); ?>"
								data-has-cur="<?= $docs['curriculum'] ? '1' : '0'; ?>"
								data-has-chr="<?= $docs['chronogram'] ? '1' : '0'; ?>">
							<?= esc($label); ?> (<?= $prog; ?>) — <?= $flags; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-4">
				<button type="button" class="btn btn-primary btn-block" id="btnAnalyzeCurriculum" <?= empty($gemini_ready) ? 'disabled' : ''; ?>>
					<i class="fa fa-search"></i> Analyze curriculum with AI
				</button>
				<button type="button" class="btn btn-outline-secondary btn-sm btn-block mt-1" id="btnForceAnalyze" <?= empty($gemini_ready) ? 'disabled' : ''; ?>>
					Re-analyze (ignore cache)
				</button>
			</div>
		</div>
		<p class="aiplan-status mt-2 mb-0" id="aiplanStatus">Upload curriculum &amp; chronogram under School Settings → Pedagogical documents, then analyze.</p>
		<p class="aiplan-busy mt-2" id="aiplanBusy"><i class="fa fa-spinner fa-spin"></i> Gemini is working — large PDFs can take up to 2 minutes…</p>
	</div>

	<div class="aiplan-card" id="aiplanResultCard" style="display:none;">
		<h5>2. Courses / modules found <span id="aiplanProgBadge" class="aiplan-badge"></span></h5>
		<div id="aiplanMeta" class="text-muted mb-2" style="font-size:.88rem;"></div>
		<div id="aiplanModules"></div>
	</div>

	<div class="aiplan-card" id="aiplanSessionCard" style="display:none;">
		<h5>3. Session / Lesson plan from Scheme of Work</h5>
		<p class="text-muted" style="font-size:.88rem;">Pick a topic/week (from the generated scheme + chronogram). TVET → Session Plan · REB → Lesson Plan.</p>
		<select id="aiplanSchemeSelect" class="form-control mb-2" style="display:none;"></select>
		<div id="aiplanTopics"></div>
		<button type="button" class="btn btn-success mt-2" id="btnGenSession" disabled>
			<i class="fa fa-file-text-o"></i> <span id="btnGenSessionLabel">Generate Session Plan</span>
		</button>
	</div>

	<div class="aiplan-card">
		<h5>Saved plans (this academic year)</h5>
		<div class="table-responsive">
			<table class="table table-sm table-hover">
				<thead>
				<tr>
					<th>Title</th>
					<th>Type</th>
					<th>Program</th>
					<th></th>
				</tr>
				</thead>
				<tbody>
				<?php if (empty($saved_plans)): ?>
					<tr><td colspan="4" class="text-muted">No plans yet.</td></tr>
				<?php else: foreach ($saved_plans as $p): ?>
					<tr>
						<td><?= esc($p['title']); ?></td>
						<td><span class="aiplan-badge"><?= esc(str_replace('_', ' ', $p['plan_type'])); ?></span></td>
						<td><?= esc(strtoupper($p['program_type'] ?? '')); ?></td>
						<td class="text-right">
							<a class="btn btn-outline-primary btn-sm" target="_blank" href="<?= base_url('view_academic_plan/' . $p['id']); ?>">View</a>
							<a class="btn btn-outline-success btn-sm" href="<?= base_url('download_academic_plan/' . $p['id']); ?>">Download</a>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script>
(function ($) {
	var analysis = null;
	var selectedModule = null;
	var selectedTopic = null;
	var lastSchemeId = null;
	var programType = 'tvet';

	function busy(on) {
		$('#aiplanBusy').toggle(!!on);
		$('#btnAnalyzeCurriculum, #btnForceAnalyze, #btnGenSession').prop('disabled', !!on);
	}
	function status(msg, isErr) {
		$('#aiplanStatus').css('color', isErr ? '#b91c1c' : '#64748b').text(msg || '');
	}
	function currentClassId() {
		return parseInt($('#aiplanClass').val() || '0', 10) || 0;
	}

	function renderModules() {
		var $box = $('#aiplanModules').empty();
		if (!analysis || !analysis.modules || !analysis.modules.length) {
			$box.html('<p class="text-muted">No modules extracted.</p>');
			return;
		}
		programType = analysis.program_type || 'tvet';
		$('#aiplanProgBadge').text((programType === 'reb' ? 'REB → Scheme + Lesson Plan' : 'TVET/RTB → Scheme + Session Plan'));
		$('#aiplanMeta').text(
			[analysis.qualification_title, analysis.sector, analysis.trade, analysis.detected_rqf_level ? ('RQF ' + analysis.detected_rqf_level) : '']
				.filter(Boolean).join(' · ')
		);
		analysis.modules.forEach(function (m, idx) {
			var matched = !!m.matched_course_id;
			var $row = $('<div class="aiplan-mod"></div>').addClass(matched ? 'is-matched' : 'is-unmatched');
			var badges = '';
			if (m.code) badges += '<span class="aiplan-badge">' + $('<div>').text(m.code).html() + '</span>';
			if (m.rqf_level) badges += '<span class="aiplan-badge">RQF ' + m.rqf_level + '</span>';
			if (matched) badges += '<span class="aiplan-badge ok">DB course matched</span>';
			else badges += '<span class="aiplan-badge warn">No DB course match</span>';
			if (m.teacher_name) badges += '<span class="aiplan-badge ok">Teacher: ' + $('<div>').text(m.teacher_name).html() + '</span>';
			else badges += '<span class="aiplan-badge warn">No teacher assigned</span>';
			if (m.matched_level_title) badges += '<span class="aiplan-badge">Level: ' + $('<div>').text(m.matched_level_title).html() + '</span>';
			var left = $('<div></div>');
			left.append('<div style="font-weight:700;">' + $('<div>').text(m.title || 'Untitled').html() + '</div>');
			left.append('<div style="margin-top:.35rem;">' + badges + '</div>');
			if (m.learning_hours) left.append('<div class="text-muted" style="font-size:.8rem;margin-top:.25rem;">Hours: ' + m.learning_hours + (m.credits ? (' · Credits: ' + m.credits) : '') + '</div>');
			var $btn = $('<button type="button" class="btn btn-info btn-sm btn-gen-sow"><i class="fa fa-magic"></i> Generate Scheme of Work</button>');
			$btn.on('click', function () { generateSow(m, idx); });
			$row.append(left).append($('<div></div>').append($btn));
			$box.append($row);
		});
		$('#aiplanResultCard').show();
	}

	function analyze(force) {
		var cid = currentClassId();
		if (!cid) { status('Select a class first', true); return; }
		var $opt = $('#aiplanClass option:selected');
		if ($opt.data('has-cur') != '1' && $opt.data('has-cur') != 1) {
			status('This class has no curriculum uploaded for the current year. Upload it in School Settings first.', true);
			return;
		}
		busy(true);
		status(force ? 'Re-analyzing with Gemini…' : 'Analyzing curriculum & chronogram…');
		$.post('<?= base_url('ai_analyze_curriculum'); ?>', { class_id: cid, force: force ? 1 : 0 }, function (res) {
			busy(false);
			if (res && res.error) { status(res.error, true); return; }
			analysis = res.analysis || null;
			selectedModule = null;
			selectedTopic = null;
			status((res.cached ? 'Loaded cached analysis. ' : 'Fresh AI analysis ready. ') + ((analysis.modules || []).length) + ' module(s).');
			renderModules();
			$('#aiplanSessionCard').hide();
		}, 'json').fail(function (xhr) {
			busy(false);
			status((xhr.responseJSON && xhr.responseJSON.error) || 'Analysis request failed', true);
		});
	}

	function generateSow(mod) {
		var cid = currentClassId();
		if (!cid || !mod) return;
		selectedModule = mod;
		busy(true);
		status('Generating Scheme of Work with Gemini…');
		$.post('<?= base_url('ai_generate_scheme_of_work'); ?>', {
			class_id: cid,
			module: JSON.stringify(mod)
		}, function (res) {
			busy(false);
			if (res && res.error) { status(res.error, true); return; }
			lastSchemeId = res.plan_id;
			status('Scheme of Work ready: ' + (res.title || ''));
			if (typeof toastada !== 'undefined') toastada.success(res.success || 'Scheme generated');
			loadTopics(res.plan_id, res.topics || []);
			if (res.preview_url) window.open(res.preview_url, '_blank');
		}, 'json').fail(function (xhr) {
			busy(false);
			status((xhr.responseJSON && xhr.responseJSON.error) || 'Scheme generation failed', true);
		});
	}

	function loadTopics(schemeId, topics) {
		lastSchemeId = schemeId;
		var $t = $('#aiplanTopics').empty();
		selectedTopic = null;
		$('#btnGenSession').prop('disabled', true);
		var label = (programType === 'reb') ? 'Generate Lesson Plan' : 'Generate Session Plan';
		$('#btnGenSessionLabel').text(label);
		if (!topics || !topics.length) {
			$.getJSON('<?= base_url('list_academic_plan_topics'); ?>', { scheme_id: schemeId }, function (res) {
				topics = (res && res.topics) || [];
				paintTopics(topics);
			});
		} else {
			paintTopics(topics);
		}
		$('#aiplanSessionCard').show();
	}

	function paintTopics(topics) {
		var $t = $('#aiplanTopics').empty();
		if (!topics.length) {
			$t.html('<p class="text-muted">No session topics returned — open the Scheme of Work and re-generate if needed.</p>');
			return;
		}
		topics.forEach(function (tp, i) {
			var line = 'Week ' + (tp.week || '?') + (tp.term ? (' · Term ' + tp.term) : '') + ' — ' + (tp.topic || tp.ic_title || tp.lo_title || 'Topic');
			if (tp.date) line += ' (' + tp.date + ')';
			var $b = $('<button type="button" class="aiplan-topic"></button>').text(line);
			$b.on('click', function () {
				$('.aiplan-topic').removeClass('is-on');
				$b.addClass('is-on');
				selectedTopic = tp;
				$('#btnGenSession').prop('disabled', false);
			});
			$t.append($b);
		});
	}

	$('#btnAnalyzeCurriculum').on('click', function () { analyze(false); });
	$('#btnForceAnalyze').on('click', function () { analyze(true); });
	$('#btnGenSession').on('click', function () {
		if (!lastSchemeId || !selectedTopic) return;
		busy(true);
		status('Generating plan with Gemini…');
		$.post('<?= base_url('ai_generate_session_plan'); ?>', {
			class_id: currentClassId(),
			scheme_id: lastSchemeId,
			topic: JSON.stringify(selectedTopic),
			module: JSON.stringify(selectedModule || {})
		}, function (res) {
			busy(false);
			if (res && res.error) { status(res.error, true); return; }
			status((res.success || 'Plan ready') + ': ' + (res.title || ''));
			if (typeof toastada !== 'undefined') toastada.success(res.success || 'Generated');
			if (res.preview_url) window.open(res.preview_url, '_blank');
			setTimeout(function () { location.reload(); }, 1200);
		}, 'json').fail(function (xhr) {
			busy(false);
			status((xhr.responseJSON && xhr.responseJSON.error) || 'Generation failed', true);
		});
	});
})(jQuery);
</script>
