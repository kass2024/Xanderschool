<?php
namespace App\Models;

use CodeIgniter\Model;

class ApplicationSettingsModel extends Model
{
	protected $table="application_settings";
	protected $allowedFields = [
		"school_id",
		"start_date",
		"end_date",
		"requirement_document",
		"registration_fees",
		"fee_mode",
		"babyeyi_required",
		"operator",
		"momo_pay_code",
		"momo_pay_name",
	];
	protected $useTimestamps = true;
	protected $primaryKey = 'id';

	public function ensureMomoPayColumns(): void
	{
		static $ready = false;
		if ($ready) {
			return;
		}
		$db = $this->db;
		if ($db->tableExists($this->table) && !$db->fieldExists('momo_pay_code', $this->table)) {
			try {
				$db->query("ALTER TABLE `application_settings` ADD COLUMN `momo_pay_code` VARCHAR(32) NOT NULL DEFAULT '' AFTER `operator`");
			} catch (\Throwable $e) {
			}
		}
		if ($db->tableExists($this->table) && !$db->fieldExists('momo_pay_name', $this->table)) {
			try {
				$db->query("ALTER TABLE `application_settings` ADD COLUMN `momo_pay_name` VARCHAR(120) NOT NULL DEFAULT '' AFTER `momo_pay_code`");
			} catch (\Throwable $e) {
			}
		}
		$ready = true;
	}

	/**
	 * Default MoMo Pay merchant shown on the public registration form.
	 * @return array{code:string,name:string}
	 */
	public function defaultMomoPayForSchool(int $schoolId): array
	{
		$wisdomId = 27;
		try {
			$found = (int) \App\Libraries\AttendanceScanService::wisdomSchoolId();
			if ($found > 0) {
				$wisdomId = $found;
			}
		} catch (\Throwable $e) {
		}
		if ($schoolId === $wisdomId || $schoolId === 27) {
			return ['code' => '059010', 'name' => 'WISDOM SCHOOL'];
		}
		return ['code' => '', 'name' => ''];
	}

	/**
	 * Merchant code + names to highlight on the application form / success page.
	 * @return array{code:string,name:string}
	 */
	public function momoPayForSchool(int $schoolId): array
	{
		if ($schoolId < 1) {
			return ['code' => '', 'name' => ''];
		}
		$this->ensureMomoPayColumns();
		$row = $this->where('school_id', $schoolId)->orderBy('id', 'desc')->first();
		$code = trim((string) ($row['momo_pay_code'] ?? ''));
		$name = trim((string) ($row['momo_pay_name'] ?? ''));
		$defaults = $this->defaultMomoPayForSchool($schoolId);
		if ($code === '' && $defaults['code'] !== '') {
			$code = $defaults['code'];
			$name = $name !== '' ? $name : $defaults['name'];
			if (!empty($row['id'])) {
				try {
					$this->update((int) $row['id'], [
						'momo_pay_code' => $code,
						'momo_pay_name' => $name,
					]);
				} catch (\Throwable $e) {
				}
			}
		}
		return ['code' => $code, 'name' => $name];
	}

	/**
	 * Get or create online-registration settings for a school.
	 * @return array
	 */
	public function forSchool(int $schoolId): array
	{
		$this->ensureMomoPayColumns();
		$row = $this->where('school_id', $schoolId)->orderBy('id', 'desc')->first();
		if ($row) {
			$pay = $this->momoPayForSchool($schoolId);
			$row['momo_pay_code'] = $pay['code'];
			$row['momo_pay_name'] = $pay['name'];
			return $row;
		}
		$today = date('Y-m-d');
		$defaults = $this->defaultMomoPayForSchool($schoolId);
		$id = $this->insert([
			'school_id' => $schoolId,
			'start_date' => $today,
			'end_date' => date('Y-m-d', strtotime('+1 year')),
			'requirement_document' => '',
			'registration_fees' => 10000,
			'fee_mode' => 'flat',
			'babyeyi_required' => 1,
			'operator' => 0,
			'momo_pay_code' => $defaults['code'],
			'momo_pay_name' => $defaults['name'],
		]);
		$created = $this->find($id);
		if ($created) {
			$created['momo_pay_code'] = $created['momo_pay_code'] ?: $defaults['code'];
			$created['momo_pay_name'] = $created['momo_pay_name'] ?: $defaults['name'];
			return $created;
		}
		return [
			'id' => (int) $id,
			'school_id' => $schoolId,
			'start_date' => $today,
			'end_date' => date('Y-m-d', strtotime('+1 year')),
			'requirement_document' => '',
			'registration_fees' => 10000,
			'fee_mode' => 'flat',
			'babyeyi_required' => 1,
			'operator' => 0,
			'momo_pay_code' => $defaults['code'],
			'momo_pay_name' => $defaults['name'],
		];
	}
}
