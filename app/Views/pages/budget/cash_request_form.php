<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=4" rel="stylesheet">

<div class="budget-cr-form">

<div class="cr-hero">
	<h4 class="mb-1"><i class="fa fa-file-invoice-dollar"></i> <?= esc($title ?? 'New Cash Request'); ?></h4>
	<p class="mb-0 small opacity-90">Accountant: link every payment to an <strong>approved budget line</strong>. Attach invoice or quotation before submitting.</p>
</div>

<div class="cr-flow-steps mb-3">
	<span class="cr-flow-step active">1. Create &amp; attach docs</span>
	<span class="cr-flow-step">2. Headteacher / Procurement</span>
	<span class="cr-flow-step">3. Budget Manager</span>
	<span class="cr-flow-step">4. Director of Finance → Pay</span>
</div>

<?php if (empty($budgets)) { ?>
<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> No <strong>approved budget</strong> yet. Prepare the annual budget, submit and get it approved first.</div>
<?php } ?>

<form id="frmCR" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)($request['id'] ?? 0); ?>">

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-link"></i> Budget link <span class="text-danger">*</span></div>
	<div class="form-row">
		<div class="col-md-6 form-group mb-md-0">
			<label class="font-weight-bold">Approved budget</label>
			<select name="budget_id" id="budget_id" class="form-control" required <?= empty($budgets) ? 'disabled' : ''; ?>>
				<?php if (empty($budgets)) { ?><option value="">— No approved budget —</option><?php } ?>
				<?php foreach ($budgets as $b) { ?>
				<option value="<?= (int)$b['id']; ?>" data-period="<?= (int)($b['budget_period_id'] ?? 0); ?>" <?= isset($request['budget_id']) && (int)$request['budget_id']===(int)$b['id']?'selected':''; ?>><?= esc($b['title']); ?> (<?= number_format((float)$b['total_expenses'],0); ?> RWF)</option>
				<?php } ?>
			</select>
		</div>
		<input type="hidden" name="budget_period_id" id="budget_period_id" value="<?= (int)($request['budget_period_id'] ?? 0); ?>">
		<div class="col-md-6 form-group mb-0">
			<label class="font-weight-bold">Expense budget line <span class="text-danger">*</span></label>
			<select name="budget_line_id" id="budget_line_id" class="form-control" required <?= empty($budgets) ? 'disabled' : ''; ?>>
				<option value="">— Select budget first —</option>
			</select>
			<small class="text-muted">Only expense categories — amount is checked against this line.</small>
		</div>
	</div>
	<div class="cr-avail-box mt-2" id="availBox" style="display:none">
		<strong>Line availability:</strong> <span id="availText"></span>
	</div>
</div>

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-user"></i> Payee &amp; purpose</div>
	<div class="form-row">
		<div class="col-md-3 form-group"><label class="font-weight-bold">Request date</label><input type="date" class="form-control" name="request_date" value="<?= esc($request['request_date'] ?? date('Y-m-d')); ?>" required></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Payment needed by</label><input type="date" class="form-control" name="required_payment_date" value="<?= esc($request['required_payment_date'] ?? ''); ?>"></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Urgency</label><select name="urgency" class="form-control"><option value="normal">Normal</option><option value="urgent" <?= ($request['urgency'] ?? '')==='urgent'?'selected':''; ?>>Urgent</option></select></div>
		<div class="col-md-3 form-group"><label class="font-weight-bold">Payment method</label><select name="payment_method" class="form-control"><option value="bank">Bank transfer</option><option value="momo">Mobile money</option><option value="cheque">Cheque</option><option value="cash">Cash</option></select></div>
	</div>
	<div class="form-row">
		<div class="col-md-6 form-group"><label class="font-weight-bold">Payee name <span class="text-danger">*</span></label><input class="form-control" name="payee_name" value="<?= esc($request['payee_name'] ?? ''); ?>" placeholder="Supplier or staff name" required></div>
		<div class="col-md-6 form-group"><label class="font-weight-bold">Payee type</label><select name="payee_type" class="form-control"><option value="supplier">Supplier</option><option value="staff">Staff</option><option value="contractor">Contractor</option><option value="other">Other</option></select></div>
	</div>
	<div class="form-group mb-0"><label class="font-weight-bold">Purpose / justification <span class="text-danger">*</span></label><textarea class="form-control" name="purpose" rows="2" placeholder="What is this payment for?" required><?= esc($request['purpose'] ?? ''); ?></textarea></div>
</div>

<div class="cr-section">
	<div class="cr-section-title"><i class="fa fa-coins"></i> Amount</div>
	<div class="form-row">
		<div class="col-md-8 form-group"><label class="font-weight-bold">Description</label><input class="form-control" name="line_description" id="line_description" value="<?= esc($lines[0]['description'] ?? ''); ?>" placeholder="Auto-filled from budget line"></div>
		<div class="col-md-4 form-group"><label class="font-weight-bold">Amount (RWF) <span class="text-danger">*</span></label><input type="number" step="0.01" min="1" class="form-control form-control-lg" name="line_amount" id="line_amount" value="<?= esc($lines[0]['amount'] ?? ''); ?>" required></div>
	</div>
	<input type="hidden" name="requested_amount" id="requested_amount" value="<?= esc($request['requested_amount'] ?? ''); ?>">
	<div class="form-group mb-0"><label class="font-weight-bold">Internal notes</label><textarea class="form-control" name="internal_notes" rows="2"><?= esc($request['internal_notes'] ?? ''); ?></textarea></div>
