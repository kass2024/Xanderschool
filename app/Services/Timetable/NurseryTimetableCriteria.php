<?php

namespace App\Services\Timetable;

use App\Libraries\TimetableTrack;

/**
 * Nursery-only timetable rules:
 * - 3 or 4 distinct taught courses per day before lunch (cover weekly periods)
 * - no taught course after lunch
 * - afternoon is one merged HOME WORK band
 */
class NurseryTimetableCriteria
{
	public const MIN_DISTINCT_COURSES_PER_DAY = 3;

	public const PREFERRED_DISTINCT_COURSES_PER_DAY = 4;

	public static function isCoreCourse(array $row): bool
	{
		return (int) round((float) ($row['marks'] ?? 0)) >= 100;
	}

	public static function morningCapacity(int $morningSlots, int $dayCount): int
	{
		$slots = max(0, $morningSlots);
		$days = max(1, $dayCount);

		return $slots * $days;
	}

	/**
	 * Cut weekly periods so they fit the morning grid. Core (marks 100) keep more
	 * periods; non-core extras are reduced first. Every course keeps 1 period while capacity allows.
	 *
	 * @param list<array{key:string|int,hours:int,is_core:bool}> $courses
	 * @return array<string|int,int>
	 */
	public static function rebalanceWeeklyHours(array $courses, int $capacity): array
	{
		$hours = [];
		$core = [];
		foreach ($courses as $item) {
			$key = $item['key'];
			$hours[$key] = max(0, (int) ($item['hours'] ?? 0));
			$core[$key] = !empty($item['is_core']);
		}
		$capacity = max(0, $capacity);
		$sum = (int) array_sum($hours);
		if ($sum <= $capacity) {
			return $hours;
		}

		$overflow = $sum - $capacity;
		$original = $hours;
		$nonCore = [];
		$coreKeys = [];
		foreach ($hours as $key => $_) {
			if (!empty($core[$key])) {
				$coreKeys[] = $key;
			} else {
				$nonCore[] = $key;
			}
		}

		$reduce = static function (array $keys, int $minKeep) use (&$hours, &$overflow, $original): void {
			while ($overflow > 0) {
				$best = null;
				$bestHours = $minKeep;
				$bestOrig = PHP_INT_MAX;
				foreach ($keys as $key) {
					$current = (int) $hours[$key];
					if ($current <= $minKeep) {
						continue;
					}
					$orig = (int) $original[$key];
					if ($current > $bestHours
						|| ($current === $bestHours && $orig < $bestOrig)
						|| ($current === $bestHours && $orig === $bestOrig && ($best === null || (string) $key < (string) $best))
					) {
						$best = $key;
						$bestHours = $current;
						$bestOrig = $orig;
					}
				}
				if ($best === null) {
					return;
				}
				$hours[$best]--;
				$overflow--;
			}
		};

		$reduce($nonCore, 1);
		$reduce($nonCore, 0);
		$reduce($coreKeys, 2);
		$reduce($coreKeys, 1);
		$reduce($coreKeys, 0);

		return $hours;
	}

	public const MIN_HOMEWORK_PER_DAY = 1;

	public const MAX_HOMEWORK_PER_DAY = 4;

	/** Homework / late window starts after lunch */
	public const HOMEWORK_START_MINUTES = 13 * 60;

	/** End of day */
	public const HOMEWORK_END_MINUTES = 16 * 60 + 30;

	/** Homework periods are ~30 minutes */
	public const HOMEWORK_DURATION_MIN = 25;

	public const HOMEWORK_DURATION_MAX = 35;

	public static function isNurseryRow(array $row): bool
	{
		$track = strtolower(trim((string) ($row['_track_key'] ?? $row['track_key'] ?? '')));
		return $track === TimetableTrack::NURSERY;
	}

