<?php
/** @var string $bhMode permission|discipline */
/** @var array $classes */
/** @var string $bhTitle */
/** @var string $bhSubtitle */
$bhMode = $bhMode ?? 'permission';
$bhTitle = $bhTitle ?? lang('app.permissionReport');
$bhSubtitle = $bhSubtitle ?? '';
$termLabel = isset($term) ? \App\Controllers\Home::TermToStr($term) : '';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/behavior-dashboard.css'); ?>">

<div class="bh-dashboard-page" id="bhDashboard" data-mode="<?= esc($bhMode, 'attr'); ?>">
	<div class="bh-dashboard-center">

		<div class="bh-dash-header">
			<h2><?= esc($bhTitle); ?></h2>
			<p><?= esc($bhSubtitle); ?><?= $termLabel !== '' ? ' · ' . esc($termLabel) : ''; ?></p>
			<div class="bh-quick-links">
				<?php if ($bhMode === 'permission') : ?>
					<a href="<?= base_url('permission_entry'); ?>"><i class="fa fa-plus-circle"></i> New permission</a>
				<?php else : ?>
					<?= view('pages/partials/disc_lang_switcher'); ?>
					<a href="<?= base_url('discipline_record_entry'); ?>"><i class="fa fa-plus-circle"></i> <?= \App\Models\DisciplineCodeModel::discLang() === 'rw' ? 'Andika ikosa' : 'Record discipline'; ?></a>
				<?php endif; ?>
			</div>
		</div>

		<div class="bh-kpi-grid" id="bhKpis">
			<div class="bh-kpi"><div class="bh-loading"><i class="fa fa-spinner fa-spin"></i></div></div>
		</div>

		<div class="bh-filter-card">
			<div class="bh-filter-row">
				<div class="bh-field">
					<label><?= lang('app.sClass'); ?></label>
					<select class="form-control select2" id="bhClass">
						<option value="">All classes</option>
						<?php foreach ($classes as $class) : ?>
							<option value="<?= (int) $class['id']; ?>">
								<?= esc($class['level_name'] . ' ' . $class['title'] . ' ' . $class['code']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php if ($bhMode === 'permission') : ?>
				<div class="bh-field">
					<label><?= lang('app.searchStudent'); ?></label>
					<select class="form-control" id="bhStudent"></select>
				</div>
				<div class="bh-field">
					<label><?= lang('app.fromDate'); ?></label>
					<input type="date" class="form-control" id="bhFrom">
				</div>
				<div class="bh-field">
					<label><?= lang('app.toDate'); ?></label>
					<input type="date" class="form-control" id="bhTo">
				</div>
				<?php endif; ?>
				<div class="bh-field" style="flex:0 0 auto;">
					<label>&nbsp;</label>
					<button type="button" class="btn btn-success bh-btn-go" id="bhRefresh">
						<i class="fa fa-sync-alt"></i> <?= lang('app.go'); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="bh-panels has-side">
			<div class="bh-panel">
				<div class="bh-panel-head">
					<h3 id="bhMainTitle"><?= $bhMode === 'permission' ? lang('app.permissionReport') : lang('app.disciplineRecord'); ?></h3>
					<span class="bh-badge muted" id="bhMainCount">—</span>
				</div>
				<div class="bh-panel-body" id="bhMainBody">
					<div class="bh-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>
				</div>
				<?php if ($bhMode === 'discipline') : ?>
				<div class="bh-legend">
					<span class="bh-legend-dot warn"></span> <?= lang('app.halfoftotalmax'); ?>
					&nbsp;&nbsp;
					<span class="bh-legend-dot ok"></span> <?= lang('app.studenthasnormal'); ?>
				</div>
				<?php endif; ?>
			</div>

			<div class="bh-panel">
				<div class="bh-panel-head">
					<h3><?= $bhMode === 'permission' ? 'Unjustified permissions' : 'Recent incidents'; ?></h3>
				</div>
				<div class="bh-panel-body" id="bhSideBody" style="max-height:520px;">
					<div class="bh-loading"><i class="fa fa-spinner fa-spin"></i></div>
				</div>
			</div>
		</div>

	</div>
</div>

<script>
(function ($) {
	const mode = $('#bhDashboard').data('mode') || 'permission';
	const apiUrl = <?= json_encode(base_url('behavior_dashboard_data')); ?>;
	const searchStudentUrl = <?= json_encode(base_url('search_student')); ?>;
	const searchPlaceholder = <?= json_encode(lang('app.searchBy')); ?>;
	const classPlaceholder = <?= json_encode(lang('app.selectClass')); ?>;
	const fmtDate = d => d ? String(d).substring(0, 16).replace('T', ' ') : '—';

	function firstDayMonth() {
		const d = new Date();
		return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-01';
	}
	function todayStr() {
		const d = new Date();
		return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
	}

	function initSelects() {
		if (!$.fn.select2) {
			return;
		}
		if ($('#bhStudent').length && !$('#bhStudent').hasClass('select2-hidden-accessible')) {
			$('#bhStudent').select2({
				allowClear: true,
				ajax: {
					url: searchStudentUrl,
					type: 'POST',
					dataType: 'json',
					delay: 250,
					data: params => ({ searchTerm: params.term }),
					processResults: response => ({ results: response }),
					cache: true
				},
				placeholder: searchPlaceholder,
				minimumInputLength: 2
			});
		}
		if (!$('#bhClass').hasClass('select2-hidden-accessible')) {
			$('#bhClass').select2({ allowClear: true, placeholder: classPlaceholder, width: '100%' });
		}
	}

	function esc(s) {
		return $('<div/>').text(s == null ? '' : s).html();
	}

	function renderKpis(k) {
		let cards = [];
		if (mode === 'discipline') {
			cards = [
				{ icon: 'slate', fa: 'fa-users', val: k.class_students, label: 'Class students' },
				{ icon: 'green', fa: 'fa-heart', val: k.discipline_avg_remaining, label: 'Avg remaining' },
				{ icon: 'orange', fa: 'fa-user-clock', val: k.students_at_risk, label: 'At-risk' },
				{ icon: 'purple', fa: 'fa-gavel', val: k.discipline_incidents_term, label: 'Incidents (term)' },
				{ icon: 'blue', fa: 'fa-calendar-day', val: k.discipline_incidents_today, label: 'Today' },
				{ icon: 'green', fa: 'fa-book', val: k.conduct_codes, label: 'Conduct codes' },
			];
		} else {
			cards = [
				{ icon: 'blue', fa: 'fa-door-open', val: k.permissions_total, label: 'Permissions (range)' },
				{ icon: 'green', fa: 'fa-calendar-day', val: k.permissions_today, label: 'Today' },
				{ icon: 'orange', fa: 'fa-walking', val: k.permissions_active, label: 'Out now' },
				{ icon: 'red', fa: 'fa-exclamation-circle', val: k.permissions_unjustified, label: 'Unjustified' },
			];
		}
		let html = '';
		cards.forEach(c => {
			html += `<div class="bh-kpi">
				<div class="bh-kpi-icon ${c.icon}"><i class="fa ${c.fa}"></i></div>
				<div class="bh-kpi-value">${esc(c.val)}</div>
				<div class="bh-kpi-label">${esc(c.label)}</div>
			</div>`;
		});
		$('#bhKpis').html(html);
	}

	function renderPermissions(rows) {
		if (!rows.length) {
			return `<div class="bh-empty"><i class="fa fa-inbox"></i>No permissions in this range.</div>`;
		}
		let tbody = '';
		rows.forEach((r, i) => {
			const st = r.status === '0'
				? '<span class="bh-badge pending">Pending</span>'
				: '<span class="bh-badge ok">Closed</span>';
			tbody += `<tr>
				<td>${i + 1}</td>
				<td><strong>${esc(r.student_name)}</strong><br><small class="text-muted">${esc(r.regno)} · ${esc(r.class_label)}</small></td>
				<td>${esc(r.destination)}</td>
				<td>${esc(r.reason)}</td>
				<td>${fmtDate(r.leave_time)}</td>
				<td>${fmtDate(r.return_time)}</td>
				<td>${st}</td>
				<td><a class="btn btn-sm btn-outline-success" href="${esc(r.print_url)}" target="_blank"><i class="fa fa-print"></i></a></td>
			</tr>`;
		});
		return `<table class="bh-table table mb-0">
			<thead><tr>
				<th>#</th><th>Student</th><th><?= lang('app.destination'); ?></th><th><?= lang('app.reason'); ?></th>
				<th><?= lang('app.leaveTime'); ?></th><th><?= lang('app.returnTime'); ?></th><th>Status</th><th></th>
			</tr></thead><tbody>${tbody}</tbody></table>`;
	}

	function renderDiscipline(rows, max) {
		if (!rows.length) {
			return `<div class="bh-empty"><i class="fa fa-users"></i>Select a class to view discipline standing.</div>`;
		}
		let tbody = '';
		rows.forEach((r, i) => {
			const cls = r.at_risk ? 'bh-row-warn' : '';
			tbody += `<tr class="${cls}">
				<td>${i + 1}</td>
				<td>${esc(r.regno)}</td>
				<td>${esc(r.student_name)}</td>
				<td><strong>${esc(r.remaining)}</strong> / ${esc(r.discipline_max || max)}</td>
			</tr>`;
		});
		return `<table class="bh-table table mb-0">
			<thead><tr><th>#</th><th><?= lang('app.regNo'); ?></th><th><?= lang('app.studentName'); ?></th><th><?= lang('app.remaining'); ?></th></tr></thead>
			<tbody>${tbody}</tbody></table>`;
	}

	function renderSidePermission(permissions) {
		const pending = (permissions || []).filter(p => p.status === '0').slice(0, 10);
		if (!pending.length) {
			return `<div class="bh-empty"><i class="fa fa-check-circle"></i>No unjustified permissions.</div>`;
		}
		return pending.map(p =>
			`<div class="bh-side-item"><strong>${esc(p.student_name)}</strong>
			<span>${esc(p.destination)} · ${fmtDate(p.leave_time)}</span></div>`
		).join('');
	}

	function renderSideDiscipline(recent) {
		if (!recent.length) {
			return `<div class="bh-empty"><i class="fa fa-check-circle"></i>No discipline records this term.</div>`;
		}
		return recent.map(r =>
			`<div class="bh-side-item"><strong>${esc(r.student_name)}</strong>
			<span>−${esc(r.marks)} · ${esc(r.comment || 'No comment')} · ${fmtDate(r.created_at)}</span></div>`
		).join('');
	}

	function showLoadError(msg) {
		const html = `<div class="bh-empty text-danger">${esc(msg)}</div>`;
		$('#bhKpis, #bhMainBody, #bhSideBody').html(html);
	}

	function loadDashboard() {
		const params = { mode: mode, class_id: $('#bhClass').val() || '' };
		if (mode === 'permission') {
			params.student_id = $('#bhStudent').val() || '';
			params.from = $('#bhFrom').val() || firstDayMonth();
			params.to = $('#bhTo').val() || todayStr();
		}
		$('#bhKpis').html('<div class="bh-kpi"><div class="bh-loading"><i class="fa fa-spinner fa-spin"></i></div></div>');
		$('#bhMainBody, #bhSideBody').html('<div class="bh-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>');
		$.ajax({
			url: apiUrl,
			method: 'GET',
			data: params,
			dataType: 'json',
			timeout: 30000
		}).done(data => {
			if (!data || !data.success) {
				showLoadError((data && data.error) ? data.error : 'Could not load data.');
				return;
			}
			renderKpis(data.kpis || {});
			if (mode === 'discipline') {
				$('#bhMainTitle').text('Class discipline standing');
				$('#bhMainCount').text((data.discipline_students || []).length + ' students');
				$('#bhMainBody').html(renderDiscipline(data.discipline_students || [], data.kpis.discipline_max));
				$('#bhSideBody').html(renderSideDiscipline(data.recent_discipline || []));
			} else {
				$('#bhMainTitle').text('Permission log');
				$('#bhMainCount').text((data.permissions || []).length + ' rows');
				$('#bhMainBody').html(renderPermissions(data.permissions || []));
				$('#bhSideBody').html(renderSidePermission(data.permissions || []));
			}
		}).fail(xhr => {
			showLoadError('Failed to load dashboard (' + xhr.status + ').');
		});
	}

	function bootDashboard() {
		if ($('#bhFrom').length) {
			if (!$('#bhFrom').val()) $('#bhFrom').val(firstDayMonth());
			if (!$('#bhTo').val()) $('#bhTo').val(todayStr());
		}
		initSelects();
		loadDashboard();
	}

	$('#bhRefresh').on('click', loadDashboard);
	$('#bhClass').on('change', loadDashboard);

	$(bootDashboard);
})(jQuery);
</script>
