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
			ac.title AS academic_year_title,
			school_fees.academic_year AS academic_year_id,
			TRIM(CONCAT(COALESCE(stf.fname,''),' ',COALESCE(stf.lname,''))) AS created_by_name
		")
			->join("levels l", "l.id = school_fees.level", "LEFT")
			->join("departments d", "d.id = school_fees.department", "LEFT")
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

	public static function displayLabel(array $row): string
	{
		$level = trim((string) ($row['level_title'] ?? ''));
		$classTitle = trim((string) ($row['class_title'] ?? ''));
		if ($classTitle === '' || $classTitle === '-----') {
			return $level;
		}
		return trim($level . ' ' . $classTitle);
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
				$groups[$key] = [
					'level_id' => (int) ($fee['level_id'] ?? 0),
					'department_id' => (int) ($fee['department_id'] ?? 0),
					'class_id' => $classId,
					'level_title' => (string) ($fee['level_title'] ?? ''),
					'dept_code' => (string) ($fee['dept_code'] ?? ''),
					'dept_title' => (string) ($fee['dept_title'] ?? ''),
					'class_title' => (string) ($fee['class_title'] ?? ''),
					'display_label' => self::displayLabel($fee),
					'academic_year_title' => (string) ($fee['academic_year_title'] ?? ''),
					'terms' => [1 => null, 2 => null, 3 => null],
				];
			}
			$term = (int) ($fee['term'] ?? 0);
			if ($term >= 1 && $term <= 3) {
				$groups[$key]['terms'][$term] = $fee;
			}
		}
		usort($groups, static function ($a, $b) {
			$labelCmp = strcmp($a['display_label'], $b['display_label']);
			if ($labelCmp !== 0) {
				return $labelCmp;
			}
			return strcmp($a['dept_code'], $b['dept_code']);
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