</div>

<div class="cr-section border-warning">
	<div class="cr-section-title"><i class="fa fa-paperclip"></i> Supporting documents <span class="text-danger">*</span> <small class="font-weight-normal text-muted">(required to submit)</small></div>
	<p class="small text-muted mb-2">Attach invoice, quotation, proforma, delivery note, or memo — then append more from your phone if needed.</p>

	<div class="cr-attach-panel">
		<div class="cr-doc-zone" id="docZone">
			<i class="fa fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
			<div>Drag files here or click <strong>Attach file</strong></div>
			<input type="file" name="documents[]" id="docInput" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx" style="display:none">
		</div>

		<div class="cr-attach-actions">
			<button type="button" class="btn btn-outline-primary cr-attach-btn" id="btnBrowseDocs">
				<i class="fa fa-paperclip"></i> Attach file
			</button>
			<span class="cr-attach-or">or append</span>
			<button type="button" class="btn btn-success cr-attach-btn" id="btnScanPhone">
				<i class="fa fa-mobile-alt"></i> Smart Scan (phone)
			</button>
		</div>

		<div class="form-group mb-0 mt-2">
			<label class="small font-weight-bold mb-1">Document type for next attach</label>
			<select name="doc_type" class="form-control form-control-sm" style="max-width:280px">
				<option value="invoice">Invoice</option>
				<option value="quotation">Quotation / Proforma</option>
				<option value="contract">Contract / PO</option>
				<option value="memo">Internal memo</option>
				<option value="delivery">Delivery note</option>
				<option value="other">Other</option>
			</select>
		</div>
	</div>

	<div class="alert alert-info py-2 small d-none mt-2 mb-0" id="scanWaitBox"><i class="fa fa-spinner fa-spin"></i> Waiting for SmartSMS… Camera opens with the same student-photo camera. Keep the app open (or allow the deep link).</div>
	<ul class="cr-doc-list" id="docPreview"></ul>
	<?php if (!empty($documents)) { ?>
	<p class="small font-weight-bold mt-2 mb-1">Already attached:</p>
	<ul class="cr-doc-list">
		<?php foreach ($documents as $doc) { ?>
		<li><span><i class="fa fa-file"></i> <?= esc($doc['original_name']); ?> <small class="text-muted">(<?= esc($doc['doc_type']); ?>)</small></span>
		<a href="<?= base_url('budget/cash_request_document/'.$doc['id']); ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fa fa-download"></i></a></li>
		<?php } ?>
	</ul>
	<?php } ?>
</div>

<div class="d-flex flex-wrap">
	<a href="<?= base_url('budget/requests'); ?>" class="btn btn-light mr-2 mb-2"><i class="fa fa-arrow-left"></i> Cancel</a>
	<button type="submit" class="btn btn-outline-primary mr-2 mb-2" <?= empty($budgets) ? 'disabled' : ''; ?>><i class="fa fa-save"></i> Save draft</button>
	<button type="button" class="btn btn-success mb-2" id="btnSubmitCR" <?= empty($budgets) ? 'disabled' : ''; ?>><i class="fa fa-paper-plane"></i> Submit for approval</button>
</div>
</form>
</div>

<script>
var lineData = {};
var existingDocCount = <?= (int)count($documents ?? []); ?>;
var preselectLine = <?= (int)($lines[0]['budget_line_id'] ?? 0); ?>;

function syncPeriod() {
	$('#budget_period_id').val($('#budget_id option:selected').data('period') || 0);
}

function showAvailability(lineId, amount) {
	var av = lineData[lineId];
	var $box = $('#availBox');
	if (!av) { $box.hide(); return; }
	var avail = parseFloat(av.available) || 0;
	var txt = 'Budgeted: ' + Number(av.revised).toLocaleString() + ' · Paid: ' + Number(av.paid).toLocaleString()
		+ ' · Committed: ' + Number(av.committed).toLocaleString()
		+ ' · <strong>Available: ' + Number(avail).toLocaleString() + ' RWF</strong>';
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
			var avail = l.availability ? ' — avail. ' + Number(l.availability.available).toLocaleString() + ' RWF' : '';
			h += '<option value="' + l.id + '" data-cat="' + $('<div>').text(l.category).html() + '">' + l.category + avail + '</option>';
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
	var cat = $('#budget_line_id option:selected').data('cat');
	if (cat && !$('#line_description').val()) $('#line_description').val(cat);
	showAvailability(lid, amt);
}

$('#budget_id').on('change', loadLines);
$('#budget_line_id, #line_amount').on('change input', checkAvail);
loadLines();

