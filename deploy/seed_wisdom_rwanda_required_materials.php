<?php
/**
 * Seed tangible student required materials for WISDOM SCHOOL RWANDA (school 27)
 * from requirement.zip class lists (2026-2027).
 *
 * - Dedupes catalog by case-insensitive name
 * - Records stationery kits material-by-material (pencil / sharpener / rubber, …)
 * - Skips school fees, transport, meals, registration, uniforms, mattress hire, shaving money
 * - Skips Holiday classes
 *
 * Run:
 *   docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_rwanda_required_materials.php
 *   docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_rwanda_required_materials.php --dry-run
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use App\Models\StudentMaterialSchemaModel;

const SCHOOL_ID = 27;
const ACADEMIC_YEAR_ID = 16;

$dryRun = in_array('--dry-run', $argv ?? [], true);

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

function hay(array $cls): string
{
	return strtolower(trim(implode(' ', array_filter([
		(string) ($cls['level_name'] ?? ''),
		(string) ($cls['title'] ?? ''),
		(string) ($cls['dept_title'] ?? ''),
		(string) ($cls['dept_code'] ?? ''),
		(string) ($cls['faculty_title'] ?? ''),
	]))));
}

function isHolidayClass(array $cls): bool
{
	return strpos(hay($cls), 'holiday') !== false;
}

function classLabel(array $cls): string
{
	return trim(implode(' ', array_filter([
		(string) ($cls['level_name'] ?? ''),
		(string) ($cls['dept_code'] ?? ''),
		(string) ($cls['title'] ?? ''),
	])));
}

/**
 * Catalog: unique tangible materials only (unit default pcs).
 *
 * @return list<array{name:string,unit:string}>
 */
function catalogDefinitions(): array
{
	$names = [
		// Classroom stationery
		['Exercise book lined 96 pages', 'pcs'],
		['Exercise book lined 200 pages', 'pcs'],
		['Subject textbooks', 'pcs'],
		['Pencil', 'pcs'],
		['Sharpener', 'pcs'],
		['Rubber', 'pcs'],
		['Blue pen', 'pcs'],
		['Black pen', 'pcs'],
		['Mathematical set', 'pcs'],
		['Ruler', 'pcs'],
		['Ream of Papers', 'pcs'],
		['Broom', 'pcs'],
		['Hand soap', 'pcs'],
		['Toilet paper', 'rolls'],
		['Bathing soap', 'pcs'],
		['School bag', 'pcs'],
		['Drinking water bottle', 'pcs'],
		['School shoes (black)', 'pairs'],
		['Laboratory gown', 'pcs'],
		['Laptop computer', 'pcs'],
		['Raclette (mop)', 'pcs'],
		// Dormitory / boarding
		['Bible', 'pcs'],
		['Hymn book', 'pcs'],
		['Washing soap (bar)', 'pcs'],
		['OMO detergent 1kg', 'pcs'],
		['Bucket', 'pcs'],
		['Pads for girls', 'packets'],
		['School jumper', 'pcs'],
		['Towel', 'pcs'],
		['Toothbrush', 'pcs'],
		['Toothpaste', 'pcs'],
		['Nail cutter', 'pcs'],
		['Comb', 'pcs'],
		['Bed sheet', 'pcs'],
		['Blanket / bed cover', 'pcs'],
		['Handkerchief', 'pcs'],
		['Socks', 'pairs'],
		['Slippers', 'pairs'],
	];
	$out = [];
	foreach ($names as [$name, $unit]) {
		$out[] = ['name' => $name, 'unit' => $unit];
	}
	return $out;
}

/** Shared secondary classroom extras (after exercise books + textbooks). */
function secondaryClassroomExtras(bool $withLabGown, bool $withDayHygiene = true): array
{
	$rows = [
		'Pencil' => 12,
		'Sharpener' => 2,
		'Rubber' => 3,
		'Ream of Papers' => 1,
		'Laptop computer' => 1,
		'School bag' => 1,
		'Drinking water bottle' => 1,
		'Raclette (mop)' => 1,
		'School shoes (black)' => 1,
	];
	if ($withLabGown) {
		$rows['Laboratory gown'] = 1;
	}
	if ($withDayHygiene) {
		$rows['Toilet paper'] = 3;
		$rows['Bathing soap'] = 3;
	}
	return $rows;
}

