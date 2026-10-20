<?php
/** @var array $fees */
/** @var array $feeGroups */
/** @var array $years */
/** @var int $selectedYear */
/** @var string $selectedYearTitle */
/** @var int $feeCount */
/** @var float $feeTotalAmount */
/** @var int $feeLevelCount */
$feeGroups = $feeGroups ?? \App\Models\SchoolFeesModel::groupByLevelDept($fees ?? []);
$studentOverrideCounts = $studentOverrideCounts ?? [];
$uniqueLevels = [];
foreach ($feeGroups as $g) {
	$uniqueLevels[$g['display_label']] = $g['display_label'];
}
ksort($uniqueLevels, SORT_NATURAL | SORT_FLAG_CASE);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/school-fees.css'); ?>">

<div class="sf-page" id="schoolFeesPage">
	<div class="sf-center">

		<div class="sf-header">
			<h2><?= esc($title ?? lang('app.schoolFees')); ?></h2>
			<p>
				<?= esc($selectedYearTitle !== '' ? $selectedYearTitle : lang('app.academicYear')); ?>
				<?php if (!empty($term)) : ?>
					· <?= esc(\App\Controllers\Home::TermToStr((int) $term)); ?>
				<?php endif; ?>
			</p>
			<div class="sf-header-actions">
				<button type="button" class="btn sf-btn-add" data-toggle="modal" data-target="#mdlfees">
					<i class="fa fa-plus-circle"></i> <?= lang('app.addNewFee'); ?>
				</button>
			</div>
		</div>

		<div class="sf-kpi-grid">
			<div class="sf-kpi">
				<div class="sf-kpi-icon blue"><i class="fa fa-list"></i></div>
				<div class="sf-kpi-value"><?= (int) $feeCount; ?></div>
				<div class="sf-kpi-label"><?= lang('app.schoolFees'); ?></div>
			</div>
			<div class="sf-kpi">
				<div class="sf-kpi-icon green"><i class="fa fa-layer-group"></i></div>
				<div class="sf-kpi-value"><?= (int) $feeLevelCount; ?></div>
				<div class="sf-kpi-label"><?= lang('app.classes'); ?></div>
			</div>
			<div class="sf-kpi">
				<div class="sf-kpi-icon orange"><i class="fa fa-coins"></i></div>
				<div class="sf-kpi-value"><?= number_format((float) $feeTotalAmount); ?></div>
				<div class="sf-kpi-label"><?= lang('app.amount'); ?></div>
			</div>
		</div>

		<div class="sf-filter-card">
			<div class="sf-filter-row">
				<div class="sf-field">
					<label for="academicYearSelect"><?= lang('app.academicYear'); ?></label>
					<select class="form-control select2" id="academicYearSelect">
						<?php foreach ($years as $year) : ?>
							<option value="<?= (int) $year['id']; ?>"
								<?= (int) $year['id'] === (int) $selectedYear ? 'selected' : ''; ?>>
								<?= esc($year['title']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php if (!empty($feeGroups)) : ?>
				<div class="sf-field">
					<label for="sfLevelFilter"><?= lang('app.selectClass'); ?></label>
					<select class="form-control" id="sfLevelFilter">
						<option value="">All classes</option>
						<?php foreach ($uniqueLevels as $lvl) : ?>
							<option value="<?= esc($lvl, 'attr'); ?>"><?= esc($lvl); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sf-field sf-search-field">
					<label for="sfSearch">Search</label>
					<input type="text" class="form-control" id="sfSearch" placeholder="Search class or department…">
				</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="sf-panel">
			<div class="sf-panel-head">
				<h3><?= lang('app.schoolFeesManagement'); ?></h3>
				<span class="sf-badge" id="sfVisibleCount"><?= count($feeGroups); ?> classes · <?= (int) $feeCount; ?> fees</span>
			</div>
			<div class="sf-panel-body">
				<?php if (empty($feeGroups)) : ?>
					<div class="sf-empty">
						<i class="fa fa-inbox"></i>
						<h4>No classes found</h4>
						<p>No classes are available for <?= esc($selectedYearTitle); ?>. Add a class first, then set school fees.</p>
					</div>
				<?php else : ?>
					<div class="sf-table-wrap">
						<table id="schoolFeesTable" class="table mb-0">
							<thead>
								<tr>
									<th><?= lang('app.selectClass'); ?></th>
									<th><?= lang('app.selectDepartment'); ?></th>
									<th class="sf-term-col-h"><?= lang('app.term1'); ?></th>
									<th class="sf-term-col-h"><?= lang('app.term2'); ?></th>
									<th class="sf-term-col-h"><?= lang('app.term3'); ?></th>
									<th class="sf-actions-col"><?= lang('app.Actions'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($feeGroups as $group) :
									$searchText = strtolower($group['display_label'] . ' ' . $group['dept_code'] . ' ' . $group['dept_title'] . ' ' . ($group['faculty_code'] ?? '') . ' ' . ($group['faculty_title'] ?? '') . ' ' . ($group['class_title'] ?? ''));
									$hasAnyTerm = !empty($group['terms'][1]) || !empty($group['terms'][2]) || !empty($group['terms'][3]);
									?>
									<tr class="sf-group-row"
										data-level="<?= esc($group['display_label'], 'attr'); ?>"
										data-search="<?= esc($searchText, 'attr'); ?>"
										data-level-id="<?= (int) $group['level_id']; ?>"
										data-dept-id="<?= (int) $group['department_id']; ?>"
										data-class-id="<?= (int) ($group['class_id'] ?? 0); ?>">
										<td class="sf-level-cell">
											<span class="sf-level-pill"><?= esc($group['display_label']); ?></span>
										</td>
										<td>
											<?php if (!empty($group['dept_code'])) : ?>
												<span class="sf-dept-tag"><?= esc($group['dept_code']); ?></span>
											<?php endif; ?>
											<?php if (!empty($group['dept_title'])) : ?>
												<span class="sf-dept-name"><?= esc($group['dept_title']); ?></span>
											<?php endif; ?>
										</td>
										<?php for ($t = 1; $t <= 3; $t++) :
											$termFee = $group['terms'][$t] ?? null;
											$modes = $termFee ? \App\Models\SchoolFeesModel::modeAmounts($termFee) : null;
											?>
											<td class="sf-term-col t<?= $t; ?>">
												<?php if ($termFee) : ?>
													<div class="sf-term-cell">
														<div class="sf-mode-amounts">
															<div class="sf-mode-line boarding">
																<span class="sf-mode-label"><?= lang('app.boarding'); ?></span>
																<span class="sf-amount"><?= $modes['boarding'] !== null ? number_format((float) $modes['boarding']) : '—'; ?></span>
															</div>
															<div class="sf-mode-line day">
																<span class="sf-mode-label"><?= lang('app.day'); ?></span>
																<span class="sf-amount"><?= $modes['day'] !== null ? number_format((float) $modes['day']) : '—'; ?></span>
															</div>
														</div>
														<?php if (!empty($termFee['created_by_name'])) : ?>
															<small class="d-block text-muted" style="font-size:.68rem;margin-top:4px;"><?= esc(lang('app.recordedBy')); ?>: <?= esc($termFee['created_by_name']); ?></small>
														<?php endif; ?>
														<div class="sf-term-actions">
															<button type="button" class="sf-icon-btn editFeeBtn" title="<?= lang('app.editFee'); ?><?= !empty($termFee['created_by_name']) ? ' · ' . lang('app.recordedBy') . ': ' . $termFee['created_by_name'] : ''; ?>"
																data-id="<?= (int) $termFee['id']; ?>"
																data-amount="<?= esc((float) ($termFee['amount'] ?? 0), 'attr'); ?>"
																data-boarding="<?= esc($modes['boarding'] !== null ? (float) $modes['boarding'] : '', 'attr'); ?>"
																data-day="<?= esc($modes['day'] !== null ? (float) $modes['day'] : '', 'attr'); ?>"
																data-term="<?= $t; ?>"
																data-term-label="<?= esc(\App\Controllers\Home::TermToStr($t), 'attr'); ?>"
																data-level="<?= esc($group['display_label'], 'attr'); ?>"
																data-dept="<?= esc($group['dept_code'], 'attr'); ?>">
																<i class="fa fa-pen"></i>
															</button>
															<button type="button" class="sf-icon-btn danger delButton" title="Delete"
																data-id="<?= (int) $termFee['id']; ?>">
																<i class="fa fa-trash"></i>
															</button>
														</div>
													</div>
												<?php else : ?>
													<span class="sf-term-empty">—</span>
												<?php endif; ?>
											</td>
										<?php endfor; ?>
										<td class="sf-row-actions">
											<?php
											$gModes = [];
											for ($gt = 1; $gt <= 3; $gt++) {
												$tf = $group['terms'][$gt] ?? null;
												$gModes[$gt] = $tf ? \App\Models\SchoolFeesModel::modeAmounts($tf) : ['boarding' => null, 'day' => null];
											}
											?>
											<?php $customStudents = (int) ($studentOverrideCounts[(int) ($group['class_id'] ?? 0)] ?? 0); ?>
											<button type="button" class="btn btn-sm btn-outline-success sfStudentBtn"
												data-class-id="<?= (int) ($group['class_id'] ?? 0); ?>"
												data-label="<?= esc($group['display_label'], 'attr'); ?>"
												<?= (int) ($group['class_id'] ?? 0) > 0 ? '' : 'disabled'; ?>>
												<i class="fa fa-user"></i> One student<?php if ($customStudents > 0) : ?> <span class="sf-custom-count"><?= $customStudents; ?></span><?php endif; ?>
											</button>
											<button type="button" class="btn btn-sm btn-outline-primary editGroupBtn"
												data-level-id="<?= (int) $group['level_id']; ?>"
												data-dept-id="<?= (int) $group['department_id']; ?>"
												data-class-id="<?= (int) ($group['class_id'] ?? 0); ?>"
												data-level="<?= esc($group['display_label'], 'attr'); ?>"
												data-dept="<?= esc($group['dept_code'], 'attr'); ?>"
												data-boarding-1="<?= esc($gModes[1]['boarding'] !== null ? (float) $gModes[1]['boarding'] : '', 'attr'); ?>"
												data-day-1="<?= esc($gModes[1]['day'] !== null ? (float) $gModes[1]['day'] : '', 'attr'); ?>"
												data-boarding-2="<?= esc($gModes[2]['boarding'] !== null ? (float) $gModes[2]['boarding'] : '', 'attr'); ?>"
												data-day-2="<?= esc($gModes[2]['day'] !== null ? (float) $gModes[2]['day'] : '', 'attr'); ?>"
												data-boarding-3="<?= esc($gModes[3]['boarding'] !== null ? (float) $gModes[3]['boarding'] : '', 'attr'); ?>"
												data-day-3="<?= esc($gModes[3]['day'] !== null ? (float) $gModes[3]['day'] : '', 'attr'); ?>">
												<i class="fa fa-edit"></i> <?= lang('app.editFee'); ?>
											</button>
											<button type="button" class="btn btn-sm btn-outline-danger delGroupBtn"
												data-level-id="<?= (int) $group['level_id']; ?>"
												data-dept-id="<?= (int) $group['department_id']; ?>"
												data-class-id="<?= (int) ($group['class_id'] ?? 0); ?>"
												data-label="<?= esc($group['display_label'], 'attr'); ?>"
												<?= $hasAnyTerm ? '' : 'disabled'; ?>>
												<i class="fa fa-trash"></i>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>

	</div>
</div>

<!-- Edit single term fee -->
<div class="modal fade" id="mdlEditFee" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('update_school_fee'); ?>" class="autoSubmit validate" id="frmEditFee">
				<input type="hidden" name="fee_id" id="edit_fee_id">
				<div class="modal-header">
					<h5 class="modal-title"><?= lang('app.editFee'); ?></h5>
					<button type="button" class="close" data-dismiss="modal"><span>×</span></button>
				</div>
				<div class="modal-body">
					<div class="sf-edit-meta">
						<div><strong><?= lang('app.level'); ?>:</strong> <span id="edit_fee_level">—</span></div>
						<div><strong><?= lang('app.selectDepartment'); ?></strong> <span id="edit_fee_dept">—</span></div>
						<div><strong><?= lang('app.term'); ?>:</strong> <span id="edit_fee_term">—</span></div>
					</div>
					<div class="form-group mt-3">
						<label><?= lang('app.boarding'); ?> <?= lang('app.amount'); ?></label>
						<input type="number" min="0" step="1" name="amount_boarding" id="edit_fee_boarding" class="form-control" required>
					</div>
					<div class="form-group">
						<label><?= lang('app.day'); ?> <?= lang('app.amount'); ?></label>
						<input type="number" min="0" step="1" name="amount_day" id="edit_fee_day" class="form-control" required>
					</div>
					<input type="hidden" name="amount" id="edit_fee_amount" value="">
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" id="edit_apply_all_terms" name="apply_all_terms" value="1">
						<label class="custom-control-label" for="edit_apply_all_terms"><?= lang('app.allTermsSameAmount'); ?></label>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal"><?= lang('app.close'); ?></button>
					<button type="submit" class="btn btn-primary" data-target="reload"><?= lang('app.save'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Edit all terms for a level -->
<div class="modal fade" id="mdlEditFeeGroup" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('update_school_fee'); ?>" class="autoSubmit validate" id="frmEditFeeGroup">
				<input type="hidden" name="level_id" id="edit_group_level_id">
				<input type="hidden" name="department_id" id="edit_group_dept_id">
				<input type="hidden" name="class_id" id="edit_group_class_id">
				<div class="modal-header">
					<h5 class="modal-title"><?= lang('app.editFee'); ?> — <span id="edit_group_title"></span></h5>
					<button type="button" class="close" data-dismiss="modal"><span>×</span></button>
				</div>
				<div class="modal-body">
					<p class="text-muted small mb-3">Update boarding and day amounts per term. Leave blank to keep unchanged.</p>
					<?php for ($et = 1; $et <= 3; $et++) : ?>
					<div class="border rounded p-2 mb-2">
						<strong class="d-block mb-2"><?= lang('app.term' . $et); ?></strong>
						<div class="form-row">
							<div class="form-group col-md-6 mb-1">
								<label class="small mb-0"><?= lang('app.boarding'); ?></label>
								<input type="number" min="0" step="1" name="amount_boarding_<?= $et; ?>" id="edit_group_boarding_<?= $et; ?>" class="form-control form-control-sm">
							</div>
							<div class="form-group col-md-6 mb-1">
								<label class="small mb-0"><?= lang('app.day'); ?></label>
								<input type="number" min="0" step="1" name="amount_day_<?= $et; ?>" id="edit_group_day_<?= $et; ?>" class="form-control form-control-sm">
							</div>
						</div>
					</div>
					<?php endfor; ?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal"><?= lang('app.close'); ?></button>
					<button type="submit" class="btn btn-primary" data-target="reload"><?= lang('app.save'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<style>
	.sf-row-actions{white-space:normal;}
	#mdlStudentAmount .modal-dialog{max-width:980px;}
	.stu-amt-layout{display:grid;grid-template-columns:280px 1fr;gap:16px;min-height:360px;}
	.stu-amt-side{border:1px solid #e5e7eb;border-radius:12px;display:flex;flex-direction:column;min-height:0;}
	.stu-amt-side input{border:0;border-bottom:1px solid #e5e7eb;border-radius:12px 12px 0 0;}
	.stu-amt-list{overflow:auto;max-height:420px;}
	.stu-amt-person{display:block;width:100%;text-align:left;border:0;border-bottom:1px solid #f1f5f9;background:#fff;padding:8px 12px;cursor:pointer;}
	.stu-amt-person:hover{background:#f8fafc;}
	.stu-amt-person.active{background:#ecfdf5;}
	.stu-amt-person small{display:block;color:#64748b;}
	.stu-amt-tag{display:inline-block;margin-left:6px;background:#fef3c7;color:#92400e;border-radius:999px;padding:0 7px;font-size:.68rem;font-weight:700;}
	.sf-custom-count{display:inline-block;min-width:1.1rem;padding:0 5px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:.72rem;}
	.stu-amt-editor{border:1px solid #e5e7eb;border-radius:12px;padding:14px;}
	.stu-amt-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;}
	.stu-amt-mode{background:#e0e7ff;color:#3730a3;border-radius:999px;padding:2px 8px;font-size:.75rem;font-weight:700;}
	.stu-amt-mode.day{background:#ecfdf5;color:#047857;}
	.stu-amt-diff{font-size:.75rem;font-weight:700;}
	.stu-amt-diff.down{color:#b45309;}
	.stu-amt-diff.up{color:#047857;}
	.stu-amt-empty{color:#64748b;padding:28px 8px;text-align:center;}
	@media(max-width:800px){.stu-amt-layout{grid-template-columns:1fr;}}
</style>
<div class="modal fade" id="mdlStudentAmount" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">One student — <span id="stuAmtTitle">class</span></h5>
				<button type="button" class="close" data-dismiss="modal"><span>×</span></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small">The class boarding and day amounts stay as they are. Pick a student and type the amount only that student should pay. Leave it equal to the class amount to follow the class again.</p>
				<div id="stuAmtLoading" class="stu-amt-empty">Loading class…</div>
				<div id="stuAmtBody" class="stu-amt-layout" style="display:none">
					<div class="stu-amt-side">
						<input type="search" id="stuAmtFilter" class="form-control" placeholder="Search name or reg no">
						<div class="stu-amt-list" id="stuAmtList"></div>
					</div>
					<div class="stu-amt-editor">
						<div id="stuAmtHint" class="stu-amt-empty">Select a student.</div>
						<div id="stuAmtForm" style="display:none">
							<div class="stu-amt-meta">
								<strong id="stuAmtName"></strong>
								<span id="stuAmtMode" class="stu-amt-mode"></span>
							</div>
							<table class="table table-sm mb-2">
								<thead><tr><th>Term</th><th>Class amount</th><th>This student</th><th></th></tr></thead>
								<tbody id="stuAmtTerms"></tbody>
							</table>
							<div class="custom-control custom-checkbox mb-2">
								<input type="checkbox" class="custom-control-input" id="stuAmtSame">
								<label class="custom-control-label" for="stuAmtSame">Use the first term’s amount for every term</label>
							</div>
							<button type="button" class="btn btn-success" id="stuAmtSave">Save this student</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
(function ($) {
	function applyFilters() {
		var level = ($('#sfLevelFilter').val() || '').toLowerCase();
		var q = ($('#sfSearch').val() || '').toLowerCase().trim();
		var visible = 0;
		$('#schoolFeesTable tbody tr.sf-group-row').each(function () {
			var $row = $(this);
			var rowLevel = ($row.data('level') || '').toString().toLowerCase();
			var rowSearch = ($row.data('search') || '').toString().toLowerCase();
			var okLevel = !level || rowLevel === level;
			var okSearch = !q || rowSearch.indexOf(q) >= 0;
			var show = okLevel && okSearch;
			$row.toggle(show);
			if (show) visible++;
		});
		$('#sfVisibleCount').text(visible + ' classes · <?= (int) $feeCount; ?> fees');
	}

	function bootSchoolFees() {
		$(document).off('change.sfYear', '#academicYearSelect').on('change.sfYear', '#academicYearSelect', function () {
			var val = $(this).val();
			if (val) window.location.href = '<?= base_url('school_fees_management?year='); ?>' + val;
		});

		$('#sfLevelFilter, #sfSearch').on('input change', applyFilters);

		$(document).off('click.sfEdit', '.editFeeBtn').on('click.sfEdit', '.editFeeBtn', function () {
			var $b = $(this);
			$('#edit_fee_id').val($b.data('id'));
			$('#edit_fee_level').text($b.data('level'));
			$('#edit_fee_dept').text($b.data('dept'));
			$('#edit_fee_term').text($b.data('term-label'));
			var boarding = $b.data('boarding');
			var day = $b.data('day');
			$('#edit_fee_boarding').val(boarding !== undefined && boarding !== '' ? boarding : $b.data('amount'));
			$('#edit_fee_day').val(day !== undefined && day !== '' ? day : $b.data('amount'));
			$('#edit_fee_amount').val($b.data('amount'));
			$('#edit_apply_all_terms').prop('checked', false);
			$('#mdlEditFee').modal('show');
		});

		$('#frmEditFee').on('submit', function () {
			var b = Number($('#edit_fee_boarding').val() || 0);
			var d = Number($('#edit_fee_day').val() || 0);
			$('#edit_fee_amount').val(Math.max(b, d));
		});

		$(document).off('click.sfEditGroup', '.editGroupBtn').on('click.sfEditGroup', '.editGroupBtn', function () {
			var $b = $(this);
			$('#edit_group_level_id').val($b.data('level-id'));
			$('#edit_group_dept_id').val($b.data('dept-id'));
			$('#edit_group_class_id').val($b.data('class-id') || 0);
			$('#edit_group_title').text($b.data('level'));
			for (var t = 1; t <= 3; t++) {
				$('#edit_group_boarding_' + t).val($b.data('boarding-' + t) || '');
				$('#edit_group_day_' + t).val($b.data('day-' + t) || '');
			}
			$('#mdlEditFeeGroup').modal('show');
		});

		$(document).off('click.sfDelGroup', '.delGroupBtn').on('click.sfDelGroup', '.delGroupBtn', function () {
			var $b = $(this);
			var label = $b.data('label') || 'this row';
			if (!confirm('Delete all fee terms for ' + label + '?\n\nThis also permanently removes linked student adjustments and payment records.')) {
				return;
			}
			$b.prop('disabled', true);
			$.ajax({
				url: '<?= base_url('deleteSchoolFeeGroup'); ?>',
				method: 'POST',
				data: {
					level_id: $b.data('level-id'),
					department_id: $b.data('dept-id'),
					class_id: $b.data('class-id') || 0
				},
				success: function (res) {
					if (res.success) {
						toastada.success(res.success);
						setTimeout(function () { window.location.reload(); }, 1000);
					} else {
						toastada.error(res.error || 'Delete failed');
						$b.prop('disabled', false);
					}
				},
				error: function (e) {
					toastada.error((e.responseJSON && e.responseJSON.error) || 'Delete failed');
					$b.prop('disabled', false);
				}
			});
		});

		var stuPanel = null;
		var stuSelected = 0;
		var stuSaved = false;
		function stuMoney(n) {
			var v = Math.round(Number(n) || 0);
			return v.toLocaleString();
		}
		function stuClassAmount(term, mode) {
			if (!term) return 0;
			var raw = mode === 0 ? term.boarding : term.day;
			if (raw === null || raw === undefined || raw === '') {
				raw = mode === 0 ? term.day : term.boarding;
			}
			return Number(raw || 0);
		}
		function stuRenderList() {
			var q = ($('#stuAmtFilter').val() || '').toLowerCase().trim();
			var html = '';
			var rows = (stuPanel.students || []).slice().sort(function (a, b) {
				return (b.custom ? 1 : 0) - (a.custom ? 1 : 0);
			});
			rows.forEach(function (st) {
				var hay = ((st.regno || '') + ' ' + (st.name || '')).toLowerCase();
				if (q && hay.indexOf(q) === -1) return;
				html += '<button type="button" class="stu-amt-person' + (st.id === stuSelected ? ' active' : '') + '" data-id="' + st.id + '">' +
					$('<div>').text(st.name || '').html() +
					(st.custom ? '<span class="stu-amt-tag">Custom</span>' : '') +
					'<small>' + $('<div>').text((st.regno || '') + ' · ' + (st.mode_label || '')).html() + '</small></button>';
			});
			if (!html) html = '<div class="stu-amt-empty">No student matches.</div>';
			$('#stuAmtList').html(html);
		}
		function stuRenderEditor() {
			var st = null;
			(stuPanel.students || []).forEach(function (row) { if (row.id === stuSelected) st = row; });
			if (!st) {
				$('#stuAmtHint').show();
				$('#stuAmtForm').hide();
				return;
			}
			$('#stuAmtHint').hide();
			$('#stuAmtForm').show();
			$('#stuAmtName').text((st.regno ? st.regno + ' · ' : '') + st.name);
			$('#stuAmtMode').text(st.mode_label || '').toggleClass('day', st.mode !== 0);
			var html = '';
			(stuPanel.terms || []).forEach(function (term) {
				var classAmt = stuClassAmount(term, st.mode);
				var own = st.amounts && st.amounts[term.term] !== undefined ? st.amounts[term.term] : classAmt;
				html += '<tr data-term="' + term.term + '" data-class="' + classAmt + '">' +
					'<td>' + $('<div>').text(term.label || '').html() + '</td>' +
					'<td>' + stuMoney(classAmt) + '</td>' +
					'<td><input type="number" min="0" step="1" class="form-control form-control-sm stu-amt-input" value="' + Math.round(Number(own) || 0) + '"></td>' +
					'<td class="stu-amt-diff"></td></tr>';
			});
			if (!html) html = '<tr><td colspan="4" class="text-muted">No school fee is set for this class yet.</td></tr>';
			$('#stuAmtTerms').html(html);
			$('#stuAmtSame').prop('checked', false);
			stuMarkDiff();
		}
		function stuMarkDiff() {
			$('#stuAmtTerms tr').each(function () {
				var classAmt = Number($(this).data('class') || 0);
				var own = Number($(this).find('.stu-amt-input').val() || 0);
				var $d = $(this).find('.stu-amt-diff').removeClass('up down').text('');
				if (Math.abs(own - classAmt) < 0.5) return;
				var delta = own - classAmt;
				$d.addClass(delta < 0 ? 'down' : 'up').text((delta > 0 ? '+' : '') + stuMoney(delta));
			});
		}
		function stuOpen(classId, label) {
			stuSelected = 0;
			stuPanel = null;
			$('#mdlStudentAmount').attr('data-class-id', classId);
			$('#stuAmtTitle').text(label || 'class');
			$('#stuAmtLoading').show().text('Loading class…');
			$('#stuAmtBody').hide();
			$('#mdlStudentAmount').modal('show');
			$.getJSON('<?= base_url('school_fee_student_panel'); ?>', { class_id: classId, year: <?= (int) $selectedYear; ?> }, function (res) {
				if (!res.success) {
					$('#stuAmtLoading').text(res.error || 'Could not load this class.');
					return;
				}
				stuPanel = res;
				$('#stuAmtTitle').text(res.class_label || label || 'class');
				$('#stuAmtLoading').hide();
				$('#stuAmtBody').show();
				$('#stuAmtFilter').val('');
				stuRenderList();
				stuRenderEditor();
			}).fail(function () {
				$('#stuAmtLoading').text('Could not load this class.');
			});
		}
		$(document).off('click.sfStu', '.sfStudentBtn').on('click.sfStu', '.sfStudentBtn', function () {
			var $b = $(this);
			if (!$b.data('class-id')) return;
			stuOpen($b.data('class-id'), $b.data('label'));
		});
		$(document).on('input', '#stuAmtFilter', stuRenderList);
		$(document).on('click', '#stuAmtList .stu-amt-person', function () {
			stuSelected = parseInt($(this).data('id'), 10) || 0;
			stuRenderList();
			stuRenderEditor();
		});
		$(document).on('input', '#stuAmtTerms .stu-amt-input', function () {
			if ($('#stuAmtSame').is(':checked')) {
				var v = $(this).val();
				$('#stuAmtTerms .stu-amt-input').val(v);
			}
			stuMarkDiff();
		});
		$(document).on('change', '#stuAmtSame', function () {
			if (!this.checked) return;
			var v = $('#stuAmtTerms .stu-amt-input').first().val();
			$('#stuAmtTerms .stu-amt-input').val(v);
			stuMarkDiff();
		});
		$(document).on('click', '#stuAmtSave', function () {
			if (!stuPanel || !stuSelected) return;
			var data = {
				class_id: $('#mdlStudentAmount').attr('data-class-id') || 0,
				student_id: stuSelected,
				year: <?= (int) $selectedYear; ?>
			};
			$('#stuAmtTerms tr').each(function () {
				var term = $(this).data('term');
				if (term) data['amount_' + term] = $(this).find('.stu-amt-input').val();
			});
			var $btn = $(this).prop('disabled', true);
			$.post('<?= base_url('save_school_fee_student'); ?>', data, function (res) {
				$btn.prop('disabled', false);
				if (!res.success) {
					toastada.error(res.error || 'Save failed');
					return;
				}
				toastada.success(res.success);
				stuSaved = true;
				var st = null;
				(stuPanel.students || []).forEach(function (row) { if (row.id === stuSelected) st = row; });
				if (st) {
					st.amounts = {};
					st.custom = false;
					$('#stuAmtTerms tr').each(function () {
						var term = $(this).data('term');
						var classAmt = Number($(this).data('class') || 0);
						var own = Number($(this).find('.stu-amt-input').val() || 0);
						if (term && Math.abs(own - classAmt) >= 0.5) {
							st.amounts[term] = own;
							st.custom = true;
						}
					});
				}
				stuRenderList();
			}, 'json').fail(function () {
				$btn.prop('disabled', false);
				toastada.error('Save failed');
			});
		});
		$('#mdlStudentAmount').on('hidden.bs.modal', function () {
			if (stuSaved) window.location.reload();
		});

		$(document).off('click.sfDel', '.delButton').on('click.sfDel', '.delButton', function (e) {
			e.stopPropagation();
			if (!confirm('Delete this fee record?\n\nThis also permanently removes linked student adjustments and payment records.')) return;
			var id = $(this).data('id');
			var $btn = $(this).prop('disabled', true);
			$.ajax({
				url: '<?= base_url('deleteSchoolFee'); ?>/' + id,
				method: 'POST',
				success: function (res) {
					if (res.success) {
						toastada.success(res.success);
						setTimeout(function () { window.location.reload(); }, 1000);
					} else {
						toastada.error(res.error || 'Delete failed');
						$btn.prop('disabled', false);
					}
				},
				error: function (e) {
					toastada.error((e.responseJSON && e.responseJSON.error) || 'Delete failed');
					$btn.prop('disabled', false);
				}
			});
		});
	}

	if (typeof $ !== 'undefined') $(bootSchoolFees);
})(window.jQuery);
</script>
