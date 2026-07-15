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

}
