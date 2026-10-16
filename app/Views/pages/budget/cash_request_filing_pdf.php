<?php
$r = $req;
$fmt = static function ($value) {
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}
	$ts = strtotime($value);
	return $ts ? date('d/m/Y', $ts) : $value;
};
$prepName = trim((string) ($r['prepared_by_name'] ?? ''));
if ($prepName === '') {
	$prepName = trim((string) (($creator['fname'] ?? '') . ' ' . ($creator['lname'] ?? '')));
}
$prepPos = trim((string) ($r['prepared_position'] ?? ''));
if ($prepPos === '') {
	$prepPos = trim((string) ($creator['post_title'] ?? ''));
}
$prepPhone = trim((string) ($r['prepared_phone'] ?? ''));
if ($prepPhone === '') {
	$prepPhone = trim((string) ($creator['phone'] ?? ''));
}
$sig = static function ($src) {
	if ($src === '') {
		return '________________';
	}
	return '<img src="' . esc($src, 'attr') . '" style="height:36px;">';
};
$lineNames = [];
foreach ($lines as $ln) {
	$cat = trim((string) ($ln['budget_category'] ?? ''));
	if ($cat !== '' && !in_array($cat, $lineNames, true)) {
		$lineNames[] = $cat;
	}
}
$navy = '#1F3864';
$lab = 'background:#DCE6F1;color:#1F3864;font-weight:bold;width:34%;';
$cell = 'border:1px solid #9AA7B0;padding:6px 8px;font-size:11px;vertical-align:top;';
?>
<div style="font-family:dejavusanscondensed,sans-serif;color:#1a1a1a;">
<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:6px;">
	<tr>
		<td width="70"><?php if ($logoSrc !== '') { ?><img src="<?= esc($logoSrc, 'attr'); ?>" style="height:52px;"><?php } ?></td>
		<td align="center">
			<div style="color:<?= $navy; ?>;font-size:16px;font-weight:bold;"><?= esc($schoolName); ?></div>
			<?php if ($motto !== '') { ?><div style="color:<?= $navy; ?>;font-style:italic;font-size:11px;">"<?= esc($motto); ?>"</div><?php } ?>
			<?php if ($website !== '') { ?><div style="font-size:10px;"><?= esc($website); ?></div><?php } ?>
		</td>
		<td width="70"></td>
	</tr>
</table>
<div style="border-bottom:3px solid <?= $navy; ?>;margin-bottom:8px;"></div>
<div style="text-align:center;color:<?= $navy; ?>;font-weight:bold;font-size:14px;letter-spacing:1px;margin-bottom:8px;">EXPENSE REQUEST FORM</div>
<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
	<tr>
		<td style="<?= $cell . $lab; ?>width:18%;">School</td>
		<td style="<?= $cell; ?>"><?= esc($schoolName); ?></td>
		<td style="<?= $cell . $lab; ?>width:18%;">Date</td>
		<td style="<?= $cell; ?>"><?= esc($fmt($r['request_date'] ?? '')); ?></td>
	</tr>
	<tr>
		<td style="<?= $cell . $lab; ?>">Request no.</td>
		<td style="<?= $cell; ?>"><?= esc($r['request_no'] ?? ''); ?></td>
		<td style="<?= $cell . $lab; ?>">Status</td>
		<td style="<?= $cell; ?>">Approved for filing</td>
	</tr>
</table>
<div style="background:<?= $navy; ?>;color:#fff;font-weight:bold;font-size:12px;padding:4px 8px;margin-top:10px;">1. Department Request Details</div>
<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
	<tr><td style="<?= $cell . $lab; ?>">Prepared by (Names)</td><td style="<?= $cell; ?>" colspan="3"><?= esc($prepName); ?></td></tr>
	<tr><td style="<?= $cell . $lab; ?>">Position</td><td style="<?= $cell; ?>" colspan="3"><?= esc($prepPos); ?></td></tr>
	<tr>
		<td style="<?= $cell . $lab; ?>">Tel.</td>
		<td style="<?= $cell; ?>"><?= esc($prepPhone); ?></td>
		<td style="<?= $cell . $lab; ?>width:18%;">Date</td>
		<td style="<?= $cell; ?>"><?= esc($fmt($r['request_date'] ?? '')); ?></td>
	</tr>
	<tr><td style="<?= $cell . $lab; ?>">Signature</td><td style="<?= $cell; ?>" colspan="3"><?= $sig($prepSig); ?></td></tr>
</table>
<div style="background:<?= $navy; ?>;color:#fff;font-weight:bold;font-size:12px;padding:4px 8px;margin-top:10px;">2. Purpose of Purchase</div>
<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
	<tr><td style="<?= $cell; ?>min-height:48px;"><?= nl2br(esc($r['purpose'] ?? '')); ?></td></tr>
