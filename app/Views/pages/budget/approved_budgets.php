<div class="card"><div class="card-body"><table class="table table-striped" id="tblApproved"><thead><tr><th>Branch</th><th>Title</th><th>Income</th><th>Expenses</th><th>Approved</th><th></th></tr></thead><tbody>
<?php
$canFinanceEdit = function_exists('budget_permission_allowed') && budget_permission_allowed('budget.edit_submitted');
foreach ($budgets as $b) { ?><tr><td><?= esc($b['branch_name']); ?></td><td><?= esc($b['title']); ?></td>
<td><?= number_format((float)$b['total_income'],2); ?></td><td><?= number_format((float)$b['total_expenses'],2); ?></td><td><?= esc($b['status']); ?></td>
<td class="text-right"><?php if ($canFinanceEdit) { ?><a href="<?= base_url('budget/edit_budget/' . (int) $b['id']); ?>" class="btn btn-sm btn-warning"><i class="fa fa-edit"></i> Edit</a><?php } ?></td>
</tr><?php } ?>
</tbody></table></div></div><script>if($.fn.DataTable)$('#tblApproved').DataTable();</script>
