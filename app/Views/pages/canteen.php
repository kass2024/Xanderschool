<?php
$items = $items ?? [];
$units = $units ?? [];
$desk = $desk ?? [];
$canteenView = (string) ($canteenView ?? 'invoices');
if (!in_array($canteenView, ['invoices', 'menu', 'units'], true)) {
	$canteenView = 'invoices';
}
$period = (string) ($desk['period'] ?? 'day');
$on = (string) ($desk['on'] ?? date('Y-m-d'));
$from = (string) ($desk['from'] ?? date('Y-m-d'));
$to = (string) ($desk['to'] ?? date('Y-m-d'));
$label = (string) ($desk['label'] ?? '');
$studentRows = $desk['studentRows'] ?? [];
$itemRows = $desk['itemRows'] ?? [];
$link = static function (array $extra) use ($period, $on, $from, $to, $canteenView): string {
	$params = array_merge([
		'period' => $period,
		'on' => $on,
		'from' => $from,
		'to' => $to,
		'view' => $canteenView,
	], $extra);
	return base_url('canteen') . '?' . http_build_query($params);
};
$keep = static function (string $view) use ($period, $on, $from, $to): string {
	$html = '';
	foreach (['period' => $period, 'on' => $on, 'from' => $from, 'to' => $to, 'view' => $view] as $key => $value) {
		$html .= '<input type="hidden" name="' . esc($key, 'attr') . '" value="' . esc($value, 'attr') . '">';
	}
	return $html;
};
$unitOptions = static function ($selected) use ($units): string {
	$html = '<option value="">Choose unit</option>';
	foreach ($units as $unit) {
		$id = (int) $unit['id'];
		$mark = $id === (int) $selected ? ' selected' : '';
		$html .= '<option value="' . $id . '"' . $mark . '>' . esc($unit['name']) . '</option>';
	}
	return $html;
};
?>
<link rel="stylesheet" href="<?= base_url('assets/css/fees-report.css'); ?>?v=6">
<link rel="stylesheet" href="<?= base_url('assets/css/canteen.css'); ?>?v=1">
<style>
	#canteenPage .cn-tabs { display: flex; gap: 10px; margin: 0 0 16px; }
	#canteenPage .cn-tabs a {
		flex: 1;
		text-align: center;
		background: #fff;
		border: 1px solid #e8edf3;
		border-radius: 14px;
		padding: 14px 8px;
		font-weight: 700;
		color: #0f172a;
		text-decoration: none;
		box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
	}
	#canteenPage .cn-tabs a small { display: block; font-weight: 600; color: #64748b; margin-top: 2px; }
	#canteenPage .cn-tabs a.active { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
	#canteenPage .cn-tabs a.active small { color: #dbeafe; }
	#canteenPage .cn-add { padding: 16px; border-bottom: 1px solid #e8edf3; }
	#canteenPage .cn-add-grid { display: grid; grid-template-columns: 2fr 1.2fr 1fr 1fr auto; gap: 10px; align-items: end; }
	#canteenPage .cn-add label { display: block; font-size: 0.72rem; font-weight: 700; color: #475569; margin-bottom: 4px; text-transform: uppercase; }
	#canteenPage .cn-menu td { vertical-align: middle; }
	#canteenPage .cn-menu .form-control { width: 100%; min-width: 0; }
	#canteenPage .cn-actions { display: flex; gap: 6px; justify-content: flex-end; }
	@media (max-width: 900px) {
		#canteenPage .cn-tabs { flex-direction: column; }
		#canteenPage .cn-add-grid { grid-template-columns: 1fr 1fr; }
	}
</style>

