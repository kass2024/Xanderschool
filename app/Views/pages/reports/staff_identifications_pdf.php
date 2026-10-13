<?php
/** @var array $school */
/** @var string $logo_src */
/** @var list<array<string,mixed>> $staffs */
/** @var string $year_title */
/** @var string $term_label */
/** @var string $printed_at */

$schoolName = trim((string) ($school['name'] ?? 'School'));
$slogan = trim((string) ($school['slogan'] ?? ''));
$header1 = trim((string) ($school['header_1'] ?? ''));
$header2 = trim((string) ($school['header_2'] ?? ''));
$logoSrc = trim((string) ($logo_src ?? ''));
$contact = array_filter([
	trim((string) ($school['address'] ?? '')) ?: null,
	!empty($school['pobox']) ? 'P.O. Box ' . $school['pobox'] : null,
	!empty($school['phone']) ? 'Tel: ' . $school['phone'] : null,
	!empty($school['email']) ? 'Email: ' . $school['email'] : null,
	!empty($school['website']) ? $school['website'] : null,
]);
$dash = static function ($value): string {
	$text = trim((string) $value);
	return $text === '' ? '—' : esc($text);
};
$sexLabel = static function ($value): string {
	$sex = strtoupper(trim((string) $value));
	if ($sex === 'M') {
		return 'Male';
	}
	if ($sex === 'F') {
		return 'Female';
	}
	return $sex === '' ? '—' : esc($sex);
};
$staffs = array_values($staffs ?? []);
$total = count($staffs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Staff identifications — <?= esc($schoolName) ?></title>
	<style>
		body { font-family: dejavusanscondensed, sans-serif; font-size: 10.5pt; color: #0f172a; }
		.sheet { page-break-after: always; }
		.sheet-last { page-break-after: auto; }
		.letter { width: 100%; border-collapse: collapse; border-bottom: 2.4pt solid #012F6B; margin-bottom: 8px; }
		.letter td { text-align: center; padding: 0 0 8px; }
		.gov { font-size: 8pt; letter-spacing: 0.4px; text-transform: uppercase; color: #334155; margin: 0; }
		.name { font-size: 16pt; font-weight: bold; color: #012F6B; text-transform: uppercase; margin: 2px 0 0; }
		.slogan { font-size: 9pt; font-style: italic; color: #475569; margin: 1px 0 0; }
		.contact { font-size: 8pt; color: #334155; margin: 3px 0 0; }
		.banner { background: #012F6B; color: #fff; text-align: center; font-size: 12pt; font-weight: bold; letter-spacing: 0.4px; padding: 6px 8px; margin: 0 0 8px; }
		.who { font-size: 14pt; font-weight: bold; color: #0f172a; margin: 0 0 2px; }
		.meta { font-size: 9pt; color: #334155; margin: 0 0 8px; }
		table.fields { width: 100%; border-collapse: collapse; }
		table.fields td { border: 0.6pt solid #cbd5e1; padding: 5px 8px; vertical-align: top; }
		table.fields td.k { width: 42%; background: #f1f5f9; font-weight: bold; color: #0f2744; }
		.idbox { margin-top: 10px; border: 0.8pt solid #94a3b8; padding: 6px; text-align: center; }
		.idbox img { width: 150mm; height: auto; }
		.idcap { font-size: 8pt; color: #475569; margin: 0 0 4px; }
		.note { font-size: 8.5pt; color: #64748b; margin-top: 8px; }
	</style>
</head>
<body>
<?php foreach ($staffs as $index => $staff):
	$kind = (string) ($staff['identity_kind'] ?? '');
	$isForeign = $kind === 'foreign';
	$nationality = $isForeign ? 'Non-Rwandan' : ($kind === 'rwandan' ? 'Rwandan' : 'Not set');
	$nature = (string) ($staff['id_nature'] ?? '');
	$natureLabel = $nature === 'passport' ? 'Passport' : ($nature === 'id' ? 'National ID' : '');
	$fullName = trim((string) ($staff['fname'] ?? '') . ' ' . (string) ($staff['lname'] ?? ''));
	$dob = trim((string) ($staff['dob'] ?? ''));
	$dobLabel = $dob !== '' && $dob !== '0000-00-00' ? date('d M Y', strtotime($dob)) : '';
	$rows = [
		['Full names', $fullName],
		['Nationality', $nationality],
		['Post', (string) ($staff['post_title'] ?? '')],
	];
	if ($isForeign) {
		$rows[] = ['Sex', $sexLabel($staff['sex'] ?? '')];
		$rows[] = ['Date of birth', $dobLabel];
		$rows[] = ['Telephone No', (string) ($staff['phone'] ?? '')];
		$rows[] = ['Email', (string) ($staff['email'] ?? '')];
		$rows[] = ['Nature of identification', $natureLabel];
		$rows[] = ['Passport / NID No', (string) ($staff['document_no'] ?? '')];
		$rows[] = ["Father's name", (string) ($staff['father_name'] ?? '')];
		$rows[] = ["Mother's name", (string) ($staff['mother_name'] ?? '')];
		$rows[] = ['Province of residence', (string) ($staff['province_name'] ?? '')];
		$rows[] = ['District of residence', (string) ($staff['district_name'] ?? '')];
		$rows[] = ['Sector of residence', (string) ($staff['sector_name'] ?? '')];
	} else {
		$rows[] = ['National ID', (string) ($staff['national_id'] ?? '')];
		$rows[] = ['Telephone No', (string) ($staff['phone'] ?? '')];
		$rows[] = ['Email', (string) ($staff['email'] ?? '')];
	}
	?>
	<div class="<?= $index === $total - 1 ? 'sheet-last' : 'sheet'; ?>">
		<table class="letter" width="100%">
			<tr>
				<td>
					<?php if ($logoSrc !== ''): ?>
					<img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8'); ?>" width="78" height="78" alt="">
					<?php endif; ?>
					<?php if ($header1 !== ''): ?><p class="gov"><?= esc($header1) ?></p><?php endif; ?>
					<?php if ($header2 !== ''): ?><p class="gov"><?= esc($header2) ?></p><?php endif; ?>
					<p class="name"><?= esc($schoolName) ?></p>
					<?php if ($slogan !== ''): ?><p class="slogan"><?= esc($slogan) ?></p><?php endif; ?>
					<?php if ($contact !== []): ?><p class="contact"><?= esc(implode('  ·  ', $contact)) ?></p><?php endif; ?>
				</td>
			</tr>
		</table>
		<div class="banner">STAFF IDENTIFICATION</div>
		<p class="who"><?= esc($fullName !== '' ? $fullName : 'Staff') ?></p>
		<p class="meta">
			<?= esc($nationality) ?>
			<?php if ($year_title !== ''): ?> · <?= esc($year_title) ?><?php endif; ?>
			<?php if ($term_label !== ''): ?> · <?= esc($term_label) ?><?php endif; ?>
			· <?= (int) ($index + 1) ?> of <?= (int) $total ?>
		</p>
		<table class="fields">
			<?php foreach ($rows as $row): ?>
			<tr>
				<td class="k"><?= esc($row[0]) ?></td>
				<td><?php
					if ($row[0] === 'Sex') {
						echo $row[1];
					} else {
						echo $dash($row[1]);
					}
				?></td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php if (!empty($staff['id_image'])): ?>
		<div class="idbox">
			<p class="idcap">Uploaded identification</p>
			<img src="<?= esc((string) $staff['id_image'], 'attr'); ?>" alt="Identification">
		</div>
		<?php elseif (!empty($staff['id_is_pdf'])): ?>
		<p class="note">An identification PDF is stored on this staff profile.</p>
		<?php elseif ($kind === 'rwandan'): ?>
		<p class="note">No identification image has been uploaded.</p>
		<?php endif; ?>
	</div>
<?php endforeach; ?>
</body>
</html>
