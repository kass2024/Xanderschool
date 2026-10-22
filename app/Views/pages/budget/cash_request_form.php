<link href="<?= base_url('assets/css/budget-preparation.css'); ?>?v=22" rel="stylesheet">
<?php
$reqRow = $request ?? [];
$wsName = strtoupper(trim((string) ($school_name ?? 'Wisdom School')));
$wsMotto = trim((string) ($school_moto ?? ''));
if ($wsMotto === '' && strpos($wsName, 'WISDOM') !== false) {
	$wsMotto = 'Fearing God is knowledge';
}
$wsWeb = trim((string) ($school_website ?? ''));
if ($wsWeb === '' && strpos($wsName, 'WISDOM') !== false) {
	$wsWeb = 'www.wisdomschoolrwanda.com';
}
$wsLogo = trim((string) ($school_logo ?? ''));
$prepName = trim((string) ($reqRow['prepared_by_name'] ?? ''));
if ($prepName === '') {
	$prepName = trim((string) (session('soma_name') ?? ''));
}
$prepPos = trim((string) ($reqRow['prepared_position'] ?? ''));
if ($prepPos === '') {
	$prepPos = trim((string) (session('soma_post_title') ?? ''));
}
$prepPhone = trim((string) ($reqRow['prepared_phone'] ?? ''));
if ($prepPhone === '') {
	$prepPhone = trim((string) ($preparer_phone ?? ''));
}
$prepSig = trim((string) ($preparer_signature ?? ''));
$sigBase = base_url('assets/images/signatures/');
$requestTerm = (int) ($reqRow['budget_term'] ?? 0);
if ($requestTerm < 1 || $requestTerm > 3) {
	$requestTerm = (int) ($term ?? 0);
}
?>

<div class="budget-cr-form ws-erf-wrap">

<?php if (empty($budgets)) { ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between ws-no-print">
	<span><i class="fa fa-exclamation-triangle"></i> No approved budget yet. Upload the Excel budget, then wait for the Chief Accountant and the Director of Finance.</span>
	<a href="<?= base_url('budget/prepare'); ?>" class="btn btn-sm btn-warning">Prepare budget</a>
</div>
<?php } ?>

<form id="frmCR" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)($reqRow['id'] ?? 0); ?>">
<input type="hidden" name="budget_period_id" id="budget_period_id" value="<?= (int)($reqRow['budget_period_id'] ?? 0); ?>">
<input type="hidden" name="requested_amount" id="requested_amount" value="<?= esc($reqRow['requested_amount'] ?? ''); ?>">
<input type="hidden" name="required_payment_date" value="<?= esc($reqRow['required_payment_date'] ?? ''); ?>">
<input type="hidden" name="urgency" value="<?= esc($reqRow['urgency'] ?? 'normal'); ?>">
<input type="hidden" name="payment_method" value="<?= esc($reqRow['payment_method'] ?? 'bank'); ?>">
<input type="hidden" name="payee_type" value="<?= esc($reqRow['payee_type'] ?? 'supplier'); ?>">
<input type="hidden" name="internal_notes" value="<?= esc($reqRow['internal_notes'] ?? ''); ?>">

<div class="ws-erf-bar ws-no-print">
	<div>
		<label>Approved budget</label>
		<select name="budget_id" id="budget_id" class="form-control form-control-sm" required <?= empty($budgets) ? 'disabled' : ''; ?>>
			<?php if (empty($budgets)) { ?><option value="">— No approved budget —</option><?php } ?>
			<?php foreach ($budgets as $b) { ?>
			<option value="<?= (int)$b['id']; ?>" data-period="<?= (int)($b['budget_period_id'] ?? 0); ?>" <?= isset($reqRow['budget_id']) && (int)$reqRow['budget_id']===(int)$b['id']?'selected':''; ?>><?= esc($b['title']); ?> (<?= number_format((float)$b['total_expenses'],0); ?> RWF)</option>
			<?php } ?>
		</select>
	</div>
	<div>
		<label>Term</label>
		<select name="budget_term" id="budget_term" class="form-control form-control-sm" required <?= empty($budgets) ? 'disabled' : ''; ?>>
			<option value="" <?= ($requestTerm < 1 || $requestTerm > 3) ? 'selected' : ''; ?>>— Select term —</option>
			<option value="1" <?= $requestTerm === 1 ? 'selected' : ''; ?>>Term I</option>
			<option value="2" <?= $requestTerm === 2 ? 'selected' : ''; ?>>Term II</option>
			<option value="3" <?= $requestTerm === 3 ? 'selected' : ''; ?>>Term III</option>
		</select>
	</div>
	<button type="button" class="btn btn-sm btn-primary btn-add-item" id="btnAddItem" <?= empty($budgets) ? 'disabled' : ''; ?>>
		<i class="fa fa-plus"></i> Add item
	</button>
	<span class="small text-muted pb-1">Items <strong id="kpiItemCount">0</strong></span>
