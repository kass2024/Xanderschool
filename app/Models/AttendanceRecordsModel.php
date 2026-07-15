<?php namespace App\Models;
use CodeIgniter\Model;

class AttendanceRecordsModel extends Model{
	protected $table = "attendance_records";
	protected $allowedFields = ["user_id","user_type","time_in","time_out","school_id","shift_id"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';

}
