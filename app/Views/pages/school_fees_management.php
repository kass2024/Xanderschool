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
$uniqueLevels = [];
foreach ($feeGroups as $g) {
	$uniqueLevels[$g['display_label']] = $g['display_label'];
}
ksort($uniqueLevels);
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
				<div class="sf-kpi-label"><?= lang('app.level'); ?></div>
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
					<label for="sfLevelFilter"><?= lang('app.level'); ?></label>
					<select class="form-control" id="sfLevelFilter">
						<option value="">All levels</option>
						<?php foreach ($uniqueLevels as $lvl) : ?>
							<option value="<?= esc($lvl, 'attr'); ?>"><?= esc($lvl); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sf-field sf-search-field">
					<label for="sfSearch">Search</label>
					<input type="text" class="form-control" id="sfSearch" placeholder="Search level or department…">
				</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="sf-panel">
			<div class="sf-panel-head">
				<h3><?= lang('app.schoolFeesManagement'); ?></h3>
				<span class="sf-badge" id="sfVisibleCount"><?= count($feeGroups); ?> levels · <?= (int) $feeCount; ?> fees</span>
			</div>
			<div class="sf-panel-body">
				<?php if (empty($feeGroups)) : ?>
					<div class="sf-empty">
						<i class="fa fa-inbox"></i>
						<h4>No fees configured</h4>
						<p>No school fees found for <?= esc($selectedYearTitle); ?>. Add a fee to get started.</p>
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
									$searchText = strtolower($group['display_label'] . ' ' . $group['dept_code'] . ' ' . $group['dept_title']);
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
												data-label="<?= esc($group['display_label'], 'attr'); ?>">
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
		$('#sfVisibleCount').text(visible + ' levels · <?= (int) $feeCount; ?> fees');
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
			$('#edit_group_title').text($b.data('level') + ' · ' + $b.data('dept'));
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
