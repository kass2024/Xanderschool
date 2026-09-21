<?php

namespace App\Libraries;

use App\Models\ExtraFeesModel;
use App\Models\FeesRecordModel;
use App\Models\SchoolFeesModel;
use App\Models\SmsModel;
use App\Models\SmsRecipientModel;
use App\Models\StudentModel;

/**
 * Installment payments: promised dates, dashboard due list, SMS reminders.
 */
class FeeInstallmentService
{
	/**
	 * Remaining balance for one fee line (school or extra).
	 */
	public static function lineBalance(int $studentId, int $feesType, int $feesId, int $studyingMode = 1): float
	{
		$paid = (float) (new FeesRecordModel())
			->selectSum('amount', 'paid')
			->where('student_id', $studentId)
			->where('fees_type', $feesType)
			->where('fees_id', $feesId)
			->where('status', 1)
			->get()->getRowArray()['paid'] ?? 0;

		if ($feesType === 0) {
			$row = (new SchoolFeesModel())->select('school_fees.amount,school_fees.amount_boarding,school_fees.amount_day,coalesce(fd.amount,0) as discount')
				->join("(select sum(amount) as amount,feesId from school_fees_discount where student=$studentId group by feesId) fd", 'fd.feesId=school_fees.id', 'LEFT')
				->where('school_fees.id', $feesId)
				->get()->getRowArray();
			$expected = $row ? SchoolFeesModel::expectedForStudent($row, $studyingMode, (float) ($row['discount'] ?? 0)) : 0.0;
		} else {
			$row = (new ExtraFeesModel())->find($feesId);
			if (!$row) {
				return 0.0;
			}
			$expected = ((int) ($row['type'] ?? 0) === 1)
				? (float) ($row['amount'] ?? 0)
				: ExtraFeesModel::expectedForMode($row, $studyingMode);
		}

		return max(0.0, $expected - $paid);
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function dueInstallments(int $schoolId, int $academicYear, int $term): array
	{
		$db = \Config\Database::connect();
		$today = date('Y-m-d');
		$sql = "
			SELECT fr.id, fr.student_id, fr.fees_type, fr.fees_id, fr.amount AS last_paid,
				fr.promised_date, fr.refNo,
				CONCAT(st.fname,' ',st.lname) AS student,
				st.regno, st.ft_phone, st.mt_phone, st.gd_phone,
				l.title AS level_name, d.code, cl.title,
				CASE WHEN fr.fees_type = 0 THEN sf.term ELSE ex.term END AS term_num
			FROM fees_records fr
			INNER JOIN students st ON st.id = fr.student_id
			INNER JOIN class_records cr ON cr.student = st.id AND cr.year = ?
			INNER JOIN classes cl ON cl.id = cr.class
			INNER JOIN levels l ON l.id = cl.level
			INNER JOIN departments d ON d.id = cl.department
			LEFT JOIN school_fees sf ON sf.id = fr.fees_id AND fr.fees_type = 0
			LEFT JOIN extra_fees ex ON ex.id = fr.fees_id AND fr.fees_type = 1
			WHERE st.school_id = ?
				AND fr.status = 1
				AND fr.is_installment = 1
				AND fr.promised_date IS NOT NULL
				AND fr.promised_date <= ?
				AND (
					(fr.fees_type = 0 AND sf.academic_year = ? AND sf.term = ?)
					OR (fr.fees_type = 1 AND ex.academic_year = ? AND ex.term = ?)
				)
			ORDER BY fr.promised_date ASC, fr.id DESC
		";
		$rows = $db->query($sql, [$academicYear, $schoolId, $today, $academicYear, $term, $academicYear, $term])->getResultArray();
		$out = [];
		foreach ($rows as $row) {
			$balance = self::lineBalance(
				(int) $row['student_id'],
				(int) $row['fees_type'],
				(int) $row['fees_id']
			);
			if ($balance <= 0) {
				continue;
			}
			$row['balance'] = $balance;
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Queue SMS for installment promises due today or overdue (once per record).
	 *
	 * @return array{queued:int,skipped:int}
	 */
	public static function sendDueReminders(int $schoolId, int $activeTermId, int $remainingSms): array
	{
		$model = new FeesRecordModel();
		$model->ensureSchema();
		$today = date('Y-m-d');
		$rows = $model->select('fees_records.id,fees_records.student_id,fees_records.fees_type,fees_records.fees_id,fees_records.promised_date,
			concat(students.fname,\' \',students.lname) as student,students.ft_phone,students.mt_phone,students.gd_phone')
			->join('students', 'students.id=fees_records.student_id')
			->where('students.school_id', $schoolId)
			->where('fees_records.status', 1)
			->where('fees_records.is_installment', 1)
			->where('fees_records.promised_date <=', $today)
			->where('fees_records.reminder_sent_at', null)
			->findAll();

		$queued = 0;
		$skipped = 0;
		$smsMdl = new SmsModel();
		$smsRMdl = new SmsRecipientModel();
		$remaining = max(0, $remainingSms);

		foreach ($rows as $row) {
			$balance = self::lineBalance((int) $row['student_id'], (int) $row['fees_type'], (int) $row['fees_id']);
			if ($balance <= 0) {
				$model->update((int) $row['id'], ['reminder_sent_at' => date('Y-m-d H:i:s')]);
				$skipped++;
				continue;
			}
			if ($remaining <= 0) {
				break;
			}
			$phone = self::pickPhone($row);
			if ($phone === '') {
				$skipped++;
				continue;
			}
			$promised = $row['promised_date'] ?? $today;
			$msg = 'Mubyeyi, turakwibutsa ko mwari mwemeje kwishyura umwenda wa '
				. ($row['student'] ?? 'umwana') . ' ku itariki ' . $promised
				. '. Asigaye: ' . number_format($balance) . ' Rwf. Murakoze.';
			$sid = $smsMdl->insert([
				'school_id' => $schoolId,
				'active_term' => $activeTermId,
				'content' => $msg,
				'recipient_type' => 0,
				'subject' => 'Fee installment reminder',
			]);
			if ($sid === false) {
				$skipped++;
				continue;
			}
			$smsRMdl->save([
				'sms_record_id' => $sid,
				'receiver_id' => (int) $row['student_id'],
				'phone' => $phone,
				'status' => 0,
			]);
			$model->update((int) $row['id'], ['reminder_sent_at' => date('Y-m-d H:i:s')]);
			$queued++;
			$remaining--;
		}

		if ($queued > 0) {
			$param = base_url('background_process/2');
			if (function_exists('exec')) {
				@exec('curl ' . escapeshellarg($param) . ' > /dev/null 2>&1 &');
			}
		}

		return ['queued' => $queued, 'skipped' => $skipped];
	}

	/**
	 * @param array<string,mixed> $row
	 */
	private static function pickPhone(array $row): string
	{
		foreach (['ft_phone', 'mt_phone', 'gd_phone'] as $key) {
			$p = trim((string) ($row[$key] ?? ''));
			if (strlen($p) >= 5) {
				return $p;
			}
		}

		return '';
	}
}
