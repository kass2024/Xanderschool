<?php namespace App\Models;
use CodeIgniter\Model;

class BusModel extends Model{
	protected $table = "bus";
	protected $allowedFields = ["school_id","plate","car_maker","car_model","car_year","places","driver","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
