<div class="mb-3"><a href="<?= base_url('budget/cash_request_form'); ?>" class="btn btn-success"><i class="fa fa-plus"></i> New cash request</a></div>
<div class="card"><div class="card-body"><table class="table table-bordered" id="tblCR"><thead><tr><th>Request #</th><th>Branch</th><th>Payee</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($requests as $r) { ?><tr><td><?= esc($r['request_no']); ?></td><td><?= esc($r['branch_name'] ?? ''); ?></td><td><?= esc($r['payee_name']); ?></td>
<td><?= number_format((float)$r['requested_amount'],2); ?></td><td><span class="badge badge-info"><?= esc($r['status']); ?></span></td>
<td><a href="<?= base_url('budget/cash_request_view/'.$r['id']); ?>" class="btn btn-sm btn-primary">View</a></td></tr><?php } ?>
</tbody></table></div></div><script>if($.fn.DataTable)$('#tblCR').DataTable({order:[[0,'desc']]});</script>
