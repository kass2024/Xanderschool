<?php namespace App\Models;

use CodeIgniter\Model;

class BoardingAttendanceModel extends Model
{
    protected $table         = 'boarding_attendance';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['student_id', 'datee', 'clock_time', 'active_term'];
    protected $useTimestamps = false;

    /**
     * Insert a new clock-in record for a student
     */
    public function clockIn($student_id, $active_term)
    {
        $data = [
            'student_id'  => $student_id,
            'datee'       => date('Y-m-d'),
            'clock_time'  => date('Y-m-d H:i:s'),
            'active_term' => $active_term,
        ];

        return $this->insert($data, true); // true = return inserted ID
    }

    /**
     * Get all clock-ins for a student on a specific date
     */
    public function getClockInsByDate($student_id, $date = null)
    {
        $date = $date ?: date('Y-m-d');

        return $this->where('student_id', $student_id)
                    ->where('datee', $date)
                    ->orderBy('clock_time', 'ASC')
                    ->findAll();
    }

    /**
     * Count clock-ins for a student on a specific date
     */
    public function getCountByDate($student_id, $date = null)
    {
        $date = $date ?: date('Y-m-d');

        return $this->where('student_id', $student_id)
                    ->where('datee', $date)
                    ->countAllResults();
    }

    /**
     * Get full attendance history for a student
     */
    public function getAttendanceHistory($student_id)
    {
        return $this->where('student_id', $student_id)
                    ->orderBy('datee', 'DESC')
                    ->orderBy('clock_time', 'ASC')
                    ->findAll();
    }
}
