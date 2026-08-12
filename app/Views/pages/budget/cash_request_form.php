<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=12" rel="stylesheet">

<div class="budget-cr-form">

<div class="cr-hero">
	<h4 class="mb-1"><i class="fa fa-file-invoice-dollar"></i> <?= esc($title ?? 'New Cash Request'); ?></h4>
</div>

<?php if (empty($budgets)) { ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between">
	<span><i class="fa fa-exclamation-triangle"></i> No approved budget yet</span>
	<a href="<?= base_url('budget/prepare'); ?>" class="btn btn-sm btn-warning">Prepare budget</a>
</div>
<?php } ?>

<form id="frmCR" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)($request['id'] ?? 0); ?>">

<div class="cr-section">
	<div class="cr-section-title d-flex flex-wrap justify-content-between align-items-center">
		<span><i class="fa fa-link"></i> Budget &amp; request items <span class="text-danger">*</span></span>
		<button type="button" class="btn btn-sm btn-outline-success" id="btnAddItem" <?= empty($budgets) ? 'disabled' : ''; ?>>
			<i class="fa fa-plus"></i> Add item
		</button>
	</div>
	<div class="form-row mb-3">
		<div class="col-md-6 form-group mb-0">
			<label class="font-weight-bold">Approved budget</label>
			<select name="budget_id" id="budget_id" class="form-control" required <?= empty($budgets) ? 'disabled' : ''; ?>>
				<?php if (empty($budgets)) { ?><option value="">— No approved budget —</option><?php } ?>
				<?php foreach ($budgets as $b) { ?>
				<option value="<?= (int)$b['id']; ?>" data-period="<?= (int)($b['budget_period_id'] ?? 0); ?>" <?= isset($request['budget_id']) && (int)$request['budget_id']===(int)$b['id']?'selected':''; ?>><?= esc($b['title']); ?> (<?= number_format((float)$b['total_expenses'],0); ?> RWF)</option>
				<?php } ?>
			</select>
		</div>
		<input type="hidden" name="budget_period_id" id="budget_period_id" value="<?= (int)($request['budget_period_id'] ?? 0); ?>">
		<div class="col-md-6 d-flex align-items-end">
			<div class="bp-kpi-row w-100 mb-0" id="itemsKpiStrip">
				<div class="bp-kpi"><label>Items</label><strong id="kpiItemCount">0</strong></div>
				<div class="bp-kpi income"><label>Total</label><strong id="kpiItemTotal">0</strong><small>RWF</small></div>
			</div>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-sm cr-items-table mb-0" id="tblRequestItems">
			<thead class="thead-light">
				<tr>
					<th style="min-width:220px">Budget line</th>
					<th>Description</th>
					<th style="width:140px">Amount (RWF)</th>
					<th style="width:150px">Remaining</th>
					<th style="width:44px"></th>
				</tr>
			</thead>
			<tbody id="requestItemsBody"></tbody>
		</table>
	</div>
	<div id="itemsWarnBox" class="cr-avail-box danger mt-2" style="display:none"></div>
	<div id="chainPreviewBox" class="bp-kpi-row mt-3 mb-0" style="display:none">
		<div class="bp-kpi income w-100">
			<label>Approval chain for this total</label>
			<strong class="small d-block" id="chainPreviewLabel">—</strong>
			<small class="text-muted d-block mt-1" id="chainPreviewSteps"></small>
		</div>
	</div>
	<input type="hidden" name="requested_amount" id="requested_amount" value="<?= esc($request['requested_amount'] ?? ''); ?>">
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
	<div class="form-group"><label class="font-weight-bold">Purpose / justification <span class="text-danger">*</span></label><textarea class="form-control" name="purpose" rows="2" placeholder="What is this payment for?" required><?= esc($request['purpose'] ?? ''); ?></textarea></div>
	<div class="form-group mb-0"><label class="font-weight-bold">Internal notes</label><textarea class="form-control" name="internal_notes" rows="2"><?= esc($request['internal_notes'] ?? ''); ?></textarea></div>
