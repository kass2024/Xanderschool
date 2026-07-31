<div class="row mb-3"><div class="col-md-8">
<button class="btn btn-success" data-toggle="modal" data-target="#mdlBudget"><i class="fa fa-plus"></i> New budget</button>
</div></div>
<div class="card"><div class="card-body"><table class="table table-striped" id="tblBudgets">
<thead><tr><th>Title</th><th>Status</th><th>Income</th><th>Expenses</th><th>Surplus</th><th></th></tr></thead><tbody>
<?php foreach ($budgets as $b) { ?>
<tr><td><?= esc($b['title']); ?></td><td><span class="badge badge-info"><?= esc($b['status']); ?></span></td>
<td><?= number_format((float)$b['total_income'],2); ?></td><td><?= number_format((float)$b['total_expenses'],2); ?></td>
<td><?= number_format((float)$b['surplus_deficit'],2); ?></td>
<td><?php if (in_array($b['status'],['DRAFT','RETURNED'])) { ?><a href="<?= base_url('budget/edit_budget/'.$b['id']); ?>" class="btn btn-sm btn-primary">Edit</a><?php } ?></td></tr>
<?php } ?>
</tbody></table></div></div>
<div class="modal fade" id="mdlBudget"><div class="modal-dialog"><form class="modal-content" id="frmBudget">
<div class="modal-header"><h5>Create budget</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<div class="form-group"><label>Title</label><input class="form-control" name="title" required></div>
<div class="form-group"><label>Period</label><select name="budget_period_id" class="form-control" required><?php foreach ($periods as $p) { ?><option value="<?= (int)$p['id']; ?>"><?= esc($p['title']); ?></option><?php } ?></select></div>
<div class="form-group"><label>Template</label><select name="template_id" class="form-control"><option value="">— Default structure —</option><?php foreach ($templates as $t) { ?><option value="<?= (int)$t['id']; ?>"><?= esc($t['name']); ?></option><?php } ?></select></div>
</div><div class="modal-footer"><button type="submit" class="btn btn-primary">Create</button></div>
</form></div></div>
<script>
if($.fn.DataTable) $('#tblBudgets').DataTable();
$('#frmBudget').on('submit',function(e){e.preventDefault();$.post('<?= base_url('budget/create_budget'); ?>',$(this).serialize(),function(r){if(r.error){toastada.error(r.error);return;}location.href='<?= base_url('budget/edit_budget/'); ?>'+r.budget_id;},'json');});
</script>
