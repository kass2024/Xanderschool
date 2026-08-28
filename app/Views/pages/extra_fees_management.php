<?php
/** @var array $fees */
/** @var array $years */
/** @var int $selectedYear */
/** @var string $selectedYearTitle */
/** @var int $feeCount */
/** @var float $feeTotalAmount */
/** @var int $classCount */
/** @var int $classFeeCount */
/** @var int $studentFeeCount */

if (!function_exists('ef_term_badges')) {
	function ef_term_badges($terms): void
	{
		foreach (explode(',', (string) $terms) as $term) {
			$term = trim($term);
			if ($term === '') {
				continue;
			}
			$label = \App\Controllers\Home::TermToStr((int) $term);
			echo '<span class="ef-term-badge t' . esc($term, 'attr') . '">' . esc($label) . '</span>';
		}
	}
}

if (!function_exists('ef_target_label')) {
	function ef_target_label(array $fee): string
	{
		if ((int) ($fee['type'] ?? 0) === 1) {
			$name = trim((string) ($fee['student_name'] ?? ''));
			$reg = trim((string) ($fee['regno'] ?? ''));
			return $reg !== '' ? $reg . ' ' . $name : ($name !== '' ? $name : 'Individual student');
		}
		return trim(($fee['level_name'] ?? '') . ' ' . ($fee['code'] ?? '') . ' ' . ($fee['classe'] ?? ''));
	}
}