</div>

<div class="cr-section border-warning">
	<div class="cr-section-title"><i class="fa fa-paperclip"></i> Supporting documents <span class="text-danger">*</span> <small class="font-weight-normal text-muted">(required to submit)</small></div>

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

	<div class="alert alert-info py-2 small d-none mt-2 mb-0" id="scanWaitBox">
		<i class="fa fa-spinner fa-spin"></i>
		Waiting for SmartSMS… Session open for <strong id="scanWaitMins">30</strong> min
		(<span id="scanCountdown">30:00</span>). Capture with the student-photo camera, then this form will append the file.
	</div>
	<ul class="cr-doc-list" id="docPreview"></ul>
	<?php if (!empty($documents)) { ?>
	<p class="small font-weight-bold mt-2 mb-1">Already attached:</p>
	<ul class="cr-doc-list">
		<?php foreach ($documents as $doc) {
			$ext = strtolower(pathinfo($doc['original_name'] ?? '', PATHINFO_EXTENSION));
			$canView = in_array($ext, ['jpg','jpeg','png','gif','webp','pdf'], true);
			$viewUrl = base_url('budget/cash_request_document/'.$doc['id'].'?inline=1');
			$dlUrl = base_url('budget/cash_request_document/'.$doc['id']);
		?>
		<li>
			<span><i class="fa fa-file"></i> <?= esc($doc['original_name']); ?> <small class="text-muted">(<?= esc($doc['doc_type']); ?>)</small></span>
			<span class="cr-doc-actions">
				<?php if ($canView) { ?><a href="<?= $viewUrl; ?>" class="btn btn-sm btn-outline-info" target="_blank" rel="noopener"><i class="fa fa-eye"></i> View</a><?php } ?>
				<a href="<?= $dlUrl; ?>" class="btn btn-sm btn-outline-primary" download><i class="fa fa-download"></i></a>
			</span>
		</li>
		<?php } ?>
	</ul>
	<?php } ?>
</div>

<!-- Lightbox for pending (unsaved) scans -->
<div id="crDocViewer" class="cr-doc-viewer d-none" aria-hidden="true">
	<div class="cr-doc-viewer-backdrop" id="crDocViewerClose"></div>
	<div class="cr-doc-viewer-panel">
		<div class="cr-doc-viewer-bar">
			<strong id="crDocViewerTitle">Document</strong>
			<div>
				<a href="#" id="crDocViewerDownload" class="btn btn-sm btn-outline-light" download><i class="fa fa-download"></i></a>
				<button type="button" class="btn btn-sm btn-light" id="crDocViewerCloseBtn"><i class="fa fa-times"></i></button>
			</div>
		</div>
		<div class="cr-doc-viewer-body">
			<img id="crDocViewerImg" alt="Document preview" class="d-none">
			<iframe id="crDocViewerFrame" title="Document" class="d-none"></iframe>
		</div>
	</div>
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
var lineOptionsHtml = '<option value="">— Select budget line —</option>';
var existingDocCount = <?= (int)count($documents ?? []); ?>;
var existingItems = <?= json_encode(array_values(array_map(static function ($ln) {
	return [
		'budget_line_id' => (int) ($ln['budget_line_id'] ?? 0),
		'description' => (string) ($ln['description'] ?? ''),
		'amount' => (float) ($ln['amount'] ?? 0),
	];
}, $lines ?? [])), JSON_UNESCAPED_UNICODE); ?>;
var chainPreviewTimer = null;

function syncPeriod() {
	$('#budget_period_id').val($('#budget_id option:selected').data('period') || 0);
}

