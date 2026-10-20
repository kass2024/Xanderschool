<?php
/** @var array $fees */
/** @var array $feeGroups */
/** @var array $years */
/** @var int $selectedYear */
/** @var string $selectedYearTitle */
/** @var int $feeCount */
/** @var float $feeTotalAmount */
/** @var int $classCount */
/** @var int $classFeeCount */
/** @var int $studentFeeCount */
/** @var int $groupCount */

$feeGroups = $feeGroups ?? \App\Models\ExtraFeesModel::groupByClassAndTitle($fees ?? []);
$groupCount = (int) ($groupCount ?? count($feeGroups));
$studentOverrideCounts = $studentOverrideCounts ?? [];

$uniqueClasses = [];
$uniqueTitles = [];
foreach ($feeGroups as $g) {
	$lbl = trim((string) ($g['display_label'] ?? ''));
	if ($lbl !== '') {
		$uniqueClasses[$lbl] = $lbl;
	}
	$ttl = trim((string) ($g['title'] ?? ''));
	if ($ttl !== '') {
		$uniqueTitles[$ttl] = $ttl;
	}
}
ksort($uniqueClasses, SORT_NATURAL | SORT_FLAG_CASE);
ksort($uniqueTitles, SORT_NATURAL | SORT_FLAG_CASE);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/school-fees.css'); ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/extra-fees.css'); ?>?v=5">

