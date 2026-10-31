<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<title><?= esc($title ?? 'Canteen day card'); ?></title>
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
	.report-title { text-align: center; font-size: 13pt; font-weight: bold; margin: 6px 0 8px; text-transform: uppercase; }
	.kpi-row { margin-bottom: 10px; font-size: 8.5pt; }
	.kpi-row span { display: inline-block; margin: 0 8px 6px 0; padding: 3px 7px; border: 1px solid #cbd5e1; border-radius: 3px; background: #f8fafc; }
	table.report { width: 100%; border-collapse: collapse; margin-top: 4px; }
	table.report th, table.report td { border: 1px solid #64748b; padding: 4px 5px; font-size: 8.5pt; vertical-align: top; }
	table.report th { background: #e2e8f0; font-weight: bold; text-align: center; }
	.text-right { text-align: right; }
	.total td { background: #f1f5f9; font-weight: bold; }
	.footer { margin-top: 10px; font-size: 8pt; color: #64748b; overflow: hidden; }
	.footer .left { float: left; }
	.footer .right { float: right; }
</style>
</head>
<body>
<?php
$card = $card ?? [];
$lines = $card['lines'] ?? [];
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
			<?php if (!empty($school_address)) : ?><div><?= esc($school_address); ?></div><?php endif; ?>
			<?php if (!empty($school_phone)) : ?><div><strong>Phone:</strong> <?= esc($school_phone); ?></div><?php endif; ?>
		</div>
	</div>
	<div class="header-right">
		<div><strong>One day card</strong></div>
		<div><strong>Day:</strong> <?= esc($card['label'] ?? ''); ?></div>
		<div><strong>Status:</strong> <?= !empty($card['paid']) ? 'Paid' : 'Unpaid'; ?></div>
		<div><strong>Sales:</strong> <?= (int) ($card['sales'] ?? 0); ?></div>
		<div><strong>Students:</strong> <?= (int) ($card['students'] ?? 0); ?></div>
		<div><strong>Printed:</strong> <?= date('d M Y H:i'); ?></div>
	</div>
	<div class="clear"></div>
</div>

<div class="report-title">Canteen card · <?= esc($card['label'] ?? ''); ?></div>
<div class="kpi-row">
	<span><strong>Day total:</strong> <?= number_format((int) ($card['amount'] ?? 0)); ?> Rwf</span>
	<span><strong><?= !empty($card['paid']) ? 'Paid' : 'Unpaid'; ?></strong></span>
</div>

<table class="report">
	<thead>
	<tr>
		<th>When</th>
		<th>Student</th>
		<th>Class</th>
		<th>Reg no</th>
		<th>What they took</th>
		<th>Amount</th>
	</tr>
	</thead>
	<tbody>
	<?php if ($lines === []) : ?>
		<tr><td colspan="6">No sales on this day.</td></tr>
	<?php else : foreach ($lines as $line) :
		$when = strtotime((string) ($line['at'] ?? ''));
		?>
		<tr>
			<td><?= $when ? esc(date('d M Y H:i', $when)) : ''; ?></td>
			<td><?= esc($line['name'] ?? ''); ?></td>
			<td><?= esc(($line['class_name'] ?? '') !== '' ? $line['class_name'] : '—'); ?></td>
			<td><?= esc($line['regno'] ?? ''); ?></td>
			<td><?= esc(($line['items'] ?? '') !== '' ? $line['items'] : '—'); ?></td>
			<td class="text-right"><?= number_format((int) ($line['amount'] ?? 0)); ?></td>
		</tr>
	<?php endforeach; endif; ?>
	</tbody>
	<tfoot>
	<tr class="total">
		<td colspan="5">DAY TOTAL</td>
		<td class="text-right"><?= number_format((int) ($card['amount'] ?? 0)); ?></td>
	</tr>
	</tfoot>
</table>

<div class="footer">
	<div class="left"><?= esc($school_name ?? ''); ?> · One canteen day card</div>
	<div class="right">Printed <?= date('d M Y H:i'); ?></div>
	<div class="clear"></div>
</div>
</body>
</html>
