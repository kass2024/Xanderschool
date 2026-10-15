<?php $obj = new App\Controllers\Home();
$kpi = $leave_kpi ?? ['given' => 0, 'out' => 0, 'returned' => 0, 'unused' => 0];
$pending = 0;
foreach ($leaves as $leaveRow) {
	if ((int) $leaveRow['status'] === 0) {
		$pending++;
	}
}
$fmt = static function ($value): string {
	if ($value === null || $value === '') {
		return '';
	}
	if (is_numeric($value)) {
		return date('d M Y, H:i', (int) $value);
	}
	$ts = strtotime((string) $value);
	return $ts ? date('d M Y, H:i', $ts) : '';
};
?>
<style>
	.leave-board { padding: 8px 18px 24px; }
	.leave-top { display: flex; justify-content: space-between; gap: 16px; align-items: flex-end; margin-bottom: 16px; flex-wrap: wrap; }
	.leave-top h2 { margin: 0; font-size: 22px; color: #0f2744; font-weight: 700; }
	.leave-top p { margin: 4px 0 0; color: #64748b; font-size: 13px; max-width: 640px; }
	.leave-filter { display: flex; gap: 8px; align-items: flex-end; background: #fff; border-radius: 14px; padding: 10px 12px; box-shadow: 0 8px 24px rgba(15, 39, 68, .06); }
	.leave-filter label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
	.leave-filter input { border: 1px solid #d7dee7; border-radius: 10px; padding: 8px 10px; }
	.leave-filter button { border: 0; background: #0f2744; color: #fff; border-radius: 10px; padding: 9px 14px; font-weight: 700; }
	.leave-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
	.leave-kpi { background: #fff; border-radius: 16px; padding: 14px 16px; box-shadow: 0 10px 30px rgba(15, 39, 68, .06); }
	.leave-kpi span { display: block; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
	.leave-kpi strong { display: block; margin-top: 4px; font-size: 28px; color: #0f2744; line-height: 1.1; }
	.leave-kpi.is-out strong { color: #b45309; }
	.leave-kpi.is-back strong { color: #166534; }
	.leave-kpi.is-wait strong { color: #92400e; }
	.leave-card { border: 0; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 39, 68, .06); overflow: hidden; margin-bottom: 16px; }
	.leave-card h3 { margin: 0; padding: 16px 16px 0; font-size: 16px; color: #0f2744; }
	.leave-card .card-body { padding: 8px 8px 16px; }
	.leave-table thead th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; border-top: 0; }
	.leave-table td { vertical-align: middle; color: #1e293b; }
	.leave-table tr.is-approved td:first-child { box-shadow: inset 3px 0 0 #16a34a; }
	.leave-table tr.is-pending td:first-child { box-shadow: inset 3px 0 0 #d97706; }
	.leave-table tr.is-denied td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
	.leave-table tr.is-out td:first-child { box-shadow: inset 3px 0 0 #d97706; }
	.leave-table tr.is-back td:first-child { box-shadow: inset 3px 0 0 #16a34a; }
	.leave-pill { display: inline-block; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 700; }
	.leave-pill.is-ok { background: #dcfce7; color: #166534; }
	.leave-pill.is-wait { background: #fef3c7; color: #92400e; }
	.leave-pill.is-no { background: #fee2e2; color: #991b1b; }
	.leave-pill.is-out { background: #ffedd5; color: #9a3412; }
	.leave-type { font-weight: 700; color: #0f2744; }
	.leave-act { border: 0; border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 700; margin-right: 6px; }
	.leave-act.is-ok { background: #dcfce7; color: #166534; }
	.leave-act.is-no { background: #fee2e2; color: #991b1b; }
	.leave-act:disabled, .leave-act.is-done { opacity: .45; }
	@media (max-width: 1100px) { .leave-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
<div class="leave-board">
	<div class="leave-top">
		<div>
			<h2><?= esc($title); ?></h2>
			<p>One list for every permission in the dates you pick. Approve or deny it here, and see when they went out and came back.</p>
		</div>
		<form class="leave-filter" method="get" action="<?= base_url('leave_management'); ?>">
			<div>
				<label>From</label>
				<input type="date" name="from" value="<?= esc($range_from ?? '', 'attr'); ?>">
			</div>
			<div>
				<label>To</label>
				<input type="date" name="to" value="<?= esc($range_to ?? '', 'attr'); ?>">
			</div>
			<button type="submit">Show</button>
		</form>
	</div>
	<div class="leave-kpis">
		<div class="leave-kpi"><span>Approved in range</span><strong><?= (int) $kpi['given']; ?></strong></div>
		<div class="leave-kpi is-out"><span>Out now</span><strong><?= (int) $kpi['out']; ?></strong></div>
		<div class="leave-kpi is-back"><span>Returned</span><strong><?= (int) $kpi['returned']; ?></strong></div>
		<div class="leave-kpi"><span>Not used yet</span><strong><?= (int) $kpi['unused']; ?></strong></div>
		<div class="leave-kpi is-wait"><span>Waiting approval</span><strong><?= (int) $pending; ?></strong></div>
	</div>
	<?php
		$gateById = [];
		foreach (($gate_rows ?? []) as $gateRow) {
			$gateId = (int) ($gateRow['leave_id'] ?? $gateRow['id'] ?? 0);
			if ($gateId > 0) {
				$gateById[$gateId] = $gateRow;
			}
		}
		$fromTs = strtotime(($range_from ?? '') . ' 00:00:00');
		$toTs = strtotime(($range_to ?? '') . ' 23:59:59');
		$list = [];
		foreach ($leaves as $leave) {
			$leaveId = (int) ($leave['id'] ?? 0);
			$gate = $gateById[$leaveId] ?? null;
			$trip = (string) ($gate['trip'] ?? '');
			$overlaps = $fromTs && $toTs
				&& (int) ($leave['fromDate'] ?? 0) <= $toTs
				&& (int) ($leave['toDate'] ?? 0) >= $fromTs;
			if (!$overlaps && (int) ($leave['status'] ?? 0) !== 0 && $trip !== 'out') {
				continue;
			}
			$leave['went_out_at'] = $gate['went_out_at'] ?? '';
			$leave['returned_at'] = $gate['returned_at'] ?? '';
			$leave['trip'] = $trip;
			$list[] = $leave;
		}
		usort($list, static function ($a, $b) {
			$rank = static function ($row) {
				if ((int) ($row['status'] ?? 0) === 0) {
					return 0;
				}
				if (($row['trip'] ?? '') === 'out') {
					return 1;
				}
				return 2;
			};
			$diff = $rank($a) <=> $rank($b);
			return $diff !== 0 ? $diff : ((int) ($b['fromDate'] ?? 0) <=> (int) ($a['fromDate'] ?? 0));
		});
	?>
	<div class="card leave-card">
		<h3>Approve or deny · <?= esc(date('d M Y', strtotime($range_from))); ?> – <?= esc(date('d M Y', strtotime($range_to))); ?></h3>
		<div class="card-body">
			<div class="table-responsive">
				<table style="width: 100%;" id="example" class="table table-hover leave-table dataTable" role="grid">
					<thead>
					<tr>
						<th>#</th>
						<th><?= lang("app.names"); ?></th>
						<th><?= lang("app.leaveType"); ?></th>
						<th><?= lang("app.reason"); ?></th>
						<th>Permission</th>
						<th>Went out</th>
						<th>Returned</th>
						<th>Gate</th>
						<th><?= lang("app.status"); ?></th>
						<th><?= lang("app.approvedBy"); ?></th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php $i = 1; foreach ($list as $leave) {
						$code = (int) $leave['status'];
						$trip = (string) ($leave['trip'] ?? '');
						if ($code === 1 && $trip === '') {
							$trip = 'unused';
						}
						$open = $code === 0;
						$row = $trip === 'out' ? 'is-out' : ($trip === 'back' ? 'is-back' : ($code === 1 ? 'is-approved' : ($code === 2 ? 'is-denied' : 'is-pending')));
						if ($code !== 1) {
							$gateLabel = '—';
							$gateClass = '';
						} else {
							$gateLabel = $trip === 'back' ? 'Back' : ($trip === 'out' ? 'Still out' : 'Not used');
							$gateClass = $trip === 'back' ? 'is-ok' : ($trip === 'out' ? 'is-out' : 'is-wait');
						}
					?>
						<tr class="<?= $row; ?>">
							<td><?= $i; ?></td>
							<td><?= esc($leave['staff']); ?></td>
							<td class="leave-type"><?= esc($obj->leaveTypeTostr($leave['type'])); ?></td>
							<td><?= esc($leave['reason']); ?></td>
							<td><?= esc($obj->leaveFromLabel($leave)); ?><?= $obj->leaveToLabel($leave) !== '' ? ' – ' . esc($obj->leaveToLabel($leave)) : ''; ?></td>
							<td><?= esc($fmt($leave['went_out_at'] ?? '')); ?></td>
							<td><?= esc($fmt($leave['returned_at'] ?? '')); ?></td>
							<td><?php if ($gateClass !== '') { ?><span class="leave-pill <?= $gateClass; ?>"><?= esc($gateLabel); ?></span><?php } else { echo '—'; } ?></td>
							<td><?= $obj->leaveStatusBadge($leave['status']); ?></td>
							<td><?= $code === 0 || trim((string) ($leave['approver'] ?? '')) === '' ? '—' : esc($leave['approver']); ?></td>
							<td style="white-space:nowrap;">
								<button type="button" class="leave-act is-ok" <?= $open ? '' : 'disabled'; ?>
									data-toggle="modal" data-target="#approve_Modal"
									data-name="<?= esc($leave['staff'], 'attr'); ?>"
									data-id="<?= (int) $leave['id']; ?>"
									data-mail="<?= esc($leave['email'] ?? '', 'attr'); ?>"><?= lang("app.approve"); ?></button>
								<button type="button" class="leave-act is-no" <?= $open ? '' : 'disabled'; ?>
									data-toggle="modal" data-target="#deny_Modal"
									data-id="<?= (int) $leave['id']; ?>"
									data-mail="<?= esc($leave['email'] ?? '', 'attr'); ?>"><?= lang("app.deny"); ?></button>
								<?php if ($code === 1) { ?>
									<a href="<?= base_url('print_leave/' . (int) $leave['id']); ?>" target="_blank" class="btn btn-sm btn-success"><?= lang("app.print"); ?></a>
								<?php } ?>
							</td>
						</tr>
					<?php $i++; } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
