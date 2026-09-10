<?php
/**
 * Map named PNGs in PHOTO_DIR to Wisdom School Rwanda staff photos.
 * Unmatched files are left alone (reported only).
 *
 * Usage (in container):
 *   php deploy/map_wisdom_staff_photos.php
 *   php deploy/map_wisdom_staff_photos.php --execute
 *
 * Env:
 *   PHOTO_DIR=/var/www/html/writable/staff_photos_import
 *   SCHOOL_ID=27
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';
helper('qonics');

$execute = in_array('--execute', $argv ?? [], true);
$schoolId = (int) (getenv('SCHOOL_ID') ?: 27);
$photoDir = rtrim((string) (getenv('PHOTO_DIR') ?: (WRITEPATH . 'staff_photos_import')), '/\\') . DIRECTORY_SEPARATOR;

$db = \Config\Database::connect();

// Ensure Director post exists
$sqlFile = __DIR__ . '/add_director_post.sql';
if (is_file($sqlFile)) {
	try {
		$db->query(file_get_contents($sqlFile));
	} catch (Throwable $e) {
		echo 'Director SQL note: ' . $e->getMessage() . PHP_EOL;
	}
}
(new \App\Models\PostsModel())->ensureLeadershipPosts();
$director = $db->table('posts')->where('title', 'Director')->get(1)->getRowArray();
printf("Director post: #%s %s\n", $director['id'] ?? '?', $director['title'] ?? 'MISSING');

if (!is_dir($photoDir)) {
	fwrite(STDERR, "PHOTO_DIR missing: {$photoDir}\n");
	exit(1);
}

$files = [];
foreach (scandir($photoDir) ?: [] as $f) {
	if ($f === '.' || $f === '..') {
		continue;
	}
	$path = $photoDir . $f;
	if (!is_file($path)) {
		continue;
	}
	$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
	if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
		continue;
	}
	$files[] = $f;
}
sort($files);

$staffRows = $db->table('staffs')
	->select('id, fname, lname, photo, status')
	->where('school_id', $schoolId)
	->where('status <>', 0)
	->orderBy('fname')
	->orderBy('lname')
	->get()
	->getResultArray();

$staff = [];
foreach ($staffRows as $row) {
	$name = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
	$staff[] = [
		'id' => (int) $row['id'],
		'name' => $name,
		'norm' => normalize_person_name($name),
		'tokens' => name_tokens($name),
		'photo' => trim((string) ($row['photo'] ?? '')),
		'used' => false,
	];
}

$skipPatterns = [
	'/^staff\s*ok$/i',
	'/^untitled/i',
	'/modern company portrait/i',
	'/id card$/i',
	'/^design\b/i',
];

$profileDir = FCPATH . 'assets/images/profile/';
if (!is_dir($profileDir)) {
	@mkdir($profileDir, 0775, true);
}

$matched = 0;
$skipped = 0;
$unmatched = 0;
$ambiguous = 0;

echo $execute ? "=== EXECUTE school={$schoolId} ===\n" : "=== DRY RUN school={$schoolId} ===\n";
echo 'Photos: ' . count($files) . ' | Staff: ' . count($staff) . PHP_EOL;

foreach ($files as $file) {
	$stem = pathinfo($file, PATHINFO_FILENAME);
	$skip = false;
	foreach ($skipPatterns as $re) {
		if (preg_match($re, $stem)) {
			$skip = true;
			break;
		}
	}
	if ($skip) {
		echo "LEAVE (generic): {$file}\n";
		$skipped++;
		continue;
	}

	$norm = normalize_person_name($stem);
	$tokens = name_tokens($stem);
	$candidates = [];

	foreach ($staff as $i => $s) {
		if ($s['used']) {
			continue;
		}
		$score = match_score($norm, $tokens, $s['norm'], $s['tokens']);
		if ($score >= 0.92) {
			$candidates[] = ['i' => $i, 'score' => $score, 'staff' => $s];
		}
	}

	usort($candidates, static function ($a, $b) {
		return $b['score'] <=> $a['score'];
	});

	if ($candidates === []) {
		echo "LEAVE (no staff): {$file}\n";
		$unmatched++;
		continue;
	}

	$best = $candidates[0];
	$second = $candidates[1]['score'] ?? 0.0;
	if (count($candidates) > 1 && ($best['score'] - $second) < 0.05 && $second >= 0.92) {
		echo "LEAVE (ambiguous): {$file} -> {$best['staff']['name']} / {$candidates[1]['staff']['name']}\n";
		$ambiguous++;
		continue;
	}

	$staffId = $best['staff']['id'];
	$staffName = $best['staff']['name'];
	$src = $photoDir . $file;
	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: 'png';
	$destName = function_exists('make_profile_photo_name')
		? make_profile_photo_name($ext)
		: ('img_' . bin2hex(random_bytes(8)) . '.' . $ext);

	printf(
		"%s  %s  ->  #%d %s  (score %.2f)%s\n",
		$execute ? 'MAP' : 'WOULD',
		$file,
		$staffId,
		$staffName,
		$best['score'],
		$best['staff']['photo'] !== '' ? ' [replace ' . $best['staff']['photo'] . ']' : ''
	);

	if ($execute) {
		$dest = $profileDir . $destName;
		if (!@copy($src, $dest)) {
			fwrite(STDERR, "COPY FAIL: {$src} -> {$dest}\n");
			continue;
		}
		@chmod($dest, 0644);
		$old = $best['staff']['photo'];
		$db->table('staffs')->where('id', $staffId)->where('school_id', $schoolId)->update(['photo' => $destName]);
		if ($old !== '' && $old !== $destName && is_file($profileDir . $old)) {
			@unlink($profileDir . $old);
		}
	}

	$staff[$best['i']]['used'] = true;
	$matched++;
}

echo PHP_EOL;
printf(
	"Summary: matched=%d unmatched=%d ambiguous=%d skipped=%d%s\n",
	$matched,
	$unmatched,
	$ambiguous,
	$skipped,
	$execute ? '' : ' (dry-run)'
);

function normalize_person_name(string $s): string
{
	$s = mb_strtolower(trim($s), 'UTF-8');
	$s = str_replace(['.', ',', '-', '_', "'", '"'], ' ', $s);
	$s = preg_replace('/\s+/', ' ', $s) ?? $s;
	return trim($s);
}

/** @return list<string> */
function name_tokens(string $s): array
{
	$norm = normalize_person_name($s);
	$parts = preg_split('/\s+/', $norm) ?: [];
	$out = [];
	foreach ($parts as $p) {
		$p = trim($p);
		if ($p === '' || strlen($p) === 1) {
			// keep single letters like "B" from "GASORE B. Frank"
			if (preg_match('/^[a-z]$/', $p)) {
				$out[] = $p;
			}
			continue;
		}
		$out[] = $p;
	}
	return array_values(array_unique($out));
}

