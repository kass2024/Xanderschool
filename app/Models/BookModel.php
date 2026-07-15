<?php
namespace App\Models;

use CodeIgniter\Model;

class BookModel extends Model
{
	protected $table="books";
		protected $allowedFields = ["school_id","title","author","category","quantity","status","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';
}
