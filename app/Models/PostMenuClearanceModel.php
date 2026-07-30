<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\MenuClearance;

/**
 * Per-post school sidebar menu clearance (JSON list of allowed keys).
 */
class PostMenuClearanceModel extends Model
{
	protected $table = 'post_menu_clearance';
	protected $primaryKey = 'id';
	protected $allowedFields = [
		'post_id',
		'menus',
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
		$db->query("CREATE TABLE IF NOT EXISTS `post_menu_clearance` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`post_id` INT UNSIGNED NOT NULL,
			`menus` TEXT NOT NULL,
			`updated_by` INT NULL DEFAULT NULL,
			`created_at` DATETIME NULL DEFAULT NULL,
			`updated_at` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `uniq_post_id` (`post_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		self::$schemaReady = true;
	}

	/**
	 * Default allowed keys for a post (legacy privileges).
	 *
	 * @param int $postId
	 * @return string[]
	 */
	public static function defaultKeysForPost($postId)
	{
		return MenuClearance::defaultKeysForPost((int) $postId);
	}

	/**
	 * Effective allowed keys: full access / DB row / defaults.
	 *
	 * @param int $postId
	 * @return string[]
	 */
	public function allowedKeysForPost($postId)
	{
		$postId = (int) $postId;
		if (MenuClearance::isFullAccessPost($postId)) {
			return MenuClearance::allKeys();
		}

		$this->ensureSchema();
		$row = $this->where('post_id', $postId)->first();
		if ($row === null || !is_array($row)) {
			return self::defaultKeysForPost($postId);
		}

		$menus = $row['menus'] ?? '';
		$decoded = is_string($menus) ? json_decode($menus, true) : $menus;
		if (!is_array($decoded)) {
			return self::defaultKeysForPost($postId);
		}

		$keys = [];
		foreach ($decoded as $k) {
			if (is_string($k) && $k !== '') {
				$keys[] = $k;
			}
		}

		// Always keep dashboard + profile
		$keys[] = 'dashboard';
		$keys[] = 'profile';

		return array_values(array_unique($keys));
	}

	/**
	 * Whether a DB override row exists (not defaults).
	 *
	 * @param int $postId
	 * @return bool
	 */
	public function hasCustomRow($postId)
	{
		$this->ensureSchema();
		return $this->where('post_id', (int) $postId)->first() !== null;
	}

	/**
	 * Save allowed keys for a post. Full-access posts are ignored.
	 *
	 * @param int $postId
	 * @param string[] $keys
	 * @param int $updatedBy
	 * @return string[]
	 */
	public function saveForPost($postId, array $keys, $updatedBy = 0)
	{
		$postId = (int) $postId;
		if (MenuClearance::isFullAccessPost($postId)) {
			return MenuClearance::allKeys();
		}

		$this->ensureSchema();
		$valid = array_flip(MenuClearance::allKeys());
		$clean = [];
		foreach ($keys as $k) {
			if (!is_string($k) || $k === '') {
				continue;
			}
			if (isset($valid[$k])) {
				$clean[] = $k;
			}
		}
		$clean[] = 'dashboard';
		$clean[] = 'profile';
		$clean = array_values(array_unique($clean));

		$payload = [
			'post_id' => $postId,
			'menus' => json_encode($clean),
			'updated_by' => (int) $updatedBy,
		];

		$existing = $this->where('post_id', $postId)->first();
		if (is_array($existing) && !empty($existing['id'])) {
			$this->update((int) $existing['id'], $payload);
		} else {
			$this->insert($payload);
		}

		return $clean;
	}

	/**
	 * Delete custom row so defaults apply again.
	 *
	 * @param int $postId
	 * @return bool
	 */
	public function resetToDefaults($postId)
	{
		$postId = (int) $postId;
		if (MenuClearance::isFullAccessPost($postId)) {
			return false;
		}
		$this->ensureSchema();
		$this->where('post_id', $postId)->delete();
		return true;
	}
}
