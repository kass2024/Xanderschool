<input type="text" id="rfidBuffer" class="sr-only" autocomplete="off" tabindex="0" aria-label="RFID scan buffer">

<div class="row mb-3">
	<div class="col-md-6">
		<label class="mr-2">Scan sequence:</label>
		<div class="btn-group btn-group-toggle" data-toggle="buttons">
			<label class="btn btn-outline-primary active"><input type="radio" name="seq" value="person_first" checked> Person first</label>
			<label class="btn btn-outline-primary"><input type="radio" name="seq" value="asset_first"> Asset first</label>
		</div>
	</div>
	<div class="col-md-6 text-md-right">
		<small class="text-muted"><i class="fa fa-wifi"></i> RFID reader active — scan card or asset tag</small>
	</div>
</div>

<div class="row mb-3">
	<div class="col-md-6">
		<div class="card border-info" id="panelPerson">
			<div class="card-header bg-info text-white">Person / borrower</div>
			<div class="card-body">
				<div class="media">
					<img id="personPhoto" src="" class="d-none mr-3 rounded" style="width:64px;height:64px;object-fit:cover" alt="">
					<div class="media-body">
						<h5 class="mb-1" id="personName">—</h5>
						<p class="mb-0"><span class="badge badge-secondary" id="personType">—</span> <span id="personId"></span></p>
						<small class="text-muted" id="personMeta"></small>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-md-6">
		<div class="card border-success" id="panelAsset">
			<div class="card-header bg-success text-white">Asset</div>
			<div class="card-body">
				<h5 class="mb-1" id="assetCode">—</h5>
				<p class="mb-0" id="assetName">—</p>
				<small>Status: <span class="badge badge-light" id="assetStatus">—</span></small>
			</div>
		</div>
	</div>
</div>

<div class="card mb-3">
	<div class="card-header">Checkout / check-in actions</div>
	<div class="card-body">
		<div class="row">
			<div class="col-md-3 form-group">
				<label>Due date</label>
				<input type="datetime-local" class="form-control" id="dueAt">
			</div>
			<div class="col-md-3 form-group">
				<label>Issue condition</label>
				<select class="form-control" id="issueCondition">
					<option value="good">good</option>
					<option value="new">new</option>
					<option value="fair">fair</option>
				</select>
			</div>
			<div class="col-md-4 form-group">
				<label>Notes</label>
				<input type="text" class="form-control" id="checkoutNotes" placeholder="Optional">
			</div>
			<div class="col-md-2 form-group d-flex align-items-end">
				<button type="button" class="btn btn-primary btn-block" id="btnCheckout">Check out</button>
			</div>
		</div>
		<hr>
		<p class="text-muted mb-2">Manual fallback</p>
		<div class="row">
			<div class="col-md-4 form-group">
				<label>Staff borrower</label>
				<select class="form-control" id="manualStaff">
					<option value="">— Scan card or select —</option>
					<?php foreach ($staffs as $s) { ?>
						<option value="<?= (int)$s['id']; ?>"><?= esc($s['names']); ?></option>
					<?php } ?>
				</select>
			</div>
			<div class="col-md-4 form-group">
				<label>Asset</label>
				<select class="form-control" id="manualAsset">
					<option value="">— Scan or select —</option>
					<?php foreach ($assets as $a) { ?>
						<option value="<?= (int)$a['id']; ?>" data-code="<?= esc($a['asset_code'], 'attr'); ?>" data-name="<?= esc($a['name'], 'attr'); ?>" data-status="<?= esc($a['lifecycle_status'], 'attr'); ?>"><?= esc($a['asset_code'] . ' — ' . $a['name']); ?></option>
					<?php } ?>
				</select>
			</div>
			<div class="col-md-4 form-group d-flex align-items-end">
				<button type="button" class="btn btn-outline-secondary" id="btnApplyManual">Apply manual selection</button>
			</div>
		</div>
	</div>
</div>

