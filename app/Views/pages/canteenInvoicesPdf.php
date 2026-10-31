<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<title><?= esc($title ?? 'Canteen invoices'); ?></title>
<style>
	@page { size: A4 portrait; margin: 8mm; }
	body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #111; margin: 0; }
	.header-box { border: 1.5px solid #1e3a5f; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px; overflow: hidden; }
	.header-left { float: left; width: 58%; }
	.header-right { float: right; width: 40%; text-align: right; }
	.gov { font-size: 8.5pt; line-height: 1.35; }
	.school-name { font-size: 13pt; font-weight: bold; color: #0f172a; margin: 4px 0 2px; }
	.school-meta { font-size: 8pt; color: #334155; line-height: 1.4; }
	.logo { max-height: 68px; max-width: 100px; margin: 4px 0; }
	.clear { clear: both; }
	.report-title { text-align: center; font-size: 13pt; font-weight: bold; margin: 6px 0 8px; text-transform: uppercase; letter-spacing: 0.4px; }
	.kpi-row { margin-bottom: 10px; font-size: 8.5pt; }
	.kpi-row span { display: inline-block; margin: 0 8px 6px 0; padding: 3px 7px; border: 1px solid #cbd5e1; border-radius: 3px; background: #f8fafc; }
	h3 { font-size: 11pt; margin: 12px 0 4px; color: #0f172a; }
	.student-head { background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 8px; margin-top: 10px; }
	.student-head strong { font-size: 11pt; }
	.student-meta { font-size: 8.5pt; color: #334155; }
	table.report { width: 100%; border-collapse: collapse; margin-top: 4px; }
	table.report th, table.report td { border: 1px solid #64748b; padding: 4px 5px; font-size: 8.5pt; vertical-align: top; }
	table.report th { background: #e2e8f0; font-weight: bold; text-align: center; }
	.text-right { text-align: right; }
	.total td { background: #f1f5f9; font-weight: bold; }
	.grand { margin-top: 14px; border: 1.5px solid #1e3a5f; padding: 8px 10px; }
	.grand strong { font-size: 12pt; }
	.footer { margin-top: 10px; font-size: 8pt; color: #64748b; overflow: hidden; }
	.footer .left { float: left; }
	.footer .right { float: right; }
	.avoid { page-break-inside: avoid; }
</style>
</head>
<body>
<?php
$desk = $desk ?? [];
$studentRows = $desk['studentRows'] ?? [];
$itemRows = $desk['itemRows'] ?? [];
$itemAmount = 0;
foreach ($itemRows as $item) {
	$itemAmount += (int) ($item['amount'] ?? 0);
}
?>
<div class="header-box">
	<div class="header-left">
		<div class="gov">
			<strong><?= esc(lang('app.republic')); ?></strong><br>
			<strong><?= esc(lang('app.ministry')); ?></strong>
		</div>
		<div class="school-name"><?= esc($school_name ?? ''); ?></div>
		<?php if (!empty($school_logo)) : ?>
			<img class="logo" src="<?= base_url('assets/images/logo/' . $school_logo); ?>" alt="Logo">
		<?php endif; ?>
		<div class="school-meta">
			<?php if (!empty($school_moto)) : ?><div><em><?= esc($school_moto); ?></em></div><?php endif; ?>
			<?php if (!empty($school_address)) : ?><div><?= esc($school_address); ?></div><?php endif; ?>
			<?php if (!empty($school_pobox)) : ?><div>P.O. Box: <?= esc($school_pobox); ?></div><?php endif; ?>
			<div>
				<?php if (!empty($school_phone)) : ?><strong>Phone:</strong> <?= esc($school_phone); ?><?php endif; ?>
				<?php if (!empty($school_email)) : ?> &nbsp; <strong>Email:</strong> <?= esc($school_email); ?><?php endif; ?>
			</div>
		</div>
	</div>
	<div class="header-right">
		<?php if (!empty($academic_year_title)) : ?><div><strong>Academic year:</strong> <?= esc($academic_year_title); ?></div><?php endif; ?>
		<div><strong>Period:</strong> <?= esc($desk['label'] ?? ''); ?></div>
		<div><strong>From:</strong> <?= esc($desk['from'] ?? ''); ?></div>
		<div><strong>To:</strong> <?= esc($desk['to'] ?? ''); ?></div>
		<div><strong>Students:</strong> <?= (int) ($desk['students'] ?? 0); ?></div>
		<div><strong>Invoices:</strong> <?= (int) ($desk['invoiceCount'] ?? 0); ?></div>
		<?php if (!empty($head_master)) : ?><div><strong>Head teacher:</strong> <?= esc($head_master); ?></div><?php endif; ?>
		<div><strong>Printed:</strong> <?= date('d M Y H:i'); ?></div>
	</div>
	<div class="clear"></div>
</div>

<div class="report-title">Canteen invoices</div>

<div class="kpi-row">
	<span><strong>Sales:</strong> <?= number_format((int) ($desk['sales'] ?? 0)); ?> Rwf</span>
	<span><strong>Unpaid:</strong> <?= number_format((int) ($desk['unpaidCount'] ?? 0)); ?> · <?= number_format((int) ($desk['unpaidSales'] ?? 0)); ?> Rwf</span>
	<span><strong>Paid:</strong> <?= number_format((int) ($desk['paidCount'] ?? 0)); ?> · <?= number_format((int) ($desk['paidSales'] ?? 0)); ?> Rwf</span>
	<span><strong>Invoices:</strong> <?= number_format((int) ($desk['invoiceCount'] ?? 0)); ?></span>
	<span><strong>Students:</strong> <?= number_format((int) ($desk['students'] ?? 0)); ?></span>
</div>

<h3>Totals by item</h3>
<table class="report">
	<thead>
	<tr>
		<th>Item</th>
		<th>Unit</th>
		<th>Quantity</th>
		<th>Amount (Rwf)</th>
	</tr>
	</thead>
	<tbody>
	<?php if ($itemRows === []) : ?>
		<tr><td colspan="4">No items sold in this period.</td></tr>
	<?php else : foreach ($itemRows as $item) : ?>
		<tr>
			<td><?= esc($item['name']); ?></td>
			<td><?= esc($item['unit'] !== '' ? $item['unit'] : '—'); ?></td>
			<td class="text-right"><?= number_format((int) $item['qty']); ?></td>
			<td class="text-right"><?= number_format((int) $item['amount']); ?></td>
		</tr>
	<?php endforeach; endif; ?>
	</tbody>
	<tfoot>
	<tr class="total">
		<td colspan="3">TOTAL</td>
		<td class="text-right"><?= number_format((int) ($desk['sales'] ?? $itemAmount)); ?></td>
	</tr>
	</tfoot>
</table>

<h3>Each invoice</h3>
<?php $invoiceRows = $desk['invoiceRows'] ?? []; ?>
<table class="report">
	<thead>
	<tr>
		<th>Invoice</th>
		<th>When</th>
		<th>Student</th>
		<th>Class</th>
		<th>What they took</th>
		<th>Amount</th>
		<th>Left on card</th>
		<th>Status</th>
	</tr>
	</thead>
	<tbody>
	<?php if ($invoiceRows === []) : ?>
		<tr><td colspan="8">No card sales in this period.</td></tr>
	<?php else : foreach ($invoiceRows as $invoice) :
		$when = strtotime((string) ($invoice['at'] ?? ''));
		?>
		<tr>
			<td>#<?= (int) ($invoice['id'] ?? 0); ?></td>
			<td><?= $when ? esc(date('d M Y H:i', $when)) : ''; ?></td>
			<td><?= esc($invoice['name'] ?? ''); ?><br><span class="student-meta"><?= esc($invoice['regno'] ?? ''); ?></span></td>
			<td><?= esc(($invoice['class_name'] ?? '') !== '' ? $invoice['class_name'] : '—'); ?></td>
			<td><?= esc(($invoice['items'] ?? '') !== '' ? $invoice['items'] : '—'); ?></td>
			<td class="text-right"><?= number_format((int) ($invoice['amount'] ?? 0)); ?></td>
			<td class="text-right"><?= number_format((int) ($invoice['balance'] ?? 0)); ?></td>
			<td><?= !empty($invoice['paid']) ? 'Paid' : 'Unpaid'; ?></td>
		</tr>
	<?php endforeach; endif; ?>
	</tbody>
	<tfoot>
	<tr class="total">
		<td colspan="5">TOTAL</td>
		<td class="text-right"><?= number_format((int) ($desk['sales'] ?? 0)); ?></td>
		<td colspan="2"><?= (int) ($desk['unpaidCount'] ?? 0); ?> unpaid · <?= (int) ($desk['paidCount'] ?? 0); ?> paid</td>
	</tr>
	</tfoot>
</table>

<div class="grand">
	<strong>Grand total: <?= number_format((int) ($desk['sales'] ?? 0)); ?> Rwf</strong><br>
	<?= (int) ($desk['students'] ?? 0); ?> students
	· <?= (int) ($desk['invoiceCount'] ?? 0); ?> invoices
	· <?= number_format((int) ($desk['portions'] ?? 0)); ?> portions
	· <?= esc($desk['label'] ?? ''); ?>
</div>

<div class="footer">
	<div class="left"><?= esc($school_name ?? ''); ?> · Canteen invoices</div>
	<div class="right">Printed <?= date('d M Y H:i'); ?></div>
	<div class="clear"></div>
</div>
</body>
</html>
