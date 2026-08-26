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

$canFeesActions = !empty($canFeesActions);
$qp = $queryParams ?? '';
$smsBaseUrl = base_url('system-report/fees/2?' . $qp);
?>
<link rel="stylesheet" href="<?= base_url('assets/css/fees-report.css'); ?>">

<div class="fr-page" id="feesReportPage">
	<div class="fr-center">

		<header class="fr-header">
			<h2><?= esc(lang('app.feesReport')); ?></h2>
			<p>
				<?php if ($hasResults) : ?>
					<?= esc($classLabel); ?> · <?= esc($selectedYearTitle); ?> · <?= esc($termLabel); ?>
				<?php else : ?>
					Choose filters below — report updates automatically
				<?php endif; ?>
			</p>
			<?php $actor = trim((string) (session('soma_name') ?? '')); if ($actor !== '') : ?>
				<p class="fr-logged-as"><?= esc(lang('app.actingAs')); ?>: <strong><?= esc($actor); ?></strong></p>
			<?php endif; ?>
		</header>

		<div class="fr-filter-card" id="frFilterCard">
			<form id="view_students_form" method="get" action="<?= base_url('system-report/fees'); ?>">
				<div class="fr-filter-row">
					<div class="fr-field">
						<label for="choose_class"><?= lang('app.sClass'); ?></label>
						<select class="form-control select2" id="choose_class" name="c" required>
							<?php if ((int) $class_id <= 0) : ?>
								<option value="" disabled selected><?= lang('app.chooseClass'); ?></option>
							<?php endif; ?>
							<?php foreach ($classes as $classe) : ?>
								<option value="<?= (int) $classe['id']; ?>" <?= (int) $classe['id'] === (int) $class_id ? 'selected' : ''; ?>>
									<?= esc("{$classe['level_name']} {$classe['dept_code']} {$classe['title']}"); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="fr-field">
						<label><?= lang('app.academicYear'); ?></label>
						<select class="form-control select2" name="academic">
							<?php foreach ($years as $year) : ?>
								<option value="<?= (int) $year['id']; ?>" <?= (int) $year['id'] === (int) $year_id ? 'selected' : ''; ?>>
									<?= esc($year['title']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="fr-field fr-field-terms">
						<label><?= lang('app.term'); ?></label>
						<div class="fr-term-picker">
							<label class="fr-term-all">
								<input type="checkbox" id="frAllTerms">
								<span>All terms</span>
							</label>
							<div class="fr-term-chips" id="frTermChips">
								<label class="fr-term-chip t1">
									<input type="checkbox" name="term[]" value="1" class="fr-term-cb"<?= in_array(1, $selectedTerms, true) ? ' checked' : ''; ?>>
									<span><?= lang('app.term1'); ?></span>
								</label>
								<label class="fr-term-chip t2">
									<input type="checkbox" name="term[]" value="2" class="fr-term-cb"<?= in_array(2, $selectedTerms, true) ? ' checked' : ''; ?>>
									<span><?= lang('app.term2'); ?></span>
								</label>
								<label class="fr-term-chip t3">
									<input type="checkbox" name="term[]" value="3" class="fr-term-cb"<?= in_array(3, $selectedTerms, true) ? ' checked' : ''; ?>>
									<span><?= lang('app.term3'); ?></span>
								</label>
							</div>
						</div>
					</div>
					<div class="fr-field">
						<label>Payment status</label>
						<input type="hidden" name="filter" id="frFilterInput" value="<?= esc($filter); ?>">
						<div class="fr-status-pills" id="frStatusPills" role="group" aria-label="Payment status filter">
							<button type="button" class="fr-status-pill pill-all<?= $filter === '0' ? ' active' : ''; ?>" data-filter="0">All</button>
							<button type="button" class="fr-status-pill pill-full<?= $filter === '1' ? ' active active-green' : ''; ?>" data-filter="1">Full paid</button>
							<button type="button" class="fr-status-pill pill-partial<?= $filter === '2' ? ' active active-amber' : ''; ?>" data-filter="2">Partial</button>
							<button type="button" class="fr-status-pill pill-zero<?= $filter === '3' ? ' active active-red' : ''; ?>" data-filter="3">Zero payment</button>
						</div>
					</div>
				</div>
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

		<div class="fr-kpi-grid">
			<div class="fr-kpi">
				<div class="fr-kpi-icon blue"><i class="fa fa-users"></i></div>
				<div class="fr-kpi-value"><?= (int) $stats['total_students']; ?></div>
				<div class="fr-kpi-label">Students</div>
			</div>
			<div class="fr-kpi">
				<div class="fr-kpi-icon indigo"><i class="fa fa-school"></i></div>
				<div class="fr-kpi-value"><?= number_format((float) ($stats['total_school_expected'] ?? 0)); ?></div>
				<div class="fr-kpi-label">School fees (Rwf)</div>
			</div>
			<div class="fr-kpi">
				<div class="fr-kpi-icon purple"><i class="fa fa-plus-circle"></i></div>
				<div class="fr-kpi-value"><?= number_format((float) ($stats['total_extra_expected'] ?? 0)); ?></div>
				<div class="fr-kpi-label">Extra fees (Rwf)</div>
			</div>
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

		<div class="fr-progress-card">
			<div class="fr-progress-head">
				<span>Collection rate</span>
				<span><strong><?= esc($stats['collection_rate']); ?>%</strong> · <?= number_format((float) $stats['total_paid']); ?> / <?= number_format((float) $stats['total_expected']); ?> Rwf</span>
			</div>
			<div class="fr-progress-bar">
				<div class="fr-progress-fill" style="width:<?= min(100, (float) $stats['collection_rate']); ?>%"></div>
			</div>
		</div>

		<div class="fr-panel">
			<div class="fr-panel-head">
				<h3><?= (int) $stats['total_students']; ?> students · <?= esc($classLabel); ?></h3>
				<div class="fr-actions">
					<a href="<?= base_url('system-report/fees/1?' . $qp); ?>" target="_blank" class="btn btn-danger">
						<i class="fa fa-file-pdf"></i> Export PDF
					</a>
				</div>
			</div>

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

			<div class="fr-search-row">
				<input type="text" class="form-control" id="frSearch" placeholder="Filter table by name or reg no…" style="max-width:320px;">
			</div>

			<div class="fr-table-wrap">
				<table class="table mb-0" id="frFeesTable">
					<thead>
					<tr>
						<th>#</th>
						<th><?= lang('app.regNo'); ?></th>
						<th><?= lang('app.names'); ?></th>
						<th><?= lang('app.slipReference'); ?></th>
						<th class="text-right">School fees</th>
						<th class="text-right">Extra fees</th>
						<th class="text-right">Paid</th>
						<th class="text-right">Balance</th>
						<th>Status</th>
						<th><?= lang('app.recordedBy'); ?></th>
						<?php if ($filter != '1' && $canFeesActions) : ?><th></th><?php endif; ?>
					</tr>
					</thead>
					<tbody>
					<?php $a = 1; foreach ($students as $student) :
						$amt = (float) ($student['amount'] ?? 0);
						$schoolAmt = (float) ($student['school_amount'] ?? 0);
						$extraAmt = (float) ($student['extra_amount'] ?? 0);
						$paid = (float) ($student['paid'] ?? 0);
						$st = fr_payment_status($amt, $paid);
						$pct = $amt > 0 ? min(100, round(($paid / $amt) * 100)) : 0;
						$refs = trim((string) ($student['ref_nos'] ?? ''));
						$actors = trim((string) ($student['recorded_by_names'] ?? ''));
						$search = strtolower($student['student'] . ' ' . $student['regno'] . ' ' . $refs . ' ' . $actors);
						$sid = (int) $student['student_id'];
						?>
						<tr data-search="<?= esc($search, 'attr'); ?>" data-student-id="<?= $sid; ?>" data-regno="<?= esc($student['regno'], 'attr'); ?>" data-name="<?= esc($student['student'], 'attr'); ?>">
							<td><?= $a++; ?></td>
							<td>
								<a href="<?= base_url('student/' . $sid); ?>" class="font-weight-bold">
									<?= esc($student['regno']); ?>
								</a>
							</td>
							<td><?= esc($student['student']); ?></td>
							<td class="fr-ref-cell">
								<?php if ($refs !== '') : ?>
									<span class="fr-ref"><?= esc($refs); ?></span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td class="text-right fr-amount-school"><?= number_format($schoolAmt); ?></td>
							<td class="text-right fr-amount-extra"><?= number_format($extraAmt); ?></td>
							<td class="text-right fr-amount-paid">
								<?= number_format($paid); ?>
								<span class="fr-mini-bar" title="<?= $pct; ?>%"><span style="width:<?= $pct; ?>%"></span></span>
							</td>
							<td class="text-right fr-amount-remain"><?= $st['remain'] > 0 ? number_format($st['remain']) : '—'; ?></td>
							<td><span class="fr-badge <?= esc($st['class']); ?>"><?= esc($st['label']); ?></span></td>
							<td class="fr-actor-cell">
								<?php if ($actors !== '') : ?>
									<span class="fr-actor"><?= esc($actors); ?></span>
								<?php else : ?>
									<span class="text-muted">—</span>
								<?php endif; ?>
							</td>
							<?php if ($filter != '1' && $canFeesActions) : ?>
							<td class="text-right">
								<?php if ($st['remain'] > 0) : ?>
									<button type="button" class="btn btn-link btn-sm fr-btn-sms-row p-0" data-student-id="<?= $sid; ?>" title="Send balance SMS">
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

	function frLoadReport() {
		const cls = $('#choose_class').val();
		if (!cls) return;
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

	$form.find('select[name="c"], select[name="academic"]').on('change', frLoadReport);

	$('#frStatusPills .fr-status-pill').on('click', function () {
		const f = String($(this).data('filter'));
		if ($('#frFilterInput').val() === f) return;
		$('#frFilterInput').val(f);
		$('#frStatusPills .fr-status-pill').removeClass('active active-green active-amber active-red');
		$(this).addClass('active');
		if (f === '1') $(this).addClass('active-green');
		if (f === '2') $(this).addClass('active-amber');
		if (f === '3') $(this).addClass('active-red');
		frLoadReport();
	});

	$('#frSearch').on('input', function () {
		const q = $(this).val().toLowerCase().trim();
		$('#frFeesTable tbody tr').each(function () {
			const show = !q || String($(this).data('search') || '').indexOf(q) !== -1;
			$(this).toggle(show);
		});
	});

	if (canFeesActions) {
	function frResolveStudentIdFromSearch() {
		const raw = $('#frSmsSearch').val().trim();
		if (!raw) return 0;
		let found = 0;
		$('#frFeesTable tbody tr').each(function () {
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
