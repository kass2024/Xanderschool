<div class="card"><div class="card-body"><table class="table table-bordered"><thead><tr><th>Request #</th><th>Payee</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($requests as $r) { ?><tr><td><?= esc($r['request_no']); ?></td><td><?= esc($r['payee_name']); ?></td>
<td><?= number_format((float)$r['requested_amount'],2); ?></td><td><?= esc($r['status']); ?></td>
<td><a href="<?= base_url('budget/cash_request_view/'.$r['id']); ?>" class="btn btn-sm btn-primary">Review</a></td></tr><?php } ?>
</tbody></table></div></div>
