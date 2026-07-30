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
