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
	protected $afterUpdate = ['enforceExclusiveClass'];

	/**
	 * Keep one regular (non-holiday) class per student per year, and at most
	 * one holiday coaching class. Extra leftover rows are deleted.
	 *
	 * @param int        $studentId
	 * @param int|string $year
	 * @param int        $keepId
	 * @param int        $keepClass
	 * @return int
	 */
	public function dropOtherClassesForStudentYear($studentId, $year, $keepId, $keepClass = 0)
	{
		$studentId = (int) $studentId;
		$keepId = (int) $keepId;
		$keepClass = (int) $keepClass;
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

	/**
	 * @param int $classId
	 * @return bool
	 */
	public function classIsHoliday($classId)
	{
		$classId = (int) $classId;
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

	/**
	 * @param array $data
	 * @return array
	 */
	protected function enforceExclusiveClass(array $data)
	{
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

	/**
	 * @param string $classAlias
	 * @param string $levelAlias
	 * @param string $deptAlias
	 * @return string
	 */
	private function holidayMatchSql($classAlias, $levelAlias, $deptAlias)
	{
		return "LOWER(CONCAT(IFNULL({$classAlias}.title,''),' ',IFNULL({$levelAlias}.title,''),' ',IFNULL({$deptAlias}.title,''),' ',IFNULL({$deptAlias}.code,''))) LIKE '%holiday%'";
	}
}
