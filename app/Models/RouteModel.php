<?php namespace App\Models;
use CodeIgniter\Model;

class RouteModel extends Model{
	protected $table = "routes";
	protected $allowedFields = ["school_id","title","details","price","created_by","updated_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