<div class="ef-page" id="extraFeesPage">
	<div class="ef-center">

		<div class="ef-header">
			<h2><?= esc($title ?? lang('app.extraFees')); ?></h2>
			<p><?= esc($selectedYearTitle !== '' ? $selectedYearTitle : lang('app.academicYear')); ?></p>
			<div class="ef-header-actions">
				<button type="button" class="btn ef-btn-add" data-toggle="modal" data-target="#mdlextrafees">
					<i class="fa fa-plus-circle"></i> <?= lang('app.addClassFee'); ?>
				</button>
				<a href="<?= base_url('extra-fees'); ?>" class="btn ef-btn-secondary">
					<i class="fa fa-users"></i> <?= lang('app.addMultipleFee'); ?>
				</a>
			</div>
		</div>

		<div class="ef-kpi-grid">
			<div class="ef-kpi">
				<div class="ef-kpi-icon blue"><i class="fa fa-list"></i></div>
				<div class="ef-kpi-value"><?= (int) $groupCount; ?></div>
				<div class="ef-kpi-label"><?= lang('app.extraFees'); ?></div>
			</div>
			<div class="ef-kpi">
				<div class="ef-kpi-icon green"><i class="fa fa-university"></i></div>
				<div class="ef-kpi-value"><?= (int) $classCount; ?></div>
				<div class="ef-kpi-label"><?= lang('app.classes'); ?></div>
			</div>
			<div class="ef-kpi">
				<div class="ef-kpi-icon purple"><i class="fa fa-user"></i></div>
				<div class="ef-kpi-value"><?= (int) $studentFeeCount; ?></div>
				<div class="ef-kpi-label">Individual fees</div>
			</div>
			<div class="ef-kpi">
				<div class="ef-kpi-icon orange"><i class="fa fa-coins"></i></div>
				<div class="ef-kpi-value"><?= number_format((float) $feeTotalAmount); ?></div>
				<div class="ef-kpi-label"><?= lang('app.amount'); ?> (Rwf)</div>
			</div>
		</div>

		<div class="ef-student-card">
			<div class="ef-student-head">
				<h3>Edit one student’s extra fees</h3>
				<p>Search a student, then change Feeding, Transport or any other extra fee for that student only.</p>
			</div>
			<div class="ef-student-search">
				<label for="efStudentSearch">Student</label>
				<select class="form-control" id="efStudentSearch" style="width:100%"></select>
			</div>
			<div id="efStudentEmpty" class="ef-student-empty">Type a name or registration number to load extra fees.</div>
			<div id="efStudentWrap" style="display:none">
				<div class="ef-student-meta" id="efStudentMeta"></div>
				<div class="ef-table-wrap">
					<table class="table mb-0" id="efStudentFeeTable">
						<thead>
						<tr>
							<th><?= lang('app.title'); ?></th>
							<th><?= lang('app.term'); ?></th>
							<th>Source</th>
							<th class="text-right" style="min-width:140px"><?= lang('app.amount'); ?></th>
							<th class="text-center"><?= lang('app.Actions'); ?></th>
						</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				<p class="text-muted small mb-0 mt-2">Saving creates or updates this student’s own extra fee. Other students in the class keep the class amount.</p>
			</div>
		</div>

		<div class="ef-filter-card">
			<div class="ef-filter-row">
				<div class="ef-field">
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
				<div class="ef-field">
					<label for="efClassFilter"><?= lang('app.selectClass'); ?></label>
					<select class="form-control" id="efClassFilter">
						<option value="">All classes</option>
						<?php foreach ($uniqueClasses as $cls) : ?>
							<option value="<?= esc($cls, 'attr'); ?>"><?= esc($cls); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ef-field">
					<label for="efTitleFilter"><?= lang('app.title'); ?></label>
					<select class="form-control" id="efTitleFilter">
						<option value="">All extra fees</option>
						<?php foreach ($uniqueTitles as $ttl) : ?>
							<option value="<?= esc($ttl, 'attr'); ?>"><?= esc($ttl); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ef-field ef-search-field">
					<label for="efSearch">Search</label>
					<input type="text" class="form-control" id="efSearch" placeholder="Search class, department or fee title…">
				</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="ef-panel">
			<div class="ef-panel-head">
				<h3><?= lang('app.extraFees'); ?></h3>
				<span class="ef-badge" id="efVisibleCount"><?= $groupCount; ?> class fees · <?= (int) $feeCount; ?> terms</span>
			</div>
			<div class="ef-panel-body">
				<?php if (empty($feeGroups)) : ?>
					<div class="ef-empty">
						<i class="fa fa-inbox"></i>
						<h4>No extra fees yet</h4>
						<p>No class extra fees configured for <?= esc($selectedYearTitle); ?>. Add a class fee with boarding/day amounts. Individual student extras are edited from the search box above.</p>
						<button type="button" class="btn ef-btn-add" data-toggle="modal" data-target="#mdlextrafees">
							<i class="fa fa-plus"></i> <?= lang('app.addClassFee'); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="ef-table-wrap">
						<table id="extraFeesTable" class="table mb-0">
							<thead>
								<tr>
									<th><?= lang('app.selectClass'); ?></th>
									<th><?= lang('app.title'); ?></th>
									<th><?= lang('app.selectDepartment'); ?></th>
									<th class="sf-term-col-h"><?= lang('app.term1'); ?></th>
									<th class="sf-term-col-h"><?= lang('app.term2'); ?></th>
									<th class="sf-term-col-h"><?= lang('app.term3'); ?></th>
									<th class="sf-actions-col"><?= lang('app.Actions'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($feeGroups as $group) :
									$searchText = strtolower($group['display_label'] . ' ' . $group['title'] . ' ' . $group['dept_code'] . ' ' . $group['dept_title'] . ' ' . ($group['faculty_code'] ?? '') . ' ' . ($group['class_title'] ?? ''));
									$hasAnyTerm = !empty($group['terms'][1]) || !empty($group['terms'][2]) || !empty($group['terms'][3]);
									?>
									<tr class="sf-group-row"
										data-class="<?= esc($group['display_label'], 'attr'); ?>"
										data-title="<?= esc($group['title'], 'attr'); ?>"
										data-search="<?= esc($searchText, 'attr'); ?>">
										<td class="sf-level-cell">
											<span class="sf-level-pill"><?= esc($group['display_label']); ?></span>
										</td>
										<td><span class="ef-fee-title"><?= esc($group['title']); ?></span></td>
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
											$modes = $termFee ? \App\Models\ExtraFeesModel::modeAmounts($termFee) : null;
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
														<?php
														$recordedBy = trim((string) ($termFee['created_by_name'] ?? ''));
														if ($recordedBy !== '') :
														?>
															<small class="d-block text-muted" style="font-size:.68rem;margin-top:4px;"><?= esc(lang('app.recordedBy')); ?>: <?= esc($recordedBy); ?></small>
														<?php endif; ?>
														<div class="sf-term-actions">
															<button type="button" class="sf-icon-btn editFeeBtn" title="<?= lang('app.editFee'); ?>"
																data-id="<?= (int) $termFee['id']; ?>"
																data-amount="<?= esc((float) ($termFee['amount'] ?? 0), 'attr'); ?>"
																data-boarding="<?= esc($modes['boarding'] !== null ? (float) $modes['boarding'] : '', 'attr'); ?>"
																data-day="<?= esc($modes['day'] !== null ? (float) $modes['day'] : '', 'attr'); ?>"
																data-term="<?= $t; ?>"
																data-term-label="<?= esc(\App\Controllers\Home::TermToStr($t), 'attr'); ?>"
																data-class="<?= esc($group['display_label'], 'attr'); ?>"
																data-title="<?= esc($group['title'], 'attr'); ?>"
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
												$gModes[$gt] = $tf ? \App\Models\ExtraFeesModel::modeAmounts($tf) : ['boarding' => null, 'day' => null];
											}
											?>
											<?php
											$customKey = (int) $group['class_id'] . '|' . strtolower(trim((string) $group['title']));
											$customStudents = (int) ($studentOverrideCounts[$customKey] ?? 0);
											?>
											<button type="button" class="btn btn-sm btn-outline-success efStudentBtn"
												data-class-id="<?= (int) $group['class_id']; ?>"
												data-title="<?= esc($group['title'], 'attr'); ?>"
												data-label="<?= esc($group['display_label'] . ' · ' . $group['title'], 'attr'); ?>">
												<i class="fa fa-user"></i> One student<?php if ($customStudents > 0) : ?> <span class="sf-custom-count"><?= $customStudents; ?></span><?php endif; ?>
											</button>
											<button type="button" class="btn btn-sm btn-outline-primary editGroupBtn"
												data-class-id="<?= (int) $group['class_id']; ?>"
												data-title="<?= esc($group['title'], 'attr'); ?>"
												data-class="<?= esc($group['display_label'], 'attr'); ?>"
												data-boarding-1="<?= esc($gModes[1]['boarding'] !== null ? (float) $gModes[1]['boarding'] : '', 'attr'); ?>"
												data-day-1="<?= esc($gModes[1]['day'] !== null ? (float) $gModes[1]['day'] : '', 'attr'); ?>"
												data-boarding-2="<?= esc($gModes[2]['boarding'] !== null ? (float) $gModes[2]['boarding'] : '', 'attr'); ?>"
												data-day-2="<?= esc($gModes[2]['day'] !== null ? (float) $gModes[2]['day'] : '', 'attr'); ?>"
												data-boarding-3="<?= esc($gModes[3]['boarding'] !== null ? (float) $gModes[3]['boarding'] : '', 'attr'); ?>"
												data-day-3="<?= esc($gModes[3]['day'] !== null ? (float) $gModes[3]['day'] : '', 'attr'); ?>">
												<i class="fa fa-edit"></i> <?= lang('app.editFee'); ?>
											</button>
											<button type="button" class="btn btn-sm btn-outline-danger delGroupBtn"
												data-class-id="<?= (int) $group['class_id']; ?>"
												data-title="<?= esc($group['title'], 'attr'); ?>"
												data-label="<?= esc($group['display_label'] . ' · ' . $group['title'], 'attr'); ?>"
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

