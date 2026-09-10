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

	/** Nursery / primary / high school (O/A/RTB/Special). */
	public static function generationPhaseKey(string $track): string
	{
		$track = self::normalize($track);
		if ($track === self::NURSERY) {
			return 'nursery';
		}
		if ($track === self::PRIMARY) {
			return 'primary';
		}
		return 'high_school';
	}

	/** @return list<string> */
	public static function generationPhaseKeys(): array
	{
		return ['nursery', 'primary', 'high_school'];
	}

	public static function normalizeGenerationPhase(?string $phase): string
	{
		$phase = strtolower(trim((string) $phase));
		if ($phase === 'secondary' || $phase === 'highschool') {
			$phase = 'high_school';
		}
		if (in_array($phase, self::generationPhaseKeys(), true)) {
			return $phase;
		}
		return 'all';
	}

	public static function generationPhaseLabel(string $phaseKey): string
	{
		switch (strtolower(trim($phaseKey))) {
			case 'nursery':
				return 'Nursery';
			case 'primary':
				return 'Primary';
			case 'high_school':
			case 'secondary':
				return 'High school';
			case 'all':
				return 'All levels';
			default:
				return 'Timetable';
		}
	}

	public static function generationPhaseHint(string $phaseKey): string
	{
		switch (self::normalizeGenerationPhase($phaseKey)) {
			case 'nursery':
				return 'Baby / Middle / Top class';
			case 'primary':
				return 'P1 – P6';
			case 'high_school':
				return 'O Level · A Level · RTB · Special';
			default:
				return 'Nursery → Primary → High school';
		}
	}

	/**
	 * Preferred generation order: nursery → primary → remaining tracks.
	 *
	 * @param list<string> $trackKeys
	 * @return list<string>
	 */
	public static function orderTracksForGeneration(array $trackKeys): array
	{
		$priority = [
			self::NURSERY => 10,
			self::PRIMARY => 20,
			self::O_LEVEL => 30,
			self::A_LEVEL => 40,
			self::SPECIAL => 50,
			self::RTB => 60,
			self::ALL => 70,
		];
		$unique = [];
		foreach ($trackKeys as $key) {
			$unique[self::normalize((string) $key)] = true;
		}
		$keys = array_keys($unique);
		usort($keys, static function (string $a, string $b) use ($priority): int {
			$pa = $priority[$a] ?? 90;
			$pb = $priority[$b] ?? 90;
			if ($pa !== $pb) {
				return $pa <=> $pb;
			}
			return strcmp($a, $b);
		});
		return $keys;
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
