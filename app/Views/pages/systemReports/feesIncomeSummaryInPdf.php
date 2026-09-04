<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<title><?= esc($title ?? 'Summary of income'); ?></title>
<style>
	@page { size: A4 portrait; margin: 10mm; }
	body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #111; margin: 0; }
	.report-title {
		text-align: center;
		font-size: 13pt;
		font-weight: bold;
		background: #fce7f3;
		padding: 10px;
		margin-bottom: 8px;
		text-transform: uppercase;
		border: 1px solid #f9a8d4;
	}
	.kpi-row { margin-bottom: 10px; font-size: 9pt; }
	.kpi-row span {
		display: inline-block;
		margin-right: 10px;
		padding: 4px 8px;
		border-radius: 4px;
		font-weight: bold;
	}
	.kpi-due { background: #dbeafe; color: #1e3a8a; }
	.kpi-paid { background: #dcfce7; color: #166534; }
	.kpi-bal { background: #ffedd5; color: #9a3412; }
	.kpi-pct { background: #ede9fe; color: #5b21b6; }
	.meta { text-align: center; margin-bottom: 10px; font-size: 9pt; color: #444; }
	table.report { width: 100%; border-collapse: collapse; }
	table.report th, table.report td { border: 1px solid #64748b; padding: 5px 6px; }
	table.report th { font-weight: bold; text-align: center; font-size: 8.5pt; }
	th.col-class { background: #fecaca; color: #7f1d1d; }
	th.col-due { background: #93c5fd; color: #1e3a8a; }
	th.col-paid { background: #fdba74; color: #9a3412; }
	th.col-bal { background: #fca5a5; color: #991b1b; }
	th.col-pct { background: #86efac; color: #14532d; }
	.text-right { text-align: right; }
	.section-head td {
		font-weight: bold;
		text-transform: uppercase;
		background: #f1f5f9;
		color: #334155;
		font-size: 9pt;
	}
	.fr-income-subtotal td { font-weight: bold; color: #fff; }
	.fr-section-nursery.fr-income-subtotal td:first-child { background: #374151; }
	.fr-section-primary.fr-income-subtotal td:first-child,
	.fr-section-reb.fr-income-subtotal td:first-child { background: #0891b2; }
	.fr-section-rtb.fr-income-subtotal td:first-child { background: #ea580c; }
	.fr-income-subtotal td:nth-child(n+2) { background: #fef08a !important; color: #713f12 !important; }
	.fr-section-reb .fr-income-row td:first-child { background: #ecfeff; font-weight: bold; }
	.fr-section-rtb .fr-income-row td:first-child { background: #fff7ed; font-weight: bold; }
	.pct-full { color: #15803d; font-weight: bold; }
	.pct-good { color: #1d4ed8; font-weight: bold; }
	.pct-partial { color: #c2410c; font-weight: bold; }
	.pct-zero { color: #b91c1c; font-weight: bold; }
	.bal-warn { color: #b91c1c; font-weight: bold; }
	.footer { margin-top: 10px; font-size: 8pt; color: #64748b; text-align: right; }
</style>
</head>
<body>
<?php
$termTitle = esc($termLabel ?? '');
$yearTitle = esc($selectedYearTitle ?? '');
$stats = $stats ?? [];
?>
<div class="report-title">SUMMARY OF INCOME — <?= $termTitle; ?> · <?= $yearTitle; ?></div>
<div class="meta">
	<?= esc($school_name ?? ''); ?>
	<?php if (($feesScope ?? 'both') === 'school') : ?> · School fees only
	<?php elseif (($feesScope ?? 'both') === 'extra') : ?> · Extra fees only
	<?php endif; ?>
</div>
<div class="kpi-row">
	<span class="kpi-due">Due: <?= number_format((float) ($stats['total_expected'] ?? 0)); ?></span>
	<span class="kpi-paid">Paid: <?= number_format((float) ($stats['total_paid'] ?? 0)); ?></span>
	<span class="kpi-bal">Balance: <?= number_format((float) ($stats['total_remain'] ?? 0)); ?></span>
	<span class="kpi-pct">Rate: <?= esc(\App\Libraries\FeesReportHelper::formatPercent((float) ($stats['collection_rate'] ?? 0))); ?></span>
</div>
<table class="report">
	<thead>
	<tr>
		<th class="col-class">CLASS</th>
		<th class="col-due">TOTAL DUE</th>
		<th class="col-paid">TOTAL PAID</th>
		<th class="col-bal">BALANCE</th>
		<th class="col-pct">PERCENTAGES</th>
	</tr>
	</thead>
	<tbody>
	<?php
	$lastSection = '';
	foreach (($incomeSummary ?? []) as $row) :
		$isSub = !empty($row['is_subtotal']);
		$section = (string) ($row['section'] ?? '');
		if (!$isSub && $section !== '' && $section !== $lastSection) :
			$lastSection = $section;
			?>
			<tr class="section-head">
				<td colspan="5"><?= esc(\App\Libraries\FeesReportHelper::sectionHeadingLabel($section)); ?></td>
			</tr>
		<?php endif;
		$pct = (float) ($row['percent'] ?? 0);
		$balance = max(0, (float) ($row['balance'] ?? 0));
		$pctLevel = \App\Libraries\FeesReportHelper::percentLevel($pct);
		$rowClass = ($isSub ? 'fr-income-subtotal' : 'fr-income-row') . ' fr-section-' . $section;
		?>
		<tr class="<?= esc($rowClass, 'attr'); ?>">
			<td><?= esc($row['class_label'] ?? ''); ?></td>
			<td class="text-right"><?= number_format((float) ($row['total_due'] ?? 0)); ?></td>
			<td class="text-right"><?= number_format((float) ($row['total_paid'] ?? 0)); ?></td>
			<td class="text-right<?= $balance > 0 ? ' bal-warn' : ''; ?>"><?= number_format($balance); ?></td>
			<td class="text-right pct-<?= esc($pctLevel, 'attr'); ?>"><?= esc(\App\Libraries\FeesReportHelper::formatPercent($pct)); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<div class="footer">Printed: <?= date('d/m/Y H:i'); ?></div>
</body>
</html>