/** Dormitory pack (boarding). OMO qty differs primary vs secondary. */
function dormPack(bool $secondary, bool $includePads): array
{
	$rows = [
		'Bible' => 1,
		'Hymn book' => 1,
		'Washing soap (bar)' => 4,
		'Bathing soap' => 5,
		'OMO detergent 1kg' => $secondary ? 2 : 1,
		'School shoes (black)' => 1,
		'Bucket' => 1,
		'Toilet paper' => 10,
		'Drinking water bottle' => 1,
		'School jumper' => 1,
		'Towel' => 1,
		'Toothbrush' => 2,
		'Toothpaste' => 1,
		'Nail cutter' => 1,
		'Comb' => 1,
		'Bed sheet' => 2,
		'Blanket / bed cover' => 1,
		'Handkerchief' => 2,
		'Socks' => 3,
		'Slippers' => 1,
		'Raclette (mop)' => 1,
	];
	if ($includePads) {
		$rows['Pads for girls'] = 12;
	}
	return $rows;
}

function mergeQty(array $a, array $b): array
{
	foreach ($b as $name => $qty) {
		$qty = (float) $qty;
		if ($qty <= 0) {
			continue;
		}
		$a[$name] = max((float) ($a[$name] ?? 0), $qty);
	}
	return $a;
}

/**
 * Map one class row → material name => quantity.
 *
 * @return array<string,float>
 */
function materialsForClass(array $cls): array
{
	if (isHolidayClass($cls)) {
		return [];
	}

	$h = hay($cls);
	$level = strtolower(trim((string) ($cls['level_name'] ?? '')));
	$code = strtoupper(trim((string) ($cls['dept_code'] ?? '')));
	$dept = strtolower(trim((string) ($cls['dept_title'] ?? '')));

	// Nursery (Baby / N1-N3)
	if (
		preg_match('/\b(baby class|n1|n2|n3|nursery)\b/', $h)
		&& !preg_match('/\bp[1-6]\b/', $level)
		&& !preg_match('/\bs[1-6]\b/', $level)
		&& strpos($h, 'software') === false
	) {
		return [
			'Exercise book lined 96 pages' => 12,
			'Pencil' => 12,
			'Sharpener' => 2,
			'Rubber' => 3,
			'Ream of Papers' => 1,
			'Hand soap' => 3,
			'Toilet paper' => 3,
			'School bag' => 1,
			'Drinking water bottle' => 1,
		];
	}

	// Primary P1-P2
	if (preg_match('/^p[12]$/', $level) || preg_match('/\bp[12]\b/', $h)) {
		$class = [
			'Exercise book lined 96 pages' => 12,
			'Subject textbooks' => 5,
			'Pencil' => 12,
			'Sharpener' => 2,
			'Rubber' => 3,
			'Ream of Papers' => 1,
			'Broom' => 1,
			'Hand soap' => 3,
			'Drinking water bottle' => 1,
		];
		// Boarding dorm (pads only from P4)
		return mergeQty($class, dormPack(false, false));
	}

	// Primary P3-P6
	if (preg_match('/^p[3-6]$/', $level) || preg_match('/\bp[3-6]\b/', $h)) {
		$class = [
			'Exercise book lined 200 pages' => 24,
			'Subject textbooks' => 5,
			'Blue pen' => 12,
			'Black pen' => 6,
			'Mathematical set' => 1,
			'Ruler' => 1,
			'Pencil' => 6,
			'Rubber' => 1,
			'Sharpener' => 1,
			'Ream of Papers' => 1,
			'Broom' => 1,
			'Hand soap' => 3,
			'School bag' => 1,
			'Drinking water bottle' => 1,
			'School shoes (black)' => 1,
		];
		$pads = (bool) preg_match('/^p[4-6]$/', $level);
		return mergeQty($class, dormPack(false, $pads));
	}

	// SOD TVET levels 3/4/5 (S4/S5 SOD style — no textbooks pack on lists)
	if (strpos($dept, 'software') !== false || $code === 'SOD') {
		$class = [
			'Exercise book lined 200 pages' => 24,
			'Pencil' => 12,
			'Sharpener' => 2,
			'Rubber' => 3,
			'Ream of Papers' => 1,
			'Laptop computer' => 1,
			'School bag' => 1,
		];
		return mergeQty($class, dormPack(true, true));
	}

	// O'level S1
	if ($level === 's1' || preg_match('/\bs1\b/', $h)) {
		$class = array_merge(
			[
				'Exercise book lined 200 pages' => 24,
				'Subject textbooks' => 11,
			],
			secondaryClassroomExtras(true, true)
		);
		return mergeQty($class, dormPack(true, true));
	}

	// O'level S2
	if ($level === 's2' || preg_match('/\bs2\b/', $h)) {
		$class = array_merge(
			[
				'Exercise book lined 200 pages' => 24,
				'Subject textbooks' => 14,
			],
			secondaryClassroomExtras(true, true)
		);
		return mergeQty($class, dormPack(true, true));
	}

	// O'level S3
	if ($level === 's3' || preg_match('/\bs3\b/', $h)) {
		$class = array_merge(
			[
				'Exercise book lined 200 pages' => 24,
				'Subject textbooks' => 11,
			],
			secondaryClassroomExtras(true, true)
		);
		return mergeQty($class, dormPack(true, true));
	}

	// Senior combinations
	if (preg_match('/^s[456]$/', $level)) {
		$bookQty = 7;
		$lab = true;

		if ($code === 'ACC' || strpos($dept, 'account') !== false) {
			$bookQty = 7;
			$lab = false;
		} elseif ($code === 'ANP' || strpos($dept, 'nurs') !== false) {
			$bookQty = ($level === 's4') ? 5 : (($level === 's5') ? 7 : 7);
			$lab = false;
		} elseif ($code === 'ST1' || preg_match('/\bstream\s*1\b/', $h) || preg_match('/\bst1\b/', $h)) {
			$bookQty = 7;
			$lab = true;
		} elseif ($code === 'ST2' || preg_match('/\bstream\s*2\b/', $h) || preg_match('/\bst2\b/', $h)) {
			$bookQty = 7;
			$lab = true;
		} elseif (in_array($code, ['MCB', 'MCE', 'MEG', 'MPC', 'MPG', 'PCB', 'PCM'], true)) {
			$bookQty = 8;
			$lab = true;
		} elseif ($code === 'STR' || strpos($dept, 'stream') !== false) {
			$bookQty = 7;
			$lab = true;
		}

		$class = array_merge(
			[
				'Exercise book lined 200 pages' => 24,
				'Subject textbooks' => $bookQty,
			],
			secondaryClassroomExtras($lab, true)
		);
		return mergeQty($class, dormPack(true, true));
	}

	return [];
}

