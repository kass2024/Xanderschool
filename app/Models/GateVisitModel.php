<?php

namespace App\Models;

use App\Libraries\CardRegistry;
use CodeIgniter\Model;

/**
 * Daily gate visitors — independent from parent visiting (student_visitors).
 * A card is assigned only while the visitor is inside; checkout releases it.
 */
class GateVisitModel extends Model
{
	protected $table = 'gate_visits';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = [
		'school_id',
		'names',
		'phone',
		'reason',
		'materials',
		'card',
		'visit_date',
		'time_in',
		'time_out',
		'source',
	];
	protected $useTimestamps = true;

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema()
	{
		if (self::$schemaReady) {
			return;
		}

		$db = \Config\Database::connect();
		try {
			$db->query("CREATE TABLE IF NOT EXISTS `gate_visits` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`school_id` INT UNSIGNED NOT NULL,
				`names` VARCHAR(150) NOT NULL,
				`phone` VARCHAR(50) NULL DEFAULT NULL,
				`reason` VARCHAR(255) NOT NULL,
				`materials` TEXT NULL,
				`card` VARCHAR(50) NOT NULL,
				`visit_date` DATE NOT NULL,
				`time_in` INT UNSIGNED NOT NULL DEFAULT 0,
				`time_out` INT UNSIGNED NOT NULL DEFAULT 0,
				`source` VARCHAR(20) NOT NULL DEFAULT 'android',
				`created_at` DATETIME NULL DEFAULT NULL,
				`updated_at` DATETIME NULL DEFAULT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_gv_school_date` (`school_id`, `visit_date`),
				KEY `idx_gv_school_card` (`school_id`, `card`),
				KEY `idx_gv_school_open` (`school_id`, `time_out`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		} catch (\Throwable $e) {
		}

		self::$schemaReady = true;
	}

	/**
	 * @return string[]
	 */
	public function cardVariants($raw)
	{
		helper('card_uid');
		$variants = card_uid_lookup_variants((string) $raw);
		return !empty($variants) ? $variants : [];
	}

	public function storedCard($raw)
	{
		helper('card_uid');
		$uid = normalize_card_uid((string) $raw);
		return $uid !== '' ? $uid : strtoupper(preg_replace('/[^A-F0-9]/', '', (string) $raw));
	}

	/**
	 * Open visit currently holding this card (card is assigned).
	 *
	 * @return array|null
	 */
	public function findOpenByCard($schoolId, $card)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$variants = $this->cardVariants($card);
		if ($schoolId <= 0 || empty($variants)) {
			return null;
		}

		return $this->where('school_id', $schoolId)
			->where('time_out', 0)
			->whereIn('card', $variants)
			->orderBy('id', 'DESC')
			->first();
	}

	/**
	 * @return array{success:bool,action?:string,message:string,visit?:array,blocked?:string}
	 */
	public function lookupCard($schoolId, $card)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$card = trim((string) $card);
		if ($schoolId <= 0 || $card === '') {
			return ['success' => false, 'message' => 'School and card are required.'];
		}

		$open = $this->findOpenByCard($schoolId, $card);
		if ($open) {
			return [
				'success' => true,
				'action' => 'checkout',
				'message' => 'Card is assigned. Confirm visitor exit.',
				'visit' => $this->presentVisit($open),
			];
		}

		$owner = CardRegistry::lookup($schoolId, $card);
		if ($owner) {
			$type = (string) ($owner['type'] ?? '');
			$labels = [
				'student' => 'a student',
				'staff' => 'a staff member',
				'visitor' => 'a parent visitor',
			];
			$who = $labels[$type] ?? $type;
			return [
				'success' => false,
				'blocked' => $type,
				'message' => 'This card belongs to ' . $who . ': ' . ($owner['name'] ?? '') . '. Use a daily visitor card.',
			];
		}

		return [
			'success' => true,
			'action' => 'checkin',
			'message' => 'Card is free. Register the visitor.',
			'card' => $this->storedCard($card),
		];
	}

	/**
	 * @return array{success:bool,message:string,visit?:array}
	 */
	public function checkIn($schoolId, array $input)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$names = trim((string) ($input['names'] ?? ''));
		$phone = trim((string) ($input['phone'] ?? ''));
		$reason = trim((string) ($input['reason'] ?? ''));
		$materials = trim((string) ($input['materials'] ?? ''));
		$cardRaw = trim((string) ($input['card'] ?? ''));
		$source = trim((string) ($input['source'] ?? 'android'));
		if ($source === '') {
			$source = 'android';
		}

		if ($schoolId <= 0) {
			return ['success' => false, 'message' => 'School is required.'];
		}
		if ($names === '' || mb_strlen($names) < 2) {
			return ['success' => false, 'message' => 'Visitor name is required.'];
		}
		if ($reason === '') {
			return ['success' => false, 'message' => 'Reason of visit is required.'];
		}
		if ($cardRaw === '') {
			return ['success' => false, 'message' => 'Swipe a visitor card to assign it.'];
		}

		$card = $this->storedCard($cardRaw);
		if ($card === '' || strlen($card) < 4) {
			return ['success' => false, 'message' => 'Card UID is not valid.'];
		}

		$open = $this->findOpenByCard($schoolId, $cardRaw);
		if ($open) {
			return [
				'success' => false,
				'message' => 'This card is still with ' . ($open['names'] ?? 'another visitor') . '. Check them out first.',
				'visit' => $this->presentVisit($open),
			];
		}

		$conflict = CardRegistry::assertAvailable($schoolId, $cardRaw, 'gate');
		if ($conflict) {
			return ['success' => false, 'message' => $conflict];
		}

		$now = time();
		$id = $this->insert([
			'school_id' => $schoolId,
			'names' => mb_substr($names, 0, 150),
			'phone' => mb_substr($phone, 0, 50),
			'reason' => mb_substr($reason, 0, 255),
			'materials' => $materials,
			'card' => $card,
			'visit_date' => date('Y-m-d', $now),
			'time_in' => $now,
			'time_out' => 0,
			'source' => mb_substr($source, 0, 20),
		]);

		$visit = $this->find($id);
		return [
			'success' => true,
			'message' => 'Visitor checked in. Card assigned.',
			'visit' => $this->presentVisit($visit ?: []),
		];
	}

	/**
	 * @return array{success:bool,message:string,visit?:array}
	 */
	public function checkOut($schoolId, $card)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$open = $this->findOpenByCard($schoolId, $card);
		if (!$open) {
			return ['success' => false, 'message' => 'No visitor is currently using this card.'];
		}

		$now = time();
		$timeIn = (int) ($open['time_in'] ?? 0);
		if ($timeIn > 0 && ($now - $timeIn) < 4) {
			return [
				'success' => true,
				'too_soon' => true,
				'message' => 'Already checked in.',
				'visit' => $this->presentVisit($open),
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
			'message' => 'Visitor checked out. Card released.',
			'visit' => $this->presentVisit($visit ?: $open),
		];
	}

	/**
	 * @return array{inside:array,today:array,counts:array}
	 */
	public function todayBoard($schoolId)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$today = date('Y-m-d');

		$inside = $this->where('school_id', $schoolId)
			->where('time_out', 0)
			->orderBy('time_in', 'DESC')
			->findAll(200);

		$todayRows = $this->where('school_id', $schoolId)
			->where('visit_date', $today)
			->orderBy('time_in', 'DESC')
			->findAll(400);

		$presentedInside = array_map([$this, 'presentVisit'], $inside);
		$presentedToday = array_map([$this, 'presentVisit'], $todayRows);
		$checkedOut = 0;
		foreach ($presentedToday as $row) {
			if (!empty($row['time_out'])) {
				$checkedOut++;
			}
		}

		return [
			'inside' => $presentedInside,
			'today' => $presentedToday,
			'counts' => [
				'inside' => count($presentedInside),
				'today' => count($presentedToday),
				'checked_out' => $checkedOut,
			],
		];
	}

	/**
	 * @return array
	 */
	public function report($schoolId, $from, $to)
	{
		$this->ensureSchema();
		$schoolId = (int) $schoolId;
		$rows = $this->where('school_id', $schoolId)
			->where('visit_date >=', $from)
			->where('visit_date <=', $to)
			->orderBy('visit_date', 'DESC')
			->orderBy('time_in', 'DESC')
			->findAll(2000);

		$presented = array_map([$this, 'presentVisit'], $rows);
		$inside = 0;
		$out = 0;
		foreach ($presented as $row) {
			if (empty($row['time_out'])) {
				$inside++;
			} else {
				$out++;
			}
		}

		return [
			'visits' => $presented,
			'summary' => [
				'total' => count($presented),
				'inside' => $inside,
				'checked_out' => $out,
			],
		];
	}

	/**
	 * @param array $row
	 * @return array
	 */
	public function presentVisit($row)
	{
		if (!is_array($row) || empty($row)) {
			return [];
		}
		$timeIn = (int) ($row['time_in'] ?? 0);
		$timeOut = (int) ($row['time_out'] ?? 0);
		$duration = 0;
		if ($timeIn > 0) {
			$duration = ($timeOut > 0 ? $timeOut : time()) - $timeIn;
		}

		return [
			'id' => (int) ($row['id'] ?? 0),
			'names' => (string) ($row['names'] ?? ''),
			'phone' => (string) ($row['phone'] ?? ''),
			'reason' => (string) ($row['reason'] ?? ''),
			'materials' => (string) ($row['materials'] ?? ''),
			'card' => (string) ($row['card'] ?? ''),
			'visit_date' => (string) ($row['visit_date'] ?? ''),
			'time_in' => $timeIn,
			'time_out' => $timeOut,
			'time_in_label' => $timeIn > 0 ? date('H:i', $timeIn) : '',
			'time_out_label' => $timeOut > 0 ? date('H:i', $timeOut) : '',
			'datetime_in' => $timeIn > 0 ? date('Y-m-d H:i', $timeIn) : '',
			'datetime_out' => $timeOut > 0 ? date('Y-m-d H:i', $timeOut) : '',
			'duration_minutes' => (int) floor($duration / 60),
			'source' => (string) ($row['source'] ?? ''),
			'inside' => $timeOut === 0,
		];
	}
}
