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

	/** Head Teacher / Deputy Head Teacher — used on staff creation privilege list. */
	public const HEAD_TEACHER_ID = 25;
	public const DEPUTY_HEAD_TEACHER_ID = 26;
	public const PHOTO_GRAPHER_ID = 27;

	/** Ensure built-in system posts exist and restricted ones get a starter clearance row. */
	public function ensureLeadershipPosts(): void
	{
		static $ready = false;
		if ($ready) {
			return;
		}
		$ready = true;
		$db = \Config\Database::connect();
		$wanted = [
			self::HEAD_TEACHER_ID => 'Head Teacher',
			self::DEPUTY_HEAD_TEACHER_ID => 'Deputy Head Teacher',
			self::PHOTO_GRAPHER_ID => 'Photo Grapher',
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

}
