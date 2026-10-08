<?php
$desk = $desk ?? [];
$group = $desk['group'] ?? [];
$schools = $desk['schools'] ?? [];
$statusLabels = [
	'DRAFT' => 'Draft',
	'SUBMITTED' => 'With Chief Accountant',
	'CHIEF_ACCOUNTANT_REVIEW' => 'With Chief Accountant',
	'DEPUTY_DIRECTOR_REVIEW' => 'With Director of Finance',
	'APPROVED' => 'Approved',
	'RETURNED' => 'Returned',
	'REJECTED' => 'Rejected',
];
$statusClass = [
	'APPROVED' => 'ok',
	'RETURNED' => 'bad',
	'REJECTED' => 'bad',
	'DEPUTY_DIRECTOR_REVIEW' => 'wait',
	'CHIEF_ACCOUNTANT_REVIEW' => 'wait',
	'SUBMITTED' => 'wait',
];
$money = static function ($amount) {
	return number_format((float) $amount, 0);
};
?>
<div class="finance-desk">
	<div class="fd-kpis">
		<a class="fd-kpi wait" href="<?= base_url('budget/prepare?tab=review'); ?>">
			<label><?= esc($desk['budget_wait_label'] ?? 'Budgets waiting'); ?></label>
			<strong><?= count($desk['budget_queue'] ?? []); ?></strong>
			<small><?= $money($desk['budget_queue_income'] ?? 0); ?> RWF income in those budgets</small>
		</a>
		<a class="fd-kpi wait" href="<?= base_url('budget/requests?tab=pending'); ?>">
			<label><?= esc($desk['request_wait_label'] ?? 'Requests waiting'); ?></label>
			<strong><?= (int) ($desk['request_queue_count'] ?? 0); ?></strong>
			<small><?= $money($desk['request_queue_amount'] ?? 0); ?> RWF requested</small>
		</a>
		<div class="fd-kpi">
			<label>Approved schools</label>
			<strong><?= (int) ($desk['approved_schools'] ?? 0); ?><span>/<?= (int) ($desk['school_count'] ?? 0); ?></span></strong>
			<small>One approved budget per school</small>
		</div>
		<div class="fd-kpi income">
			<label>Approved income</label>
			<strong><?= $money($group['income'] ?? 0); ?></strong>
			<small>RWF from approved Excel budgets</small>
		</div>
		<div class="fd-kpi expense">
			<label>Approved expenses</label>
			<strong><?= $money($group['expense'] ?? 0); ?></strong>
			<small>RWF</small>
		</div>
		<div class="fd-kpi <?= ((float) ($group['surplus'] ?? 0)) >= 0 ? 'income' : 'expense'; ?>">
			<label>Surplus</label>
			<strong><?= $money($group['surplus'] ?? 0); ?></strong>
			<small>Income minus expenses</small>
		</div>
		<a class="fd-kpi" href="<?= base_url('budget/requests?tab=payments'); ?>">
			<label>Money allowed</label>
			<strong><?= $money($desk['money_allowed'] ?? 0); ?></strong>
			<small><?= (int) ($desk['money_allowed_count'] ?? 0); ?> request<?= ((int) ($desk['money_allowed_count'] ?? 0)) === 1 ? '' : 's'; ?> signed by Finance</small>
		</a>
		<div class="fd-kpi">
			<label>Paid</label>
			<strong><?= $money($desk['paid'] ?? 0); ?></strong>
			<small>RWF already paid</small>
		</div>
	</div>

	<div class="fd-section">
		<h3>Approved budgets by term</h3>
		<?= view('pages/budget/partials/term_kpi_board', ['term_figures' => $group['terms'] ?? []]); ?>
	</div>

	<div class="fd-split">
		<section class="fd-panel">
			<header>
				<h3><?= esc($desk['budget_wait_label'] ?? 'Budgets'); ?></h3>
				<a href="<?= base_url('budget/prepare?tab=review'); ?>">Open review</a>
			</header>
			<?php if (empty($desk['budget_queue'])) { ?>
			<p class="fd-empty">No budget is waiting for you.</p>
			<?php } else { ?>
			<ul class="fd-list">
				<?php foreach ($desk['budget_queue'] as $item) { ?>
				<li>
					<div>
						<strong><?= esc($item['school']); ?></strong>
						<span><?= esc($statusLabels[$item['status']] ?? $item['status']); ?> · Income <?= $money($item['income']); ?> · Expenses <?= $money($item['expense']); ?></span>
					</div>
					<a class="btn btn-sm btn-primary" href="<?= base_url('budget/edit_budget/' . (int) $item['id']); ?>">Open</a>
				</li>
				<?php } ?>
			</ul>
			<?php } ?>
		</section>
		<section class="fd-panel">
			<header>
				<h3><?= esc($desk['request_wait_label'] ?? 'Requests'); ?></h3>
				<a href="<?= base_url('budget/requests?tab=pending'); ?>">Open requests</a>
			</header>
			<?php if (empty($desk['request_queue'])) { ?>
			<p class="fd-empty">No cash request is waiting for you.</p>
			<?php } else { ?>
			<ul class="fd-list">
				<?php foreach ($desk['request_queue'] as $item) { ?>
				<li>
					<div>
						<strong><?= esc($item['request_no'] ?: ('Request #' . $item['id'])); ?></strong>
						<span><?= esc($item['school']); ?><?= $item['purpose'] !== '' ? ' · ' . esc($item['purpose']) : ''; ?></span>
					</div>
					<div class="fd-list-end">
						<b><?= $money($item['amount']); ?></b>
						<a class="btn btn-sm btn-primary" href="<?= base_url('budget/cash_request_view/' . (int) $item['id']); ?>">Open</a>
					</div>
				</li>
				<?php } ?>
			</ul>
			<?php } ?>
		</section>
	</div>

	<div class="fd-section">
		<h3>Schools</h3>
		<div class="table-responsive fd-table-wrap">
			<table class="fd-table">
				<thead>
					<tr>
						<th>School</th>
						<th>Budget</th>
						<th class="num">Income</th>
						<th class="num">Expenses</th>
						<th class="num">Surplus</th>
						<th class="num">Term I</th>
						<th class="num">Term II</th>
						<th class="num">Term III</th>
						<th>Waiting</th>
					</tr>
				</thead>
				<tbody>
				<?php if (!$schools) { ?>
					<tr><td colspan="9" class="fd-empty">No schools are linked to this account.</td></tr>
				<?php } ?>
				<?php foreach ($schools as $school) {
					$st = (string) ($school['status'] ?? '');
					$net = (float) ($school['surplus'] ?? 0);
					$termNet = static function ($terms, $n) {
						$inc = (float) ($terms[$n]['income'] ?? 0);
						$exp = (float) ($terms[$n]['expense'] ?? 0);
						return $inc - $exp;
					};
				?>
					<tr>
						<td>
							<strong><?= esc($school['school']); ?></strong>
							<?php if (!empty($school['budget_id'])) { ?>
							<div><a href="<?= base_url('budget/edit_budget/' . (int) $school['budget_id']); ?>"><?= esc($school['title'] ?: 'Open budget'); ?></a></div>
							<?php } ?>
						</td>
						<td><span class="fd-status <?= esc($statusClass[$st] ?? 'idle'); ?>"><?= esc($st === '' ? 'No budget' : ($statusLabels[$st] ?? $st)); ?></span></td>
						<td class="num"><?= $school['budget_id'] ? $money($school['income']) : '—'; ?></td>
						<td class="num"><?= $school['budget_id'] ? $money($school['expense']) : '—'; ?></td>
						<td class="num <?= $net >= 0 ? 'pos' : 'neg'; ?>"><?= $school['budget_id'] ? $money($net) : '—'; ?></td>
						<td class="num"><?= $school['budget_id'] ? $money($termNet($school['terms'], 1)) : '—'; ?></td>
						<td class="num"><?= $school['budget_id'] ? $money($termNet($school['terms'], 2)) : '—'; ?></td>
						<td class="num"><?= $school['budget_id'] ? $money($termNet($school['terms'], 3)) : '—'; ?></td>
						<td>
							<?php if (!empty($school['needs_you_budget'])) { ?><span class="fd-pill">Budget</span><?php } ?>
							<?php if (!empty($school['needs_you_request'])) { ?><span class="fd-pill"><?= (int) $school['needs_you_request']; ?> request<?= (int) $school['needs_you_request'] === 1 ? '' : 's'; ?></span><?php } ?>
							<?php if (empty($school['needs_you_budget']) && empty($school['needs_you_request'])) { ?><span class="text-muted">—</span><?php } ?>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