function buildLineOptionsHtml(lines) {
	lineData = {};
	var h = '<option value="">— Select budget line —</option>';
	var groups = {};
	var order = [];
	$.each(lines || [], function (i, l) {
		lineData[l.id] = l.availability || null;
		var sec = (l.section || l.section_label || 'OTHER').toString();
		if (!groups[sec]) { groups[sec] = []; order.push(sec); }
		groups[sec].push(l);
	});
	$.each(order, function (i, sec) {
		h += '<optgroup label="' + $('<div>').text(sec).html() + '">';
		$.each(groups[sec], function (j, l) {
			var availNum = l.availability ? Number(l.availability.available) : 0;
			var avail = ' - remaining ' + availNum.toLocaleString() + ' RWF';
			h += '<option value="' + l.id + '" data-cat="' + $('<div>').text(l.category).html() + '">'
				+ $('<div>').text(l.category).html() + avail + '</option>';
		});
		h += '</optgroup>';
	});
	return h;
}

function addItemRow(preset) {
	preset = preset || {};
	var $tr = $('<tr class="cr-item-row">'
		+ '<td><select name="item_budget_line_id[]" class="form-control form-control-sm item-line" required>' + lineOptionsHtml + '</select></td>'
		+ '<td><input type="text" name="item_description[]" class="form-control form-control-sm item-desc" placeholder="Item description" value=""></td>'
		+ '<td><input type="number" step="0.01" min="0.01" name="item_amount[]" class="form-control form-control-sm item-amount" placeholder="0" required></td>'
		+ '<td class="item-remain small text-muted align-middle">—</td>'
		+ '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-rm-item" title="Remove item">&times;</button></td>'
		+ '</tr>');
	$('#requestItemsBody').append($tr);
	if (preset.budget_line_id) {
		$tr.find('.item-line').val(String(preset.budget_line_id));
	}
	if (preset.description) {
		$tr.find('.item-desc').val(preset.description);
	}
	if (preset.amount) {
		$tr.find('.item-amount').val(preset.amount);
	}
	refreshItemRow($tr);
	recalcItems();
}

function refreshItemRow($tr) {
	var lid = $tr.find('.item-line').val();
	var amt = parseFloat($tr.find('.item-amount').val()) || 0;
	var av = lineData[lid];
	var $remain = $tr.find('.item-remain');
	$tr.removeClass('table-danger table-warning');
	if (!lid || !av) {
		$remain.text('—').removeClass('text-danger text-warning text-success');
		return;
	}
	var remain = parseFloat(av.available) || 0;
	// Deduct other rows on same budget line
	var usedSame = 0;
	$('#requestItemsBody tr').each(function () {
		if (this === $tr[0]) return;
		if (String($(this).find('.item-line').val()) === String(lid)) {
			usedSame += parseFloat($(this).find('.item-amount').val()) || 0;
		}
	});
	var left = remain - usedSame;
	$remain.text(Number(left).toLocaleString() + ' RWF');
	if (amt > left) {
		$remain.addClass('text-danger').removeClass('text-warning text-success');
		$tr.addClass('table-danger');
	} else if (amt > left * 0.8) {
		$remain.addClass('text-warning').removeClass('text-danger text-success');
		$tr.addClass('table-warning');
	} else {
		$remain.addClass('text-success').removeClass('text-danger text-warning');
	}
	var cat = $tr.find('.item-line option:selected').data('cat');
	var $desc = $tr.find('.item-desc');
	if (cat && (!$desc.val() || $desc.data('auto') === 1)) {
		$desc.val(cat).data('auto', 1);
	}
}

