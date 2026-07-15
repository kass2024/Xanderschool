<?php
namespace App\Models;

use CodeIgniter\Model;

class BookRecordModel extends Model
{
	protected $table="book_records";
	protected $allowedFields = ["book_id","school_id","type","typeId","academic_year","term","borrow_date","return_due_date","return_date","status","created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';
}