$db = \Config\Database::connect();
$school = $db->table('schools')->where('id', SCHOOL_ID)->get(1)->getRowArray();
if (!$school) {
	say('ERROR: school ' . SCHOOL_ID . ' not found');
	exit(1);
}

$year = $db->table('academic_year')
	->where('id', ACADEMIC_YEAR_ID)
	->where('school_id', SCHOOL_ID)
	->get(1)->getRowArray();
if (!$year) {
	say('ERROR: academic year ' . ACADEMIC_YEAR_ID . ' not found');
	exit(1);
}

$matSchema = new StudentMaterialSchemaModel();
$matSchema->ensureSchema();

$classes = $db->table('classes c')
	->select('c.id, c.title, l.title as level_name, d.title as dept_title, d.code as dept_code, f.title as faculty_title')
	->join('levels l', 'l.id = c.level', 'left')
	->join('departments d', 'd.id = c.department', 'left')
	->join('faculty f', 'f.id = d.faculty_id', 'left')
	->where('c.school_id', SCHOOL_ID)
	->orderBy('l.title', 'ASC')
	->orderBy('d.code', 'ASC')
	->orderBy('c.title', 'ASC')
	->get()->getResultArray();

say('WISDOM SCHOOL RWANDA required materials seed');
say('School: ' . ($school['name'] ?? SCHOOL_ID) . ' (id ' . SCHOOL_ID . ')');
say('Academic year: ' . ($year['title'] ?? ACADEMIC_YEAR_ID) . ' (id ' . ACADEMIC_YEAR_ID . ')');
say($dryRun ? 'MODE: dry-run' : 'MODE: live');
say('');

// --- Catalog upsert (no duplicates) ---
$existing = $matSchema->listMaterials(SCHOOL_ID, false);
$byKey = [];
foreach ($existing as $row) {
	$key = strtolower(trim((string) ($row['name'] ?? '')));
	if ($key === '') {
		continue;
	}
	if (!isset($byKey[$key]) || (int) ($row['active'] ?? 0) === 1) {
		$byKey[$key] = $row;
	}
}

