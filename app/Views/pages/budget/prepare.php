<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=11" rel="stylesheet">

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
		<p class="text-muted small mb-3">Upload this school's own Excel budget. Line names can differ from school to school, and School Fees stay exactly as they are in the file. Only one budget is allowed for each academic year. After it is approved it stays locked until the Chief Accountant opens Term I, Term II, or Term III.</p>
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
					<label class="small font-weight-bold">Title (optional)</label>
					<input class="form-control" name="title" placeholder="Taken from the Excel sheet if empty">
				</div>
			</div>
			<button type="submit" class="btn btn-primary" id="btnUploadBudget"><i class="fa fa-upload"></i> Upload and extract lines</button>
		</form>
	</div>
</div>
<?php } elseif (\Config\MenuClearance::isBudgetViewOnlyPost((int) ($_SESSION['soma_post'] ?? 0))) { ?>
<a href="<?= base_url('budget/dashboard'); ?>" class="btn btn-outline-secondary mb-3"><i class="fa fa-eye"></i> View dashboard</a>
<?php } ?>

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
foreach ($budgets as $b) {
	$statusClass = $b['status'] === 'DRAFT' ? 'secondary' : ($b['status'] === 'APPROVED' ? 'success' : 'info');
?>
<div class="bp-budget-card">
	<div class="d-flex justify-content-between align-items-start mb-2">
		<div>
			<h5 class="mb-1 font-weight-bold"><?= esc($b['title']); ?></h5>
			<span class="badge badge-<?= $statusClass; ?>"><?= esc($b['pending_label'] ?? $b['status']); ?></span>
		</div>
		<div class="text-right">
			<?php
			$canFinanceAdjust = function_exists('budget_permission_allowed') && budget_permission_allowed('budget.edit_submitted');
			$isPreparerEdit = in_array($b['status'], ['DRAFT', 'RETURNED'], true);
			$isSubmittedPipeline = in_array($b['status'], ['SUBMITTED', 'PROCUREMENT_REVIEW', 'BUDGET_MANAGER_REVIEW', 'DEPUTY_DIRECTOR_REVIEW', 'APPROVED', 'REJECTED'], true);
			?>
			<?php if ($viewOnlyUi) { ?>
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
	<div class="row small text-muted mb-0">
		<div class="col-4">Income (year)<br><strong class="text-success"><?= number_format((float)$b['total_income'], 0); ?></strong></div>
		<div class="col-4">Expenses (year)<br><strong class="text-danger"><?= number_format((float)$b['total_expenses'], 0); ?></strong></div>
		<div class="col-4">Surplus<br><strong class="<?= (float)$b['surplus_deficit'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?= number_format((float)$b['surplus_deficit'], 0); ?></strong></div>
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
