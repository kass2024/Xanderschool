<?php
namespace App\Models;

use CodeIgniter\Model;

class DocumentsModel extends Model
{
	protected $table         = 'documents';
	protected $allowedFields = [
		'id',
		'applicationId',
		'fileName',
		'documentName',
	];
	protected $primaryKey    = 'id';
	protected $createdField  = 'created_at';
}
