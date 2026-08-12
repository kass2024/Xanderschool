<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=5" rel="stylesheet">

<?php
$statusBadge = $budget['status'] === 'DRAFT' ? 'secondary' : ($budget['status'] === 'APPROVED' ? 'success' : 'warning');
$canEdit = isset($can_edit) ? (bool) $can_edit : in_array($budget['status'], ['DRAFT', 'RETURNED'], true);
$isFinanceAdjust = !empty($is_finance_adjust);
$canSubmit = isset($can_submit) ? (bool) $can_submit : ($canEdit && in_array($budget['status'], ['DRAFT', 'RETURNED'], true));
$academicYear = $setup['academic_year'] ?? '';
$branchFillMode = !empty($budget_branch_fill);
?>

<div class="budget-workspace" id="budgetWorkspace" data-budget-id="<?= (int)$budget['id']; ?>">

<div class="bp-hero">
	<div class="d-flex flex-wrap justify-content-between align-items-start">
		<div>
			<h2><i class="fa fa-chart-pie"></i> <?= esc($budget['title']); ?></h2>
			<p class="bp-meta mb-0"><?= esc($branch_label); ?> · <?= esc($academicYear ?: ($budget['period_title'] ?? 'Annual')); ?> · <?= esc($budget['currency'] ?? 'RWF'); ?>
			<span class="badge badge-<?= $statusBadge; ?> ml-2"><?= esc($budget['status']); ?></span>
			<?php if ($isFinanceAdjust) { ?>
			<span class="badge badge-warning ml-1">Director of Finance edit</span>
			<?php } ?>
			</p>
		</div>
		<a href="<?= base_url('budget/prepare'); ?>" class="btn btn-sm btn-light"><i class="fa fa-arrow-left"></i> All budgets</a>
	</div>
	<div class="bp-kpi-row">
		<div class="bp-kpi income"><label>Annual income</label><strong id="kpiIncome"><?= number_format((float)$budget['total_income'], 0); ?></strong></div>
		<div class="bp-kpi expense"><label>Annual expenses</label><strong id="kpiExpense"><?= number_format((float)$budget['total_expenses'], 0); ?></strong></div>
		<div class="bp-kpi surplus <?= (float)$budget['surplus_deficit'] >= 0 ? 'pos' : 'neg'; ?>"><label>Surplus / deficit</label><strong id="kpiSurplus"><?= number_format((float)$budget['surplus_deficit'], 0); ?></strong></div>
		<div class="bp-kpi"><label>Completion</label><strong id="kpiProgress">0%</strong></div>
	</div>
	<div class="bp-progress mt-2"><div class="bp-progress-bar" id="progressBar" style="width:0%"></div></div>
</div>

<div class="bp-stepper" id="bpStepper">
	<button type="button" class="bp-step active" data-tab="setup" data-step="1">
		<span class="bp-step-icon">1</span>
		<span><span class="bp-step-label">Setup</span><span class="bp-step-desc">Academic year &amp; assumptions</span></span>
	</button>
	<button type="button" class="bp-step" data-tab="plan" data-step="2">
		<span class="bp-step-icon">2</span>
		<span><span class="bp-step-label">Three-term budget</span><span class="bp-step-desc">Term I · II · III amounts</span></span>
	</button>
	<button type="button" class="bp-step" data-tab="summary" data-step="3">
		<span class="bp-step-icon">3</span>
		<span><span class="bp-step-label">Summary &amp; submit</span><span class="bp-step-desc">Review full year</span></span>
	</button>
</div>

