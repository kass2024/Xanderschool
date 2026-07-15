<?php
namespace App\Models;

use CodeIgniter\Model;

class ApplicationTransactionModel extends Model
{
	protected $table         = 'application_transactions';
	protected $allowedFields = [
		'applicationId',
		'transaction_id',
		'amount',
		'momo_ref',
		'status',
		'response_body'
	];
	protected $useTimestamps = true;
}
