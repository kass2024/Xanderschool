<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=13" rel="stylesheet">

<?php
$tab = $tab ?? 'summary';
$months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$f = $financials ?? [];
$canMonitorAll = !empty($can_monitor_all);
$monitorAll = !empty($monitor_all_schools);
$selectedBranchId = (int) ($selected_branch_id ?? 0);
$reportBranches = $report_branches ?? [];
$schoolRows = $school_report_rows ?? [];

$reportUrl = static function ($t, $branchKey = null) use ($selectedBranchId, $canMonitorAll, $monitorAll) {
	$q = ['tab' => $t];
	if ($canMonitorAll) {
		if ($branchKey === 'all' || ($branchKey === null && $monitorAll && $selectedBranchId <= 0)) {
			$q['branch_id'] = 'all';
		} elseif ($branchKey !== null) {
			$q['branch_id'] = $branchKey;
		} elseif ($selectedBranchId > 0) {
			$q['branch_id'] = $selectedBranchId;
		} else {
			$q['branch_id'] = 'all';
		}
	}
	return base_url('budget/reports?' . http_build_query($q));
};
?>

<div class="bp-hero mb-3">
	<div class="d-flex flex-wrap justify-content-between align-items-start">
		<div>
			<h2><i class="fa fa-chart-bar"></i> Reports</h2>
			<p class="bp-meta mb-0"><?= esc($branch_label ?? ''); ?></p>
		</div>
		<?php if ($canMonitorAll && !empty($reportBranches)) { ?>
		<form method="get" action="<?= base_url('budget/reports'); ?>" class="form-inline mt-1" id="frmReportSchool">
			<input type="hidden" name="tab" value="<?= esc($tab); ?>">
			<label class="mr-2 font-weight-bold small mb-0">School</label>
			<select name="branch_id" class="form-control form-control-sm" id="reportBranchSelect" style="min-width:220px">
				<option value="all" <?= ($monitorAll || $selectedBranchId <= 0) ? 'selected' : ''; ?>>All child schools</option>
				<?php foreach ($reportBranches as $br) { ?>
				<option value="<?= (int)$br['id']; ?>" <?= $selectedBranchId === (int)$br['id'] ? 'selected' : ''; ?>>
					<?= esc($br['display_name'] ?? $br['name'] ?? ''); ?>
				</option>
				<?php } ?>
			</select>
		</form>
		<?php } ?>
	</div>
</div>

<?= view('pages/budget/partials/hub_nav', [
	'hub' => 'reports',
	'tab' => $tab,
	'report_branch_id' => $canMonitorAll ? ($monitorAll && $selectedBranchId <= 0 ? 'all' : $selectedBranchId) : null,
]); ?>

