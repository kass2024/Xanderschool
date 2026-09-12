<?php

namespace App\Libraries;

/**
 * Cross-module RFID card ownership — student, staff, and visitor cards are mutually exclusive.
 */
class CardRegistry
{
	/**
	 * @return array{type:string,id:int,name:string,card:string,school_id:int}|null
	 */
	public static function lookup(int $schoolId, string $card): ?array
	{
		helper('card_uid');
		$variants = card_uid_lookup_variants($card);
		if ($schoolId <= 0 || empty($variants)) {
			return null;
		}

		$db = \Config\Database::connect();
		$placeholders = implode(',', array_fill(0, count($variants), '?'));
		$scopeSchoolIds = self::scopeSchoolIds($schoolId);
		$scopePlaceholders = implode(',', array_fill(0, count($scopeSchoolIds), '?'));
		$params = array_merge($scopeSchoolIds, $variants);

		$student = $db->query(
			"SELECT id, school_id, CONCAT(fname, ' ', lname) AS name, card FROM students
			WHERE school_id IN ({$scopePlaceholders}) AND status = 1 AND UPPER(TRIM(card)) IN ({$placeholders})
			ORDER BY school_id ASC, id ASC LIMIT 1",
			$params
		)->getRowArray();
		if ($student) {
			return [
				'type' => 'student',
				'id' => (int) $student['id'],
				'name' => (string) $student['name'],
				'card' => (string) $student['card'],
				'school_id' => (int) ($student['school_id'] ?? $schoolId),
			];
		}

		if ($db->fieldExists('card', 'staffs')) {
			$staff = $db->query(
				"SELECT id, school_id, CONCAT(fname, ' ', lname) AS name, card FROM staffs
				WHERE school_id IN ({$scopePlaceholders}) AND card IS NOT NULL AND TRIM(card) <> ''
				AND UPPER(TRIM(card)) IN ({$placeholders})
				ORDER BY school_id ASC, id ASC LIMIT 1",
				$params
			)->getRowArray();
			if ($staff) {
				return [
					'type' => 'staff',
					'id' => (int) $staff['id'],
					'name' => (string) $staff['name'],
					'card' => (string) $staff['card'],
					'school_id' => (int) ($staff['school_id'] ?? $schoolId),
				];
			}
		}

		$visitor = $db->query(
			"SELECT sv.id, sv.school_id, sv.names AS name, sv.card FROM student_visitors sv
			INNER JOIN students st ON st.id = sv.student_id AND st.school_id = sv.school_id AND st.status = 1
			WHERE sv.school_id IN ({$scopePlaceholders}) AND sv.status = 1 AND UPPER(TRIM(sv.card)) IN ({$placeholders})
			ORDER BY sv.school_id ASC, sv.id ASC LIMIT 1",
			$params
		)->getRowArray();
		if ($visitor) {
			return [
				'type' => 'visitor',
				'id' => (int) $visitor['id'],
				'name' => (string) $visitor['name'],
				'card' => (string) $visitor['card'],
				'school_id' => (int) ($visitor['school_id'] ?? $schoolId),
			];
		}

		return null;
	}

	/**
	 * @return list<int>
	 */
	private static function scopeSchoolIds(int $schoolId): array
	{
		$schoolId = (int) $schoolId;
		if ($schoolId <= 0) {
			return [];
		}

		$db = \Config\Database::connect();
		$scope = [$schoolId];
		try {
			if ($db->fieldExists('is_master', 'schools') && $db->fieldExists('master_school_id', 'schools')) {
				$row = $db->table('schools')
					->select('is_master')
					->where('id', $schoolId)
					->get()
					->getRowArray();
				if (!empty($row['is_master'])) {
					$children = $db->table('schools')
						->select('id')
						->where('master_school_id', $schoolId)
						->get()
						->getResultArray();
					foreach ($children as $child) {
						$childId = (int) ($child['id'] ?? 0);
						if ($childId > 0) {
							$scope[] = $childId;
						}
					}
				}
			}
		} catch (\Throwable $e) {
			// Fall back to the requested school only when hierarchy metadata is unavailable.
		}
		return array_values(array_unique(array_filter(array_map('intval', $scope))));
	}

	/**
	 * @param string $forType student|staff|visitor
	 * @return string|null Error message or null if available
	 */
	public static function assertAvailable(int $schoolId, string $card, string $forType, int $excludeId = 0): ?string
	{
		$forType = strtolower(trim($forType));
		$owner = self::lookup($schoolId, $card);
		if (!$owner) {
			return null;
		}
		if ($owner['type'] === $forType && ($excludeId <= 0 || (int) $owner['id'] === $excludeId)) {
			return null;
		}
		$labels = [
			'student' => 'student',
			'staff' => 'staff member',
			'visitor' => 'parent visitor',
		];
		$who = $labels[$owner['type']] ?? $owner['type'];
		if ($forType === 'staff') {
			return "This card is already assigned to {$who}: {$owner['name']}. Staff cards cannot share UIDs with students or visitors.";
		}
		if ($forType === 'student') {
			return "This card is already assigned to {$who}: {$owner['name']}. Student cards cannot share UIDs with staff or visitors.";
		}
		if ($forType === 'visitor') {
			return "This card is already assigned to {$who}: {$owner['name']}. Visitor cards cannot share UIDs with students or staff.";
		}
		if ($forType === 'gate') {
			return "This card is already assigned to {$who}: {$owner['name']}. Daily visitor cards cannot share UIDs with students, staff, or parent visitors.";
		}
		return "This card is already assigned to {$who}: {$owner['name']}.";
	}

	/**
	 * Resolve person for library / asset operations from a scanned card.
	 *
	 * @return array{type:string,id:int,name:string,class?:string,regno?:string}|null
	 */
	public static function lookupPerson(int $schoolId, string $card): ?array
	{
		$owner = self::lookup($schoolId, $card);
		if (!$owner) {
			return null;
		}
		$db = \Config\Database::connect();
		if ($owner['type'] === 'student') {
			$row = $db->query(
				"SELECT s.id, CONCAT(s.fname, ' ', s.lname) AS name, s.regno,
					c.id AS class_id,
					CONCAT(l.title, ' ', d.code, ' ', c.title) AS class_name
				FROM students s
				LEFT JOIN class_records cr ON cr.student = s.id
				LEFT JOIN classes c ON c.id = cr.class
				LEFT JOIN departments d ON d.id = c.department
				LEFT JOIN levels l ON l.id = c.level
				WHERE s.id = ? AND s.school_id = ? LIMIT 1",
				[(int) $owner['id'], (int) ($owner['school_id'] ?? $schoolId)]
			)->getRowArray();
			if (!$row) {
				return null;
			}
			return [
				'type' => 'student',
				'id' => (int) $row['id'],
				'name' => (string) $row['name'],
				'regno' => (string) ($row['regno'] ?? ''),
				'class' => (string) ($row['class_name'] ?? ''),
				'class_id' => (int) ($row['class_id'] ?? 0),
			];
		}
		if ($owner['type'] === 'staff') {
			return [
				'type' => 'staff',
				'id' => (int) $owner['id'],
				'name' => (string) $owner['name'],
			];
		}
		return null;
	}
}
