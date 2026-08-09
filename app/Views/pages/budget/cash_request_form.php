<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=2" rel="stylesheet">

<div class="budget-cr-form">

<div class="cr-hero">

	<h4 class="mb-1"><i class="fa fa-file-invoice-dollar"></i> <?= esc($title ?? 'New Cash Request'); ?></h4>

	<p class="mb-0 small opacity-90">Spend against an approved budget line. Attach invoices, quotations, or memos as supporting documents.</p>

</div>



<div class="cr-flow-steps mb-3">

	<span class="cr-flow-step active">1. Draft request</span>

	<span class="cr-flow-step">2. Headteacher</span>

	<span class="cr-flow-step">3. Procurement</span>

	<span class="cr-flow-step">4. Budget Manager</span>

	<span class="cr-flow-step">5. Deputy Director</span>

	<span class="cr-flow-step">6. Payment</span>

</div>



<form id="frmCR" enctype="multipart/form-data">

<input type="hidden" name="id" value="<?= (int)($request['id'] ?? 0); ?>">



<div class="cr-section">

	<div class="cr-section-title"><i class="fa fa-link"></i> Budget link</div>

	<div class="form-row">

		<div class="col-md-6 form-group">

			<label class="font-weight-bold">Approved budget</label>

			<select name="budget_id" id="budget_id" class="form-control" required>

				<?php if (empty($budgets)) { ?><option value="">— No approved budget — submit &amp; approve a budget first —</option><?php } ?>

				<?php foreach ($budgets as $b) { ?>

				<option value="<?= (int)$b['id']; ?>" data-period="<?= (int)($b['budget_period_id'] ?? 0); ?>" <?= isset($request['budget_id']) && (int)$request['budget_id']===(int)$b['id']?'selected':''; ?>><?= esc($b['title']); ?></option>

				<?php } ?>

			</select>

		</div>

		<input type="hidden" name="budget_period_id" id="budget_period_id" value="<?= (int)($request['budget_period_id'] ?? 0); ?>">

		<div class="col-md-6 form-group">

			<label class="font-weight-bold">Budget line <small class="text-muted">(expense category)</small></label>

			<select name="budget_line_id" id="budget_line_id" class="form-control" required>

				<option value="">— Select budget first —</option>

			</select>

		</div>

	</div>

	<div class="cr-avail-box" id="availBox">

		<strong>Budget availability:</strong>

		<span id="availText">Select a budget line to see available funds.</span>

	</div>

</div>



<div class="cr-section">

	<div class="cr-section-title"><i class="fa fa-user"></i> Payee &amp; purpose</div>

	<div class="form-row">

		<div class="col-md-3 form-group"><label class="font-weight-bold">Request date</label><input type="date" class="form-control" name="request_date" value="<?= esc($request['request_date'] ?? date('Y-m-d')); ?>" required></div>

		<div class="col-md-3 form-group"><label class="font-weight-bold">Required payment date</label><input type="date" class="form-control" name="required_payment_date" value="<?= esc($request['required_payment_date'] ?? ''); ?>"></div>

		<div class="col-md-3 form-group"><label class="font-weight-bold">Urgency</label><select name="urgency" class="form-control"><option value="normal" <?= ($request['urgency'] ?? '')==='normal'?'selected':''; ?>>Normal</option><option value="urgent" <?= ($request['urgency'] ?? '')==='urgent'?'selected':''; ?>>Urgent</option></select></div>

		<div class="col-md-3 form-group"><label class="font-weight-bold">Payment method</label><select name="payment_method" class="form-control"><option value="bank">Bank transfer</option><option value="momo">Mobile money</option><option value="cheque">Cheque</option><option value="cash">Cash</option></select></div>

	</div>

	<div class="form-row">

		<div class="col-md-6 form-group"><label class="font-weight-bold">Payee name</label><input class="form-control" name="payee_name" value="<?= esc($request['payee_name'] ?? ''); ?>" placeholder="Supplier or staff name" required></div>

		<div class="col-md-6 form-group"><label class="font-weight-bold">Payee type</label><select name="payee_type" class="form-control"><option value="supplier">Supplier</option><option value="staff">Staff</option><option value="contractor">Contractor</option><option value="other">Other</option></select></div>

	</div>

	<div class="form-group"><label class="font-weight-bold">Purpose / justification</label><textarea class="form-control" name="purpose" rows="2" placeholder="What is this payment for? Link to school activity or purchase." required><?= esc($request['purpose'] ?? ''); ?></textarea></div>

