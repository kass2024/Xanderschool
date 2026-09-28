<?php
/**
 * Wisdom child-school acronyms: capital letters only, no hyphen or other marks.
 * The master school (Wisdom School Rwanda) is left unchanged.
 *
 * Usage:
 *   php deploy/set_wisdom_child_acronyms.php --dry-run
 *   php deploy/set_wisdom_child_acronyms.php
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();
$dryRun = in_array('--dry-run', $argv ?? [], true);

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

function letters_only(string $value): string
{
	return preg_replace('/[^A-Z]/', '', strtoupper($value)) ?? '';
}

function compact_name(string $value): string
{
	return preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? '';
}

$hasMaster = $db->fieldExists('is_master', 'schools');
$select = 'id, name, acronym, status';
if ($hasMaster) {
	$select .= ', is_master, master_school_id';
}
$rows = $db->table('schools')
	->select($select)
	->groupStart()
		->like('name', 'WISDOM', 'both')
		->orLike('name', 'Wisdom', 'both')
		->orLike('acronym', 'WIS', 'after')
		->orWhere('acronym', 'WSY')
	->groupEnd()
	->orderBy('id', 'ASC')
	->get()
	->getResultArray();

$now = date('Y-m-d H:i:s');
$changes = [];
foreach ($rows as $row) {
	$nameKey = compact_name((string) ($row['name'] ?? ''));
	$isMaster = $hasMaster && !empty($row['is_master']);
	$isRwandaMaster = $nameKey === 'WISDOMSCHOOLRWANDA' || $nameKey === 'WISDOMSCHOOLS';
	$current = (string) ($row['acronym'] ?? '');
	$next = letters_only($current);
	if ($isMaster || $isRwandaMaster) {
		say('KEEP master id=' . $row['id'] . ' ' . $row['name'] . ' acronym=' . $current);
		continue;
	}
	if ($next === '' || !preg_match('/^[A-Z]{2,12}$/', $next)) {
		say('SKIP id=' . $row['id'] . ' ' . $row['name'] . ' acronym=' . $current . ' (cannot make a letters-only code)');
		continue;
	}
	if ($next === $current && preg_match('/^[A-Z]+$/', $current)) {
		say('OK   id=' . $row['id'] . ' ' . $row['name'] . ' acronym=' . $current);
		continue;
	}
	$taken = $db->table('schools')->where('acronym', $next)->where('id !=', (int) $row['id'])->get(1)->getRowArray();
	if ($taken) {
		say('SKIP id=' . $row['id'] . ' ' . $row['name'] . ' ' . $current . ' -> ' . $next . ' (already used by id ' . $taken['id'] . ')');
		continue;
	}
	$changes[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'from' => $current, 'to' => $next];
}

if ($changes !== [] && !$dryRun) {
	$db->transStart();
	foreach ($changes as $change) {
		$db->table('schools')->where('id', $change['id'])->update([
			'acronym' => $change['to'],
			'updated_at' => $now,
		]);
	}
	$db->transComplete();
	if (!$db->transStatus()) {
		fwrite(STDERR, "Acronym update failed\n");
		exit(1);
	}
}

if ($dryRun) {
	say('DRY RUN — no database changes committed');
}
say('Updated: ' . count($changes));
foreach ($changes as $change) {
	say('  id=' . $change['id'] . ' ' . $change['name'] . ' ' . $change['from'] . ' -> ' . $change['to']);
}

exit(0);
