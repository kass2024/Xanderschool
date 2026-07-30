<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentVisitorModel extends Model
{
	protected $table = 'student_visitors';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = [
		'school_id',
		'student_id',
		'names',
		'phone',
		'relationship',
		'card',
		'status',
		'created_by',
		'updated_by',
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

		$db->query("CREATE TABLE IF NOT EXISTS `student_visitors` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`student_id` INT UNSIGNED NOT NULL,
			`names` VARCHAR(150) NOT NULL,
			`phone` VARCHAR(50) NULL DEFAULT NULL,
			`relationship` VARCHAR(80) NULL DEFAULT NULL,
			`card` VARCHAR(50) NULL DEFAULT NULL,
			`status` TINYINT(1) NOT NULL DEFAULT 1,
			`created_by` INT NULL DEFAULT NULL,
			`updated_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_sv_school_student` (`school_id`, `student_id`),
			KEY `idx_sv_school_card` (`school_id`, `card`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		$db->query("CREATE TABLE IF NOT EXISTS `visitor_visits` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`school_id` INT UNSIGNED NOT NULL,
			`visitor_id` INT UNSIGNED NOT NULL,
			`student_id` INT UNSIGNED NOT NULL,
			`card` VARCHAR(50) NULL DEFAULT NULL,
			`visit_date` DATE NOT NULL,
			`time_in` INT UNSIGNED NOT NULL DEFAULT 0,
			`time_out` INT UNSIGNED NOT NULL DEFAULT 0,
			`source` VARCHAR(20) NOT NULL DEFAULT 'web',
			`operator` INT NULL DEFAULT NULL,
			`notes` TEXT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_vv_school_date` (`school_id`, `visit_date`),
			KEY `idx_vv_visitor` (`visitor_id`),
			KEY `idx_vv_student` (`student_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		self::$schemaReady = true;
	}

	/**
	 * Check if card is already used by a student or visitor in this school.
	 * @param int $schoolId
	 * @param string $card
	 * @param int $excludeVisitorId
	 * @return array|null ['type'=>'student'|'visitor','name'=>..., 'id'=>...]
	 */
	public function findCardCollision($schoolId, $card, $excludeVisitorId = 0)
	{
		$schoolId = (int) $schoolId;
		$card = strtoupper(trim((string) $card));
		if ($card === '' || $schoolId <= 0) {
			return null;
		}

		$db = \Config\Database::connect();

		$student = $db->table('students')
			->select("id, CONCAT(fname, ' ', lname) AS name")
			->where('school_id', $schoolId)
			->where('UPPER(TRIM(card))', $card)
			->get(1)
			->getRowArray();

		if ($student) {
			return [
				'type' => 'student',
				'id' => (int) $student['id'],
				'name' => $student['name'],
			];
		}

		$builder = $db->table('student_visitors')
			->select('id, names')
			->where('school_id', $schoolId)
			->where('UPPER(TRIM(card))', $card)
			->where('status', 1);

		if ($excludeVisitorId > 0) {
			$builder->where('id !=', (int) $excludeVisitorId);
		}

		$visitor = $builder->get(1)->getRowArray();
		if ($visitor) {
			return [
				'type' => 'visitor',
				'id' => (int) $visitor['id'],
				'name' => $visitor['names'],
			];
		}

		return null;
	}

	/**
	 * Active visitor count for a student.
	 * @param int $schoolId
	 * @param int $studentId
	 * @return int
	 */
	public function countActiveForStudent($schoolId, $studentId)
	{
		return (int) $this->where('school_id', (int) $schoolId)
			->where('student_id', (int) $studentId)
			->where('status', 1)
			->countAllResults();
	}
}
