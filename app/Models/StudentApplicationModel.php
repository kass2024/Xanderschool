<?php
namespace App\Models;

use CodeIgniter\Model;

class StudentApplicationModel extends Model
{
    protected $table            = 'applications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    // Return as array so it's easy to merge/patch fields (works fine with ->save())
    protected $returnType       = 'array';

    /**
     * Columns allowed to be set via insert()/update()/save().
     * Added: documents, report1, report2, report3
     */
    protected $allowedFields = [
        'schoolId',
        'settingsId',
        'fname',
        'lname',
        'gender',
        'phoneNumber',
        'parentType',
        'parentPhoneNumber',
        'parentNames',
        'dateOfBirth',
        'level',
        'studyingMode',
        'faculty_id',
        'department_id',
        'code',
        'status',
        'admitted',

        // uploads
        'documents',
        'report1',
        'report2',
        'report3',
    ];

    // Your table has created_at (timestamp default current_timestamp) and updated_at (datetime)
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // (Optional) soft deletes not used by your table
    protected $useSoftDeletes = false;

    // (Optional) validation rules can be added here if you later want to enforce constraints
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = true;
}
