<?php

namespace App\Models;

use App\Libraries\WisdomDisciplineCatalog;
use CodeIgniter\Model;

class DisciplineCodeModel extends Model
{
	protected $table = 'discipline_codes';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = [
		'school_id', 'category_key', 'category_en', 'category_rw', 'code_no',
		'title_en', 'title_rw', 'first_marks', 'second_marks', 'third_marks',
		'first_sanction_en', 'first_sanction_rw', 'second_sanction_en', 'second_sanction_rw',
		'third_sanction_en', 'third_sanction_rw', 'sort_order', 'active',
	];
	protected $useTimestamps = true;

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}
		$db = \Config\Database::connect();
		$db->query("CREATE TABLE IF NOT EXISTS `discipline_codes` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`category_key` VARCHAR(40) NOT NULL DEFAULT '',
			`category_en` VARCHAR(255) NOT NULL DEFAULT '',
			`category_rw` VARCHAR(255) NOT NULL DEFAULT '',
			`code_no` INT NOT NULL DEFAULT 1,
			`title_en` TEXT NOT NULL,
			`title_rw` TEXT NOT NULL,
			`first_marks` INT NOT NULL DEFAULT 0,
			`second_marks` INT NOT NULL DEFAULT 0,
			`third_marks` INT NOT NULL DEFAULT 0,
			`first_sanction_en` VARCHAR(255) NOT NULL DEFAULT '',
			`first_sanction_rw` VARCHAR(255) NOT NULL DEFAULT '',
			`second_sanction_en` VARCHAR(255) NOT NULL DEFAULT '',
			`second_sanction_rw` VARCHAR(255) NOT NULL DEFAULT '',
			`third_sanction_en` VARCHAR(255) NOT NULL DEFAULT '',
			`third_sanction_rw` VARCHAR(255) NOT NULL DEFAULT '',
			`sort_order` INT NOT NULL DEFAULT 0,
			`active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_dc_school` (`school_id`, `active`, `sort_order`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$cols = array_map('strtolower', $db->getFieldNames('disciplines') ?: []);
		if (!in_array('code_id', $cols, true)) {
			$db->query("ALTER TABLE `disciplines` ADD COLUMN `code_id` INT UNSIGNED NULL DEFAULT NULL AFTER `type`");
		}
		if (!in_array('occurrence', $cols, true)) {
			$db->query("ALTER TABLE `disciplines` ADD COLUMN `occurrence` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `code_id`");
		}
		self::$schemaReady = true;
	}

	public function seedIfEmpty(int $schoolId): void
	{
		$this->ensureSchema();
		if ($schoolId < 1) {
			return;
		}
		$exists = $this->where('school_id', $schoolId)->countAllResults();
		if ($exists > 0) {
			return;
		}
		$now = date('Y-m-d H:i:s');
		$sort = 0;
		foreach (WisdomDisciplineCatalog::categories() as $cat) {
			foreach ($cat['items'] as $item) {
				$sort++;
				$marks = $item['marks'] ?? [0, 0, 0];
				$se = $item['se'] ?? ['', '', ''];
				$sr = $item['sr'] ?? ['', '', ''];
				$this->insert([
					'school_id' => $schoolId,
					'category_key' => (string) ($cat['key'] ?? ''),
					'category_en' => (string) ($cat['en'] ?? ''),
					'category_rw' => (string) ($cat['rw'] ?? ''),
					'code_no' => (int) ($item['no'] ?? $sort),
					'title_en' => (string) ($item['en'] ?? ''),
					'title_rw' => (string) ($item['rw'] ?? ''),
					'first_marks' => (int) ($marks[0] ?? 0),
					'second_marks' => (int) ($marks[1] ?? 0),
					'third_marks' => (int) ($marks[2] ?? 0),
					'first_sanction_en' => (string) ($se[0] ?? ''),
					'first_sanction_rw' => (string) ($sr[0] ?? ''),
					'second_sanction_en' => (string) ($se[1] ?? ''),
					'second_sanction_rw' => (string) ($sr[1] ?? ''),
					'third_sanction_en' => (string) ($se[2] ?? ''),
					'third_sanction_rw' => (string) ($sr[2] ?? ''),
					'sort_order' => $sort,
					'active' => 1,
					'created_at' => $now,
					'updated_at' => $now,
				]);
			}
		}
	}

	/** @return list<array<string,mixed>> */
	public function listCodes(int $schoolId, bool $activeOnly = false): array
	{
		$this->ensureSchema();
		$this->seedIfEmpty($schoolId);
		$b = $this->where('school_id', $schoolId);
		if ($activeOnly) {
			$b->where('active', 1);
		}
		return $b->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
	}

	/** @return array<string, list<array<string,mixed>>> */
	public function groupedCodes(int $schoolId, bool $activeOnly = false): array
	{
		$grouped = [];
		foreach ($this->listCodes($schoolId, $activeOnly) as $row) {
			$key = (string) ($row['category_key'] ?? 'other');
			if (!isset($grouped[$key])) {
				$grouped[$key] = [
					'key' => $key,
					'en' => (string) ($row['category_en'] ?? ''),
					'rw' => (string) ($row['category_rw'] ?? ''),
					'items' => [],
				];
			}
			$grouped[$key]['items'][] = $row;
		}
		return array_values($grouped);
	}

	public function countOccurrences(int $schoolId, int $studentId, int $codeId, int $termId): int
	{
		if ($schoolId < 1 || $studentId < 1 || $codeId < 1 || $termId < 1) {
			return 0;
		}
		$db = \Config\Database::connect();
		$row = $db->table('disciplines')
			->select('COUNT(*) AS c')
			->where('school_id', $schoolId)
			->where('student_id', $studentId)
			->where('code_id', $codeId)
			->where('active_term', $termId)
			->get(1)->getRowArray();
		return (int) ($row['c'] ?? 0);
	}

	/**
	 * @param array<string,mixed> $code
	 * @return array{occurrence:int,marks:int,sanction_en:string,sanction_rw:string,label_en:string,label_rw:string}
	 */
	public function resolveOccurrence(array $code, int $previousCount): array
	{
		$n = $previousCount + 1;
		if ($n < 1) {
			$n = 1;
		}
		if ($n > 3) {
			$n = 3;
		}
		$marks = [1 => (int) ($code['first_marks'] ?? 0), 2 => (int) ($code['second_marks'] ?? 0), 3 => (int) ($code['third_marks'] ?? 0)];
		$en = [1 => (string) ($code['first_sanction_en'] ?? ''), 2 => (string) ($code['second_sanction_en'] ?? ''), 3 => (string) ($code['third_sanction_en'] ?? '')];
		$rw = [1 => (string) ($code['first_sanction_rw'] ?? ''), 2 => (string) ($code['second_sanction_rw'] ?? ''), 3 => (string) ($code['third_sanction_rw'] ?? '')];
		$labels = [
			1 => ['en' => '1st time', 'rw' => 'Bwa mbere'],
			2 => ['en' => '2nd time', 'rw' => 'Bwa kabiri'],
			3 => ['en' => '3rd time', 'rw' => 'Bwa gatatu'],
		];
		return [
			'occurrence' => $n,
			'marks' => max(0, $marks[$n]),
			'sanction_en' => $en[$n],
			'sanction_rw' => $rw[$n],
			'label_en' => $labels[$n]['en'],
			'label_rw' => $labels[$n]['rw'],
		];
	}

	public static function discLang(): string
	{
		$lang = strtolower(trim((string) (session()->get('disc_lang') ?? 'en')));
		return $lang === 'rw' ? 'rw' : 'en';
	}

	/** @param array<string,mixed> $code */
	public static function titleFor(array $code, ?string $lang = null): string
	{
		$lang = $lang ?: self::discLang();
		$rw = trim((string) ($code['title_rw'] ?? ''));
		$en = trim((string) ($code['title_en'] ?? ''));
		if ($lang === 'rw') {
			return $rw !== '' ? $rw : $en;
		}
		return $en !== '' ? $en : $rw;
	}

	/** @param array<string,mixed> $code */
	public static function categoryFor(array $code, ?string $lang = null): string
	{
		$lang = $lang ?: self::discLang();
		$rw = trim((string) ($code['category_rw'] ?? ''));
		$en = trim((string) ($code['category_en'] ?? ''));
		if ($lang === 'rw') {
			return $rw !== '' ? $rw : $en;
		}
		return $en !== '' ? $en : $rw;
	}

	public static function slugCategory(string $en): string
	{
		$s = strtolower(trim($en));
		$s = preg_replace('/[^a-z0-9]+/', '_', $s);
		$s = trim((string) $s, '_');
		if ($s === '') {
			return 'other';
		}
		return substr($s, 0, 40);
	}

	public function resolveCategoryKey(int $schoolId, string $en, string $rw = ''): string
	{
		$en = trim($en);
		$rw = trim($rw);
		$b = $this->where('school_id', $schoolId);
		$b->groupStart();
		if ($en !== '') {
			$b->where('category_en', $en);
		}
		if ($rw !== '') {
			if ($en !== '') {
				$b->orWhere('category_rw', $rw);
			} else {
				$b->where('category_rw', $rw);
			}
		}
		$b->groupEnd();
		$row = $b->orderBy('id', 'ASC')->first();
		if ($row && trim((string) ($row['category_key'] ?? '')) !== '') {
			return (string) $row['category_key'];
		}
		return self::slugCategory($en !== '' ? $en : $rw);
	}
}