<div class="modal fade" id="mdlEditFee" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('update_extra_fee'); ?>" class="autoSubmit validate" id="frmEditFee">
				<input type="hidden" name="fee_id" id="edit_fee_id">
				<input type="hidden" name="year" value="<?= (int) $selectedYear; ?>">
				<div class="modal-header">
					<h5 class="modal-title"><?= lang('app.editFee'); ?></h5>
					<button type="button" class="close" data-dismiss="modal"><span>×</span></button>
				</div>
				<div class="modal-body">
					<div class="sf-edit-meta">
						<div><strong><?= lang('app.sClass'); ?>:</strong> <span id="edit_fee_class">—</span></div>
						<div><strong><?= lang('app.title'); ?>:</strong> <span id="edit_fee_title">—</span></div>
						<div><strong><?= lang('app.term'); ?>:</strong> <span id="edit_fee_term">—</span></div>
					</div>
					<div class="form-group mt-3">
						<label><?= lang('app.boarding'); ?> <?= lang('app.amount'); ?></label>
						<input type="number" min="0" step="1" name="amount_boarding" id="edit_fee_boarding" class="form-control" placeholder="Leave blank if not charged">
					</div>
					<div class="form-group">
						<label><?= lang('app.day'); ?> <?= lang('app.amount'); ?></label>
						<input type="number" min="0" step="1" name="amount_day" id="edit_fee_day" class="form-control" placeholder="Leave blank if not charged">
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

