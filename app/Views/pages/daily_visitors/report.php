<?php
$visits = $visits ?? [];
$summary = $summary ?? ['total' => 0, 'inside' => 0, 'checked_out' => 0];
$fromDate = $from_date ?? date('Y-m-d');
$toDate = $to_date ?? date('Y-m-d');
?>
<style>
.gv-rpt { --navy:#0b1f4a; }
.gv-toolbar { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; margin-bottom:16px; }
.gv-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:16px; }
.gv-stat { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; text-align:center; }
.gv-stat b { display:block; font-size:1.5rem; color:var(--navy); }
.gv-stat span { font-size:.72rem; color:#64748b; text-transform:uppercase; }
.gv-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.gv-table { width:100%; margin:0; font-size:.86rem; }
.gv-table th, .gv-table td { padding:9px 11px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
.gv-table th { background:#f8fafc; font-size:.72rem; text-transform:uppercase; color:#64748b; }
.gv-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:.72rem; font-weight:700; }
.gv-badge.in { background:#dcfce7; color:#166534; }
.gv-badge.out { background:#e2e8f0; color:#475569; }
.gv-card-uid { font-family:ui-monospace,monospace; }
</style>

<div class="container-fluid mt-3 gv-rpt">
	<form class="gv-toolbar" method="get" action="<?= base_url('daily_visitors/report') ?>">
		<div class="form-row align-items-end">
			<div class="form-group col-md-3 mb-2">
				<label>From</label>
				<input type="date" name="from" class="form-control" value="<?= esc($fromDate) ?>">
			</div>
			<div class="form-group col-md-3 mb-2">
				<label>To</label>
				<input type="date" name="to" class="form-control" value="<?= esc($toDate) ?>">
			</div>
			<div class="form-group col-md-3 mb-2">
				<button type="submit" class="btn btn-primary">Filter</button>
				<a class="btn btn-light" href="<?= base_url('daily_visitors/report') ?>">Today</a>
			</div>
		</div>
	</form>

	<div class="gv-stats">
		<div class="gv-stat"><b><?= (int) $summary['total'] ?></b><span>Visits</span></div>
		<div class="gv-stat"><b><?= (int) $summary['inside'] ?></b><span>Still inside</span></div>
		<div class="gv-stat"><b><?= (int) $summary['checked_out'] ?></b><span>Checked out</span></div>
	</div>

	<div class="gv-card">
		<div class="table-responsive">
			<table class="gv-table">
				<thead>
					<tr>
						<th>Date</th>
						<th>Name</th>
						<th>Phone</th>
						<th>Reason</th>
						<th>Materials brought</th>
						<th>Card</th>
						<th>In</th>
						<th>Out</th>
						<th>Duration</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($visits)) { ?>
						<tr><td colspan="10" class="text-muted text-center py-4">No daily visitors in this period.</td></tr>
					<?php } else { foreach ($visits as $row) { ?>
						<tr>
							<td><?= esc($row['visit_date']) ?></td>
							<td><strong><?= esc($row['names']) ?></strong></td>
							<td><?= esc($row['phone']) ?></td>
							<td><?= esc($row['reason']) ?></td>
							<td><?= esc($row['materials'] ?: '—') ?></td>
							<td class="gv-card-uid"><?= esc($row['card']) ?></td>
							<td><?= esc($row['time_in_label']) ?></td>
							<td><?= esc($row['time_out_label'] ?: '—') ?></td>
							<td><?= (int) $row['duration_minutes'] ?> min</td>
							<td>
								<?php if (!empty($row['inside'])) { ?>
									<span class="gv-badge in">Inside</span>
								<?php } else { ?>
									<span class="gv-badge out">Left</span>
								<?php } ?>
							</td>
						</tr>
					<?php } } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
