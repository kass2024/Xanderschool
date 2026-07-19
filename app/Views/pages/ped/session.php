<?php
/**
 * Session Plan — weekly topic activity from Scheme of Work (C Programming sample)
 * @var array $classes
 * @var array $scheme_plans
 * @var array $session_plans
 * @var bool $gemini_ready
 */
include __DIR__ . '/_nav.php';
$schemesByClass = [];
foreach ($scheme_plans ?? [] as $p) {
	$cid = (int) ($p['class_id'] ?? 0);
	if (!isset($schemesByClass[$cid])) {
		$schemesByClass[$cid] = [];
	}
	$schemesByClass[$cid][] = [
		'id' => (int) $p['id'],
		'title' => $p['title'] ?? '',
		'program_type' => $p['program_type'] ?? 'tvet',
		'topic' => $p['topic'] ?? '',
	];
}
?>
<style>
	.aiplan-wrap { max-width: 1180px; }
	.aiplan-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.15rem; margin-bottom:1rem; }
	.aiplan-card h5 { font-weight:700; margin:0 0 .75rem; }
	.aiplan-status { font-size:.85rem; color:#64748b; }
	.aiplan-busy { display:none; color:#0f766e; font-weight:600; }
	.aiplan-topic {
		display:block; width:100%; text-align:left; border:1px solid #e2e8f0; background:#fff;
		border-radius:8px; padding:.55rem .7rem; margin-bottom:.4rem; cursor:pointer;
	}
	.aiplan-topic:hover, .aiplan-topic.is-on { border-color:#0ea5e9; background:#f0f9ff; }
	#aiplanTopics { max-height:320px; overflow:auto; }
</style>

<div class="aiplan-wrap">
	<div class="aiplan-card">
		<h5>Generate Session Plan (weekly topic)</h5>
		<p class="text-muted" style="font-size:.88rem;">
			Pick a saved Scheme of Work, then a week/topic. The Session Plan follows the C Programming sample
			(Learning Outcome, Indicative content, Introduction, Development steps, Assessment…).
		</p>
		<div class="row">
			<div class="col-md-6 mb-2">
				<label>Class</label>
				<select id="spClass" class="form-control">
					<option value="">— Choose class —</option>
					<?php foreach ($classes as $c):
						$cid = (int) $c['id'];
						$label = trim(($c['level_name'] ?? '') . ' ' . ($c['dept_code'] ?? '') . ' ' . ($c['title'] ?? ''));
						$n = isset($schemesByClass[$cid]) ? count($schemesByClass[$cid]) : 0;
						?>
						<option value="<?= $cid; ?>" data-schemes="<?= $n; ?>">
							<?= esc($label); ?><?= $n ? (' · ' . $n . ' scheme(s)') : ' · No scheme yet'; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-6 mb-2">
				<label>Scheme of Work</label>
				<select id="spScheme" class="form-control" disabled>
					<option value="">— Select scheme —</option>
				</select>
			</div>
		</div>
		<div id="spNeedScheme" class="alert alert-warning" style="display:none;">
			No Scheme of Work for this class yet.
			<a href="<?= base_url('ped_scheme_of_work'); ?>">Generate a Scheme of Work</a> first.
		</div>
		<p class="aiplan-status mt-1 mb-0" id="spStatus"></p>
		<p class="aiplan-busy mt-2" id="spBusy"><i class="fa fa-spinner fa-spin"></i> Generating Session Plan…</p>
	</div>

	<div class="aiplan-card" id="spTopicsCard" style="display:none;">
		<h5>Select week / topic</h5>
		<div id="aiplanTopics"></div>
		<button type="button" class="btn btn-success mt-2" id="btnGenSession" disabled>
			<i class="fa fa-file-text-o"></i> <span id="btnGenSessionLabel">Generate Session Plan</span>
		</button>
	</div>

	<div class="aiplan-card">
		<h5>Saved Session / Lesson plans</h5>
		<div class="table-responsive">
			<table class="table table-sm table-hover">
				<thead><tr><th>Title</th><th>Type</th><th>Week</th><th></th></tr></thead>
				<tbody>
				<?php if (empty($session_plans)): ?>
					<tr><td colspan="4" class="text-muted">No session plans yet.</td></tr>
				<?php else: foreach ($session_plans as $p): ?>
					<tr>
						<td><?= esc($p['title']); ?></td>
						<td><?= esc(str_replace('_', ' ', $p['plan_type'] ?? '')); ?></td>
						<td><?= esc($p['week_number'] ?? ''); ?></td>
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
	var SCHEMES = <?= json_encode($schemesByClass, JSON_UNESCAPED_UNICODE); ?> || {};
	var selectedTopic = null;
	var selectedSchemeId = null;
	var programType = 'tvet';

	function status(msg, err) { $('#spStatus').css('color', err ? '#b91c1c' : '#64748b').text(msg || ''); }
	function busy(on) { $('#spBusy').toggle(!!on); $('#btnGenSession').prop('disabled', !!on || !selectedTopic); }

	$('#spClass').on('change', function () {
		var cid = $(this).val();
		var list = SCHEMES[String(cid)] || [];
		var $s = $('#spScheme').empty().append('<option value="">— Select scheme —</option>');
		$('#spTopicsCard').hide();
		selectedTopic = null;
		selectedSchemeId = null;
		if (!cid) { $s.prop('disabled', true); $('#spNeedScheme').hide(); return; }
		if (!list.length) {
			$s.prop('disabled', true);
			$('#spNeedScheme').show();
			status('Generate a Scheme of Work first.', true);
			return;
		}
		$('#spNeedScheme').hide();
		list.forEach(function (p) {
			$s.append($('<option></option>').val(p.id).text(p.title).attr('data-prog', p.program_type || 'tvet'));
		});
		$s.prop('disabled', false);
		status(list.length + ' scheme(s) available.');
	});

	$('#spScheme').on('change', function () {
		selectedSchemeId = parseInt($(this).val() || '0', 10) || 0;
		selectedTopic = null;
		$('#btnGenSession').prop('disabled', true);
		programType = $('#spScheme option:selected').data('prog') || 'tvet';
		$('#btnGenSessionLabel').text(programType === 'reb' ? 'Generate Lesson Plan' : 'Generate Session Plan');
		if (!selectedSchemeId) { $('#spTopicsCard').hide(); return; }
		status('Loading topics from Scheme of Work…');
		$.getJSON('<?= base_url('list_academic_plan_topics'); ?>', { scheme_id: selectedSchemeId }, function (res) {
			var topics = (res && res.topics) || [];
			paintTopics(topics);
		}).fail(function () { status('Could not load topics', true); });
	});

	function paintTopics(topics) {
		var $t = $('#aiplanTopics').empty();
		if (!topics.length) {
			$t.html('<p class="text-muted">No weekly topics found in this Scheme of Work. Re-generate the scheme.</p>');
			$('#spTopicsCard').show();
			return;
		}
		topics.forEach(function (tp) {
			var line = 'Week ' + (tp.week || '?') + (tp.term ? (' · Term ' + tp.term) : '') + ' — ' + (tp.topic || tp.ic_title || tp.lo_title || 'Topic');
			if (tp.date) line += ' (' + tp.date + ')';
			if (tp.duration) line += ' · ' + tp.duration;
			var $b = $('<button type="button" class="aiplan-topic"></button>').text(line);
			$b.on('click', function () {
				$('.aiplan-topic').removeClass('is-on');
				$b.addClass('is-on');
				selectedTopic = tp;
				$('#btnGenSession').prop('disabled', false);
			});
			$t.append($b);
		});
		$('#spTopicsCard').show();
		status(topics.length + ' weekly topic(s). Select one to generate a Session Plan.');
	}

	$('#btnGenSession').on('click', function () {
		if (!selectedSchemeId || !selectedTopic) return;
		busy(true);
		status('Generating weekly Session Plan…');
		$.post('<?= base_url('ai_generate_session_plan'); ?>', {
			class_id: $('#spClass').val(),
			scheme_id: selectedSchemeId,
			topic: JSON.stringify(selectedTopic),
			module: JSON.stringify({})
		}, function (res) {
			busy(false);
			if (res && res.error) { status(res.error, true); return; }
			status((res.success || 'Plan ready') + ': ' + (res.title || ''));
			if (res.preview_url) window.open(res.preview_url, '_blank');
			setTimeout(function () { location.reload(); }, 900);
		}, 'json').fail(function (xhr) {
			busy(false);
			status((xhr.responseJSON && xhr.responseJSON.error) || 'Generation failed', true);
		});
	});
})(jQuery);
</script>
