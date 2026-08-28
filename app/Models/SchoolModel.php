<?php namespace App\Models;

use CodeIgniter\Model;

class SchoolModel extends Model{
	protected $table         = 'schools';
	protected $allowedFields = [
		'name',
		'acronym',
		'slogan',
		'logo',
		'country',
		'address',
		'phone',
		'email',
		'head_master',
		'head_master_gender',
		'headmaster_signature',
		'matron_signature',        // ✅ added
		'patron_signature',        // ✅ added
		'discipline_signature',    // ✅ added
		'card_background',
		'sf_card_background',
		'sf_card_template',
		'sf_card_orientation',
		'sf_card_bg_mode',
		'sf_card_layout',
		'card_design',
		'card_orientation',
		'card_bg_mode',
		'card_template',
		'card_layout',
		'website',
		'pobox',
		'package',
		'extra_sms',
		'active_term',
		'status',
		'discipline_max',
		'created_by',
		'header_text_1',
		'header_text_2',
		'header_color',
		'main_color',
		'footer_color',
		'paint_color',
		'capitalize',
		'sf_header_text_1',
		'sf_header_text_2',
		'sf_header_color',
		'sf_main_color',
		'sf_footer_color',
		'sf_paint_color',
		'sf_capitalize',
		'in_time',
		'leave_time',
		'tolerance',
		'bank_account',
		'bank_name',
		'mtn_momo_phone',
		'pocket_money_phone',
		'created_by',
	];
	protected $useTimestamps = true;

	public function getSchool($val = null)
	{
		$data = $this->db->table($this->table)
			->select('schools.*,pk.title as package_title,
			at.sms_usage,pk.sms_limit,at.use_period,at.locked_periods,at.term')
			->join('active_term as at', 'at.id=schools.active_term', 'left')
			->join('packages as pk', 'pk.id=schools.package');
		if ($val !== null)
		{
			$data->where($val);
		}
		return $data->get();
	}
}