<div class="bp-toolbar">
	<div class="bp-save-status saved" id="saveStatus"><i class="fa fa-check"></i> All changes saved</div>
	<div>
		<?php if ($canEdit) { ?>
		<?php if ($isFinanceAdjust) { ?>
		<span class="badge badge-warning mr-2 p-2"><i class="fa fa-user-tie"></i> Finance adjust — status stays <?= esc($budget['status']); ?>; school submissions still follow verification</span>
		<?php } elseif (!$branchFillMode) { ?>
		<button type="button" class="btn btn-outline-success btn-sm mr-1" id="btnFillExcel"><i class="fa fa-file-excel"></i> Fill from Excel</button>
		<?php } else { ?>
		<span class="badge badge-info mr-2 p-2">Branch fill — lines from master; enter your term amounts (totals may differ per school)</span>
		<?php } ?>
		<button type="button" class="btn btn-primary btn-sm" id="btnSave"><i class="fa fa-save"></i> Save</button>
		<?php if ($canSubmit) { ?>
		<button type="button" class="btn btn-success btn-sm" id="btnSubmit"><i class="fa fa-paper-plane"></i> Submit</button>
		<?php } ?>
		<?php } else { ?>
		<span class="badge badge-info p-2">Read-only — <?= esc($budget['status']); ?></span>
		<?php } ?>
	</div>
</div>

<div class="bp-layout">
<div class="bp-main">

<form id="frmBudgetWorkspace">
<input type="hidden" name="budget_id" value="<?= (int)$budget['id']; ?>">

<!-- SETUP -->
<div class="bp-panel active" id="panel-setup">
<div class="bp-panel-help"><i class="fa fa-info-circle"></i> <strong>Step 1 — Academic year setup.</strong> Record enrollment, opening cash, and planning notes before entering term amounts.</div>
<div class="bp-setup-grid">
	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">BRANCH</label><div class="h6 mb-0"><?= esc($branch_label); ?></div></div>
	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">PERIOD</label><div class="h6 mb-0"><?= esc($budget['period_title'] ?? '—'); ?></div></div>
	<div class="bp-setup-card"><label class="small font-weight-bold text-muted">CURRENCY</label><div class="h6 mb-0"><?= esc($budget['currency'] ?? 'RWF'); ?></div></div>
</div>
<div class="card border-0 shadow-sm mt-3"><div class="card-body">
	<div class="row">
		<div class="col-md-6 form-group"><label class="font-weight-bold">Budget title</label><input class="form-control" name="setup_title" id="setupTitle" value="<?= esc($budget['title']); ?>" <?= $canEdit ? '' : 'readonly'; ?>></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Academic year</label><input class="form-control" name="academic_year" id="setupAcademicYear" value="<?= esc($academicYear); ?>" placeholder="2025-26" <?= $canEdit ? '' : 'readonly'; ?>></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Expected enrollment</label><input type="number" class="form-control" name="enrollment" id="setupEnrollment" value="<?= esc($setup['enrollment'] ?? ''); ?>" <?= $canEdit ? '' : 'readonly'; ?>></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Opening cash (RWF)</label><input type="number" step="0.01" class="form-control" name="opening_cash" id="setupOpeningCash" value="<?= esc($setup['opening_cash'] ?? ''); ?>" <?= $canEdit ? '' : 'readonly'; ?>></div>
		<div class="col-12 form-group"><label class="font-weight-bold">Planning notes</label><textarea class="form-control" rows="3" name="planning_notes" id="setupNotes" <?= $canEdit ? '' : 'readonly'; ?>><?= esc($setup['planning_notes'] ?? ''); ?></textarea></div>
	</div>
	<?php if ($canEdit) { ?>
	<button type="button" class="btn btn-outline-primary btn-sm" id="btnSaveSetup"><i class="fa fa-check"></i> Save setup</button>
	<?php } ?>
</div></div>
<div class="bp-nav-footer"><span></span><button type="button" class="btn btn-primary btnNext" data-next="plan">Continue to three-term budget <i class="fa fa-arrow-right"></i></button></div>
</div>

<!-- THREE-TERM BUDGET GRID -->
<div class="bp-panel" id="panel-plan">
<div class="bp-panel-help"><i class="fa fa-table"></i> <strong>Step 2 — Full-year budget by term.</strong> Enter RWF for Term I, Term II and Term III. <em>Annual = T1 + T2 + T3</em><?php if ($branchFillMode) { ?> — amounts reflect your branch only; master school defines the line list.<?php } ?></div>