$uniqueClasses = [];
foreach ($classes ?? [] as $cRow) {
	$label = trim(($cRow['level_name'] ?? '') . ' ' . ($cRow['code'] ?? '') . ' ' . ($cRow['title'] ?? ''));
	if ($label === '' || stripos($label, 'holiday') !== false) {
		continue;
	}
	$uniqueClasses[$label] = $label;
}
foreach ($fees as $fee) {
	if ((int) ($fee['type'] ?? 0) === 0) {
		$label = ef_target_label($fee);
		if ($label !== '' && stripos($label, 'holiday') === false) {
			$uniqueClasses[$label] = $label;
		}
	}
}
uksort($uniqueClasses, 'strnatcasecmp');
?>
<link rel="stylesheet" href="<?= base_url('assets/css/extra-fees.css'); ?>?v=3">

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
				<div class="ef-kpi-value" id="efKpiCount"><?= (int) $feeCount; ?></div>
				<div class="ef-kpi-label"><?= lang('app.extraFees'); ?></div>
			</div>
			<div class="ef-kpi">
				<div class="ef-kpi-icon green"><i class="fa fa-university"></i></div>
				<div class="ef-kpi-value" id="efKpiClass"><?= (int) $classFeeCount; ?></div>
				<div class="ef-kpi-label"><?= lang('app.addClassFee'); ?></div>
			</div>
			<div class="ef-kpi">
				<div class="ef-kpi-icon purple"><i class="fa fa-user"></i></div>
				<div class="ef-kpi-value" id="efKpiStudent"><?= (int) $studentFeeCount; ?></div>
				<div class="ef-kpi-label">Individual fees</div>
			</div>
			<div class="ef-kpi">
				<div class="ef-kpi-icon orange"><i class="fa fa-coins"></i></div>
				<div class="ef-kpi-value" id="efKpiAmount"><?= number_format((float) $feeTotalAmount); ?></div>
				<div class="ef-kpi-label"><?= lang('app.amount'); ?> (Rwf)</div>
				<small class="text-muted d-block" style="font-size:.7rem;margin-top:2px">Unit × students</small>
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
				<?php if (!empty($fees)) : ?>
				<div class="ef-field">
					<label for="efTypeFilter">Type</label>
					<select class="form-control" id="efTypeFilter">
						<option value="">All types</option>
						<option value="class">Class fees</option>
						<option value="student">Individual fees</option>
					</select>
				</div>
				<?php if (!empty($uniqueClasses)) : ?>
				<div class="ef-field">
					<label for="efClassFilter"><?= lang('app.sClass'); ?></label>
					<select class="form-control" id="efClassFilter">
						<option value="">All classes</option>
						<?php foreach ($uniqueClasses as $cls) : ?>
							<option value="<?= esc($cls, 'attr'); ?>"><?= esc($cls); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>
				<div class="ef-field">
					<label for="efTermFilter"><?= lang('app.term'); ?></label>
					<select class="form-control" id="efTermFilter">
						<option value="">All terms</option>
						<option value="1"><?= lang('app.term1'); ?></option>
						<option value="2"><?= lang('app.term2'); ?></option>
						<option value="3"><?= lang('app.term3'); ?></option>
					</select>
				</div>
				<div class="ef-field ef-search-field">
					<label for="efSearch">Search</label>
					<input type="text" class="form-control" id="efSearch" placeholder="Search title, class or student…">
				</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="ef-panel">
			<div class="ef-panel-head">
				<h3>Configured extra fees</h3>
				<span class="ef-badge" id="efVisibleCount"><?= (int) $feeCount; ?> fee<?= $feeCount === 1 ? '' : 's'; ?></span>
			</div>
			<?php if (!empty($fees)) : ?>
			<div class="ef-bulk-bar" id="efBulkBar">
				<label class="ef-bulk-select-all mb-0">
					<input type="checkbox" id="efSelectAllVisible"> Select all visible
				</label>
				<span class="ef-bulk-count text-muted" id="efSelectedCount">0 selected</span>
				<button type="button" class="btn btn-danger btn-sm" id="efBulkDeleteBtn" disabled>
					<i class="fa fa-trash"></i> Delete selected
				</button>
			</div>
			<?php endif; ?>
			<div class="ef-panel-body">
				<?php if (empty($fees)) : ?>
					<div class="ef-empty">
						<i class="fa fa-inbox"></i>
						<h4>No extra fees yet</h4>
						<p>No extra fees configured for <?= esc($selectedYearTitle); ?>. Add a class fee with boarding/day amounts, or assign fees to multiple students. Primary (P1–P6), Software Development, Accounting and Stream registration fees are created automatically.</p>
						<button type="button" class="btn ef-btn-add" data-toggle="modal" data-target="#mdlextrafees">
							<i class="fa fa-plus"></i> <?= lang('app.addClassFee'); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="ef-table-wrap">
						<table id="extraFeesTable" class="table mb-0">
							<thead>
							<tr>
								<th class="ef-col-check text-center" style="width:42px">
									<span class="sr-only">Select</span>
								</th>
								<th><?= lang('app.title'); ?></th>
								<th>Type</th>
								<th><?= lang('app.sClass'); ?> / Student</th>
								<th class="text-right"><?= lang('app.amount'); ?></th>
								<th><?= lang('app.term'); ?></th>
								<th><?= lang('app.academicYear'); ?></th>
								<th><?= lang('app.recordedBy'); ?></th>
								<th class="text-center"><?= lang('app.Actions'); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($fees as $fee) :
								$isStudent = (int) ($fee['type'] ?? 0) === 1;
								$target = ef_target_label($fee);
								$searchText = strtolower($fee['title'] . ' ' . $target . ' ' . ($fee['regno'] ?? '') . ' ' . ($fee['created_by_name'] ?? ''));
								$termStr = (string) ($fee['term'] ?? '');
								$modes = \App\Models\ExtraFeesModel::modeAmounts($fee);
								$unitAmt = (float) ($fee['amount'] ?? 0);
								$stuCount = (int) ($fee['student_count'] ?? ($isStudent ? 1 : 0));
								$lineTotal = (float) ($fee['line_total'] ?? $unitAmt);
								$boardAmt = $modes['boarding'];
								$dayAmt = $modes['day'];
								$splitModes = !$isStudent && $boardAmt !== null && $dayAmt !== null && abs((float) $boardAmt - (float) $dayAmt) > 0.00001;
								?>
								<tr class="ef-fee-row"
									data-id="<?= (int) $fee['id']; ?>"
									data-type="<?= $isStudent ? 'student' : 'class'; ?>"
									data-class="<?= esc($isStudent ? '' : $target, 'attr'); ?>"
									data-terms="<?= esc($termStr, 'attr'); ?>"
									data-search="<?= esc($searchText, 'attr'); ?>">
									<td class="text-center ef-col-check">
										<input type="checkbox" class="ef-row-check" value="<?= (int) $fee['id']; ?>" aria-label="Select fee">
									</td>
									<td><span class="ef-fee-title"><?= esc($fee['title']); ?></span></td>
									<td>
										<span class="ef-target-badge <?= $isStudent ? 'student' : 'class'; ?>">
											<?= $isStudent ? 'Individual' : 'Class'; ?>
										</span>
									</td>
									<td><?= esc($target); ?></td>
									<td class="text-right">
										<?php if ($splitModes) { ?>
										<span class="ef-mode-amt board"><?= number_format((float) $boardAmt); ?> boarding</span>
										<span class="ef-mode-amt day"><?= number_format((float) $dayAmt); ?> day</span>
										<?php } else { ?>
										<span class="ef-amount"><?= number_format($lineTotal); ?></span>
										<?php } ?>
										<?php if (!$isStudent) { ?>
										<small class="d-block text-muted" style="font-size:.72rem">
											<?php if (!$splitModes) { ?>
												<?= number_format($unitAmt); ?> × <?= $stuCount; ?> student<?= $stuCount === 1 ? '' : 's'; ?>
											<?php } else { ?>
												Saved for the class<?= $stuCount > 0 ? ' · ' . $stuCount . ' student' . ($stuCount === 1 ? '' : 's') : ' · no students yet'; ?>
											<?php } ?>
										</small>
										<?php } elseif ($stuCount > 0) { ?>
										<small class="d-block text-muted" style="font-size:.72rem">1 student</small>
										<?php } ?>
									</td>
									<td><?php ef_term_badges($fee['term']); ?></td>
									<td><?= esc($fee['academic_year']); ?></td>
									<td><?= esc(trim((string) ($fee['created_by_name'] ?? '')) !== '' ? $fee['created_by_name'] : '—'); ?></td>
									<td class="text-center">
										<button type="button" class="btn btn-outline-danger btn-sm ef-btn-del delButton" data-id="<?= (int) $fee['id']; ?>">
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