</div>

<div class="ws-erf">
<table class="ws-head">
	<tr>
		<td style="width:90px">
			<?php if ($wsLogo !== '') { ?>
			<img class="ws-logo" src="<?= base_url('assets/images/logo/' . $wsLogo); ?>" alt="">
			<?php } ?>
		</td>
		<td>
			<div class="ws-school"><?= esc($wsName); ?></div>
			<?php if ($wsMotto !== '') { ?><div class="ws-motto">"<?= esc($wsMotto); ?>"</div><?php } ?>
			<?php if ($wsWeb !== '') { ?><div class="ws-web"><?= esc($wsWeb); ?></div><?php } ?>
		</td>
		<td style="width:90px"></td>
	</tr>
</table>
<hr class="ws-rule">
<div class="ws-title">EXPENSE REQUEST FORM</div>

<table class="ws-grid">
	<tr>
		<td class="ws-lab narrow">School</td>
		<td><?= esc($wsName); ?></td>
		<td class="ws-lab narrow">Date</td>
		<td style="width:22%"><input type="date" class="form-control" name="request_date" value="<?= esc($reqRow['request_date'] ?? date('Y-m-d')); ?>" required></td>
	</tr>
</table>

<div class="ws-banner">1. Department Request Details</div>
<table class="ws-grid">
	<tr>
		<td class="ws-lab">Prepared by (Names)</td>
		<td colspan="3"><input class="form-control" name="prepared_by_name" value="<?= esc($prepName); ?>" required></td>
	</tr>
	<tr>
		<td class="ws-lab">Position</td>
		<td colspan="3"><input class="form-control" name="prepared_position" value="<?= esc($prepPos); ?>"></td>
	</tr>
	<tr>
		<td class="ws-lab">Tel.</td>
		<td><input class="form-control" name="prepared_phone" value="<?= esc($prepPhone); ?>"></td>
		<td class="ws-lab narrow">Date</td>
		<td><input type="date" class="form-control" id="prepDateMirror" value="<?= esc($reqRow['request_date'] ?? date('Y-m-d')); ?>"></td>
	</tr>
	<tr>
		<td class="ws-lab">Signature</td>
		<td colspan="3">
			<?php if ($prepSig !== '') { ?>
			<img class="ws-sig-img" src="<?= esc($sigBase . $prepSig, 'attr'); ?>" alt="Signature">
			<?php } else { ?>
			<span class="ws-sign-line">______________________________</span>
			<div class="small text-muted">Uses the signature saved on your <a href="<?= base_url('profile'); ?>">profile</a>. Draw it there or upload a picture.</div>
			<?php } ?>
		</td>
	</tr>
</table>

<div class="ws-banner">2. Purpose of Purchase</div>
<table class="ws-grid">
	<tr>
		<td class="ws-write">
			<label class="ws-field-lab" for="wsPurpose">Write the purpose here</label>
			<textarea class="ws-purpose" id="wsPurpose" name="purpose" rows="4" placeholder="Example: Buy exercise books and chalk for level 3 SOD." required><?= esc($reqRow['purpose'] ?? ''); ?></textarea>
		</td>
	</tr>
</table>

<div class="ws-banner">3. Description of Materials / Items and Amounts</div>
<p class="ws-item-help">Select the term first. Start with the first item. Another row appears only when you add it, or when this request already has that item.</p>
<table class="ws-grid ws-items" id="tblRequestItems">
	<thead>
		<tr>
			<th class="ws-no">No.</th>
			<th>Description of Materials / Items</th>
			<th class="ws-amt-head">Amount (FRW)</th>
			<th class="ws-rm-head"></th>
		</tr>
	</thead>
	<tbody id="requestItemsBody"></tbody>
	<tfoot>
		<tr>
			<td colspan="2" style="text-align:right">TOTAL</td>
			<td id="kpiItemTotal">0</td>
			<td></td>
		</tr>
	</tfoot>
