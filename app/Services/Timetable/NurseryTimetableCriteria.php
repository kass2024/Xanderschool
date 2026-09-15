<?php

namespace App\Services\Timetable;

use App\Libraries\TimetableTrack;

/**
 * Nursery-only timetable rules: varied days, one homework period per course
 * in the post-lunch window (labelled "Homework in …"), pastel course colors.
 */
class NurseryTimetableCriteria
{
	public const MIN_DISTINCT_COURSES_PER_DAY = 3;

	/** 13:00 — first teaching slot after lunch */
	public const HOMEWORK_START_MINUTES = 13 * 60;

	/** 16:30 — periods 5–7 (after lunch through end of day) so every course can get homework */
	public const HOMEWORK_END_MINUTES = 16 * 60 + 30;

	public static function isNurseryRow(array $row): bool
	{
		$track = strtolower(trim((string) ($row['_track_key'] ?? $row['track_key'] ?? '')));
		return $track === TimetableTrack::NURSERY;
	}

	/**
	 * Explicit homework course titles only (e.g. "Homework in Writing").
	 * Plain "Writing" is a normal lesson, not homework.
	 */
	public static function isHomeworkCourse(string $title): bool
	{
		$t = self::normalizeTitle($title);
		if ($t === '') {
			return false;
		}
		if (strpos($t, 'homework') !== false || strpos($t, 'home work') !== false || strpos($t, 'home-work') !== false) {
			return true;
		}

		return (bool) preg_match('/\bhw\b/', $t);
	}

	public static function homeworkLabelForCourse(string $title): string
	{
		$title = trim($title);
		if ($title === '') {
			return 'Homework';
		}
		if (self::isHomeworkCourse($title)) {
			return $title;
		}

		return 'Homework in ' . $title;
	}

	public static function slotOverlapsHomeworkWindow(?string $startTime, ?string $endTime): bool
	{
		$start = TimetableGeneratorService::clockMinutesFromString((string) $startTime);
		$end = TimetableGeneratorService::clockMinutesFromString((string) $endTime);
		if ($end <= $start) {
			$end = $start + 40;
		}

		return $start < self::HOMEWORK_END_MINUTES && $end > self::HOMEWORK_START_MINUTES;
	}

	public static function maxPerDay(int $uniqueCoursesOnDay, bool $alreadyHasThisCourse): int
	{
		if (!$alreadyHasThisCourse) {
			return 1;
		}
		// A second period of the same course is allowed only after the day already has 3 subjects.
		return $uniqueCoursesOnDay >= self::MIN_DISTINCT_COURSES_PER_DAY ? 2 : 1;
	}

	public static function varietyScoreDelta(int $uniqueCoursesOnDay, bool $alreadyHasThisCourse): int
	{
		if ($alreadyHasThisCourse) {
			if ($uniqueCoursesOnDay < self::MIN_DISTINCT_COURSES_PER_DAY) {
				return 9000;
			}
			return 2500;
		}
		if ($uniqueCoursesOnDay < self::MIN_DISTINCT_COURSES_PER_DAY) {
			return -4500;
		}

		return -150;
	}

	/**
	 * Every nursery course needs one homework-window period in the week.
	 * Explicit "Homework in …" courses stay in that window only.
	 */
	public static function homeworkScoreDelta(
		array $row,
		?string $startTime,
		?string $endTime,
		bool $alreadyHasHomework = false
	): int {
		$explicit = self::isHomeworkCourse((string) ($row['course_title'] ?? ''));
		$inWindow = self::slotOverlapsHomeworkWindow($startTime, $endTime);
		if ($explicit) {
			return $inWindow ? -8500 : 14000;
		}
		if (!$alreadyHasHomework) {
			return $inWindow ? -9000 : 4500;
		}

		return $inWindow ? 3500 : 0;
	}