	/**
	 * Explicit homework course titles only (e.g. "Homework in Writing", "HOME WORK").
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

	public static function slotIsAfterLunch(?string $startTime, ?string $endTime = null): bool
	{
		$start = TimetableGeneratorService::clockMinutesFromString((string) $startTime);
		if ($start >= self::HOMEWORK_START_MINUTES) {
			return true;
		}
		if ($endTime === null || $endTime === '') {
			return false;
		}
		$end = TimetableGeneratorService::clockMinutesFromString((string) $endTime);

		return $end > self::HOMEWORK_START_MINUTES;
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

	/**
	 * Afternoon ~30-minute bells reserved for homework (not the morning 10:00–10:30 lesson).
	 */
	public static function isHomeworkSizedSlot(?string $startTime, ?string $endTime): bool
	{
		if (!self::slotOverlapsHomeworkWindow($startTime, $endTime)) {
			return false;
		}
		$start = TimetableGeneratorService::clockMinutesFromString((string) $startTime);
		$end = TimetableGeneratorService::clockMinutesFromString((string) $endTime);
		if ($end <= $start) {
			return false;
		}
		$mins = $end - $start;

		return $mins >= self::HOMEWORK_DURATION_MIN && $mins <= self::HOMEWORK_DURATION_MAX;
	}

	/** Prefer the true last 30-minute periods for homework. */
	public static function homeworkLatenessBonus(?string $startTime): int
	{
		$start = TimetableGeneratorService::clockMinutesFromString((string) $startTime);
		if ($start >= 16 * 60) {
			return -5200;
		}
		if ($start >= 15 * 60 + 30) {
			return -4800;
		}
		if ($start >= 14 * 60 + 30) {
			return -3600;
		}
		if ($start >= 14 * 60) {
			return -3000;
		}
		if ($start >= 13 * 60 + 30) {
			return -1800;
		}
		if ($start >= 13 * 60) {
			return -600;
		}

		return 9000;
	}

	public static function maxPerDay(int $uniqueCoursesOnDay, bool $alreadyHasThisCourse, bool $isCore = true): int
	{
		if (!$isCore) {
			return 1;
		}
		// Core (marks 100) may appear twice in one day.
		return 2;
	}

	public static function varietyScoreDelta(int $uniqueCoursesOnDay, bool $alreadyHasThisCourse): int
	{
		if ($alreadyHasThisCourse) {
			if ($uniqueCoursesOnDay < self::MIN_DISTINCT_COURSES_PER_DAY) {
				return 12000;
			}
			return 2800;
		}
		if ($uniqueCoursesOnDay < self::MIN_DISTINCT_COURSES_PER_DAY) {
			return -7000;
		}
		if ($uniqueCoursesOnDay < self::PREFERRED_DISTINCT_COURSES_PER_DAY) {
			return -2500;
		}

		return -200;
	}

	/**
	 * @param int $homeworkOnDay how many homework cells already on this class/day
	 */
	public static function homeworkScoreDelta(
		array $row,
		?string $startTime,
		?string $endTime,
		bool $alreadyHasHomework = false,
		int $homeworkOnDay = 0,
		int $uniqueCoursesOnDay = 0
	): int {
		$explicit = self::isHomeworkCourse((string) ($row['course_title'] ?? ''))
			|| !empty($row['_nursery_hw_place']);
		$inWindow = self::slotOverlapsHomeworkWindow($startTime, $endTime);
		$sized = self::isHomeworkSizedSlot($startTime, $endTime);

		if ($explicit) {
			if (!$inWindow || !$sized) {
				return 18000;
			}
			if ($homeworkOnDay >= self::MAX_HOMEWORK_PER_DAY) {
				return 14000;
			}
			$score = self::homeworkLatenessBonus($startTime);
			// Prefer days that already have 4 taught courses, but still place homework otherwise.
			if ($uniqueCoursesOnDay < self::MIN_DISTINCT_COURSES_PER_DAY) {
				$score += 2500;
			}
			if ($homeworkOnDay === 0) {
				$score -= 3500;
			} elseif ($homeworkOnDay === 1) {
				$score -= 1200;
			} elseif ($homeworkOnDay === 2) {
				$score -= 400;
			}

			return $score;
		}

		// Normal taught lesson: mornings only. After lunch is HOME WORK.
		if ($inWindow || self::slotIsAfterLunch($startTime, $endTime)) {
			return 50000;
		}

		return $uniqueCoursesOnDay < self::MIN_DISTINCT_COURSES_PER_DAY ? -500 : (
			$uniqueCoursesOnDay < self::PREFERRED_DISTINCT_COURSES_PER_DAY ? -150 : 0
		);
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
