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

        // parent visiting visitors (copied to student_visitors on approval)
        'visitor1_names',
        'visitor1_phone',
        'visitor1_relationship',
        'visitor2_names',
        'visitor2_phone',
        'visitor2_relationship',
        'email',

        // same fields as Students.xlsx mass-upload template
        'nationality',
        'religion',
        'medical_status',
        'cell_id',
        'village_id',
        'class_id',
        'father',
        'ft_phone',
        'father_nid',
        'mother',
        'mt_phone',
        'mother_nid',
        'guardian',
        'gd_phone',
        'guardian_nid',

        'processed_by',
        'processed_at',
        'processed_action',
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

    /** @var bool */
    private static $visitorColumnsReady = false;

    /**
     * Ensure applications table has visitor + student profile columns from Excel template.
     */
    public function ensureVisitorColumns(): void
    {
        if (self::$visitorColumnsReady) {
            return;
        }
        $db = \Config\Database::connect();
        $columns = [
            'visitor1_names' => 'VARCHAR(150) NULL DEFAULT NULL',
            'visitor1_phone' => 'VARCHAR(50) NULL DEFAULT NULL',
            'visitor1_relationship' => 'VARCHAR(80) NULL DEFAULT NULL',
            'visitor2_names' => 'VARCHAR(150) NULL DEFAULT NULL',
            'visitor2_phone' => 'VARCHAR(50) NULL DEFAULT NULL',
            'visitor2_relationship' => 'VARCHAR(80) NULL DEFAULT NULL',
            'email' => 'VARCHAR(120) NULL DEFAULT NULL',
            'nationality' => 'VARCHAR(80) NULL DEFAULT NULL',
            'religion' => 'VARCHAR(80) NULL DEFAULT NULL',
            'medical_status' => 'VARCHAR(255) NULL DEFAULT \'Normal\'',
            'cell_id' => 'INT NULL DEFAULT NULL',
            'village_id' => 'INT NULL DEFAULT NULL',
            'class_id' => 'INT NULL DEFAULT NULL',
            'father' => 'VARCHAR(150) NULL DEFAULT NULL',
            'ft_phone' => 'VARCHAR(50) NULL DEFAULT NULL',
            'father_nid' => 'VARCHAR(32) NULL DEFAULT NULL',
            'mother' => 'VARCHAR(150) NULL DEFAULT NULL',
            'mt_phone' => 'VARCHAR(50) NULL DEFAULT NULL',
            'mother_nid' => 'VARCHAR(32) NULL DEFAULT NULL',
            'guardian' => 'VARCHAR(150) NULL DEFAULT NULL',
            'gd_phone' => 'VARCHAR(50) NULL DEFAULT NULL',
            'guardian_nid' => 'VARCHAR(32) NULL DEFAULT NULL',
        ];
        foreach ($columns as $name => $def) {
            if (!$db->fieldExists($name, 'applications')) {
                $db->query("ALTER TABLE `applications` ADD COLUMN `{$name}` {$def}");
            }
        }
        $this->ensureAuditColumns();
        self::$visitorColumnsReady = true;
    }

    public function ensureAuditColumns(): void
    {
        $db = \Config\Database::connect();
        $audit = [
            'processed_by' => 'INT NULL DEFAULT NULL',
            'processed_at' => 'DATETIME NULL DEFAULT NULL',
            'processed_action' => 'VARCHAR(32) NULL DEFAULT NULL',
        ];
        foreach ($audit as $name => $def) {
            if (!$db->fieldExists($name, 'applications')) {
                $db->query("ALTER TABLE `applications` ADD COLUMN `{$name}` {$def}");
            }
        }
    }
}
