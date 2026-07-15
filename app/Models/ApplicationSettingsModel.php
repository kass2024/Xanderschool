<?php
namespace App\Models;

use CodeIgniter\Model;

class ApplicationSettingsModel extends Model
{
	protected $table="application_settings";
	protected $allowedFields = ["school_id","start_date","end_date","requirement_document","registration_fees","operator"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';

}
