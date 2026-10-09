<?php
$streamGroups = [];
foreach ($classes as $class) {
	$levelId = (int) ($class['level_id'] ?? 0);
	$deptId = (int) ($class['department_id'] ?? 0);
	if ($levelId > 0) {
		$streamGroups[$deptId . ':' . $levelId][] = $class;
	}
}
$streamShown = [];
foreach ($classes as $class) {
	$deptId = (int) ($class['department_id'] ?? 0);
	$levelId = (int) ($class['level_id'] ?? 0);
	$streamKey = $deptId . ':' . $levelId;
	if ($levelId > 0 && count($streamGroups[$streamKey] ?? []) > 1 && empty($streamShown[$streamKey])) {
		$streamShown[$streamKey] = true;
		$streamLabel = trim((string) ($class['level_name'] ?? ''));
		$streamCode = trim((string) ($class['code'] ?? ''));
		if ($streamCode !== '' && !preg_match('/^-+$/', $streamCode)) {
			$streamLabel = trim($streamLabel . ' ' . $streamCode);
		}
		?>
		<option data-fac="<?= (int) ($class['facul_id'] ?? 0); ?>" data-id="<?= (int) ($class['facul_id'] ?? 0); ?>"
			value="g<?= $deptId; ?>l<?= $levelId; ?>"> <?= esc($streamLabel); ?></option>
		<?php
	}
	?>
	<option data-fac="<?= (int) ($class['facul_id'] ?? 0); ?>" data-id="<?= (int) ($class['facul_id'] ?? 0); ?>"
		id="faculty<?= (int) $class['id']; ?>"
		value="<?= $class['id']; ?>"> <?= $class['level_name'] . " " . $class['code'] . " " . $class['title']; ?></option>
	<?php
}