<div class="modal fade" id="mdlEditFeeGroup" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form action="<?= base_url('update_extra_fee'); ?>" class="autoSubmit validate" id="frmEditFeeGroup">
				<input type="hidden" name="class_id" id="edit_group_class_id">
				<input type="hidden" name="title" id="edit_group_title_val">
				<input type="hidden" name="year" value="<?= (int) $selectedYear; ?>">
				<div class="modal-header">
					<h5 class="modal-title"><?= lang('app.editFee'); ?> — <span id="edit_group_heading"></span></h5>
					<button type="button" class="close" data-dismiss="modal"><span>×</span></button>
				</div>
				<div class="modal-body">
					<p class="text-muted small mb-3">Update boarding and day amounts per term. Leave a term blank to keep it unchanged. Leave a mode blank if that extra fee is not charged.</p>
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
	#mdlEfStudent .modal-dialog{max-width:980px;}
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
	.sf-row-actions{white-space:normal;}
	@media(max-width:800px){.stu-amt-layout{grid-template-columns:1fr;}}
</style>
<div class="modal fade" id="mdlEfStudent" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">One student — <span id="efStuTitle">extra fee</span></h5>
				<button type="button" class="close" data-dismiss="modal"><span>×</span></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small">The class amount for this extra fee stays the same. Pick one student and set a different amount. Matching the class amount again removes that student’s personal fee.</p>
				<div id="efStuLoading" class="stu-amt-empty">Loading class…</div>
				<div id="efStuBody" class="stu-amt-layout" style="display:none">
					<div class="stu-amt-side">
						<input type="search" id="efStuFilter" class="form-control" placeholder="Search name or reg no">
						<div class="stu-amt-list" id="efStuList"></div>
					</div>
					<div class="stu-amt-editor">
						<div id="efStuHint" class="stu-amt-empty">Select a student.</div>
						<div id="efStuForm" style="display:none">
							<div class="stu-amt-meta">
								<strong id="efStuName"></strong>
								<span id="efStuMode" class="stu-amt-mode"></span>
							</div>
							<table class="table table-sm mb-2">
								<thead><tr><th>Term</th><th>Class amount</th><th>This student</th><th></th></tr></thead>
								<tbody id="efStuTerms"></tbody>
							</table>
							<div class="custom-control custom-checkbox mb-2">
								<input type="checkbox" class="custom-control-input" id="efStuSame">
								<label class="custom-control-label" for="efStuSame">Use the first term’s amount for every term</label>
							</div>
							<button type="button" class="btn btn-success" id="efStuSave">Save this student</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
