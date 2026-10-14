<?php $obj = new App\Controllers\Home(); ?>
<style>
	.leave-board { padding: 8px 18px 24px; }
	.leave-top { margin-bottom: 16px; }
	.leave-top h2 { margin: 0; font-size: 22px; color: #0f2744; font-weight: 700; }
	.leave-top p { margin: 4px 0 0; color: #64748b; font-size: 13px; }
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
	.leave-act { border: 0; border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 700; margin-right: 6px; }
	.leave-act.is-ok { background: #dcfce7; color: #166534; }
	.leave-act.is-no { background: #fee2e2; color: #991b1b; }
	.leave-act:disabled, .leave-act.is-done { opacity: .45; }
</style>
<div class="leave-board">
	<div class="leave-top">
		<h2><?= esc($title); ?></h2>
		<p>Approve a permission and the staff card may go out. The gate tablet must be online, and it shows a green light only while that permission is active.</p>
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
						<th></th>
						<th></th>
					</tr>
					</thead>
					<tbody>
					<?php $i = 1; foreach ($leaves as $leave) {
						$code = (int) $leave['status'];
						$row = $code === 1 ? 'is-approved' : ($code === 2 ? 'is-denied' : 'is-pending');
						$open = $code === 0;
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
							<td style="white-space:nowrap;">
								<button type="button" class="leave-act is-ok" <?= $open ? '' : 'disabled'; ?>
									data-toggle="modal" data-target="#approve_Modal"
									data-name="<?= esc($leave['staff'], 'attr'); ?>"
									data-id="<?= (int) $leave['id']; ?>"
									data-mail="<?= esc($leave['email'], 'attr'); ?>"><?= lang("app.approve"); ?></button>
								<button type="button" class="leave-act is-no" <?= $open ? '' : 'disabled'; ?>
									data-toggle="modal" data-target="#deny_Modal"
									data-id="<?= (int) $leave['id']; ?>"
									data-mail="<?= esc($leave['email'], 'attr'); ?>"><?= lang("app.deny"); ?></button>
							</td>
							<td>
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