</table>
<div class="ws-add-item ws-no-print">
	<button type="button" class="btn btn-primary btn-add-item" <?= empty($budgets) ? 'disabled' : ''; ?>>
		<i class="fa fa-plus"></i> Add item
	</button>
</div>
<div id="itemsWarnBox" class="cr-avail-box danger mt-2" style="display:none"></div>

<div class="ws-banner">4. Budget Information</div>
<table class="ws-grid">
	<tr>
		<td class="ws-lab">Term</td>
		<td id="budgetTermLabel"><?php
			echo esc([1 => 'Term I', 2 => 'Term II', 3 => 'Term III'][$requestTerm] ?? '—');
		?></td>
	</tr>
	<tr>
		<td class="ws-lab">Budget line description</td>
		<td id="budgetLineSummary">—</td>
	</tr>
	<tr>
		<td class="ws-lab">Total amount in FRW requested (in figures)</td>
		<td id="wsFigures">0</td>
	</tr>
	<tr>
		<td class="ws-lab">Total amount in FRW (in words)</td>
		<td id="amountWords"></td>
	</tr>
</table>
<div id="budgetRemainNote" class="small text-muted px-1 pt-1"></div>

<div class="ws-banner">5. Payment Details</div>
<table class="ws-grid">
	<tr>
		<td class="ws-lab">Payment to be made to</td>
		<td><input class="form-control" name="payee_name" value="<?= esc($reqRow['payee_name'] ?? ''); ?>" required></td>
	</tr>
	<tr>
		<td class="ws-lab">Contact Info:</td>
		<td><input class="form-control" name="payee_contact" value="<?= esc($reqRow['payee_contact'] ?? ''); ?>"></td>
	</tr>
	<tr>
		<td class="ws-lab">Payment / Account Number:</td>
		<td><input class="form-control" name="payee_account" value="<?= esc($reqRow['payee_account'] ?? ''); ?>"></td>
	</tr>
	<tr>
		<td class="ws-lab">Bank Name:</td>
		<td><input class="form-control" name="payee_bank" value="<?= esc($reqRow['payee_bank'] ?? ''); ?>"></td>
	</tr>
</table>

<div class="ws-banner">6. Verification and Approval</div>
<table class="ws-grid">
	<tr>
		<td class="ws-lab">Verified by the Accountant</td>
		<td class="ws-sign-line">Signature: ____________</td>
		<td class="ws-sign-line">Date: ____________</td>
	</tr>
	<tr>
		<td class="ws-lab">Verified by the Headteacher</td>
		<td class="ws-sign-line">Signature: ____________</td>
		<td class="ws-sign-line">Date: ____________</td>
	</tr>
	<tr>
		<td class="ws-lab">Approved by the Chief Accountant</td>
		<td class="ws-sign-line">Signature: ____________</td>
		<td class="ws-sign-line">Date: ____________</td>
	</tr>
	<tr>
		<td class="ws-lab">Authorized by the Director of Finance</td>
		<td class="ws-sign-line">Signature: ____________</td>
		<td class="ws-sign-line">Date: ____________</td>
	</tr>
</table>

<div id="chainPreviewBox" class="small text-muted px-1 pt-2 ws-no-print" style="display:none">
	<strong id="chainPreviewLabel">—</strong>
	<div id="chainPreviewSteps"></div>
</div>
</div>

