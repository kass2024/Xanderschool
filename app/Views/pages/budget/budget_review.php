<div class="card border-0 shadow-sm">
	<div class="card-header bg-white">
		<strong><i class="fa fa-tasks text-primary"></i> School budget approval (thin chain)</strong>
		<p class="small text-muted mb-0">Each school’s prepared budget is approved for that school only: Procurement → Budget Manager → Deputy Director of Finance.</p>
	</div>
	<div class="card-body p-0">
		<?php if (empty($budgets)) { ?>
		<div class="p-4 text-center text-muted">No budgets waiting for your approval step.</div>
		<?php } else { ?>
		<div class="table-responsive">
		<table class="table table-hover mb-0">
			<thead class="thead-light">
				<tr>
					<th>School / budget</th>
					<th>Status</th>
					<th>Income</th>
					<th>Expenses</th>
					<th class="text-right">Your action</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$labels = [
				'procurement_review' => ['Procurement OK', 'info'],
				'budget_review' => ['Budget Manager OK', 'info'],
				'final_review' => ['Send to Deputy Director', 'primary'],
				'approve' => ['Final approve', 'success'],
				'return' => ['Return', 'warning'],
				'reject' => ['Reject', 'danger'],
			];
			foreach ($budgets as $b) {
				$actions = $b['allowed_actions'] ?? [];
			?>
			<tr>
				<td>
					<strong><?= esc($b['title']); ?></strong>
					<?php if (!empty($b['branch_name'])) { ?>
					<br><small class="text-muted"><i class="fa fa-school"></i> <?= esc($b['branch_name']); ?></small>
					<?php } ?>
				</td>
				<td><span class="badge badge-info"><?= esc($b['status']); ?></span></td>
				<td class="text-success"><?= number_format((float)($b['total_income'] ?? 0), 0); ?></td>
				<td class="text-danger"><?= number_format((float)($b['total_expenses'] ?? 0), 0); ?></td>
				<td class="text-right">
					<?php if (function_exists('budget_permission_allowed') && budget_permission_allowed('budget.edit_submitted')) { ?>
					<a href="<?= base_url('budget/edit_budget/' . (int) $b['id']); ?>" class="btn btn-sm btn-warning mb-1"><i class="fa fa-edit"></i> Edit</a>
					<?php } ?>
					<?php if (empty($actions)) { ?>
					<small class="text-muted">Waiting for another role</small>
					<?php } else {
						foreach ($actions as $act) {
							$meta = $labels[$act] ?? [$act, 'secondary'];
					?>
					<button type="button" class="btn btn-sm btn-<?= esc($meta[1]); ?> btn-act mb-1"
						data-id="<?= (int)$b['id']; ?>" data-action="<?= esc($act); ?>"><?= esc($meta[0]); ?></button>
					<?php }
					} ?>
				</td>
			</tr>
			<?php } ?>
			</tbody>
		</table>
		</div>
		<?php } ?>
	</div>
</div>
<script>
$('.btn-act').on('click', function () {
	var action = $(this).data('action');
	var id = $(this).data('id');
	var c = prompt(action === 'approve' ? 'Final approval comment (optional):' : 'Comment (optional):') || '';
	var $btn = $(this).prop('disabled', true);
	$.post('<?= base_url('budget/budget_action'); ?>', { budget_id: id, action: action, comment: c }, function (r) {
		if (r.error) { toastada.error(r.error); $btn.prop('disabled', false); return; }
		toastada.success(r.status ? ('Moved to ' + r.status) : 'Updated');
		location.reload();
	}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Request failed'); });
});
</script>
