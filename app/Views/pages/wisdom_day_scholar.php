<?php
$ps = is_array($print_school ?? null) ? $print_school : [];
$logo = basename(trim((string) ($ps['logo'] ?? '')));
$schoolName = trim((string) ($ps['name'] ?? $school_label ?? ''));
$kind = (string) ($kind ?? 'morning');
$counts = is_array($counts ?? null) ? $counts : [];
$schoolId = (int) ($school_id ?? 0);
$accents = [
	'morning' => '#15803d',
	'lunch' => '#b45309',
	'back' => '#1d4ed8',
	'evening' => '#6d28d9',
];
$accent = $accents[$kind] ?? '#1d4ed8';
$tabs = [
	'morning' => ['Arrived', 'Morning'],
	'lunch' => ['Went out', 'Lunch'],
	'back' => ['Returned', 'Back'],
	'evening' => ['Left school', 'Evening'],
];
$clock = static function ($ts): string {
	$ts = (int) $ts;
	return $ts > 0 ? date('H:i', $ts) : '—';
};
$girl = static function ($sex): bool {
	$sex = strtoupper(trim((string) $sex));
	return $sex !== '' && ($sex[0] === 'F' || in_array($sex, ['GIRL', 'GIRLS', 'FEMALE', 'FEMININ', 'FEMININE', 'GORE'], true));
};
?>
<style>
	.wds-page { background: #fff; border-radius: 14px; padding: 18px 18px 24px; }
	.wds-actions { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
	.wds-letter { text-align: center; border-bottom: 3px solid <?= $accent; ?>; padding-bottom: 10px; margin-bottom: 12px; }
	.wds-letter img { width: 78px; height: 78px; object-fit: contain; }
	.wds-gov { font-size: .78rem; letter-spacing: .04em; text-transform: uppercase; color: #334155; margin: 0; }
	.wds-letter h1 { margin: 4px 0 0; font-size: 1.35rem; color: #0f172a; letter-spacing: .04em; }
	.wds-slogan { margin: 2px 0 0; font-style: italic; color: #475569; }
	.wds-contacts { margin: 4px 0 0; font-size: .82rem; color: #334155; }
	.wds-doc-title { margin: 10px 0 0; font-size: 1.05rem; color: <?= $accent; ?>; text-transform: uppercase; letter-spacing: .06em; }
	.wds-meta { margin: 2px 0 0; color: #64748b; font-size: .85rem; }
	.wds-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin: 0 0 12px; }
	.wds-tabs a { display: block; text-decoration: none; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px 10px; color: #0f172a; }
	.wds-tabs a.on { border-color: <?= $accent; ?>; background: #fff; box-shadow: inset 0 -3px 0 <?= $accent; ?>; }
	.wds-tabs b { display: block; font-size: 1.25rem; line-height: 1.1; }
	.wds-tabs span { display: block; font-size: .75rem; color: #64748b; }
	.wds-tabs a.on-morning b { color: #15803d; }
	.wds-tabs a.on-lunch b { color: #b45309; }
	.wds-tabs a.on-back b { color: #1d4ed8; }
	.wds-tabs a.on-evening b { color: #6d28d9; }
	.wds-find { max-width: 320px; margin: 0 0 10px; }
	.wds-table { width: 100%; border-collapse: collapse; }
	.wds-table th, .wds-table td { border: 1px solid #e5e7eb; padding: 7px 8px; text-align: left; vertical-align: top; }
	.wds-table th { background: #f8fafc; color: #334155; font-size: .75rem; text-transform: uppercase; }
	.wds-table td.hot, .wds-table th.hot { background: #f8fafc; color: <?= $accent; ?>; font-weight: 700; }
	.wds-table tbody tr:nth-child(even) td { background: #f8fafc; }
	.wds-table tbody tr:nth-child(even) td.hot { background: #eef2ff; }
	.wds-empty { padding: 24px; text-align: center; color: #64748b; }
	.wds-muted { color: #94a3b8; }
	@media (min-width: 800px) { .wds-tabs { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
	@media print {
		.app-sidebar, .app-header, .app-footer, .wds-actions, .wds-find, .wds-tabs, .fixed-sidebar, .app-header__logo, .header-btn-lg, .search-wrapper { display: none !important; }
		.app-main__outer, .app-main__inner, .app-main { margin: 0 !important; padding: 0 !important; }
		.wds-page { border: 0; border-radius: 0; padding: 0; }
		.wds-table thead { display: table-header-group; }
		.wds-table tr { break-inside: avoid; }
		@page { size: A4 portrait; margin: 12mm; }
	}
</style>
<div class="wds-page">
	<div class="wds-actions">
		<button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
		<a class="btn btn-outline-secondary" href="<?= base_url('dashboard'); ?>">Back to dashboard</a>
	</div>
	<div class="wds-tabs">
		<?php foreach ($tabs as $key => $label): ?>
			<a class="<?= $key === $kind ? 'on' : ''; ?>" href="<?= base_url('wisdom-day-scholar/' . $schoolId . '/' . $key); ?>">
				<b><?= (int) ($counts[$key] ?? 0); ?></b>
				<span><?= esc($label[1]); ?> · <?= esc($label[0]); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<div class="wds-letter">
		<?php if ($logo !== ''): ?>
			<img src="<?= base_url('assets/images/logo/' . $logo); ?>" alt="">
		<?php endif; ?>
		<?php if (trim((string) ($ps['header_text_1'] ?? '')) !== ''): ?>
			<p class="wds-gov"><?= esc($ps['header_text_1']); ?></p>
		<?php endif; ?>
		<?php if (trim((string) ($ps['header_text_2'] ?? '')) !== ''): ?>
			<p class="wds-gov"><?= esc($ps['header_text_2']); ?></p>
		<?php endif; ?>
		<h1><?= esc(strtoupper($schoolName)); ?></h1>
		<?php if (trim((string) ($ps['slogan'] ?? '')) !== ''): ?>
			<p class="wds-slogan"><?= esc($ps['slogan']); ?></p>
		<?php endif; ?>
		<p class="wds-contacts">
			<?php
			$bits = array_filter([
				trim((string) ($ps['address'] ?? '')),
				trim((string) ($ps['pobox'] ?? '')) !== '' ? 'P.O. Box ' . trim((string) $ps['pobox']) : '',
				trim((string) ($ps['phone'] ?? '')) !== '' ? 'Tel: ' . trim((string) $ps['phone']) : '',
				trim((string) ($ps['email'] ?? '')),
			]);
			echo esc(implode(' · ', $bits));
			?>
		</p>
		<div class="wds-doc-title"><?= esc($tap_label ?? 'Day scholars'); ?></div>
		<p class="wds-meta"><?= esc($school_label); ?> · <?= date('l, d F Y'); ?> · <span id="wdsCount"><?= count($list); ?></span> day scholars</p>
	</div>
	<?php if (!$list): ?>
		<div class="wds-empty">No day scholars in this list yet today.</div>
	<?php else: ?>
		<input type="search" id="wdsFind" class="form-control wds-find" placeholder="Search name or class">
		<table class="wds-table" id="wdsTable">
			<thead>
				<tr>
					<th>#</th>
					<th>Student</th>
					<th>Class</th>
					<th class="<?= $kind === 'morning' ? 'hot' : ''; ?>">Morning</th>
					<th class="<?= $kind === 'lunch' ? 'hot' : ''; ?>">Lunch out</th>
					<th class="<?= $kind === 'back' ? 'hot' : ''; ?>">Back</th>
					<th class="<?= $kind === 'evening' ? 'hot' : ''; ?>">Evening</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($list as $i => $student): ?>
					<tr data-find="<?= esc(strtolower($student['name'] . ' ' . $student['class'] . ' ' . $student['regno'])); ?>">
						<td class="wds-no"><?= $i + 1; ?></td>
						<td>
							<strong><?= esc($student['name']); ?></strong>
							<?php
								$sexLabel = trim((string) $student['sex']) === '' ? '' : ($girl($student['sex']) ? 'Girl' : 'Boy');
								$meta = array_filter([trim((string) $student['regno']), $sexLabel]);
							?>
							<?php if ($meta): ?>
								<div class="wds-muted"><?= esc(implode(' · ', $meta)); ?></div>
							<?php endif; ?>
						</td>
						<td><?= esc($student['class'] !== '' ? $student['class'] : '—'); ?></td>
						<td class="<?= $kind === 'morning' ? 'hot' : ''; ?>"><?= esc($clock($student['time_in'])); ?></td>
						<td class="<?= $kind === 'lunch' ? 'hot' : ''; ?>"><?= esc($clock($student['lunch_at'])); ?></td>
						<td class="<?= $kind === 'back' ? 'hot' : ''; ?>"><?= esc($clock($student['back_at'])); ?></td>
						<td class="<?= $kind === 'evening' ? 'hot' : ''; ?>"><?= esc($clock($student['time_out'])); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
<script>
(function () {
	var box = document.getElementById('wdsFind');
	var table = document.getElementById('wdsTable');
	var count = document.getElementById('wdsCount');
	if (!box || !table) return;
	box.addEventListener('input', function () {
		var q = box.value.toLowerCase().trim();
		var n = 0;
		var shown = 0;
		table.querySelectorAll('tbody tr').forEach(function (row) {
			var ok = q === '' || (row.getAttribute('data-find') || '').indexOf(q) !== -1;
			row.style.display = ok ? '' : 'none';
			if (ok) {
				shown++;
				var cell = row.querySelector('.wds-no');
				if (cell) cell.textContent = String(shown);
			}
		});
		n = shown;
		if (count) count.textContent = String(n);
	});
})();
</script>