<div class="cr-section border-warning ws-no-print mt-3">
	<div class="cr-section-title"><i class="fa fa-paperclip"></i> Supporting documents <span class="text-danger">*</span> <small class="font-weight-normal text-muted">(required to submit)</small></div>

	<div class="cr-attach-panel">
		<div class="cr-doc-zone" id="docZone">
			<i class="fa fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
			<div>Drag files here or click to choose from this computer</div>
			<input type="file" name="documents[]" id="docInput" class="cr-file-hit" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx">
		</div>

		<div class="cr-attach-actions">
			<label for="docInput" class="btn btn-outline-primary cr-attach-btn mb-0" id="btnBrowseDocs">
				<i class="fa fa-paperclip"></i> Attach file
			</label>
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

	<div class="cr-scan-wait d-none" id="scanWaitBox">
		<div class="cr-scan-orb" aria-hidden="true">
			<span class="cr-scan-orb-spin"></span>
			<i class="fa fa-mobile-alt"></i>
		</div>
		<div class="cr-scan-wait-body">
			<div class="cr-scan-wait-title">Waiting for the phone upload</div>
			<div class="cr-scan-wait-sub">The phone camera opens when you tap Smart Scan. Fit the page, tap the circle, and this form attaches the file.</div>
			<div class="cr-scan-meter" aria-hidden="true"><span></span></div>
			<div class="cr-scan-wait-meta">Time left <strong id="scanCountdown">30:00</strong> <span class="d-none" id="scanWaitMins">30</span></div>
		</div>
		<button type="button" class="btn btn-sm btn-outline-secondary" id="btnCancelScan">Cancel</button>
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

<div class="d-flex flex-wrap ws-actions ws-no-print">
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
			var avail = ' - remaining ' + formatMoney(availNum) + ' RWF';
			h += '<option value="' + l.id + '" data-cat="' + $('<div>').text(l.category).html() + '">'
				+ $('<div>').text(l.category).html() + avail + '</option>';
		});
		h += '</optgroup>';
	});
	return h;
}

function frwWords(n) {
	n = Math.round(Number(n) || 0);
	if (n <= 0) return '';
	var ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
	var tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
	function chunk(num) {
		if (num < 20) return ones[num];
		if (num < 100) return tens[Math.floor(num / 10)] + (num % 10 ? '-' + ones[num % 10] : '');
		return ones[Math.floor(num / 100)] + ' hundred' + (num % 100 ? ' ' + chunk(num % 100) : '');
	}
	var parts = [];
	var scales = [[1000000000, 'billion'], [1000000, 'million'], [1000, 'thousand']];
	var num = n;
	for (var i = 0; i < scales.length; i++) {
		var v = Math.floor(num / scales[i][0]);
		if (v) {
			parts.push(chunk(v) + ' ' + scales[i][1]);
			num %= scales[i][0];
		}
	}
	if (num) parts.push(chunk(num));
	var text = parts.join(' ');
	return text.charAt(0).toUpperCase() + text.slice(1) + ' Rwandan francs';
}

function formatMoney(n) {
	return Math.round(Number(n) || 0).toLocaleString('en-US');
}
function parseMoney(v) {
	var s = String(v || '').replace(/[\s\u00a0\u202f]/g, '').replace(/,/g, '');
	if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
	var n = parseFloat(s);
	return isNaN(n) ? 0 : n;
}
function amountsByLine() {
	var byLine = {};
	$('#requestItemsBody tr').each(function () {
		var lid = $(this).find('.item-line').val();
		if (!lid) return;
		byLine[lid] = (byLine[lid] || 0) + parseMoney($(this).find('.item-amount').val());
	});
	return byLine;
}

function rowBlank($tr) {
	var lid = $tr.find('.item-line').val();
	var amt = parseMoney($tr.find('.item-amount').val());
	var desc = $.trim($tr.find('.item-desc').val() || '');
	return !lid && amt <= 0 && desc === '';
}

function addItemRow(preset) {
	preset = preset || {};
	var $tr = $('<tr class="cr-item-row">'
		+ '<td class="ws-no"></td>'
		+ '<td class="ws-desc-cell">'
		+ '<label class="ws-field-lab">Budget line</label>'
		+ '<select name="item_budget_line_id[]" class="form-control form-control-sm item-line">' + lineOptionsHtml + '</select>'
		+ '<label class="ws-field-lab">Description — type what you are buying</label>'
		+ '<textarea name="item_description[]" class="form-control item-desc" rows="2" placeholder="Example: 40 exercise books, 10 boxes of chalk"></textarea>'
		+ '<div class="item-remain ws-remain">—</div></td>'
		+ '<td class="ws-amt-cell">'
		+ '<label class="ws-field-lab">Type the amount</label>'
		+ '<input type="text" inputmode="decimal" autocomplete="off" name="item_amount[]" class="form-control item-amount" placeholder="25000">'
		+ '</td>'
		+ '<td class="ws-rm-cell"><button type="button" class="ws-rm btn-rm-item" title="Remove item">&times;</button></td>'
		+ '</tr>');
	$('#requestItemsBody').append($tr);
	if (preset.budget_line_id) {
		$tr.find('.item-line').val(String(preset.budget_line_id));
	}
	if (preset.description) {
		$tr.find('.item-desc').val(preset.description);
	}
	if (preset.amount) {
		var presetAmt = parseMoney(preset.amount);
		$tr.find('.item-amount').val(presetAmt > 0 ? formatMoney(presetAmt) : '');
	}
	refreshItemRow($tr);
	recalcItems();
}

