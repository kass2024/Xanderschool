<div class="card"><div class="card-body"><form id="frmCR">
<input type="hidden" name="id" value="<?= (int)($request['id'] ?? 0); ?>">
<div class="form-row">
<div class="col-md-6 form-group"><label>Approved budget</label><select name="budget_id" id="budget_id" class="form-control"><?php foreach ($budgets as $b) { ?><option value="<?= (int)$b['id']; ?>" <?= isset($request['budget_id']) && (int)$request['budget_id']===(int)$b['id']?'selected':''; ?>><?= esc($b['title']); ?></option><?php } ?></select></div>
<div class="col-md-3 form-group"><label>Request date</label><input type="date" class="form-control" name="request_date" value="<?= esc($request['request_date'] ?? date('Y-m-d')); ?>" required></div>
<div class="col-md-3 form-group"><label>Required payment date</label><input type="date" class="form-control" name="required_payment_date" value="<?= esc($request['required_payment_date'] ?? ''); ?>"></div>
</div>
<div class="form-row">
<div class="col-md-6 form-group"><label>Payee</label><input class="form-control" name="payee_name" value="<?= esc($request['payee_name'] ?? ''); ?>" required></div>
<div class="col-md-3 form-group"><label>Payee type</label><input class="form-control" name="payee_type" value="<?= esc($request['payee_type'] ?? 'supplier'); ?>"></div>
<div class="col-md-3 form-group"><label>Payment method</label><select name="payment_method" class="form-control"><option value="bank">Bank transfer</option><option value="momo">Mobile money</option><option value="cheque">Cheque</option><option value="cash">Cash</option></select></div>
</div>
<div class="form-group"><label>Purpose</label><textarea class="form-control" name="purpose" rows="2" required><?= esc($request['purpose'] ?? ''); ?></textarea></div>
<div class="form-row border-top pt-3">
<div class="col-md-6 form-group"><label>Budget line</label><select name="budget_line_id" id="budget_line_id" class="form-control"><option value="">— Select after choosing budget —</option></select></div>
<div class="col-md-3 form-group"><label>Line amount (RWF)</label><input type="number" step="0.01" class="form-control" name="line_amount" value="<?= esc($lines[0]['amount'] ?? ''); ?>" required></div>
<div class="col-md-3 form-group"><label>Total request</label><input type="number" step="0.01" class="form-control" name="requested_amount" value="<?= esc($request['requested_amount'] ?? ''); ?>" required></div>
</div>
<div class="form-group"><label>Line description</label><input class="form-control" name="line_description" value="<?= esc($lines[0]['description'] ?? ''); ?>" required></div>
<div class="form-group"><label>Internal notes</label><textarea class="form-control" name="internal_notes" rows="2"><?= esc($request['internal_notes'] ?? ''); ?></textarea></div>
<button type="submit" class="btn btn-primary">Save draft</button>
<button type="button" class="btn btn-success" id="btnSubmitCR">Save &amp; submit</button>
</form></div></div>
<script>
function loadLines(){var bid=$('#budget_id').val();if(!bid)return;$.getJSON('<?= base_url('budget/get_budget_lines_json/'); ?>'+bid,function(r){var h='<option value="">— Select line —</option>';$.each(r.lines||[],function(i,l){h+='<option value="'+l.id+'">'+l.category+'</option>';});$('#budget_line_id').html(h);});}
$('#budget_id').on('change',loadLines);loadLines();
$('#frmCR').on('submit',function(e){e.preventDefault();$.post('<?= base_url('budget/save_cash_request'); ?>',$(this).serialize(),function(r){if(r.error){toastada.error(r.error);return;}toastada.success(r.success);location.href='<?= base_url('budget/cash_requests'); ?>';},'json');});
$('#btnSubmitCR').on('click',function(){var d=$('#frmCR').serialize()+'&submit_now=1';$.post('<?= base_url('budget/save_cash_request'); ?>',d,function(r){if(r.error){toastada.error(r.error);return;}toastada.success('Submitted');location.href='<?= base_url('budget/cash_requests'); ?>';},'json');});
</script>
