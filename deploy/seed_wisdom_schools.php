<?php
/**
 * Seed all 15 Wisdom Schools branches as standalone schools on VPS/local.
 * Run: php deploy/seed_wisdom_schools.php
 * Or:  docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_schools.php
 */
define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Config/Paths.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$db = \Config\Database::connect();

const DEFAULT_PASSWORD = 'Wisdom@2026';
const DEFAULT_PACKAGE = 7;
const CREATED_BY = 1;

const WISDOM_RWANDA_NAMES = ['WISDOM SCHOOL RWANDA', 'Wisdom School Rwanda'];

$branches = [
	['MUS', 'Musanze', true],  // use existing WISDOM SCHOOL RWANDA — no separate school/admin
	['NYB', 'Nyabihu', false],
	['RUB', 'Rubavu', false],
	['RUN', 'Runda', false],
	['NYM', 'Nyamasheke', false],
	['RBE', 'Rubengera', false],
	['FUM', 'Fumbwe', false],
	['KAY', 'Kayonza', false],
	['KIR', 'Kiramuruzi', false],
	['MUY', 'Muyumbu', false],
	['SUS', 'Susa', false],
	['KAN', 'Kanzenze', false],
	['BUR', 'Burera', false],
	['NGO', 'Ngororero', false],
	['KAB', 'Kabarore', false],
];

$now = date('Y-m-d H:i:s');
$hash = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);
$created = [];
$skipped = [];
$errors = [];

foreach ($branches as [$code, $location, $useWisdomRwanda]) {
	if ($useWisdomRwanda) {
		$existingSchool = null;
		foreach (WISDOM_RWANDA_NAMES as $tryName) {
			$existingSchool = $db->table('schools')->where('name', $tryName)->get(1)->getRowArray();
			if ($existingSchool) {
				break;
			}
		}
		if (!$existingSchool) {
			$existingSchool = $db->table('schools')->like('name', 'WISDOM SCHOOL RWANDA', 'both')->get(1)->getRowArray();
		}
		if (!$existingSchool) {
			$errors[] = 'WISDOM SCHOOL RWANDA not found for Musanze branch';
			continue;
		}
		$schoolId = (int) $existingSchool['id'];
		$skipped[] = ['school' => $existingSchool['name'] . ' (Musanze)', 'id' => $schoolId, 'reason' => 'master school — login not created'];
		$headStaff = $db->table('staffs')->where('school_id', $schoolId)->orderBy('id', 'ASC')->get(1)->getRowArray();
		try {
			$schema = new \App\Models\BudgetSchemaModel();
			$schema->ensureSchema();
			$schema->seedFoundation($schoolId, $headStaff ? (int) $headStaff['id'] : 0);
			$org = $db->table('organizations')->where('slug', 'wisdom-schools')->get(1)->getRowArray();
			if ($org) {
				$db->table('branches')
					->where('organization_id', (int) $org['id'])
					->where('branch_code', $code)
					->update(['school_id' => $schoolId, 'updated_at' => $now]);
			}
		} catch (\Throwable $e) {
			$errors[] = 'Budget seed Musanze: ' . $e->getMessage();
		}
		continue;
	}

	$schoolName = 'Wisdom ' . $location;
	$acronym = 'WIS-' . $code;
	$email = strtolower('admin.' . $code . '@wisdomschools.rw');
	if ($code === 'KAY') {
		$email = 'umutonihenry@gmail.com';
	}
	$phone = '078800' . str_pad((string) array_search([$code, $location, false], $branches, true) + 1, 4, '0', STR_PAD_LEFT);

	$existingSchool = $db->table('schools')
		->where('name', $schoolName)
		->orWhere('acronym', $acronym)
		->get(1)->getRowArray();

	if ($existingSchool) {
		$schoolId = (int) $existingSchool['id'];
		$skipped[] = ['school' => $schoolName, 'id' => $schoolId, 'reason' => 'school exists'];
	} else {
		try {
			$db->table('schools')->insert([
				'name' => $schoolName,
				'acronym' => $acronym,
				'slogan' => 'Excellence in Education',
				'logo' => '',
				'country' => 'Rwanda',
				'address' => $location . ', Rwanda',
				'phone' => $phone,
				'email' => $email,
				'head_master' => 'Head Teacher ' . $location,
				'head_master_gender' => 'M',
				'extra_sms' => 0,
				'card_design' => 0,
				'card_background' => '',
				'header_text_1' => '',
				'header_text_2' => '',
				'header_color' => '#0a66b7',
				'main_color' => '#0a66b7',
				'footer_color' => '#000000',
				'capitalize' => 1,
				'sf_card_background' => '',
				'website' => '',
				'pobox' => null,
				'package' => DEFAULT_PACKAGE,
				'active_term' => null,
				'discipline_max' => null,
				'secret' => '123456',
				'in_time' => '07:00:00',
				'leave_time' => '17:00:00',
				'tolerance' => 20,
				'pocket_money_phone' => '',
				'created_at' => $now,
				'created_by' => CREATED_BY,
				'status' => 1,
				'updated_at' => $now,
			]);
			$schoolId = (int) $db->insertID();
			$created[] = ['school' => $schoolName, 'id' => $schoolId];
		} catch (\Throwable $e) {
			$errors[] = $schoolName . ': ' . $e->getMessage();
			continue;
		}
	}

	$staff = $db->table('staffs')->where('email', $email)->get(1)->getRowArray();
	if (!$staff) {
		try {
			$db->table('staffs')->insert([
				'school_id' => $schoolId,
				'fname' => 'Admin',
				'lname' => $location,
				'phone' => $phone,
				'password' => $hash,
				'status' => 1,
				'last_login' => 0,
				'email' => $email,
				'post' => 1,
				'shift_id' => 0,
				'country' => 'Rwanda',
				'city' => $location,
				'address' => $location . ', Rwanda',
				'photo' => '',
				'lang' => 'en',
				'next_login' => 0,
				'reset_exp' => 0,
				'created_at' => $now,
				'created_by' => CREATED_BY,
				'updated_at' => $now,
				'updated_by' => CREATED_BY,
				'updateVersion' => 1,
			]);
		} catch (\Throwable $e) {
			$errors[] = $email . ': ' . $e->getMessage();
		}
	} else {
		$db->table('staffs')->where('id', (int) $staff['id'])->update([
			'school_id' => $schoolId,
			'status' => 1,
			'post' => 1,
			'password' => $hash,
			'updated_at' => $now,
		]);
	}

	// Budget branch link + org setup
	try {
		$schema = new \App\Models\BudgetSchemaModel();
		$schema->ensureSchema();
		$staffRow = $db->table('staffs')->where('email', $email)->get(1)->getRowArray();
		$staffId = $staffRow ? (int) $staffRow['id'] : 0;
		$schema->seedFoundation($schoolId, $staffId);
	} catch (\Throwable $e) {
		$errors[] = 'Budget seed ' . $schoolName . ': ' . $e->getMessage();
	}
}

