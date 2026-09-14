<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetLocationModel extends Model
{
	protected $table = 'asset_locations';
	protected $primaryKey = 'id';
	protected $returnType = 'array';
	protected $useTimestamps = true;
	protected $allowedFields = [
		'school_id', 'parent_location_id', 'location_code', 'name', 'location_type',
		'description', 'campus', 'building', 'floor', 'room', 'capacity', 'status',
		'responsible_staff_id', 'created_by', 'updated_by', 'archived_at',
	];

	public function ensureSchema()
	{
		(new AssetSchemaModel())->ensureSchema();
	}

	/**
	 * @param int $schoolId
	 * @param bool $includeArchived
	 * @return array
	 */
	public function listForSchool($schoolId, $includeArchived = false)
	{
		$this->ensureSchema();
		$builder = $this->select('asset_locations.*, CONCAT(s.fname, " ", s.lname) AS custodian_name')
			->join('staffs s', 's.id = asset_locations.responsible_staff_id', 'left')
			->where('asset_locations.school_id', (int) $schoolId)
			->orderBy('asset_locations.name', 'ASC');
		if (!$includeArchived) {
			$builder->where('asset_locations.status', 1);
		}
		return $builder->findAll();
	}

	/**
	 * Build nested tree from flat list.
	 *
	 * @param array $rows
	 * @return array
	 */
	public function buildTree(array $rows)
	{
		$byParent = [];
		foreach ($rows as $row) {
			$pid = $row['parent_location_id'] ? (int) $row['parent_location_id'] : 0;
			if (!isset($byParent[$pid])) {
				$byParent[$pid] = [];
			}
			$byParent[$pid][] = $row;
		}

		$walk = function ($parentId) use (&$walk, $byParent) {
			$nodes = [];
			if (!isset($byParent[$parentId])) {
				return $nodes;
			}
			foreach ($byParent[$parentId] as $row) {
				$row['children'] = $walk((int) $row['id']);
				$nodes[] = $row;
			}
			return $nodes;
		};

		return $walk(0);
	}

	/**
	 * Count active assets at location (direct).
	 *
	 * @param int $schoolId
	 * @param int $locationId
	 * @return array{count:int,value:float}
	 */
	public function findOrCreateByName($schoolId, $name, $actorId = null)
	{
		$this->ensureSchema();
		$name = trim((string) $name);
		if ($name === '') {
			return null;
		}
		$schoolId = (int) $schoolId;
		$existing = $this->where('school_id', $schoolId)
			->where('name', $name)
			->where('status', 1)
			->first();
		if ($existing) {
			return $existing;
		}
		$slug = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $name));
		$slug = substr(trim($slug, '_'), 0, 36);
		if ($slug === '') {
			$slug = 'LOC' . time();
		}
		$code = $slug;
		$n = 1;
		while ($this->where('school_id', $schoolId)->where('location_code', $code)->first()) {
			$code = substr($slug, 0, 32) . '_' . $n;
			$n++;
		}
		$this->insert([
			'school_id' => $schoolId,
			'location_code' => $code,
			'name' => $name,
			'status' => 1,
			'created_by' => $actorId,
			'updated_by' => $actorId,
		]);
		return $this->find($this->getInsertID());
	}

	public function assetStats($schoolId, $locationId)
	{
		$db = \Config\Database::connect();
		$row = $db->table('assets')
			->select('COUNT(*) AS cnt, COALESCE(SUM(total_acquisition_cost),0) AS val')
			->where('school_id', (int) $schoolId)
			->where('location_id', (int) $locationId)
			->where('archived_at', null)
			->get()
			->getRowArray();
		return [
			'count' => (int) ($row['cnt'] ?? 0),
			'value' => (float) ($row['val'] ?? 0),
		];
	}
}
