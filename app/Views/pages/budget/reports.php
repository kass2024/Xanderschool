<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=3" rel="stylesheet">

<?php
$tab = $tab ?? 'summary';
$months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$f = $financials ?? [];
?>

<div class="bp-hero mb-3">
	<h2><i class="fa fa-chart-bar"></i> Reports</h2>
	<p class="bp-meta mb-0"><?= esc($branch_label ?? ''); ?> — budget summary, cash flow, and actual spending.</p>
</div>

<?= view('pages/budget/partials/hub_nav', ['hub' => 'reports', 'tab' => $tab]); ?>

<?php if ($tab === 'summary') { ?>
<div class="card mb-3"><div class="card-header">Budget summary — plan vs paid</div><div class="card-body p-0">
<?php if (empty($summary_lines)) { ?>
<p class="p-3 text-muted mb-0">No approved budget lines yet. Complete budget preparation and get approval first.</p>
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

<?php } elseif ($tab === 'cashflow') { ?>
<div class="card"><div class="card-header">Monthly cash flow (from budget spread)</div><div class="card-body p-0">
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
<div class="card"><div class="card-header">Actual expenditure / revenue (payments recorded)</div><div class="card-body p-0">
<table class="table table-sm mb-0" id="tblActuals"><thead><tr><th>Date</th><th>Request #</th><th>Payee</th><th>Description</th><th>Amount</th></tr></thead><tbody>
<?php if (empty($actuals)) { ?><tr><td colspan="5" class="text-muted text-center py-3">No payments recorded yet.</td></tr><?php } ?>
<?php foreach ($actuals as $a) { ?>
<tr>
	<td><?= esc($a['payment_date'] ?? ''); ?></td>
	<td><?= esc($a['request_no'] ?? ''); ?></td>
	<td><?= esc($a['payee_name'] ?? ''); ?></td>
	<td><?= esc($a['purpose'] ?? ''); ?></td>
	<td><?= number_format((float)$a['amount'], 0); ?></td>
</tr>
<?php } ?>
</tbody></table></div></div>
<script>if($.fn.DataTable)$('#tblActuals').DataTable({order:[[0,'desc']]});</script>

<?php } else { /* audit */ ?>
<?php if (budget_menu_any(['budget_settings']) && budget_permission_allowed('budget.settings.manage')) { ?>
<div class="card mb-3"><div class="card-header">Budget settings</div><div class="card-body">
<form id="frmBudgetSettings">
<div class="form-row">
<div class="col-md-3 form-group"><label>Currency</label><input class="form-control" name="default_currency" value="<?= esc($settings['default_currency'] ?? 'RWF'); ?>"></div>
<div class="col-md-3 form-group"><label>Utilization alert %</label><input type="number" class="form-control" name="budget_utilization_alert_pct" value="<?= esc($settings['budget_utilization_alert_pct'] ?? 80); ?>"></div>
<div class="col-md-3 form-group"><label>Headteacher approval</label><select class="form-control" name="headteacher_approval_mode"><option value="evidence" <?= ($settings['headteacher_approval_mode'] ?? '') === 'evidence' ? 'selected' : ''; ?>>With evidence</option><option value="always" <?= ($settings['headteacher_approval_mode'] ?? '') === 'always' ? 'selected' : ''; ?>>Always</option></select></div>
<div class="col-md-3 form-group pt-4"><label><input type="checkbox" name="ai_enabled" value="1" <?= !empty($settings['ai_enabled']) ? 'checked' : ''; ?>> AI suggestions</label></div>
</div>
<button type="submit" class="btn btn-primary btn-sm">Save settings</button>
</form></div></div>
<script>$('#frmBudgetSettings').on('submit',function(e){e.preventDefault();$.post('<?= base_url('budget/save_settings'); ?>',$(this).serialize(),function(r){toastada.success(r.success||'Saved');},'json');});</script>
<?php } ?>

<div class="card"><div class="card-header">Audit trail</div><div class="card-body p-0">
<table class="table table-sm mb-0" id="tblAudit"><thead><tr><th>When</th><th>Entity</th><th>Action</th><th>By</th></tr></thead><tbody>
<?php if (empty($logs)) { ?><tr><td colspan="4" class="text-muted text-center py-3">No audit entries.</td></tr><?php } ?>
<?php foreach ($logs as $log) { ?>
<tr><td><?= esc($log['created_at'] ?? ''); ?></td><td><?= esc($log['entity_type'] ?? ''); ?> #<?= (int)($log['entity_id'] ?? 0); ?></td><td><?= esc($log['action'] ?? ''); ?></td><td><?= (int)($log['actor_id'] ?? 0); ?></td></tr>
<?php } ?>
</tbody></table></div></div>
<script>if($.fn.DataTable)$('#tblAudit').DataTable({order:[[0,'desc']]});</script>
<?php } ?>