/**
 * @param list<string> $aTokens
 * @param list<string> $bTokens
 */
function match_score(string $aNorm, array $aTokens, string $bNorm, array $bTokens): float
{
	if ($aNorm === '' || $bNorm === '') {
		return 0.0;
	}
	if ($aNorm === $bNorm) {
		return 1.0;
	}
	// Compact compare (ignore spaces)
	$ac = str_replace(' ', '', $aNorm);
	$bc = str_replace(' ', '', $bNorm);
	if ($ac === $bc) {
		return 0.99;
	}
	if ($aTokens === [] || $bTokens === []) {
		similar_text($aNorm, $bNorm, $pct);
		return $pct / 100.0;
	}

	$aSet = array_fill_keys($aTokens, true);
	$bSet = array_fill_keys($bTokens, true);
	$inter = 0;
	foreach ($aSet as $t => $_) {
		if (isset($bSet[$t])) {
			$inter++;
		}
	}
	$union = count($aSet) + count($bSet) - $inter;
	$jaccard = $union > 0 ? $inter / $union : 0.0;

	// Require strong token coverage both ways for multi-word names
	$coverA = count($aSet) > 0 ? $inter / count($aSet) : 0.0;
	$coverB = count($bSet) > 0 ? $inter / count($bSet) : 0.0;
	$minCover = min($coverA, $coverB);

	similar_text($aNorm, $bNorm, $pct);
	$sim = $pct / 100.0;

	$score = max($jaccard, $sim * 0.95);
	if ($minCover >= 0.8 && $inter >= 2) {
		$score = max($score, 0.94);
	}
	if ($minCover >= 1.0 && count($aSet) >= 2) {
		$score = max($score, 0.97);
	}
	// First+last strong match when staff has extra middle names
	if (count($aTokens) >= 2 && count($bTokens) >= 2) {
		$aFirst = $aTokens[0];
		$aLast = $aTokens[count($aTokens) - 1];
		$bFirst = $bTokens[0];
		$bLast = $bTokens[count($bTokens) - 1];
		if ($aFirst === $bFirst && $aLast === $bLast) {
			$score = max($score, 0.96);
		}
	}

	return $score;
}
