<?php
/**
 * Update Wisdom School Rubavu (school 30) staff profile photos only.
 * Crops each file to a 3:4 ID portrait on a white background.
 *
 *   php deploy/import_wisdom_rubavu_staff_photos.php --dry-run
 *   php deploy/import_wisdom_rubavu_staff_photos.php --execute
 */
declare(strict_types=1);

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

helper('qonics');

const TARGET_SCHOOL_ID = 30;
const TARGET_NAME = 'WISDOM SCHOOL RUBAVU';

$execute = in_array('--execute', $argv ?? [], true);
$photoDir = rtrim((string) (getenv('PHOTO_DIR') ?: (WRITEPATH . 'staff_photos_import_rubavu')), '/\\') . DIRECTORY_SEPARATOR;
$profileDir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;

function say(string $msg): void
{
	echo $msg . PHP_EOL;
}

function tokens(string $value): array
{
	$value = strtoupper($value);
	$value = preg_replace('/\.(JPE?G|PNG|WEBP)$/i', '', $value) ?? $value;
	$value = str_replace(['_', '-', '.'], ' ', $value);
	$value = preg_replace('/\b(JPG|JPEG|PNG|WEBP|PICTURE|PHOTO)\b/', ' ', $value) ?? $value;
	$parts = preg_split('/\s+/', trim($value)) ?: [];
	$aliases = [
		'MICHEAL' => 'MICHAEL',
		'MADELENE' => 'MADELEINE',
		'NIYODUSENGA' => 'DUSENGIMANA',
	];
	$out = [];
	foreach ($parts as $part) {
		$part = preg_replace('/[^A-Z]/', '', $part) ?? '';
		if (strlen($part) < 3) {
			continue;
		}
		if (isset($aliases[$part])) {
			$part = $aliases[$part];
		}
		$out[$part] = $part;
	}
	return array_values($out);
}

function slug_tokens(array $parts): string
{
	return strtolower(implode('', $parts));
}

function list_photos(string $dir): array
{
	if (!is_dir($dir)) {
		return [];
	}
	$files = [];
	foreach (scandir($dir) ?: [] as $name) {
		if ($name === '.' || $name === '..') {
			continue;
		}
		$path = $dir . $name;
		if (!is_file($path)) {
			continue;
		}
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
			continue;
		}
		$tok = tokens(pathinfo($name, PATHINFO_FILENAME));
		$files[] = [
			'file' => $name,
			'path' => $path,
			'tokens' => $tok,
			'slug' => slug_tokens($tok),
			'len' => count($tok),
		];
	}
	usort($files, static function ($a, $b) {
		if ($a['len'] !== $b['len']) {
			return $b['len'] <=> $a['len'];
		}
		return strlen($b['slug']) <=> strlen($a['slug']);
	});
	return $files;
}

$db = \Config\Database::connect();
$school = $db->table('schools')->select('id, name')->where('id', TARGET_SCHOOL_ID)->get(1)->getRowArray();
if (!$school) {
	fwrite(STDERR, "School " . TARGET_SCHOOL_ID . " not found\n");
	exit(1);
}
say('school=' . $school['id'] . ' ' . ($school['name'] ?? ''));
say('mode=' . ($execute ? 'EXECUTE' : 'DRY-RUN'));
say('photos_dir=' . $photoDir);
say('profile_dir=' . $profileDir);

$staffRows = $db->table('staffs')
	->select('id, fname, lname, photo, status')
	->where('school_id', TARGET_SCHOOL_ID)
	->whereIn('status', [1, 2])
	->orderBy('fname', 'ASC')
	->get()->getResultArray();
say('staff_active=' . count($staffRows));

$photos = list_photos($photoDir);
say('photo_files=' . count($photos));
if ($photos === []) {
	fwrite(STDERR, "No photos found in {$photoDir}\n");
	exit(1);
}

$staff = [];
foreach ($staffRows as $row) {
	$full = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
	$tok = tokens($full);
	$staff[] = [
		'id' => (int) $row['id'],
		'name' => $full,
		'tokens' => $tok,
		'slug' => slug_tokens($tok),
		'photo' => (string) ($row['photo'] ?? ''),
		'used' => false,
	];
}

