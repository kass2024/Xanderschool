<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassesModel extends Model
{
	protected $table = "classes";
	protected $allowedFields = ["school_id", "level", "department", "mentor", "title", "created_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField = 'created_at';
	protected $updatedField = 'updated_at';

	public function get_classes()
	{
		$data = $this->select("classes.id,if(classes.title='','-----',classes.title) as title,d.title as department_name,d.id as department_id,d.code,l.title as level_name
			,f.type,f.abbrev as faculty_code,f.id as facul_id,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf,
		(select count(cc.id) from class_records cc where cc.class=classes.id and year=" . date('Y') . ") as students,
		(select count(c1.id) from course_records c1 where c1.class=classes.id and year=" . date('Y') . ") as courses
		")
			->join("departments d", "d.id=classes.department")
			->join("levels l", "l.id=classes.level")
			->join("faculty f", "f.id=d.faculty_id")
			->join("staffs s", "s.id=classes.mentor", "LEFT")
			->where("classes.school_id", $_SESSION["soma_school_id"])
			->groupBy("classes.id")
			->get()->getResultArray();
		return $data;
	}

	public function get_class_name($val = null)
	{
		$builder = $this->select('concat(l.title," ",d.code," ",classes.title) as classe')
			->join('departments d', 'd.id=classes.department')
			->join('levels l', 'l.id=classes.level')
			->where('classes.id', $val);
		$data = $builder->get();
		if ($data->getRowArray() == null)
			return "";
		return $data->getRowArray()["classe"];
	}

	public function get_teacher_classes($id, $school_id)
	{

		$builder = $this->select("classes.id,classes.title,d.title as department_name,d.code as dept_code,l.title as level_name,f.type,f.abbrev as faculty_code, '' AS subjects, '' AS students")
			->join("departments d", "d.id=classes.department")
			->join("levels l", "l.id=classes.level")
			->join("faculty f", "f.id=d.faculty_id")
//			->join("class_records r", "r.class=classes.id")
			->where("classes.school_id", $school_id)
			;
		if ($id != null) {
			$builder->join("course_records cr", "cr.class=classes.id")
				->join("staffs s", "s.id=cr.lecturer")
				->join("active_term at", "at.academic_year=cr.year and at.school_id=s.school_id")
				->where("s.id", $id);
		}
		$data = $builder->groupBy("classes.id")->get()->getResultArray();
		return $data;
	}
}
