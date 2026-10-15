<?php
namespace App\Models;

use CodeIgniter\Model;

class FacultyModel extends Model
{
	protected $table = "faculty";
	protected $allowedFields = ["title", "abbrev", "type", "status"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';

	public const TYPE_TVET = 1;
	public const TYPE_REB = 2;
	public const TYPE_SPECIAL = 3;
	public const SPECIAL_TITLE = 'Nursing ANP';
	public const SPECIAL_CODE = 'ANP';

	/**
	 * Ensure the Special educational path exists with faculty + department both named Nursing ANP.
	 *
	 * @return array{faculty: array<string,mixed>, department: array<string,mixed>}
	 */
	public function ensureSpecialNursingAnp(): array
	{
		$db = $this->db;
		$fac = $db->table($this->table)
			->where('type', self::TYPE_SPECIAL)
			->get(1)->getRowArray();
		if (!$fac) {
			$fac = $db->table($this->table)
				->where('abbrev', self::SPECIAL_CODE)
				->where('type', self::TYPE_SPECIAL)
				->get(1)->getRowArray();
		}
		if (!$fac) {
			$fac = $db->table($this->table)
				->groupStart()
					->where('title', self::SPECIAL_TITLE)
					->orWhere('title', 'Nursing ANP')
				->groupEnd()
				->where('type', self::TYPE_SPECIAL)
				->get(1)->getRowArray();
		}

		if (!$fac) {
			$db->table($this->table)->insert([
				'title' => self::SPECIAL_TITLE,
				'abbrev' => self::SPECIAL_CODE,
				'type' => self::TYPE_SPECIAL,
				'status' => 1,
			]);
			$facId = (int) $db->insertID();
			$fac = $db->table($this->table)->where('id', $facId)->get(1)->getRowArray();
		} else {
			$facId = (int) $fac['id'];
			$needs = [];
			if (strcasecmp((string) ($fac['title'] ?? ''), self::SPECIAL_TITLE) !== 0) {
				$needs['title'] = self::SPECIAL_TITLE;
			}
			if (strcasecmp((string) ($fac['abbrev'] ?? ''), self::SPECIAL_CODE) !== 0) {
				$needs['abbrev'] = self::SPECIAL_CODE;
			}
			if ((int) ($fac['type'] ?? 0) !== self::TYPE_SPECIAL) {
				$needs['type'] = self::TYPE_SPECIAL;
			}
			if ((int) ($fac['status'] ?? 0) !== 1) {
				$needs['status'] = 1;
			}
			if ($needs) {
				$db->table($this->table)->where('id', $facId)->update($needs);
				$fac = $db->table($this->table)->where('id', $facId)->get(1)->getRowArray();
			}
		}

		$deptTable = $db->table('departments');
		$dept = $deptTable->where('faculty_id', $facId)
			->groupStart()
				->where('title', self::SPECIAL_TITLE)
				->orWhere('title', 'Nursing')
				->orWhere('code', self::SPECIAL_CODE)
			->groupEnd()
			->get(1)->getRowArray();

		$now = date('Y-m-d H:i:s');
		if (!$dept) {
			$deptTable->insert([
				'title' => self::SPECIAL_TITLE,
				'code' => self::SPECIAL_CODE,
				'faculty_id' => $facId,
				'created_by' => 0,
				'updated_by' => 0,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$deptId = (int) $db->insertID();
			$dept = $db->table('departments')->where('id', $deptId)->get(1)->getRowArray();
		} else {
			$deptId = (int) $dept['id'];
			$deptNeeds = [];
			if (strcasecmp((string) ($dept['title'] ?? ''), self::SPECIAL_TITLE) !== 0) {
				$deptNeeds['title'] = self::SPECIAL_TITLE;
			}
			if (strcasecmp((string) ($dept['code'] ?? ''), self::SPECIAL_CODE) !== 0) {
				$deptNeeds['code'] = self::SPECIAL_CODE;
			}
			if ($deptNeeds) {
				$deptNeeds['updated_at'] = $now;
				$db->table('departments')->where('id', $deptId)->update($deptNeeds);
				$dept = $db->table('departments')->where('id', $deptId)->get(1)->getRowArray();
			}
		}

		$rename = ['Year 1' => 'S4', 'Year 2' => 'S5', 'Year 3' => 'S6'];
		foreach ($rename as $from => $to) {
			$fromRow = $db->table('levels')
				->where('faculty_id', $facId)
				->where('title', $from)
				->get(1)->getRowArray();
			if (!$fromRow) {
				continue;
			}
			$toRow = $db->table('levels')
				->where('faculty_id', $facId)
				->where('title', $to)
				->get(1)->getRowArray();
			if (!$toRow) {
				$db->table('levels')->where('id', (int) $fromRow['id'])->update([
					'title' => $to,
					'type' => self::TYPE_SPECIAL,
				]);
				continue;
			}
			$db->table('classes')->where('level', (int) $fromRow['id'])->update(['level' => (int) $toRow['id']]);
			$db->table('levels')->where('id', (int) $fromRow['id'])->delete();
		}

		foreach (['S4', 'S5', 'S6'] as $title) {
			$exists = $db->table('levels')
				->where('faculty_id', $facId)
				->where('title', $title)
				->countAllResults();
			if ($exists > 0) {
				continue;
			}
			$db->table('levels')->insert([
				'title' => $title,
				'type' => self::TYPE_SPECIAL,
				'faculty_id' => $facId,
				'status' => 1,
			]);
		}

		return [
			'faculty' => $fac ?: [],
			'department' => $dept ?: [],
		];
	}

	public function isWisdomKayonza(int $schoolId): bool
	{
		if ($schoolId < 1) {
			return false;
		}
		$row = $this->db->table('schools')->select('name, acronym')->where('id', $schoolId)->get(1)->getRowArray();
		if (!$row) {
			return false;
		}
		$name = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) ($row['name'] ?? ''))));
		$acr = strtoupper(trim((string) ($row['acronym'] ?? '')));
		$isWisdom = strpos($name, 'WISDOM') !== false || in_array($acr, ['WSY', 'KAY'], true);
		return $isWisdom && (strpos($name, 'KAYONZA') !== false || $acr === 'WSY');
	}

	public function departmentIsAnp(int $departmentId): bool
	{
		if ($departmentId < 1) {
			return false;
		}
		$row = $this->db->table('departments d')
			->select('d.code, d.title, f.type')
			->join('faculty f', 'f.id = d.faculty_id', 'left')
			->where('d.id', $departmentId)
			->get(1)
			->getRowArray();
		if (!$row) {
			return false;
		}
		$code = strtoupper(trim((string) ($row['code'] ?? '')));
		$title = strtoupper(trim((string) ($row['title'] ?? '')));
		return $code === self::SPECIAL_CODE
			|| strpos($title, 'ANP') !== false
			|| (int) ($row['type'] ?? 0) === self::TYPE_SPECIAL;
	}

	/** Wisdom School Kayonza has no Nursing ANP classes. */
	public function removeKayonzaAnpClasses(int $schoolId): int
	{
		if (!$this->isWisdomKayonza($schoolId)) {
			return 0;
		}
		$db = $this->db;
		$rows = $db->query(
			"SELECT c.id
			FROM classes c
			LEFT JOIN departments d ON d.id = c.department
			LEFT JOIN faculty f ON f.id = d.faculty_id
			LEFT JOIN levels l ON l.id = c.level
			WHERE c.school_id = ?
			AND (
				UPPER(TRIM(IFNULL(d.code,''))) = 'ANP'
				OR UPPER(IFNULL(d.title,'')) LIKE '%ANP%'
				OR IFNULL(f.type, 0) = ?
				OR UPPER(IFNULL(c.title,'')) LIKE '%ANP%'
				OR UPPER(IFNULL(l.title,'')) LIKE '%ANP%'
			)",
			[$schoolId, self::TYPE_SPECIAL]
		)->getResultArray();
		$ids = [];
		foreach ($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		if ($ids === []) {
			return 0;
		}
		$db->transStart();
		if ($db->tableExists('class_records')) {
			$db->table('class_records')->whereIn('class', $ids)->delete();
		}
		if ($db->tableExists('course_records')) {
			$db->table('course_records')->whereIn('class', $ids)->delete();
		}
		if ($db->tableExists('school_fees') && $db->fieldExists('class_id', 'school_fees')) {
			$db->table('school_fees')->where('school_id', $schoolId)->whereIn('class_id', $ids)->delete();
		}
		$db->table('classes')->where('school_id', $schoolId)->whereIn('id', $ids)->delete();
		$db->transComplete();
		return $db->transStatus() ? count($ids) : 0;
	}

	/** Create S4–S6 Nursing (ANP) classes for a school if they are missing. */
	public function ensureSpecialNursingAnpClasses(int $schoolId): int
	{
		if ($schoolId < 1) {
			return 0;
		}
		if ($this->isWisdomKayonza($schoolId)) {
			$this->removeKayonzaAnpClasses($schoolId);
			return 0;
		}
		$ensured = $this->ensureSpecialNursingAnp();
		$deptId = (int) ($ensured['department']['id'] ?? 0);
		$facId = (int) ($ensured['faculty']['id'] ?? 0);
		if ($deptId < 1 || $facId < 1) {
			return 0;
		}
		$db = $this->db;
		$mentor = $db->table('staffs')->select('id')->where('school_id', $schoolId)->orderBy('id', 'ASC')->get(1)->getRowArray();
		$mentorId = (int) ($mentor['id'] ?? 0);
		$levels = $db->table('levels')->select('id')
			->where('faculty_id', $facId)
			->where('type', self::TYPE_SPECIAL)
			->whereIn('title', ['S4', 'S5', 'S6'])
			->orderBy('title', 'ASC')
			->get()->getResultArray();
		$now = date('Y-m-d H:i:s');
		$created = 0;
		foreach ($levels as $lv) {
			$levelId = (int) ($lv['id'] ?? 0);
			if ($levelId < 1) {
				continue;
			}
			$exists = $db->table('classes')
				->where('school_id', $schoolId)
				->where('level', $levelId)
				->where('department', $deptId)
				->countAllResults();
			if ($exists > 0) {
				continue;
			}
			$row = [
				'school_id' => $schoolId,
				'level' => $levelId,
				'department' => $deptId,
				'title' => '',
				'mentor' => $mentorId,
				'created_by' => 0,
				'updated_by' => 0,
				'created_at' => $now,
				'updated_at' => $now,
			];
			$db->table('classes')->insert($row);
			$created++;
		}
		return $created;
	}
}
