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
<link rel="stylesheet" href="<?= base_url('assets/css/canteen.css'); ?>?v=2">
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
			<p><?= $canteenView === 'menu' ? 'What the till sells, with a unit and a stock count.' : ($canteenView === 'units' ? 'Measures used on the menu, such as cup or 1.5 L.' : 'One card for each selling day in ' . esc($label) . '. A card is paid only when that day’s full amount is paid.'); ?></p>
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
		$dayCards = $desk['dayCards'] ?? [];
		$canSettle = !empty($canSettleCanteen);
		?>
		<div class="cn-kpis">
			<div class="cn-kpi">
				<span>Sales</span>
				<strong><?= number_format((int) ($desk['sales'] ?? 0)); ?></strong>
				<small>Rwf in this period</small>
			</div>
			<div class="cn-kpi cn-kpi-open">
				<span>Unpaid days</span>
				<strong id="cnUnpaidCount"><?= number_format((int) ($desk['unpaidCount'] ?? 0)); ?></strong>
				<small id="cnUnpaidAmount"><?= number_format((int) ($desk['unpaidSales'] ?? 0)); ?> Rwf</small>
			</div>
			<div class="cn-kpi cn-kpi-paid">
				<span>Paid days</span>
				<strong id="cnPaidCount"><?= number_format((int) ($desk['paidCount'] ?? 0)); ?></strong>
				<small id="cnPaidAmount"><?= number_format((int) ($desk['paidSales'] ?? 0)); ?> Rwf</small>
			</div>
			<div class="cn-kpi">
				<span>Day cards</span>
				<strong><?= number_format((int) ($desk['dayCount'] ?? count($dayCards))); ?></strong>
				<small><?= number_format((int) ($desk['invoiceCount'] ?? 0)); ?> sales</small>
			</div>
		</div>

		<div class="cn-tools">
			<input type="search" class="form-control" id="canteenSearch" placeholder="Find a day or a student inside a card" autocomplete="off">
			<div class="cn-pills" id="cnPills">
				<button type="button" class="is-on" data-status="">All</button>
				<button type="button" data-status="unpaid">Unpaid</button>
				<button type="button" data-status="paid">Paid</button>
			</div>
		</div>
		<p class="cn-hint">Showing <span id="cnShown"><?= count($dayCards); ?></span> of <?= count($dayCards); ?> day cards.<?= $canSettle ? ' Mark a card paid only after the whole day is paid.' : ' The Director of Finance marks a card paid.'; ?></p>

		<div class="cn-days" id="cnDays">
			<?php foreach ($dayCards as $card) :
				$paid = !empty($card['paid']);
				$paidAt = strtotime((string) ($card['paid_at'] ?? ''));
				$paidLabel = $paidAt ? date('d M Y H:i', $paidAt) : '';
				$by = trim((string) ($card['paid_by_name'] ?? ''));
				$searchBits = [$card['label'], $card['date'], $paid ? 'paid' : 'unpaid'];
				foreach ($card['lines'] as $line) {
					$searchBits[] = $line['name'] . ' ' . $line['regno'] . ' ' . $line['class_name'] . ' ' . $line['items'];
				}
				$export = base_url('canteen') . '?' . http_build_query(['export' => 'pdf', 'day' => $card['date']]);
				$excel = base_url('canteen') . '?' . http_build_query(['export' => 'excel', 'day' => $card['date']]);
				?>
				<article class="cn-day <?= $paid ? 'is-paid' : 'is-unpaid'; ?>" data-status="<?= $paid ? 'paid' : 'unpaid'; ?>" data-day="<?= esc($card['date'], 'attr'); ?>" data-amount="<?= (int) $card['amount']; ?>" data-search="<?= esc(strtolower(implode(' ', $searchBits)), 'attr'); ?>">
					<header class="cn-day-head">
						<div>
							<div class="cn-day-date"><?= esc($card['label']); ?></div>
							<div class="cn-meta"><?= (int) $card['sales']; ?> sales · <?= (int) $card['students']; ?> students</div>
						</div>
						<div class="cn-day-total">
							<strong><?= number_format((int) $card['amount']); ?> <small>Rwf</small></strong>
							<span class="cn-badge <?= $paid ? 'is-paid' : 'is-unpaid'; ?>"><?= $paid ? 'Paid' : 'Unpaid'; ?></span>
						</div>
					</header>
					<div class="cn-day-actions">
						<a class="btn btn-danger btn-sm" href="<?= esc($export); ?>" target="_blank">PDF this card</a>
						<a class="btn btn-success btn-sm" href="<?= esc($excel); ?>">Excel this card</a>
						<?php if ($canSettle) { ?>
							<button type="button" class="cn-mark" data-paid="<?= $paid ? '1' : '0'; ?>"><?= $paid ? 'Mark unpaid' : 'Mark whole day paid'; ?></button>
						<?php } ?>
						<div class="cn-paid-note"><?php
							if ($paid) {
								echo esc(trim($paidLabel . ($by !== '' ? ' · ' . $by : '')));
							} elseif ((int) ($card['settled_amount'] ?? 0) > 0) {
								echo 'An earlier mark was ' . number_format((int) $card['settled_amount']) . ' Rwf. This day is unpaid until the full ' . number_format((int) $card['amount']) . ' Rwf is marked.';
							}
						?></div>
					</div>
					<div class="fr-table-wrap">
						<table class="table mb-0 cn-day-lines">
							<thead>
							<tr>
								<th>TIME</th>
								<th>STUDENT</th>
								<th>WHAT THEY TOOK</th>
								<th class="text-right">AMOUNT</th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($card['lines'] as $line) :
								$when = strtotime((string) ($line['at'] ?? ''));
								?>
								<tr>
									<td><?= $when ? esc(date('H:i', $when)) : ''; ?></td>
									<td>
										<div class="cn-name"><?= esc($line['name']); ?></div>
										<div class="cn-meta"><?= esc($line['class_name'] !== '' ? $line['class_name'] : '—'); ?> · <?= esc($line['regno']); ?></div>
									</td>
									<td><?= esc($line['items'] !== '' ? $line['items'] : '—'); ?></td>
									<td class="text-right cn-amount"><?= number_format((int) $line['amount']); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
							<tfoot>
							<tr class="fr-balance-total">
								<td colspan="3"><strong>Day total</strong></td>
								<td class="text-right"><strong><?= number_format((int) $card['amount']); ?></strong></td>
							</tr>
							</tfoot>
						</table>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<p class="cn-empty" id="cnEmpty"<?= $dayCards === [] ? '' : ' hidden'; ?>><?= $dayCards === [] ? 'No card sales in this period.' : 'No day card matches that search.'; ?></p>
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
	var list = document.getElementById('cnDays');
	if (!list) return;
	var cards = list.querySelectorAll('.cn-day');
	var pills = document.getElementById('cnPills');
	var shown = document.getElementById('cnShown');
	var empty = document.getElementById('cnEmpty');
	var status = '';
	function money(n) {
		return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}
	function paintTotals() {
		var paidN = 0, openN = 0, paidAmt = 0, openAmt = 0;
		for (var i = 0; i < cards.length; i++) {
			var amt = parseInt(cards[i].getAttribute('data-amount') || '0', 10) || 0;
			if (cards[i].getAttribute('data-status') === 'paid') {
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
		for (var i = 0; i < cards.length; i++) {
			var hay = cards[i].getAttribute('data-search') || '';
			var st = cards[i].getAttribute('data-status') || '';
			var ok = (q === '' || hay.indexOf(q) !== -1) && (status === '' || st === status);
			cards[i].style.display = ok ? '' : 'none';
			if (ok) visible++;
		}
		if (shown) shown.textContent = String(visible);
		if (empty && cards.length) empty.hidden = visible !== 0;
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
	list.addEventListener('click', function (event) {
		var button = event.target.closest('.cn-mark');
		if (!button) return;
		var card = button.closest('.cn-day');
		var nextPaid = button.getAttribute('data-paid') === '1' ? '0' : '1';
		if (nextPaid === '1' && !window.confirm('Mark this whole day paid for ' + money(parseInt(card.getAttribute('data-amount') || '0', 10)) + ' Rwf?')) {
			return;
		}
		button.disabled = true;
		var body = 'day=' + encodeURIComponent(card.getAttribute('data-day') || '') + '&paid=' + nextPaid;
		fetch(<?= json_encode(base_url('canteen_day_paid')); ?>, {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded'},
			body: body
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok || !data || data.ok !== true) {
					throw new Error((data && data.error) || 'Could not update this day card.');
				}
				return data;
			});
		}).then(function (data) {
			var paid = !!data.paid;
			card.setAttribute('data-status', paid ? 'paid' : 'unpaid');
			card.classList.toggle('is-paid', paid);
			card.classList.toggle('is-unpaid', !paid);
			var badge = card.querySelector('.cn-badge');
			if (badge) {
				badge.textContent = paid ? 'Paid' : 'Unpaid';
				badge.classList.toggle('is-paid', paid);
				badge.classList.toggle('is-unpaid', !paid);
			}
			var note = card.querySelector('.cn-paid-note');
			if (note) {
				note.textContent = paid ? [data.paid_at || '', data.paid_by_name || ''].filter(Boolean).join(' · ') : '';
			}
			button.setAttribute('data-paid', paid ? '1' : '0');
			button.textContent = paid ? 'Mark unpaid' : 'Mark whole day paid';
			paintTotals();
			applyFilter();
		}).catch(function (err) {
			window.alert(err.message || 'Could not update this day card.');
		}).then(function () {
			button.disabled = false;
		});
	});
})();
</script>
