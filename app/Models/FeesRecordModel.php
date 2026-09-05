<?php
namespace App\Models;

use CodeIgniter\Model;

class FeesRecordModel extends Model
{
	protected $table = 'fees_records';
	protected $allowedFields = [
		'student_id', 'fees_type', 'amount', 'fees_id', 'apiId', 'refNo',
		'payment_mode', 'due_date', 'status', 'created_by',
		'is_installment', 'promised_date', 'reminder_sent_at',
	];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField = 'created_at';
	protected $updatedField = 'updated_at';
	private static $schemaReady = false;

	public function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}

		$db = \Config\Database::connect();
		$this->ensureColumn(
			$db,
			'fees_records',
			'is_installment',
			'TINYINT(1) NOT NULL DEFAULT 0',
			'`status`'
		);
		$this->ensureColumn(
			$db,
			'fees_records',
			'promised_date',
			'DATE NULL DEFAULT NULL',
			'`is_installment`'
		);
		$this->ensureColumn(
			$db,
			'fees_records',
			'reminder_sent_at',
			'DATETIME NULL DEFAULT NULL',
			'`promised_date`'
		);
		self::$schemaReady = true;
	}

	private function ensureColumn($db, string $table, string $column, string $definition, string $afterColumn): void
	{
		if ($this->columnExists($db, $table, $column)) {
			return;
		}

		$isSqlite = stripos((string) ($db->DBDriver ?? ''), 'sqlite') !== false;
		$sql = $isSqlite
			? sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition)
			: sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s AFTER %s', $table, $column, $definition, $afterColumn);

		try {
			$db->query($sql);
		} catch (\Throwable $e) {
			if ($this->columnExists($db, $table, $column) || $this->isDuplicateColumnError($e)) {
				return;
			}
			throw $e;
		}
	}

	private function columnExists($db, string $table, string $column): bool
	{
		if ($db->fieldExists($column, $table)) {
			return true;
		}

		if (stripos((string) ($db->DBDriver ?? ''), 'sqlite') === false) {
			return false;
		}

		$rows = $db->query('PRAGMA table_info(`' . $table . '`)')->getResultArray();
		foreach ($rows as $row) {
			if (strcasecmp((string) ($row['name'] ?? ''), $column) === 0) {
				return true;
			}
		}
		return false;
	}

	private function isDuplicateColumnError(\Throwable $e): bool
	{
		return stripos($e->getMessage(), 'duplicate column name') !== false;
	}
}