$('#docZone').on('click', function () { $('#docInput').click(); });
$('#btnBrowseDocs').on('click', function (e) { e.preventDefault(); e.stopPropagation(); $('#docInput').click(); });
$('#docZone').on('dragover', function (e) { e.preventDefault(); $(this).addClass('dragover'); });
$('#docZone').on('dragleave drop', function (e) { e.preventDefault(); $(this).removeClass('dragover'); });
$('#docZone').on('drop', function (e) {
	var incoming = e.originalEvent.dataTransfer.files;
	if (!incoming || !incoming.length) return;
	var dt = new DataTransfer();
	var input = $('#docInput')[0];
	if (input.files) {
		for (var i = 0; i < input.files.length; i++) dt.items.add(input.files[i]);
	}
	for (var j = 0; j < incoming.length; j++) dt.items.add(incoming[j]);
	input.files = dt.files;
	renderDocPreview(input.files);
});
$('#docInput').on('change', function () { renderDocPreview(this.files); });

function renderDocPreview(files) {
	var $ul = $('#docPreview').empty();
	for (var i = 0; i < files.length; i++) {
		$ul.append('<li><span><i class="fa fa-file"></i> ' + files[i].name + '</span><small class="text-muted">' + Math.round(files[i].size/1024) + ' KB</small></li>');
	}
}

function validateSubmit() {
	if (!$('#budget_line_id').val()) { toastada.error('Select a budget line.'); return false; }
	if (!$('#line_amount').val() || parseFloat($('#line_amount').val()) <= 0) { toastada.error('Enter amount.'); return false; }
	var newDocs = ($('#docInput')[0].files && $('#docInput')[0].files.length) || 0;
	if (newDocs + existingDocCount < 1) {
		toastada.error('Attach at least one supporting document before submitting.');
		return false;
	}
	return true;
}

function postForm(submitNow) {
	if (submitNow && !validateSubmit()) return;
	var fd = new FormData(document.getElementById('frmCR'));
	if (submitNow) fd.append('submit_now', '1');
	$.ajax({
		url: '<?= base_url('budget/save_cash_request'); ?>',
		type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
		success: function (r) {
			if (r.error) { toastada.error(r.error); return; }
			toastada.success(r.success || 'Saved');
			location.href = '<?= base_url('budget/cash_request_view/'); ?>' + r.id;
		}
	});
}

var scanPollTimer = null;
var scanReceivedToken = null;

function addMobileScanFile(name, blob) {
	var file = new File([blob], name, { type: blob.type || 'image/jpeg' });
	var dt = new DataTransfer();
	var input = $('#docInput')[0];
	if (input.files) {
		for (var i = 0; i < input.files.length; i++) dt.items.add(input.files[i]);
	}
	dt.items.add(file);
	input.files = dt.files;
	renderDocPreview(input.files);
}

function stopScanPoll() {
	if (scanPollTimer) { clearInterval(scanPollTimer); scanPollTimer = null; }
	$('#scanWaitBox').addClass('d-none');
}

function openSmartSmsAmScan(r) {
	var token = r.token || '';
	var intentLink = r.intent_link || ('intent://amscan?token=' + encodeURIComponent(token)
		+ '#Intent;scheme=smartsms;package=com.xandertech.smartsms;end');
	var deepLink = r.deep_link || ('smartsms://amscan?token=' + encodeURIComponent(token));
	try {
		window.location.href = intentLink;
	} catch (e1) {
		try {
			var ifr = document.createElement('iframe');
			ifr.style.display = 'none';
			ifr.src = deepLink;
			document.body.appendChild(ifr);
			setTimeout(function () { try { document.body.removeChild(ifr); } catch (e2) {} }, 2500);
		} catch (e3) {}
	}
}

function startScanPoll(token) {
	stopScanPoll();
	scanReceivedToken = null;
	$('#scanWaitBox').removeClass('d-none');
	scanPollTimer = setInterval(function () {
		$.getJSON('<?= base_url('budget/scan_session_poll'); ?>', { token: token }, function (r) {
			if (r.status === 'ready' && r.image_base64) {
				if (scanReceivedToken === token) return;
				scanReceivedToken = token;
				stopScanPoll();
				var raw = atob(r.image_base64);
				var arr = new Uint8Array(raw.length);
				for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
				var blob = new Blob([arr], { type: r.mime || 'image/jpeg' });
				addMobileScanFile(r.filename || 'phone-scan.jpg', blob);
				toastada.success('Document received from phone.');
			} else if (r.status === 'expired') {
				stopScanPoll();
				toastada.error('Scan session expired. Try again.');
			}
		});
	}, 1500);
}

$('#btnScanPhone').on('click', function () {
	$.post('<?= base_url('budget/scan_session_start'); ?>', {}, function (r) {
		if (r.error) { toastada.error(r.error); return; }
		toastada.info('Opening SmartSMS Smart Scan… Capture starts automatically.');
		startScanPoll(r.token);
		openSmartSmsAmScan(r);
	}, 'json');
});
$('#frmCR').on('submit', function (e) { e.preventDefault(); postForm(false); });
$('#btnSubmitCR').on('click', function () { postForm(true); });
</script>
