<?php namespace App\Models;
use CodeIgniter\Model;

class PaymentModel extends Model{
	protected $table = "payment_transactions";
	protected $allowedFields = ["student_id","amount","type","source","balance","txn_fee","txn_Id","reference_id"
		,"status","created_by","extra_options","tx_error"];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';

}
