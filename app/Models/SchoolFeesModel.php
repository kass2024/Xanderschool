<?php
namespace App\Models;

use App\Controllers\Home;
use CodeIgniter\Model;

class SchoolFeesModel extends Model
{
	protected $table = "school_fees";
	protected $allowedFields = [
		"school_id", "level", "department", "class_id",
		"amount", "amount_boarding", "amount_day",
		"term", "academic_year", "created_by",
	];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField = 'created_at';
	protected $updatedField = 'updated_at';

	/**
	 * Ensure class_id + boarding/day amount columns exist.
	 */
	public function ensureSchema(): void
	{
		$db = \Config\Database::connect();
		if (!$db->fieldExists('class_id', 'school_fees')) {
			$db->query('ALTER TABLE `school_fees` ADD COLUMN `class_id` int(11) DEFAULT NULL AFTER `department`');
		}
		if (!$db->fieldExists('amount_boarding', 'school_fees')) {
			$db->query('ALTER TABLE `school_fees` ADD COLUMN `amount_boarding` decimal(15,2) DEFAULT NULL AFTER `amount`');
		}
		if (!$db->fieldExists('amount_day', 'school_fees')) {
			$db->query('ALTER TABLE `school_fees` ADD COLUMN `amount_day` decimal(15,2) DEFAULT NULL AFTER `amount_boarding`');
		}
	}