</div>



<div class="cr-section">

	<div class="cr-section-title"><i class="fa fa-coins"></i> Amount</div>

	<div class="form-row">

		<div class="col-md-8 form-group"><label class="font-weight-bold">Line description</label><input class="form-control" name="line_description" value="<?= esc($lines[0]['description'] ?? ''); ?>" placeholder="e.g. Science lab consumables — Term 2" required></div>

		<div class="col-md-4 form-group"><label class="font-weight-bold">Amount (RWF)</label><input type="number" step="0.01" class="form-control form-control-lg" name="line_amount" id="line_amount" value="<?= esc($lines[0]['amount'] ?? ''); ?>" required></div>

	</div>

	<input type="hidden" name="requested_amount" id="requested_amount" value="<?= esc($request['requested_amount'] ?? ''); ?>">

	<div class="form-group mb-0"><label class="font-weight-bold">Internal notes <small class="text-muted">(not shared with payee)</small></label><textarea class="form-control" name="internal_notes" rows="2"><?= esc($request['internal_notes'] ?? ''); ?></textarea></div>

</div>



<div class="cr-section">

	<div class="cr-section-title"><i class="fa fa-paperclip"></i> Supporting documents</div>

	<p class="small text-muted">Upload invoice, quotation, proforma, delivery note, or approval memo. PDF, Word, Excel, or images — max 10 MB each.</p>

	<div class="cr-doc-zone" id="docZone">

		<i class="fa fa-cloud-upload-alt fa-2x text-muted mb-2"></i>

		<div>Drag files here or <strong>click to browse</strong></div>

		<input type="file" name="documents[]" id="docInput" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx" style="display:none">

	</div>

	<div class="form-row mt-2">

		<div class="col-md-6 form-group mb-0">

			<label class="small font-weight-bold">Document type (for new uploads)</label>

			<select name="doc_type" class="form-control form-control-sm">

				<option value="invoice">Invoice</option>

				<option value="quotation">Quotation / Proforma</option>

				<option value="contract">Contract / PO</option>

				<option value="memo">Internal memo</option>

				<option value="delivery">Delivery note</option>

				<option value="other">Other</option>

			</select>

		</div>

	</div>

	<ul class="cr-doc-list" id="docPreview"></ul>

	<?php if (!empty($documents)) { ?>

	<ul class="cr-doc-list mt-2">

		<?php foreach ($documents as $doc) { ?>

		<li><span><i class="fa fa-file"></i> <?= esc($doc['original_name']); ?> <small class="text-muted">(<?= esc($doc['doc_type']); ?>)</small></span>

		<a href="<?= base_url('budget/cash_request_document/'.$doc['id']); ?>" class="btn btn-xs btn-outline-primary btn-sm" target="_blank"><i class="fa fa-download"></i></a></li>

		<?php } ?>

	</ul>

	<?php } ?>

</div>



<div class="d-flex flex-wrap gap-2">

	<a href="<?= base_url('budget/cash_requests'); ?>" class="btn btn-light mr-2"><i class="fa fa-arrow-left"></i> Cancel</a>

	<button type="submit" class="btn btn-primary mr-2"><i class="fa fa-save"></i> Save draft</button>

	<button type="button" class="btn btn-success" id="btnSubmitCR"><i class="fa fa-paper-plane"></i> Save &amp; submit for approval</button>