function recalcItems() {
	var total = 0;
	var count = 0;
	var over = [];
	var byLine = {};
	$('#requestItemsBody tr').each(function () {
		var $tr = $(this);
		refreshItemRow($tr);
		var lid = $tr.find('.item-line').val();
		var amt = parseFloat($tr.find('.item-amount').val()) || 0;
		if (amt > 0) {
			total += amt;
			count += 1;
		}
		if (lid) {
			byLine[lid] = (byLine[lid] || 0) + amt;
		}
	});
	Object.keys(byLine).forEach(function (lid) {
		var av = lineData[lid];
		if (!av) return;
		var remain = parseFloat(av.available) || 0;
		if (byLine[lid] > remain) {
			var name = $('#requestItemsBody .item-line').filter(function () {
				return String($(this).val()) === String(lid);
			}).first().find('option:selected').text() || ('Line #' + lid);
			name = String(name).split(' - remaining')[0];
			over.push(name.trim() + ' needs ' + Number(byLine[lid]).toLocaleString()
				+ ' but only ' + Number(remain).toLocaleString() + ' RWF remaining');
		}
	});
	$('#kpiItemCount').text(count);
	$('#kpiItemTotal').text(Number(total).toLocaleString());
	$('#requested_amount').val(total > 0 ? total : '');
	if (over.length) {
		$('#itemsWarnBox').html('<strong>Over budget:</strong> ' + over.join('; ')).show();
	} else {
		$('#itemsWarnBox').hide().empty();
	}
	refreshChainPreview(total);
}

function loadLines(done) {
	var bid = $('#budget_id').val();
	if (!bid) {
		lineOptionsHtml = '<option value="">— Select budget first —</option>';
		$('#requestItemsBody .item-line').html(lineOptionsHtml);
		if (typeof done === 'function') done();
		return;
	}
	syncPeriod();
	$.getJSON('<?= base_url('budget/get_budget_lines_json/'); ?>' + bid, function (r) {
		lineOptionsHtml = buildLineOptionsHtml(r.lines || []);
		var presets = [];
		$('#requestItemsBody tr').each(function () {
			presets.push({
				budget_line_id: $(this).find('.item-line').val(),
				description: $(this).find('.item-desc').val(),
				amount: $(this).find('.item-amount').val()
			});
		});
		if (!presets.length && existingItems.length) {
			presets = existingItems.slice();
			existingItems = [];
		}
		$('#requestItemsBody').empty();
		if (!presets.length) {
			addItemRow();
		} else {
			presets.forEach(function (p) { addItemRow(p); });
		}
		recalcItems();
		if (typeof done === 'function') done();
	});
}

function refreshChainPreview(amt) {
	var $box = $('#chainPreviewBox');
	if (!amt || amt <= 0) { $box.hide(); return; }
	clearTimeout(chainPreviewTimer);
	chainPreviewTimer = setTimeout(function () {
		$.getJSON('<?= base_url('budget/resolve_approval_chain'); ?>', { amount: amt }, function (r) {
			$('#chainPreviewLabel').text(r.label || r.chain || '—');
			$('#chainPreviewSteps').text(r.steps_label || ((r.steps || []).join(' → ')));
			$box.show();
		});
	}, 250);
}

$('#budget_id').on('change', function () { loadLines(); });
$('#btnAddItem').on('click', function () { addItemRow(); });
$(document).on('click', '.btn-rm-item', function () {
	if ($('#requestItemsBody tr').length <= 1) {
		toastada.error('Keep at least one item.');
		return;
	}
	$(this).closest('tr').remove();
	recalcItems();
});
$(document).on('change', '.item-line', function () {
	var $tr = $(this).closest('tr');
	$tr.find('.item-desc').data('auto', 1);
	recalcItems();
});
$(document).on('input', '.item-amount', recalcItems);
$(document).on('input', '.item-desc', function () {
	$(this).data('auto', 0);
});
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

var previewUrls = [];
var scanPollTimer = null;
var scanCountdownTimer = null;
var scanReceivedToken = null;
var scanActiveToken = null;
var scanExpiresAt = 0;

function escHtml(s) {
	return String(s || '').replace(/[&<>"']/g, function (c) {
		return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
	});
}

function clearPreviewUrls() {
	for (var i = 0; i < previewUrls.length; i++) {
		try { URL.revokeObjectURL(previewUrls[i]); } catch (e) {}
	}
	previewUrls = [];
}

function docTypeLabel() {
	var $opt = $('select[name="doc_type"] option:selected');
	var text = ($opt.text() || 'Document').trim();
	text = text.replace(/\s*\/\s*.*$/, '').replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '');
	return text || 'Document';
}

