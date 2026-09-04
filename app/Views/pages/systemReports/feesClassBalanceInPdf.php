<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<title><?= esc($title ?? 'Class balance list'); ?></title>
<style>
	@page { size: A4 portrait; margin: 10mm; }
	body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #111; margin: 0; }
	.report-title {
		text-align: center;
		font-size: 13pt;
		font-weight: bold;
		background: #fce7f3;
		padding: 8px;
		margin-bottom: 10px;
		text-transform: uppercase;
	}
	.meta { text-align: center; margin-bottom: 10px; font-size: 9pt; color: #444; }
	table.report { width: 100%; border-collapse: collapse; }
	table.report th, table.report td { border: 1px solid #666; padding: 5px 6px; }
	table.report th { font-weight: bold; text-align: center; }
	.fr-th-no, .fr-th-names, .fr-th-dates { background: #fecaca; }
	.fr-th-due { background: #93c5fd; }
	.fr-th-paid { background: #fdba74; }
	.fr-th-bal { background: #fca5a5; }
	.text-right { text-align: right; }
	.fr-balance-name { text-transform: uppercase; font-weight: 600; }
	tfoot td { font-weight: bold; background: #f1f5f9; }
	.footer { margin-top: 10px; font-size: 8pt; color: #64748b; text-align: right; }
</style>
</head>
<body>
<div class="report-title"><?= esc($school_name ?? ''); ?> — Class balance list</div>
<div class="meta">
	<strong><?= esc($classLabel ?? ''); ?></strong> · <?= esc($selectedYearTitle ?? ''); ?> · <?= esc($termLabel ?? ''); ?>
	<?php if (($feesScope ?? 'both') === 'school') : ?> · School fees only
	<?php elseif (($feesScope ?? 'both') === 'extra') : ?> · Extra fees only
	<?php endif; ?>
</div>
<table class="report">
	<thead>
	<tr>
		<th class="fr-th-no">NO</th>
		<th class="fr-th-names">NAMES</th>
		<th class="fr-th-dates">DATES</th>
		<th class="fr-th-due">TOTAL FEES DUE</th>
		<th class="fr-th-paid">TOTAL PAID</th>
		<th class="fr-th-bal">BALANCES</th>
	</tr>
	</thead>
	<tbody>
	<?php $a = 1; foreach (($students ?? []) as $student) :
		$norm = \App\Libraries\FeesReportHelper::normalizeCollectionAmounts(
			(float) ($student['amount'] ?? 0),
			(float) ($student['paid'] ?? 0)
		);
		$amt = $norm['due'];
		$paid = $norm['paid'];
		$balance = $norm['balance'];
		$dateStr = \App\Libraries\FeesReportHelper::formatPaymentDate($student['last_payment'] ?? '');
		?>
		<tr>
			<td class="text-center"><?= $a++; ?></td>
			<td class="fr-balance-name"><?= esc(strtoupper($student['student'] ?? '')); ?></td>
			<td class="text-center"><?= $dateStr !== '' ? esc($dateStr) : '—'; ?></td>
			<td class="text-right"><?= number_format($amt); ?></td>
			<td class="text-right"><?= number_format($paid); ?></td>
			<td class="text-right"><?= number_format($balance); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
	<tfoot>
	<tr>
		<td colspan="3">TOTAL</td>
		<td class="text-right"><?= number_format((float) ($stats['total_expected'] ?? 0)); ?></td>
		<td class="text-right"><?= number_format((float) ($stats['total_paid'] ?? 0)); ?></td>
		<td class="text-right"><?= number_format((float) ($stats['total_remain'] ?? 0)); ?></td>
	</tr>
	</tfoot>
</table>
<div class="footer">Printed: <?= date('d/m/Y H:i'); ?></div>
</body>
</html>
