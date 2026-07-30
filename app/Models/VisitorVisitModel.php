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
	 * Record visit IN or OUT for today (toggle like attendance).
	 * Returns result array with action, visit, message.
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

		$open = $this->where('school_id', $schoolId)
			->where('visitor_id', $visitorId)
			->where('visit_date', $today)
			->where('time_out', 0)
			->orderBy('id', 'DESC')
			->first();

		if ($open) {
			// Require at least 2 minutes between IN and OUT to avoid double-scan
			$timeIn = (int) ($open['time_in'] ?? 0);
			if ($timeIn > 0 && ($now - $timeIn) < 120) {
				return [
					'success' => true,
					'action' => 'in',
					'too_soon' => true,
					'visit' => $open,
					'message' => 'Already checked IN. Wait a moment before OUT.',
				];
			}

			$this->save([
				'id' => (int) $open['id'],
				'time_out' => $now,
				'updated_at' => date('Y-m-d H:i:s'),
			]);

			$visit = $this->find((int) $open['id']);

			return [
				'success' => true,
				'action' => 'out',
				'too_soon' => false,
				'visit' => $visit,
				'message' => 'Visit OUT recorded.',
			];
		}

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
}