<?php foreach ($sections as $secKey => $sec) {
	if (empty($sec['lines'])) continue;
	$isIncome = !empty($sec['is_income']);
?>
<div class="bp-section bp-term-section" data-section="<?= esc($secKey); ?>">
	<div class="bp-section-head <?= $isIncome ? 'income' : 'expense'; ?>">
		<span><i class="fa fa-<?= $isIncome ? 'arrow-down' : 'arrow-up'; ?>"></i> <?= esc($secKey); ?></span>
		<span class="bp-section-total" data-section-total="<?= esc($secKey); ?>">0 RWF</span>
	</div>
	<div class="table-responsive">
		<table class="table table-sm table-bordered bp-term-table mb-0">
			<thead class="thead-light">
				<tr>
					<th style="min-width:220px;text-align:left">Budget line</th>
					<th class="text-right" style="width:120px">Term I</th>
					<th class="text-right" style="width:120px">Term II</th>
					<th class="text-right" style="width:120px">Term III</th>
					<th class="text-right" style="width:130px">Annual total</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($sec['lines'] as $ln) {
				$lid = (int)$ln['id'];
				$isTotal = (int)($ln['is_total_row'] ?? 0) === 1;
				$t1 = (float)($ln['term_1_amount'] ?? 0);
				$t2 = (float)($ln['term_2_amount'] ?? 0);
				$t3 = (float)($ln['term_3_amount'] ?? 0);
				$annual = (float)($ln['annual_amount'] ?? ($t1 + $t2 + $t3));
				if ($isTotal) { ?>
				<tr class="table-secondary font-weight-bold total-row" data-section="<?= esc($secKey); ?>">
					<td><?= esc($ln['category']); ?></td>
					<td class="text-right term-t1-display">—</td>
					<td class="text-right term-t2-display">—</td>
					<td class="text-right term-t3-display">—</td>
					<td class="text-right line-annual-display"><?= number_format($annual, 0); ?></td>
				</tr>
				<?php continue; }
				$ro = $canEdit ? '' : 'readonly disabled';
			?>
				<tr class="budget-line" data-line-id="<?= $lid; ?>" data-income="<?= $isIncome ? '1' : '0'; ?>" data-section="<?= esc($secKey); ?>">
					<td>
						<strong><?= esc($ln['category']); ?></strong>
						<input type="hidden" class="calc-mode-input" name="lines[<?= $lid; ?>][calculation_mode]" value="term_sum">
						<input type="text" class="form-control form-control-sm mt-1" name="lines[<?= $lid; ?>][assumptions]" value="<?= esc($ln['assumptions'] ?? ''); ?>" placeholder="Notes (optional)" <?= $ro; ?>>
					</td>
					<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right inp-term inp-term-1" name="lines[<?= $lid; ?>][term_1_amount]" value="<?= $t1 > 0 ? esc($t1) : ''; ?>" placeholder="0" <?= $ro; ?>></td>
					<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right inp-term inp-term-2" name="lines[<?= $lid; ?>][term_2_amount]" value="<?= $t2 > 0 ? esc($t2) : ''; ?>" placeholder="0" <?= $ro; ?>></td>
					<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-right inp-term inp-term-3" name="lines[<?= $lid; ?>][term_3_amount]" value="<?= $t3 > 0 ? esc($t3) : ''; ?>" placeholder="0" <?= $ro; ?>></td>
					<td class="text-right align-middle"><strong class="line-annual-display"><?= number_format($annual, 0); ?></strong></td>
				</tr>
			<?php } ?>
			</tbody>
			<tfoot class="thead-light">
				<tr class="font-weight-bold">
					<td>Section subtotal</td>
					<td class="text-right section-t1" data-section="<?= esc($secKey); ?>">0</td>
					<td class="text-right section-t2" data-section="<?= esc($secKey); ?>">0</td>
					<td class="text-right section-t3" data-section="<?= esc($secKey); ?>">0</td>
					<td class="text-right" data-section-total-foot="<?= esc($secKey); ?>">0</td>
				</tr>
			</tfoot>
		</table>
	</div>
</div>
<?php } ?>

<div class="card border-primary mt-3"><div class="card-body py-2">
	<div class="row text-center small">
		<div class="col-3"><span class="text-muted">Term I total (expenses)</span><br><strong id="footTerm1">0</strong></div>
		<div class="col-3"><span class="text-muted">Term II total (expenses)</span><br><strong id="footTerm2">0</strong></div>
		<div class="col-3"><span class="text-muted">Term III total (expenses)</span><br><strong id="footTerm3">0</strong></div>
		<div class="col-3"><span class="text-muted">Full year expenses</span><br><strong class="text-danger" id="footAnnualExp">0</strong></div>
	</div>