$sort = 10;
$idByName = [];
$created = 0;
$reactivated = 0;

foreach (catalogDefinitions() as $def) {
	$name = $def['name'];
	$unit = $def['unit'];
	$key = strtolower($name);
	$sort += 10;

	if (isset($byKey[$key])) {
		$row = $byKey[$key];
		$id = (int) $row['id'];
		$idByName[$name] = $id;
		if (!$dryRun) {
			$update = ['sort_order' => $sort, 'unit' => $unit];
			if ((int) ($row['active'] ?? 0) !== 1) {
				$update['active'] = 1;
				$reactivated++;
			}
			// Prefer canonical casing for "Ream of Papers" already in DB
			if (strcasecmp((string) $row['name'], $name) !== 0 && strcasecmp($name, 'Ream of Papers') === 0) {
				// keep existing name
			} elseif (strcasecmp((string) $row['name'], $name) !== 0) {
				$update['name'] = $name;
			}
			$matSchema->update($id, $update);
		}
		say('  CATALOG keep: ' . $name . ' (id ' . $id . ')');
		continue;
	}

	if ($dryRun) {
		$idByName[$name] = 0;
		$created++;
		say('  CATALOG add: ' . $name . ' [' . $unit . ']');
		continue;
	}

	$id = (int) $matSchema->insert([
		'school_id' => SCHOOL_ID,
		'name' => $name,
		'unit' => $unit,
		'sort_order' => $sort,
		'active' => 1,
	]);
	if ($id <= 0) {
		say('  ERROR adding ' . $name);
		continue;
	}
	$idByName[$name] = $id;
	$byKey[$key] = ['id' => $id, 'name' => $name, 'unit' => $unit, 'active' => 1];
	$created++;
	say('  CATALOG add: ' . $name . ' (id ' . $id . ')');
}

say('');
say(sprintf('Catalog: created=%d reactivated=%d total_mapped=%d', $created, $reactivated, count($idByName)));
say('');

// Soft-deactivate catalog items that look like fees (safety)
$feeLike = '/\b(school\s*fees?|transport|feeding|meals?|registration|hiring\s*mattress|shaving|rebelling|uniform)\b/i';
foreach ($matSchema->listMaterials(SCHOOL_ID, true) as $row) {
	$name = (string) ($row['name'] ?? '');
	if ($name !== '' && preg_match($feeLike, $name)) {
		say('  DEACTIVATE fee-like catalog item: ' . $name);
		if (!$dryRun) {
			$matSchema->update((int) $row['id'], ['active' => 0]);
		}
	}
}

// --- Per-class assignments ---
$assignedClasses = 0;
$skipped = 0;
$totalRows = 0;

foreach ($classes as $cls) {
	$label = classLabel($cls);
	$mats = materialsForClass($cls);
	if ($mats === []) {
		$skipped++;
		say('  SKIP ' . $label);
		continue;
	}

	$rows = [];
	foreach ($mats as $matName => $qty) {
		$qty = (float) $qty;
		if ($qty <= 0) {
			continue;
		}
		$mid = (int) ($idByName[$matName] ?? 0);
		if ($mid <= 0 && !$dryRun) {
			say('  WARN missing catalog id for ' . $matName . ' on ' . $label);
			continue;
		}
		$rows[] = ['material_id' => $mid, 'quantity' => $qty];
	}

	if (!$dryRun) {
		$matSchema->saveClassAssignments(SCHOOL_ID, (int) $cls['id'], ACADEMIC_YEAR_ID, $rows);
	}
	$assignedClasses++;
	$totalRows += count($rows);
	$names = [];
	foreach ($mats as $n => $q) {
		$names[] = $n . '=' . $q;
	}
	say(sprintf('  ASSIGN %s | items=%d | %s', $label, count($rows), implode(', ', array_slice($names, 0, 8)) . (count($names) > 8 ? ', ...' : '')));
}

say('');
	say(sprintf(
	'Done. classes_assigned=%d skipped=%d assignment_rows~%d dry_run=%s',
	$assignedClasses,
	$skipped,
	$totalRows,
	$dryRun ? 'yes' : 'no'
));
