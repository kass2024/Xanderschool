<?php

namespace App\Models;

use CodeIgniter\Model;

class VisitorVisitModel extends Model
{
	protected $table = 'visitor_visits';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = [
		'school_id',
		'visitor_id',
		'student_id',
		'card',
		'visit_date',
		'time_in',
		'time_out',
		'source',
		'operator',
		'notes',
	];
	protected $useTimestamps = true;

	/**
	 * One IN per visitor per day. Later taps set/overwrite OUT (not a second IN).
	 *
	 * @param array $visitor Row with at least id, student_id, names, card, status
	 * @param int $schoolId
	 * @param string $card
	 * @param string $source web|android
	 * @param int|null $operator
	 * @param string $notes
	 * @return array
	 */
	public function toggleVisitToday(array $visitor, $schoolId, $card, $source = 'web', $operator = null, $notes = '')
	{
		$schoolId = (int) $schoolId;
		$visitorId = (int) ($visitor['id'] ?? 0);
		$studentId = (int) ($visitor['student_id'] ?? 0);
		$now = time();
		$today = date('Y-m-d', $now);

		// Any visit row for this visitor today (open or already checked out).
		$todayVisit = $this->where('school_id', $schoolId)
			->where('visitor_id', $visitorId)
			->where('visit_date', $today)
			->orderBy('id', 'DESC')
			->first();

		if (!$todayVisit) {
			$id = $this->insert([
				'school_id' => $schoolId,
				'visitor_id' => $visitorId,
				'student_id' => $studentId,
				'card' => $card,
				'visit_date' => $today,
				'time_in' => $now,
				'time_out' => 0,
				'source' => $source,
				'operator' => $operator,
				'notes' => $notes,
			]);

			$visit = $this->find($id);

			return [
				'success' => true,
				'action' => 'in',
				'too_soon' => false,
				'visit' => $visit,
				'message' => 'Visit IN recorded.',
			];
		}

		$timeIn = (int) ($todayVisit['time_in'] ?? 0);
		// Short debounce only — avoid flipping IN→OUT on the same double-tap.
		if ($timeIn > 0 && (int) ($todayVisit['time_out'] ?? 0) === 0 && ($now - $timeIn) < 3) {
			return [
				'success' => true,
				'action' => 'in',
				'too_soon' => true,
				'visit' => $todayVisit,
				'message' => 'Already checked IN.',
			];
		}

		$this->save([
			'id' => (int) $todayVisit['id'],
			'time_out' => $now,
			'updated_at' => date('Y-m-d H:i:s'),
		]);

		$visit = $this->find((int) $todayVisit['id']);

		return [
			'success' => true,
			'action' => 'out',
			'too_soon' => false,
			'visit' => $visit,
			'message' => 'Visit OUT recorded.',
		];
	}
}
