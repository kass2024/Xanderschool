<div class="card"><div class="card-body"><table class="table table-bordered"><thead><tr><th>Title</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($budgets as $b) { ?>
<tr><td><?= esc($b['title']); ?></td><td><?= esc($b['status']); ?></td><td>
<?php if ($b['status'] === 'SUBMITTED') { ?><button class="btn btn-sm btn-info btn-act" data-id="<?= (int)$b['id']; ?>" data-action="procurement_review">Procurement OK</button><?php } ?>
<?php if ($b['status'] === 'PROCUREMENT_REVIEW') { ?><button class="btn btn-sm btn-info btn-act" data-id="<?= (int)$b['id']; ?>" data-action="budget_review">Budget review</button><?php } ?>
<?php if ($b['status'] === 'BUDGET_MANAGER_REVIEW') { ?><button class="btn btn-sm btn-info btn-act" data-id="<?= (int)$b['id']; ?>" data-action="final_review">To Deputy Director</button><?php } ?>
<?php if ($b['status'] === 'DEPUTY_DIRECTOR_REVIEW') { ?><button class="btn btn-sm btn-success btn-act" data-id="<?= (int)$b['id']; ?>" data-action="approve">Approve</button><?php } ?>
<button class="btn btn-sm btn-warning btn-act" data-id="<?= (int)$b['id']; ?>" data-action="return">Return</button>
</td></tr><?php } ?></tbody></table></div></div>
<script>$('.btn-act').on('click',function(){var c=prompt('Comment (optional):')||'';$.post('<?= base_url('budget/budget_action'); ?>',{budget_id:$(this).data('id'),action:$(this).data('action'),comment:c},function(r){if(r.error){toastada.error(r.error);return;}toastada.success('Updated');location.reload();},'json');});</script>
