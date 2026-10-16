<?php
/** @var array $students */
/** @var array $classes */
/** @var array $years */
/** @var array $stats */
/** @var int|string $class_id */
/** @var int|string $year_id */
/** @var string $term */
/** @var array $selected_terms */
/** @var string $filter */
/** @var bool $hasResults */
/** @var string $selectedYearTitle */
/** @var string $classLabel */
/** @var string $termLabel */
/** @var string $queryParams */
/** @var string $reportType */
/** @var array $reportTypeLabels */
/** @var array $incomeSummary */
/** @var string $feesScope */
/** @var array $feesScopeLabels */

$stats = $stats ?? [
	'total_students' => 0, 'total_expected' => 0, 'total_school_expected' => 0,
	'total_extra_expected' => 0, 'total_paid' => 0, 'total_remain' => 0,
	'full_paid' => 0, 'partial_paid' => 0, 'zero_paid' => 0, 'collection_rate' => 0,
];
$selectedTerms = $selected_terms ?? [(int) ($term ?? 1)];

if (!function_exists('fr_payment_status')) {
	function fr_payment_status(float $amount, float $paid): array
	{
		if ($paid > $amount && $amount > 0) {
			return ['label' => 'Overpay', 'class' => 'over', 'remain' => 0];
		}
		if ($amount > 0 && $paid >= $amount) {
			return ['label' => 'Full paid', 'class' => 'full', 'remain' => 0];
		}
		if ($paid > 0) {
			return ['label' => 'Partial', 'class' => 'partial', 'remain' => $amount - $paid];
		}
		return ['label' => 'Zero payment', 'class' => 'zero', 'remain' => $amount];
	}
}

if (!function_exists('fr_fee_item_cell')) {
	/**
	 * Render one fee amount cell with unpaid highlighting.
	 */
	function fr_fee_item_cell(float $expected, float $paid): string
	{
		if ($expected <= 0 && $paid <= 0) {
			return '—';
		}
		$unpaid = max(0, $expected - $paid);
		$state = 'none';
		if ($expected > 0 && $unpaid <= 0) {
			$state = 'paid';
		} elseif ($paid > 0 && $unpaid > 0) {
			$state = 'partial';
		} elseif ($unpaid > 0) {
			$state = 'unpaid';
		}
		$html = '<div class="fr-fee-cell fr-fee-' . $state . '">';
		$html .= '<div class="fr-extra-amt">' . number_format($expected) . '</div>';
		if ($state === 'paid') {
			$html .= '<div class="fr-extra-paid">paid ' . number_format($paid) . '</div>';
		} elseif ($state === 'partial') {
			$html .= '<div class="fr-extra-paid">paid ' . number_format($paid) . '</div>';
			$html .= '<div class="fr-extra-unpaid">unpaid ' . number_format($unpaid) . '</div>';
		} elseif ($state === 'unpaid') {
			$html .= '<div class="fr-extra-unpaid">unpaid ' . number_format($unpaid) . '</div>';
		}
		$html .= '</div>';
		return $html;
	}
}

$canFeesActions = !empty($canFeesActions);
$qp = $queryParams ?? '';
$reportType = $reportType ?? 'detailed';
$reportTypeLabels = $reportTypeLabels ?? [
	'detailed' => 'Detailed fees report',
	'class_balance' => 'Class balance list',
	'income_summary' => 'Summary of income',
];
$feesScope = $feesScope ?? 'both';
$feesScopeLabels = $feesScopeLabels ?? [
	'both' => 'Both',
	'school' => 'School fees',
	'extra' => 'Extra fees',
];
$showSchoolFees = ($feesScope !== 'extra');
$showExtraFees = ($feesScope !== 'school');
$incomeSummary = $incomeSummary ?? [];
$isDetailed = ($reportType === 'detailed');
$isClassBalance = ($reportType === 'class_balance');
$isIncomeSummary = ($reportType === 'income_summary');
$smsBaseUrl = base_url('system-report/fees/2?' . $qp);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/fees-report.css'); ?>?v=6">

