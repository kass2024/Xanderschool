<?php

namespace App\Libraries;

/**
 * A student who did not sit a quiz, test, or exam scores 0.
 * Earlier periods are included so a blank sitting cannot lift the rank.
 */
class MissedAssessmentZero
{
	/**
	 * @return array{updated:int,inserted:int}
	 */
	public static function apply(int $classId = 0, int $courseId = 0): array
	{
		$db = \Config\Database::connect();
		$scope = 'mark_type IN (1, 2, 3)';
		$params = [];
		if ($classId > 0) {
			$scope .= ' AND class_id = ?';
			$params[] = $classId;
		}
		if ($courseId > 0) {
			$scope .= ' AND course_id = ?';
			$params[] = $courseId;
		}
		$db->query("UPDATE marks SET marks = 0 WHERE marks < 0 AND {$scope}", $params);
		$updated = (int) $db->affectedRows();

		$extra = '';
		$insertParams = [];
		if ($classId > 0) {
			$extra .= ' AND src.class_id = ?';
			$insertParams[] = $classId;
		}
		if ($courseId > 0) {
			$extra .= ' AND src.course_id = ?';
			$insertParams[] = $courseId;
		}
		$db->query(
			"INSERT INTO marks (student_id, term, examDate, course_id, class_id, mark_type, marks, outof, cat_type, period, created_by, created_at, updated_at)
			SELECT cr.student, src.term, src.examDate, src.course_id, src.class_id, src.mark_type, 0, src.outof, src.cat_type, src.period, src.created_by, NOW(), NOW()
			FROM class_records cr
			INNER JOIN students st ON st.id = cr.student AND st.status IN (1, 2)
			INNER JOIN (
				SELECT class_id, term, course_id, mark_type,
					TRIM(COALESCE(cat_type, '')) AS cat_type,
					period,
					MAX(outof) AS outof,
					MAX(examDate) AS examDate,
					MAX(created_by) AS created_by
				FROM marks
				WHERE mark_type IN (1, 2, 3)
				GROUP BY class_id, term, course_id, mark_type, TRIM(COALESCE(cat_type, '')), period
			) src ON src.class_id = cr.class
			INNER JOIN active_term at ON at.id = src.term AND at.academic_year = cr.year
			WHERE IFNULL(cr.status, 0) = 1
			{$extra}
			AND NOT EXISTS (
				SELECT 1 FROM marks m
				WHERE m.student_id = cr.student
				  AND m.class_id = src.class_id
				  AND m.term = src.term
				  AND m.course_id = src.course_id
				  AND m.mark_type = src.mark_type
				  AND m.period = src.period
				  AND TRIM(COALESCE(m.cat_type, '')) = src.cat_type
			)",
			$insertParams
		);

		return [
			'updated' => $updated,
			'inserted' => (int) $db->affectedRows(),
		];
	}
}
