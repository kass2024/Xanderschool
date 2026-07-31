<?php $r = $request; ?>
<div class="card mb-3"><div class="card-body">
<h5><?= esc($r['request_no']); ?> <span class="badge badge-info"><?= esc($r['status']); ?></span></h5>
<p><strong>Payee:</strong> <?= esc($r['payee_name']); ?> | <strong>Amount:</strong> <?= number_format((float)$r['requested_amount'], 2); ?> RWF</p>
<p><?= esc($r['purpose']); ?></p>
</div></div>
<div class="row">
<div class="col-md-6">
<div class="card mb-3"><div class="card-header">Lines &amp; budget availability</div><div class="card-body">
<?php foreach ($lines as $ln) {
	$av = !empty($ln['budget_line_id']) ? ($availability[$ln['budget_line_id']] ?? null) : null; ?>
<p><strong><?= esc($ln['description']); ?></strong>: <?= number_format((float)$ln['amount'], 2); ?>
<?php if ($av) { ?><br><small class="text-muted">Available: <?= number_format($av['available'], 2); ?> (<?= $av['utilization_pct']; ?>% used)</small><?php } ?></p>
<?php } ?>
</div></div>
<?php if (!empty($payments)) { ?>
<div class="card"><div class="card-header">Payments</div><ul class="list-group list-group-flush">
<?php foreach ($payments as $p) { ?>
<li class="list-group-item"><?= esc($p['payment_date']); ?> — <?= number_format((float)$p['amount'], 2); ?> (<?= esc($p['payment_reference']); ?>)</li>
<?php } ?></ul></div>
<?php } ?>
</div>
<div class="col-md-6">
<div class="card mb-3"><div class="card-header">Workflow actions</div><div class="card-body">
<?php
$wfActions = [];
if ($r['status'] === 'SUBMITTED') {
	$wfActions = ['headteacher_approve' => 'Headteacher approve', 'procurement_approve' => 'Procurement approve', 'return' => 'Return'];
} elseif ($r['status'] === 'HEADTEACHER_APPROVED') {
	$wfActions = ['procurement_approve' => 'Procurement approve', 'return' => 'Return'];
} elseif ($r['status'] === 'PROCUREMENT_APPROVED') {
	$wfActions = ['budget_approve' => 'Budget approve', 'return' => 'Return'];
} elseif ($r['status'] === 'BUDGET_APPROVED') {
	$wfActions = ['final_approve' => 'Authorize payment', 'reject' => 'Reject', 'return' => 'Return'];
}
foreach ($wfActions as $key => $label) { ?>
<button type="button" class="btn btn-sm btn-primary mr-1 mb-1 btn-wf" data-action="<?= esc($key, 'attr'); ?>"><?= esc($label); ?></button>
<?php } ?>
<?php if (empty($wfActions)) { ?><p class="text-muted mb-0">No actions for this status.</p><?php } ?>
</div></div>
<div class="card"><div class="card-header">Approval timeline</div><ul class="list-group list-group-flush">
<?php foreach ($actions as $act) { ?>
<li class="list-group-item small">
<strong><?= esc($act['action']); ?></strong>: <?= esc($act['previous_status']); ?> → <?= esc($act['new_status']); ?>
<?php if (!empty($act['comment'])) { ?><br><em><?= esc($act['comment']); ?></em><?php } ?>
<br><span class="text-muted"><?= esc($act['created_at']); ?></span>
</li>
<?php } ?>
</ul></div>
</div>
</div>
<script>
$('.btn-wf').on('click', function () {
	var action = $(this).data('action');
	var comment = prompt('Comment (required for return/reject):') || '';
	var extra = {};
	if (action === 'budget_approve' && !confirm('Confirm budget availability?')) return;
	$.post('<?= base_url('budget/cash_request_action'); ?>', {
		request_id: <?= (int)$r['id']; ?>,
		action: action,
		comment: comment
	}, function (res) {
		if (res.error) { toastada.error(res.error); return; }
		toastada.success('Status: ' + res.status);
		location.reload();
	}, 'json');
});
</script>
