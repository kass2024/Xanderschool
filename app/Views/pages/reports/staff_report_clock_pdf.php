<?php
/**
 * mPDF staff clock-in / clock-out list. No KPI cards.
 *
 * @var list<array<string,mixed>> $summaries
 * @var array $ayBounds
 * @var string $date1
 * @var string $date2
 * @var string $logoSrc
 * @var list<string> $contactBits
 */
$singleDay = ($date1 === $date2);
$periodLabel = $singleDay ? $date1 : ($date1 . '  →  ' . $date2);
$pdfTitle = $singleDay
	? 'Staff clock-in / clock-out'
	: 'Staff clock-in / clock-out register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?= esc($pdfTitle); ?></title>
	<style>
		body { font-family: dejavusanscondensed, sans-serif; color: #0f172a; font-size: 9.5pt; }
		table.hdr { width: 100%; border-collapse: collapse; border: 2px solid #012F6B; margin-bottom: 8px; }
		table.hdr td { vertical-align: middle; padding: 8px 10px; }
		.school { font-size: 14pt; font-weight: bold; color: #012F6B; text-transform: uppercase; }
		.slogan { font-style: italic; color: #475569; font-size: 9pt; }
		.contact { color: #64748b; font-size: 8pt; }
		.meta { text-align: right; font-size: 8.5pt; color: #334155; }
		.title { text-align: center; font-size: 13pt; font-weight: bold; color: #012F6B; margin: 4px 0 0; }
		.sub { text-align: center; color: #64748b; font-size: 9pt; margin: 0 0 10px; }
		table.clk { width: 100%; border-collapse: collapse; }
		table.clk thead { display: table-header-group; }
		table.clk th {
			background-color: #012F6B; color: #ffffff; font-size: 8pt; text-transform: uppercase;
			padding: 6px 6px; text-align: left; border: 0.4pt solid #012F6B; font-weight: bold;
		}
		table.clk td { border: 0.4pt solid #cbd5e1; padding: 5px 6px; font-size: 9pt; vertical-align: middle; }
		table.clk tr.alt td { background-color: #f8fafc; }
		table.clk tr.miss td { background-color: #fff7ed; }
		table.clk tr.leave td { background-color: #f0f9ff; }
		.name { font-weight: bold; color: #0f172a; }
		.post { color: #64748b; font-size: 8pt; }
		.in { color: #047857; font-weight: bold; }
		.out { color: #b91c1c; font-weight: bold; }
		.nco { color: #c2410c; font-weight: bold; }
		.st-present { color: #047857; font-weight: bold; }
		.st-late { color: #c2410c; font-weight: bold; }
		.st-absent { color: #b91c1c; font-weight: bold; }
		.st-leave { color: #0369a1; font-weight: bold; }
		.st-nco { color: #c2410c; font-weight: bold; }
		.center { text-align: center; }
		.staffhead { background-color: #012F6B; color: #ffffff; font-size: 10pt; text-transform: none; }
		.staffmeta { font-weight: normal; color: #dbeafe; font-size: 8.5pt; }
		.block { page-break-inside: avoid; margin-bottom: 8px; }
	</style>
</head>
<body>
<table class="hdr">
	<tr>
		<td width="78" style="width:78px; text-align:center;">
			<?php if (!empty($logoSrc)) : ?>
				<img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" width="64" height="64" alt="Logo" style="width:64px; height:64px;">
			<?php endif; ?>
		</td>
		<td>
			<div class="school"><?= esc($school_name ?? ''); ?></div>
			<?php if (!empty($school_moto)) : ?>
				<div class="slogan"><?= esc($school_moto); ?></div>
			<?php endif; ?>
			<?php if (!empty($contactBits)) : ?>
				<div class="contact"><?= esc(implode('  •  ', $contactBits)); ?></div>
			<?php endif; ?>
		</td>
		<td class="meta" width="190">
			<div><?= esc($ayBounds['label'] ?? ($academic_year_title ?? '')); ?></div>
			<div><?= esc($periodLabel); ?></div>
			<div>Printed <?= date('Y-m-d H:i'); ?></div>
			<div><?= count($summaries); ?> staff</div>
		</td>
	</tr>
</table>

<div class="title"><?= esc($pdfTitle); ?></div>
<div class="sub"><?= esc($periodLabel); ?><?php if (!empty($ayBounds['label'])) : ?> · <?= esc($ayBounds['label']); ?><?php endif; ?></div>

<?php if (count($summaries) === 0) : ?>
	<p class="center">No staff found for this period.</p>
<?php elseif ($singleDay) : ?>
	<table class="clk">
		<thead>
		<tr>
			<th width="6%">#</th>
			<th width="28%">Staff</th>
			<th width="22%">Post / shift</th>
			<th width="12%">Clock in</th>
			<th width="12%">Clock out</th>
			<th width="8%">Hours</th>
			<th width="12%">Status</th>
		</tr>
		</thead>
		<tbody>
		<?php $n = 1; foreach ($summaries as $i => $sum) :
			$day = null;
			foreach (($sum['days'] ?? []) as $d) {
				$day = $d;
				break;
			}
			$code = (string) ($day['code'] ?? ((int) $sum['absent'] > 0 ? 'absent' : 'present'));
			$rowClass = in_array($code, ['absent', 'nco'], true) ? 'miss' : ($code === 'leave' ? 'leave' : (($i % 2) === 1 ? 'alt' : ''));
			$in = trim((string) ($day['in'] ?? ''));
			$out = trim((string) ($day['out'] ?? ''));
			?>
			<tr class="<?= $rowClass; ?>">
				<td class="center"><?= $n++; ?></td>
				<td class="name"><?= esc($sum['name']); ?></td>
				<td>
					<?= esc($sum['post'] ?: 'Staff'); ?>
					<?php if (!empty($sum['shift'])) : ?><div class="post"><?= esc($sum['shift']); ?></div><?php endif; ?>
				</td>
				<td><?php if ($in !== '') : ?><span class="in"><?= esc($in); ?></span><?php else : ?>—<?php endif; ?></td>
				<td>
					<?php if ($out !== '') : ?>
						<span class="out"><?= esc($out); ?></span>
					<?php elseif ($code === 'nco') : ?>
						<span class="nco">No checkout</span>
					<?php else : ?>
						—
					<?php endif; ?>
				</td>
				<td><?= esc(($day['duration'] ?? '') !== '' && ($day['duration'] ?? '') !== '—' ? $day['duration'] : '—'); ?></td>
				<td class="st-<?= esc($code); ?>"><?= esc($day['label_status'] ?? ($code === 'absent' ? 'Absent' : '—')); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<?php foreach ($summaries as $sum) : ?>
		<div class="block">
			<table class="clk">
				<thead>
				<tr>
					<th colspan="6" class="staffhead">
						<?= esc($sum['name']); ?>
						<span class="staffmeta">
							<?= esc($sum['post'] ?: 'Staff'); ?>
							<?php if (!empty($sum['shift'])) : ?> · <?= esc($sum['shift']); ?><?php endif; ?>
							· Present <?= (int) $sum['present']; ?>
							· Absent <?= (int) $sum['absent']; ?>
							· Late <?= (int) $sum['late_count']; ?>
						</span>
					</th>
				</tr>
				<tr>
					<th width="8%">#</th>
					<th width="28%">Date</th>
					<th width="16%">Clock in</th>
					<th width="16%">Clock out</th>
					<th width="12%">Hours</th>
					<th width="20%">Status</th>
				</tr>
				</thead>
				<tbody>
				<?php
				$days = $sum['days'] ?? [];
				if ($days === []) : ?>
					<tr><td colspan="6" class="center">No scheduled working days in this period.</td></tr>
				<?php else :
					$n = 1;
					foreach ($days as $di => $d) :
						$code = (string) ($d['code'] ?? 'present');
						$rowClass = in_array($code, ['absent', 'nco'], true) ? 'miss' : ($code === 'leave' ? 'leave' : (($di % 2) === 1 ? 'alt' : ''));
						$in = trim((string) ($d['in'] ?? ''));
						$out = trim((string) ($d['out'] ?? ''));
						?>
						<tr class="<?= $rowClass; ?>">
							<td class="center"><?= $n++; ?></td>
							<td><?= esc($d['label'] ?? ''); ?></td>
							<td><?php if ($in !== '') : ?><span class="in"><?= esc($in); ?></span><?php else : ?>—<?php endif; ?></td>
							<td>
								<?php if ($out !== '') : ?>
									<span class="out"><?= esc($out); ?></span>
								<?php elseif ($code === 'nco') : ?>
									<span class="nco">No checkout</span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?= esc(($d['duration'] ?? '') !== '' ? $d['duration'] : '—'); ?></td>
							<td class="st-<?= esc($code); ?>"><?= esc($d['label_status'] ?? ''); ?></td>
						</tr>
					<?php endforeach;
				endif; ?>
				</tbody>
			</table>
		</div>
	<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
