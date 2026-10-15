<?php
$leave = is_array($leaves ?? null) ? $leaves : [];
$approver = is_array($approver ?? null) ? $approver : [];
$type = (int) ($leave['type'] ?? 0);
$status = (int) ($leave['status'] ?? 0);
$fromTs = (int) ($leave['fromDate'] ?? 0);
$toTs = (int) ($leave['toDate'] ?? 0);
$span = $toTs - $fromTs;
$hourly = $type === 5 && $span > 0 && $span <= 43200;
$hours = $hourly ? round($span / 3600, 2) : 0;
$daysRaw = (float) ($leave['days'] ?? 0);
$daysLabel = rtrim(rtrim(number_format($daysRaw, 2, '.', ''), '0'), '.');
if ($daysLabel === '') {
	$daysLabel = '0';
}
$hoursLabel = rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.');

$appliedOn = '';
if (!empty($leave['created_at'])) {
	$appliedTs = strtotime((string) $leave['created_at']);
	if ($appliedTs) {
		$appliedOn = date('d-M-Y', $appliedTs);
	}
}
if ($appliedOn === '' && $fromTs > 0) {
	$appliedOn = date('d-M-Y', $fromTs);
}

$approvedOn = '';
if (!empty($approver['updated_at'])) {
	$approvedTs = strtotime((string) $approver['updated_at']);
	if ($approvedTs) {
		$approvedOn = date('d-M-Y', $approvedTs);
	}
}

$fromLabel = $fromTs > 0 ? date($hourly ? 'd-M-Y H:i' : 'd-M-Y', $fromTs) : '';
$toLabel = $toTs > 0 ? date($hourly ? 'd-M-Y H:i' : 'd-M-Y', $toTs) : '';

$types = [
	1 => trim((string) lang('app.annualLeave')),
	2 => trim((string) lang('app.sickLeave')),
	3 => trim((string) lang('app.maternityLeave')),
	4 => trim((string) lang('app.unpaidLeave')),
	5 => trim((string) lang('app.shortPermission')),
];

$statusKey = 'pending';
$statusClass = 'wait';
if ($status === 1) {
	$statusKey = 'approved';
	$statusClass = 'ok';
} elseif ($status === 2) {
	$statusKey = 'denied';
	$statusClass = 'no';
}

$applicantSign = function_exists('report_signature_img')
	? report_signature_img($leave['staff_signature'] ?? '', false, 18, 58)
	: '';
$approverSign = function_exists('report_signature_img')
	? report_signature_img($approver['staff_signature'] ?? '', false, 18, 58)
	: '';

