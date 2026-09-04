<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<title><?= esc($title ?? 'Fees report'); ?></title>
<style>
	@page { size: A4 landscape; margin: 8mm; }
	body {
		font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
		font-size: 9pt;
		color: #111;
		margin: 0;
		padding: 0;
	}
	.header-box {
		border: 1.5px solid #1e3a5f;
		border-radius: 4px;
		padding: 8px 10px;
		margin-bottom: 8px;
		overflow: hidden;
	}
	.header-left { float: left; width: 48%; }
	.header-right { float: right; width: 48%; text-align: right; }
	.header-left .gov { font-size: 8.5pt; line-height: 1.35; }
	.school-name {
		font-size: 13pt;
		font-weight: bold;
		color: #0f172a;
		margin: 4px 0 2px;
	}
	.school-meta { font-size: 8pt; color: #334155; line-height: 1.4; }
	.logo {
		max-height: 72px;
		max-width: 110px;
		margin: 4px 0;
	}
	.clear { clear: both; }
	.report-title {
		text-align: center;
		font-size: 12pt;
		font-weight: bold;
		margin: 4px 0 8px;
		text-transform: uppercase;
		letter-spacing: 0.4px;
	}
	.kpi-row {
		margin-bottom: 8px;
		font-size: 8pt;
	}
	.kpi-row span {
		display: inline-block;
		margin-right: 14px;
		padding: 2px 6px;
		border: 1px solid #cbd5e1;
		border-radius: 3px;
		background: #f8fafc;
	}
	table.report {
		width: 100%;
		border-collapse: collapse;
		table-layout: auto;
	}
	table.report th, table.report td {
		border: 1px solid #64748b;
		padding: 3px 4px;
		font-size: 7.5pt;
		vertical-align: top;
	}
	table.report th {
		background: #e2e8f0;
		font-weight: bold;
		text-align: center;
		font-size: 7pt;
	}
	.text-right { text-align: right; }
	.text-center { text-align: center; }
	.extra-head { background: #ede9fe !important; color: #5b21b6; }
	.badge {
		display: inline-block;
		border: 1px solid #777;
		border-radius: 3px;
		padding: 1px 4px;
		font-size: 7pt;
		font-weight: bold;
	}
	.badge-full { color: #15803d; border-color: #86efac; }
	.badge-partial { color: #b45309; border-color: #fcd34d; }
	.badge-zero { color: #b91c1c; border-color: #fca5a5; }
	.badge-over { color: #a16207; border-color: #fde68a; }
	.footer {
		margin-top: 8px;
		font-size: 7.5pt;
		color: #64748b;
		overflow: hidden;
	}
	.footer .left { float: left; }
	.footer .right { float: right; }
	.paid-sub { color: #15803d; font-size: 6.5pt; }
	.unpaid-sub { color: #b91c1c; font-size: 6.5pt; font-weight: bold; }
	.cell-unpaid { background: #fff1f2; }
	.cell-partial { background: #fff7ed; }
	.cell-paid { background: #f0fdf4; }
</style>
</head>
<body>
<?php
$extraCols = $extraFeeColumns ?? [];
$stats = $stats ?? [];
$feesScope = $feesScope ?? 'both';
$showSchoolFees = ($feesScope !== 'extra');
$showExtraFees = ($feesScope !== 'school');
if (!$showExtraFees) {
	$extraCols = [];
}
$feesScopeLabel = '';
if ($feesScope === 'school') {
	$feesScopeLabel = 'School fees only';
} elseif ($feesScope === 'extra') {
	$feesScopeLabel = 'Extra fees only';
}
$classeRow = is_array($classe ?? null) ? $classe : [];
$classMentor = trim((string) ($classeRow['mentor_name'] ?? ''));
$className = esc(!empty($classLabel) ? $classLabel : trim(($classeRow['level_name'] ?? '') . ' ' . ($classeRow['dept_code'] ?? '') . ' ' . ($classeRow['title'] ?? '')));

if (!function_exists('fr_pdf_status')) {
	function fr_pdf_status($amount, $paid): array
	{
		$amount = (float) $amount;
		$paid = (float) $paid;
		if ($paid > $amount && $amount > 0) {
			return ['label' => 'Overpay', 'class' => 'badge-over', 'remain' => 0];
		}
		if ($amount > 0 && $paid >= $amount) {
			return ['label' => 'Full paid', 'class' => 'badge-full', 'remain' => 0];
		}
		if ($paid > 0) {
			return ['label' => 'Partial', 'class' => 'badge-partial', 'remain' => $amount - $paid];
		}
		return ['label' => 'Zero payment', 'class' => 'badge-zero', 'remain' => $amount];
	}
}

if (!function_exists('fr_pdf_fee_cell')) {
	function fr_pdf_fee_cell(float $expected, float $paid): array
	{
		if ($expected <= 0 && $paid <= 0) {
			return ['html' => '—', 'class' => ''];
		}
		$unpaid = max(0, $expected - $paid);
		$class = '';
		$html = number_format($expected);
		if ($expected > 0 && $unpaid <= 0) {
			$class = 'cell-paid';
			$html .= '<div class="paid-sub">paid ' . number_format($paid) . '</div>';
		} elseif ($paid > 0 && $unpaid > 0) {
			$class = 'cell-partial';
			$html .= '<div class="paid-sub">paid ' . number_format($paid) . '</div>';
			$html .= '<div class="unpaid-sub">unpaid ' . number_format($unpaid) . '</div>';
		} elseif ($unpaid > 0) {
			$class = 'cell-unpaid';
			$html .= '<div class="unpaid-sub">unpaid ' . number_format($unpaid) . '</div>';
		}
		return ['html' => $html, 'class' => $class];
	}
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
				<?php if (!empty($school_phone)) : ?><strong><?= esc(lang('app.phone')); ?>:</strong> <?= esc($school_phone); ?><?php endif; ?>
				<?php if (!empty($school_email)) : ?> &nbsp; <strong><?= esc(lang('app.mail')); ?>:</strong> <?= esc($school_email); ?><?php endif; ?>
			</div>
			<?php if (!empty($school_website)) : ?><div><?= esc($school_website); ?></div><?php endif; ?>
		</div>
	</div>
	<div class="header-right">
		<div><strong>Academic year:</strong> <?= esc($selectedYearTitle ?? ''); ?></div>
		<div><strong>Term:</strong> <?= esc($termLabel ?? ''); ?></div>
		<div><strong>Class:</strong> <?= $className; ?></div>
		<div><strong>Class mentor:</strong> <?= esc($classMentor !== '' ? $classMentor : '—'); ?></div>
		<div><strong>Head teacher:</strong> <?= esc($head_master ?? '—'); ?></div>
		<div><strong>Students listed:</strong> <?= (int) count($students ?? []); ?></div>
		<?php if ($feesScopeLabel !== '') : ?>
		<div><strong>Fees type:</strong> <?= esc($feesScopeLabel); ?></div>
		<?php endif; ?>
		<div><strong>Printed:</strong> <?= date('d-M-Y H:i'); ?></div>
	</div>
	<div class="clear"></div>
</div>

<div class="report-title"><?= esc($title ?? lang('app.feesReport')); ?></div>

<div class="kpi-row">
	<span><strong>Expected:</strong> <?= number_format((float) ($stats['total_expected'] ?? 0)); ?> Rwf</span>
	<?php if ($showSchoolFees) : ?>
	<span><strong>School fees:</strong> <?= number_format((float) ($stats['total_school_expected'] ?? 0)); ?> Rwf</span>
	<?php endif; ?>
	<?php if ($showExtraFees) : ?>
	<span><strong>Extra fees:</strong> <?= number_format((float) ($stats['total_extra_expected'] ?? 0)); ?> Rwf</span>
	<?php endif; ?>
	<span><strong>Collected:</strong> <?= number_format((float) ($stats['total_paid'] ?? 0)); ?> Rwf</span>
	<span><strong>Outstanding:</strong> <?= number_format((float) ($stats['total_remain'] ?? 0)); ?> Rwf</span>
	<span><strong>Rate:</strong> <?= esc((string) ($stats['collection_rate'] ?? 0)); ?>%</span>
</div>

<table class="report">
	<thead>
	<tr>
		<th>#</th>
		<th><?= esc(lang('app.regNo')); ?></th>
		<th><?= esc(lang('app.names')); ?></th>
		<th>Mode</th>
		<th><?= esc(lang('app.slipReference')); ?></th>
		<th>Payment method</th>
		<?php if ($showSchoolFees) : ?>
		<th>School fees</th>
		<?php endif; ?>
		<?php if ($showExtraFees) : ?>
		<?php foreach ($extraCols as $feeTitle) : ?>
			<th class="extra-head"><?= esc($feeTitle); ?></th>
		<?php endforeach; ?>
		<th>Extra total</th>
		<?php endif; ?>
		<th>Paid</th>
		<th>Balance</th>
		<th>Status</th>
		<th><?= esc(lang('app.recordedBy')); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php
	$i = 1;
	foreach (($students ?? []) as $student) :
		$amt = (float) ($student['amount'] ?? 0);
		$schoolAmt = (float) ($student['school_amount'] ?? 0);
		$extraAmt = (float) ($student['extra_amount'] ?? 0);
		$paid = (float) ($student['paid'] ?? 0);
		$st = fr_pdf_status($amt, $paid);
		$breakdown = $student['extra_breakdown'] ?? [];
		$refs = trim((string) ($student['ref_nos'] ?? ''));
		$modes = trim((string) ($student['payment_modes'] ?? ''));
		$actors = trim((string) ($student['recorded_by_names'] ?? ''));
		?>
		<tr>
			<td class="text-center"><?= $i++; ?></td>
			<td><?= esc($student['regno'] ?? ''); ?></td>
			<td><?= esc($student['student'] ?? ''); ?></td>
			<td class="text-center"><?= esc(\App\Controllers\Home::ModeToStr($student['studying_mode'] ?? 1)); ?></td>
			<td><?= esc($refs !== '' ? $refs : '—'); ?></td>
			<td><?= esc($modes !== '' ? $modes : '—'); ?></td>
			<?php
			$schoolCell = fr_pdf_fee_cell($schoolAmt, (float) ($student['school_paid'] ?? 0));
			$extraTotalCell = fr_pdf_fee_cell($extraAmt, (float) ($student['extra_paid'] ?? 0));
			?>
			<?php if ($showSchoolFees) : ?>
			<td class="text-right <?= esc($schoolCell['class']); ?>"><?= $schoolCell['html']; ?></td>
			<?php endif; ?>
			<?php if ($showExtraFees) : ?>
			<?php foreach ($extraCols as $feeTitle) :
				$exp = (float) ($breakdown[$feeTitle]['expected'] ?? 0);
				$feePaid = (float) ($breakdown[$feeTitle]['paid'] ?? 0);
				$feeCell = fr_pdf_fee_cell($exp, $feePaid);
				?>
				<td class="text-right <?= esc($feeCell['class']); ?>"><?= $feeCell['html']; ?></td>
			<?php endforeach; ?>
			<td class="text-right <?= esc($extraTotalCell['class']); ?>"><?= $extraTotalCell['html']; ?></td>
			<?php endif; ?>
			<td class="text-right"><?= number_format($paid); ?></td>
			<td class="text-right"><?= $st['remain'] > 0 ? number_format($st['remain']) : '—'; ?></td>
			<td class="text-center"><span class="badge <?= esc($st['class']); ?>"><?= esc($st['label']); ?></span></td>
			<td><?= esc($actors !== '' ? $actors : '—'); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<div class="footer">
	<div class="left"><?= esc(lang('app.printedBy')); ?> <?= esc(session('soma_name') ?? ''); ?></div>
	<div class="right"><?= esc($school_name ?? ''); ?> · Fees report</div>
	<div class="clear"></div>
</div>
</body>
</html>
