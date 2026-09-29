<?php
$prepDays = $prep_days ?? [];
$filterDay = (int) ($default_day ?? 0);
if ($prepDays === []) {
	return;
}
?>
<div class="io-letter" style="margin-bottom:16px;">
	<h4 style="margin-bottom:6px;">Invigilators</h4>
	<p style="color:#64748b;margin:0 0 12px;">Staff on the prep rota must tap their card at the Morning prep or Evening prep reader. <strong>Present</strong> means they tapped. <strong>Absent</strong> means they were on duty and did not tap.</p>
	<?php foreach ($prepDays as $pd):
		$dayNum = (int) ($pd['day_num'] ?? 0);
		$hidden = $filterDay > 0 && $dayNum !== $filterDay;
		?>
		<div class="io-inv-block" data-day="<?= $dayNum; ?>"<?= $hidden ? ' style="display:none"' : ''; ?>>
			<div style="font-weight:700;margin:12px 0 8px;"><?= esc((string) ($pd['label'] ?? '')); ?></div>
			<?php foreach (($pd['slots'] ?? []) as $slotKey => $slot):
				$present = $slot['present'] ?? [];
				$absent = $slot['absent'] ?? [];
				if ($present === [] && $absent === []) {
					continue;
				}
				$slotLabel = $slotKey === 'evening' ? 'Evening prep' : 'Morning prep';
				?>
				<div style="margin:0 0 12px;">
					<div style="font-weight:600;margin-bottom:6px;"><?= esc($slotLabel); ?>
						<span style="font-weight:500;color:#047857;">Present <?= count($present); ?></span>
						<span style="font-weight:500;color:#b91c1c;margin-left:8px;">Absent <?= count($absent); ?></span>
					</div>
					<table class="io-ex-table">
						<thead>
						<tr>
							<th>Staff</th>
							<th>Post</th>
							<th>Status</th>
							<th>Tap</th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($present as $person): ?>
							<tr>
								<td><?= esc((string) ($person['name'] ?? '')); ?></td>
								<td><?= esc((string) ($person['post'] ?? '')); ?></td>
								<td><span class="io-st present">Present</span></td>
								<td><?= esc((string) ($person['time'] ?? '')); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php foreach ($absent as $person): ?>
							<tr>
								<td><?= esc((string) ($person['name'] ?? '')); ?></td>
								<td><?= esc((string) ($person['post'] ?? '')); ?></td>
								<td><span class="io-st absent">Absent</span></td>
								<td>—</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</div>