function nextScanFileName(ext) {
	ext = (ext || 'jpg').replace(/^\./, '');
	var base = docTypeLabel();
	var input = $('#docInput')[0];
	var maxN = 0;
	var re = new RegExp('^' + base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '-(\\d+)\\.', 'i');
	if (input && input.files) {
		for (var i = 0; i < input.files.length; i++) {
			var m = input.files[i].name.match(re);
			if (m) maxN = Math.max(maxN, parseInt(m[1], 10) || 0);
		}
	}
	return base + '-' + (maxN + 1) + '.' + ext;
}

function isPreviewable(file) {
	var n = (file.name || '').toLowerCase();
	var t = (file.type || '').toLowerCase();
	return t.indexOf('image/') === 0 || t === 'application/pdf'
		|| /\.(jpe?g|png|gif|webp|pdf)$/i.test(n);
}

function renderDocPreview(files) {
	clearPreviewUrls();
	var $ul = $('#docPreview').empty();
	if (!files || !files.length) return;
	for (var i = 0; i < files.length; i++) {
		(function (idx, file) {
			var url = URL.createObjectURL(file);
			previewUrls.push(url);
			var canView = isPreviewable(file);
			var actions = '<span class="cr-doc-actions">'
				+ (canView ? '<button type="button" class="btn btn-sm btn-outline-info cr-doc-view" data-url="' + url + '" data-name="' + escHtml(file.name) + '" data-type="' + escHtml(file.type || '') + '"><i class="fa fa-eye"></i> View</button>' : '')
				+ '<a class="btn btn-sm btn-outline-primary" download="' + escHtml(file.name) + '" href="' + url + '"><i class="fa fa-download"></i></a>'
				+ '<button type="button" class="btn btn-sm btn-outline-danger cr-doc-remove" data-idx="' + idx + '" title="Remove"><i class="fa fa-times"></i></button>'
				+ '</span>';
			$ul.append(
				'<li><span class="cr-doc-meta"><i class="fa fa-file"></i> <strong>' + escHtml(file.name) + '</strong>'
				+ '<br><small class="text-muted">' + Math.round(file.size / 1024) + ' KB</small></span>'
				+ actions + '</li>'
			);
		})(i, files[i]);
	}
}

$('#docPreview').on('click', '.cr-doc-view', function () {
	openDocViewer($(this).data('url'), $(this).data('name'), $(this).data('type'));
});
$('#docPreview').on('click', '.cr-doc-remove', function () {
	var idx = parseInt($(this).data('idx'), 10);
	var input = $('#docInput')[0];
	var dt = new DataTransfer();
	if (input.files) {
		for (var i = 0; i < input.files.length; i++) {
			if (i !== idx) dt.items.add(input.files[i]);
		}
	}
	input.files = dt.files;
	renderDocPreview(input.files);
});

function openDocViewer(url, name, mime) {
	$('#crDocViewerTitle').text(name || 'Document');
	$('#crDocViewerDownload').attr({ href: url, download: name || 'document' });
	var isPdf = (mime || '').indexOf('pdf') >= 0 || /\.pdf$/i.test(name || '');
	$('#crDocViewerImg').addClass('d-none').attr('src', '');
	$('#crDocViewerFrame').addClass('d-none').attr('src', '');
	if (isPdf) {
		$('#crDocViewerFrame').removeClass('d-none').attr('src', url);
	} else {
		$('#crDocViewerImg').removeClass('d-none').attr('src', url);
	}
	$('#crDocViewer').removeClass('d-none').attr('aria-hidden', 'false');
}
function closeDocViewer() {
	$('#crDocViewer').addClass('d-none').attr('aria-hidden', 'true');
	$('#crDocViewerImg').attr('src', '');
	$('#crDocViewerFrame').attr('src', '');
}
$('#crDocViewerClose, #crDocViewerCloseBtn').on('click', closeDocViewer);

