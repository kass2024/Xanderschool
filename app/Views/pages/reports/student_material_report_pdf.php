<?php
/** @var array $class */
/** @var array|null $year */
/** @var array $students */
/** @var array $materials */
/** @var array $class_kpi */
/** @var string $filter_label */
/** @var string $printed_at */
/** @var string $class_label */
/** @var string $school_name */
/** @var string $school_logo */

function smrPdfStatusLabel(string $st): string
{
	$map = [
		'complete' => 'Complete',
		'partial' => 'Partial',
		'missing' => 'Missing',
		'unchecked' => 'Not checked',
		'none' => 'N/A',
	];
	return $map[$st] ?? ucfirst($st);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Required Materials Report</title>
	<style>
		@page { size: A4 portrait; margin: 12mm; }
		body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #111; margin: 0; }
		.hdr { border: 1px solid #333; padding: 10px 12px; margin-bottom: 12px; overflow: hidden; }
		.hdr-left { float: left; width: 55%; }
		.hdr-right { float: right; width: 42%; text-align: right; }
		.logo { max-width: 90px; max-height: 70px; margin-top: 4px; }
		.title { text-align: center; font-size: 14pt; font-weight: bold; margin: 10px 0 4px; }
		.subtitle { text-align: center; font-size: 10pt; color: #444; margin-bottom: 12px; }
		.kpi-row { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
		.kpi-row td { border: 1px solid #ccc; text-align: center; padding: 6px 4px; font-size: 9pt; }
		.kpi-val { font-size: 14pt; font-weight: bold; display: block; }
		table.data { width: 100%; border-collapse: collapse; }
		table.data th, table.data td { border: 1px solid #777; padding: 4px 5px; vertical-align: top; font-size: 9pt; }
		table.data th { background: #f0f0f0; font-weight: bold; }
		.st-complete { color: #15803d; font-weight: bold; }
		.st-partial { color: #b45309; font-weight: bold; }
		.st-missing { color: #b91c1c; font-weight: bold; }
		.st-unchecked { color: #64748b; font-weight: bold; }
		.missing-col { font-size: 8.5pt; line-height: 1.35; }
		.foot { margin-top: 14px; font-size: 8pt; color: #666; text-align: center; }
	</style>
</head>
<body>
<div class="hdr">
	<div class="hdr-left">
		<strong><?= esc($school_name ?? '') ?></strong><br>
		<?php if (!empty($school_logo)) : ?>
			<img class="logo" src="<?= esc(base_url('assets/images/logo/' . $school_logo), 'attr') ?>" alt="">
		<?php endif; ?>
	</div>
	<div class="hdr-right">
		<strong>Academic year:</strong> <?= esc($year['title'] ?? '') ?><br>
		<strong>Class:</strong> <?= esc($class_label) ?><br>
		<strong>Mentor:</strong> <?= esc($class['mentor_name'] ?? '—') ?><br>
		<strong>Filter:</strong> <?= esc($filter_label) ?><br>
		<strong>Printed:</strong> <?= esc($printed_at) ?>
	</div>
</div>

<div class="title">Required Materials Supply Report</div>
<div class="subtitle"><?= esc($class_label) ?> · <?= count($students) ?> student(s) listed</div>

<table class="kpi-row">
	<tr>
		<td><span class="kpi-val"><?= (int) ($class_kpi['total'] ?? 0) ?></span>Students</td>
		<td><span class="kpi-val"><?= (int) ($class_kpi['complete'] ?? 0) ?></span>Fully supplied</td>
		<td><span class="kpi-val"><?= (int) ($class_kpi['partial'] ?? 0) ?></span>Partial</td>
		<td><span class="kpi-val"><?= (int) ($class_kpi['missing'] ?? 0) ?></span>Missing</td>
		<td><span class="kpi-val"><?= (int) ($class_kpi['unchecked'] ?? 0) ?></span>Not checked</td>
	</tr>
</table>

<?php if (empty($materials)) : ?>
	<p style="text-align:center;color:#666;">No materials configured for this class.</p>
<?php else : ?>
	<table class="data">
		<thead>
		<tr>
			<th style="width:28px">#</th>
			<th style="width:72px">Reg no</th>
			<th style="width:120px">Student name</th>
			<th style="width:58px">Status</th>
			<th style="width:110px">Checked by</th>
			<?php foreach ($materials as $m) : ?>
				<th style="text-align:center"><?= esc($m['name']) ?><br><small>(<?= esc($m['unit']) ?>)</small></th>
			<?php endforeach; ?>
			<th>Still missing / notes</th>
		</tr>
		</thead>
		<tbody>
		<?php $n = 0; foreach ($students as $st) :
			$n++;
			$overall = (string) ($st['overall'] ?? '');
			$byMat = [];
			foreach ($st['items'] ?? [] as $it) {
				$byMat[(int) $it['material_id']] = $it;
			}
			$checkerBits = array_filter([
				trim((string) ($st['checker_name'] ?? '')),
				trim((string) ($st['checker_post'] ?? '')),
			]);
			$checkerLine = $checkerBits ? implode(' · ', $checkerBits) : '—';
			?>
			<tr>
				<td><?= $n ?></td>
				<td><?= esc($st['regno'] ?? '') ?></td>
				<td><?= esc($st['name'] ?? '') ?></td>
				<td class="st-<?= esc($overall, 'attr') ?>"><?= esc(smrPdfStatusLabel($overall)) ?></td>
				<td class="missing-col"><?= esc($checkerLine) ?><?php if (!empty($st['checked_at'])) : ?><br><small><?= esc($st['checked_at']) ?></small><?php endif; ?></td>
				<?php foreach ($materials as $m) :
					$it = $byMat[(int) $m['material_id']] ?? null;
					$brought = $it ? (float) $it['brought'] : 0;
					$req = (float) $m['quantity'];
					?>
					<td style="text-align:center"><?= $brought >= $req && $req > 0 ? '✓' : ($brought > 0 ? number_format($brought, 0) . '/' . number_format($req, 0) : '—') ?></td>
				<?php endforeach; ?>
				<td class="missing-col"><?= esc($st['missing_summary'] ?? '—') ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<div class="foot">Generated by <?= esc($school_name ?? 'School MIS') ?> · Required Material Check</div>
</body>
</html>
