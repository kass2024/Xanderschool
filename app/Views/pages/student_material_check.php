<link rel="stylesheet" href="<?= base_url('assets/css/student-materials.css') ?>?v=3">
<link rel="stylesheet" href="<?= base_url('assets/css/card-scan-ui.css') ?>">

<?php
$mentorOnly = empty($material_check_full_access ?? true);
$checker = $checker ?? ['name' => '', 'post' => ''];
$checkerName = trim((string) ($checker['name'] ?? ''));
$checkerPost = trim((string) ($checker['post'] ?? ''));
$checkerLabel = $checkerName !== '' ? $checkerName : 'Staff';
if ($checkerPost !== '') {
	$checkerLabel .= ' · ' . $checkerPost;
}
?>
<div class="smc-page card-scan-page">
	<div class="smc-center">
		<section class="smc-hero">
			<div class="smc-hero-top">
				<div>
					<div class="smc-hero-kicker"><i class="fa fa-clipboard-check"></i> Supply desk</div>
					<h4>Required material check</h4>
					<p class="smc-hero-sub">Live-search any student, or pick a class. Every save is stamped with your staff post so accountants and the headmaster can see who checked.</p>
				</div>
				<div class="smc-actor-badge" title="Logged-in staff performing checks">
					<span class="smc-actor-label">Checking as</span>
					<strong><?= esc($checkerLabel) ?></strong>
				</div>
			</div>

			<div class="smc-live-search">
				<div class="smc-live-search-shell" id="smcFindShell">
					<i class="fa fa-search" aria-hidden="true"></i>
					<input type="search" id="smcFindQ" placeholder="Start typing a name or registration number…"
						autocomplete="off" spellcheck="false" aria-label="Live search students">
					<span class="smc-live-spin" id="smcFindSpin" aria-hidden="true"><i class="fa fa-circle-o-notch"></i></span>
					<button type="button" class="smc-live-clear" id="smcFindClear" title="Clear" aria-label="Clear search">
						<i class="fa fa-times"></i>
					</button>
				</div>
				<div id="smcFindResult" class="smc-find-result" aria-live="polite"></div>
			</div>
		</section>

		<?php if ($mentorOnly && !empty($classes)) : ?>
			<div class="smc-scope-banner">
				<i class="fa fa-info-circle"></i>
				Class mentor mode — you can check materials only for:
				<strong><?= esc(implode(', ', array_map(static function ($c) {
					return trim(($c['level_name'] ?? '') . ' ' . ($c['title'] ?? ''));
				}, $classes))); ?></strong>
			</div>
		<?php endif; ?>

		<div class="smc-layout">
			<aside class="smc-scan-panel">
				<?= view('pages/partials/card_scan_search', [
					'classes' => $classes,
					'use_lang' => true,
					'default_mode' => 'class',
					'student_placeholder' => 'Type student name or reg no...',
				]) ?>
				<div class="smc-year-field mt-3">
					<label for="smcYear"><?= lang('app.academicYear') ?></label>
					<select class="form-control select2" id="smcYear">
						<?php foreach ($years as $year) :
							$yrId = (int) $year['id'];
							$sel = ($yrId === (int) ($selectedYear ?? 0)) ? ' selected' : '';
							?>
							<option value="<?= $yrId ?>"<?= $sel ?>><?= esc($year['title']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</aside>

			<main class="smc-workspace">
				<div class="smc-workspace-card" id="smcWorkspace">
					<div class="smc-dash" id="smcDash" style="display:none;">
						<div class="smc-dash-head">
							<div>
								<h2 id="smcDashTitle">Class overview</h2>
								<p id="smcDashSub">Material supply status for all students</p>
							</div>
							<div class="smc-dash-meta" id="smcDashMeta"></div>
						</div>
						<div class="smc-kpi-grid" id="smcClassKpi"></div>
						<div class="smc-progress-card" id="smcClassProgress" style="display:none;">
							<div class="smc-progress-head">
								<span>Class completion rate</span>
								<strong id="smcProgressPct">0%</strong>
							</div>
							<div class="smc-progress-bar"><div class="smc-progress-fill" id="smcProgressFill"></div></div>
						</div>

						<div class="smc-item-totals" id="smcItemTotals" style="display:none;">
							<div class="smc-item-totals-head">
								<div>
									<h3><i class="fa fa-cubes"></i> Item totals</h3>
									<p>Required vs brought for each material — by class and by hostel.</p>
								</div>
								<div class="smc-item-tabs">
									<button type="button" class="smc-item-tab is-active" data-totals="class">By class</button>
									<button type="button" class="smc-item-tab" data-totals="hostel">By hostel</button>
								</div>
							</div>
							<div id="smcItemTotalsBody" class="smc-item-totals-body"></div>
							<div class="smc-item-totals-actions">
								<a href="#" class="btn btn-sm btn-outline-danger" id="smcPdfBtn" target="_blank" rel="noopener">
									<i class="fa fa-file-pdf-o"></i> Download A4 PDF
								</a>
							</div>
						</div>

						<div class="smc-activity" id="smcActivity" style="display:none;">
							<div class="smc-activity-head">
								<h3><i class="fa fa-history"></i> Check activity</h3>
								<p>Who saved material checks for this class (visible to accountant, headmaster, and managers).</p>
							</div>
							<div id="smcActivityBody" class="smc-activity-body"></div>
						</div>
					</div>

					<div class="smc-body" id="smcBody">
						<section class="smc-roster" id="smcRoster" style="display:none;">
							<div class="smc-roster-head">
								<div class="smc-roster-title-row">
									<h3><i class="fa fa-users"></i> Students</h3>
									<input type="search" class="form-control form-control-sm smc-roster-filter" id="smcRosterFilter" placeholder="Filter by name or reg no…" autocomplete="off">
								</div>
								<div class="smc-roster-filters" id="smcRosterFilters"></div>
							</div>
							<div class="smc-roster-table-wrap">
								<table class="table table-sm mb-0 smc-roster-table">
									<thead>
									<tr>
										<th>#</th>
										<th>Reg no</th>
										<th>Name</th>
										<th>Hostel</th>
										<th>Checked by</th>
										<th class="text-center">Status</th>
									</tr>
									</thead>
									<tbody id="smcRosterBody"></tbody>
								</table>
							</div>
							<div class="smc-roster-foot" id="smcRosterFoot"></div>
						</section>

						<section class="smc-detail" id="smcDetail">
							<div class="smc-student-hero" id="smcStudentCard">
								<div class="smc-student-empty">
									<div class="smc-empty-icon"><i class="fa fa-clipboard-check"></i></div>
									<h3>Ready to check materials</h3>
									<p>Use live search above, select a class student, or scan a card.</p>
								</div>
							</div>

							<div class="smc-check-section" id="smcCheckSection" style="display:none;">
								<div class="smc-last-check" id="smcLastCheck" style="display:none;"></div>
								<div class="smc-kpi-grid smc-student-kpi" id="smcSummary"></div>
								<div class="smc-table-wrap">
									<table class="table mb-0 smc-mat-table" id="smcMatTable">
										<thead>
										<tr>
											<th>Material</th>
											<th class="text-center">Required</th>
											<th class="text-center">Brought</th>
											<th class="text-center">Missing</th>
											<th>Status</th>
										</tr>
										</thead>
										<tbody></tbody>
									</table>
								</div>
								<div class="smc-actions">
									<button type="button" class="btn btn-success btn-lg" id="smcSaveBtn">
										<i class="fa fa-save"></i> Save material check
									</button>
									<span class="smc-save-as" id="smcSaveAs">Will be recorded as <?= esc($checkerLabel) ?></span>
								</div>
							</div>
						</section>
					</div>
				</div>
			</main>
		</div>
	</div>
</div>

<script src="<?= base_url('assets/js/card-uid.js') ?>"></script>
<script>
$(function () {
	const CHECKER = <?= json_encode([
		'name' => $checkerName,
		'post' => $checkerPost,
		'label' => $checkerLabel,
	], JSON_UNESCAPED_UNICODE) ?>;

	let currentStudent = null;
	let cardBuffer = '';
	let selectedClassId = null;
	let rosterStudents = [];
	let reportFilter = 'all';
	let classMaterials = [];
	let itemTotalsView = 'class';
	let classItemTotals = [];
	let classHostelTotals = [];
	let findTimer = null;
	let findReq = null;
	let findSeq = 0;

	function setScanStatus(msg, kind) {
		const $el = $('#cardScanStatus');
		if (!$el.length) return;
		$el.text(msg || '').attr('data-kind', kind || '');
	}

	function esc(s) {
		return $('<span>').text(s == null ? '' : String(s)).html();
	}

	function fmtWhen(iso) {
		if (!iso) return '';
		const d = new Date(String(iso).replace(' ', 'T'));
		if (isNaN(d.getTime())) return String(iso);
		return d.toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
	}

	function checkerLine(name, post, at) {
		const who = [name, post].filter(Boolean).join(' · ');
		if (!who && !at) return '';
		let line = who || 'Staff';
		if (at) line += ' · ' + fmtWhen(at);
		return line;
	}

	function overallBadge(st) {
		const map = {
			complete: ['Complete', 'complete'],
			partial: ['Partial', 'partial'],
			missing: ['Missing', 'missing'],
			unchecked: ['Not checked', 'unchecked'],
			none: ['No materials', 'none']
		};
		const m = map[st] || map.missing;
		return '<span class="smc-pill ' + m[1] + '">' + m[0] + '</span>';
	}

	function statusBadge(st) {
		if (st === 'complete') return '<span class="smc-pill complete">Complete</span>';
		if (st === 'partial') return '<span class="smc-pill partial">Partial</span>';
		return '<span class="smc-pill missing">Missing</span>';
	}

	function renderKpiGrid(target, items) {
		let html = '';
		items.forEach(function (k) {
			html += '<div class="smc-kpi-card">' +
				'<div class="smc-kpi-icon ' + k.color + '"><i class="fa ' + k.icon + '"></i></div>' +
				'<div class="smc-kpi-value">' + k.value + '</div>' +
				'<div class="smc-kpi-label">' + k.label + '</div></div>';
		});
		$(target).html(html);
	}

	function renderClassKpi(kpi, materialCount, classLabel) {
		const total = kpi.total || 0;
		const complete = kpi.complete || 0;
		const pct = total > 0 ? Math.round((complete / total) * 100) : 0;
		$('#smcDashTitle').text(classLabel || 'Class overview');
		$('#smcDashSub').text(materialCount + ' required material' + (materialCount === 1 ? '' : 's') + ' · ' + total + ' student' + (total === 1 ? '' : 's'));
		$('#smcDashMeta').html('<span class="smc-meta-chip"><i class="fa fa-calendar"></i> ' + esc($('#smcYear option:selected').text()) + '</span>');
		renderKpiGrid('#smcClassKpi', [
			{ icon: 'fa-users', color: 'indigo', value: total, label: 'Students' },
			{ icon: 'fa-check-circle', color: 'green', value: complete, label: 'Fully supplied' },
			{ icon: 'fa-adjust', color: 'orange', value: kpi.partial || 0, label: 'Partial' },
			{ icon: 'fa-times-circle', color: 'red', value: kpi.missing || 0, label: 'Missing' },
			{ icon: 'fa-clock-o', color: 'slate', value: kpi.unchecked || 0, label: 'Not checked' }
		]);
		if (total > 0 && materialCount > 0) {
			$('#smcClassProgress').show();
			$('#smcProgressPct').text(pct + '%');
			$('#smcProgressFill').css('width', pct + '%');
		} else {
			$('#smcClassProgress').hide();
		}
		$('#smcDash').show();
	}

	function renderRoster(students) {
		rosterStudents = students || [];
		filterRoster($('#smcRosterFilter').val());
		$('#smcRosterFoot').text(rosterStudents.length + ' student' + (rosterStudents.length === 1 ? '' : 's'));
	}

	function filterRoster(term) {
		term = (term || '').toLowerCase().trim();
		const statusFilter = reportFilter;
		const $tb = $('#smcRosterBody').empty();
		let n = 0;
		rosterStudents.forEach(function (st) {
			if (statusFilter !== 'all') {
				const ov = st.overall || '';
				if (statusFilter === 'unchecked') {
					if (ov !== 'unchecked' && ov !== 'none') return;
				} else if (ov !== statusFilter) {
					return;
				}
			}
			const hay = (st.regno + ' ' + st.name).toLowerCase();
			if (term && hay.indexOf(term) === -1) return;
			n++;
			const active = currentStudent && currentStudent.id === st.id ? ' active' : '';
			const by = checkerLine(st.checker_name, st.checker_post, st.checked_at);
			$tb.append(
				'<tr class="smc-roster-row' + active + '" data-id="' + st.id + '">' +
				'<td class="text-muted">' + n + '</td>' +
				'<td class="smc-reg">' + esc(st.regno) + '</td>' +
				'<td class="smc-name">' + esc(st.name) + '</td>' +
				'<td class="smc-hostel">' + esc(st.hostel_name || '—') + '</td>' +
				'<td class="smc-checked-by">' + (by ? esc(by) : '<span class="text-muted">—</span>') + '</td>' +
				'<td class="text-center">' + overallBadge(st.overall) + '</td></tr>'
			);
		});
		if (!n) {
			$tb.html('<tr><td colspan="6" class="text-muted text-center py-4">No students match your filter.</td></tr>');
		}
	}

	function renderStudent(st) {
		const by = checkerLine(st.checker_name, st.checker_post, st.checked_at);
		const html =
			'<div class="smc-hero-photo">' + (st.photo_html || '<div class="smc-photo-ph"><i class="fa fa-user"></i></div>') + '</div>' +
			'<div class="smc-hero-info">' +
			'<div class="smc-hero-top">' +
			'<h3>' + esc(st.name || '') + '</h3>' +
			(st.overall ? overallBadge(st.overall) : '') +
			'</div>' +
			'<div class="smc-hero-meta">' +
			'<span><i class="fa fa-id-card"></i> ' + esc(st.regno || '') + '</span>' +
			'<span><i class="fa fa-university"></i> ' + esc(st.class_label || '') + '</span>' +
			(by ? '<span><i class="fa fa-user-circle-o"></i> ' + esc(by) + '</span>' : '') +
			'</div></div>';
		$('#smcStudentCard').addClass('has-student').html(html);
	}

	function renderLastCheck(meta) {
		const $box = $('#smcLastCheck');
		if (!meta || (!meta.checker_name && !meta.checked_at)) {
			$box.hide().empty();
			return;
		}
		const line = checkerLine(meta.checker_name, meta.checker_post, meta.checked_at);
		$box.html('<i class="fa fa-stamp"></i> Last saved by <strong>' + esc(line) + '</strong>').show();
	}

	function clearStudent() {
		currentStudent = null;
		$('#smcStudentCard').removeClass('has-student').html(
			'<div class="smc-student-empty">' +
			'<div class="smc-empty-icon"><i class="fa fa-clipboard-check"></i></div>' +
			'<h3>Ready to check materials</h3>' +
			'<p>Use live search above, select a class student, or scan a card.</p></div>'
		);
		$('#smcCheckSection').hide();
		$('#smcLastCheck').hide().empty();
		$('.smc-roster-row').removeClass('active');
	}

	function renderRosterFilters(kpi) {
		const k = kpi || {};
		const items = [
			{ key: 'all', label: 'All', count: k.total || 0 },
			{ key: 'complete', label: 'Finished', count: k.complete || 0 },
			{ key: 'partial', label: 'Partial', count: k.partial || 0 },
			{ key: 'missing', label: 'Missing', count: k.missing || 0 },
			{ key: 'unchecked', label: 'Not checked', count: k.unchecked || 0 }
		];
		let html = '';
		items.forEach(function (it) {
			const active = reportFilter === it.key ? ' active' : '';
			html += '<button type="button" class="smc-filter-pill' + active + '" data-filter="' + it.key + '">' +
				'<span class="smc-filter-count">' + it.count + '</span>' + it.label + '</button>';
		});
		$('#smcRosterFilters').html(html);
	}

	function fmtQty(n) {
		const x = Number(n) || 0;
		return (Math.round(x * 100) / 100).toString();
	}

	function renderItemTotalsTable(rows) {
		if (!rows || !rows.length) {
			return '<p class="text-muted mb-0">No required materials assigned to this class yet.</p>';
		}
		let html = '<div class="smc-totals-table-wrap"><table class="table table-sm mb-0 smc-totals-table"><thead><tr>' +
			'<th>Item</th><th class="text-right">Each</th><th class="text-right">Students</th>' +
			'<th class="text-right">Required</th><th class="text-right">Brought</th><th class="text-right">Missing</th>' +
			'<th class="text-center">Done</th></tr></thead><tbody>';
		rows.forEach(function (r) {
			const unit = r.unit ? ' ' + r.unit : '';
			const done = (r.students_complete || 0) + '/' + (r.students || 0);
			html += '<tr>' +
				'<td><strong>' + esc(r.name || '') + '</strong>' +
					(r.unit ? '<small class="text-muted"> · ' + esc(r.unit) + '</small>' : '') + '</td>' +
				'<td class="text-right">' + fmtQty(r.qty_each) + unit + '</td>' +
				'<td class="text-right">' + (r.students || 0) + '</td>' +
				'<td class="text-right">' + fmtQty(r.required_total) + '</td>' +
				'<td class="text-right smc-qty-ok">' + fmtQty(r.brought_total) + '</td>' +
				'<td class="text-right smc-qty-miss">' + fmtQty(r.missing_total) + '</td>' +
				'<td class="text-center"><span class="smc-done-pill">' + done + '</span></td></tr>';
		});
		html += '</tbody></table></div>';
		return html;
	}

	function renderItemTotals() {
		const $body = $('#smcItemTotalsBody');
		if (itemTotalsView === 'hostel') {
			const groups = classHostelTotals || [];
			if (!groups.length) {
				$body.html('<p class="text-muted mb-0">No hostel data for this class yet.</p>');
			} else {
				let html = '';
				groups.forEach(function (g) {
					html += '<div class="smc-hostel-block">' +
						'<div class="smc-hostel-block-head">' +
							'<strong><i class="fa fa-bed"></i> ' + esc(g.hostel_name || 'Hostel') + '</strong>' +
							'<span>' + (g.student_count || 0) + ' student' + ((g.student_count || 0) === 1 ? '' : 's') + '</span>' +
						'</div>' +
						renderItemTotalsTable(g.item_totals || []) +
					'</div>';
				});
				$body.html(html);
			}
		} else {
			$body.html(renderItemTotalsTable(classItemTotals || []));
		}
		$('#smcItemTotals').show();
		updatePdfLink();
	}

	function renderActivity(rows) {
		const $box = $('#smcActivityBody');
		if (!rows || !rows.length) {
			$box.html('<p class="text-muted mb-0">No saved checks yet for this class.</p>');
			$('#smcActivity').show();
			return;
		}
		let html = '<ul class="smc-activity-list">';
		rows.forEach(function (a) {
			const who = checkerLine(a.checker_name, a.checker_post, a.checked_at);
			html += '<li data-id="' + a.student_id + '">' +
				'<div class="smc-act-main">' +
					'<strong>' + esc(a.student_name || '') + '</strong>' +
					'<span class="smc-act-reg">' + esc(a.regno || '') + '</span>' +
				'</div>' +
				'<div class="smc-act-meta">' +
					'<span><i class="fa fa-user"></i> ' + esc(who) + '</span>' +
					'<span>' + (a.items_checked || 0) + ' item' + ((a.items_checked || 0) === 1 ? '' : 's') + '</span>' +
				'</div></li>';
		});
		html += '</ul>';
		$box.html(html);
		$('#smcActivity').show();
	}

	function updatePdfLink() {
		if (!selectedClassId) return;
		const year = $('#smcYear').val();
		const url = '<?= base_url('student_material_report_pdf/') ?>' + selectedClassId +
			'?year=' + encodeURIComponent(year) + '&filter=' + encodeURIComponent(reportFilter);
		$('#smcPdfBtn').attr('href', url);
	}

	function renderSmartReport(students, kpi, materials, itemTotals, hostelTotals, activity) {
		reportFilter = 'all';
		itemTotalsView = 'class';
		$('.smc-item-tab').removeClass('is-active');
		$('.smc-item-tab[data-totals="class"]').addClass('is-active');
		classMaterials = materials || [];
		classItemTotals = itemTotals || [];
		classHostelTotals = hostelTotals || [];
		renderRosterFilters(kpi);
		renderItemTotals();
		renderActivity(activity || []);
	}

	function showRoster(show) {
		if (show) {
			$('#smcRoster').show();
			$('#smcBody').addClass('has-roster');
		} else {
			$('#smcRoster').hide();
			$('#smcBody').removeClass('has-roster');
			$('#smcDash').hide();
			$('#smcItemTotals').hide();
			$('#smcActivity').hide();
		}
	}

	function loadClassStudents(classId) {
		const year = $('#smcYear').val();
		if (!classId || !year) return;
		selectedClassId = classId;
		const classLabel = $('#search_class option:selected').text().trim();
		setScanStatus('Loading class…', 'busy');
		$.getJSON('<?= base_url('student_material_class_overview/') ?>' + classId + '?year=' + year, function (res) {
			if (!res.success) {
				setScanStatus(res.error || 'Failed', 'err');
				toastada.error(res.error || 'Could not load class.');
				return;
			}
			renderClassKpi(res.class_kpi || {}, res.material_count || 0, classLabel);
			renderRoster(res.students || []);
			renderSmartReport(res.students || [], res.class_kpi || {}, res.materials || [], res.item_totals || [], res.hostel_totals || [], res.recent_activity || []);
			showRoster(true);
			clearStudent();
			setScanStatus('Select a student from the list', 'ok');
		}).fail(function () {
			setScanStatus('Could not load class', 'err');
		});
	}

	function renderChecklist(items, summary) {
		const $tb = $('#smcMatTable tbody').empty();
		if (!items.length) {
			$tb.html('<tr><td colspan="5" class="text-muted text-center py-4">No materials assigned to this class. Configure them in School Settings.</td></tr>');
			$('#smcSummary').html('');
			$('#smcCheckSection').show();
			return;
		}
		items.forEach(function (it) {
			$tb.append(
				'<tr data-material-id="' + it.material_id + '" data-required="' + it.quantity_required + '">' +
				'<td><span class="smc-mat-name">' + esc(it.name) + '</span>' +
				'<small class="text-muted">' + esc(it.unit) + '</small></td>' +
				'<td class="text-center smc-num">' + it.quantity_required + '</td>' +
				'<td class="text-center"><input type="number" min="0" step="0.01" class="form-control form-control-sm smc-brought" value="' + it.quantity_brought + '"></td>' +
				'<td class="text-center smc-num smc-missing">' + it.quantity_missing + '</td>' +
				'<td class="smc-status">' + statusBadge(it.status) + '</td></tr>'
			);
		});
		const s = summary || {};
		const total = s.total || items.length;
		renderKpiGrid('#smcSummary', [
			{ icon: 'fa-list', color: 'indigo', value: total, label: 'Materials' },
			{ icon: 'fa-check', color: 'green', value: s.complete || 0, label: 'Complete' },
			{ icon: 'fa-adjust', color: 'orange', value: s.partial || 0, label: 'Partial' },
			{ icon: 'fa-times', color: 'red', value: s.missing || 0, label: 'Missing' }
		]);
		$('#smcCheckSection').show();
		updateLiveKpi();
	}

	function updateLiveKpi() {
		let complete = 0, partial = 0, missing = 0, total = 0;
		$('#smcMatTable tbody tr[data-material-id]').each(function () {
			total++;
			const req = parseFloat($(this).data('required')) || 0;
			const brought = parseFloat($(this).find('.smc-brought').val()) || 0;
			if (brought >= req && req > 0) complete++;
			else if (brought > 0) partial++;
			else missing++;
		});
		if (!total) return;
		renderKpiGrid('#smcSummary', [
			{ icon: 'fa-list', color: 'indigo', value: total, label: 'Materials' },
			{ icon: 'fa-check', color: 'green', value: complete, label: 'Complete' },
			{ icon: 'fa-adjust', color: 'orange', value: partial, label: 'Partial' },
			{ icon: 'fa-times', color: 'red', value: missing, label: 'Missing' }
		]);
	}

	function recomputeRow($tr) {
		const req = parseFloat($tr.data('required')) || 0;
		const brought = parseFloat($tr.find('.smc-brought').val()) || 0;
		const missing = Math.max(0, req - brought);
		let st = 'missing';
		if (brought >= req && req > 0) st = 'complete';
		else if (brought > 0) st = 'partial';
		$tr.find('.smc-missing').text(missing);
		$tr.find('.smc-status').html(statusBadge(st));
		updateLiveKpi();
	}

	function refreshRosterRow(studentId, overall) {
		const st = rosterStudents.find(function (s) { return s.id === studentId; });
		if (st) st.overall = overall;
		filterRoster($('#smcRosterFilter').val());
	}

	function loadStudent(studentId) {
		const year = $('#smcYear').val();
		if (!studentId || !year) return;
		setScanStatus('Loading…', 'busy');
		$.getJSON('<?= base_url('student_material_check_context/') ?>' + studentId + '?year=' + year, function (res) {
			if (!res.success) {
				setScanStatus(res.error || 'Not found', 'err');
				toastada.error(res.error || 'Could not load student.');
				return;
			}
			currentStudent = res.student;
			currentStudent.overall = (res.summary || {}).overall;
			renderStudent(currentStudent);
			renderLastCheck(res.last_check || currentStudent);
			renderChecklist(res.materials || [], res.summary || {});
			$('.smc-roster-row').removeClass('active');
			$('.smc-roster-row[data-id="' + res.student.id + '"]').addClass('active');
			setScanStatus('✅ ' + res.student.name, 'ok');
		}).fail(function () {
			setScanStatus('Network error', 'err');
		});
	}

	function handleCardScan(uid) {
		setScanStatus('Checking card…', 'busy');
		fetch('<?= base_url('api/permission_card_scan') ?>', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'card=' + encodeURIComponent(uid) + '&school_id=<?= (int) session('soma_school_id') ?>'
		}).then(r => r.json()).then(res => {
			if (!res.success || res.error || !res.student) {
				setScanStatus(res.error || 'Card not found', 'err');
				return;
			}
			showRoster(false);
			loadStudent(res.student.id);
		}).catch(function () { setScanStatus('Scan failed', 'err'); });
	}

	function setFindBusy(on) {
		$('#smcFindSpin').toggleClass('is-on', !!on);
	}

	function syncFindClear() {
		$('#smcFindClear').toggleClass('is-on', $.trim($('#smcFindQ').val() || '').length > 0);
	}

	function renderFindEmpty(msg) {
		$('#smcFindResult').html('<div class="smc-find-hint">' + esc(msg) + '</div>');
	}

	function runStudentFind() {
		const q = $.trim($('#smcFindQ').val() || '');
		const $box = $('#smcFindResult');
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
		const seq = ++findSeq;
		setFindBusy(true);
		findReq = $.ajax({
			url: '<?= base_url('student_material_student_search') ?>',
			dataType: 'json',
			data: { year: $('#smcYear').val(), q: q }
		}).done(function (res) {
			if (seq !== findSeq) return;
			if (res.error) {
				renderFindEmpty(res.error);
				return;
			}
			const rows = res.students || [];
			if (!rows.length) {
				renderFindEmpty('No students match “' + q + '”.');
				return;
			}
			let html = '<ul class="smc-find-list">';
			rows.forEach(function (s) {
				html += '<li class="smc-find-item" data-id="' + s.id + '">' +
					'<div class="smc-find-main">' +
						'<span class="smc-find-name">' + esc(s.name || '') +
							(s.regno ? ' <span class="smc-find-reg">· ' + esc(s.regno) + '</span>' : '') +
						'</span>' +
						'<div class="smc-find-pills">' +
							'<span class="smc-pill-soft"><i class="fa fa-graduation-cap"></i> ' + esc(s.class_label || 'No class') + '</span>' +
							(s.hostel_name ? '<span class="smc-pill-soft"><i class="fa fa-bed"></i> ' + esc(s.hostel_name) + '</span>' : '') +
						'</div>' +
					'</div>' +
					'<button type="button" class="smc-find-open">Check</button>' +
				'</li>';
			});
			html += '</ul>';
			$box.html(html);
		}).fail(function (_xhr, status) {
			if (status === 'abort' || seq !== findSeq) return;
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
		findTimer = setTimeout(runStudentFind, 180);
	}

	$('#smcFindQ')
		.on('focus', function () { $('#smcFindShell').addClass('is-focused'); })
		.on('blur', function () { $('#smcFindShell').removeClass('is-focused'); })
		.on('input', scheduleFind)
		.on('keydown', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				$('#smcFindQ').val('');
				syncFindClear();
				$('#smcFindResult').empty();
			}
		});

	$('#smcFindClear').on('click', function () {
		$('#smcFindQ').val('').focus();
		syncFindClear();
		$('#smcFindResult').empty();
	});

	$(document).on('click', '.smc-find-item, .smc-find-open', function (e) {
		e.preventDefault();
		const id = $(this).closest('.smc-find-item').data('id') || $(this).data('id');
		if (!id) return;
		$('#smcFindQ').val('');
		syncFindClear();
		$('#smcFindResult').empty();
		showRoster(false);
		loadStudent(id);
	});

	$(document).on('click', '.smc-activity-list li', function () {
		const id = $(this).data('id');
		if (id) loadStudent(id);
	});

	$('#search_mode').on('change', function () {
		const mode = $(this).val();
		if (mode === 'card') setScanStatus('Waiting for card…', '');
		if (mode === 'class') {
			const classId = $('#search_class').val();
			if (classId) loadClassStudents(classId);
		} else {
			showRoster(false);
		}
	});

	$('#search_class').on('select2:select change', function (e) {
		const classId = e.params ? e.params.data.id : $(this).val();
		if (classId) loadClassStudents(classId);
	});

	$(document).on('click', '.smc-filter-pill', function () {
		reportFilter = $(this).data('filter') || 'all';
		$('.smc-filter-pill').removeClass('active');
		$(this).addClass('active');
		filterRoster($('#smcRosterFilter').val());
		updatePdfLink();
	});

	$(document).on('click', '.smc-item-tab', function () {
		$('.smc-item-tab').removeClass('is-active');
		$(this).addClass('is-active');
		itemTotalsView = $(this).data('totals') || 'class';
		renderItemTotals();
	});

	$(document).on('click', '.smc-roster-row', function () {
		loadStudent(parseInt($(this).data('id'), 10));
	});

	$('#smcRosterFilter').on('input', function () {
		filterRoster($(this).val());
	});

	$(document).on('input', '.smc-brought', function () {
		recomputeRow($(this).closest('tr'));
	});

	$('#smcSaveBtn').on('click', function () {
		if (!currentStudent) return;
		const items = [];
		$('#smcMatTable tbody tr').each(function () {
			const mid = $(this).data('material-id');
			if (!mid) return;
			items.push({
				material_id: mid,
				quantity_required: parseFloat($(this).data('required')) || 0,
				quantity_brought: parseFloat($(this).find('.smc-brought').val()) || 0
			});
		});
		const $btn = $(this).prop('disabled', true);
		$.post('<?= base_url('save_student_material_check') ?>', {
			student_id: currentStudent.id,
			class_id: currentStudent.class_id,
			year: $('#smcYear').val(),
			items: JSON.stringify(items)
		}, function (res) {
			$btn.prop('disabled', false);
			if (res.success) {
				toastada.success(res.success);
				loadStudent(currentStudent.id);
				if (selectedClassId) loadClassStudents(selectedClassId);
			} else toastada.error(res.error || 'Save failed.');
		}, 'json').fail(function () {
			$btn.prop('disabled', false);
			toastada.error('Save failed.');
		});
	});

	$('#smcYear').on('change', function () {
		if ($('#search_mode').val() === 'class' && selectedClassId) {
			loadClassStudents(selectedClassId);
		} else if (currentStudent) {
			loadStudent(currentStudent.id);
		} else {
			clearStudent();
		}
		if ($.trim($('#smcFindQ').val() || '').length >= 2) runStudentFind();
	});

	$('#student_search_input').on('keyup', function () {
		const term = $(this).val().trim();
		if (term.length < 2) { $('#student_search_box').hide().empty(); return; }
		$.getJSON('<?= base_url('student_material_student_search') ?>', { year: $('#smcYear').val(), q: term }, function (res) {
			let html = '';
			const rows = res.students || [];
			if (!rows.length) html = "<div class='text-muted text-center p-2'>No students found</div>";
			else rows.forEach(function (st) {
				html += "<div class='card-scan-student-item student-item' data-id='" + st.id + "'>" +
					esc(st.name) + " <small class='text-muted'>" + esc(st.regno || '') + " · " + esc(st.class_label || '') + "</small></div>";
			});
			$('#student_search_box').html(html).show();
		});
	});

	$(document).on('click', '.student-item', function () {
		$('#student_search_input').val('');
		$('#student_search_box').hide();
		showRoster(false);
		loadStudent($(this).data('id'));
	});

	$(document).on('keypress', function (e) {
		if ($('#search_mode').val() !== 'card') return;
		if (e.key === 'Enter') {
			const uid = cardBuffer.trim();
			cardBuffer = '';
			if (uid.length >= 4) {
				handleCardScan((window.CardUid && CardUid.forScan) ? CardUid.forScan(uid) : uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase());
			}
		} else if (e.key && e.key.length === 1) {
			cardBuffer += e.key;
		}
	});

	if ($('#search_mode').val() === 'class' && $('#search_class').val()) {
		loadClassStudents($('#search_class').val());
	}
});
</script>
