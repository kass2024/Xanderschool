<?php
/**
 * Seed Term 1 class extra fees for WISDOM SCHOOL RWANDA (school 27).
 *
 * Run:
 *   docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_rwanda_extra_fees.php
 *   docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_rwanda_extra_fees.php --dry-run
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

use App\Models\ExtraFeesModel;

const SCHOOL_ID = 27;
const ACADEMIC_YEAR_ID = 16;
const CREATED_BY = 0;
const TERMS = [1, 2, 3];

$dryRun = in_array('--dry-run', $argv ?? [], true);

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

function isHolidayClass(array $cls): bool
{
	$hay = strtolower(trim(implode(' ', array_filter([
		(string) ($cls['title'] ?? ''),
		(string) ($cls['level_name'] ?? ''),
		(string) ($cls['dept_title'] ?? ''),
		(string) ($cls['dept_code'] ?? ''),
		(string) ($cls['faculty_title'] ?? ''),
	]))));
	return $hay !== '' && strpos($hay, 'holiday') !== false;
}

function isNurseryClass(array $cls): bool
{
	$hay = strtolower(trim(implode(' ', array_filter([
		(string) ($cls['level_name'] ?? ''),
		(string) ($cls['title'] ?? ''),
		(string) ($cls['dept_title'] ?? ''),
		(string) ($cls['dept_code'] ?? ''),
		(string) ($cls['faculty_title'] ?? ''),
	]))));
	if ($hay === '') {
		return false;
	}
	return (bool) preg_match('/\b(nursery|baby class|middle class|top class|n1|n2|n3)\b/', $hay);
}

function isPrimaryClass(array $cls): bool
{
	return ExtraFeesModel::isPrimaryRegistrationClass(
		$cls['level_name'] ?? '',
		$cls['title'] ?? '',
		$cls['dept_title'] ?? '',
		$cls['dept_code'] ?? ''
	);
}

function classLabel(array $cls): string
{
	return trim(implode(' ', array_filter([
		(string) ($cls['level_name'] ?? ''),
		(string) ($cls['dept_code'] ?? ''),
		(string) ($cls['title'] ?? ''),
	])));
}

/** @return list<array{title:string,boarding:?float,day:?float,scope:string}> */
function feeDefinitions(): array
{
	return [
		['title' => 'Hiring Mattress', 'boarding' => 15000.0, 'day' => null, 'scope' => 'all'],
		['title' => 'Uniform', 'boarding' => 110000.0, 'day' => 110000.0, 'scope' => 'all'],
		['title' => 'Transport', 'boarding' => null, 'day' => 60000.0, 'scope' => 'primary'],
		['title' => 'Feeding', 'boarding' => null, 'day' => 60000.0, 'scope' => 'primary'],
		['title' => 'Feeding', 'boarding' => null, 'day' => 100000.0, 'scope' => 'high_school'],
		['title' => 'Transport', 'boarding' => null, 'day' => 80000.0, 'scope' => 'high_school'],
		['title' => 'Money for Shaving', 'boarding' => 5000.0, 'day' => 5000.0, 'scope' => 'all'],
		['title' => 'Money for Rebelling', 'boarding' => 5000.0, 'day' => 5000.0, 'scope' => 'all'],
	];
}

function isPrimaryOrNurseryClass(array $cls): bool
{
	return isPrimaryClass($cls) || isNurseryClass($cls);
}

function classMatchesScope(array $cls, string $scope): bool
{
	if (isHolidayClass($cls)) {
		return false;
	}
	if ($scope === 'all') {
		return true;
	}
	if ($scope === 'primary') {
		return isPrimaryOrNurseryClass($cls);
	}
	if ($scope === 'high_school') {
		return !isPrimaryOrNurseryClass($cls);
	}
	return false;
}

$db = \Config\Database::connect();
$school = $db->table('schools')->where('id', SCHOOL_ID)->get(1)->getRowArray();
if (!$school) {
	say('ERROR: school ' . SCHOOL_ID . ' not found');
	exit(1);
}

$year = $db->table('academic_year')->where('id', ACADEMIC_YEAR_ID)->where('school_id', SCHOOL_ID)->get(1)->getRowArray();
if (!$year) {
	say('ERROR: academic year ' . ACADEMIC_YEAR_ID . ' not found for school ' . SCHOOL_ID);
	exit(1);
}

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

$extraFees = new ExtraFeesModel();
$extraFees->ensureSchema();

say('WISDOM SCHOOL RWANDA extra fees seed');
say('School: ' . ($school['name'] ?? SCHOOL_ID) . ' (id ' . SCHOOL_ID . ')');
say('Academic year: ' . ($year['title'] ?? ACADEMIC_YEAR_ID) . ' (id ' . ACADEMIC_YEAR_ID . ')');
say('Terms: ' . implode(', ', TERMS));
say($dryRun ? 'MODE: dry-run (no DB writes)' : 'MODE: live');
say('Regular classes loaded: ' . count(array_filter($classes, static fn ($c) => !isHolidayClass($c))));
say('');

$summary = [];
$totalUpserts = 0;

foreach (feeDefinitions() as $fee) {
	$scope = $fee['scope'];
	$title = $fee['title'];
	$boarding = $fee['boarding'];
	$day = $fee['day'];
	$matched = [];
	$upserted = 0;

	foreach ($classes as $cls) {
		if (!classMatchesScope($cls, $scope)) {
			continue;
		}
		$matched[] = classLabel($cls);
		$terms = in_array($title, ['Feeding', 'Transport'], true) ? TERMS : [1];
		if ($dryRun) {
			$upserted += count($terms);
			continue;
		}
		foreach ($terms as $term) {
			$id = $extraFees->upsertClassModeFee(
				SCHOOL_ID,
				ACADEMIC_YEAR_ID,
				(int) $cls['id'],
				$title,
				(int) $term,
				$boarding,
				$day,
				CREATED_BY
			);
			if ($id > 0) {
				$upserted++;
			}
		}
	}

	$boardLabel = $boarding === null ? '—' : number_format($boarding, 0, '.', ',');
	$dayLabel = $day === null ? '—' : number_format($day, 0, '.', ',');
	$key = $title . ' [' . $scope . ']';
	$summary[$key] = [
		'title' => $title,
		'scope' => $scope,
		'boarding' => $boardLabel,
		'day' => $dayLabel,
		'classes' => count($matched),
		'upserted' => $upserted,
		'sample' => array_slice($matched, 0, 5),
	];
	$totalUpserts += $upserted;
}

say('Results:');
foreach ($summary as $row) {
	say(sprintf(
		'  %s | scope=%s | boarding=%s day=%s | classes=%d | upserted=%d',
		$row['title'],
		$row['scope'],
		$row['boarding'],
		$row['day'],
		$row['classes'],
		$row['upserted']
	));
	if ($row['sample'] !== []) {
		say('    sample: ' . implode(', ', $row['sample']));
	}
}

say('');
say('Total class fee rows upserted: ' . $totalUpserts);
say('DONE');

exit(0);
