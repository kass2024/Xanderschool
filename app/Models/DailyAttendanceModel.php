<?php namespace App\Models;
use CodeIgniter\Model;

class DailyAttendanceModel extends Model{
	protected $table = "daily_attendance";
	protected $allowedFields = ["student_id","datee","active_term"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';

}
