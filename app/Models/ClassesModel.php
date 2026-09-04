<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassesModel extends Model
{
	protected $table = "classes";
	protected $allowedFields = ["school_id", "level", "department", "mentor", "title", "created_by", "updated_by"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';
	protected $createdField = 'created_at';
	protected $updatedField = 'updated_at';

	public function get_classes()
	{
		$year = (int) ($_SESSION['soma_academics_year'] ?? 0);
		if ($year <= 0) {
			$year = (int) date('Y');
		}
		// Pedagogical stage: Nursery → Primary → O'Level → A'Level → Special → TVET/RTB
		$stageOrder = "(CASE
			WHEN f.type = 2 AND (
				LOWER(IFNULL(f.title,'')) LIKE '%nursery%'
				OR LOWER(IFNULL(d.title,'')) LIKE '%nursery%'
				OR LOWER(TRIM(IFNULL(l.title,''))) REGEXP '^(n[1-3]|baby)'
			) THEN 1
			WHEN f.type = 2 AND (
				LOWER(IFNULL(f.title,'')) LIKE '%primary%'
				OR LOWER(TRIM(IFNULL(l.title,''))) REGEXP '^p[1-6]$'
			) THEN 2
			WHEN f.type = 2 AND (
				LOWER(IFNULL(f.title,'')) LIKE '%ordinary%'
				OR LOWER(IFNULL(f.abbrev,'')) LIKE '%o''%'
				OR LOWER(TRIM(IFNULL(l.title,''))) REGEXP '^s[1-3]$'
			) THEN 3
			WHEN f.type = 2 AND (
				LOWER(IFNULL(f.title,'')) LIKE '%advanced%'
				OR LOWER(IFNULL(f.abbrev,'')) LIKE '%a''%'
				OR LOWER(TRIM(IFNULL(l.title,''))) REGEXP '^s[4-6]$'
			) THEN 4
			WHEN f.type = 2 THEN 5
			WHEN f.type = 3 THEN 6
			WHEN f.type = 1 THEN 7
			ELSE 8
		END)";
		$levelOrder = "(CASE
			WHEN LOWER(TRIM(IFNULL(l.title,''))) IN ('baby class','baby') THEN 0
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'n1' THEN 1
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'n2' THEN 2
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'n3' THEN 3
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'p1' THEN 1
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'p2' THEN 2
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'p3' THEN 3
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'p4' THEN 4
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'p5' THEN 5
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 'p6' THEN 6
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 's1' THEN 1
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 's2' THEN 2
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 's3' THEN 3
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 's4' THEN 4
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 's5' THEN 5
			WHEN LOWER(TRIM(IFNULL(l.title,''))) = 's6' THEN 6
			WHEN LOWER(TRIM(IFNULL(l.title,''))) REGEXP 'level[[:space:]]*1|^l[[:space:]]*1$' THEN 1
			WHEN LOWER(TRIM(IFNULL(l.title,''))) REGEXP 'level[[:space:]]*2|^l[[:space:]]*2$' THEN 2
			WHEN LOWER(TRIM(IFNULL(l.title,''))) REGEXP 'level[[:space:]]*3|^l[[:space:]]*3$' THEN 3
			WHEN LOWER(TRIM(IFNULL(l.title,''))) REGEXP 'level[[:space:]]*4|^l[[:space:]]*4$' THEN 4
			WHEN LOWER(TRIM(IFNULL(l.title,''))) REGEXP 'level[[:space:]]*5|^l[[:space:]]*5$' THEN 5
			ELSE 50
		END)";
		$titleOrder = "(CASE
			WHEN TRIM(IFNULL(classes.title,'')) = '' OR TRIM(classes.title) = '-----' THEN 0
			WHEN UPPER(TRIM(classes.title)) = 'A' THEN 1
			WHEN UPPER(TRIM(classes.title)) = 'B' THEN 2
			WHEN UPPER(TRIM(classes.title)) = 'C' THEN 3
			WHEN LOWER(TRIM(classes.title)) = 'holiday' THEN 90
			ELSE 40
		END)";
		$data = $this->select("classes.id,if(classes.title='','-----',classes.title) as title,d.title as department_name,d.id as department_id,d.code,l.title as level_name
			,f.type,f.abbrev as faculty_code,f.id as facul_id,concat(s.fname,' ',s.lname) as mentor_name,s.id as idstf,
		(select count(cc.id) from class_records cc where cc.class=classes.id and cc.year=" . $year . ") as students,
		(select count(c1.id) from course_records c1 where c1.class=classes.id and c1.year=" . $year . ") as courses
		")
			->join("departments d", "d.id=classes.department", "LEFT")
			->join("levels l", "l.id=classes.level", "LEFT")
			->join("faculty f", "f.id=d.faculty_id", "LEFT")
			->join("staffs s", "s.id=classes.mentor", "LEFT")
			->where("classes.school_id", $_SESSION["soma_school_id"])
			->groupBy("classes.id")
			->orderBy($stageOrder, 'ASC', false)
			->orderBy($levelOrder, 'ASC', false)
			->orderBy('d.title', 'ASC')
			->orderBy('d.code', 'ASC')
			->orderBy($titleOrder, 'ASC', false)
			->orderBy('classes.title', 'ASC')
			->orderBy('classes.id', 'ASC')
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
			->join("departments d", "d.id=classes.department", "LEFT")
			->join("levels l", "l.id=classes.level", "LEFT")
			->join("faculty f", "f.id=d.faculty_id", "LEFT")
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
