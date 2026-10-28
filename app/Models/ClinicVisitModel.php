<?php namespace App\Models;

use CodeIgniter\Model;

class ClinicVisitModel extends Model
{
	protected $table = 'clinic_visits';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $allowedFields = [
		'school_id', 'student_id', 'visit_date', 'disease', 'outcome',
		'hospital_cost', 'transport', 'note', 'created_by',
	];
	protected $useTimestamps = true;

	public const OUTCOME_HERE = 'here';
	public const OUTCOME_HOSPITAL = 'hospital';
	public const OUTCOME_HOME = 'home';

	public function ensureSchema(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('clinic_visits')) {
			$db->query("CREATE TABLE clinic_visits (
				id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				school_id INT NOT NULL,
				student_id INT NOT NULL,
				visit_date DATE NOT NULL,
				disease VARCHAR(120) NOT NULL,
				outcome VARCHAR(20) NOT NULL,
				hospital_cost DECIMAL(12,2) NULL DEFAULT NULL,
				transport VARCHAR(40) NULL DEFAULT NULL,
				note VARCHAR(240) NULL DEFAULT NULL,
				created_by INT NOT NULL DEFAULT 0,
				created_at DATETIME NULL,
				updated_at DATETIME NULL,
				PRIMARY KEY (id),
				KEY idx_clinic_school_day (school_id, visit_date)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
		$done = true;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function listDay(int $schoolId, string $date): array
	{
		$this->ensureSchema();
		if ($schoolId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return [];
		}
		$db = \Config\Database::connect();
		return $db->query(
			"SELECT v.id, v.student_id, v.visit_date, v.disease, v.outcome, v.hospital_cost, v.transport, v.note,
				TRIM(CONCAT(s.fname, ' ', s.lname)) AS student_name, s.regno,
				MAX(CONCAT(COALESCE(l.title,''), ' ', COALESCE(dp.code,''), ' ', COALESCE(c.title,''))) AS class_name
			FROM clinic_visits v
			JOIN students s ON s.id = v.student_id
			LEFT JOIN class_records cr ON cr.student = s.id AND cr.status = 1
			LEFT JOIN classes c ON c.id = cr.class
			LEFT JOIN levels l ON l.id = c.level
			LEFT JOIN departments dp ON dp.id = c.department
			WHERE v.school_id = ? AND v.visit_date = ?
			GROUP BY v.id, v.student_id, v.visit_date, v.disease, v.outcome, v.hospital_cost, v.transport, v.note, s.fname, s.lname, s.regno
			ORDER BY v.id DESC",
			[$schoolId, $date]
		)->getResultArray();
	}
}
