<?php

namespace App\Services\Budget;

use App\Models\SchoolFeesModel;

/**
 * Project School Fees budget income from fees management (boarding/day × students).
 */
class SchoolFeesBudgetProjectionService
{
	/**
	 * @return array{
	 *   success:bool,
	 *   term_1:float,
	 *   term_2:float,
	 *   term_3:float,
	 *   annual:float,
	 *   boarding_students:int,
	 *   day_students:int,
	 *   total_students:int,
	 *   academic_year_id:int,
	 *   academic_year_title:string,
	 *   classes_used:int,
	 *   fees_rows:int,
	 *   breakdown:list<array<string,mixed>>,
	 *   notes:string,
	 *   error?:string
	 * }
	 */
	public function projectForSchool(int $schoolId, $academicYearHint = null): array
	{
		$db = \Config\Database::connect();
		$schoolId = (int) $schoolId;
		if ($schoolId < 1) {
			return $this->emptyResult('Invalid school.');
		}

		$year = $this->resolveAcademicYear($schoolId, $academicYearHint);
		if (!$year) {
			return $this->emptyResult('Academic year not found for fee projection.');
		}
		$yearId = (int) $year['id'];
		$yearTitle = (string) ($year['title'] ?? '');

		$feesModel = new SchoolFeesModel();
		$feesModel->ensureSchema();
		$fees = $feesModel->listForSchool($schoolId, $yearId);
		if (!$fees) {
			return array_merge($this->emptyResult('No school fees configured for this academic year.'), [
				'academic_year_id' => $yearId,
				'academic_year_title' => $yearTitle,
			]);
		}

		// class_id => [boarding => n, day => n, level_id, dept_id, label]
		$classStats = $this->studentCountsByClass($schoolId, $yearId);
		if (!$classStats) {
			return array_merge($this->emptyResult('No active students found for fee projection.'), [
				'academic_year_id' => $yearId,
				'academic_year_title' => $yearTitle,
				'fees_rows' => count($fees),
			]);
		}

		// Index fees: class-specific and level+dept fallbacks per term
		$byClassTerm = []; // classId-term => fee
		$byLevelDeptTerm = []; // level-dept-term => fee
		foreach ($fees as $fee) {
			$term = (int) ($fee['term'] ?? 0);
			if ($term < 1 || $term > 3) {
				continue;
			}
			$classId = (int) ($fee['class_id'] ?? 0);
			$levelId = (int) ($fee['level_id'] ?? 0);
			$deptId = (int) ($fee['department_id'] ?? 0);
			if ($classId > 0) {
				$byClassTerm[$classId . '-' . $term] = $fee;
			} else {
				$byLevelDeptTerm[$levelId . '-' . $deptId . '-' . $term] = $fee;
			}
		}

		$terms = [1 => 0.0, 2 => 0.0, 3 => 0.0];
		$breakdown = [];
		$boardingTotal = 0;
		$dayTotal = 0;

		foreach ($classStats as $classId => $stat) {
			$boardingN = (int) ($stat['boarding'] ?? 0);
			$dayN = (int) ($stat['day'] ?? 0);
			$boardingTotal += $boardingN;
			$dayTotal += $dayN;
			$levelId = (int) ($stat['level_id'] ?? 0);
			$deptId = (int) ($stat['dept_id'] ?? 0);
			$label = (string) ($stat['label'] ?? ('Class #' . $classId));

			for ($term = 1; $term <= 3; $term++) {
				$fee = $byClassTerm[$classId . '-' . $term]
					?? $byLevelDeptTerm[$levelId . '-' . $deptId . '-' . $term]
					?? null;
				if (!$fee) {
					continue;
				}
				$modes = SchoolFeesModel::modeAmounts($fee);
				$boardAmt = $modes['boarding'] !== null ? (float) $modes['boarding'] : (float) $modes['legacy'];
				$dayAmt = $modes['day'] !== null ? (float) $modes['day'] : (float) $modes['legacy'];
				$line = ($boardingN * $boardAmt) + ($dayN * $dayAmt);
				$terms[$term] += $line;
				if ($line > 0) {
					$breakdown[] = [
						'class' => $label,
						'term' => $term,
						'boarding_students' => $boardingN,
						'day_students' => $dayN,
						'boarding_rate' => $boardAmt,
						'day_rate' => $dayAmt,
						'total' => round($line, 2),
					];
				}
			}
		}

		$annual = $terms[1] + $terms[2] + $terms[3];
		$notes = sprintf(
			'Auto from fees management · AY %s · %d boarding + %d day = %d students · %d classes',
			$yearTitle,
			$boardingTotal,
			$dayTotal,
			$boardingTotal + $dayTotal,
			count($classStats)
		);

		return [
			'success' => true,
			'term_1' => round($terms[1], 2),
			'term_2' => round($terms[2], 2),
			'term_3' => round($terms[3], 2),
			'annual' => round($annual, 2),
			'boarding_students' => $boardingTotal,
			'day_students' => $dayTotal,
			'total_students' => $boardingTotal + $dayTotal,
			'academic_year_id' => $yearId,
			'academic_year_title' => $yearTitle,
			'classes_used' => count($classStats),
			'fees_rows' => count($fees),
			'breakdown' => $breakdown,
			'notes' => $notes,
		];
	}