<?php if (!empty($open_loans)) { ?>
<div class="card border-danger mb-3">
	<div class="card-header bg-danger text-white">Overdue loans (<?= count($open_loans); ?>)</div>
	<div class="card-body p-0">
		<table class="table table-sm mb-0">
			<thead><tr><th>Asset</th><th>Borrower</th><th>Due</th><th>Status</th></tr></thead>
			<tbody>
			<?php foreach ($open_loans as $ln) { ?>
				<tr>
					<td><?= esc(($ln['asset_code'] ?? '') . ' — ' . ($ln['asset_name'] ?? '')); ?></td>
					<td><?= esc($ln['borrower_type'] . ' #' . $ln['borrower_id']); ?></td>
					<td><?= esc($ln['due_at'] ?? '—'); ?></td>
					<td><span class="badge badge-danger"><?= esc($ln['status']); ?></span></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>
<?php } ?>

<div class="card">
	<div class="card-header">Active loans</div>
	<div class="card-body">
		<table id="tblLoans" class="table table-striped table-bordered" style="width:100%">
			<thead>
			<tr>
				<th>#</th>
				<th>Asset</th>
				<th>Borrower</th>
				<th>Issued</th>
				<th>Due</th>
				<th>Status</th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($active_loans as $ln) { ?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc(($ln['asset_code'] ?? '') . ' — ' . ($ln['asset_name'] ?? '')); ?></td>
					<td><?= esc($ln['borrower_type'] . ' #' . $ln['borrower_id']); ?></td>
					<td><?= esc($ln['issue_at'] ?? '—'); ?></td>
					<td><?= esc($ln['due_at'] ?? '—'); ?></td>
					<td><?= esc($ln['status']); ?></td>
					<td>
						<button type="button" class="btn btn-sm btn-warning btn-return" data-id="<?= (int)$ln['id']; ?>">Return</button>
					</td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="mdlReturn" tabindex="-1">
	<div class="modal-dialog modal-sm">
		<form class="modal-content" id="frmReturn">
			<div class="modal-header"><h5 class="modal-title">Check in asset</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
			<div class="modal-body">
				<input type="hidden" name="loan_id" id="returnLoanId">
				<div class="form-group">
					<label>Return condition</label>
					<select name="return_condition" class="form-control">
						<option value="good">good</option>
						<option value="fair">fair</option>
						<option value="damaged">damaged</option>
					</select>
				</div>
				<div class="form-group">
					<label>Notes</label>
					<input type="text" name="notes" class="form-control">
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-primary">Confirm return</button>
			</div>
		</form>
	</div>
</div>

<script>
$(function () {
	var state = { person: null, asset: null, step: 'person', scanLock: false };
	var $rfid = $('#rfidBuffer');
	var defaultDue = new Date();
	defaultDue.setDate(defaultDue.getDate() + 7);
	$('#dueAt').val(defaultDue.toISOString().slice(0, 16));
	if ($.fn.DataTable) $('#tblLoans').DataTable({ pageLength: 25, order: [[0, 'desc']] });
	$rfid.focus();

	function getSeq() {
		return $('input[name=seq]:checked').val() || 'person_first';
	}
	function resetStep() {
		state.step = getSeq() === 'asset_first' ? 'asset' : 'person';
	}
	$('input[name=seq]').on('change', resetStep);
	resetStep();

	function normalizeUID(uid) {
		uid = (uid || '').trim();
		if (!uid) return '';
		if (/^\d+$/.test(uid)) {
			try {
				var num = BigInt(uid);
				uid = num.toString(16).toUpperCase();
				uid = uid.padStart(8, '0');
			} catch (e) {}
		}
		uid = uid.replace(/[^A-Fa-f0-9]/g, '').toUpperCase();
		if (uid.length % 2 === 0) {
			var bytes = uid.match(/.{1,2}/g);
			bytes.reverse();
			uid = bytes.join('');
		}
		return uid.toUpperCase();
	}

	function showPerson(p) {
		state.person = p;
		$('#personName').text(p.full_name || '—');
		$('#personType').text(p.type || '—');
		$('#personId').text('#' + (p.id || ''));
		$('#personMeta').text(p.reg_no ? ('Reg: ' + p.reg_no) : (p.email || p.phone || ''));
		if (p.photo_path) {
			$('#personPhoto').attr('src', '<?= base_url(); ?>' + p.photo_path.replace(/^\//, '')).removeClass('d-none');
		} else {
			$('#personPhoto').addClass('d-none');
		}
		if (p.type === 'staff') $('#manualStaff').val(p.id);
	}

	function showAsset(a) {
		state.asset = a;
		$('#assetCode').text(a.asset_code || '—');
		$('#assetName').text(a.name || '—');
		$('#assetStatus').text(a.lifecycle_status || '—');
		if (a.id) $('#manualAsset').val(a.id);
	}

	function handleScan(raw) {
		if (state.scanLock) return;
		state.scanLock = true;
		setTimeout(function () { state.scanLock = false; }, 400);
		var code = normalizeUID(raw);
		if (!code) return;
		var expect = state.step;
		if (expect === 'person') {
			$.post('<?= base_url('asset_management/scan_person'); ?>', { card: code }, function (res) {
				if (res.error) { toastada.error(res.error); return; }
				if (!res.type) { toastada.error('Person not found'); return; }
				showPerson(res);
				state.step = 'asset';
				toastada.success('Person scanned — scan asset');
			}, 'json').fail(function () { toastada.error('Scan failed'); });
		} else {
			$.post('<?= base_url('asset_management/scan_asset'); ?>', { code: code }, function (res) {
				if (res.error) { toastada.error(res.error); return; }
				if (!res.id) { toastada.error('Asset not found'); return; }
				showAsset(res);
				state.step = 'person';
				toastada.success('Asset scanned');
			}, 'json').fail(function () { toastada.error('Scan failed'); });
		}
	}

	var buffer = '';
	$(document).on('keypress', function (e) {
		if ($(e.target).is('input, textarea, select') && e.target.id !== 'rfidBuffer') return;
		if (e.key === 'Enter') {
			var raw = buffer.trim() || $rfid.val().trim();
			buffer = '';
			$rfid.val('');
			if (raw.length >= 3) handleScan(raw);
			e.preventDefault();
		} else if (e.key.length === 1) {
			buffer += e.key;
		}
	});
	$rfid.on('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			handleScan($(this).val());
			$(this).val('');
		}
	});

	$('#btnApplyManual').on('click', function () {
		var sid = $('#manualStaff').val();
		if (sid) {
			showPerson({ type: 'staff', id: parseInt(sid, 10), full_name: $('#manualStaff option:selected').text() });
		}
		var $opt = $('#manualAsset option:selected');
		if ($opt.val()) {
			showAsset({
				id: parseInt($opt.val(), 10),
				asset_code: $opt.data('code'),
				name: $opt.data('name'),
				lifecycle_status: $opt.data('status')
			});
		}
		toastada.success('Manual selection applied');
	});

	$('#btnCheckout').on('click', function () {
		if (!state.person || !state.asset) {
			toastada.error('Scan or select both person and asset');
			return;
		}
		$.post('<?= base_url('asset_management/do_checkout'); ?>', {
			asset_id: state.asset.id,
			borrower_type: state.person.type,
			borrower_id: state.person.id,
			due_at: $('#dueAt').val(),
			issue_condition: $('#issueCondition').val(),
			notes: $('#checkoutNotes').val()
		}, function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Checked out');
			setTimeout(function () { location.reload(); }, 700);
		}, 'json');
	});

	$(document).on('click', '.btn-return', function () {
		$('#returnLoanId').val($(this).data('id'));
		$('#mdlReturn').modal('show');
	});
	$('#frmReturn').on('submit', function (e) {
		e.preventDefault();
		$.post('<?= base_url('asset_management/do_checkin'); ?>', $(this).serialize(), function (res) {
			if (res.error) { toastada.error(res.error); return; }
			toastada.success('Returned');
			setTimeout(function () { location.reload(); }, 600);
		}, 'json');
	});

	setInterval(function () { if (!$('input:focus, textarea:focus, select:focus').length) $rfid.focus(); }, 2000);
});
</script>
