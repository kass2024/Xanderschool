<?php
namespace App\Models;

use CodeIgniter\Model;

class ClassRecordModel extends Model
{
	protected $table = 'class_records';
	protected $allowedFields = ['student', 'year', 'class', 'status'];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $afterInsert = ['enforceExclusiveClass'];

	/** @var bool */
	private $skipExclusive = false;

	public function insert($data = null, bool $returnID = true)
	{
		if (is_array($data) && empty($data[$this->primaryKey])) {
			$student = (int) ($data['student'] ?? 0);
			$class = (int) ($data['class'] ?? 0);
			$year = $data['year'] ?? null;
			if ($student > 0 && $class > 0 && $year !== null && $year !== '') {
				$existing = $this->db->query(
					'SELECT id FROM class_records WHERE student = ? AND year = ? AND class = ? ORDER BY id DESC LIMIT 1',
					[$student, $year, $class]
				)->getRowArray();
				if ($existing) {
					$id = (int) $existing['id'];
					$update = $data;
					unset($update[$this->primaryKey]);
					$this->skipExclusive = true;
					try {
						parent::update($id, $update);
					} finally {
						$this->skipExclusive = false;
					}
					$this->dropOtherClassesForStudentYear($student, $year, $id, $class);
					$this->insertID = $id;
					return $returnID ? $id : true;
				}
			}
		}

		return parent::insert($data, $returnID);
	}

	/**
	 * Keep one regular (non-holiday) class per student per year, and at most
	 * one holiday coaching class. Extra leftover rows are deleted.
	 */
	public function dropOtherClassesForStudentYear($studentId, $year, int $keepId, int $keepClass = 0): int
	{
		$studentId = (int) $studentId;
		if ($studentId < 1 || $keepId < 1) {
			return 0;
		}
		if ($keepClass < 1) {
			$row = $this->db->query(
				'SELECT class FROM class_records WHERE id = ?',
				[$keepId]
			)->getRowArray();
			$keepClass = (int) ($row['class'] ?? 0);
		}
		if ($keepClass < 1) {
			return 0;
		}

		$keepHoliday = $this->classIsHoliday($keepClass);
		$holidaySql = $this->holidayMatchSql('c', 'l', 'd');
		$kindSql = $keepHoliday ? $holidaySql : ('NOT (' . $holidaySql . ')');
		$this->db->query(
			"DELETE cr FROM class_records cr
			 INNER JOIN classes c ON c.id = cr.class
			 LEFT JOIN levels l ON l.id = c.level
			 LEFT JOIN departments d ON d.id = c.department
			 WHERE cr.student = ?
			   AND cr.year = ?
			   AND cr.id <> ?
			   AND {$kindSql}",
			[$studentId, $year, $keepId]
		);

		return (int) $this->db->affectedRows();
	}

	public function classIsHoliday(int $classId): bool
	{
		if ($classId < 1) {
			return false;
		}
		$row = $this->db->query(
			'SELECT c.title, l.title AS level_title, d.title AS dept_title, d.code AS dept_code
			 FROM classes c
			 LEFT JOIN levels l ON l.id = c.level
			 LEFT JOIN departments d ON d.id = c.department
			 WHERE c.id = ?
			 LIMIT 1',
			[$classId]
		)->getRowArray();
		if (!$row) {
			return false;
		}
		$hay = strtolower(trim(implode(' ', [
			$row['title'] ?? '',
			$row['level_title'] ?? '',
			$row['dept_title'] ?? '',
			$row['dept_code'] ?? '',
		])));

		return strpos($hay, 'holiday') !== false;
	}

	protected function enforceExclusiveClass(array $data)
	{
		if ($this->skipExclusive) {
			return $data;
		}
		$id = 0;
		if (isset($data['id'])) {
			$id = is_array($data['id']) ? (int) ($data['id'][0] ?? 0) : (int) $data['id'];
		}
		$row = is_array($data['data'] ?? null) ? $data['data'] : [];
		$student = (int) ($row['student'] ?? 0);
		$year = $row['year'] ?? null;
		$class = (int) ($row['class'] ?? 0);
		if ($id > 0 && ($student < 1 || $class < 1 || $year === null || $year === '')) {
			$found = $this->db->query(
				'SELECT student, year, class FROM class_records WHERE id = ? LIMIT 1',
				[$id]
			)->getRowArray();
			if ($found) {
				$student = $student > 0 ? $student : (int) $found['student'];
				$class = $class > 0 ? $class : (int) $found['class'];
				if ($year === null || $year === '') {
					$year = $found['year'];
				}
			}
		}
		if ($student < 1 || $class < 1 || $year === null || $year === '' || $id < 1) {
			return $data;
		}
		$this->dropOtherClassesForStudentYear($student, $year, $id, $class);

		return $data;
	}

	private function holidayMatchSql(string $classAlias, string $levelAlias, string $deptAlias): string
	{
		return "LOWER(CONCAT(IFNULL({$classAlias}.title,''),' ',IFNULL({$levelAlias}.title,''),' ',IFNULL({$deptAlias}.title,''),' ',IFNULL({$deptAlias}.code,''))) LIKE '%holiday%'";
	}
}
