<?php namespace App\Models;
use CodeIgniter\Model;
class CourseAttendanceRecordsModel extends Model{
	protected $table = "course_attendance_records";
	protected $allowedFields = ["attendance_id","student_id"];
	protected $useTimestamps = false;
	protected $primaryKey = 'id';

}
