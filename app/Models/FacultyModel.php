<?php
namespace App\Models;

use CodeIgniter\Model;

class FacultyModel extends Model
{
	protected $table = "faculty";
	protected $allowedFields = ["title", "abbrev", "type", "status"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';

	public const TYPE_TVET = 1;
	public const TYPE_REB = 2;
	public const TYPE_SPECIAL = 3;
	public const SPECIAL_TITLE = 'Nursing ANP';
	public const SPECIAL_CODE = 'ANP';

	/**
	 * Ensure the Special educational path exists with faculty + department both named Nursing ANP.
	 *
	 * @return array{faculty: array<string,mixed>, department: array<string,mixed>}
	 */
	public function ensureSpecialNursingAnp(): array
	{
		$db = $this->db;
		$fac = $db->table($this->table)
			->where('type', self::TYPE_SPECIAL)
			->groupStart()
				->where('title', self::SPECIAL_TITLE)
				->orWhere('abbrev', self::SPECIAL_CODE)
			->groupEnd()
			->get(1)->getRowArray();

		if (!$fac) {
			$db->table($this->table)->insert([
				'title' => self::SPECIAL_TITLE,
				'abbrev' => self::SPECIAL_CODE,
				'type' => self::TYPE_SPECIAL,
				'status' => 1,
			]);
			$facId = (int) $db->insertID();
			$fac = $db->table($this->table)->where('id', $facId)->get(1)->getRowArray();
		} else {
			$facId = (int) $fac['id'];
			$needs = [];
			if (strcasecmp((string) ($fac['title'] ?? ''), self::SPECIAL_TITLE) !== 0) {
				$needs['title'] = self::SPECIAL_TITLE;
			}
			if (strcasecmp((string) ($fac['abbrev'] ?? ''), self::SPECIAL_CODE) !== 0) {
				$needs['abbrev'] = self::SPECIAL_CODE;
			}
			if ((int) ($fac['type'] ?? 0) !== self::TYPE_SPECIAL) {
				$needs['type'] = self::TYPE_SPECIAL;
			}
			if ($needs) {
				$db->table($this->table)->where('id', $facId)->update($needs);
				$fac = $db->table($this->table)->where('id', $facId)->get(1)->getRowArray();
			}
		}

		$deptTable = $db->table('departments');
		$dept = $deptTable->where('faculty_id', $facId)
			->groupStart()
				->where('title', self::SPECIAL_TITLE)
				->orWhere('code', self::SPECIAL_CODE)
			->groupEnd()
			->get(1)->getRowArray();

		if (!$dept) {
			$now = date('Y-m-d H:i:s');
			$deptTable->insert([
				'title' => self::SPECIAL_TITLE,
				'code' => self::SPECIAL_CODE,
				'faculty_id' => $facId,
				'created_by' => 0,
				'updated_by' => 0,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			$deptId = (int) $db->insertID();
			$dept = $db->table('departments')->where('id', $deptId)->get(1)->getRowArray();
		}

		$levelCount = $db->table('levels')->where('faculty_id', $facId)->where('type', self::TYPE_SPECIAL)->countAllResults();
		if ($levelCount === 0) {
			foreach (['Year 1', 'Year 2', 'Year 3'] as $title) {
				$db->table('levels')->insert([
					'title' => $title,
					'type' => self::TYPE_SPECIAL,
					'faculty_id' => $facId,
					'status' => 1,
				]);
			}
		}

		return [
			'faculty' => $fac ?: [],
			'department' => $dept ?: [],
		];
	}
}