<script>
$(function () {
	$('#academicYearSelect').on('change', function () {
		window.location.href = '<?= base_url('extra_fees_management?year='); ?>' + $(this).val();
	});

	function efSelectedIds() {
		const ids = [];
		$('.ef-fee-row:visible .ef-row-check:checked').each(function () {
			ids.push(parseInt($(this).val(), 10));
		});
		return ids.filter(function (id) { return id > 0; });
	}

	function efUpdateBulkUi() {
		const ids = efSelectedIds();
		const n = ids.length;
		$('#efSelectedCount').text(n + ' selected');
		$('#efBulkDeleteBtn').prop('disabled', n < 1);
		const $visible = $('.ef-fee-row:visible .ef-row-check');
		const allChecked = $visible.length > 0 && $visible.filter(':checked').length === $visible.length;
		$('#efSelectAllVisible').prop('checked', allChecked);
	}

	function efApplyFilters() {
		const type = $('#efTypeFilter').val();
		const cls = ($('#efClassFilter').val() || '').toLowerCase();
		const term = $('#efTermFilter').val();
		const q = ($('#efSearch').val() || '').toLowerCase().trim();
		let visible = 0;
		$('.ef-fee-row').each(function () {
			const $row = $(this);
			let show = true;
			if (type && $row.data('type') !== type) show = false;
			if (show && cls && String($row.data('class') || '').toLowerCase().indexOf(cls) === -1) show = false;
			if (show && term) {
				const terms = String($row.data('terms') || '');
				if (terms.split(',').map(function (t) { return t.trim(); }).indexOf(term) === -1) show = false;
			}
			if (show && q && String($row.data('search') || '').indexOf(q) === -1) show = false;
			$row.toggle(show);
			if (!show) $row.find('.ef-row-check').prop('checked', false);
			if (show) visible++;
		});
		$('#efVisibleCount').text(visible + ' fee' + (visible === 1 ? '' : 's'));
		efUpdateBulkUi();
	}

	$('#efTypeFilter, #efClassFilter, #efTermFilter').on('change', efApplyFilters);
	$('#efSearch').on('input', efApplyFilters);

	$(document).on('change', '.ef-row-check', efUpdateBulkUi);
	$('#efSelectAllVisible').on('change', function () {
		const on = $(this).is(':checked');
		$('.ef-fee-row:visible .ef-row-check').prop('checked', on);
		efUpdateBulkUi();
	});

	$('#efBulkDeleteBtn').on('click', function () {
		const ids = efSelectedIds();
		if (!ids.length) return;
		if (!confirm('Delete ' + ids.length + ' selected extra fee(s)?\n\nThis also permanently removes linked payment records.')) return;
		const $btn = $(this).prop('disabled', true);
		$.ajax({
			url: '<?= base_url('deleteExtraFeesBulk'); ?>',
			method: 'POST',
			dataType: 'json',
			data: { ids: ids },
			success: function (res) {
				if (res.success) {
					toastada.success(res.success);
					setTimeout(function () { window.location.reload(); }, 600);
				} else {
					toastada.error(res.error || 'Delete failed.');
					$btn.prop('disabled', false);
					efUpdateBulkUi();
				}
			},
			error: function (e) {
				toastada.error((e.responseJSON && e.responseJSON.error) ? e.responseJSON.error : 'Delete failed.');
				$btn.prop('disabled', false);
				efUpdateBulkUi();
			}
		});
	});

	$(document).on('click', '.delButton', function () {
		if (!confirm('Delete this extra fee?\n\nThis also permanently removes linked payment records.')) return;
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
