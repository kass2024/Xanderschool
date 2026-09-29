<?php
/**
 * Landscape mPDF: overall / absent staff attendance with school letterhead.
 *
 * @var list<array<string,mixed>> $summaries
 * @var array<string,list<array<string,mixed>>> $byShift
 * @var array<string,int|float> $org
 * @var array $ayBounds
 * @var string $date1
 * @var string $date2
 * @var string $logoSrc
 * @var list<string> $contactBits
 * @var string $reportTitle
 * @var string $reportType
 */
$sameMonth = substr((string) $date1, 0, 7) === substr((string) $date2, 0, 7)
	&& substr((string) $date1, 8, 2) === '01';
$periodLabel = $sameMonth
	? date('F Y', strtotime($date1))
	: (date('j M Y', strtotime($date1)) . ' – ' . date('j M Y', strtotime($date2)));
$ayLabel = (string) ($ayBounds['label'] ?? ($academic_year_title ?? ''));
$groups = ($reportType ?? '') === 'overall'
	? ($byShift ?? [])
	: ['Staff with absence' => $summaries ?? []];

$rateColor = static function (int $pct): string {
	if ($pct >= 90) {
		return '#047857';
	}
	if ($pct >= 80) {
		return '#c2410c';
	}
	return '#b91c1c';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?= esc($reportTitle ?? 'Staff attendance'); ?></title>
	<style>
		body { font-family: dejavusanscondensed, sans-serif; color: #0f172a; font-size: 8.5pt; }
		table.brand { width: 100%; border-collapse: collapse; border-bottom: 2.5px solid #012F6B; }
		table.brand td { vertical-align: middle; padding: 0 4px 4px 0; }
		.school { font-size: 13pt; font-weight: bold; color: #012F6B; text-transform: uppercase; letter-spacing: 0.3px; }
		.slogan { font-style: italic; color: #475569; font-size: 8pt; }
		.contact { color: #64748b; font-size: 7.5pt; }
		.brand-meta { text-align: right; color: #012F6B; font-size: 8pt; }
		.brand-meta .ttl { font-size: 11pt; font-weight: bold; }
		table.kpis { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin: 8px 0 6px; }
		table.kpis td { background-color: #f8fafc; border: 0.6pt solid #e2e8f0; padding: 5px 6px; width: 12.5%; }
		.k { display: block; font-size: 6.5pt; font-weight: bold; text-transform: uppercase; color: #64748b; }
		.v { display: block; font-size: 12pt; font-weight: bold; color: #0f172a; }
		.v.in { color: #047857; }
		.v.out { color: #b91c1c; }
		.v.warn { color: #c2410c; }
		.v.navy { color: #012F6B; }
		.note { font-size: 7.5pt; color: #475569; margin: 0 0 8px; }
		.shiftbar { background-color: #012F6B; color: #ffffff; font-size: 9pt; font-weight: bold; padding: 4px 8px; margin: 8px 0 0; }
		.shiftmeta { font-weight: normal; color: #dbeafe; font-size: 7.5pt; }
		table.sum { width: 100%; border-collapse: collapse; margin: 0 0 4px; }
		table.sum thead { display: table-header-group; }
		table.sum th {
			background-color: #e8eef7; color: #012F6B; font-size: 7pt; text-transform: uppercase;
			padding: 4px 3px; text-align: center; border: 0.4pt solid #cbd5e1; font-weight: bold;
		}
		table.sum th.left { text-align: left; }
		table.sum td { border: 0.4pt solid #e2e8f0; padding: 4px 4px; font-size: 8pt; vertical-align: middle; line-height: 1.25; }
		table.sum td.c { text-align: center; }
		table.sum tr.alt td { background-color: #f8fafc; }
		table.sum tr.miss td { background-color: #fff7ed; }
		table.sum tr.total td { background-color: #012F6B; color: #ffffff; font-weight: bold; border-color: #012F6B; }
		.name { font-weight: bold; }
		.sub { color: #64748b; font-size: 7pt; }
		.bad { color: #b91c1c; font-weight: bold; }
		.gap { color: #c2410c; font-weight: bold; }
		.ok { color: #047857; font-weight: bold; }
		.sched { font-weight: bold; color: #012F6B; font-size: 10pt; }
	</style>
</head>
<body>
<htmlpageheader name="schoolbrand">
	<table class="brand">
		<tr>
			<td width="52" style="width:52px;">
				<?php if (!empty($logoSrc)) : ?>
					<img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" width="46" height="46" alt="" style="width:46px;height:46px;">
				<?php endif; ?>
			</td>
			<td>
				<div class="school"><?= esc($school_name ?? ''); ?></div>
				<?php if (!empty($school_moto)) : ?>
					<div class="slogan"><?= esc($school_moto); ?></div>
				<?php endif; ?>
				<?php if (!empty($contactBits)) : ?>
					<div class="contact"><?= esc(implode('   ·   ', $contactBits)); ?></div>
				<?php endif; ?>
			</td>
			<td class="brand-meta" width="210">
				<div class="ttl"><?= esc($reportTitle ?? 'Overall attendance report'); ?></div>
				<div><?= esc($periodLabel); ?><?php if ($ayLabel !== '') : ?> · <?= esc($ayLabel); ?><?php endif; ?></div>
				<div><?= count($summaries ?? []); ?> staff · Printed <?= date('j M Y H:i'); ?></div>
			</td>
		</tr>
	</table>
</htmlpageheader>
<sethtmlpageheader name="schoolbrand" value="on" show-this-page="1" />

<table class="kpis">
	<tr>
		<td><div class="k">Staff</div><div class="v navy"><?= (int) ($org['staff'] ?? 0); ?></div></td>
		<td><div class="k">Scheduled days</div><div class="v navy"><?= (int) ($org['scheduled'] ?? 0); ?></div></td>
		<td><div class="k">Attendance</div><div class="v in"><?= (int) ($org['attendance_rate'] ?? 0); ?>%</div></td>
		<td><div class="k">Absenteeism</div><div class="v out"><?= (int) ($org['absenteeism'] ?? 0); ?>%</div></td>
		<td><div class="k">Clock in</div><div class="v in"><?= (int) ($org['clock_in'] ?? 0); ?></div></td>
		<td><div class="k">Clock out</div><div class="v"><?= (int) ($org['clock_out'] ?? 0); ?></div></td>
		<td><div class="k">Missing clock-out</div><div class="v warn"><?= (int) ($org['nco'] ?? 0); ?></div></td>
		<td><div class="k">Absent days</div><div class="v out"><?= (int) ($org['absent'] ?? 0); ?></div></td>
	</tr>
</table>
<p class="note">Scheduled is that shift's working days in this period, so everyone on the shift has the same number. Attendance = (clock-in + clock-out) / (scheduled days × 2). A missing clock-out cannot be 100%.</p>

<?php if (count($summaries ?? []) === 0) : ?>
	<p class="note">No staff found for this period.</p>
<?php else : ?>
	<?php foreach ($groups as $shiftName => $rows) :
		if ($rows === []) {
			continue;
		}
		$shiftKpi = \App\Libraries\StaffAttendanceReport::orgKpis($rows);
		$pattern = trim((string) ($rows[0]['shift_pattern'] ?? ''));
		?>
		<div class="shiftbar">
			<?= esc($shiftName); ?>
			<span class="shiftmeta">
				<?php if ($pattern !== '') : ?> · <?= esc($pattern); ?><?php endif; ?>
				· <?= count($rows); ?> staff
				· Attendance <?= (int) $shiftKpi['attendance_rate']; ?>%
				· Clock in <?= (int) $shiftKpi['clock_in']; ?> / out <?= (int) $shiftKpi['clock_out']; ?>
				<?php if ((int) $shiftKpi['nco'] > 0) : ?> · <?= (int) $shiftKpi['nco']; ?> missing clock-out<?php endif; ?>
			</span>
		</div>
		<table class="sum">
			<thead>
			<tr>
				<th width="4%">#</th>
				<th class="left" width="16%">Staff</th>
				<th class="left" width="12%">Post</th>
				<th width="12%">Scheduled</th>
				<th width="7%">Present</th>
				<th width="7%">Absent</th>
				<th width="6%">Leave</th>
				<th width="6%">Late</th>
				<th width="9%">Hours</th>
				<th width="7%">Clock in</th>
				<th width="8%">Clock out</th>
				<th width="6%">Attend.</th>
			</tr>
			</thead>
			<tbody>
			<?php $n = 1; foreach ($rows as $i => $r) :
				$clockIn = (int) ($r['clock_in'] ?? 0);
				$clockOut = (int) ($r['clock_out'] ?? 0);
				$gap = max(0, $clockIn - $clockOut);
				$miss = (int) $r['absent'] > 0 || $gap > 0;
				$rowClass = $miss ? 'miss' : ((($i % 2) === 1) ? 'alt' : '');
				?>
				<tr class="<?= $rowClass; ?>">
					<td class="c"><?= $n++; ?></td>
					<td><span class="name"><?= esc($r['name']); ?></span><br><span class="sub">ID <?= (int) $r['id']; ?></span></td>
					<td><?= esc($r['post'] ?: '—'); ?></td>
					<td class="c">
						<span class="sched"><?= (int) $r['scheduled']; ?></span><br>
						<?php if (trim((string) ($r['shift_pattern'] ?? '')) !== '') : ?>
							<span class="sub"><?= esc($r['shift_pattern']); ?></span>
						<?php else : ?>
							<span class="sub">No shift</span>
						<?php endif; ?>
						<?php if ((int) ($r['elapsed'] ?? $r['scheduled']) < (int) $r['scheduled']) : ?>
							<br><span class="sub"><?= (int) $r['elapsed']; ?> due so far</span>
						<?php endif; ?>
					</td>
					<td class="c"><?= (int) $r['present']; ?></td>
					<td class="c<?= (int) $r['absent'] > 0 ? ' bad' : ''; ?>"><?= (int) $r['absent']; ?></td>
					<td class="c"><?= (int) $r['leave']; ?></td>
					<td class="c"><?= (int) $r['late_count']; ?></td>
					<td class="c"><?= esc($r['hours_worked']); ?></td>
					<td class="c ok"><?= $clockIn; ?></td>
					<td class="c">
						<?php if ($gap > 0) : ?>
							<span class="gap"><?= $clockOut; ?></span><br><span class="sub gap"><?= $gap; ?> missing</span>
						<?php else : ?>
							<?= $clockOut; ?>
						<?php endif; ?>
					</td>
					<td class="c" style="color:<?= $rateColor((int) $r['attendance_rate']); ?>;font-weight:bold;"><?= (int) $r['attendance_rate']; ?>%</td>
				</tr>
			<?php endforeach; ?>
			<tr class="total">
				<td></td>
				<td colspan="2"><?= count($rows); ?> staff</td>
				<td class="c"><?= (int) $shiftKpi['scheduled']; ?></td>
				<td class="c"><?= (int) $shiftKpi['present']; ?></td>
				<td class="c"><?= (int) $shiftKpi['absent']; ?></td>
				<td class="c"><?= (int) $shiftKpi['leave']; ?></td>
				<td class="c"><?= (int) $shiftKpi['late']; ?></td>
				<td class="c"><?= esc((string) $shiftKpi['hours']); ?>h</td>
				<td class="c"><?= (int) $shiftKpi['clock_in']; ?></td>
				<td class="c"><?= (int) $shiftKpi['clock_out']; ?></td>
				<td class="c"><?= (int) $shiftKpi['attendance_rate']; ?>%</td>
			</tr>
			</tbody>
		</table>
	<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
