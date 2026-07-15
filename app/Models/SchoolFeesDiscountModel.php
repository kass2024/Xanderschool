<?php
namespace App\Models;

use CodeIgniter\Model;

class SchoolFeesDiscountModel extends Model
{
	protected $table="school_fees_discount";
	protected $allowedFields = ["student","feesId","type","amount","comment","operator","status"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField  = 'created_at';
	protected $updatedField  = 'updated_at';

}
