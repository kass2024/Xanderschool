<?php
namespace App\Models;

use CodeIgniter\Model;

class PostsModel extends Model
{
	protected $table="posts";
	protected $allowedFields = ["title","status"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

	/** Leadership post shown as Executive Principal (legacy title: Principal). */
	public const PRINCIPAL_ID = 15;
	public const PRINCIPAL_TITLE = 'Executive Principal';

	/** Head Teacher / Deputy Head Teacher — used on staff creation privilege list. */
	public const HEAD_TEACHER_ID = 25;
	public const DEPUTY_HEAD_TEACHER_ID = 26;
	public const PHOTO_GRAPHER_ID = 27;
	public const CHIEF_ACCOUNTANT_ID = 28;
	/** Same menu/budget rights as Head master (#1). */
	public const DIRECTOR_ID = 29;
	/** Academic Deputy Director — same menu/budget rights as Director / Head master. Not finance post #21. */
	public const DEPUTY_DIRECTOR_ID = 30;
	public const COOKER_ID = 14;
	public const COOKER_TITLE = 'Cooker';
	public const CUSTOMER_CARE_TITLE = 'Customer Care';
	public const DHT_DISCIPLINE_TITLE = 'DHT DISCIPLINE';
	public const DHT_ACADEMICS_TITLE = 'DHT ACADEMICS';

	/** Ensure built-in system posts exist and restricted ones get a starter clearance row. */
	public function ensureLeadershipPosts(): void
	{
		static $ready = false;
		if ($ready) {
			return;
		}
		$ready = true;
		$db = \Config\Database::connect();
		$this->renameExactPostTitle(self::PRINCIPAL_ID, 'Principal', self::PRINCIPAL_TITLE);
		$this->renameExactPostTitle(self::COOKER_ID, 'Cooks', self::COOKER_TITLE);
		$this->ensurePostByTitle(self::CUSTOMER_CARE_TITLE);
		$this->ensurePostByTitle(self::DHT_DISCIPLINE_TITLE);
		$this->ensurePostByTitle(self::DHT_ACADEMICS_TITLE);
		$wanted = [
			self::HEAD_TEACHER_ID => 'Head Teacher',
			self::DEPUTY_HEAD_TEACHER_ID => 'Deputy Head Teacher',
			self::PHOTO_GRAPHER_ID => 'Photo Grapher',
			self::CHIEF_ACCOUNTANT_ID => 'Chief Accountant',
			self::DIRECTOR_ID => 'Director',
			self::DEPUTY_DIRECTOR_ID => 'Deputy Director',
		];
		foreach ($wanted as $id => $title) {
			$byTitle = $db->table('posts')->where('title', $title)->get(1)->getRowArray();
			if ($byTitle) {
				if ((int) ($byTitle['status'] ?? 0) !== 1) {
					$db->table('posts')->where('id', $byTitle['id'])->update(['status' => 1]);
				}
				$this->ensureDefaultRestrictedClearance((int) $byTitle['id'], $title);
				continue;
			}
			$byId = $db->table('posts')->where('id', $id)->get(1)->getRowArray();
			if ($byId) {
				try {
					$db->table('posts')->insert(['title' => $title, 'status' => 1]);
				} catch (\Throwable $e) {
					// unique title or similar — ignore
				}
				$created = $db->table('posts')->where('title', $title)->get(1)->getRowArray();
				if ($created) {
					$this->ensureDefaultRestrictedClearance((int) $created['id'], $title);
				}
				continue;
			}
			try {
				$db->table('posts')->insert(['id' => $id, 'title' => $title, 'status' => 1]);
			} catch (\Throwable $e) {
				// ignore
			}
			$this->ensureDefaultRestrictedClearance($id, $title);
		}
	}

	/** Rename a built-in post when the current title still matches the legacy label. */
	private function renameExactPostTitle(int $id, string $from, string $to): void
	{
		if ($id < 1 || $from === '' || $to === '' || strcasecmp($from, $to) === 0) {
			return;
		}
		try {
			$db = \Config\Database::connect();
			$clash = $db->table('posts')->where('title', $to)->where('id !=', $id)->get(1)->getRowArray();
			if ($clash) {
				return;
			}
			$row = $db->table('posts')->where('id', $id)->get(1)->getRowArray();
			if ($row && strcasecmp(trim((string) ($row['title'] ?? '')), $from) === 0) {
				$db->table('posts')->where('id', $id)->update(['title' => $to, 'status' => 1]);
				return;
			}
			$db->table('posts')->where('title', $from)->update(['title' => $to]);
		} catch (\Throwable $e) {
			// ignore rename failures; existing title still works
		}
	}

	/** Seed restricted system-post menu access only if no custom override exists yet. */
	private function ensureDefaultRestrictedClearance(int $postId, string $title): void
	{
		if ($postId < 1 || strtolower(trim($title)) !== 'photo grapher') {
			return;
		}
		try {
			$clearanceMdl = new PostMenuClearanceModel();
			$clearanceMdl->ensureSchema();
			if (!$clearanceMdl->hasCustomRow($postId)) {
				$clearanceMdl->saveForPost($postId, ['student-photo'], 0);
			}
		} catch (\Throwable $e) {
			// ignore permission bootstrap failures; post creation still matters
		}
	}

	/** Create a regular operational post by title if it is missing. */
	private function ensurePostByTitle(string $title): void
	{
		$title = trim($title);
		if ($title === '') {
			return;
		}
		try {
			$db = \Config\Database::connect();
			$existing = $db->table('posts')->where('title', $title)->get(1)->getRowArray();
			if ($existing) {
				if ((int) ($existing['status'] ?? 0) !== 1) {
					$db->table('posts')->where('id', $existing['id'])->update(['status' => 1]);
				}
				return;
			}
			$db->table('posts')->insert(['title' => $title, 'status' => 1]);
		} catch (\Throwable $e) {
			// ignore duplicate title races
		}
	}

}
