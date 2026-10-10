<?php namespace App\Models;
use CodeIgniter\Model;

class IntouchAccount extends Model{
	protected $table = "intouch_accounts";
	protected $allowedFields = ["school_id","username","password","provider","sender"];
	protected $useTimestamps = true;

	public function ensureSchema(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$db = \Config\Database::connect();
		if ($db->tableExists('intouch_accounts')) {
			if (!$db->fieldExists('provider', 'intouch_accounts')) {
				$db->query("ALTER TABLE `intouch_accounts` ADD COLUMN `provider` VARCHAR(20) NOT NULL DEFAULT 'swiftqom'");
			}
			if (!$db->fieldExists('sender', 'intouch_accounts')) {
				$db->query("ALTER TABLE `intouch_accounts` ADD COLUMN `sender` VARCHAR(20) NULL DEFAULT NULL");
			}
			if (!$db->fieldExists('created_at', 'intouch_accounts')) {
				$db->query("ALTER TABLE `intouch_accounts` ADD COLUMN `created_at` DATETIME NULL DEFAULT NULL");
			}
			if (!$db->fieldExists('updated_at', 'intouch_accounts')) {
				$db->query("ALTER TABLE `intouch_accounts` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL");
			}
		}
		$done = true;
	}

}
