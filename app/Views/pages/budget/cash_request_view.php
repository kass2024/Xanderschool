<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=3" rel="stylesheet">

<?php
$r = $request;
$chain = $approval_chain ?? ($r['approval_chain'] ?? 'full');
$statusFlow = ['DRAFT','SUBMITTED','HEADTEACHER_APPROVED','PROCUREMENT_APPROVED','BUDGET_APPROVED','FINANCE_AUTHORIZED','PAID','RECEIPT_CONFIRMED','CLOSED'];
$currentIdx = array_search($r['status'], $statusFlow, true);
if ($currentIdx === false) {
	$currentIdx = 0;
}
$steps = $approval_flow ?? \App\Services\Budget\CashRequestApprovalPolicy::flowLabels($chain);
$wfActions = $wf_actions ?? \App\Services\Budget\CashRequestWorkflowService::uiActionsForRequest($r);
?>

<div class="budget-cr-view budget-cr-form">

<div class="cr-hero">
	<div class="d-flex flex-wrap justify-content-between align-items-start">
		<div>
			<h4 class="mb-1"><?= esc($r['request_no']); ?></h4>
			<span class="badge badge-light"><?= esc($r['status']); ?></span>
			<span class="ml-2"><?= number_format((float)$r['requested_amount'], 0); ?> RWF</span>
			<span class="badge badge-info ml-2"><?= esc($approval_steps_label ?? $chain); ?></span>
		</div>
		<a href="<?= base_url('budget/requests'); ?>" class="btn btn-sm btn-light"><i class="fa fa-arrow-left"></i> All requests</a>
	</div>
</div>

<div class="cr-flow-steps mb-3">
<?php foreach ($steps as $s) {
	$sIdx = array_search($s[0], $statusFlow, true);
	$done = $sIdx !== false && $sIdx <= $currentIdx;
	$active = $r['status'] === $s[0];
?>
<span class="cr-flow-step <?= ($active || $done) ? 'active' : ''; ?>"><?= esc($s[1]); ?></span>
<?php } ?>
</div>

<div class="row">
<div class="col-lg-7">

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-info-circle"></i> Request details</div>
	<p><strong>Payee:</strong> <?= esc($r['payee_name']); ?> (<?= esc($r['payee_type'] ?? 'supplier'); ?>)</p>
	<p><strong>Purpose:</strong> <?= esc($r['purpose']); ?></p>
	<p class="mb-0 small text-muted">Request date: <?= esc($r['request_date']); ?> · Payment method: <?= esc($r['payment_method'] ?? '—'); ?></p>
</div>

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-chart-pie"></i> Budget consumption</div>
	<?php foreach ($lines as $ln) {
		$av = !empty($ln['budget_line_id']) ? ($availability[$ln['budget_line_id']] ?? null) : null; ?>
	<div class="mb-3 p-2 bg-light rounded">
		<strong><?= esc($ln['description']); ?></strong>: <?= number_format((float)$ln['amount'], 0); ?> RWF
		<?php if ($av) { ?>
		<div class="progress mt-2" style="height:8px"><div class="progress-bar bg-success" style="width:<?= min(100, (float)$av['utilization_pct']); ?>%"></div></div>
		<small class="text-muted">Line budget: <?= number_format($av['revised'], 0); ?> · Remaining: <strong><?= number_format($av['available'], 0); ?></strong> RWF</small>
		<?php } ?>
	</div>
	<?php } ?>
</div>

<?php if (!empty($documents)) { ?>
<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-paperclip"></i> Supporting documents</div>
	<ul class="cr-doc-list">
		<?php foreach ($documents as $doc) { ?>
		<li><span><i class="fa fa-file-pdf"></i> <?= esc($doc['original_name']); ?> <small class="text-muted">(<?= esc($doc['doc_type']); ?>)</small></span>
		<a href="<?= base_url('budget/cash_request_document/'.$doc['id']); ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fa fa-download"></i> Download</a></li>
		<?php } ?>
	</ul>
</div>
<?php } ?>

<?php if (!empty($payments)) { ?>
<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-money-check"></i> Payments</div>
	<ul class="list-group list-group-flush">
	<?php foreach ($payments as $p) { ?>
	<li class="list-group-item px-0"><?= esc($p['payment_date']); ?> — <?= number_format((float)$p['amount'], 0); ?> RWF (<?= esc($p['payment_reference'] ?? '—'); ?>)</li>
	<?php } ?>
	</ul>
</div>
<?php } ?>

</div>

<div class="col-lg-5">

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-gavel"></i> Your actions</div>
	<?php foreach ($wfActions as $key => $label) { ?>
	<button type="button" class="btn btn-sm btn-primary mr-1 mb-2 btn-wf btn-block text-left" data-action="<?= esc($key, 'attr'); ?>"><i class="fa fa-check"></i> <?= esc($label); ?></button>
	<?php } ?>
	<?php if (empty($wfActions)) { ?><p class="text-muted mb-0">No actions available at status <strong><?= esc($r['status']); ?></strong>.</p><?php } ?>
</div>

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-history"></i> Approval timeline</div>
	<div class="cr-timeline">
	<?php foreach ($actions as $act) { ?>
	<div class="cr-timeline-item">
		<strong><?= esc($act['action']); ?></strong><br>
		<?= esc($act['previous_status']); ?> → <?= esc($act['new_status']); ?>
		<?php if (!empty($act['comment'])) { ?><br><em><?= esc($act['comment']); ?></em><?php } ?>
		<br><span class="text-muted"><?= esc($act['created_at']); ?></span>
	</div>
	<?php } ?>
	<?php if (empty($actions)) { ?><p class="text-muted small mb-0">No actions recorded yet.</p><?php } ?>
	</div>
</div>

<?= view('pages/budget/partials/process_guide', ['ctx' => 'execution', 'compact' => true]); ?>
</div>
</div>
</div>

<script>
$('.btn-wf').on('click', function () {
	var action = $(this).data('action');
	var comment = prompt('Comment (required for return/reject):') || '';
	if ((action === 'return' || action === 'reject') && !comment.trim()) {
		toastada.error('Comment is required.');
		return;
	}
	if (action === 'budget_approve' && !confirm('Confirm budget line has sufficient uncommitted funds for this request?')) return;
	if (action === 'final_approve' && !confirm('Authorize payment for this request?')) return;
	var $btn = $(this).prop('disabled', true);
	$.post('<?= base_url('budget/cash_request_action'); ?>', {
		request_id: <?= (int)$r['id']; ?>,
		action: action,
		comment: comment
	}, function (res) {
		if (res.error) { toastada.error(res.error); $btn.prop('disabled', false); return; }
		toastada.success('Status: ' + res.status);
		location.reload();
	}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Request failed'); });
});
</script>
