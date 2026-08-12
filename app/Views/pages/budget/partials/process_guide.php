<?php
/**
 * Compact action shortcuts (no long instructional copy — user guide later).
 * $ctx: full | prep | execution
 */
$compact = !empty($compact);
$ctx = $ctx ?? 'full';
?>
<div class="bp-process-guide bp-process-actions <?= $compact ? 'bp-process-compact' : ''; ?>">
	<div class="bp-action-grid">
		<?php if ($ctx === 'full' || $ctx === 'prep') { ?>
		<a class="btn btn-outline-primary btn-sm bp-action-btn" href="<?= base_url('budget/prepare'); ?>">
			<span class="bp-action-num">1</span> Prepare budget
		</a>
		<a class="btn btn-outline-primary btn-sm bp-action-btn" href="<?= base_url('budget/prepare?tab=review'); ?>">
			<span class="bp-action-num">2</span> Budget review
		</a>
		<?php } ?>
		<?php if ($ctx === 'full' || $ctx === 'execution') { ?>
		<a class="btn btn-outline-success btn-sm bp-action-btn" href="<?= base_url('budget/cash_request_form'); ?>">
			<span class="bp-action-num"><?= ($ctx === 'execution') ? '1' : '3'; ?></span> New cash request
		</a>
		<a class="btn btn-outline-secondary btn-sm bp-action-btn" href="<?= base_url('budget/requests?tab=pending'); ?>">
			<span class="bp-action-num"><?= ($ctx === 'execution') ? '2' : '4'; ?></span> Pending approvals
		</a>
		<?php if ($ctx === 'full' || $ctx === 'execution') { ?>
		<a class="btn btn-outline-secondary btn-sm bp-action-btn" href="<?= base_url('budget/requests?tab=payments'); ?>">
			<span class="bp-action-num"><?= ($ctx === 'execution') ? '3' : '5'; ?></span> Payments
		</a>
		<?php } ?>
		<?php } ?>
		<?php if ($ctx === 'prep') { ?>
		<a class="btn btn-outline-secondary btn-sm bp-action-btn" href="<?= base_url('budget/dashboard'); ?>">
			<span class="bp-action-num">3</span> Dashboard
		</a>
		<?php } ?>
	</div>
</div>
