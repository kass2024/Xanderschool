<?php

namespace App\Libraries;

/**
 * Timetable schedule categories (not individual grades like S6 or P1).
 */
class TimetableTrack
{
	public const ALL = 'all';
	public const PRIMARY = 'primary';
	public const NURSERY = 'nursery';
	public const O_LEVEL = 'o_level';
	public const A_LEVEL = 'a_level';
	public const SPECIAL = 'special';
	public const RTB = 'rtb';

	/** @return array<string,string> */
	public static function labels(): array
	{
		return [
			self::ALL => 'All levels (shared)',
			self::PRIMARY => 'Primary',
			self::NURSERY => 'Nursery',
			self::O_LEVEL => 'O Level',
			self::A_LEVEL => 'A Level',
			self::SPECIAL => 'Special',
			self::RTB => 'RTB / TVET',
		];
	}

	/** @return list<string> */
	public static function categoryKeys(): array
	{
		return [self::PRIMARY, self::NURSERY, self::O_LEVEL, self::A_LEVEL, self::SPECIAL, self::RTB];
	}

	public static function normalize(?string $track): string
	{
		$track = strtolower(trim((string) $track));
		$labels = self::labels();
		return isset($labels[$track]) ? $track : self::ALL;
	}

	/**
	 * Resolve track from class + level + faculty data.
	 *
	 * @param array<string,mixed> $row
	 */
	public static function resolveFromRow(array $row): string
	{
		$levelTitle = strtolower(trim((string) ($row['level_title'] ?? $row['level_name'] ?? '')));
		$facTitle = strtolower(trim((string) ($row['faculty_title'] ?? '')));
		$facAbbrev = strtolower(trim((string) ($row['faculty_abbrev'] ?? '')));
		$facType = (int) ($row['faculty_type'] ?? 0);
		$levelFacultyId = (int) ($row['level_faculty_id'] ?? 0);
		$deptFacultyId = (int) ($row['dept_faculty_id'] ?? 0);

		$hay = $levelTitle . ' ' . $facTitle . ' ' . $facAbbrev;

		if ($facType === 3 || self::matches($hay, ['special', 'nursing', 'anp'])) {
			return self::SPECIAL;
		}
		if ($facType === 1 || self::matches($hay, ['rtb', 'tvet', 'wda']) || self::matches($levelTitle, ['level 1', 'level 2', 'level 3', 'level 4', 'level 5', 'year 1', 'year 2', 'year 3'])) {
			return self::RTB;
		}
		if ($levelFacultyId === 19 || $deptFacultyId === 19 || self::matches($hay, ['nursery', 'n1', 'n2', 'n3', 'baby class', 'middle class', 'top class'])) {
			return self::NURSERY;
		}
		if ($levelFacultyId === 3 || $deptFacultyId === 3 || self::matches($hay, ['primary', 'p1', 'p2', 'p3', 'p4', 'p5', 'p6'])) {
			return self::PRIMARY;
		}
		if ($levelFacultyId === 2 || $deptFacultyId === 2 || self::matches($hay, ["o level", "o' level", 'ordinary']) || preg_match('/\bs[123]\b/', $levelTitle)) {
			return self::O_LEVEL;
		}
		if ($levelFacultyId === 1 || $deptFacultyId === 1 || self::matches($hay, ["a level", "a' level", 'science and math']) || preg_match('/\b(s[456]|senior\s*[456])\b/', $levelTitle)) {
			return self::A_LEVEL;
		}
		if (preg_match('/\bp[1-6]\b/', $levelTitle)) {
			return self::PRIMARY;
		}
		if (preg_match('/\bn[1-3]\b/', $levelTitle)) {
			return self::NURSERY;
		}
		if (preg_match('/\bs[1-3]\b/', $levelTitle)) {
			return self::O_LEVEL;
		}
		if (preg_match('/\bs[4-6]\b/', $levelTitle) || stripos($levelTitle, 'senior') !== false) {
			return self::A_LEVEL;
		}

		return self::PRIMARY;
	}

	public static function resolveForClassId(int $classId): string
	{
		if ($classId <= 0) {
			return self::ALL;
		}
		$db = \Config\Database::connect();
		$row = $db->table('classes c')
			->select('l.title AS level_title, l.faculty_id AS level_faculty_id,
				f.title AS faculty_title, f.abbrev AS faculty_abbrev, f.type AS faculty_type,
				d.faculty_id AS dept_faculty_id')
			->join('levels l', 'l.id = c.level', 'left')
			->join('departments d', 'd.id = c.department', 'left')
			->join('faculty f', 'f.id = d.faculty_id', 'left')
			->where('c.id', $classId)
			->get(1)->getRowArray();

		return $row ? self::resolveFromRow($row) : self::ALL;
	}

	/** @return list<string> tracks used by classes in this school */
	public static function tracksForSchool(int $schoolId): array
	{
		$db = \Config\Database::connect();
		$rows = $db->table('classes c')
			->select('l.title AS level_title, l.faculty_id AS level_faculty_id,
				f.title AS faculty_title, f.abbrev AS faculty_abbrev, f.type AS faculty_type,
				d.faculty_id AS dept_faculty_id')
			->join('levels l', 'l.id = c.level', 'left')
			->join('departments d', 'd.id = c.department', 'left')
			->join('faculty f', 'f.id = d.faculty_id', 'left')
			->where('c.school_id', $schoolId)
			->groupBy('c.level, d.faculty_id')
			->get()->getResultArray();

		$found = [];
		foreach ($rows as $row) {
			$found[self::resolveFromRow($row)] = true;
		}

		$ordered = [];
		foreach (self::categoryKeys() as $key) {
			if (!empty($found[$key])) {
				$ordered[] = $key;
			}
		}
		return $ordered;
	}

	private static function matches(string $haystack, array $needles): bool
	{
		foreach ($needles as $needle) {
			if ($needle !== '' && strpos($haystack, strtolower($needle)) !== false) {
				return true;
			}
		}
		return false;
	}
}