$(function () {
	$('#academicYearSelect').on('change', function () {
		window.location.href = '<?= base_url('extra_fees_management?year='); ?>' + $(this).val();
	});

	const efYear = <?= (int) $selectedYear; ?>;
	let efCurrentStudent = 0;

	function efLoadStudentFees(studentId) {
		if (!studentId) return;
		efCurrentStudent = parseInt(studentId, 10);
		$('#efStudentEmpty').text('Loading extra fees…').show();
		$('#efStudentWrap').hide();
		$.getJSON('<?= base_url('extra_fee_student_lines'); ?>', { student: studentId, year: efYear }, function (res) {
			if (!res.success) {
				$('#efStudentEmpty').text(res.error || 'Could not load student extra fees.').show();
				return;
			}
			const st = res.student || {};
			$('#efStudentMeta').html(
				'<strong>' + (st.regno || '') + ' ' + (st.name || '') + '</strong>' +
				'<span>' + (st.class_label || '') + '</span>' +
				'<span class="ef-mode-pill">' + (st.mode_label || '') + '</span>'
			);
			let html = '';
			(res.lines || []).forEach(function (line) {
				html += '<tr>' +
					'<td>' + $('<div>').text(line.title || '').html() + '</td>' +
					'<td>' + $('<div>').text(line.term_label || '').html() + '</td>' +
					'<td><span class="ef-target-badge ' + (line.type === 'student' ? 'student' : 'class') + '">' +
						(line.type === 'student' ? 'This student' : 'Class default') + '</span></td>' +
					'<td class="text-right"><input type="number" min="0" step="1" class="form-control form-control-sm text-right ef-stu-amt" value="' +
						Math.round(Number(line.amount || 0)) + '" data-id="' + line.id + '" data-title="' +
						$('<div>').text(line.title || '').html() + '" data-term="' + line.term + '"></td>' +
					'<td class="text-center"><button type="button" class="btn btn-sm btn-success ef-stu-save" data-id="' + line.id + '">Save</button></td>' +
					'</tr>';
			});
			if (!html) {
				html = '<tr><td colspan="5" class="text-muted text-center">No extra fees for this student.</td></tr>';
			}
			$('#efStudentFeeTable tbody').html(html);
			$('#efStudentEmpty').hide();
			$('#efStudentWrap').show();
		}).fail(function () {
			$('#efStudentEmpty').text('Could not load student extra fees.').show();
		});
	}

	$('#efStudentSearch').select2({
		ajax: {
			url: '<?= base_url('search_student'); ?>',
			type: 'post',
			dataType: 'json',
			delay: 250,
			data: function (params) {
				return { searchTerm: params.term };
			},
			processResults: function (response) {
				return { results: response || [] };
			},
			cache: true
		},
		placeholder: 'Search by name or registration number…',
		minimumInputLength: 2,
		width: '100%'
	});
	$('#efStudentSearch').on('select2:select', function (e) {
		const id = e.params && e.params.data ? e.params.data.id : $(this).val();
		efLoadStudentFees(id);
	});

	$(document).on('click', '.ef-stu-save', function () {
		const $row = $(this).closest('tr');
		const $amt = $row.find('.ef-stu-amt');
		const $btn = $(this).prop('disabled', true);
		$.post('<?= base_url('save_student_extra_fee'); ?>', {
			studentId: efCurrentStudent,
			feeId: $amt.data('id'),
			title: $amt.data('title'),
			term: $amt.data('term'),
			amount: $amt.val(),
			year: efYear
		}, function (res) {
			if (res.success) {
				toastada.success(res.success);
				efLoadStudentFees(efCurrentStudent);
			} else {
				toastada.error(res.error || 'Save failed.');
				$btn.prop('disabled', false);
			}
		}, 'json').fail(function () {
			toastada.error('Save failed.');
			$btn.prop('disabled', false);
		});
	});

	let efStuPanel = null;
	let efStuSelected = 0;
	let efStuSaved = false;
	let efStuClass = 0;
	let efStuFeeTitle = '';
	function efMoney(n) { return Math.round(Number(n) || 0).toLocaleString(); }
	function efClassAmount(term, mode) {
		if (!term) return 0;
		let raw = mode === 0 ? term.boarding : term.day;
		if (raw === null || raw === undefined || raw === '') raw = mode === 0 ? term.day : term.boarding;
		return Number(raw || 0);
	}
	function efRenderList() {
		const q = ($('#efStuFilter').val() || '').toLowerCase().trim();
		let html = '';
		const rows = (efStuPanel.students || []).slice().sort(function (a, b) {
			return (b.custom ? 1 : 0) - (a.custom ? 1 : 0);
		});
		rows.forEach(function (st) {
			const hay = ((st.regno || '') + ' ' + (st.name || '')).toLowerCase();
			if (q && hay.indexOf(q) === -1) return;
			html += '<button type="button" class="stu-amt-person' + (st.id === efStuSelected ? ' active' : '') + '" data-id="' + st.id + '">' +
				$('<div>').text(st.name || '').html() +
				(st.custom ? '<span class="stu-amt-tag">Custom</span>' : '') +
				'<small>' + $('<div>').text((st.regno || '') + ' · ' + (st.mode_label || '')).html() + '</small></button>';
		});
		$('#efStuList').html(html || '<div class="stu-amt-empty">No student matches.</div>');
	}
	function efMarkDiff() {
		$('#efStuTerms tr').each(function () {
			const classAmt = Number($(this).data('class') || 0);
			const own = Number($(this).find('.ef-stu-input').val() || 0);
			const $d = $(this).find('.stu-amt-diff').removeClass('up down').text('');
			if (Math.abs(own - classAmt) < 0.5) return;
			const delta = own - classAmt;
			$d.addClass(delta < 0 ? 'down' : 'up').text((delta > 0 ? '+' : '') + efMoney(delta));
		});
	}
	function efRenderEditor() {
		let st = null;
		(efStuPanel.students || []).forEach(function (row) { if (row.id === efStuSelected) st = row; });
		if (!st) {
			$('#efStuHint').show();
			$('#efStuForm').hide();
			return;
		}
		$('#efStuHint').hide();
		$('#efStuForm').show();
		$('#efStuName').text((st.regno ? st.regno + ' · ' : '') + st.name);
		$('#efStuMode').text(st.mode_label || '').toggleClass('day', st.mode !== 0);
		let html = '';
		(efStuPanel.terms || []).forEach(function (term) {
			const classAmt = efClassAmount(term, st.mode);
			const own = st.amounts && st.amounts[term.term] !== undefined ? st.amounts[term.term] : classAmt;
			html += '<tr data-term="' + term.term + '" data-class="' + classAmt + '"><td>' +
				$('<div>').text(term.label || '').html() + '</td><td>' + efMoney(classAmt) +
				'</td><td><input type="number" min="0" step="1" class="form-control form-control-sm ef-stu-input" value="' +
				Math.round(Number(own) || 0) + '"></td><td class="stu-amt-diff"></td></tr>';
		});
		$('#efStuTerms').html(html || '<tr><td colspan="4" class="text-muted">No amount is set for this extra fee yet.</td></tr>');
		$('#efStuSame').prop('checked', false);
		efMarkDiff();
	}
	$(document).on('click', '.efStudentBtn', function () {
		const $b = $(this);
		efStuClass = parseInt($b.data('class-id'), 10) || 0;
		efStuFeeTitle = $b.data('title') || '';
		efStuSelected = 0;
		efStuPanel = null;
		$('#efStuTitle').text($b.data('label') || efStuFeeTitle);
		$('#efStuLoading').show().text('Loading class…');
		$('#efStuBody').hide();
		$('#mdlEfStudent').modal('show');
		$.getJSON('<?= base_url('extra_fee_class_panel'); ?>', {
			class_id: efStuClass,
			title: efStuFeeTitle,
			year: efYear
		}, function (res) {
			if (!res.success) {
				$('#efStuLoading').text(res.error || 'Could not load this class.');
				return;
			}
			efStuPanel = res;
			$('#efStuLoading').hide();
			$('#efStuBody').show();
			$('#efStuFilter').val('');
			efRenderList();
			efRenderEditor();
		}).fail(function () {
			$('#efStuLoading').text('Could not load this class.');
		});
	});
	$(document).on('input', '#efStuFilter', efRenderList);
	$(document).on('click', '#efStuList .stu-amt-person', function () {
		efStuSelected = parseInt($(this).data('id'), 10) || 0;
		efRenderList();
		efRenderEditor();
	});
	$(document).on('input', '#efStuTerms .ef-stu-input', function () {
		if ($('#efStuSame').is(':checked')) {
			$('#efStuTerms .ef-stu-input').val($(this).val());
		}
		efMarkDiff();
	});
	$(document).on('change', '#efStuSame', function () {
		if (!this.checked) return;
		$('#efStuTerms .ef-stu-input').val($('#efStuTerms .ef-stu-input').first().val());
		efMarkDiff();
	});
	$(document).on('click', '#efStuSave', function () {
		if (!efStuPanel || !efStuSelected) return;
		const data = { class_id: efStuClass, student_id: efStuSelected, title: efStuFeeTitle, year: efYear };
		$('#efStuTerms tr').each(function () {
			const term = $(this).data('term');
			if (term) data['amount_' + term] = $(this).find('.ef-stu-input').val();
		});
		const $btn = $(this).prop('disabled', true);
		$.post('<?= base_url('save_extra_fee_student_class'); ?>', data, function (res) {
			$btn.prop('disabled', false);
			if (!res.success) {
				toastada.error(res.error || 'Save failed.');
				return;
			}
			toastada.success(res.success);
			efStuSaved = true;
			let st = null;
			(efStuPanel.students || []).forEach(function (row) { if (row.id === efStuSelected) st = row; });
			if (st) {
				st.amounts = {};
				st.custom = false;
				$('#efStuTerms tr').each(function () {
					const term = $(this).data('term');
					const classAmt = Number($(this).data('class') || 0);
					const own = Number($(this).find('.ef-stu-input').val() || 0);
					if (term && Math.abs(own - classAmt) >= 0.5) {
						st.amounts[term] = own;
						st.custom = true;
					}
				});
			}
			efRenderList();
		}, 'json').fail(function () {
			$btn.prop('disabled', false);
			toastada.error('Save failed.');
		});
	});
	$('#mdlEfStudent').on('hidden.bs.modal', function () {
		if (efStuSaved) window.location.reload();
	});

	function efApplyFilters() {
		const cls = ($('#efClassFilter').val() || '').toLowerCase();
		const title = ($('#efTitleFilter').val() || '').toLowerCase();
		const q = ($('#efSearch').val() || '').toLowerCase().trim();
		let visible = 0;
		$('#extraFeesTable tbody tr.sf-group-row').each(function () {
			const $row = $(this);
			let show = true;
			if (cls && String($row.data('class') || '').toLowerCase() !== cls) show = false;
			if (show && title && String($row.data('title') || '').toLowerCase() !== title) show = false;
			if (show && q && String($row.data('search') || '').indexOf(q) === -1) show = false;
			$row.toggle(show);
			if (show) visible++;
		});
		$('#efVisibleCount').text(visible + ' class fee' + (visible === 1 ? '' : 's') + ' · <?= (int) $feeCount; ?> terms');
	}

	$('#efClassFilter, #efTitleFilter').on('change', efApplyFilters);
	$('#efSearch').on('input', efApplyFilters);

	$(document).on('click', '.editFeeBtn', function () {
		const $b = $(this);
		$('#edit_fee_id').val($b.data('id'));
		$('#edit_fee_class').text($b.data('class'));
		$('#edit_fee_title').text($b.data('title'));
		$('#edit_fee_term').text($b.data('term-label'));
		const boarding = $b.data('boarding');
		const day = $b.data('day');
		$('#edit_fee_boarding').val(boarding !== undefined && boarding !== '' ? boarding : '');
		$('#edit_fee_day').val(day !== undefined && day !== '' ? day : '');
		$('#edit_fee_amount').val($b.data('amount'));
		$('#edit_apply_all_terms').prop('checked', false);
		$('#mdlEditFee').modal('show');
	});

	$('#frmEditFee').on('submit', function () {
		const b = Number($('#edit_fee_boarding').val() || 0);
		const d = Number($('#edit_fee_day').val() || 0);
		$('#edit_fee_amount').val(Math.max(b, d));
	});

	$(document).on('click', '.editGroupBtn', function () {
		const $b = $(this);
		$('#edit_group_class_id').val($b.data('class-id'));
		$('#edit_group_title_val').val($b.data('title'));
		$('#edit_group_heading').text(($b.data('class') || '') + ' · ' + ($b.data('title') || ''));
		for (let t = 1; t <= 3; t++) {
			$('#edit_group_boarding_' + t).val($b.data('boarding-' + t) || '');
			$('#edit_group_day_' + t).val($b.data('day-' + t) || '');
		}
		$('#mdlEditFeeGroup').modal('show');
	});

	$(document).on('click', '.delGroupBtn', function () {
		const $b = $(this);
		const label = $b.data('label') || 'this extra fee';
		if (!confirm('Delete all terms of ' + label + '?\n\nThis also permanently removes linked payment records.')) {
			return;
		}
		$b.prop('disabled', true);
		$.ajax({
			url: '<?= base_url('deleteExtraFeeGroup'); ?>',
			method: 'POST',
			dataType: 'json',
			data: {
				class_id: $b.data('class-id'),
				title: $b.data('title'),
				year: efYear
			},
			success: function (res) {
				if (res.success) {
					toastada.success(res.success);
					setTimeout(function () { window.location.reload(); }, 600);
				} else {
					toastada.error(res.error || 'Delete failed.');
					$b.prop('disabled', false);
				}
			},
			error: function (e) {
				toastada.error((e.responseJSON && e.responseJSON.error) ? e.responseJSON.error : 'Delete failed.');
				$b.prop('disabled', false);
			}
		});
	});

	$(document).on('click', '.delButton', function () {
		if (!confirm('Delete this extra fee term?\n\nThis also permanently removes linked payment records.')) return;
		const id = $(this).data('id');
		const $btn = $(this).prop('disabled', true);
		$.ajax({
			url: '<?= base_url('deleteExtraFee'); ?>/' + id,
			method: 'POST',
			dataType: 'json',
			success: function (res) {
				if (res.success) {
					toastada.success(res.success);
					setTimeout(function () { window.location.reload(); }, 600);
				} else {
					toastada.error(res.error || 'Delete failed.');
					$btn.prop('disabled', false);
				}
			},
			error: function (e) {
				toastada.error((e.responseJSON && e.responseJSON.error) ? e.responseJSON.error : 'Delete failed.');
				$btn.prop('disabled', false);
			}
		});
	});
});
</script>
