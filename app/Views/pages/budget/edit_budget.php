<div class="card mb-3"><div class="card-body">
<strong><?= esc($budget['title']); ?></strong> — <span class="badge badge-secondary"><?= esc($budget['status']); ?></span>
| Income: <?= number_format((float)$budget['total_income'],2); ?> |
Expenses: <?= number_format((float)$budget['total_expenses'],2); ?> |
Surplus: <?= number_format((float)$budget['surplus_deficit'],2); ?>
</div></div>
<form id="frmLines"><input type="hidden" name="budget_id" value="<?= (int)$budget['id']; ?>">
<div class="table-responsive"><table class="table table-sm table-bordered">
<thead><tr><th>Section</th><th>Category</th><th>Amount (RWF)</th><th>Assumptions</th></tr></thead><tbody>
<?php foreach ($lines as $ln) {
	if ((int)$ln['is_editable'] !== 1) { ?>
<tr class="table-active"><td><?= esc($ln['section_label']); ?></td><td><strong><?= esc($ln['category']); ?></strong></td><td><?= number_format((float)$ln['annual_amount'],2); ?></td><td></td></tr>
<?php continue; } ?>
<tr><td><?= esc($ln['section_label']); ?></td><td><?= esc($ln['category']); ?></td>
<td><input type="number" step="0.01" class="form-control form-control-sm" name="lines[<?= (int)$ln['id']; ?>][user_amount]" value="<?= esc($ln['user_amount'] ?: $ln['annual_amount']); ?>"></td>
<td><input type="text" class="form-control form-control-sm" name="lines[<?= (int)$ln['id']; ?>][assumptions]" value="<?= esc($ln['assumptions'] ?? ''); ?>"></td></tr>
<?php } ?>
</tbody></table></div>
<button type="submit" class="btn btn-primary">Save draft</button>
<button type="button" class="btn btn-success" id="btnSubmit">Submit for approval</button>
</form>
<script>
$('#frmLines').on('submit',function(e){e.preventDefault();$.post('<?= base_url('budget/save_budget_lines'); ?>',$(this).serialize(),function(r){if(r.error){toastada.error(r.error);return;}toastada.success(r.success);},'json');});
$('#btnSubmit').on('click',function(){$.post('<?= base_url('budget/save_budget_lines'); ?>',$('#frmLines').serialize(),function(){$.post('<?= base_url('budget/submit_budget'); ?>',{budget_id:<?= (int)$budget['id']; ?>},function(r){if(r.error){toastada.error(r.error);return;}toastada.success('Submitted');location.href='<?= base_url('budget/prepare'); ?>';},'json');});});
</script>