<div class="fr-page" id="canteenPage">
	<div class="fr-center">
		<div class="fr-header">
			<h2>Canteen</h2>
			<p><?= $canteenView === 'menu' ? 'What the till sells, with a unit and a stock count.' : ($canteenView === 'units' ? 'Measures used on the menu, such as cup or 1.5 L.' : 'Each card sale is its own invoice for ' . esc($label) . '.'); ?></p>
		</div>

		<?php if (session()->getFlashdata('success')) { ?>
			<div class="alert alert-success"><?= esc(session()->getFlashdata('success')); ?></div>
		<?php } ?>
		<?php if (session()->getFlashdata('error')) { ?>
			<div class="alert alert-danger"><?= esc(session()->getFlashdata('error')); ?></div>
		<?php } ?>

		<nav class="cn-tabs">
			<a class="<?= $canteenView === 'invoices' ? 'active' : ''; ?>" href="<?= esc($link(['view' => 'invoices'])); ?>">
				Invoices
				<small><?= (int) ($desk['invoiceCount'] ?? 0); ?> in this period</small>
			</a>
			<a class="<?= $canteenView === 'menu' ? 'active' : ''; ?>" href="<?= esc($link(['view' => 'menu'])); ?>">
				Menu
				<small><?= count($items); ?> items</small>
			</a>
			<a class="<?= $canteenView === 'units' ? 'active' : ''; ?>" href="<?= esc($link(['view' => 'units'])); ?>">
				Units
				<small><?= count($units); ?> measures</small>
			</a>
		</nav>

		<?php if ($canteenView === 'invoices') { ?>
		<div class="fr-filter-card">
			<form method="get" action="<?= base_url('canteen'); ?>">
				<input type="hidden" name="view" value="invoices">
				<table class="fr-select-table">
					<tr>
						<th>Period</th>
						<td>
							<select class="form-control" name="period" onchange="this.form.submit()">
								<option value="day"<?= $period === 'day' ? ' selected' : ''; ?>>Today / one day</option>
								<option value="week"<?= $period === 'week' ? ' selected' : ''; ?>>This week</option>
								<option value="month"<?= $period === 'month' ? ' selected' : ''; ?>>This month</option>
								<option value="range"<?= $period === 'range' ? ' selected' : ''; ?>>Date range</option>
							</select>
						</td>
						<?php if ($period === 'month') { ?>
							<th>Month</th>
							<td><input class="form-control" type="month" name="on" value="<?= esc($on, 'attr'); ?>"></td>
							<th></th><td></td>
						<?php } elseif ($period === 'range') { ?>
							<th>From</th>
							<td><input class="form-control" type="date" name="from" value="<?= esc($from, 'attr'); ?>" required></td>
							<th>To</th>
							<td><input class="form-control" type="date" name="to" value="<?= esc($to, 'attr'); ?>" required></td>
						<?php } elseif ($period === 'week') { ?>
							<th>Week containing</th>
							<td><input class="form-control" type="date" name="on" value="<?= esc($on, 'attr'); ?>" required></td>
							<th></th><td></td>
						<?php } else { ?>
							<th>Day</th>
							<td><input class="form-control" type="date" name="on" value="<?= esc($on, 'attr'); ?>" required></td>
							<th></th><td></td>
						<?php } ?>
					</tr>
				</table>
				<div style="margin-top:10px;">
					<button class="btn btn-primary" type="submit">Show report</button>
				</div>
			</form>
		</div>

		<?php
		$invoiceRows = $desk['invoiceRows'] ?? [];
		$canSettle = !empty($canSettleCanteen);
		?>
		<div class="cn-kpis">
			<div class="cn-kpi">
				<span>Sales</span>
				<strong><?= number_format((int) ($desk['sales'] ?? 0)); ?></strong>
				<small>Rwf this period</small>
			</div>
			<div class="cn-kpi cn-kpi-open">
				<span>Unpaid</span>
				<strong id="cnUnpaidCount"><?= number_format((int) ($desk['unpaidCount'] ?? 0)); ?></strong>
				<small id="cnUnpaidAmount"><?= number_format((int) ($desk['unpaidSales'] ?? 0)); ?> Rwf</small>
			</div>
			<div class="cn-kpi cn-kpi-paid">
				<span>Paid</span>
				<strong id="cnPaidCount"><?= number_format((int) ($desk['paidCount'] ?? 0)); ?></strong>
				<small id="cnPaidAmount"><?= number_format((int) ($desk['paidSales'] ?? 0)); ?> Rwf</small>
			</div>
			<div class="cn-kpi">
				<span>Invoices</span>
				<strong><?= number_format((int) ($desk['invoiceCount'] ?? 0)); ?></strong>
				<small><?= number_format((int) ($desk['students'] ?? 0)); ?> students</small>
			</div>
		</div>

		<div class="fr-panel">
			<div class="fr-panel-head">
				<h3>Invoices · <?= esc($label); ?></h3>
				<div class="fr-actions">
					<a href="<?= esc($link(['view' => 'invoices', 'pdf' => '1'])); ?>" target="_blank" class="btn btn-danger">
						<i class="fa fa-file-pdf"></i> Export PDF
					</a>
				</div>
			</div>
			<div class="cn-tools">
				<input type="search" class="form-control" id="canteenSearch" placeholder="Search one invoice: student, class, reg no, or item" autocomplete="off">
				<div class="cn-pills" id="cnPills">
					<button type="button" class="is-on" data-status="">All</button>
					<button type="button" data-status="unpaid">Unpaid</button>
					<button type="button" data-status="paid">Paid</button>
				</div>
			</div>
			<p class="cn-hint" id="cnHint">Showing <span id="cnShown"><?= count($invoiceRows); ?></span> of <?= count($invoiceRows); ?> invoices.<?= $canSettle ? ' You can mark an invoice paid.' : ' Paid and unpaid stay separate.'; ?></p>
			<div class="fr-table-wrap">
				<table class="table mb-0 cn-invoices" id="canteenInvoices">
					<thead>
					<tr>
						<th>INVOICE</th>
						<th>STUDENT</th>
						<th>WHAT THEY TOOK</th>
						<th class="text-right">AMOUNT</th>
						<th class="text-right">LEFT ON CARD</th>
						<th>STATUS</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($invoiceRows as $invoice) :
						$paid = !empty($invoice['paid']);
						$when = strtotime((string) ($invoice['at'] ?? ''));
						$whenLabel = $when ? date('d M Y H:i', $when) : '';
						$paidAt = strtotime((string) ($invoice['paid_at'] ?? ''));
						$paidLabel = $paidAt ? date('d M Y H:i', $paidAt) : '';
						$itemsText = trim((string) ($invoice['items'] ?? ''));
						$chips = $itemsText === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $itemsText))));
						$search = strtolower('#' . (int) $invoice['id'] . ' ' . $invoice['name'] . ' ' . $invoice['regno'] . ' ' . $invoice['class_name'] . ' ' . $itemsText . ' ' . ($paid ? 'paid' : 'unpaid'));
						?>
						<tr class="<?= $paid ? 'is-paid' : 'is-unpaid'; ?>" data-search="<?= esc($search, 'attr'); ?>" data-status="<?= $paid ? 'paid' : 'unpaid'; ?>" data-id="<?= (int) $invoice['id']; ?>" data-amount="<?= (int) $invoice['amount']; ?>">
							<td>
								<div class="cn-inv">#<?= (int) $invoice['id']; ?></div>
								<div class="cn-when"><?= esc($whenLabel); ?></div>
							</td>
							<td>
								<div class="cn-name"><?= esc($invoice['name']); ?></div>
								<div class="cn-meta"><?= esc($invoice['class_name'] !== '' ? $invoice['class_name'] : '—'); ?> · <?= esc($invoice['regno']); ?></div>
							</td>
							<td>
								<?php if ($chips === []) { ?>
									<span class="cn-meta">—</span>
								<?php } else { foreach ($chips as $chip) { ?>
									<span class="cn-chip"><?= esc($chip); ?></span>
								<?php } } ?>
							</td>
							<td class="text-right cn-amount"><?= number_format((int) $invoice['amount']); ?></td>
							<td class="text-right"><?= number_format((int) $invoice['balance']); ?></td>
							<td class="cn-status">
								<span class="cn-badge <?= $paid ? 'is-paid' : 'is-unpaid'; ?>"><?= $paid ? 'Paid' : 'Unpaid'; ?></span>
								<div class="cn-paid-note"><?php
									$by = trim((string) ($invoice['paid_by_name'] ?? ''));
									echo $paid ? esc(trim($paidLabel . ($by !== '' ? ' · ' . $by : ''))) : '';
								?></div>
								<?php if ($canSettle) { ?>
									<button type="button" class="cn-mark" data-paid="<?= $paid ? '1' : '0'; ?>"><?= $paid ? 'Mark unpaid' : 'Mark paid'; ?></button>
								<?php } ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="cn-empty" id="cnEmpty"<?= $invoiceRows === [] ? '' : ' hidden'; ?>><?= $invoiceRows === [] ? 'No card sales in this period.' : 'No invoice matches that search.'; ?></p>
		</div>

		<div class="fr-panel">
			<div class="fr-panel-head"><h3>Sold in this period</h3></div>
			<div class="fr-table-wrap">
				<table class="table mb-0">
					<thead>
					<tr>
						<th>ITEM</th>
						<th>UNIT</th>
						<th class="text-right">QTY</th>
						<th class="text-right">AMOUNT</th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($itemRows as $row) { ?>
						<tr>
							<td><?= esc($row['name']); ?></td>
							<td><?= esc($row['unit'] !== '' ? $row['unit'] : '—'); ?></td>
							<td class="text-right"><?= number_format((int) $row['qty']); ?></td>
							<td class="text-right"><?= number_format((int) $row['amount']); ?></td>
						</tr>
					<?php } ?>
					</tbody>
					<tfoot>
					<tr class="fr-balance-total">
						<td colspan="3"><strong>TOTAL</strong></td>
						<td class="text-right"><strong><?= number_format((int) ($desk['sales'] ?? 0)); ?></strong></td>
					</tr>
					</tfoot>
				</table>
			</div>
			<?php if ($itemRows === []) { ?><p class="text-muted" style="padding:12px 16px;">No items sold in this period.</p><?php } ?>
		</div>
		<?php } ?>

		<?php if ($canteenView === 'menu') { ?>
		<div class="fr-panel">
			<div class="fr-panel-head"><h3>Menu</h3></div>
			<div class="cn-add">
				<form method="post" action="<?= base_url('canteen_save'); ?>">
					<?= $keep('menu'); ?>
					<div class="cn-add-grid">
						<div>
							<label>Item</label>
							<input class="form-control" name="name" placeholder="Tea" required>
						</div>
						<div>
							<label>Unit</label>
							<select class="form-control" name="unit_id" required><?= $unitOptions(0); ?></select>
						</div>
						<div>
							<label>Price (Rwf)</label>
							<input class="form-control" name="price" type="number" min="1" placeholder="200" required>
						</div>
						<div>
							<label>Opening stock</label>
							<input class="form-control" name="stock" type="number" min="0" placeholder="0" required>
						</div>
						<div>
							<label>&nbsp;</label>
							<button class="btn btn-primary" type="submit">Add item</button>
						</div>
					</div>
				</form>
			</div>
			<div class="fr-table-wrap">
				<table class="table mb-0 cn-menu">
					<thead>
					<tr>
						<th>ITEM</th>
						<th>UNIT</th>
						<th>PRICE</th>
						<th>STOCK</th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($items as $item) {
						$formId = 'menu-item-' . (int) $item['id'];
						$deleteId = 'menu-delete-' . (int) $item['id'];
						?>
						<tr>
							<td><input class="form-control" form="<?= esc($formId, 'attr'); ?>" name="name" value="<?= esc($item['name'], 'attr'); ?>" required></td>
							<td><select class="form-control" form="<?= esc($formId, 'attr'); ?>" name="unit_id" required><?= $unitOptions($item['unit_id'] ?? 0); ?></select></td>
							<td style="width:120px;"><input class="form-control" form="<?= esc($formId, 'attr'); ?>" name="price" type="number" min="1" value="<?= (int) $item['price']; ?>" required></td>
							<td style="width:110px;"><input class="form-control" form="<?= esc($formId, 'attr'); ?>" name="stock" type="number" min="0" value="<?= (int) ($item['stock'] ?? 0); ?>" required></td>
							<td style="width:170px;">
								<div class="cn-actions">
									<button class="btn btn-success" type="submit" form="<?= esc($formId, 'attr'); ?>">Save</button>
									<button class="btn btn-outline-danger" type="submit" form="<?= esc($deleteId, 'attr'); ?>">Remove</button>
								</div>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
			<?php if ($items === []) { ?><p class="text-muted" style="padding:12px 16px;">No items yet. Add tea, milk, or whatever the canteen sells.</p><?php } ?>
		</div>
		<?php foreach ($items as $item) {
			$formId = 'menu-item-' . (int) $item['id'];
			$deleteId = 'menu-delete-' . (int) $item['id'];
			?>
			<form id="<?= esc($formId, 'attr'); ?>" method="post" action="<?= base_url('canteen_save'); ?>">
				<?= $keep('menu'); ?>
				<input type="hidden" name="id" value="<?= (int) $item['id']; ?>">
			</form>
			<form id="<?= esc($deleteId, 'attr'); ?>" method="post" action="<?= base_url('canteen_delete'); ?>" onsubmit="return confirm('Remove this item?');">
				<?= $keep('menu'); ?>
				<input type="hidden" name="id" value="<?= (int) $item['id']; ?>">
			</form>
		<?php } ?>
		<?php } ?>

		<?php if ($canteenView === 'units') { ?>
		<div class="fr-panel">
			<div class="fr-panel-head"><h3>Units</h3></div>
			<div class="cn-add">
				<form method="post" action="<?= base_url('canteen_unit_save'); ?>">
					<?= $keep('units'); ?>
					<div class="cn-add-grid" style="grid-template-columns: 2fr auto;">
						<div>
							<label>New unit</label>
							<input class="form-control" name="name" placeholder="Cup, plate, 1/2 L, 1.5 L" required maxlength="40">
						</div>
						<div>
							<label>&nbsp;</label>
							<button class="btn btn-primary" type="submit">Add unit</button>
						</div>
					</div>
				</form>
			</div>
			<div class="fr-table-wrap">
				<table class="table mb-0 cn-menu">
					<thead>
					<tr>
						<th>UNIT</th>
						<th style="width:180px;"></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($units as $unit) {
						$formId = 'unit-' . (int) $unit['id'];
						$deleteId = 'unit-delete-' . (int) $unit['id'];
						?>
						<tr>
							<td><input class="form-control" form="<?= esc($formId, 'attr'); ?>" name="name" value="<?= esc($unit['name'], 'attr'); ?>" required maxlength="40"></td>
							<td>
								<div class="cn-actions">
									<button class="btn btn-success" type="submit" form="<?= esc($formId, 'attr'); ?>">Save</button>
									<button class="btn btn-outline-danger" type="submit" form="<?= esc($deleteId, 'attr'); ?>">Remove</button>
								</div>
							</td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php foreach ($units as $unit) {
			$formId = 'unit-' . (int) $unit['id'];
			$deleteId = 'unit-delete-' . (int) $unit['id'];
			?>
			<form id="<?= esc($formId, 'attr'); ?>" method="post" action="<?= base_url('canteen_unit_save'); ?>">
				<?= $keep('units'); ?>
				<input type="hidden" name="id" value="<?= (int) $unit['id']; ?>">
			</form>
			<form id="<?= esc($deleteId, 'attr'); ?>" method="post" action="<?= base_url('canteen_unit_delete'); ?>" onsubmit="return confirm('Remove this unit?');">
				<?= $keep('units'); ?>
				<input type="hidden" name="id" value="<?= (int) $unit['id']; ?>">
			</form>
		<?php } ?>
		<?php } ?>
	</div>
</div>
<script>
(function () {
	var box = document.getElementById('canteenSearch');
	var table = document.getElementById('canteenInvoices');
	if (!table) return;
	var rows = table.querySelectorAll('tbody tr');
	var pills = document.getElementById('cnPills');
	var shown = document.getElementById('cnShown');
	var empty = document.getElementById('cnEmpty');
	var status = '';
	function money(n) {
		return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}
	function paintTotals() {
		var paidN = 0, openN = 0, paidAmt = 0, openAmt = 0;
		for (var i = 0; i < rows.length; i++) {
			var amt = parseInt(rows[i].getAttribute('data-amount') || '0', 10) || 0;
			if (rows[i].getAttribute('data-status') === 'paid') {
				paidN++;
				paidAmt += amt;
			} else {
				openN++;
				openAmt += amt;
			}
		}
		var paidCount = document.getElementById('cnPaidCount');
		var openCount = document.getElementById('cnUnpaidCount');
		var paidAmount = document.getElementById('cnPaidAmount');
		var openAmount = document.getElementById('cnUnpaidAmount');
		if (paidCount) paidCount.textContent = money(paidN);
		if (openCount) openCount.textContent = money(openN);
		if (paidAmount) paidAmount.textContent = money(paidAmt) + ' Rwf';
		if (openAmount) openAmount.textContent = money(openAmt) + ' Rwf';
	}
	function applyFilter() {
		var q = box ? box.value.toLowerCase().trim() : '';
		var visible = 0;
		for (var i = 0; i < rows.length; i++) {
			var hay = rows[i].getAttribute('data-search') || '';
			var st = rows[i].getAttribute('data-status') || '';
			var ok = (q === '' || hay.indexOf(q) !== -1) && (status === '' || st === status);
			rows[i].style.display = ok ? '' : 'none';
			if (ok) visible++;
		}
		if (shown) shown.textContent = String(visible);
		if (empty && rows.length) empty.hidden = visible !== 0;
	}
	if (box) box.addEventListener('input', applyFilter);
	if (pills) {
		pills.addEventListener('click', function (event) {
			var button = event.target.closest('button');
			if (!button) return;
			status = button.getAttribute('data-status') || '';
			var all = pills.querySelectorAll('button');
			for (var i = 0; i < all.length; i++) all[i].classList.toggle('is-on', all[i] === button);
			applyFilter();
		});
	}
	table.addEventListener('click', function (event) {
		var button = event.target.closest('.cn-mark');
		if (!button) return;
		var row = button.closest('tr');
		var nextPaid = button.getAttribute('data-paid') === '1' ? '0' : '1';
		button.disabled = true;
		var body = 'id=' + encodeURIComponent(row.getAttribute('data-id') || '') + '&paid=' + nextPaid;
		fetch(<?= json_encode(base_url('canteen_invoice_paid')); ?>, {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded'},
			body: body
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok || !data || data.ok !== true) {
					throw new Error((data && data.error) || 'Could not update this invoice.');
				}
				return data;
			});
		}).then(function (data) {
			var paid = !!data.paid;
			row.setAttribute('data-status', paid ? 'paid' : 'unpaid');
			row.classList.toggle('is-paid', paid);
			row.classList.toggle('is-unpaid', !paid);
			var badge = row.querySelector('.cn-badge');
			if (badge) {
				badge.textContent = paid ? 'Paid' : 'Unpaid';
				badge.classList.toggle('is-paid', paid);
				badge.classList.toggle('is-unpaid', !paid);
			}
			var note = row.querySelector('.cn-paid-note');
			if (note) {
				note.textContent = paid ? [data.paid_at || '', data.paid_by_name || ''].filter(Boolean).join(' · ') : '';
			}
			var hay = row.getAttribute('data-search') || '';
			hay = hay.replace(/\bpaid\b/g, '').replace(/\bunpaid\b/g, '').replace(/\s+/g, ' ').trim();
			row.setAttribute('data-search', hay + ' ' + (paid ? 'paid' : 'unpaid'));
			button.setAttribute('data-paid', paid ? '1' : '0');
			button.textContent = paid ? 'Mark unpaid' : 'Mark paid';
			paintTotals();
			applyFilter();
		}).catch(function (err) {
			window.alert(err.message || 'Could not update this invoice.');
		}).then(function () {
			button.disabled = false;
		});
	});
})();
</script>
