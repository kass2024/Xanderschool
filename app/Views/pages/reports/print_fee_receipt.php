<?php
/** @var array $records */
/** @var array $student */
/** @var string $yearTitle */
/** @var bool $autoprint */
/** @var string $pdfUrl */
helper('qonics');
$total = 0;
foreach ($records as $record) {
	$total += (float) ($record['amount'] ?? 0);
}
$defaultFormat = 'thermal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cash Receipt | <?= esc($school_name ?? ''); ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/thermal-print.css'); ?>">
</head>
<body data-tp-format="thermal">

<div class="tp-toolbar no-print">
	<button type="button" class="tp-primary active" data-tp-format-btn="thermal">Print Thermal (80mm)</button>
	<button type="button" data-tp-format-btn="a4">Print A4</button>
	<?php if (!empty($pdfUrl)) : ?>
		<a class="tp-link" href="<?= esc($pdfUrl); ?>" target="_blank">Download PDF</a>
	<?php endif; ?>
	<p class="tp-hint">Thermal opens automatically after save. Use A4 or PDF if no thermal printer is connected.</p>
</div>

<div class="tp-wrap">
	<div class="tp-thermal" id="tpThermal">
		<div class="tp-logo">
			<?php if (!empty($school_logo)) : ?>
				<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" alt="Logo">
			<?php endif; ?>
		</div>
		<div class="tp-school"><?= esc($school_name ?? ''); ?></div>
		<div class="tp-meta"><?= esc($school_phone ?? ''); ?> · <?= esc($school_email ?? ''); ?></div>
		<div class="tp-title">CASH DEPOSIT RECEIPT</div>

		<div class="tp-line"><strong>Student:</strong> <?= esc($student['stdnames'] ?? ''); ?></div>
		<div class="tp-line"><strong>Class:</strong> <?= esc(trim(($student['level_name'] ?? '') . ' ' . ($student['title'] ?? '') . ' ' . ($student['code'] ?? ''))); ?></div>
		<div class="tp-line"><strong>Reg no:</strong> <?= esc($student['regno'] ?? ''); ?></div>
		<div class="tp-line"><strong>Year:</strong> <?= esc($yearTitle ?? ''); ?></div>
		<div class="tp-line"><strong>Date:</strong> <?= date('d-m-Y H:i'); ?></div>
		<?php
		$receiptActors = [];
		foreach ($records as $r) {
			$nm = trim((string) ($r['recorded_by_name'] ?? ''));
			if ($nm !== '') {
				$receiptActors[$nm] = $nm;
			}
		}
		?>
		<?php if ($receiptActors !== []) : ?>
			<div class="tp-line"><strong>Recorded by:</strong> <?= esc(implode(', ', $receiptActors)); ?></div>
		<?php endif; ?>

		<?php $i = 1; foreach ($records as $record) : ?>
			<div class="tp-item">
				<div><?= $i; ?>. <?= esc($record['item']); ?></div>
				<div class="tp-item-row">
					<span><?= esc(lang('app.' . termToStr($record['term']))); ?></span>
					<span><?= number_format((float) $record['amount']); ?> Rwf</span>
				</div>
				<div style="font-size:10px;color:#555;"><?= esc(paymentModeToString($record['payment_mode'])); ?> · <?= date('d-M-Y', strtotime($record['date'])); ?></div>
			</div>
		<?php $i++; endforeach; ?>

		<div class="tp-total">
			<span>TOTAL</span>
			<span><?= number_format($total); ?> Rwf</span>
		</div>
		<div class="tp-footer">Thank you — <?= esc($school_name ?? ''); ?></div>
	</div>

	<div class="tp-a4" id="tpA4">
		<div class="tp-head">
			<?php if (!empty($school_logo)) : ?>
				<img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" alt="Logo">
			<?php endif; ?>
			<div>
				<div style="font-size:18px;font-weight:700;"><?= esc($school_name ?? ''); ?></div>
				<div>Phone: <?= esc($school_phone ?? ''); ?></div>
				<div>Email: <?= esc($school_email ?? ''); ?></div>
			</div>
		</div>

		<h1 class="tp-doc-title">CASH DEPOSIT RECEIPT</h1>

		<div class="tp-grid">
			<div><strong>Student:</strong> <?= esc($student['stdnames'] ?? ''); ?></div>
			<div><strong>Reg no:</strong> <?= esc($student['regno'] ?? ''); ?></div>
			<div><strong>Class:</strong> <?= esc(trim(($student['level_name'] ?? '') . ' ' . ($student['title'] ?? '') . ' ' . ($student['code'] ?? ''))); ?></div>
			<div><strong>Academic year:</strong> <?= esc($yearTitle ?? ''); ?></div>
			<div><strong>Date:</strong> <?= date('d-m-Y H:i'); ?></div>
			<?php if ($receiptActors !== []) : ?>
				<div><strong>Recorded by:</strong> <?= esc(implode(', ', $receiptActors)); ?></div>
			<?php endif; ?>
		</div>

		<table>
			<thead>
			<tr>
				<th>#</th>
				<th>Item</th>
				<th>Term</th>
				<th>Mode</th>
				<th>Date</th>
				<th style="text-align:right;">Amount (Rwf)</th>
			</tr>
			</thead>
			<tbody>
			<?php $i = 1; foreach ($records as $record) : ?>
				<tr>
					<td><?= $i++; ?></td>
					<td><?= esc($record['item']); ?></td>
					<td><?= esc(lang('app.' . termToStr($record['term']))); ?></td>
					<td><?= esc(paymentModeToString($record['payment_mode'])); ?></td>
					<td><?= date('d-M-Y', strtotime($record['date'])); ?></td>
					<td style="text-align:right;"><?= number_format((float) $record['amount']); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="tp-total-a4">Total: <?= number_format($total); ?> Rwf</div>
	</div>
</div>

<script src="<?= base_url('assets/js/thermal-print.js'); ?>"></script>
<script>
ThermalPrint.init({
	defaultFormat: <?= json_encode($defaultFormat); ?>,
	autoprint: <?= !empty($autoprint) ? 'true' : 'false'; ?>
});
</script>
</body>
</html>
