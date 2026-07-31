<div class="card"><div class="card-body"><table class="table table-bordered"><thead><tr><th>Request #</th><th>Payee</th><th>Amount</th><th></th></tr></thead><tbody>
<?php foreach ($requests as $r) { ?><tr><td><?= esc($r['request_no']); ?></td><td><?= esc($r['payee_name']); ?></td>
<td><?= number_format((float)$r['requested_amount'],2); ?></td>
<td><button class="btn btn-sm btn-success btn-rcpt" data-id="<?= (int)$r['id']; ?>">Confirm receipt &amp; close</button></td></tr><?php } ?>
</tbody></table></div></div>
<script>
$('.btn-rcpt').on('click',function(){
	var ref=prompt('Filing reference (optional):')||'';
	var notes=prompt('Notes (optional):')||'';
	$.post('<?= base_url('budget/confirm_receipt'); ?>',{request_id:$(this).data('id'),filing_reference:ref,notes:notes},function(r){
		if(r.error){toastada.error(r.error);return;} toastada.success(r.success); location.reload();
	},'json');
});
</script>