</div>

</form>

</div>



<script>

var lineData = {};

var preselectLine = <?= (int)($lines[0]['budget_line_id'] ?? 0); ?>;



function syncPeriod() {

	var pid = $('#budget_id option:selected').data('period') || 0;

	$('#budget_period_id').val(pid);

}



function showAvailability(lineId, amount) {

	var av = lineData[lineId];

	var $box = $('#availBox');

	if (!av) { $box.hide(); return; }

	var avail = parseFloat(av.available) || 0;

	var txt = 'Budgeted: ' + Number(av.revised).toLocaleString() + ' RWF · Paid: ' + Number(av.paid).toLocaleString() + ' · Committed: ' + Number(av.committed).toLocaleString() + ' · <strong>Available: ' + Number(avail).toLocaleString() + ' RWF</strong> (' + av.utilization_pct + '% used)';

	$('#availText').html(txt);

	$box.removeClass('warn danger').show();

	if (amount > avail) $box.addClass('danger');

	else if (amount > avail * 0.8) $box.addClass('warn');

}



function loadLines() {

	var bid = $('#budget_id').val();

	if (!bid) return;

	syncPeriod();

	$.getJSON('<?= base_url('budget/get_budget_lines_json/'); ?>' + bid, function (r) {

		lineData = {};

		var h = '<option value="">— Select expense line —</option>';

		$.each(r.lines || [], function (i, l) {

			lineData[l.id] = l.availability || null;

			var avail = l.availability ? ' — avail. ' + Number(l.availability.available).toLocaleString() : '';

			h += '<option value="' + l.id + '">' + l.category + avail + '</option>';

		});

		$('#budget_line_id').html(h);

		if (preselectLine) { $('#budget_line_id').val(preselectLine); preselectLine = 0; }

		checkAvail();

	});

}



function checkAvail() {

	var lid = $('#budget_line_id').val();

	var amt = parseFloat($('#line_amount').val()) || 0;

	$('#requested_amount').val(amt);

	showAvailability(lid, amt);

}



$('#budget_id').on('change', loadLines);

$('#budget_line_id, #line_amount').on('change input', checkAvail);

loadLines();



var pendingFiles = [];

$('#docZone').on('click', function () { $('#docInput').click(); });

$('#docZone').on('dragover', function (e) { e.preventDefault(); $(this).addClass('dragover'); });

$('#docZone').on('dragleave drop', function (e) { e.preventDefault(); $(this).removeClass('dragover'); });

$('#docZone').on('drop', function (e) {

	var files = e.originalEvent.dataTransfer.files;

	$('#docInput')[0].files = files;

	renderDocPreview(files);

});

$('#docInput').on('change', function () { renderDocPreview(this.files); });



function renderDocPreview(files) {

	var $ul = $('#docPreview').empty();

	for (var i = 0; i < files.length; i++) {

		$ul.append('<li><span><i class="fa fa-file"></i> ' + files[i].name + '</span><small class="text-muted">' + Math.round(files[i].size/1024) + ' KB</small></li>');

	}

}



function postForm(submitNow) {

	var fd = new FormData(document.getElementById('frmCR'));

	if (submitNow) fd.append('submit_now', '1');

	$.ajax({

		url: '<?= base_url('budget/save_cash_request'); ?>',

		type: 'POST',

		data: fd,

		processData: false,

		contentType: false,

		dataType: 'json',

		success: function (r) {

			if (r.error) { toastada.error(r.error); return; }

			toastada.success(submitNow ? 'Submitted for approval' : r.success);

			location.href = '<?= base_url('budget/cash_request_view/'); ?>' + r.id;

		}

	});

}



$('#frmCR').on('submit', function (e) { e.preventDefault(); postForm(false); });

$('#btnSubmitCR').on('click', function () { postForm(true); });

</script>

