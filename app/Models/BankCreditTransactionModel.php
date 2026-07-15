<?php namespace App\Models;
use CodeIgniter\Model;

class BankCreditTransactionModel extends Model{
	protected $table = "bank_credit_transactions";
	protected $allowedFields = ["wallet_id","amount","school_id","refNo","status","errorMessage","retryCount"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';

}
