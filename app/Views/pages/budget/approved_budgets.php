<div class="card"><div class="card-body"><table class="table table-striped" id="tblApproved"><thead><tr><th>Branch</th><th>Title</th><th>Income</th><th>Expenses</th><th>Approved</th><th></th></tr></thead><tbody>
<?php
$canFinanceEdit = function_exists('budget_permission_allowed') && budget_permission_allowed('budget.edit_submitted');
foreach ($budgets as $b) { ?><tr><td><?= esc($b['branch_name']); ?></td><td><?= esc($b['title']); ?></td>
<td><?= number_format((float)$b['total_income'],2); ?></td><td><?= number_format((float)$b['total_expenses'],2); ?></td><td><?= esc($b['status']); ?></td>
<td class="text-right"><?= view('pages/budget/partials/excel_download', ['budget' => $b]); ?><?php if ($canFinanceEdit) { ?><a href="<?= base_url('budget/edit_budget/' . (int) $b['id']); ?>" class="btn btn-sm btn-warning"><i class="fa fa-edit"></i> Edit</a><?php } ?><?php if (\Config\MenuClearance::isDirectorPost((int) ($_SESSION['soma_post'] ?? 0))) { ?><button type="button" class="btn btn-sm btn-outline-danger btn-del-budget" data-id="<?= (int) $b['id']; ?>" data-title="<?= esc($b['title']); ?>"><i class="fa fa-trash"></i> Delete</button><?php } ?></td>
</tr><?php } ?>
</tbody></table></div></div><script>if($.fn.DataTable)$('#tblApproved').DataTable();
$(document).on('click', '.btn-del-budget', function () {
	var id = $(this).data('id');
	var title = $(this).data('title') || 'this budget';
	if (!confirm('Delete "' + title + '"?\n\nLines and approval history will be removed. Budgets with cash requests cannot be deleted.')) return;
	var $btn = $(this).prop('disabled', true);
	$.post('<?= base_url('budget/delete_budget'); ?>', { budget_id: id }, function (r) {
		if (r.error) { toastada.error(r.error); $btn.prop('disabled', false); return; }
		toastada.success(r.success || 'Deleted');
		location.reload();
	}, 'json').fail(function () { $btn.prop('disabled', false); toastada.error('Delete failed'); });
});
</script>
