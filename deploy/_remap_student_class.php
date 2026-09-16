<?php
if (!function_exists('remapOnMove')) {
function remapOnMove(
	\CodeIgniter\Database\BaseConnection $db,
	int $studentId,
	int $fromClassId,
	int $toClassId,
	string $yearKey,
	int $fromLevel,
	int $fromDept,
	int $toLevel,
	int $toDept
): void {
	if ($db->tableExists('marks')) {
		$db->query(
			'UPDATE marks SET class_id = ? WHERE student_id = ? AND class_id = ?',
			[$toClassId, $studentId, $fromClassId]
		);
		if ($db->tableExists('course_records') && $db->tableExists('courses')) {
			$db->query(
				'UPDATE marks m
				 INNER JOIN courses c_old ON c_old.id = m.course_id
				 INNER JOIN course_records cr_new ON cr_new.class = ? AND cr_new.year = ?
				 INNER JOIN courses c_new ON c_new.id = cr_new.course
				 SET m.course_id = c_new.id
				 WHERE m.student_id = ? AND m.class_id = ?
				   AND c_old.id <> c_new.id
				   AND (
					 (c_old.code IS NOT NULL AND c_old.code <> \'\' AND LOWER(TRIM(c_old.code)) = LOWER(TRIM(c_new.code)))
					 OR LOWER(TRIM(c_old.title)) = LOWER(TRIM(c_new.title))
				   )',
				[$toClassId, $yearKey, $studentId, $toClassId]
			);
		}
	}
	if ($db->tableExists('student_material_checks') && $db->fieldExists('class_id', 'student_material_checks')) {
		$sql = 'UPDATE student_material_checks SET class_id = ? WHERE student_id = ? AND class_id = ?';
		$bind = [$toClassId, $studentId, $fromClassId];
		if ($db->fieldExists('academic_year', 'student_material_checks')) {
			$sql .= ' AND academic_year = ?';
			$bind[] = $yearKey;
		}
		$db->query($sql, $bind);
	}
	if ($db->tableExists('course_attendance_records') && $db->fieldExists('class_id', 'course_attendance_records')) {
		$db->query(
			'UPDATE course_attendance_records SET class_id = ? WHERE student_id = ? AND class_id = ?',
			[$toClassId, $studentId, $fromClassId]
		);
	}
	if ($db->tableExists('deliberation_records')) {
		if ($db->fieldExists('oldClass', 'deliberation_records') && $db->fieldExists('studentId', 'deliberation_records')) {
			$db->query(
				'UPDATE deliberation_records SET oldClass = ? WHERE studentId = ? AND oldClass = ?',
				[$toClassId, $studentId, $fromClassId]
			);
		}
		if ($db->fieldExists('newClass', 'deliberation_records') && $db->fieldExists('studentId', 'deliberation_records')) {
			$db->query(
				'UPDATE deliberation_records SET newClass = ? WHERE studentId = ? AND newClass = ?',
				[$toClassId, $studentId, $fromClassId]
			);
		}
	}
	if ($db->tableExists('fees_records') && $db->tableExists('extra_fees')) {
		$paid = $db->query(
			'SELECT DISTINCT fr.fees_id
			 FROM fees_records fr
			 INNER JOIN extra_fees ef ON ef.id = fr.fees_id
			 WHERE fr.student_id = ? AND fr.fees_type = 1
			   AND ef.school_id = ? AND ef.type = 0 AND ef.type_id = ? AND ef.academic_year = ?',
			[$studentId, SCHOOL_ID, $fromClassId, $yearKey]
		)->getResultArray();
		foreach ($paid as $row) {
			$oldId = (int) ($row['fees_id'] ?? 0);
			if ($oldId < 1) {
				continue;
			}
			$oldFee = $db->query('SELECT * FROM extra_fees WHERE id = ? LIMIT 1', [$oldId])->getRowArray();
			if (!$oldFee) {
				continue;
			}
			$title = trim((string) ($oldFee['title'] ?? ''));
			$term = (int) ($oldFee['term'] ?? 0);
			$match = $db->query(
				'SELECT id FROM extra_fees
				 WHERE school_id = ? AND type = 0 AND type_id = ? AND academic_year = ?
				   AND LOWER(TRIM(title)) = LOWER(?) AND term = ?
				 LIMIT 1',
				[SCHOOL_ID, $toClassId, $yearKey, $title, $term]
			)->getRowArray();
			$newId = (int) ($match['id'] ?? 0);
			if ($newId < 1) {
				$loose = $db->query(
					'SELECT id FROM extra_fees
					 WHERE school_id = ? AND type = 0 AND type_id = ? AND academic_year = ?
					   AND LOWER(TRIM(title)) = LOWER(?)
					 ORDER BY ABS(term - ?) ASC, id ASC LIMIT 1',
					[SCHOOL_ID, $toClassId, $yearKey, $title, $term]
				)->getRowArray();
				$newId = (int) ($loose['id'] ?? 0);
			}
			if ($newId < 1) {
				$insert = [
					'school_id' => SCHOOL_ID,
					'title' => $title !== '' ? $title : ('Fee ' . $oldId),
					'academic_year' => $yearKey,
					'type_id' => $toClassId,
					'type' => 0,
					'term' => $term > 0 ? $term : 1,
					'amount' => $oldFee['amount'] ?? 0,
					'created_by' => (int) ($oldFee['created_by'] ?? 0),
				];
				if ($db->fieldExists('amount_boarding', 'extra_fees')) {
					$insert['amount_boarding'] = $oldFee['amount_boarding'] ?? null;
				}
				if ($db->fieldExists('amount_day', 'extra_fees')) {
					$insert['amount_day'] = $oldFee['amount_day'] ?? null;
				}
				$db->table('extra_fees')->insert($insert);
				$newId = (int) $db->insertID();
			}
			if ($newId > 0 && $newId !== $oldId) {
				$db->query(
					'UPDATE fees_records SET fees_id = ? WHERE student_id = ? AND fees_type = 1 AND fees_id = ?',
					[$newId, $studentId, $oldId]
				);
			}
		}
	}
	if (
		$db->tableExists('fees_records')
		&& $db->tableExists('school_fees')
		&& $fromLevel > 0 && $toLevel > 0
		&& ($fromLevel !== $toLevel || $fromDept !== $toDept)
	) {
		$oldFees = $db->query(
			'SELECT id, term FROM school_fees WHERE school_id = ? AND level = ? AND department = ? AND academic_year = ?',
			[SCHOOL_ID, $fromLevel, $fromDept, $yearKey]
		)->getResultArray();
		$newFees = $db->query(
			'SELECT id, term FROM school_fees WHERE school_id = ? AND level = ? AND department = ? AND academic_year = ?',
			[SCHOOL_ID, $toLevel, $toDept, $yearKey]
		)->getResultArray();
		$newByTerm = [];
		foreach ($newFees as $sf) {
			$t = (int) ($sf['term'] ?? 0);
			if ($t > 0 && !isset($newByTerm[$t])) {
				$newByTerm[$t] = (int) $sf['id'];
			}
		}
		foreach ($oldFees as $sf) {
			$oldId = (int) ($sf['id'] ?? 0);
			$newId = $newByTerm[(int) ($sf['term'] ?? 0)] ?? 0;
			if ($oldId < 1 || $newId < 1 || $oldId === $newId) {
				continue;
			}
			$db->query(
				'UPDATE fees_records SET fees_id = ? WHERE student_id = ? AND fees_type = 0 AND fees_id = ?',
				[$newId, $studentId, $oldId]
			);
			if ($db->tableExists('school_fees_discount')) {
				$db->query(
					'UPDATE school_fees_discount SET feesId = ? WHERE student = ? AND feesId = ?',
					[$newId, $studentId, $oldId]
				);
			}
		}
	}
	if ($db->tableExists('students') && $db->fieldExists('application_id', 'students') && $db->tableExists('applications')) {
		$appId = (int) ($db->query(
			'SELECT application_id FROM students WHERE id = ? AND school_id = ? LIMIT 1',
			[$studentId, SCHOOL_ID]
		)->getRowArray()['application_id'] ?? 0);
		if ($appId > 0 && $db->fieldExists('class_id', 'applications')) {
			$db->query(
				'UPDATE applications SET class_id = ? WHERE id = ? AND schoolId = ?',
				[$toClassId, $appId, SCHOOL_ID]
			);
		}
	}
}
}