function validateSubmit() {
	var ok = true;
	var total = 0;
	var rows = 0;
	$('#requestItemsBody tr').each(function () {
		var lid = $(this).find('.item-line').val();
		var amt = parseFloat($(this).find('.item-amount').val()) || 0;
		if (!lid || amt <= 0) { ok = false; }
		if (lid && amt > 0) { total += amt; rows++; }
	});
	if (!ok || rows < 1) {
		toastada.error('Add at least one item with a budget line and amount.');
		return false;
	}
	if ($('#itemsWarnBox').is(':visible')) {
		toastada.error('One or more items exceed remaining budget.');
		return false;
	}
	var newDocs = ($('#docInput')[0].files && $('#docInput')[0].files.length) || 0;
	if (newDocs + existingDocCount < 1) {
		toastada.error('Attach at least one supporting document before submitting.');
		return false;
	}
	$('#requested_amount').val(total);
	return true;
}

function postForm(submitNow) {
	if (submitNow && !validateSubmit()) return;
	recalcItems();
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
	if (scanCountdownTimer) { clearInterval(scanCountdownTimer); scanCountdownTimer = null; }
	$('#scanWaitBox').addClass('d-none');
	scanActiveToken = null;
}

function formatCountdown(sec) {
	sec = Math.max(0, sec | 0);
	var m = Math.floor(sec / 60);
	var s = sec % 60;
	return m + ':' + (s < 10 ? '0' : '') + s;
}

function startScanCountdown(expiresIn) {
	if (scanCountdownTimer) clearInterval(scanCountdownTimer);
	scanExpiresAt = Date.now() + (expiresIn * 1000);
	$('#scanWaitMins').text(Math.round(expiresIn / 60));
	function tick() {
		var left = Math.ceil((scanExpiresAt - Date.now()) / 1000);
		$('#scanCountdown').text(formatCountdown(left));
		if (left <= 0 && scanPollTimer) {
			/* let poll handler report expired once */
		}
	}
	tick();
	scanCountdownTimer = setInterval(tick, 1000);
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

function startScanPoll(token, expiresIn) {
	stopScanPoll();
	scanReceivedToken = null;
	scanActiveToken = token;
	$('#scanWaitBox').removeClass('d-none');
	startScanCountdown(expiresIn || 1800);
	scanPollTimer = setInterval(function () {
		var watching = scanActiveToken;
		if (!watching) return;
		$.getJSON('<?= base_url('budget/scan_session_poll'); ?>', { token: watching }, function (r) {
			if (watching !== scanActiveToken && watching !== scanReceivedToken) return;
			if (r.status === 'ready' && r.image_base64) {
				if (scanReceivedToken === watching) return;
				scanReceivedToken = watching;
				stopScanPoll();
				var raw = atob(r.image_base64);
				var arr = new Uint8Array(raw.length);
				for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
				var mime = r.mime || 'image/jpeg';
				var blob = new Blob([arr], { type: mime });
				var ext = (mime.indexOf('png') >= 0) ? 'png' : 'jpg';
				var fname = nextScanFileName(ext);
				addMobileScanFile(fname, blob);
				toastada.success('Document received: ' + fname);
			} else if (r.status === 'expired') {
				// After a successful receive the session is consumed — ignore late "expired" polls
				if (scanReceivedToken === watching) return;
				if (scanActiveToken !== watching) return;
				stopScanPoll();
				toastada.error('Scan session expired. Tap Smart Scan again (open for 30 minutes).');
			}
		});
	}, 1500);
}

$('#btnScanPhone').on('click', function () {
	$.post('<?= base_url('budget/scan_session_start'); ?>', {}, function (r) {
		if (r.error) { toastada.error(r.error); return; }
		toastada.info('Smart Scan open for ' + Math.round((r.expires_in || 1800) / 60) + ' minutes. Capture on your phone…');
		startScanPoll(r.token, r.expires_in || 1800);
		openSmartSmsAmScan(r);
	}, 'json');
});
$('#frmCR').on('submit', function (e) { e.preventDefault(); postForm(false); });
$('#btnSubmitCR').on('click', function () { postForm(true); });
</script>
