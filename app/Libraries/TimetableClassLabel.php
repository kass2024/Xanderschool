<?php

namespace App\Libraries;

/**
 * Full class label: level + department/combination code + stream (e.g. S6 PCBM, P4 A).
 */
class TimetableClassLabel
{
	public static function format(
		?string $levelName,
		?string $classTitle,
		?string $deptCode,
		?string $deptName = null
	): string {
		$level = trim((string) $levelName);
		$title = trim((string) $classTitle);
		$code = trim((string) $deptCode);
		$dept = trim((string) $deptName);

		// Skip generic department names when code is empty (Primary, Nursery, etc.)
		if ($code === '' && $dept !== '') {
			$generic = ['primary', 'nursery', "o' level", 'o level', 'secondary', 'a level', 'tvet'];
			foreach ($generic as $g) {
				if (strcasecmp($dept, $g) === 0) {
					$dept = '';
					break;
				}
			}
		}

		if ($code === '' && $dept !== '' && strlen($dept) <= 8) {
			$code = $dept;
		}

		$label = trim($level . ' ' . $code . ' ' . $title);
		$label = preg_replace('/\s+/', ' ', $label) ?? '';

		return $label !== '' ? $label : 'Class';
	}

	/** @param array<string,mixed> $row */
	public static function fromRow(array $row): string
	{
		return self::format(
			$row['level_name'] ?? $row['level'] ?? '',
			$row['class_title'] ?? $row['title'] ?? '',
			$row['dept_code'] ?? $row['code'] ?? '',
			$row['dept_name'] ?? $row['department_name'] ?? ''
		);
	}
}