function refreshItemRow($tr, byLine) {
	var lid = String($tr.find('.item-line').val() || '');
	var av = lineData[lid];
	var $remain = $tr.find('.item-remain');
	$tr.removeClass('table-danger table-warning');
	$remain.removeClass('text-danger text-warning text-success');
	if (!lid || !av) {
		$remain.text('—');
		return;
	}
	var ceiling = parseFloat(av.available) || 0;
	var used = byLine[lid] || 0;
	var left = ceiling - used;
	$remain.text('Remaining ' + formatMoney(left) + ' FRW');
	if (left < 0) {
		$remain.addClass('text-danger');
		$tr.addClass('table-danger');
	} else if (used > 0 && ceiling > 0 && used >= ceiling * 0.8) {
		$remain.addClass('text-warning');
		$tr.addClass('table-warning');
	} else {
		$remain.addClass('text-success');
	}
}
function paintLineRemainings(byLine) {
	$('#requestItemsBody .item-line').each(function () {
		var $sel = $(this);
		var current = $sel.val();
		$sel.find('option').each(function () {
			var id = String($(this).val() || '');
			if (!id || !lineData[id]) return;
			var name = $(this).attr('data-cat') || '';
			var ceiling = parseFloat(lineData[id].available) || 0;
			var left = ceiling - (byLine[id] || 0);
			$(this).text(name + ' - remaining ' + formatMoney(left) + ' RWF');
		});
		if (current) $sel.val(current);
	});
}