	/**
	 * All school fee rows for a school and academic year.
	 */
	public function listForSchool(int $schoolId, int $academicYearId): array
	{
		$this->ensureSchema();

		return $this->select("
			school_fees.id,
			school_fees.amount,
			school_fees.amount_boarding,
			school_fees.amount_day,
			school_fees.term,
			school_fees.level AS level_id,
			school_fees.department AS department_id,
			school_fees.class_id,
			l.title AS level_title,
			d.code AS dept_code,
			d.title AS dept_title,
			c.title AS class_title,
			f.abbrev AS faculty_code,
			f.title AS faculty_title,
			f.type AS faculty_type,
			ac.title AS academic_year_title,
			school_fees.academic_year AS academic_year_id,
			TRIM(CONCAT(COALESCE(stf.fname,''),' ',COALESCE(stf.lname,''))) AS created_by_name
		")
			->join("levels l", "l.id = school_fees.level", "LEFT")
			->join("departments d", "d.id = school_fees.department", "LEFT")
			->join("faculty f", "f.id = d.faculty_id", "LEFT")
			->join("classes c", "c.id = school_fees.class_id", "LEFT")
			->join("academic_year ac", "ac.id = school_fees.academic_year", "LEFT")
			->join("staffs stf", "stf.id = school_fees.created_by", "LEFT")
			->where("school_fees.school_id", $schoolId)
			->where("school_fees.academic_year", $academicYearId)
			->orderBy("l.title", "ASC")
			->orderBy("c.title", "ASC")
			->orderBy("d.code", "ASC")
			->orderBy("school_fees.term", "ASC")
			->get()->getResultArray();
	}

	/**
	 * Every class in the school (including special/ANP). Holiday coaching is excluded later.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function listClassesForSchool(int $schoolId): array
	{
		if ($schoolId < 1) {
			return [];
		}

		return $this->db->table('classes c')
			->select("c.id AS class_id, c.title AS class_title, c.level AS level_id, c.department AS department_id,
				l.title AS level_title, d.code AS dept_code, d.title AS dept_title,
				f.abbrev AS faculty_code, f.title AS faculty_title, f.type AS faculty_type")
			->join('levels l', 'l.id = c.level', 'left')
			->join('departments d', 'd.id = c.department', 'left')
			->join('faculty f', 'f.id = d.faculty_id', 'left')
			->where('c.school_id', $schoolId)
			->orderBy('l.title', 'ASC')
			->orderBy('d.code', 'ASC')
			->orderBy('c.title', 'ASC')
			->get()->getResultArray();
	}

	public static function isHolidayClass(array $row): bool
	{
		$hay = strtolower(trim(implode(' ', [
			$row['class_title'] ?? '',
			$row['title'] ?? '',
			$row['level_title'] ?? '',
			$row['level_name'] ?? '',
			$row['dept_title'] ?? '',
			$row['dept_code'] ?? '',
			$row['code'] ?? '',
			$row['faculty_title'] ?? '',
			$row['faculty_code'] ?? '',
		])));
		return strpos($hay, 'holiday') !== false;
	}

	/**
	 * Match class-creation names: "P1 A", "Baby class", "S4 MCB", "S4 ANP", "Level 3 SOD".
	 * Do not insert generic stage names (Primary, Nursery, O'Level) into the class label.
	 */
	public static function displayLabel(array $row): string
	{
		$level = trim((string) ($row['level_title'] ?? $row['level_name'] ?? ''));
		if (preg_match('/^level\s+(\d+)$/i', $level, $m)) {
			$level = 'Level ' . $m[1];
		}
		$classTitle = trim((string) ($row['class_title'] ?? $row['title'] ?? ''));
		if ($classTitle === '-----') {
			$classTitle = '';
		}
		$dept = trim((string) ($row['dept_code'] ?? $row['code'] ?? ''));
		$faculty = trim((string) ($row['faculty_code'] ?? ''));
		$deptTitle = trim((string) ($row['dept_title'] ?? ''));
		$mid = $dept !== '' ? $dept : $faculty;

		$parts = [];
		if ($level !== '') {
			$parts[] = $level;
		}
		if ($mid !== '' && !self::isGenericStageName($mid, $deptTitle)) {
			$alreadyInLevel = strcasecmp($mid, $level) === 0;
			$alreadyInTitle = $classTitle !== '' && (
				strcasecmp($mid, $classTitle) === 0
				|| stripos($classTitle, $mid) !== false
			);
			if (!$alreadyInLevel && !$alreadyInTitle) {
				$parts[] = $mid;
			}
		}
		if ($classTitle !== '' && strcasecmp($classTitle, $level) !== 0) {
			$parts[] = $classTitle;
		}
		$label = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
		return $label !== '' ? $label : $level;
	}

	/** Primary / Nursery / O'Level are stages, not part of the spoken class name. */
	private static function isGenericStageName(string $code, string $title = ''): bool
	{
		$hay = strtolower(trim($code . ' ' . $title));
		if ($hay === '') {
			return false;
		}
		if (preg_match('/\b(anp|nursing)\b/', $hay)) {
			return false;
		}
		return (bool) preg_match(
			'/\b(pri|pre|nur|primary|nursery|ordinary(\s*level)?|o[\'’]?\s*-?level|olevel|a[\'’]?\s*-?level|alevel|advanced(\s*level)?|reb|secondary|high\s*school)\b/i',
			$hay
		);
	}

	/**
	 * Boarding / day amounts for display (falls back to legacy single amount).
	 *
	 * @return array{boarding:?float,day:?float,legacy:float}
	 */
	public static function modeAmounts(array $row): array
	{
		$legacy = (float) ($row['amount'] ?? 0);
		$boarding = $row['amount_boarding'] ?? null;
		$day = $row['amount_day'] ?? null;
		$boarding = ($boarding === null || $boarding === '') ? null : (float) $boarding;
		$day = ($day === null || $day === '') ? null : (float) $day;
		if ($boarding === null && $day === null && $legacy > 0) {
			// Legacy fee: show same amount for both until re-saved with modes
			$boarding = $legacy;
			$day = $legacy;
		}
		return [
			'boarding' => $boarding,
			'day' => $day,
			'legacy' => $legacy,
		];
	}

	/**
	 * Normalize a fee row for JSON API responses (web + Android).
	 */
	public static function formatRowForApi(array $row): array
	{
		$term = (int) ($row['term'] ?? 0);
		$modes = self::modeAmounts($row);

		return [
			'id' => (int) ($row['id'] ?? 0),
			'level_id' => (int) ($row['level_id'] ?? 0),
			'level' => (string) ($row['level_title'] ?? ''),
			'class_id' => (int) ($row['class_id'] ?? 0),
			'class_title' => (string) ($row['class_title'] ?? ''),
			'display_label' => self::displayLabel($row),
			'department_id' => (int) ($row['department_id'] ?? 0),
			'department_code' => (string) ($row['dept_code'] ?? ''),
			'department' => (string) ($row['dept_title'] ?? ''),
			'term' => $term,
			'term_label' => Home::TermToStr($term),
			'amount' => (float) ($row['amount'] ?? 0),
			'amount_boarding' => $modes['boarding'],
			'amount_day' => $modes['day'],
			'academic_year_id' => (int) ($row['academic_year_id'] ?? 0),
			'academic_year' => (string) ($row['academic_year_title'] ?? ''),
		];
	}

	/**
	 * Group fee rows by level + department + class for compact table display.
	 */
	public static function groupByLevelDept(array $fees): array
	{
		$groups = [];
		foreach ($fees as $fee) {
			$classId = (int) ($fee['class_id'] ?? 0);
			$key = (int) ($fee['level_id'] ?? 0) . '-' . (int) ($fee['department_id'] ?? 0) . '-' . $classId;
			if (!isset($groups[$key])) {
				$groups[$key] = self::emptyGroupFromRow($fee, $classId);
			}
			$term = (int) ($fee['term'] ?? 0);
			if ($term >= 1 && $term <= 3) {
				$groups[$key]['terms'][$term] = $fee;
			}
		}
		return self::sortGroups(array_values($groups));
	}

	/**
	 * One row per real class (including special/ANP), with fees attached when they exist.
	 * Fees saved on a class_id go only to that class; level+department fees (class_id empty)
	 * apply to every class of that level and department.
	 *
	 * @param list<array<string,mixed>> $classes
	 * @param list<array<string,mixed>> $fees
	 * @return list<array<string,mixed>>
	 */
	public static function groupsForAllClasses(array $classes, array $fees): array
	{
		$byClass = [];
		$byLevelDept = [];
		foreach ($fees as $fee) {
			$classId = (int) ($fee['class_id'] ?? 0);
			$levelId = (int) ($fee['level_id'] ?? 0);
			$deptId = (int) ($fee['department_id'] ?? 0);
			if ($classId > 0) {
				$byClass[$classId][] = $fee;
			} else {
				$byLevelDept[$levelId . '-' . $deptId][] = $fee;
			}
		}

		$groups = [];
		foreach ($classes as $class) {
			if (!is_array($class) || self::isHolidayClass($class)) {
				continue;
			}
			$classId = (int) ($class['class_id'] ?? $class['id'] ?? 0);
			$levelId = (int) ($class['level_id'] ?? $class['level'] ?? 0);
			$deptId = (int) ($class['department_id'] ?? $class['department'] ?? 0);
			if ($classId < 1) {
				continue;
			}
			$group = self::emptyGroupFromRow($class, $classId);
			$attach = $byClass[$classId] ?? [];
			if ($attach === []) {
				$attach = $byLevelDept[$levelId . '-' . $deptId] ?? [];
			}
			foreach ($attach as $fee) {
				$term = (int) ($fee['term'] ?? 0);
				if ($term >= 1 && $term <= 3) {
					$group['terms'][$term] = $fee;
				}
			}
			$groups[] = $group;
		}

		return self::sortGroups($groups);
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function emptyGroupFromRow(array $row, int $classId): array
	{
		return [
			'level_id' => (int) ($row['level_id'] ?? $row['level'] ?? 0),
			'department_id' => (int) ($row['department_id'] ?? $row['department'] ?? 0),
			'class_id' => $classId,
			'level_title' => (string) ($row['level_title'] ?? $row['level_name'] ?? ''),
			'dept_code' => (string) ($row['dept_code'] ?? $row['code'] ?? ''),
			'dept_title' => (string) ($row['dept_title'] ?? ''),
			'class_title' => (string) ($row['class_title'] ?? $row['title'] ?? ''),
			'faculty_code' => (string) ($row['faculty_code'] ?? ''),
			'faculty_title' => (string) ($row['faculty_title'] ?? ''),
			'display_label' => self::displayLabel($row),
			'academic_year_title' => (string) ($row['academic_year_title'] ?? ''),
			'terms' => [1 => null, 2 => null, 3 => null],
		];
	}

	/**
	 * @param list<array<string,mixed>> $groups
	 * @return list<array<string,mixed>>
	 */
	private static function sortGroups(array $groups): array
	{
		usort($groups, static function ($a, $b) {
			$labelCmp = strnatcasecmp((string) $a['display_label'], (string) $b['display_label']);
			if ($labelCmp !== 0) {
				return $labelCmp;
			}
			return strnatcasecmp((string) $a['dept_code'], (string) $b['dept_code']);
		});
		return array_values($groups);
	}

	/**
	 * Scope query to a fee target (class-specific or whole level).
	 */
	public function scopeFeeTarget($builder, int $schoolId, int $levelId, int $deptId, int $classId, int $term, int $academicYear)
	{
		$builder->where('school_id', $schoolId)
			->where('term', $term)
			->where('academic_year', $academicYear);

		if ($classId > 0) {
			return $builder->where('class_id', $classId);
		}

		return $builder->where('level', $levelId)
			->where('department', $deptId)
			->groupStart()
				->where('class_id IS NULL', null, false)
				->orWhere('class_id', 0)
			->groupEnd();
	}

	/**
	 * Delete a school fee and all linked student adjustments + payment records.
	 *
	 * @return array{ok:bool,error?:string,discounts:int,payments:int}
	 */
	public function deleteWithLinkedData(int $feeId, int $schoolId): array
	{
		$feeId = (int) $feeId;
		$schoolId = (int) $schoolId;
		if ($feeId < 1 || $schoolId < 1) {
			return ['ok' => false, 'error' => 'Invalid fee.', 'discounts' => 0, 'payments' => 0];
		}

		$row = $this->where('id', $feeId)->where('school_id', $schoolId)->first();
		if (!$row) {
			return ['ok' => false, 'error' => 'Fee not found.', 'discounts' => 0, 'payments' => 0];
		}

		$db = \Config\Database::connect();
		$db->transStart();

		$discountCount = (int) $db->table('school_fees_discount')->where('feesId', $feeId)->countAllResults();
		$db->table('school_fees_discount')->where('feesId', $feeId)->delete();

		// fees_type 0 = school fee payments; 2 = school-fee due/invoice rows linked to same fees_id
		$paymentCount = (int) $db->table('fees_records')
			->where('fees_id', $feeId)
			->whereIn('fees_type', [0, 2])
			->countAllResults();
		$db->table('fees_records')
			->where('fees_id', $feeId)
			->whereIn('fees_type', [0, 2])
			->delete();

		$this->delete($feeId);

		$db->transComplete();
		if ($db->transStatus() === false) {
			return ['ok' => false, 'error' => 'Delete failed. Please try again.', 'discounts' => 0, 'payments' => 0];
		}

		return [
			'ok' => true,
			'discounts' => (int) $discountCount,
			'payments' => (int) $paymentCount,
		];
	}
}