<div class="fr-page" id="feesReportPage">
	<div class="fr-center">

		<div class="fr-filter-card" id="frFilterCard">
			<form id="view_students_form" method="get" action="<?= base_url('system-report/fees'); ?>">
				<table class="fr-select-table">
					<tr>
						<th>Report type</th>
						<td>
							<select class="form-control" id="frReportType" name="rtype">
								<?php foreach ($reportTypeLabels as $rtypeKey => $rtypeLabel) : ?>
									<option value="<?= esc($rtypeKey, 'attr'); ?>" <?= $reportType === $rtypeKey ? 'selected' : ''; ?>>
										<?= esc($rtypeLabel); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<th id="frClassLabel"<?= $isIncomeSummary ? ' class="fr-field-hidden"' : ''; ?>><?= lang('app.sClass'); ?></th>
						<td id="frClassField"<?= $isIncomeSummary ? ' class="fr-field-hidden"' : ''; ?>>
							<select class="form-control" id="choose_class" name="c"<?= $isIncomeSummary ? '' : ' required'; ?>>
								<?php if ((int) $class_id <= 0) : ?>
									<option value="" disabled selected><?= lang('app.chooseClass'); ?></option>
								<?php endif; ?>
								<?php foreach ($classes as $classe) : ?>
									<option value="<?= (int) $classe['id']; ?>" <?= (int) $classe['id'] === (int) $class_id ? 'selected' : ''; ?>>
										<?= esc("{$classe['level_name']} {$classe['dept_code']} {$classe['title']}"); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<th><?= lang('app.academicYear'); ?></th>
						<td>
							<select class="form-control" name="academic">
								<?php foreach ($years as $year) : ?>
									<option value="<?= (int) $year['id']; ?>" <?= (int) $year['id'] === (int) $year_id ? 'selected' : ''; ?>>
										<?= esc($year['title']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?= lang('app.term'); ?></th>
						<td>
							<div class="fr-term-checks">
								<label class="fr-term-all"><input type="checkbox" id="frAllTerms"> All terms</label>
								<label class="fr-term-chip"><input type="checkbox" name="term[]" value="1" class="fr-term-cb"<?= in_array(1, $selectedTerms, true) ? ' checked' : ''; ?>> <?= lang('app.term1'); ?></label>
								<label class="fr-term-chip"><input type="checkbox" name="term[]" value="2" class="fr-term-cb"<?= in_array(2, $selectedTerms, true) ? ' checked' : ''; ?>> <?= lang('app.term2'); ?></label>
								<label class="fr-term-chip"><input type="checkbox" name="term[]" value="3" class="fr-term-cb"<?= in_array(3, $selectedTerms, true) ? ' checked' : ''; ?>> <?= lang('app.term3'); ?></label>
							</div>
						</td>
						<th>Fees type</th>
						<td>
							<select class="form-control" name="fscope" id="frFeesScopeInput">
								<option value="both"<?= $feesScope === 'both' ? ' selected' : ''; ?>>Both</option>
								<option value="school"<?= $feesScope === 'school' ? ' selected' : ''; ?>>School fees</option>
								<option value="extra"<?= $feesScope === 'extra' ? ' selected' : ''; ?>>Extra fees</option>
							</select>
						</td>
						<th id="frFilterStatusLabel"<?= $isIncomeSummary ? ' class="fr-field-hidden"' : ''; ?>>Payment status</th>
						<td id="frFilterStatusField"<?= $isIncomeSummary ? ' class="fr-field-hidden"' : ''; ?>>
							<select class="form-control" name="filter" id="frFilterInput">
								<option value="0"<?= (string) $filter === '0' ? ' selected' : ''; ?>>All</option>
								<option value="1"<?= (string) $filter === '1' ? ' selected' : ''; ?>>Full paid</option>
								<option value="2"<?= (string) $filter === '2' ? ' selected' : ''; ?>>Partial</option>
								<option value="3"<?= (string) $filter === '3' ? ' selected' : ''; ?>>Zero payment</option>
							</select>
						</td>
					</tr>
				</table>
			</form>
		</div>

		<?php if (!$hasResults) : ?>
			<div class="fr-panel">
				<div class="fr-empty">
					<i class="fa fa-chart-bar"></i>
					<h4>No report loaded</h4>
					<p>No classes found for this school, or select a class above to view fees collection.</p>
				</div>
			</div>
		<?php else : ?>

		<?php if (!$isIncomeSummary) : ?>
		<div class="fr-kpi-grid">
			<div class="fr-kpi">
				<div class="fr-kpi-icon blue"><i class="fa fa-users"></i></div>
				<div class="fr-kpi-value"><?= (int) $stats['total_students']; ?></div>
				<div class="fr-kpi-label">Students</div>
			</div>
			<?php if ($showSchoolFees) : ?>
			<div class="fr-kpi">
				<div class="fr-kpi-icon indigo"><i class="fa fa-school"></i></div>
				<div class="fr-kpi-value"><?= number_format((float) ($stats['total_school_expected'] ?? 0)); ?></div>
				<div class="fr-kpi-label">School fees (Rwf)</div>
			</div>
			<?php endif; ?>
			<?php if ($showExtraFees) : ?>
			<div class="fr-kpi">
				<div class="fr-kpi-icon purple"><i class="fa fa-plus-circle"></i></div>
				<div class="fr-kpi-value"><?= number_format((float) ($stats['total_extra_expected'] ?? 0)); ?></div>
				<div class="fr-kpi-label">Extra fees (Rwf)</div>
			</div>
			<?php endif; ?>
			<div class="fr-kpi">
				<div class="fr-kpi-icon green"><i class="fa fa-check-circle"></i></div>
				<div class="fr-kpi-value"><?= number_format((float) $stats['total_paid']); ?></div>
				<div class="fr-kpi-label">Collected (Rwf)</div>
			</div>
			<div class="fr-kpi">
				<div class="fr-kpi-icon orange"><i class="fa fa-clock"></i></div>
				<div class="fr-kpi-value"><?= number_format((float) $stats['total_remain']); ?></div>
				<div class="fr-kpi-label">Outstanding (Rwf)</div>
			</div>
			<div class="fr-kpi">
				<div class="fr-kpi-icon red"><i class="fa fa-exclamation-circle"></i></div>
				<div class="fr-kpi-value"><?= (int) $stats['zero_paid']; ?></div>
				<div class="fr-kpi-label">Zero payment</div>
			</div>
		</div>

		<?php if ($isDetailed) : ?>
		<div class="fr-progress-card">
			<div class="fr-progress-head">
				<span>Collection rate</span>
				<span><strong><?= esc($stats['collection_rate']); ?>%</strong> · <?= number_format((float) $stats['total_paid']); ?> / <?= number_format((float) $stats['total_expected']); ?> Rwf</span>
			</div>
			<div class="fr-progress-bar">
				<div class="fr-progress-fill" style="width:<?= min(100, (float) $stats['collection_rate']); ?>%"></div>
			</div>
		</div>
		<?php endif; ?>
		<?php endif; ?>

		<div class="fr-panel">
			<div class="fr-panel-head">
				<h3>
					<?php if ($isIncomeSummary) : ?>
						Summary of income · <?= esc($selectedYearTitle); ?> · <?= esc($termLabel); ?>
					<?php elseif ($isClassBalance) : ?>
						<?= (int) $stats['total_students']; ?> students · <?= esc($classLabel); ?> · Balance list
					<?php else : ?>
						<?= (int) $stats['total_students']; ?> students · <?= esc($classLabel); ?>
					<?php endif; ?>
				</h3>
				<div class="fr-actions">
					<a href="<?= base_url('system-report/fees/1?' . $qp); ?>" target="_blank" class="btn btn-danger">
						<i class="fa fa-file-pdf"></i> Export PDF
					</a>
				</div>
			</div>

			<?php if ($isClassBalance) : ?>
			<div class="fr-search-row">
				<input type="text" class="form-control" id="frSearch" placeholder="Filter by name…" style="max-width:320px;">
			</div>
			<div class="fr-table-wrap">
				<table class="table mb-0 fr-balance-table" id="frFeesTable">
					<thead>
					<tr>
						<th class="fr-th-no">NO</th>
						<th class="fr-th-names">NAMES</th>
						<th class="fr-th-dates">DATES</th>
						<th class="fr-th-due text-right">TOTAL FEES DUE</th>
						<th class="fr-th-paid text-right">TOTAL PAID</th>
						<th class="fr-th-bal text-right">BALANCES</th>
					</tr>
					</thead>
					<tbody>
					<?php $a = 1; foreach ($students as $student) :
						$norm = \App\Libraries\FeesReportHelper::normalizeCollectionAmounts(
							(float) ($student['amount'] ?? 0),
							(float) ($student['paid'] ?? 0)
						);
						$amt = $norm['due'];
						$paid = $norm['paid'];
						$balance = $norm['balance'];
						$dateStr = \App\Libraries\FeesReportHelper::formatPaymentDate($student['last_payment'] ?? '');
						$search = strtolower($student['student'] ?? '');
						?>
						<tr data-search="<?= esc($search, 'attr'); ?>">
							<td><?= $a++; ?></td>
							<td class="fr-balance-name"><?= esc(strtoupper($student['student'] ?? '')); ?></td>
							<td><?= $dateStr !== '' ? esc($dateStr) : '—'; ?></td>
							<td class="text-right"><?= number_format($amt); ?></td>
							<td class="text-right"><?= number_format($paid); ?></td>
							<td class="text-right fr-balance-cell<?= $balance > 0 ? ' has-balance' : ''; ?>"><?= number_format($balance); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot>
					<tr class="fr-balance-total">
						<td colspan="3"><strong>TOTAL</strong></td>
						<td class="text-right"><strong><?= number_format((float) ($stats['total_expected'] ?? 0)); ?></strong></td>
						<td class="text-right"><strong><?= number_format((float) ($stats['total_paid'] ?? 0)); ?></strong></td>
						<td class="text-right"><strong><?= number_format((float) ($stats['total_remain'] ?? 0)); ?></strong></td>
					</tr>
					</tfoot>
				</table>
			</div>

			<?php elseif ($isIncomeSummary) : ?>
			<div class="fr-income-banner">
				<div class="fr-income-banner-title">Summary of income</div>
				<div class="fr-income-banner-meta">School fees, transport, registration and feeding</div>
				<div class="fr-income-banner-meta"><?= esc($selectedYearTitle); ?> · <?= esc($termLabel); ?></div>
			</div>
			<div class="fr-income-kpi-row">
				<div class="fr-income-kpi due">
					<span class="lbl">Total due</span>
					<span class="val"><?= number_format((float) ($stats['total_expected'] ?? 0)); ?></span>
				</div>
				<div class="fr-income-kpi paid">
					<span class="lbl">Total paid</span>
					<span class="val"><?= number_format((float) ($stats['total_paid'] ?? 0)); ?></span>
				</div>
				<div class="fr-income-kpi bal">
					<span class="lbl">Balance</span>
					<span class="val"><?= number_format((float) ($stats['total_remain'] ?? 0)); ?></span>
				</div>
				<div class="fr-income-kpi pct">
					<span class="lbl">Collection</span>
					<span class="val"><?= esc(\App\Libraries\FeesReportHelper::formatPercent((float) ($stats['collection_rate'] ?? 0))); ?></span>
				</div>
			</div>
			<div class="fr-table-wrap fr-income-wrap">
				<table class="table mb-0 fr-income-table" id="frIncomeTable">
					<thead>
					<tr>
						<th class="fr-th-class">CLASS</th>
						<th class="fr-th-due text-right">TOTAL DUE</th>
						<th class="fr-th-paid text-right">TOTAL PAID</th>
						<th class="fr-th-bal text-right">BALANCE</th>
						<th class="fr-th-pct text-right">PERCENTAGES</th>
					</tr>
					</thead>
					<tbody>
					<?php
					$lastSection = '';
					foreach ($incomeSummary as $row) :
						$isSub = !empty($row['is_subtotal']);
						$section = (string) ($row['section'] ?? '');
						if (!$isSub && $section !== '' && $section !== $lastSection) :
							$lastSection = $section;
							?>
							<tr class="fr-income-section-head fr-section-<?= esc($section, 'attr'); ?>">
								<td colspan="5"><?= esc(\App\Libraries\FeesReportHelper::sectionHeadingLabel($section)); ?></td>
							</tr>
						<?php endif;
						$pct = (float) ($row['percent'] ?? 0);
						$balance = max(0, (float) ($row['balance'] ?? 0));
						$pctLevel = \App\Libraries\FeesReportHelper::percentLevel($pct);
						$rowClass = ($isSub ? 'fr-income-subtotal' : 'fr-income-row') . ' fr-section-' . $section;
						?>
						<tr class="<?= esc($rowClass, 'attr'); ?>">
							<td class="fr-income-class"><?= esc($row['class_label'] ?? ''); ?></td>
							<td class="text-right fr-num-due"><?= number_format((float) ($row['total_due'] ?? 0)); ?></td>
							<td class="text-right fr-num-paid"><?= number_format((float) ($row['total_paid'] ?? 0)); ?></td>
							<td class="text-right fr-num-bal<?= $balance > 0 ? ' has-balance' : ''; ?>"><?= number_format($balance); ?></td>
							<td class="text-right">
								<span class="fr-pct-badge pct-<?= esc($pctLevel, 'attr'); ?>"><?= esc(\App\Libraries\FeesReportHelper::formatPercent($pct)); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php else : ?>

			<?php if ($filter != '1' && $canFeesActions) : ?>
			<div class="fr-sms-bar">
				<div class="fr-sms-group">
					<button type="button" class="btn btn-info btn-sm" id="btn-send-fees-sms-all">
						<i class="fa fa-sms"></i> SMS all with balance
					</button>
				</div>
				<div class="fr-sms-group fr-sms-one">
					<input type="text" class="form-control form-control-sm" id="frSmsSearch" list="frSmsStudentList" placeholder="Search student to SMS…">
					<datalist id="frSmsStudentList">
						<?php foreach ($students as $student) :
							$bal = (float) ($student['amount'] ?? 0) - (float) ($student['paid'] ?? 0);
							if ($bal <= 0) continue;
							?>
							<option value="<?= esc($student['regno'] . ' — ' . $student['student'], 'attr'); ?>" data-id="<?= (int) $student['student_id']; ?>">
						<?php endforeach; ?>
					</datalist>
					<button type="button" class="btn btn-outline-info btn-sm" id="btn-send-fees-sms-one">
						<i class="fa fa-paper-plane"></i> Send SMS
					</button>
				</div>
			</div>
			<?php endif; ?>

			<?php $feeSections = $feeSections ?? []; ?>
			<div class="fr-search-row">
				<input type="text" class="form-control" id="frSearch" placeholder="Filter by name or reg no…" style="max-width:320px;">
				<span class="fr-fold-hint"><?= count($feeSections); ?> item<?= count($feeSections) === 1 ? '' : 's'; ?> · open a row to see students</span>
			</div>

			<div class="fr-folds" id="frFolds">
			<?php $foldOpen = true; foreach ($feeSections as $section) :
				$pct = (float) ($section['percent'] ?? 0);
				?>
				<section class="fr-fold<?= $foldOpen ? ' open' : ''; ?> fr-fold-<?= esc($section['kind'], 'attr'); ?>" data-fold="<?= esc($section['id'], 'attr'); ?>">
					<button type="button" class="fr-fold-head" aria-expanded="<?= $foldOpen ? 'true' : 'false'; ?>">
						<span class="fr-fold-chevron" aria-hidden="true"></span>
						<span class="fr-fold-title">
							<strong><?= esc($section['title']); ?></strong>
							<em><?= esc($section['term_label']); ?> only · <?= (int) $section['students']; ?> student<?= (int) $section['students'] === 1 ? '' : 's'; ?></em>
						</span>
						<span class="fr-fold-stat"><b><?= number_format((float) $section['expected']); ?></b><small>Expected</small></span>
						<span class="fr-fold-stat paid"><b><?= number_format((float) $section['paid']); ?></b><small>Collected</small></span>
						<span class="fr-fold-stat<?= (float) $section['balance'] > 0 ? ' bal' : ''; ?>"><b><?= number_format((float) $section['balance']); ?></b><small>Outstanding</small></span>
						<span class="fr-fold-pct"><?= number_format($pct, 0); ?>%</span>
						<span class="fr-fold-bar" aria-hidden="true"><span style="width:<?= number_format($pct, 1, '.', ''); ?>%"></span></span>
					</button>
					<div class="fr-fold-body">
						<div class="fr-table-wrap">
							<table class="table mb-0 fr-fold-table">
								<thead>
								<tr>
									<th>#</th>
									<th><?= lang('app.regNo'); ?></th>
									<th><?= lang('app.names'); ?></th>
									<th class="text-right">Expected</th>
									<th class="text-right">Paid</th>
									<th class="text-right">Balance</th>
									<th>Status</th>
									<?php if ($filter != '1' && $canFeesActions) : ?><th></th><?php endif; ?>
								</tr>
								</thead>
								<tbody>
								<?php $n = 1; foreach ($section['rows'] as $row) :
									$st = fr_payment_status((float) $row['expected'], (float) $row['paid']);
									$search = strtolower($row['name'] . ' ' . $row['regno']);
									?>
									<tr class="fr-fold-row" data-search="<?= esc($search, 'attr'); ?>" data-student-id="<?= (int) $row['student_id']; ?>" data-regno="<?= esc($row['regno'], 'attr'); ?>" data-name="<?= esc($row['name'], 'attr'); ?>">
										<td><?= $n++; ?></td>
										<td><a href="<?= base_url('student/' . (int) $row['student_id']); ?>" class="font-weight-bold"><?= esc($row['regno']); ?></a></td>
										<td><?= esc($row['name']); ?></td>
										<td class="text-right"><?= number_format((float) $row['expected']); ?></td>
										<td class="text-right"><?= number_format((float) $row['paid']); ?></td>
										<td class="text-right"><?= (float) $row['balance'] > 0 ? number_format((float) $row['balance']) : '—'; ?></td>
										<td><span class="fr-badge <?= esc($st['class']); ?>"><?= esc($st['label']); ?></span></td>
										<?php if ($filter != '1' && $canFeesActions) : ?>
										<td class="text-right">
											<?php if ((float) $row['balance'] > 0) : ?>
												<button type="button" class="btn btn-link btn-sm fr-btn-sms-row p-0" data-student-id="<?= (int) $row['student_id']; ?>" title="Send balance SMS">
													<i class="fa fa-sms text-info"></i>
												</button>
											<?php endif; ?>
										</td>
										<?php endif; ?>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</section>
			<?php $foldOpen = false; endforeach; ?>
			<?php if ($feeSections === []) : ?>
				<div class="fr-empty"><p>No fee items for the selected term.</p></div>
			<?php endif; ?>
			</div>

			<?php endif; ?>
		</div>

		<?php endif; ?>

	</div>
</div>

<script>
$(function () {
	const $form = $('#view_students_form');
	const smsBaseUrl = <?= json_encode($smsBaseUrl); ?>;
	const canFeesActions = <?= $canFeesActions ? 'true' : 'false'; ?>;

	function frSyncTermChips() {
		$('.fr-term-chip').each(function () {
			$(this).toggleClass('active', $(this).find('.fr-term-cb').is(':checked'));
		});
		const allOn = $('.fr-term-cb').length === $('.fr-term-cb:checked').length;
		$('#frAllTerms').prop('checked', allOn);
	}

	function frIsIncomeSummary() {
		return $('#frReportType').val() === 'income_summary';
	}

	function frToggleClassField() {
		const income = frIsIncomeSummary();
		$('#frClassField, #frClassLabel, #frFilterStatusField, #frFilterStatusLabel').toggleClass('fr-field-hidden', income);
		$('#choose_class').prop('required', !income).prop('disabled', income);
	}

	function frLoadReport() {
		frToggleClassField();
		if (!frIsIncomeSummary()) {
			const cls = $('#choose_class').val();
			if (!cls) return;
		}
		if ($('.fr-term-cb:checked').length === 0) {
			toastada.error('Select at least one term.');
			return;
		}
		$('#frFilterCard').addClass('fr-loading');
		$form[0].submit();
	}

	$('#frAllTerms').on('change', function () {
		$('.fr-term-cb').prop('checked', $(this).is(':checked'));
		frSyncTermChips();
		frLoadReport();
	});

	$(document).on('change', '.fr-term-cb', function () {
		frSyncTermChips();
		frLoadReport();
	});

	frSyncTermChips();
	frToggleClassField();

	$form.find('select').on('change', frLoadReport);

	$('#frFolds').on('click', '.fr-fold-head', function () {
		const $fold = $(this).closest('.fr-fold');
		const open = !$fold.hasClass('open');
		$fold.toggleClass('open', open);
		$(this).attr('aria-expanded', open ? 'true' : 'false');
	});

	$('#frSearch').on('input', function () {
		const q = $(this).val().toLowerCase().trim();
		$('#frFolds .fr-fold').each(function () {
			let any = false;
			$(this).find('.fr-fold-row').each(function () {
				const show = !q || String($(this).data('search') || '').indexOf(q) !== -1;
				$(this).toggle(show);
				if (show) any = true;
			});
			$(this).toggle(any);
			if (q && any) {
				$(this).addClass('open');
			}
		});
		$('#frFeesTable tbody tr').each(function () {
			const show = !q || String($(this).data('search') || '').indexOf(q) !== -1;
			$(this).toggle(show);
		});
	});

	if (canFeesActions && !frIsIncomeSummary()) {
	function frResolveStudentIdFromSearch() {
		const raw = $('#frSmsSearch').val().trim();
		if (!raw) return 0;
		let found = 0;
		$('#frFeesTable tbody tr, .fr-fold-row').each(function () {
			const reg = String($(this).data('regno') || '');
			const name = String($(this).data('name') || '');
			const label = reg + ' — ' + name;
			if (raw === label || raw === reg || name.toLowerCase() === raw.toLowerCase()) {
				found = parseInt($(this).data('student-id'), 10) || 0;
				return false;
			}
		});
		return found;
	}

	function frSendSms(studentId, $btn) {
		const label = studentId ? 'this student' : 'all students with balance';
		if (!confirm('Send balance reminder SMS to ' + label + '?')) return;
		const url = smsBaseUrl + (studentId ? '&student_id=' + studentId : '');
		const orig = $btn.html();
		$btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
		$.getJSON(url, function (data) {
			$btn.prop('disabled', false).html(orig);
			if (data.success) toastada.success(data.success);
			else toastada.error(data.error || 'SMS failed.');
		}).fail(function () {
			$btn.prop('disabled', false).html(orig);
			toastada.error('SMS request failed.');
		});
	}

	$('#btn-send-fees-sms-all').on('click', function () {
		frSendSms(0, $(this));
	});

	$('#btn-send-fees-sms-one').on('click', function () {
		const sid = frResolveStudentIdFromSearch();
		if (!sid) {
			toastada.error('Select a student from the list (with balance due).');
			return;
		}
		frSendSms(sid, $(this));
	});

	$(document).on('click', '.fr-btn-sms-row', function () {
		frSendSms(parseInt($(this).data('student-id'), 10) || 0, $(this));
	});
	}
});
</script>