echo "=== Wisdom Schools seed complete ===\n";
echo 'Default password for all accounts: ' . DEFAULT_PASSWORD . "\n\n";

if ($created) {
	echo "Created schools (" . count($created) . "):\n";
	foreach ($created as $row) {
		echo "  [{$row['id']}] {$row['school']}\n";
	}
	echo "\n";
}
if ($skipped) {
	echo "Existing schools (" . count($skipped) . "):\n";
	foreach ($skipped as $row) {
		echo "  [{$row['id']}] {$row['school']}\n";
	}
	echo "\n";
}

echo "Login credentials (email = username, all Head master):\n";
foreach ($branches as [$code, $location, $useWisdomRwanda]) {
	if ($useWisdomRwanda) {
		$row = $db->table('schools s')
			->select('s.name, st.email')
			->join('staffs st', 'st.school_id = s.id AND st.post = 1', 'left')
			->like('s.name', 'WISDOM SCHOOL RWANDA', 'both')
			->get(1)->getRowArray();
		$label = $row['name'] ?? 'WISDOM SCHOOL RWANDA';
		$email = $row['email'] ?? '(existing headmaster email)';
		echo sprintf("  %-22s  %s  (Musanze branch)\n", $label, $email);
		continue;
	}
	$email = strtolower('admin.' . $code . '@wisdomschools.rw');
	if ($code === 'KAY') {
		$email = 'umutonihenry@gmail.com';
	}
	echo sprintf("  %-22s  %s\n", 'Wisdom ' . $location, $email);
}

if ($errors) {
	echo "\nWarnings/errors:\n";
	foreach ($errors as $err) {
		echo "  - $err\n";
	}
	exit(1);
}

exit(0);