	/**
	 * @param mixed $hint year id or title string
	 * @return array{id:int,title:string}|null
	 */
	protected function resolveAcademicYear(int $schoolId, $hint): ?array
	{
		$db = \Config\Database::connect();
		if (is_numeric($hint) && (int) $hint > 0) {
			$row = $db->table('academic_year')->select('id,title')
				->where('school_id', $schoolId)->where('id', (int) $hint)->get(1)->getRowArray();
			if ($row) {
				return $row;
			}
		}
		$hintStr = trim((string) $hint);
		if ($hintStr !== '') {
			// Normalize 2026-27 / 2026-2027
			$row = $db->table('academic_year')->select('id,title')
				->where('school_id', $schoolId)
				->like('title', substr($hintStr, 0, 4))
				->orderBy('id', 'DESC')->get(1)->getRowArray();
			if ($row) {
				return $row;
			}
			$row = $db->table('academic_year')->select('id,title')
				->where('school_id', $schoolId)->where('title', $hintStr)->get(1)->getRowArray();
			if ($row) {
				return $row;
			}
		}
		$sessionYear = (int) ($_SESSION['soma_academics_year'] ?? 0);
		if ($sessionYear > 0) {
			$row = $db->table('academic_year')->select('id,title')
				->where('school_id', $schoolId)->where('id', $sessionYear)->get(1)->getRowArray();
			if ($row) {
				return $row;
			}
		}
		return $db->table('academic_year')->select('id,title')
			->where('school_id', $schoolId)->orderBy('id', 'DESC')->get(1)->getRowArray();
	}

	/**
	 * @return array<int,array{boarding:int,day:int,level_id:int,dept_id:int,label:string}>
	 */
	protected function studentCountsByClass(int $schoolId, int $yearId): array
	{
		$db = \Config\Database::connect();
		$rows = $db->query(
			"SELECT c.id AS class_id, c.level AS level_id, c.department AS dept_id,
				l.title AS level_title, c.title AS class_title, d.code AS dept_code,
				students.studying_mode, COUNT(DISTINCT students.id) AS cnt
			FROM students
			INNER JOIN class_records cr ON cr.student = students.id AND cr.status = '1' AND cr.year = ?
			INNER JOIN classes c ON c.id = cr.class AND c.school_id = ?
			INNER JOIN levels l ON l.id = c.level
			INNER JOIN departments d ON d.id = c.department
			WHERE students.school_id = ? AND students.status = '1'
			GROUP BY c.id, students.studying_mode, c.level, c.department, l.title, c.title, d.code",
			[$yearId, $schoolId, $schoolId]
		)->getResultArray();

		$out = [];
		foreach ($rows as $r) {
			$cid = (int) $r['class_id'];
			if (!isset($out[$cid])) {
				$classTitle = trim((string) ($r['class_title'] ?? ''));
				$label = trim(($r['level_title'] ?? '') . ' ' . ($classTitle !== '' && $classTitle !== '-----' ? $classTitle : '') . ' ' . ($r['dept_code'] ?? ''));
				$out[$cid] = [
					'boarding' => 0,
					'day' => 0,
					'level_id' => (int) $r['level_id'],
					'dept_id' => (int) $r['dept_id'],
					'label' => trim($label) ?: ('Class #' . $cid),
				];
			}
			$mode = (int) ($r['studying_mode'] ?? 1);
			$cnt = (int) ($r['cnt'] ?? 0);
			if ($mode === 0) {
				$out[$cid]['boarding'] += $cnt;
			} else {
				$out[$cid]['day'] += $cnt;
			}
		}
		return $out;
	}

	protected function emptyResult(string $error = ''): array
	{
		return [
			'success' => $error === '',
			'term_1' => 0.0,
			'term_2' => 0.0,
			'term_3' => 0.0,
			'annual' => 0.0,
			'boarding_students' => 0,
			'day_students' => 0,
			'total_students' => 0,
			'academic_year_id' => 0,
			'academic_year_title' => '',
			'classes_used' => 0,
			'fees_rows' => 0,
			'breakdown' => [],
			'notes' => '',
			'error' => $error !== '' ? $error : null,
		];
	}
}