$matches = [];
$unmatchedPhotos = [];
foreach ($photos as $photo) {
	$bestIdx = -1;
	$bestScore = 0.0;
	foreach ($staff as $idx => $person) {
		if ($person['used']) {
			continue;
		}
		$overlap = count(array_intersect($photo['tokens'], $person['tokens']));
		if ($overlap < 1) {
			$fuzzy = 0;
			foreach ($photo['tokens'] as $pt) {
				foreach ($person['tokens'] as $st) {
					if (levenshtein($pt, $st) === 1 && strlen($pt) >= 5 && strlen($st) >= 5) {
						$fuzzy++;
					}
				}
			}
			$overlap += $fuzzy;
		}
		if ($overlap < 1) {
			continue;
		}
		$score = $overlap / max(count($photo['tokens']), count($person['tokens']), 1);
		if ($photo['slug'] !== '' && $photo['slug'] === $person['slug']) {
			$score = 1.0;
		} elseif ($overlap >= 1) {
			$candidates = 0;
			foreach ($staff as $other) {
				if ($other['used']) {
					continue;
				}
				$o = count(array_intersect($photo['tokens'], $other['tokens']));
				if ($o < 1) {
					foreach ($photo['tokens'] as $pt) {
						foreach ($other['tokens'] as $st) {
							if (levenshtein($pt, $st) === 1 && strlen($pt) >= 5 && strlen($st) >= 5) {
								$o++;
								break 2;
							}
						}
					}
				}
				if ($o >= 1) {
					$candidates++;
				}
			}
			if ($candidates === 1) {
				$score = max($score, 0.76);
			} elseif ($overlap < 2 && $score < 0.75) {
				continue;
			}
		} elseif ($overlap < 2 && $score < 0.75) {
			continue;
		}
		if ($score > $bestScore) {
			$bestScore = $score;
			$bestIdx = $idx;
		}
	}
	if ($bestIdx < 0 || $bestScore < 0.5) {
		$unmatchedPhotos[] = $photo['file'];
		continue;
	}
	$staff[$bestIdx]['used'] = true;
	$matches[] = [
		'staff_id' => $staff[$bestIdx]['id'],
		'name' => $staff[$bestIdx]['name'],
		'file' => $photo['file'],
		'path' => $photo['path'],
		'score' => round($bestScore, 2),
		'old' => $staff[$bestIdx]['photo'],
	];
}

say('matched=' . count($matches) . ' unmatched_photos=' . count($unmatchedPhotos));
foreach ($matches as $row) {
	say('MATCH id=' . $row['staff_id'] . ' score=' . $row['score'] . ' file=' . $row['file']);
}
foreach ($unmatchedPhotos as $file) {
	say('UNMATCHED_PHOTO ' . $file);
}

if (!$execute) {
	say('Dry run only. Re-run with --execute to crop, save, and update Rubavu staff photos.');
	exit(0);
}

if (!is_dir($profileDir) && !@mkdir($profileDir, 0775, true) && !is_dir($profileDir)) {
	fwrite(STDERR, "Cannot create {$profileDir}\n");
	exit(1);
}

$saved = 0;
$failed = 0;
foreach ($matches as $row) {
	$name = function_exists('make_profile_photo_name') ? make_profile_photo_name('jpg') : ('img_' . bin2hex(random_bytes(8)) . '.jpg');
	$dest = $profileDir . $name;
	$ok = save_profile_photo_white_bg($row['path'], $dest, true);
	if (!$ok || !is_file($dest)) {
		$failed++;
		say('FAIL_PHOTO id=' . $row['staff_id'] . ' file=' . $row['file']);
		continue;
	}
	$db->table('staffs')
		->where('id', $row['staff_id'])
		->where('school_id', TARGET_SCHOOL_ID)
		->update(['photo' => $name]);
	$old = basename((string) $row['old']);
	if ($old !== '' && $old !== $name && is_file($profileDir . $old)) {
		@unlink($profileDir . $old);
	}
	$saved++;
	say('SAVED id=' . $row['staff_id'] . ' photo=' . $name);
}

say('DONE saved=' . $saved . ' failed=' . $failed);
exit($failed === 0 ? 0 : 1);