$applicantName = trim((string) ($leave['staff'] ?? ''));
$approverName = trim((string) ($approver['staff'] ?? ''));
$applicantPost = trim((string) ($leave['post_title'] ?? ''));
$approverPost = trim((string) ($approver['post_title'] ?? ''));
$reason = trim((string) ($leave['reason'] ?? ''));
$denyReason = trim((string) ($leave['deny_reason'] ?? ''));
$address = trim((string) ($leave['address'] ?? ''));
$ref = 'LR-' . str_pad((string) ((int) ($leave['id'] ?? 0)), 4, '0', STR_PAD_LEFT);
$logo = trim((string) ($school_logo ?? ''));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
	* { box-sizing: border-box; }
	html, body {
		margin: 0;
		background: #ffffff;
		color: #1c2834;
		color-scheme: light;
		font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
		font-size: 12px;
		line-height: 1.45;
	}
	table { border-collapse: collapse; width: 100%; background: #ffffff; }
	td { background: #ffffff; color: #1c2834; }
	.sheet { width: 100%; }
	.head td { vertical-align: middle; }
	.logo-cell { width: 92px; }
	.logo-cell img { width: 84px; height: auto; }
	.identity { padding-left: 8px; }
	.republic {
		font-size: 11px;
		letter-spacing: 0.14em;
		color: #0c3d66;
		font-weight: bold;
	}
	.ministry {
		font-size: 11px;
		letter-spacing: 0.08em;
		color: #3d4d5c;
		margin-top: 1px;
	}
	.school {
		font-size: 16px;
		font-weight: bold;
		color: #0c3d66;
		margin-top: 3px;
		letter-spacing: 0.02em;
	}
	.contacts { color: #5c6b78; font-size: 11px; margin-top: 3px; }
	.status-cell { width: 118px; text-align: right; }
	.pill {
		display: inline-block;
		padding: 5px 10px;
		border-radius: 12px;
		font-size: 10px;
		font-weight: bold;
		letter-spacing: 0.08em;
	}
	.pill.ok { background: #e5f6ec; color: #0d6b3d; border: 1px solid #9ed4b4; }
	.pill.wait { background: #fff6e5; color: #8a5a00; border: 1px solid #f0d59a; }
	.pill.no { background: #fdecec; color: #9b1c1c; border: 1px solid #f0b4b4; }
	.ref { margin-top: 6px; color: #7a8794; font-size: 10px; letter-spacing: 0.06em; }
	.gold {
		height: 3px;
		background: #c4a35a;
		margin: 8px 0 2px;
	}
	.navy {
		height: 1px;
		background: #0c3d66;
		margin-bottom: 10px;
	}
	h1 {
		margin: 0 0 2px;
		text-align: center;
		font-size: 22px;
		letter-spacing: 0.12em;
		color: #0c3d66;
		font-weight: bold;
		text-transform: uppercase;
	}
	.wish {
		text-align: center;
		color: #5c6b78;
		font-style: italic;
		margin: 0 0 12px;
	}
	.panel {
		width: 100%;
		border: 1px solid #d5deE8;
		margin-bottom: 12px;
	}
	.panel td {
		padding: 7px 10px;
		border-bottom: 1px solid #e6edf4;
		vertical-align: top;
	}
	.panel tr:last-child td { border-bottom: 0; }
	.k {
		width: 18%;
		color: #5c6b78;
		font-size: 10px;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		background: #f6f8fb;
		font-weight: bold;
	}
	.v { font-weight: bold; color: #102033; font-size: 13px; }
	.section {
		background: #0c3d66;
		color: #fff;
		font-size: 11px;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		padding: 6px 10px;
		font-weight: bold;
	}
	.types td {
		width: 50%;
		padding: 8px 10px;
		border: 1px solid #d5dee8;
		border-top: 0;
	}
	.types td.picked {
		background: #eef6f1;
		font-weight: bold;
		color: #0d6b3d;
	}
	.box {
		display: inline-block;
		width: 12px;
		height: 12px;
		border: 1.5px solid #0c3d66;
		margin-right: 6px;
		text-align: center;
		line-height: 11px;
		font-size: 10px;
		color: #0d6b3d;
		font-weight: bold;
		vertical-align: -1px;
	}
	.picked .box { background: #0d6b3d; color: #fff; border-color: #0d6b3d; }
	.reason {
		border: 1px solid #d5dee8;
		border-top: 0;
		padding: 8px 10px 10px;
		margin-bottom: 12px;
	}
	.reason b { color: #0c3d66; }
	.note {
		margin-top: 6px;
		color: #6b7785;
		font-size: 10px;
		font-style: italic;
	}
	.metrics { margin-bottom: 12px; }
	.metrics td {
		width: 33.33%;
		background: #f6f8fb;
		border: 1px solid #d5dee8;
		padding: 8px 10px;
	}
	.metrics span {
		display: block;
		color: #5c6b78;
		font-size: 10px;
		letter-spacing: 0.05em;
		text-transform: uppercase;
	}
	.metrics strong { font-size: 14px; color: #0c3d66; }
	.signs { margin-top: 4px; }
	.signs td {
		width: 50%;
		vertical-align: top;
		border: 1px solid #d5dee8;
		padding: 0;
	}
	.signs td + td { border-left: 0; }
	.sign-body { padding: 10px 12px 12px; }
	.ink {
		background: #fff;
		border-bottom: 1.5px solid #0c3d66;
		min-height: 24mm;
		text-align: left;
		margin: 8px 0 8px;
		padding: 1mm 0 0;
	}
	.ink img { display: block; }
	.who { font-weight: bold; font-size: 13px; color: #1c2834; text-transform: uppercase; }
	.role { color: #5c6b78; font-size: 11px; margin-top: 1px; }
	.when { color: #3d4d5c; margin-top: 4px; font-size: 11px; }
	.deny {
		margin-top: 10px;
		border: 1px solid #f0b4b4;
		background: #fdecec;
		color: #9b1c1c;
		padding: 8px 10px;
	}
</style>
</head>
<body>
<div class="sheet">
	<table class="head">
		<tr>
			<td class="logo-cell">
				<?php if ($logo !== ''): ?>
					<img src="<?= base_url('assets/images/logo/' . $logo); ?>" alt="">
				<?php endif; ?>
			</td>
			<td class="identity">
				<div class="republic"><?= trim((string) lang('app.republic')); ?></div>
				<div class="ministry"><?= trim((string) lang('app.ministry')); ?></div>
				<div class="school"><?= esc($school_name ?? ''); ?></div>
				<div class="contacts">
					<?= trim((string) lang('app.mail')); ?>: <?= esc($school_email ?? ''); ?>
					&nbsp;&nbsp;·&nbsp;&nbsp;
					<?= trim((string) lang('app.phone')); ?>: <?= esc($school_phone ?? ''); ?>
				</div>
			</td>
			<td class="status-cell">
				<div class="pill <?= $statusClass; ?>"><?= esc(strtoupper(trim((string) lang('app.' . $statusKey)))); ?></div>
				<div class="ref"><?= esc($ref); ?></div>
			</td>
		</tr>
	</table>
	<div class="gold"></div>
	<div class="navy"></div>
	<h1><?= esc(trim((string) lang('app.leaveRequest'))); ?></h1>
	<p class="wish"><?= esc(trim((string) lang('app.wish'))); ?></p>

	<table class="panel">
		<tr>
			<td class="k"><?= esc(trim((string) lang('app.NamesofEmployee'))); ?></td>
			<td class="v" colspan="3"><?= esc($applicantName); ?></td>
		</tr>
		<tr>
			<td class="k"><?= esc(trim((string) lang('app.PhoneNumber'))); ?></td>
			<td class="v"><?= esc($leave['staff_phone'] ?? ''); ?></td>
			<td class="k"><?= esc(trim((string) lang('app.mail'))); ?></td>
			<td class="v"><?= esc($leave['staff_email'] ?? ''); ?></td>
		</tr>
		<?php if ($applicantPost !== ''): ?>
		<tr>
			<td class="k"><?= esc(trim((string) lang('app.position'))); ?></td>
			<td class="v" colspan="3"><?= esc($applicantPost); ?></td>
		</tr>
		<?php endif; ?>
	</table>

	<div class="section"><?= esc(trim((string) lang('app.typeofleave'))); ?></div>
	<table class="types">
		<?php
		$ids = array_keys($types);
		for ($i = 0; $i < count($ids); $i += 2):
			$left = $ids[$i];
			$right = $ids[$i + 1] ?? null;
		?>
		<tr>
			<?php if ($right === null): ?>
			<td colspan="2" class="<?= $left === $type ? 'picked' : ''; ?>">
				<span class="box"><?= $left === $type ? '&#10003;' : ''; ?></span><?= esc($types[$left]); ?>
			</td>
			<?php else: ?>
			<td class="<?= $left === $type ? 'picked' : ''; ?>">
				<span class="box"><?= $left === $type ? '&#10003;' : ''; ?></span><?= esc($types[$left]); ?>
			</td>
			<td class="<?= $right === $type ? 'picked' : ''; ?>">
				<span class="box"><?= $right === $type ? '&#10003;' : ''; ?></span><?= esc($types[$right]); ?>
			</td>
			<?php endif; ?>
		</tr>
		<?php endfor; ?>
	</table>
	<?php if ($reason !== '' || $type === 2 || $type === 3): ?>
	<div class="reason">
		<?php if ($reason !== ''): ?>
			<b><?= esc(trim((string) lang('app.reason'))); ?>:</b> <?= esc($reason); ?>
		<?php endif; ?>
		<?php if ($type === 2): ?>
			<div class="note"><?= esc(trim((string) lang('app.execess1day'))); ?></div>
		<?php elseif ($type === 3): ?>
			<div class="note"><?= esc(trim((string) lang('app.attached'))); ?></div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<table class="metrics">
		<tr>
			<td>
				<span><?= esc($hourly ? trim((string) lang('app.hoursRequested')) : trim((string) lang('app.daysRequest'))); ?></span>
				<strong><?= esc($hourly ? $hoursLabel . ' h' : $daysLabel . ' ' . trim((string) lang('app.dayss'))); ?></strong>
			</td>
			<td>
				<span><?= esc(trim((string) lang('app.from'))); ?></span>
				<strong><?= esc($fromLabel); ?></strong>
			</td>
			<td>
				<span><?= esc(trim((string) lang('app.to'))); ?></span>
				<strong><?= esc($toLabel); ?></strong>
			</td>
		</tr>
	</table>

	<table class="panel">
		<tr>
			<td class="k"><?= esc(trim((string) lang('app.addresDuringLeave'))); ?></td>
			<td class="v"><?= esc($address); ?></td>
		</tr>
		<tr>
			<td class="k"><?= esc(trim((string) lang('app.tel'))); ?></td>
			<td class="v"><?= esc($leave['staff_phone'] ?? ''); ?></td>
		</tr>
	</table>

	<table class="signs">
		<tr>
			<td>
				<div class="section"><?= esc(trim((string) lang('app.appSign'))); ?></div>
				<div class="sign-body">
					<div class="ink"><?= $applicantSign; ?></div>
					<div class="who"><?= esc($applicantName); ?></div>
					<?php if ($applicantPost !== ''): ?>
						<div class="role"><?= esc($applicantPost); ?></div>
					<?php endif; ?>
					<div class="when"><?= esc(trim((string) lang('app.date'))); ?>: <?= esc($appliedOn); ?></div>
				</div>
			</td>
			<td>
				<div class="section"><?= esc(trim((string) lang('app.approvedBy'))); ?></div>
				<div class="sign-body">
					<div class="ink"><?= $status === 1 ? $approverSign : ''; ?></div>
					<div class="who"><?= esc($status === 1 ? $approverName : trim((string) lang('app.' . $statusKey))); ?></div>
					<?php if ($status === 1 && $approverPost !== ''): ?>
						<div class="role"><?= esc($approverPost); ?></div>
					<?php endif; ?>
					<?php if ($status === 1 && $approvedOn !== ''): ?>
						<div class="when"><?= esc(trim((string) lang('app.on'))); ?>: <?= esc($approvedOn); ?></div>
					<?php endif; ?>
				</div>
			</td>
		</tr>
	</table>

	<?php if ($status === 2 && $denyReason !== ''): ?>
		<div class="deny"><b><?= esc(trim((string) lang('app.reason'))); ?>:</b> <?= esc($denyReason); ?></div>
	<?php endif; ?>
</div>
</body>
</html>
