<?php
namespace App\Models;

use CodeIgniter\Model;

class FinanceAuditLogModel extends Model
{
	protected $table = 'finance_audit_log';
	protected $primaryKey = 'id';
	protected $allowedFields = [
		'school_id', 'staff_id', 'staff_name', 'action',
		'entity_type', 'entity_id', 'student_id', 'subject', 'details',
	];
	protected $useTimestamps = true;
	protected $createdField = 'created_at';
	protected $updatedField = '';

	/** @var bool */
	private static $schemaReady = false;

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}
		$db = \Config\Database::connect();
		if (!$db->tableExists('finance_audit_log')) {
			$db->query("CREATE TABLE `finance_audit_log` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`school_id` INT NOT NULL,
				`staff_id` INT NULL DEFAULT NULL,
				`staff_name` VARCHAR(191) NULL DEFAULT NULL,
				`action` VARCHAR(64) NOT NULL,
				`entity_type` VARCHAR(64) NOT NULL,
				`entity_id` INT NULL DEFAULT NULL,
				`student_id` INT NULL DEFAULT NULL,
				`subject` VARCHAR(255) NULL DEFAULT NULL,
				`details` TEXT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `idx_fal_school_created` (`school_id`, `created_at`),
				KEY `idx_fal_entity` (`entity_type`, `entity_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		}
		self::$schemaReady = true;
	}

	public function recentForSchool(int $schoolId, int $limit = 20, array $actions = []): array
	{
		$this->ensureSchema();
		$builder = $this->where('school_id', $schoolId)->orderBy('id', 'DESC');
		if ($actions !== []) {
			$builder->whereIn('action', $actions);
		}
		return $builder->get($limit)->getResultArray();
	}
}