function recalcItems() {
	var total = 0;
	var count = 0;
	var over = [];
	var byLine = amountsByLine();
	paintLineRemainings(byLine);
	$('#requestItemsBody tr').each(function () {
		var $tr = $(this);
		refreshItemRow($tr, byLine);
		var amt = parseMoney($tr.find('.item-amount').val());
		if (amt > 0) {
			total += amt;
			count += 1;
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
	var rowNo = 0;
	$('#requestItemsBody tr').each(function () {
		rowNo += 1;
		$(this).find('.ws-no').text(rowNo);
	});
	var lineNames = [];
	var seenLine = {};
	$('#requestItemsBody .item-line').each(function () {
		var lid = $(this).val();
		if (!lid || seenLine[lid]) return;
		seenLine[lid] = 1;
		var name = String($(this).find('option:selected').text() || '').split(' - remaining')[0].trim();
		if (name && name.charAt(0) !== '—' && name.indexOf('Select') !== 0) lineNames.push(name);
	});
	$('#budgetLineSummary').text(lineNames.length ? lineNames.join('; ') : '—');
	$('#kpiItemCount').text(count);
	$('#kpiItemTotal').text(Number(total).toLocaleString());
	$('#wsFigures').text(total > 0 ? Number(total).toLocaleString() : '0');
	$('#amountWords').text(total > 0 ? frwWords(total) : '');
	$('#requested_amount').val(total > 0 ? total : '');
	var remainBits = [];
	Object.keys(byLine).forEach(function (lid) {
		var av = lineData[lid];
		if (!av || !byLine[lid]) return;
		var remain = parseFloat(av.available) || 0;
		var name = $('#requestItemsBody .item-line').filter(function () {
			return String($(this).val()) === String(lid);
		}).first().find('option:selected').text() || ('Line #' + lid);
		name = String(name).split(' - remaining')[0].trim();
		remainBits.push(name + ' deducts ' + Number(byLine[lid]).toLocaleString()
			+ ' FRW — remaining ' + Number(remain - byLine[lid]).toLocaleString() + ' FRW');
	});
	$('#budgetRemainNote').text(remainBits.length ? remainBits.join(' · ') : '');
	if (over.length) {
		$('#itemsWarnBox').html('<strong>Over budget:</strong> ' + over.join('; ')).show();
	} else {
		$('#itemsWarnBox').hide().empty();
	}
	refreshChainPreview(total);
}

function itemHasContent(p) {
	return !!(p && (p.budget_line_id || parseMoney(p.amount) > 0 || $.trim(p.description || '')));
}
function paintItemRows(presets) {
	var rows = (presets || []).filter(itemHasContent);
	$('#requestItemsBody').empty();
	if (!rows.length) {
		addItemRow();
		return;
	}
	rows.forEach(function (p) { addItemRow(p); });
}
function loadLines(done) {
	var bid = $('#budget_id').val();
	var term = $('#budget_term').val();
	var termLabels = {1: 'Term I', 2: 'Term II', 3: 'Term III'};
	$('#budgetTermLabel').text(termLabels[term] || '—');
	if (!bid || !term) {
		lineOptionsHtml = '<option value="">' + (!bid ? '— Select budget first —' : '— Select the term first —') + '</option>';
		if (!$('#requestItemsBody tr').length) addItemRow();
		else $('#requestItemsBody .item-line').html(lineOptionsHtml);
		if (typeof done === 'function') done();
		return;
	}
	syncPeriod();
	$.getJSON('<?= base_url('budget/get_budget_lines_json/'); ?>' + bid + '?term=' + encodeURIComponent(term), function (r) {
		var lines = r.lines || [];
		lineOptionsHtml = lines.length ? buildLineOptionsHtml(lines) : '<option value="">— No expense lines in this term —</option>';
		var presets = [];
		$('#requestItemsBody tr').each(function () {
			var $tr = $(this);
			if (rowBlank($tr)) return;
			presets.push({
				budget_line_id: $tr.find('.item-line').val(),
				description: $tr.find('.item-desc').val(),
				amount: $tr.find('.item-amount').val()
			});
		});
		if (!presets.length && existingItems.length) {
			presets = existingItems.slice();
			existingItems = [];
		}
		paintItemRows(presets);
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

$('#budget_id, #budget_term').on('change', function () { loadLines(); });
$(document).on('click', '.btn-add-item', function () { addItemRow(); });
$(document).on('click', '.btn-rm-item', function () {
	if ($('#requestItemsBody tr').length <= 1) {
		toastada.error('Keep at least one item.');
		return;
	}
	$(this).closest('tr').remove();
	recalcItems();
});
$(document).on('change', '.item-line', function () {
	recalcItems();
});
$(document).on('input', '.item-amount', recalcItems);
$(document).on('blur', '.item-amount', function () {
	var n = parseMoney($(this).val());
	$(this).val(n > 0 ? formatMoney(n) : '');
	recalcItems();
});
$(document).on('input', '.item-desc', function () {
	$(this).data('auto', 0);
});
loadLines();

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

$('input[name="request_date"]').on('change', function () {
	$('#prepDateMirror').val($(this).val());
});
$('#prepDateMirror').on('change', function () {
	$('input[name="request_date"]').val($(this).val());
});

function validateSubmit() {
	var ok = true;
	var total = 0;
	var rows = 0;
	$('#requestItemsBody tr').each(function () {
		var $tr = $(this);
		if (rowBlank($tr)) return;
		var lid = $tr.find('.item-line').val();
		var amt = parseMoney($tr.find('.item-amount').val());
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
	if (!$('#budget_term').val()) {
		toastada.error('Select Term I, Term II, or Term III first.');
		return;
	}
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
	var deepLink = r.deep_link || ('smartsms://amscan?token=' + encodeURIComponent(token));
	try {
		var ifr = document.createElement('iframe');
		ifr.style.display = 'none';
		ifr.src = deepLink;
		document.body.appendChild(ifr);
		setTimeout(function () { try { document.body.removeChild(ifr); } catch (e2) {} }, 2000);
	} catch (e3) {}
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

$('#btnCancelScan').on('click', function () { stopScanPoll(); });
$('#btnScanPhone').on('click', function () {
	$.post('<?= base_url('budget/scan_session_start'); ?>', {}, function (r) {
		if (r.error) { toastada.error(r.error); return; }
		startScanPoll(r.token, r.expires_in || 1800);
		openSmartSmsAmScan(r);
	}, 'json');
});
$('#frmCR').on('submit', function (e) { e.preventDefault(); postForm(false); });
$('#btnSubmitCR').on('click', function () { postForm(true); });
</script>
