<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=3" rel="stylesheet">

<div class="bp-hero mb-3">
	<h2><i class="fa fa-hand-holding-usd"></i> Requests &amp; Approvals</h2>
</div>

<?= view('pages/budget/partials/hub_nav', ['hub' => 'requests', 'tab' => $tab ?? 'all']); ?>

<?php if (($tab ?? 'all') === 'all') { ?>
<div class="mb-3">
	<?php if (function_exists('budget_permission_allowed') && budget_permission_allowed('cash_request.create')) { ?>
	<a href="<?= base_url('budget/cash_request_form'); ?>" class="btn btn-success"><i class="fa fa-plus"></i> New cash request</a>
	<?php } ?>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-hover mb-0" id="tblCR"><thead><tr><th>Request #</th><th>Branch</th><th>Payee</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($requests as $r) { ?>
<tr>
	<td><?= esc($r['request_no']); ?></td>
	<td><?= esc($r['branch_name'] ?? ''); ?></td>
	<td><?= esc($r['payee_name']); ?></td>
	<td><?= number_format((float)$r['requested_amount'], 0); ?></td>
	<td><span class="badge badge-info"><?= esc($r['status']); ?></span></td>
	<td><a href="<?= base_url('budget/cash_request_view/'.$r['id']); ?>" class="btn btn-sm btn-primary">Open</a></td>
</tr>
<?php } ?>
</tbody></table></div></div>

<?php } elseif (($tab ?? '') === 'pending') { ?>
<div class="card"><div class="card-body p-0">
<table class="table table-bordered mb-0"><thead><tr><th>Request #</th><th>Payee</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
<?php if (empty($requests)) { ?><tr><td colspan="5" class="text-muted text-center py-4">Nothing pending for you right now.</td></tr><?php } ?>
<?php foreach ($requests as $r) { ?>
<tr><td><?= esc($r['request_no']); ?></td><td><?= esc($r['payee_name']); ?></td>
<td><?= number_format((float)$r['requested_amount'], 0); ?></td><td><?= esc($r['status']); ?></td>
<td><a href="<?= base_url('budget/cash_request_view/'.$r['id']); ?>" class="btn btn-sm btn-primary">Review</a></td></tr>
<?php } ?>
</tbody></table></div></div>

<?php } elseif (($tab ?? '') === 'payments') { ?>
<div class="card"><div class="card-body p-0">
<table class="table table-bordered mb-0"><thead><tr><th>Request #</th><th>Payee</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($requests as $r) { ?>
<tr><td><?= esc($r['request_no']); ?></td><td><?= esc($r['payee_name']); ?></td>
<td><?= number_format((float)$r['requested_amount'], 0); ?></td><td><?= esc($r['status']); ?></td>
<td><a href="<?= base_url('budget/cash_request_view/'.$r['id']); ?>" class="btn btn-sm btn-success">Pay</a></td></tr>
<?php } ?>
</tbody></table></div></div>

<?php } else { /* receipts */ ?>
<div class="card"><div class="card-body p-0">
<table class="table table-bordered mb-0"><thead><tr><th>Request #</th><th>Payee</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($requests as $r) { ?>
<tr><td><?= esc($r['request_no']); ?></td><td><?= esc($r['payee_name']); ?></td>
<td><?= number_format((float)$r['requested_amount'], 0); ?></td><td><?= esc($r['status']); ?></td>
<td><a href="<?= base_url('budget/cash_request_view/'.$r['id']); ?>" class="btn btn-sm btn-info">File receipt</a></td></tr>
<?php } ?>
</tbody></table></div></div>
<?php } ?>

<script>if($.fn.DataTable&&$('#tblCR').length)$('#tblCR').DataTable({order:[[0,'desc']]});</script>