<?php if ($tab === 'summary') { ?>

<?php if ($canMonitorAll && ($monitorAll || $selectedBranchId <= 0)) { ?>
<div class="card mb-3 border-0 shadow-sm">
	<div class="card-header bg-white"><strong><i class="fa fa-school text-primary"></i> All schools — budget monitor</strong></div>
	<div class="card-body p-0 table-responsive">
		<table class="table table-hover mb-0" id="tblSchoolReports">
			<thead class="thead-light">
				<tr>
					<th>School</th>
					<th>Period</th>
					<th class="text-right">Income plan</th>
					<th class="text-right">Expense plan</th>
					<th class="text-right">Paid</th>
					<th class="text-right">Remaining</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php if (empty($schoolRows)) { ?>
				<tr><td colspan="7" class="text-muted text-center py-4">No child school branches found.</td></tr>
			<?php } ?>
			<?php
			$sumInc = $sumExp = $sumPaid = $sumVar = 0.0;
			foreach ($schoolRows as $sr) {
				$sumInc += (float) $sr['total_income'];
				$sumExp += (float) $sr['total_budget'];
				$sumPaid += (float) $sr['total_actual'];
				$sumVar += (float) $sr['variance'];
			?>
			<tr>
				<td><strong><?= esc($sr['display_name']); ?></strong>
					<?php if (empty($sr['has_budget'])) { ?><br><small class="text-warning">No approved budget</small><?php } ?>
				</td>
				<td><?= esc($sr['period_title'] ?: '—'); ?></td>
				<td class="text-right text-success"><?= number_format((float)$sr['total_income'], 0); ?></td>
				<td class="text-right text-danger"><?= number_format((float)$sr['total_budget'], 0); ?></td>
				<td class="text-right"><?= number_format((float)$sr['total_actual'], 0); ?></td>
				<td class="text-right <?= (float)$sr['variance'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?= number_format((float)$sr['variance'], 0); ?></td>
				<td class="text-right">
					<a class="btn btn-sm btn-outline-primary" href="<?= esc($reportUrl('summary', (int)$sr['branch_id'])); ?>">Open</a>
				</td>
			</tr>
			<?php } ?>
			</tbody>
			<?php if (!empty($schoolRows)) { ?>
			<tfoot class="thead-light">
				<tr>
					<th colspan="2">Group total</th>
					<th class="text-right"><?= number_format($sumInc, 0); ?></th>
					<th class="text-right"><?= number_format($sumExp, 0); ?></th>
					<th class="text-right"><?= number_format($sumPaid, 0); ?></th>
					<th class="text-right"><?= number_format($sumVar, 0); ?></th>
					<th></th>
				</tr>
			</tfoot>
			<?php } ?>
		</table>
	</div>
</div>
<?php } else { ?>

<div class="card mb-3"><div class="card-header">Budget summary — plan vs paid<?= $canMonitorAll ? ' · ' . esc($branch_label ?? '') : ''; ?></div><div class="card-body p-0">
<?php if (empty($summary_lines)) { ?>
<p class="p-3 text-muted mb-0">No approved budget lines yet for this school.</p>
<?php } else { ?>
<table class="table table-sm table-striped mb-0"><thead><tr><th>Category</th><th>Type</th><th>Annual budget</th><th>Status</th></tr></thead><tbody>
<?php foreach ($summary_lines as $ln) {
	$isIncome = stripos($ln['section_label'] ?? '', 'INCOME') !== false;
?>
<tr>
	<td><?= esc($ln['category']); ?></td>
	<td><?= $isIncome ? 'Revenue' : 'Expense'; ?></td>
	<td><?= number_format((float)$ln['annual_amount'], 0); ?></td>
	<td><span class="badge badge-success">Within budget</span></td>
</tr>
<?php } ?>
</tbody>
<tfoot><tr class="font-weight-bold"><td colspan="2">Totals (approved plan)</td>
<td><?= number_format((float)($f['total_income'] ?? 0) + (float)($f['total_budget'] ?? 0), 0); ?></td><td></td></tr>
</tfoot></table>
<?php } ?>
</div></div>
<div class="row">
	<div class="col-md-4"><div class="card"><div class="card-body text-center"><h4><?= number_format((float)($f['total_budget'] ?? 0), 0); ?></h4><small>Expense budget</small></div></div></div>
	<div class="col-md-4"><div class="card"><div class="card-body text-center"><h4><?= number_format((float)($f['total_actual'] ?? 0), 0); ?></h4><small>Paid to date</small></div></div></div>
	<div class="col-md-4"><div class="card"><div class="card-body text-center"><h4 class="<?= (float)($f['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>"><?= number_format((float)($f['variance'] ?? 0), 0); ?></h4><small>Remaining</small></div></div></div>
</div>
<?php if ($canMonitorAll) { ?>
<p class="mt-3 mb-0"><a href="<?= esc($reportUrl('summary', 'all')); ?>" class="btn btn-sm btn-outline-secondary"><i class="fa fa-th-list"></i> Back to all schools</a></p>
<?php } ?>

<?php } ?>

<?php } elseif ($tab === 'cashflow') { ?>
<div class="card"><div class="card-header">Monthly cash flow<?= $canMonitorAll ? ' · ' . esc($branch_label ?? '') : ''; ?></div><div class="card-body p-0">
<table class="table table-bordered table-sm mb-0 text-center"><thead><tr><th>Month</th><th>Cash in</th><th>Cash out</th><th>Net</th></tr></thead><tbody>
<?php foreach ($months as $i => $m) {
	$in = (float)($cashflow[$m]['in'] ?? 0);
	$out = (float)($cashflow[$m]['out'] ?? 0);
	$net = $in - $out;
?>
<tr><td><?= $monthLabels[$i]; ?></td><td class="text-success"><?= number_format($in, 0); ?></td><td class="text-danger"><?= number_format($out, 0); ?></td><td class="<?= $net >= 0 ? 'text-success' : 'text-danger'; ?>"><?= number_format($net, 0); ?></td></tr>
<?php } ?>
</tbody></table>
</div></div>

<?php } elseif ($tab === 'actuals') { ?>
<div class="card"><div class="card-header">Actual expenditure / revenue<?= $canMonitorAll ? ' · ' . esc($branch_label ?? '') : ''; ?></div><div class="card-body p-0">
<table class="table table-sm mb-0" id="tblActuals"><thead><tr>
	<?php if ($canMonitorAll && ($monitorAll || $selectedBranchId <= 0)) { ?><th>School</th><?php } ?>
	<th>Date</th><th>Request #</th><th>Payee</th><th>Description</th><th>Amount</th>
</tr></thead><tbody>
<?php if (empty($actuals)) { ?><tr><td colspan="6" class="text-muted text-center py-3">No payments recorded yet.</td></tr><?php } ?>
<?php foreach ($actuals as $a) { ?>
<tr>
	<?php if ($canMonitorAll && ($monitorAll || $selectedBranchId <= 0)) { ?><td><?= esc($a['branch_name'] ?? ''); ?></td><?php } ?>
	<td><?= esc($a['payment_date'] ?? ''); ?></td>
	<td><?= esc($a['request_no'] ?? ''); ?></td>
	<td><?= esc($a['payee_name'] ?? ''); ?></td>
	<td><?= esc($a['purpose'] ?? ''); ?></td>
	<td><?= number_format((float)$a['amount'], 0); ?></td>
</tr>
<?php } ?>
</tbody></table></div></div>
<script>if($.fn.DataTable)$('#tblActuals').DataTable({order:[[<?= ($canMonitorAll && ($monitorAll || $selectedBranchId <= 0)) ? 1 : 0; ?>,'desc']]});</script>

<?php } else { /* audit */ ?>
<?php if (budget_menu_any(['budget_settings']) && budget_permission_allowed('budget.settings.manage')) { ?>
<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">
	<span>Cash flow settings</span>
	<a href="<?= base_url('budget/settings'); ?>" class="btn btn-sm btn-primary"><i class="fa fa-sliders-h"></i> Open amount approval settings</a>
</div>
<div class="card-body small text-muted mb-0">Configure which approval chain applies by request amount (master school).</div>
</div>
<?php } ?>

<div class="card"><div class="card-header">Audit trail<?= $canMonitorAll ? ' · ' . esc($branch_label ?? '') : ''; ?></div><div class="card-body p-0">
<table class="table table-sm mb-0" id="tblAudit"><thead><tr><th>When</th><th>Entity</th><th>Action</th><th>By</th></tr></thead><tbody>
<?php if (empty($logs)) { ?><tr><td colspan="4" class="text-muted text-center py-3">No audit entries.</td></tr><?php } ?>
<?php foreach ($logs as $log) { ?>
<tr><td><?= esc($log['created_at'] ?? ''); ?></td><td><?= esc($log['entity_type'] ?? ''); ?> #<?= (int)($log['entity_id'] ?? 0); ?></td><td><?= esc($log['action'] ?? ''); ?></td><td><?= (int)($log['actor_id'] ?? 0); ?></td></tr>
<?php } ?>
</tbody></table></div></div>
<script>if($.fn.DataTable)$('#tblAudit').DataTable({order:[[0,'desc']]});</script>
<?php } ?>

<?php if ($canMonitorAll) { ?>
<script>
$('#reportBranchSelect').on('change', function () {
	$('#frmReportSchool').submit();
});
</script>
<?php } ?>
