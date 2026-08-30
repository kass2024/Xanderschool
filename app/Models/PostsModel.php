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

	/**
	 * Ensure Head Teacher and Deputy Head Teacher exist in `posts`.
	 * Safe to call on every staff-create / privilege load.
	 */
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
		];
		foreach ($wanted as $id => $title) {
			$byTitle = $db->table('posts')->where('title', $title)->get(1)->getRowArray();
			if ($byTitle) {
				if ((int) ($byTitle['status'] ?? 0) !== 1) {
					$db->table('posts')->where('id', $byTitle['id'])->update(['status' => 1]);
				}
				continue;
			}
			$byId = $db->table('posts')->where('id', $id)->get(1)->getRowArray();
			if ($byId) {
				try {
					$db->table('posts')->insert(['title' => $title, 'status' => 1]);
				} catch (\Throwable $e) {
					// unique title or similar — ignore
				}
				continue;
			}
			try {
				$db->table('posts')->insert(['id' => $id, 'title' => $title, 'status' => 1]);
			} catch (\Throwable $e) {
				// ignore
			}
		}
	}

}
