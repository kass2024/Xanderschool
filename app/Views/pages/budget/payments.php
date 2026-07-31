<div class="card"><div class="card-body"><table class="table table-bordered"><thead><tr><th>Request #</th><th>Payee</th><th>Authorized</th><th>Paid</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($requests as $r) {
	$auth = (float)($r['authorized_amount'] ?? $r['requested_amount']);
	$paid = (float)$r['paid_amount'];
	$remaining = $auth - $paid;
?>
<tr><td><?= esc($r['request_no']); ?></td><td><?= esc($r['payee_name']); ?></td>
<td><?= number_format($auth,2); ?></td><td><?= number_format($paid,2); ?></td><td><?= esc($r['status']); ?></td>
<td><button class="btn btn-sm btn-success btn-pay" data-id="<?= (int)$r['id']; ?>" data-remaining="<?= $remaining; ?>">Record payment</button></td></tr>
<?php } ?></tbody></table></div></div>
<script>
$('.btn-pay').on('click',function(){
	var id=$(this).data('id'), rem=parseFloat($(this).data('remaining'));
	var amt=prompt('Payment amount (remaining '+rem.toFixed(2)+'):', rem.toFixed(2));
	if(!amt) return;
	var ref=prompt('Payment reference (unique):'); if(!ref) return;
	var method=prompt('Method (bank/momo/cheque/cash):','bank')||'bank';
	$.post('<?= base_url('budget/record_payment'); ?>',{request_id:id,amount:amt,payment_reference:ref,payment_method:method,payment_date:'<?= date('Y-m-d'); ?>'},function(r){
		if(r.error){toastada.error(r.error);return;} toastada.success('Payment recorded'); location.reload();
	},'json');
});
</script>
