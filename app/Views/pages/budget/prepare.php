<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=19" rel="stylesheet">

<?php $tab = $tab ?? 'budgets'; ?>

<div class="budget-prep-list">

<div class="bp-hero mb-3">
	<h2><i class="fa fa-calculator"></i> Prepare Annual Budget</h2>
	<p class="bp-meta mb-0"><?= esc($branch_label ?? ''); ?></p>
</div>

<?= view('pages/budget/partials/hub_nav', ['hub' => 'prepare', 'tab' => $tab]); ?>

<?php if ($tab === 'budgets') { ?>

<?php
$canUploadBudget = \Config\MenuClearance::canPrepareBudgetAtSchool((int) ($_SESSION['soma_post'] ?? 0))
	&& function_exists('budget_permission_allowed') && budget_permission_allowed('budget.prepare');
?>
<?php if ($canUploadBudget) { ?>
<div class="card border-0 shadow-sm mb-4">
	<div class="card-body">
		<h5 class="font-weight-bold mb-1"><i class="fa fa-file-excel text-success"></i> Upload Excel budget</h5>
		<p class="text-muted small mb-3">Upload this school's own Excel budget and choose the term those amounts belong to. Line names can differ from school to school, and School Fees stay exactly as they are in the file. Only one budget is allowed for each academic year. Uploading again for another term keeps the amounts already saved in the other terms.</p>
		<form id="frmUploadBudget" enctype="multipart/form-data">
			<div class="form-row">
				<div class="form-group col-md-5 mb-2">
					<label class="small font-weight-bold">Excel file (.xlsx)</label>
					<input type="file" name="budget_file" id="budgetFile" class="form-control" accept=".xlsx,.xls" required>
				</div>
				<div class="form-group col-md-3 mb-2">
					<label class="small font-weight-bold">Academic year</label>
					<input class="form-control" name="academic_year" value="<?= date('Y'); ?>-<?= substr((string) (date('Y') + 1), -2); ?>" placeholder="2026-27">
				</div>
				<div class="form-group col-md-4 mb-2">
					<label class="small font-weight-bold">Term</label>
					<select class="form-control" name="budget_term" required>
						<option value="">— Select term —</option>
						<option value="1">Term I</option>
						<option value="2">Term II</option>
						<option value="3">Term III</option>
					</select>
				</div>
			</div>
			<button type="submit" class="btn btn-primary" id="btnUploadBudget"><i class="fa fa-upload"></i> Upload and extract lines</button>
		</form>
	</div>
</div>
<?php } ?>

<div class="d-flex flex-wrap align-items-center justify-content-between">
	<?= view('pages/budget/partials/term_view_switch'); ?>
	<?php if (\Config\MenuClearance::isBudgetViewOnlyPost((int) ($_SESSION['soma_post'] ?? 0))) { ?>
	<a href="<?= base_url('budget/dashboard'); ?>" class="btn btn-outline-secondary mb-3"><i class="fa fa-eye"></i> View dashboard</a>
	<?php } ?>
</div>

<div class="row mb-4">
	<div class="col-lg-7">
<?php if (empty($budgets)) { ?>
<div class="bp-empty">
	<i class="fa fa-file-invoice-dollar d-block"></i>
	<h5>No budget uploaded yet</h5>
	<p class="text-muted">Upload this school's Excel file. Its own budget lines and term amounts are extracted here.</p>
</div>
<?php } else { ?>
<?php
$postIdSession = (int) ($_SESSION['soma_post'] ?? 0);
$canPrepareUi = \Config\MenuClearance::canPrepareBudgetAtSchool($postIdSession);
$viewOnlyUi = \Config\MenuClearance::isBudgetViewOnlyPost($postIdSession);
$isDirectorUi = \Config\MenuClearance::isDirectorPost($postIdSession);
foreach ($budgets as $b) {
	$statusClass = $b['status'] === 'DRAFT' ? 'secondary' : ($b['status'] === 'APPROVED' ? 'success' : 'info');
?>
<div class="bp-budget-card">
	<div class="d-flex justify-content-between align-items-start mb-2">
		<div>
			<h5 class="mb-1 font-weight-bold"><?= esc($b['title']); ?></h5>
			<?php if (!empty($show_all_schools) && !empty($b['branch_name'])) { ?>
			<div class="small text-muted mb-1"><i class="fa fa-school"></i> <?= esc($b['branch_name']); ?></div>
			<?php } ?>
			<span class="badge badge-<?= $statusClass; ?>"><?= esc($b['pending_label'] ?? $b['status']); ?></span>
			<?= view('pages/budget/partials/excel_download', ['budget' => $b, 'show_name' => true]); ?>
		</div>
		<div class="text-right">
			<?php
			$canFinanceAdjust = function_exists('budget_permission_allowed') && budget_permission_allowed('budget.edit_submitted');
			$isPreparerEdit = in_array($b['status'], ['DRAFT', 'RETURNED'], true);
			$isSubmittedPipeline = in_array($b['status'], ['SUBMITTED', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW', 'DEPUTY_DIRECTOR_REVIEW', 'APPROVED', 'REJECTED'], true);
			?>
			<?= view('pages/budget/partials/excel_download', ['budget' => $b]); ?>
			<?php if (\Config\MenuClearance::isHeadMasterEquivalent($postIdSession) && ($b['status'] ?? '') === 'HEADTEACHER_REVIEW') { ?>
			<button type="button" class="btn btn-sm btn-success mb-1 btn-ht-approve" data-id="<?= (int) $b['id']; ?>" data-title="<?= esc($b['title']); ?>"><i class="fa fa-check"></i> Approve</button>
			<?php } ?>
			<?php if ($isDirectorUi) { ?>
			<a href="<?= base_url('budget/edit_budget/'.$b['id']); ?>" class="btn btn-sm btn-outline-primary mb-1"><i class="fa fa-list"></i> View lines</a>
			<button type="button" class="btn btn-sm btn-outline-danger mb-1 btn-del-budget" data-id="<?= (int) $b['id']; ?>" data-title="<?= esc($b['title']); ?>"><i class="fa fa-trash"></i> Delete</button>
			<?php } elseif ($viewOnlyUi) { ?>
			<a href="<?= base_url('budget/dashboard'); ?>" class="btn btn-sm btn-outline-secondary mb-1"><i class="fa fa-eye"></i> View on dashboard</a>
			<?php } else { ?>
			<a href="<?= base_url('budget/edit_budget/'.$b['id']); ?>" class="btn btn-sm btn-primary mb-1"><i class="fa fa-eye"></i> <?= ($canPrepareUi && $isPreparerEdit) || ($canFinanceAdjust && $isSubmittedPipeline) ? 'Open budget' : 'View lines'; ?></a>
			<?php } ?>
			<?php if ($canPrepareUi && !in_array($b['status'], ['CANCELLED'], true)) { ?>
			<button type="button" class="btn btn-sm btn-outline-warning mb-1 btn-cancel-budget" data-id="<?= (int)$b['id']; ?>" data-title="<?= esc($b['title']); ?>"><i class="fa fa-ban"></i> Cancel</button>
			<?php } ?>
			<?php if (!$viewOnlyUi && $b['status'] === 'APPROVED') { ?>
			<a href="<?= base_url('budget/cash_request_form'); ?>" class="btn btn-sm btn-success mb-1"><i class="fa fa-money-bill"></i> New request</a>
			<?php } elseif (!$viewOnlyUi && !$isPreparerEdit && $b['status'] !== 'APPROVED') { ?>
			<a href="<?= base_url('budget/prepare?tab=review'); ?>" class="btn btn-sm btn-outline-info mb-1"><i class="fa fa-tasks"></i> In approval</a>
			<?php } ?>
			<?php if ($canPrepareUi && function_exists('budget_permission_allowed') && (budget_permission_allowed('budget.prepare') || budget_permission_allowed('budget.edit_own') || budget_permission_allowed('budget.final_approve') || budget_permission_allowed('budget.edit_submitted'))) { ?>
			<button type="button" class="btn btn-sm btn-outline-danger mb-1 btn-del-budget" data-id="<?= (int)$b['id']; ?>" data-title="<?= esc($b['title']); ?>"><i class="fa fa-trash"></i> Delete</button>
			<?php } ?>
		</div>
	</div>
	<?php
	$cardTerms = $b['terms'] ?? [];
	$cardInc = [
		1 => (float) ($cardTerms[1]['income'] ?? 0),
		2 => (float) ($cardTerms[2]['income'] ?? 0),
		3 => (float) ($cardTerms[3]['income'] ?? 0),
	];
	$cardExp = [
		1 => (float) ($cardTerms[1]['expense'] ?? 0),
		2 => (float) ($cardTerms[2]['expense'] ?? 0),
		3 => (float) ($cardTerms[3]['expense'] ?? 0),
	];
	?>
	<div class="bp-year-figures">
		<div><span class="term-label" data-label-y="Income (year)" data-label-1="Income (Term I)" data-label-2="Income (Term II)" data-label-3="Income (Term III)">Income (year)</span><strong class="text-success term-money" data-y="<?= (float) $b['total_income']; ?>" data-t1="<?= $cardInc[1]; ?>" data-t2="<?= $cardInc[2]; ?>" data-t3="<?= $cardInc[3]; ?>"><?= number_format((float)$b['total_income'], 0); ?></strong></div>
		<div><span class="term-label" data-label-y="Expenses (year)" data-label-1="Expenses (Term I)" data-label-2="Expenses (Term II)" data-label-3="Expenses (Term III)">Expenses (year)</span><strong class="text-danger term-money" data-y="<?= (float) $b['total_expenses']; ?>" data-t1="<?= $cardExp[1]; ?>" data-t2="<?= $cardExp[2]; ?>" data-t3="<?= $cardExp[3]; ?>"><?= number_format((float)$b['total_expenses'], 0); ?></strong></div>
		<div><span class="term-label" data-label-y="Surplus" data-label-1="Surplus (Term I)" data-label-2="Surplus (Term II)" data-label-3="Surplus (Term III)">Surplus</span><strong class="term-money term-surplus <?= (float)$b['surplus_deficit'] >= 0 ? 'text-success' : 'text-danger'; ?>" data-y="<?= (float) $b['surplus_deficit']; ?>" data-t1="<?= $cardInc[1] - $cardExp[1]; ?>" data-t2="<?= $cardInc[2] - $cardExp[2]; ?>" data-t3="<?= $cardInc[3] - $cardExp[3]; ?>"><?= number_format((float)$b['surplus_deficit'], 0); ?></strong></div>
	</div>
	<?php if (!empty($b['terms'])) { ?>
	<div class="mt-3"><?= view('pages/budget/partials/term_kpi_board', ['term_figures' => $b['terms']]); ?></div>
	<?php } ?>
</div>
<?php } ?>
<?php } ?>
	</div>
	<div class="col-lg-5"><?= view('pages/budget/partials/process_guide', ['ctx' => 'full', 'compact' => true]); ?></div>
</div>

<script>
$('#frmUploadBudget').on('submit', function (e) {
	e.preventDefault();
	var $btn = $('#btnUploadBudget').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Extracting...');
	var data = new FormData(this);
	$.ajax({
		url: '<?= base_url('budget/upload_prepared_budget'); ?>',
		type: 'POST',
		data: data,
		processData: false,
		contentType: false,
		dataType: 'json'
	}).done(function (r) {
		if (r.error) {
			toastada.error(r.error);
			$btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Upload and extract lines');
			return;
		}
		toastada.success(r.success || 'Budget extracted');
		location.href = '<?= base_url('budget/edit_budget/'); ?>' + r.budget_id;
	}).fail(function () {
		$btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Upload and extract lines');
		toastada.error('Upload failed. Use the Wisdom .xlsx template.');
	});
});
$(document).on('click', '.btn-cancel-budget', function () {
	var id = $(this).data('id');
	var title = $(this).data('title') || 'this budget';
	if (!confirm('Cancel "' + title + '"?\n\nYou can then upload a new Excel file. Budgets that already have cash requests cannot be cancelled.')) return;
	var $btn = $(this).prop('disabled', true);
	$.post('<?= base_url('budget/cancel_budget'); ?>', { budget_id: id }, function (r) {
		if (r.error) { toastada.error(r.error); $btn.prop('disabled', false); return; }
		toastada.success(r.success || 'Cancelled');
		location.reload();
	}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Cancel failed'); });
});
$(document).on('click', '.btn-ht-approve', function () {
	var id = $(this).data('id');
	var title = $(this).data('title') || 'this budget';
	if (!confirm('Approve "' + title + '" as the head teacher of this school?\n\nIt then goes to the Chief Accountant, and after that to the Director of Finance.')) return;
	var $btn = $(this).prop('disabled', true);
	$.post('<?= base_url('budget/budget_action'); ?>', { budget_id: id, action: 'headteacher_approve' }, function (r) {
		if (r.error) { toastada.error(r.error); $btn.prop('disabled', false); return; }
		toastada.success('Sent to the Chief Accountant');
		location.reload();
	}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Approval failed'); });
});
$(document).on('click', '.btn-del-budget', function () {
	var id = $(this).data('id');
	var title = $(this).data('title') || 'this budget';
	if (!confirm('Delete "' + title + '" for this school?\n\nLines and approval history will be removed. Budgets with cash requests cannot be deleted.')) return;
	var $btn = $(this).prop('disabled', true);
	$.post('<?= base_url('budget/delete_budget'); ?>', { budget_id: id }, function (r) {
		if (r.error) { toastada.error(r.error); $btn.prop('disabled', false); return; }
		toastada.success(r.success || 'Deleted');
		location.reload();
	}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Delete failed'); });
});
</script>

<?php } elseif ($tab === 'periods') { ?>
<?php
$periods = $periods ?? [];
$branches = $branches ?? [];
echo view('pages/budget/periods', compact('periods', 'branches'));
?>

<?php } elseif ($tab === 'review') { ?>
<?php $budgets = $review_budgets ?? []; echo view('pages/budget/budget_review', compact('budgets')); ?>

<?php } else { ?>
<?php $budgets = $approved_budgets ?? []; echo view('pages/budget/approved_budgets', compact('budgets')); ?>
<?php } ?>

</div>
