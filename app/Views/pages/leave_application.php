<?php $obj = new App\Controllers\Home(); ?>
<style>
	.leave-board { padding: 8px 18px 24px; }
	.leave-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
	.leave-top h2 { margin: 0; font-size: 22px; color: #0f2744; font-weight: 700; }
	.leave-top p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
	.leave-apply {
		background: #0f766e; color: #fff; border: 0; border-radius: 999px; padding: 10px 18px;
		font-weight: 700; box-shadow: 0 8px 18px rgba(15, 118, 110, .25);
	}
	.leave-apply:hover { color: #fff; background: #115e59; }
	.leave-card { border: 0; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 39, 68, .06); overflow: hidden; }
	.leave-card .card-body { padding: 8px 8px 16px; }
	.leave-table thead th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; border-top: 0; }
	.leave-table td { vertical-align: middle; color: #1e293b; }
	.leave-table tr.is-approved td:first-child { box-shadow: inset 3px 0 0 #16a34a; }
	.leave-table tr.is-pending td:first-child { box-shadow: inset 3px 0 0 #d97706; }
	.leave-table tr.is-denied td:first-child { box-shadow: inset 3px 0 0 #dc2626; }
	.leave-pill { display: inline-block; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 700; }
	.leave-pill.is-ok { background: #dcfce7; color: #166534; }
	.leave-pill.is-wait { background: #fef3c7; color: #92400e; }
	.leave-pill.is-no { background: #fee2e2; color: #991b1b; }
	.leave-type { font-weight: 700; color: #0f2744; }
</style>
<div class="leave-board">
	<div class="leave-top">
		<div>
			<h2><?= esc($title); ?></h2>
			<p>Ask for leave, or a short permission of a few hours or up to 3 days. The gate turns green only after approval, and only while the tablet is online.</p>
		</div>
		<button class="leave-apply" data-toggle="modal" data-target="#apply_leave_model">+ <?= lang("app.applyforLeave"); ?></button>
	</div>
	<div class="card leave-card mb-3">
		<div class="card-body">
			<div class="table-responsive">
				<table style="width: 100%;" id="example" class="table table-hover leave-table dataTable" role="grid">
					<thead>
					<tr>
						<th>#</th>
						<th><?= lang("app.names"); ?></th>
						<th><?= lang("app.leaveType"); ?></th>
						<th><?= lang("app.reason"); ?></th>
						<th><?= lang("app.leaveFrom"); ?></th>
						<th><?= lang("app.leaveTo"); ?></th>
						<th><?= lang("app.address"); ?></th>
						<th><?= lang("app.status"); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php $i = 1; foreach ($leaves as $leave) {
						$code = (int) $leave['status'];
						$row = $code === 1 ? 'is-approved' : ($code === 2 ? 'is-denied' : 'is-pending');
					?>
						<tr class="<?= $row; ?>">
							<td><?= $i; ?></td>
							<td><?= esc($leave['staff']); ?></td>
							<td class="leave-type"><?= esc($obj->leaveTypeTostr($leave['type'])); ?></td>
							<td><?= esc($leave['reason']); ?></td>
							<td><?= esc($obj->leaveFromLabel($leave)); ?></td>
							<td><?= esc($obj->leaveToLabel($leave)); ?></td>
							<td><?= esc($leave['address']); ?></td>
							<td><?= $obj->leaveStatusBadge($leave['status']); ?></td>
						</tr>
					<?php $i++; } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
