<?php
$inside = $inside ?? [];
$today = $today ?? [];
$counts = $counts ?? ['inside' => 0, 'today' => 0, 'checked_out' => 0];
?>
<style>
.gv-wrap { --navy:#0b1f4a; --ok:#166534; --warn:#b45309; }
.gv-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:16px; }
.gv-stat { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; text-align:center; }
.gv-stat b { display:block; font-size:1.7rem; color:var(--navy); line-height:1.1; }
.gv-stat span { font-size:.75rem; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
.gv-stat.ok b { color:var(--ok); }
.gv-note { background:#fff7ed; border:1px solid #fdba74; color:#9a3412; border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:.9rem; }
.gv-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:16px; }
.gv-card h5 { margin:0; padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:.95rem; color:var(--navy); }
.gv-table { width:100%; margin:0; font-size:.88rem; }
.gv-table th, .gv-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
.gv-table th { background:#fafafa; font-size:.72rem; text-transform:uppercase; color:#64748b; }
.gv-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:.72rem; font-weight:700; }
.gv-badge.in { background:#dcfce7; color:#166534; }
.gv-badge.out { background:#e2e8f0; color:#475569; }
.gv-card-uid { font-family:ui-monospace,monospace; letter-spacing:.04em; }
</style>

<div class="container-fluid mt-3 gv-wrap">
	<div class="gv-note">
		This is the <strong>daily visitor gate</strong> register from the tablet app.
		It is separate from <strong>Parent visiting</strong> (parents of students).
	</div>
	<div class="gv-stats">
		<div class="gv-stat ok"><b id="gvInsideCount"><?= (int) $counts['inside'] ?></b><span>Currently inside</span></div>
		<div class="gv-stat"><b id="gvTodayCount"><?= (int) $counts['today'] ?></b><span>Visits today</span></div>
		<div class="gv-stat"><b id="gvOutCount"><?= (int) $counts['checked_out'] ?></b><span>Checked out today</span></div>
	</div>

	<div class="gv-card">
		<h5><i class="pe-7s-users"></i> Visitors currently inside</h5>
		<div class="table-responsive">
			<table class="gv-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Phone</th>
						<th>Reason</th>
						<th>Materials</th>
						<th>Card</th>
						<th>Time in</th>
					</tr>
				</thead>
				<tbody id="gvInsideBody">
					<?php if (empty($inside)) { ?>
						<tr><td colspan="6" class="text-muted text-center py-4">No visitor is inside right now.</td></tr>
					<?php } else { foreach ($inside as $row) { ?>
						<tr>
							<td><strong><?= esc($row['names']) ?></strong></td>
							<td><?= esc($row['phone']) ?></td>
							<td><?= esc($row['reason']) ?></td>
							<td><?= esc($row['materials'] ?: '—') ?></td>
							<td class="gv-card-uid"><?= esc($row['card']) ?></td>
							<td><?= esc($row['datetime_in']) ?></td>
						</tr>
					<?php } } ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="gv-card">
		<h5>Today's register</h5>
		<div class="table-responsive">
			<table class="gv-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Reason</th>
						<th>Materials</th>
						<th>In</th>
						<th>Out</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody id="gvTodayBody">
					<?php if (empty($today)) { ?>
						<tr><td colspan="6" class="text-muted text-center py-4">No daily visitors recorded today.</td></tr>
					<?php } else { foreach ($today as $row) { ?>
						<tr>
							<td><strong><?= esc($row['names']) ?></strong><br><small><?= esc($row['phone']) ?></small></td>
							<td><?= esc($row['reason']) ?></td>
							<td><?= esc($row['materials'] ?: '—') ?></td>
							<td><?= esc($row['time_in_label']) ?></td>
							<td><?= esc($row['time_out_label'] ?: '—') ?></td>
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
<script>
(function () {
	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}
	function refresh() {
		fetch("<?= base_url('daily_visitors/inside_json') ?>", { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data || !data.board) return;
				var b = data.board;
				var c = b.counts || {};
				document.getElementById('gvInsideCount').textContent = c.inside || 0;
				document.getElementById('gvTodayCount').textContent = c.today || 0;
				document.getElementById('gvOutCount').textContent = c.checked_out || 0;
				var inside = b.inside || [];
				var ib = document.getElementById('gvInsideBody');
				if (!inside.length) {
					ib.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-4">No visitor is inside right now.</td></tr>';
				} else {
					ib.innerHTML = inside.map(function (row) {
						return '<tr><td><strong>' + esc(row.names) + '</strong></td><td>' + esc(row.phone) + '</td><td>'
							+ esc(row.reason) + '</td><td>' + esc(row.materials || '—') + '</td><td class="gv-card-uid">'
							+ esc(row.card) + '</td><td>' + esc(row.datetime_in) + '</td></tr>';
					}).join('');
				}
				var today = b.today || [];
				var tb = document.getElementById('gvTodayBody');
				if (!today.length) {
					tb.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-4">No daily visitors recorded today.</td></tr>';
				} else {
					tb.innerHTML = today.map(function (row) {
						var st = row.inside
							? '<span class="gv-badge in">Inside</span>'
							: '<span class="gv-badge out">Left</span>';
						return '<tr><td><strong>' + esc(row.names) + '</strong><br><small>' + esc(row.phone)
							+ '</small></td><td>' + esc(row.reason) + '</td><td>' + esc(row.materials || '—')
							+ '</td><td>' + esc(row.time_in_label) + '</td><td>' + esc(row.time_out_label || '—')
							+ '</td><td>' + st + '</td></tr>';
					}).join('');
				}
			}).catch(function () {});
	}
	setInterval(refresh, 15000);
})();
</script>