</div></div>

<div class="bp-nav-footer">
	<button type="button" class="btn btn-outline-secondary btnPrev" data-prev="setup"><i class="fa fa-arrow-left"></i> Back</button>
	<button type="button" class="btn btn-primary btnNext" data-next="summary">Continue to summary <i class="fa fa-arrow-right"></i></button>
</div>
</div>

<!-- SUMMARY -->
<div class="bp-panel" id="panel-summary">
<div class="bp-panel-help"><i class="fa fa-check-double"></i> <strong>Step 3 — Review full year.</strong> Confirm annual income covers expenses across all three terms, then submit for approval.</div>
<div class="row">
<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
<h6 class="font-weight-bold mb-3">Budget by section (annual)</h6>
<div class="bp-summary-chart" id="summaryChart"></div>
</div></div></div>
<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body">
<h6 class="font-weight-bold mb-3">Term overview</h6>
<table class="table table-sm">
	<thead><tr><th></th><th class="text-right">Income</th><th class="text-right">Expenses</th><th class="text-right">Net</th></tr></thead>
	<tbody>
		<tr><td>Term I</td><td class="text-right text-success" id="sumT1Inc">0</td><td class="text-right text-danger" id="sumT1Exp">0</td><td class="text-right" id="sumT1Net">0</td></tr>
		<tr><td>Term II</td><td class="text-right text-success" id="sumT2Inc">0</td><td class="text-right text-danger" id="sumT2Exp">0</td><td class="text-right" id="sumT2Net">0</td></tr>
		<tr><td>Term III</td><td class="text-right text-success" id="sumT3Inc">0</td><td class="text-right text-danger" id="sumT3Exp">0</td><td class="text-right" id="sumT3Net">0</td></tr>
		<tr class="font-weight-bold table-light"><td>Full year</td><td class="text-right text-success" id="sumIncome">0</td><td class="text-right text-danger" id="sumExpense">0</td><td class="text-right" id="summarySurplus">0</td></tr>
	</tbody>
</table>
<?php if ($canSubmit) { ?>
<button type="button" class="btn btn-success btn-block mt-2" id="btnSubmitSummary"><i class="fa fa-paper-plane"></i> Submit budget for approval</button>
<?php } elseif ($isFinanceAdjust) { ?>
<div class="alert alert-warning small mt-2 mb-0"><i class="fa fa-info-circle"></i> Changes are saved as a Director of Finance adjustment. Status remains <strong><?= esc($budget['status']); ?></strong>. New school budgets still require Submit → Procurement → Budget Manager → Deputy Director of Finance.</div>
<?php } ?>
</div></div></div>
</div>
<div class="bp-nav-footer">
	<button type="button" class="btn btn-outline-secondary btnPrev" data-prev="plan"><i class="fa fa-arrow-left"></i> Back to budget grid</button>
	<span></span>
</div>
</div>

</form>
</div>

<div class="bp-sidebar">
	<?= view('pages/budget/partials/process_guide', ['ctx' => 'prep', 'compact' => true]); ?>
	<div class="mt-3 small text-muted"><p class="mb-0"><i class="fa fa-info-circle"></i> Cash requests spend against <strong>annual</strong> budget lines after approval.</p></div>
</div>
</div>
</div>

<script src="<?= base_url('assets/js/budget-workspace.js'); ?>?v=3"></script>
<script>BudgetWorkspace.init({
	budgetId: <?= (int)$budget['id']; ?>,
	canEdit: <?= $canEdit ? 'true' : 'false'; ?>,
	saveUrl: '<?= base_url('budget/save_budget_lines'); ?>',
	setupUrl: '<?= base_url('budget/save_budget_setup'); ?>',
	submitUrl: '<?= base_url('budget/submit_budget'); ?>',
	fillExcelUrl: '<?= base_url('budget/fill_budget_from_excel'); ?>',
	redirectUrl: '<?= base_url('budget/prepare'); ?>'
});</script>