	/**
	 * @return array{bg:string,fg:string}
	 */
	public static function colorForCourse(string $title, int $courseId = 0): array
	{
		$t = self::normalizeTitle($title);
		if (self::isHomeworkCourse($title) || strpos($t, 'homework in') === 0) {
			return ['bg' => '#fde68a', 'fg' => '#92400e'];
		}
		$map = [
			'drawing' => ['bg' => '#fed7aa', 'fg' => '#9a3412'],
			'art' => ['bg' => '#fed7aa', 'fg' => '#9a3412'],
			'craft' => ['bg' => '#fdba74', 'fg' => '#7c2d12'],
			'music' => ['bg' => '#e9d5ff', 'fg' => '#6b21a8'],
			'song' => ['bg' => '#e9d5ff', 'fg' => '#6b21a8'],
			'dance' => ['bg' => '#f5d0fe', 'fg' => '#86198f'],
			'english' => ['bg' => '#bfdbfe', 'fg' => '#1e3a8a'],
			'kinyarwanda' => ['bg' => '#bbf7d0', 'fg' => '#14532d'],
			'kinya' => ['bg' => '#bbf7d0', 'fg' => '#14532d'],
			'french' => ['bg' => '#a5f3fc', 'fg' => '#155e75'],
			'math' => ['bg' => '#fef08a', 'fg' => '#854d0e'],
			'number' => ['bg' => '#fef08a', 'fg' => '#854d0e'],
			'health' => ['bg' => '#86efac', 'fg' => '#14532d'],
			'hygiene' => ['bg' => '#86efac', 'fg' => '#14532d'],
			'habit' => ['bg' => '#6ee7b7', 'fg' => '#065f46'],
			'writing' => ['bg' => '#fde68a', 'fg' => '#92400e'],
			'reading' => ['bg' => '#c7d2fe', 'fg' => '#3730a3'],
			'science' => ['bg' => '#99f6e4', 'fg' => '#115e59'],
			'discovery' => ['bg' => '#99f6e4', 'fg' => '#115e59'],
			'religion' => ['bg' => '#ddd6fe', 'fg' => '#5b21b6'],
			'bible' => ['bg' => '#ddd6fe', 'fg' => '#5b21b6'],
			'sport' => ['bg' => '#fecaca', 'fg' => '#991b1b'],
			'physical' => ['bg' => '#fecaca', 'fg' => '#991b1b'],
			'play' => ['bg' => '#fecaca', 'fg' => '#991b1b'],
			'story' => ['bg' => '#fbcfe8', 'fg' => '#9d174d'],
			'rhyme' => ['bg' => '#fbcfe8', 'fg' => '#9d174d'],
			'poem' => ['bg' => '#fbcfe8', 'fg' => '#9d174d'],
			'social' => ['bg' => '#c7d2fe', 'fg' => '#3730a3'],
			'oral' => ['bg' => '#bae6fd', 'fg' => '#075985'],
		];
		foreach ($map as $needle => $tone) {
			if (strpos($t, $needle) !== false) {
				return $tone;
			}
		}

		$palette = [
			['bg' => '#fecdd3', 'fg' => '#9f1239'],
			['bg' => '#bae6fd', 'fg' => '#075985'],
			['bg' => '#d9f99d', 'fg' => '#3f6212'],
			['bg' => '#fbcfe8', 'fg' => '#9d174d'],
			['bg' => '#a5b4fc', 'fg' => '#312e81'],
			['bg' => '#fcd34d', 'fg' => '#78350f'],
			['bg' => '#67e8f9', 'fg' => '#155e75'],
			['bg' => '#fca5a5', 'fg' => '#7f1d1d'],
		];
		$seed = $courseId > 0 ? $courseId : (int) sprintf('%u', crc32($t !== '' ? $t : 'course'));

		return $palette[$seed % count($palette)];
	}

	private static function normalizeTitle(string $title): string
	{
		return strtolower(trim((string) preg_replace('/\s+/', ' ', $title)));
	}
}
