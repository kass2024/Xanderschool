<?php

namespace App\Libraries;

use App\Models\DailyAttendanceModel;
use App\Models\DisciplineModel;
use App\Models\MarksModel;
use App\Models\PermissionModel;
use App\Models\StudentModel;
use App\Models\StudentVisitorModel;

/**
 * Class-list Delete removes one class_records enrollment only.
 * The student row is purged only when no other enrollments remain.
 */
class StudentClassRemoval
{
	/**
	 * @return array{ok:bool,error?:string,mode?:string,message?:string}
	 */
	public static function remove(int $schoolId, int $studentId, int $recordId = 0, int $classId = 0, int $yearId = 0): array
	{
		if ($schoolId < 1 || $studentId < 1) {
			return ['ok' => false, 'error' => 'Missing school or student.'];
		}

		$stMdl = new StudentModel();
		$student = $stMdl->select('id, school_id, fname, lname, updateVersion')
			->where('id', $studentId)
			->where('school_id', $schoolId)
			->get(1)
			->getRowArray();
		if ($student == null) {
			return ['ok' => false, 'error' => 'Student not found.'];
		}

		$db = \Config\Database::connect();
		$enrollments = $db->query(
			'SELECT cr.id, cr.class, cr.year, cr.status,
			        TRIM(CONCAT(IFNULL(l.title,""), " ", IFNULL(d.code,""), " ", IFNULL(c.title,""))) AS class_label
			 FROM class_records cr
			 LEFT JOIN classes c ON c.id = cr.class
			 LEFT JOIN departments d ON d.id = c.department
			 LEFT JOIN levels l ON l.id = c.level
			 WHERE cr.student = ?
			 ORDER BY cr.id ASC',
			[$studentId]
		)->getResultArray();

		$target = self::resolveTarget($enrollments, $recordId, $classId, $yearId);
		if (isset($target['error'])) {
			return ['ok' => false, 'error' => $target['error']];
		}

		$targetId = (int) $target['id'];
		$remaining = 0;
		foreach ($enrollments as $row) {
			if ((int) $row['id'] !== $targetId) {
				$remaining++;
			}
		}

		$db->transStart();
		$db->table('class_records')
			->where('id', $targetId)
			->where('student', $studentId)
			->delete();

		$name = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
		if ($name === '') {
			$name = 'Student';
		}
		$classLabel = trim((string) ($target['class_label'] ?? 'this class'));
		if ($classLabel === '') {
			$classLabel = 'this class';
		}

		if ($remaining > 0) {
			$stMdl->save([
				'id' => $studentId,
				'updateVersion' => ((int) ($student['updateVersion'] ?? 0)) + 1,
			]);
			$db->transComplete();
			if ($db->transStatus() === false) {
				return ['ok' => false, 'error' => 'Failed to remove student from this class.'];
			}
			return [
				'ok' => true,
				'mode' => 'unenrolled',
				'message' => $name . ' was removed from ' . $classLabel . ' only and remains in other class(es).',
			];
		}

		self::purgeOwnedRows($db, $schoolId, $studentId);
		$db->transComplete();
		if ($db->transStatus() === false) {
			return ['ok' => false, 'error' => 'Failed to delete student.'];
		}

		return [
			'ok' => true,
			'mode' => 'deleted',
			'message' => $name . ' was deleted from ' . $classLabel . '.',
		];
	}

	/**
	 * @param list<array<string,mixed>> $enrollments
	 * @return array<string,mixed>
	 */
	private static function resolveTarget(array $enrollments, int $recordId, int $classId, int $yearId): array
	{
		if ($recordId > 0) {
			foreach ($enrollments as $row) {
				if ((int) $row['id'] === $recordId) {
					return $row;
				}
			}
			return ['error' => 'That class enrollment was not found for this student.'];
		}

		if ($classId > 0) {
			$match = null;
			foreach ($enrollments as $row) {
				if ((int) $row['class'] !== $classId) {
					continue;
				}
				if ($yearId > 0 && (int) $row['year'] !== $yearId) {
					continue;
				}
				$match = $row;
				break;
			}
			if ($match === null) {
				return ['error' => 'This student is not in the selected class.'];
			}
			return $match;
		}

		if (count($enrollments) === 1) {
			return $enrollments[0];
		}

		if (count($enrollments) > 1) {
			$labels = [];
			foreach ($enrollments as $row) {
				$lab = trim((string) ($row['class_label'] ?? ''));
				if ($lab !== '' && !in_array($lab, $labels, true)) {
					$labels[] = $lab;
				}
			}
			$shown = $labels !== [] ? implode(', ', $labels) : 'multiple classes';
			return [
				'error' => 'This student is enrolled in more than one class (' . $shown . '). Remove them from one class at a time so other classes are not deleted.',
			];
		}

		return ['error' => 'This student has no class enrollment to remove.'];
	}

	private static function purgeOwnedRows($db, int $schoolId, int $studentId): void
	{
		$visitorMdl = new StudentVisitorModel();
		$visitorMdl->ensureSchema();
		$visitorMdl->purgeForStudent($schoolId, $studentId);

		$db->table('class_records')->where('student', $studentId)->delete();
		(new MarksModel())->where('student_id', $studentId)->delete();
		(new DisciplineModel())->where('student_id', $studentId)->delete();
		(new PermissionModel())->where('student_id', $studentId)->delete();
		(new DailyAttendanceModel())->where('student_id', $studentId)->delete();
		(new StudentModel())->delete($studentId);
	}
}
