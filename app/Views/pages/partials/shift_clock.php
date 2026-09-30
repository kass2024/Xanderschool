<?php
$role = $role ?? 'start';
$decimal = (string) ($decimal ?? ($role === 'end' ? '17.0' : '9.0'));
$val = (float) $decimal;
$hh24 = (int) floor($val + 1e-8);
$minute = (int) round(($val - $hh24) * 60);
if ($minute >= 60) {
	$hh24++;
	$minute %= 60;
}
$mer = $hh24 >= 12 ? 'pm' : 'am';
$h12 = $hh24 % 12;
if ($h12 === 0) {
	$h12 = 12;
}
$cls = $role === 'end' ? 'hours-end' : 'hours-start';
$label = $role === 'end' ? 'End' : 'Start';
?>
<div class="shift-clock <?= $cls ?>" data-value="<?= esc($decimal, 'attr') ?>">
	<span class="shift-clock-label"><?= esc($label) ?></span>
	<div class="shift-clock-face">
		<select class="shift-clock-h" aria-label="<?= esc($label) ?> hour">
			<?php for ($h = 1; $h <= 12; $h++): ?>
				<option value="<?= $h ?>"<?= $h === $h12 ? ' selected' : '' ?>><?= $h ?></option>
			<?php endfor; ?>
		</select>
		<span class="shift-clock-sep">:</span>
		<select class="shift-clock-m" aria-label="<?= esc($label) ?> minute">
			<?php for ($m = 0; $m < 60; $m++): ?>
				<option value="<?= $m ?>"<?= $m === $minute ? ' selected' : '' ?>>:<?= sprintf('%02d', $m) ?></option>
			<?php endfor; ?>
		</select>
		<div class="shift-clock-mer" role="group" aria-label="<?= esc($label) ?> AM or PM">
			<button type="button" data-mer="am" class="<?= $mer === 'am' ? 'is-on' : '' ?>">AM</button>
			<button type="button" data-mer="pm" class="<?= $mer === 'pm' ? 'is-on' : '' ?>">PM</button>
		</div>
	</div>
	<div class="shift-clock-quick">
		<?php foreach ([0, 15, 30, 45] as $quick): ?>
			<button type="button" data-min="<?= $quick ?>" class="<?= $minute === $quick ? 'is-on' : '' ?>">:<?= sprintf('%02d', $quick) ?></button>
		<?php endforeach; ?>
	</div>
</div>
