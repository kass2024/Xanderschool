<?php
namespace App\Models;

use CodeIgniter\Model;

class BookCategoryModel extends Model
{
	protected $table="bookcategory";
	protected $allowedFields = ["school_id","title","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';
}
