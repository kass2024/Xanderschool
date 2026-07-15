<?php namespace App\Models;
use CodeIgniter\Model;

class TestModel extends Model{
	protected $table = "test";
	protected $allowedFields = ["pid","created_at"];
	protected $useTimestamps = false;
}
