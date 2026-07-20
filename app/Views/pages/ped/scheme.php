<?php
/**
 * Scheme of Work — select extracted course, map chronogram, generate weekly SoW
 * @var array $classes
 * @var array $analysis_cache
 * @var array $scheme_plans
 * @var bool $gemini_ready
 */
include __DIR__ . '/_nav.php';
$cacheJson = [];
foreach ($analysis_cache ?? [] as $cid => $row) {
	$cacheJson[(string) $cid] = [
		'has_cache' => !empty($row['has_cache']),
		'module_count' => (int) ($row['module_count'] ?? 0),
		'modules' => $row['modules'] ?? [],
		'program_type' => $row['program_type'] ?? 'tvet',
		'analysis' => $row['analysis'] ?? null,
	];
}
?>
<style>
	.aiplan-wrap { max-width: 1180px; }
	.aiplan-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.15rem; margin-bottom:1rem; }
	.aiplan-card h5 { font-weight:700; margin:0 0 .75rem; }
	.aiplan-status { font-size:.85rem; color:#64748b; }
	.aiplan-mod { border:1px solid #e2e8f0; border-radius:10px; padding:.75rem .9rem; margin-bottom:.55rem; display:flex; justify-content:space-between; gap:.75rem; flex-wrap:wrap; align-items:flex-start; }
	.aiplan-mod.is-matched { border-color:#86efac; background:#f0fdf4; }
	.aiplan-badge { display:inline-block; font-size:.72rem; font-weight:700; padding:.15rem .45rem; border-radius:999px; background:#e0f2fe; color:#0369a1; margin-right:.25rem; }
	.aiplan-busy { display:none; color:#0f766e; font-weight:600; }
	#aiplanModules { max-height:420px; overflow:auto; }
	.aiplan-opts { font-size:.84rem; color:#475569; margin-top:.5rem; }
</style>

<div class="aiplan-wrap">
	<div class="aiplan-card">
		<h5>Select class</h5>
		<p class="text-muted" style="font-size:.88rem;">
			Schemes are built from the <b>cached</b> curriculum analysis (LO / IC + chronogram weeks) — no Gemini credits by default.
			Edit inline, then download Word or PDF.
		</p>
		<select id="sowClass" class="form-control">
			<option value="">— Choose class —</option>
			<?php foreach ($classes as $c):
				$cid = (int) $c['id'];
				$cache = $analysis_cache[$cid] ?? null;
				$label = trim(($c['level_name'] ?? '') . ' ' . ($c['dept_code'] ?? '') . ' ' . ($c['title'] ?? ''));
				$flag = !empty($cache['has_cache']) ? (' · ' . (int)$cache['module_count'] . ' courses cached') : ' · Analysis required';
				?>
				<option value="<?= $cid; ?>" data-has-cache="<?= !empty($cache['has_cache']) ? '1' : '0'; ?>">
					<?= esc($label); ?><?= esc($flag); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<div class="aiplan-opts">
			<label class="mb-0 mr-3"><input type="checkbox" id="sowForce"> Force re-generate (replace cache)</label>
			<label class="mb-0"><input type="checkbox" id="sowUseAi" <?= !empty($gemini_ready) ? '' : 'disabled'; ?>> Optional AI polish (uses Gemini credits)</label>
		</div>
		<p class="aiplan-status mt-2 mb-0" id="sowStatus">If analysis is missing, go to Analyse Curriculum &amp; Chronogram first.</p>
		<p class="aiplan-busy mt-2" id="sowBusy"><i class="fa fa-spinner fa-spin"></i> Building Scheme of Work…</p>
		<div id="sowNeedAnalyse" class="alert alert-warning mt-2" style="display:none;">
			No cached analysis for this class.
			<a href="<?= base_url('ped_analyse'); ?>">Run Analyse Curriculum &amp; Chronogram</a> first.
		</div>
	</div>

	<div class="aiplan-card" id="sowModulesCard" style="display:none;">
		<h5>Extracted courses — generate Scheme of Work</h5>
		<div id="aiplanModules"></div>
	</div>

	<div class="aiplan-card">
		<h5>Saved Schemes of Work</h5>
		<div class="table-responsive">
			<table class="table table-sm table-hover">
				<thead><tr><th>Title</th><th>Program</th><th></th></tr></thead>
				<tbody>
				<?php if (empty($scheme_plans)): ?>
					<tr><td colspan="3" class="text-muted">No schemes yet.</td></tr>
				<?php else: foreach ($scheme_plans as $p): ?>
					<tr>
						<td><?= esc($p['title']); ?></td>
						<td><?= esc(strtoupper($p['program_type'] ?? '')); ?></td>
						<td class="text-right text-nowrap">
							<a class="btn btn-primary btn-sm" href="<?= base_url('edit_academic_plan/' . $p['id']); ?>">Edit</a>
							<a class="btn btn-outline-primary btn-sm" target="_blank" href="<?= base_url('view_academic_plan/' . $p['id']); ?>">View</a>
							<a class="btn btn-outline-success btn-sm" href="<?= base_url('download_academic_plan/' . $p['id']); ?>">Word</a>
							<a class="btn btn-outline-danger btn-sm" href="<?= base_url('download_academic_plan_pdf/' . $p['id']); ?>">PDF</a>
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
	var CACHE = <?= json_encode($cacheJson, JSON_UNESCAPED_UNICODE); ?> || {};
	var currentModules = [];
	function status(msg, err) { $('#sowStatus').css('color', err ? '#b91c1c' : '#64748b').text(msg || ''); }
	function busy(on) { $('#sowBusy').toggle(!!on); }

	function renderModules(cid) {
		var pack = CACHE[String(cid)] || {};
		currentModules = pack.modules || [];
		var $box = $('#aiplanModules').empty();
		if (!pack.has_cache || !currentModules.length) {
			$('#sowModulesCard').hide();
			$('#sowNeedAnalyse').show();
			return;
		}
		$('#sowNeedAnalyse').hide();
		$('#sowModulesCard').show();
		currentModules.forEach(function (m) {
			var $row = $('<div class="aiplan-mod"></div>').addClass(m.matched_course_id ? 'is-matched' : '');
			var left = $('<div></div>');
			left.append('<div style="font-weight:700;">' + $('<div>').text((m.code ? m.code + ' — ' : '') + (m.title || 'Untitled')).html() + '</div>');
			var badges = '';
			if (m.learning_hours) badges += '<span class="aiplan-badge">' + m.learning_hours + ' hrs</span>';
			if (m.teacher_name) badges += '<span class="aiplan-badge">' + $('<div>').text(m.teacher_name).html() + '</span>';
			var slots = (m.chronogram_slots || []).length;
			if (slots) badges += '<span class="aiplan-badge">' + slots + ' chronogram weeks</span>';
			left.append('<div style="margin-top:.35rem;">' + badges + '</div>');
			var $btn = $('<button type="button" class="btn btn-info btn-sm"><i class="fa fa-magic"></i> Generate / Open</button>');
			$btn.on('click', function () { genSow(m); });
			$row.append(left).append($('<div></div>').append($btn));
			$box.append($row);
		});
		status(currentModules.length + ' course(s) ready from cached analysis.');
	}

	function genSow(mod) {
		var cid = parseInt($('#sowClass').val() || '0', 10);
		if (!cid || !mod) return;
		busy(true);
		status('Building Scheme of Work from curriculum cache…');
		$.post('<?= base_url('ai_generate_scheme_of_work'); ?>', {
			class_id: cid,
			module: JSON.stringify(mod),
			force: $('#sowForce').is(':checked') ? 1 : 0,
			use_ai: $('#sowUseAi').is(':checked') ? 1 : 0
		}, function (res) {
			busy(false);
			if (res && res.error) { status(res.error, true); return; }
			var msg = res.from_cache
				? ('Loaded from DB cache: ' + (res.title || ''))
				: (res.success || ('Scheme ready: ' + (res.title || '')));
			status(msg);
			if (res.edit_url) {
				window.location.href = res.edit_url;
				return;
			}
			if (res.preview_url) window.open(res.preview_url, '_blank');
			setTimeout(function () { location.reload(); }, 700);
		}, 'json').fail(function (xhr) {
			busy(false);
			var err = (xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText || 'Scheme generation failed';
			status(typeof err === 'string' ? err : 'Scheme generation failed', true);
		});
	}

	$('#sowClass').on('change', function () {
		var cid = $(this).val();
		if (!cid) { $('#sowModulesCard').hide(); $('#sowNeedAnalyse').hide(); return; }
		renderModules(cid);
	});
})(jQuery);
</script>
