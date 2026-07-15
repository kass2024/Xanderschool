<?php namespace App\Models;
use CodeIgniter\Model;

class CourseAttendanceModel extends Model{
	protected $table = "course_attendance";
	protected $allowedFields = ["teacher_id","course_id","class_id"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';

}
