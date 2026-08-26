<?php
$momoPayCode = trim((string) ($momo_pay_code ?? ''));
$momoPayName = trim((string) ($momo_pay_name ?? ''));
$variant = (string) ($momo_pay_variant ?? 'form');
$locked = !empty($momo_pay_locked);
$visible = $momoPayCode !== '';
$variantClass = 'inline';
if ($variant === 'success') {
	$variantClass = 'success';
} elseif ($variant === 'end') {
	$variantClass = 'end';
} elseif ($variant === 'top') {
	$variantClass = 'top';
}
$boxClass = 'ss-momo-pay-box ss-momo-pay-' . $variantClass;
?>
<div class="<?= esc($boxClass, 'attr'); ?>"
	 data-locked="<?= $locked ? '1' : '0'; ?>"
	 style="<?= $visible ? '' : 'display:none;'; ?>">
	<div class="ss-momo-pay-badge"><i class="fa fa-mobile"></i> Pay with MoMo Pay</div>
	<p class="ss-momo-pay-lead">
		<?php if ($variant === 'success'): ?>
			Pay the registration amount using this merchant code so the school can confirm your application:
		<?php elseif ($variant === 'end'): ?>
			Use this MoMo Pay merchant code to pay the amount shown on this form:
		<?php elseif ($variant === 'top'): ?>
			Pay registration with this MoMo Pay merchant:
		<?php else: ?>
			Pay the amount above using MoMo Pay:
		<?php endif; ?>
	</p>
	<div class="ss-momo-pay-grid">
		<div class="ss-momo-pay-item">
			<span class="ss-momo-pay-label">MoMo Pay code</span>
			<strong class="ss-momo-pay-code"><?= esc($momoPayCode); ?></strong>
			<button type="button" class="ss-momo-pay-copy" data-copy="<?= esc($momoPayCode, 'attr'); ?>">Copy code</button>
		</div>
		<div class="ss-momo-pay-item">
			<span class="ss-momo-pay-label">MoMo Pay names</span>
			<strong class="ss-momo-pay-name"><?= esc($momoPayName !== '' ? $momoPayName : '—'); ?></strong>
		</div>
	</div>
	<p class="ss-momo-pay-hint">
		On MTN: dial <strong>*182*8*1#</strong>, enter code <strong class="ss-momo-pay-code"><?= esc($momoPayCode); ?></strong>,
		confirm the name <strong class="ss-momo-pay-name"><?= esc($momoPayName !== '' ? $momoPayName : '—'); ?></strong>, then enter the amount.
	</p>
</div>