</table>
<div style="background:<?= $navy; ?>;color:#fff;font-weight:bold;font-size:12px;padding:4px 8px;margin-top:10px;">3. Description of Materials / Items and Amounts</div>
<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
	<tr>
		<th style="<?= $cell . $lab; ?>width:36px;">No.</th>
		<th style="<?= $cell . $lab; ?>">Description of Materials / Items</th>
		<th style="<?= $cell . $lab; ?>width:110px;text-align:right;">Amount (FRW)</th>
	</tr>
	<?php
	$n = 0;
	foreach ($lines as $ln) {
		$n++;
		$desc = trim((string) ($ln['description'] ?? ''));
		$cat = trim((string) ($ln['budget_category'] ?? ''));
		if ($cat !== '' && strcasecmp($cat, $desc) !== 0) {
			$desc = $cat . ($desc !== '' ? "\n" . $desc : '');
		}
	?>
	<tr>
		<td style="<?= $cell; ?>text-align:center;"><?= $n; ?></td>
		<td style="<?= $cell; ?>"><?= nl2br(esc($desc)); ?></td>
		<td style="<?= $cell; ?>text-align:right;"><?= number_format((float) ($ln['amount'] ?? 0), 0); ?></td>
	</tr>
	<?php } ?>
	<tr>
		<td style="<?= $cell . $lab; ?>text-align:right;" colspan="2">TOTAL</td>
		<td style="<?= $cell . $lab; ?>text-align:right;"><?= number_format((float) $total, 0); ?></td>
	</tr>
</table>
<div style="background:<?= $navy; ?>;color:#fff;font-weight:bold;font-size:12px;padding:4px 8px;margin-top:10px;">4. Budget Information</div>
<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
	<tr><td style="<?= $cell . $lab; ?>">Budget line description</td><td style="<?= $cell; ?>"><?= esc($lineNames ? implode('; ', $lineNames) : '—'); ?></td></tr>
	<tr><td style="<?= $cell . $lab; ?>">Total amount in FRW requested (in figures)</td><td style="<?= $cell; ?>"><?= number_format((float) $total, 0); ?></td></tr>
	<tr><td style="<?= $cell . $lab; ?>">Total amount in FRW (in words)</td><td style="<?= $cell; ?>"><?= esc($words); ?></td></tr>
</table>
<div style="background:<?= $navy; ?>;color:#fff;font-weight:bold;font-size:12px;padding:4px 8px;margin-top:10px;">5. Payment Details</div>
<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
	<tr><td style="<?= $cell . $lab; ?>">Payment to be made to</td><td style="<?= $cell; ?>"><?= esc($r['payee_name'] ?? ''); ?></td></tr>
	<tr><td style="<?= $cell . $lab; ?>">Contact Info:</td><td style="<?= $cell; ?>"><?= esc($r['payee_contact'] ?? ''); ?></td></tr>
	<tr><td style="<?= $cell . $lab; ?>">Payment / Account Number:</td><td style="<?= $cell; ?>"><?= esc($r['payee_account'] ?? ''); ?></td></tr>
	<tr><td style="<?= $cell . $lab; ?>">Bank Name:</td><td style="<?= $cell; ?>"><?= esc($r['payee_bank'] ?? ''); ?></td></tr>
</table>
<div style="background:<?= $navy; ?>;color:#fff;font-weight:bold;font-size:12px;padding:4px 8px;margin-top:10px;">6. Verification and Approval</div>
<table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
	<tr>
		<td style="<?= $cell . $lab; ?>">Verified by the Accountant</td>
		<td style="<?= $cell; ?>"><?= $sig($prepSig); ?></td>
		<td style="<?= $cell; ?>">Date: <?= esc($fmt($when['submit'] ?? '')); ?></td>
	</tr>
	<tr>
		<td style="<?= $cell . $lab; ?>">Verified by the Headteacher</td>
		<td style="<?= $cell; ?>"><?= $sig($headSig); ?></td>
		<td style="<?= $cell; ?>">Date: <?= esc($fmt($when['headteacher_approve'] ?? '')); ?></td>
	</tr>
	<tr>
		<td style="<?= $cell . $lab; ?>">Approved by the Chief Accountant</td>
		<td style="<?= $cell; ?>"><?= $sig($chiefSig); ?></td>
		<td style="<?= $cell; ?>">Date: <?= esc($fmt($when['chief_accountant_approve'] ?? '')); ?></td>
	</tr>
	<tr>
		<td style="<?= $cell . $lab; ?>">Authorized by the Director of Finance</td>
		<td style="<?= $cell; ?>"><?= $sig($financeSig); ?></td>
		<td style="<?= $cell; ?>">Date: <?= esc($fmt($when['final_approve'] ?? '')); ?></td>
	</tr>
</table>
</div>
