<?php
$ps = is_array($print_school ?? null) ? $print_school : [];
$logo = basename(trim((string) ($ps['logo'] ?? '')));
$schoolName = trim((string) ($ps['name'] ?? $school_label ?? ''));
$isAbsent = ($kind ?? '') === 'absent';
$title = $isAbsent ? 'Staff absent today' : 'Staff in today';
$accent = $isAbsent ? '#dc2626' : '#15803d';
?>
<style>
	.wst-page { background: #fff; border-radius: 14px; padding: 18px 18px 24px; }
	.wst-actions { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
	.wst-letter { text-align: center; border-bottom: 3px solid <?= $accent; ?>; padding-bottom: 10px; margin-bottom: 12px; }
	.wst-letter img { width: 78px; height: 78px; object-fit: contain; }
	.wst-gov { font-size: .78rem; letter-spacing: .04em; text-transform: uppercase; color: #334155; margin: 0; }
	.wst-letter h1 { margin: 4px 0 0; font-size: 1.35rem; color: #0f172a; letter-spacing: .04em; }
	.wst-slogan { margin: 2px 0 0; font-style: italic; color: #475569; }
	.wst-contacts { margin: 4px 0 0; font-size: .82rem; color: #334155; }
	.wst-doc-title { margin: 10px 0 0; font-size: 1.05rem; color: <?= $accent; ?>; text-transform: uppercase; letter-spacing: .06em; }
	.wst-meta { margin: 2px 0 0; color: #64748b; font-size: .85rem; }
	.wst-table { width: 100%; border-collapse: collapse; }
	.wst-table th, .wst-table td { border: 1px solid #e5e7eb; padding: 7px 8px; text-align: left; vertical-align: top; }
	.wst-table th { background: <?= $isAbsent ? '#fef2f2' : '#ecfdf5'; ?>; color: <?= $accent; ?>; font-size: .78rem; text-transform: uppercase; }
	.wst-table tr.absent td { color: #991b1b; }
	.wst-table tbody tr:nth-child(even) td { background: #f8fafc; }
	.wst-empty { padding: 24px; text-align: center; color: #64748b; }
	.wst-sign { margin-top: 28px; display: flex; justify-content: flex-end; }
	.wst-sign div { width: 220px; text-align: center; font-size: .85rem; }
	.wst-sign .line { margin-top: 36px; border-top: 1px solid #0f172a; padding-top: 4px; }
	@media print {
		.app-sidebar, .app-header, .app-footer, .wst-actions, .fixed-sidebar, .app-header__logo, .header-btn-lg, .search-wrapper { display: none !important; }
		.app-main__outer, .app-main__inner, .app-main { margin: 0 !important; padding: 0 !important; }
		.wst-page { border: 0; border-radius: 0; padding: 0; }
		.wst-table thead { display: table-header-group; }
		.wst-table tr { break-inside: avoid; }
		@page { size: A4 portrait; margin: 12mm; }
	}
</style>
<div class="wst-page<?= $isAbsent ? ' is-absent' : ''; ?>">
	<div class="wst-actions">
		<button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
		<a class="btn btn-outline-secondary" href="<?= base_url('dashboard'); ?>">Back to dashboard</a>
	</div>
	<div class="wst-letter">
		<?php if ($logo !== ''): ?>
			<img src="<?= base_url('assets/images/logo/' . $logo); ?>" alt="">
		<?php endif; ?>
		<?php if (trim((string) ($ps['header_text_1'] ?? '')) !== ''): ?>
			<p class="wst-gov"><?= esc($ps['header_text_1']); ?></p>
		<?php endif; ?>
		<?php if (trim((string) ($ps['header_text_2'] ?? '')) !== ''): ?>
			<p class="wst-gov"><?= esc($ps['header_text_2']); ?></p>
		<?php endif; ?>
		<h1><?= esc(strtoupper($schoolName)); ?></h1>
		<?php if (trim((string) ($ps['slogan'] ?? '')) !== ''): ?>
			<p class="wst-slogan"><?= esc($ps['slogan']); ?></p>
		<?php endif; ?>
		<p class="wst-contacts">
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
		<div class="wst-doc-title"><?= esc($title); ?></div>
		<p class="wst-meta"><?= esc($school_label); ?> · <?= date('l, d F Y'); ?> · <?= count($list); ?> staff</p>
	</div>
	<?php if (!$list): ?>
		<div class="wst-empty">No staff in this list today.</div>
	<?php else: ?>
		<table class="wst-table">
			<thead>
				<tr>
					<th>#</th>
					<th>Name</th>
					<th>Post</th>
					<th>School</th>
					<th>Time</th>
					<th>Note</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($list as $i => $person): ?>
					<tr class="<?= $isAbsent ? 'absent' : 'in'; ?>">
						<td><?= $i + 1; ?></td>
						<td><strong><?= esc($person['name']); ?></strong></td>
						<td><?= esc($person['post'] !== '' ? $person['post'] : 'Staff'); ?></td>
						<td><?= esc($person['school']); ?></td>
						<td><?= esc($person['time'] !== '' ? $person['time'] : '—'); ?></td>
						<td><?= esc($person['note']); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="wst-sign">
			<div>
				Prepared by <?= esc((string) (session('soma_name') ?? '')); ?>
				<div class="line">Signature</div>
			</div>
		</div>
	<?php endif; ?>
</div>
