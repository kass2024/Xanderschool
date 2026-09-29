<?php
$schoolName = trim((string) ($school['name'] ?? 'School'));
$slogan = trim((string) ($school['slogan'] ?? ''));
$contact = array_filter([
	trim((string) ($school['address'] ?? '')),
	trim((string) ($school['phone'] ?? '')) !== '' ? 'Tel ' . trim((string) $school['phone']) : '',
	trim((string) ($school['email'] ?? '')),
]);
$yearTitle = trim((string) ($year_title ?? ''));
$printedAt = trim((string) ($printed_at ?? ''));
$totalStudents = 0;
foreach ($dorms as $dorm) {
	$totalStudents += count($dorm['students'] ?? []);
}
?>
<htmlpageheader name="schoolbrand">
<table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:2px solid #012F6B;">
	<tr>
		<?php if (!empty($logo_src)): ?>
		<td width="64" valign="middle"><img src="<?= esc($logo_src, 'attr'); ?>" style="width:52px;height:52px;"></td>
		<?php endif; ?>
		<td valign="middle">
			<div style="font-size:16px;font-weight:bold;color:#012F6B;"><?= esc($schoolName !== '' ? $schoolName : 'School'); ?></div>
			<?php if ($slogan !== ''): ?><div style="font-size:9px;color:#334155;"><?= esc($slogan); ?></div><?php endif; ?>
			<?php if ($contact !== []): ?><div style="font-size:8px;color:#64748B;"><?= esc(implode('  ·  ', $contact)); ?></div><?php endif; ?>
		</td>
		<td width="180" valign="middle" align="right">
			<div style="font-size:11px;font-weight:bold;color:#012F6B;">Dormitory lists</div>
			<div style="font-size:9px;color:#334155;"><?= esc($yearTitle); ?></div>
			<div style="font-size:8px;color:#64748B;"><?= count($dorms); ?> dormitories · <?= (int) $totalStudents; ?> students</div>
		</td>
	</tr>
</table>
</htmlpageheader>
<sethtmlpageheader name="schoolbrand" value="on" show-this-page="1" />

<?php if ($dorms === []): ?>
<p style="color:#64748B;">No dormitories are set up for this school.</p>
<?php endif; ?>

<?php foreach ($dorms as $index => $dorm):
	$students = $dorm['students'] ?? [];
	$occupied = (int) ($dorm['occupied'] ?? count($students));
	$beds = (int) ($dorm['max_beds'] ?? 0);
	$free = (int) ($dorm['free_beds'] ?? max(0, $beds - $occupied));
?>
<?php if ($index > 0): ?><pagebreak /><?php endif; ?>
<table width="100%" cellpadding="6" cellspacing="0" style="background:#E8EEF8;margin-bottom:8px;">
	<tr>
		<td>
			<div style="font-size:14px;font-weight:bold;color:#012F6B;"><?= esc((string) ($dorm['name'] ?? 'Dormitory')); ?></div>
			<div style="font-size:9px;color:#334155;">
				<?= esc((string) ($dorm['gender_label'] ?? '')); ?>
				<?php if ($yearTitle !== ''): ?> · <?= esc($yearTitle); ?><?php endif; ?>
				· <?= $occupied; ?> student<?= $occupied === 1 ? '' : 's'; ?>
				<?php if ($beds > 0): ?> · <?= $occupied; ?> / <?= $beds; ?> beds · <?= $free; ?> free<?php endif; ?>
			</div>
		</td>
	</tr>
</table>
<table width="100%" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:10px;">
	<thead>
		<tr style="background:#012F6B;color:#ffffff;">
			<th width="6%" align="center">#</th>
			<th width="32%" align="left">Student</th>
			<th width="18%" align="left">Registration no.</th>
			<th width="12%" align="left">Gender</th>
			<th width="32%" align="left">Class</th>
		</tr>
	</thead>
	<tbody>
	<?php if ($students === []): ?>
		<tr><td colspan="5" style="color:#64748B;font-style:italic;padding:8px;">No students assigned to this dormitory.</td></tr>
	<?php else: ?>
		<?php foreach ($students as $i => $student): ?>
		<tr style="background:<?= $i % 2 === 1 ? '#F8FAFC' : '#FFFFFF'; ?>;">
			<td align="center" style="border-bottom:1px solid #E2E8F0;"><?= $i + 1; ?></td>
			<td style="border-bottom:1px solid #E2E8F0;"><?= esc((string) ($student['name'] ?? '')); ?></td>
			<td style="border-bottom:1px solid #E2E8F0;"><?= esc((string) ($student['regno'] ?? '')); ?></td>
			<td style="border-bottom:1px solid #E2E8F0;"><?= esc((string) ($student['gender'] ?? '')); ?></td>
			<td style="border-bottom:1px solid #E2E8F0;"><?= esc((string) ($student['class'] ?? '')); ?></td>
		</tr>
		<?php endforeach; ?>
	<?php endif; ?>
	</tbody>
</table>
<?php endforeach; ?>
