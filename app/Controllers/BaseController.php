<?php
namespace App\Controllers;
use App\Models\BankCreditTransactionModel;
use App\Models\ClassRecordModel;
use App\Models\CourseCategoryModel;
use App\Models\SchoolModel;
use App\Models\StaffModel;
use App\Models\StudentModel;
use App\Models\UserModel;
use App\Models\IntouchAccount;
use CodeIgniter\Controller;
define('version', "V2.0.0");
const PER_SMS=160;
//define("SMS_API","http://dstr.connectbind.com:8080/sendsms?username=kod-somanet&password=BDS2020&type=0&dlr=1&source=SOMANET");
//const SMS_API="https://www.intouchsms.co.rw/api/sendsms/.json";
//const APP_API_KEY = "A478yud1c6dd40f5%495b323k06336d12f2=";

const SMS_API="https://www.intouchsms.co.rw/api/sendsms/.json";
const APP_API_KEY = "A478yud1c6dd40f5%495b323k06336d12f2=";

//const BESOFT_CHARGES_ACCOUNT="250788784718";
const BESOFT_CHARGES_ACCOUNT="250785753712";
const SOMANET_CHARGES_ACCOUNT="250780699435";
const BESOFT_API_URL="https://mo.mopay.rw/api/v2/payment";
const ID_SUFFIX="SOMA";
const ID_SUFFIXREG="SOMAREG";
const BESOFT_API_TOKEN="895a3c5c-745e-78y8-od51-8210c5905e7y";
const FCM_SERVER_KEY = "AAAAL014UUM:APA91bHSS82I_IrgSCnClghup6fkKw_8dllhTuUh4u0yoNvrrh60AZRf7QFTuysXUGkvePQp_JVhynI3QDyPCmzmD_UrI180J1TVOrpMMdPkwPDANTzAFNYB6MkO3eDcSVvupxYkErop";
require_once APPPATH . 'ThirdParty/PHPMailer/PHPMailer.php';
require_once APPPATH . 'ThirdParty/PHPMailer/SMTP.php';
require_once APPPATH . 'ThirdParty/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Services\Mopay\MopayGatewayClient;
class BaseController extends Controller
{

	/**
	 * An array of helpers to be loaded automatically upon
	 * class instantiation. These helpers will be available
	 * to all other controllers that extend BaseController.
	 *
	 * @var array
	 */
	protected $helpers = [];
	protected $session;
	protected $curl;
	protected $email;

	public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
	{
		// Do Not Edit This Line
		parent::initController($request, $response, $logger);

		//--------------------------------------------------------------------
		// Preload any models, libraries, etc, here.
		//--------------------------------------------------------------------
		// E.g.:
		$this->session = \Config\Services::session();
		$this->blockChiefAccountantMarks();
	}

	/** Chief Accountant uses the Executive Principal dashboard, and cannot open Marks. */
	private function blockChiefAccountantMarks(): void
	{
		try {
		$postId = (int) ($this->session->get('soma_post') ?? 0);
		if (!\Config\MenuClearance::isChiefAccountantPost($postId)) {
			return;
		}
		$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
		$path = strtolower(trim((string) parse_url($uri, PHP_URL_PATH), '/'));
		$path = preg_replace('#^(index\\.php/)+#', '', $path);
		$first = explode('/', $path)[0] ?? '';
		$blocked = [
			'marks_entry',
			'manipulate_marks',
			'get_uploaded_marks',
			'get_periodic_report',
			'get_periodic_marks',
			'get_periodic_slip',
			'student_report',
			'student_report_slip',
			'proclamation_list',
			'student_term_results',
			'class-deliberation',
			'finish_deliberation',
			'deliberation',
			'deliberation_settings',
			'lock_marks_editing',
		];
		if (!in_array($first, $blocked, true)) {
			return;
		}
		helper('url');
		header('Location: ' . base_url('dashboard'));
		exit;
		} catch (\Throwable $e) {
			return;
		}
	}
	public function change_status($type,$value){
		switch ($type){
			case "school":
				$schoolMdl =  new SchoolModel();
				$id = $this->request->getPost("data");
				try {
					$schoolMdl->save(array("id" => $id, "status" => $value));
					return $this->response->setJSON(array("success"=>"School status changed"));
				}catch (\Exception $e){
					return $this->response->setJSON(array("error"=>"Error occurred: ".$e->getMessage()));
				}
				break;
			case "user":
				$userMdl =  new UserModel();
				$id = $this->request->getPost("data");
				try {
					$userMdl->save(array("id" => $id, "status" => $value));
					return $this->response->setJSON(array("success"=>"User status changed"));
				}catch (\Exception $e){
					return $this->response->setJSON(array("error"=>"Error occurred: ".$e->getMessage()));
				}
				break;
			case "staff":
				$staffMdl =  new StaffModel();
				$id = $this->request->getPost("data");
				try {
					$staffMdl->save(array("id" => $id, "status" => $value));
					return $this->response->setJSON(array("success"=>"Staff status changed"));
				}catch (\Exception $e){
					return $this->response->setJSON(array("error"=>"Error occurred: ".$e->getMessage()));
				}
				break;
			case "student":
				helper('qonics');
				if (!function_exists('can_manage_student_lock_delete') || !can_manage_student_lock_delete()) {
					return $this->response->setJSON(array("error"=>"Only the Director can change student status"));
				}
				$stMdl =  new StudentModel();
				$crMdl =  new ClassRecordModel();
				$id = $this->request->getPost("data");
				$record_id = $this->request->getPost("record_id");
				if (strlen($id)==0){
					return $this->response->setJSON(array("error"=>"Error occurred: please provide student id"));
				}
				try {
					$stMdl->save(array("id" => $id, "status" => $value));
					$db = \Config\Database::connect();
					// If a real class_records.id was provided, update it first
					if (strlen($record_id) > 0) {
						$exists = $db->table('class_records')
							->where('id', (int) $record_id)
							->where('student', (int) $id)
							->countAllResults();
						if ($exists > 0) {
							$crMdl->save(array("id" => $record_id, "status" => $value));
						}
					}
					// Always heal ALL class records for this student (fixes Dismissed page unlock
					// which incorrectly posts student id as record_id).
					$db->table('class_records')
						->where('student', (int) $id)
						->update(['status' => (int) $value]);
					return $this->response->setJSON(array("success"=>"Student status changed"));
				}catch (\Exception $e){
					return $this->response->setJSON(array("error"=>"Error occurred: ".$e->getMessage()));
				}
				break;
			case "category":
				$categoryMdl =  new CourseCategoryModel();
				$id = $this->request->getPost("data");
				try {
					$categoryMdl->save(array("id" => $id, "status" => $value));
					return $this->response->setJSON(array("success"=>"Course category status changed"));
				}catch (\Exception $e){
					return $this->response->setJSON(array("error"=>"Error occurred: ".$e->getMessage()));
				}
				break;
		}
	}
	public function random_password($length=10)
	{
		$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890+=-_!*&^%$#@)({}|?,.';
		$password = array();
		$alpha_length = strlen($alphabet) - 1;
		for ($i = 0; $i < $length; $i++)
		{
			$n = rand(0, $alpha_length);
			$password[] = $alphabet[$n];
		}
		$pass = implode($password);
		return $pass;
	}

	/** GSM-7 safe password so credential SMS stays on the 160-char alphabet. */
	protected function _smsSafePassword(int $length = 8): string
	{
		$alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$max = strlen($alphabet) - 1;
		$out = '';
		for ($i = 0; $i < $length; $i++) {
			$out .= $alphabet[random_int(0, $max)];
		}
		return $out;
	}

	/** Package remaining + extra SMS (never negative). */
	protected function _sms_balance($sms_limit, $sms_usage, $extra_sms): int
	{
		return max(0, (int) $sms_limit - (int) $sms_usage) + max(0, (int) $extra_sms);
	}

	/** Compact SMS for staff credentials (user + password, no login URL). */
	protected function _staffCredentialSms(string $name, string $loginUser, string $password, bool $isReset = false): array
	{
		$name = trim(preg_replace('/[^A-Za-z0-9 .]/', '', $name));
		$loginUser = preg_replace('/\s+/', '', $loginUser);
		$intro = $isReset
			? 'Dear ' . $name . ', SmartSMS login reset.'
			: 'Dear ' . $name . ', your SmartSMS account is ready.';
		$body = $intro . ' User: ' . $loginUser . '. Password: ' . $password;
		if (strlen($body) > 150) {
			$short = trim(strtok($name, ' '));
			$intro = $isReset ? 'SmartSMS login reset.' : 'Your SmartSMS account is ready.';
			if ($short !== '') {
				$intro = 'Dear ' . $short . ', ' . $intro;
			}
			$body = $intro . ' User: ' . $loginUser . '. Password: ' . $password;
		}
		$logBody = preg_replace('/Password: \S+/', 'Password: **********', $body);
		return ['body' => $body, 'log' => $logBody];
	}

	/**
	 * Write email/SMS debug lines to writable/logs/comms-YYYY-mm-dd.log
	 * Enabled when DEBUG_COMMS=1 in .env (default on for easier troubleshooting).
	 */
	protected function _comms_debug(string $channel, string $message, array $context = []): void
	{
		$enabled = (string) env('DEBUG_COMMS', '1');
		if ($enabled === '0' || strtolower($enabled) === 'false') {
			return;
		}

		$line = '[' . date('Y-m-d H:i:s') . "] [{$channel}] {$message}";
		if (! empty($context)) {
			// Never log raw SMTP/SMS secrets
			unset($context['password'], $context['pass'], $context['api_key'], $context['SMTP_PASSWORD']);
			$line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		$line .= PHP_EOL;

		log_message('debug', trim($line));

		$dir = WRITEPATH . 'logs';
		if (! is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		@file_put_contents($dir . '/comms-' . date('Y-m-d') . '.log', $line, FILE_APPEND);
	}

	protected function _normalize_rw_phone($phone): string
	{
		$phone = preg_replace('/\D+/', '', (string) $phone);
		if ($phone === '') {
			return '';
		}
		if (substr($phone, 0, 3) === '250') {
			return strlen($phone) >= 12 ? substr($phone, 0, 12) : $phone;
		}
		if (isset($phone[0]) && $phone[0] === '0') {
			return '250' . substr($phone, 1, 9);
		}
		if (strlen($phone) === 9) {
			return '250' . $phone;
		}
		if (strlen($phone) === 12 && substr($phone, 0, 2) === '25') {
			return $phone;
		}
		return '';
	}

	/**
	 * Rwandan mobile line as 2507XXXXXXXX. Empty when the number is missing or not a Rwandan line.
	 */
	protected function _rwandan_parent_msisdn($phone): string
	{
		$digits = preg_replace('/\D+/', '', (string) $phone);
		if (!is_string($digits) || $digits === '') {
			return '';
		}
		if (strncmp($digits, '00', 2) === 0) {
			$digits = substr($digits, 2);
		}
		if (strncmp($digits, '2500', 4) === 0 && isset($digits[4]) && $digits[4] === '7') {
			$digits = '250' . substr($digits, 4, 9);
		} elseif (strncmp($digits, '250', 3) === 0) {
			$digits = substr($digits, 0, 12);
		} elseif (isset($digits[0]) && $digits[0] === '0') {
			$digits = '250' . substr($digits, 1, 9);
		} elseif (strlen($digits) === 9 && $digits[0] === '7') {
			$digits = '250' . $digits;
		} else {
			return '';
		}
		return preg_match('/^2507\d{8}$/', $digits) ? $digits : '';
	}

	/**
	 * Unique Rwandan lines for father, mother, and guardian.
	 * Keys are normalized 2507XXXXXXXX numbers; values are the numbers as stored.
	 *
	 * @return array<string, string>
	 */
	protected function _parent_rwandan_lines(array $row): array
	{
		$lines = [];
		foreach (['ft_phone', 'mt_phone', 'gd_phone'] as $field) {
			$raw = trim((string) ($row[$field] ?? ''));
			$msisdn = $this->_rwandan_parent_msisdn($raw);
			if ($msisdn === '' || isset($lines[$msisdn])) {
				continue;
			}
			$lines[$msisdn] = $raw !== '' ? $raw : $msisdn;
		}
		return $lines;
	}

	/** Provider/error payload as a string for SMS logs and UI. */
	protected function _smsFailReason($fail): string
	{
		if ($fail === null || $fail === false || $fail === '') {
			return '';
		}
		if (is_array($fail)) {
			$content = $fail['content'] ?? $fail['message'] ?? null;
			if (is_string($content) && trim($content) !== '') {
				return trim($content);
			}
			$encoded = json_encode($fail);
			return $encoded !== false ? $encoded : 'SMS failed';
		}
		return trim((string) $fail);
	}

	function _send_sms($phone, $message, &$result, $remaining_sms, $school_acronym = "SOMANET", $school_id = null)
	{
		$this->_comms_debug('SMS', '_send_sms start (SwiftQOM)', [
			'phone_raw' => $phone,
			'remaining_sms' => $remaining_sms,
			'school_acronym' => $school_acronym,
			'school_id' => $school_id,
			'msg_len' => strlen((string) $message),
		]);

		if ($remaining_sms <= 0) {
			$result = ["code" => 200, "content" => "SMS limit reached, contact SOMANET admin"];
			$this->_comms_debug('SMS', 'blocked — SMS limit reached', ['remaining_sms' => $remaining_sms]);
			return false;
		}

		// SwiftQOM only accepts the registered sender_id; school acronyms are rejected.
		return $this->sendSMS($phone, $message, $result, null, 30, $school_id);
	}

	public function sendSMS($phone, $message, &$result, $sender = null, $timeout = 30, $schoolId = null): bool
	{
		$smsConfig = config('Sms');
		$smsType = $smsConfig->type;
		$sender = trim((string) ($sender ?: ($smsConfig->swiftqomSender ?: 'SWIFTQOM')));
		$schoolId = (int) ($schoolId ?? 0);
		if ($schoolId < 1 && isset($this->session)) {
			$schoolId = (int) $this->session->get('soma_school_id');
		}
		if ($schoolId < 1 && isset($this->request)) {
			$postedSchool = (int) $this->request->getPost('school_id');
			if ($postedSchool < 1) {
				$postedSchool = (int) $this->request->getGet('school_id');
			}
			if ($postedSchool > 0) {
				$schoolId = $postedSchool;
			}
		}
		$account = $this->schoolSmsAccount($schoolId);
		if (($account['provider'] ?? '') === 'intouch') {
			return $this->sendIntouchSms($phone, $message, $result, $account, (int) $timeout);
		}

		$this->_comms_debug('SMS', 'sendSMS start', [
			'sms.type' => $smsType,
			'phone_raw' => $phone,
			'sender' => $sender,
			'msg_len' => strlen((string) $message),
			'endpoint' => $smsConfig->swiftqomUrl,
			'has_swiftqom_key' => $smsConfig->swiftqomKey !== '',
		]);

		$phone = $this->_normalize_rw_phone($phone);
		if ($phone === '' || strlen($phone) < 12) {
			$result = ["code" => 400, "content" => 'Invalid phone number'];
			$this->_comms_debug('SMS', 'invalid phone', ['phone_raw' => $phone]);
			return false;
		}

		if ($smsType !== 'swiftqom') {
			$result = ["code" => 500, "content" => "Unsupported sms.type [{$smsType}]"];
			$this->_comms_debug('SMS', 'sendSMS: unsupported provider', ['sms.type' => $smsType]);
			return false;
		}

		$apiKey = $smsConfig->swiftqomKey;
		if ($apiKey === '') {
			$result = ["code" => 500, "content" => 'SwiftQOM API key not configured'];
			$this->_comms_debug('SMS', 'swiftqom: missing API key', []);
			return false;
		}

		$data = [
			'phone' => $phone,
			'sender_id' => $sender,
			'message' => $message,
		];
		$this->_comms_debug('SMS', 'swiftqom: request prepared', [
			'phone' => $phone,
			'sender_id' => $sender,
			'api_key_prefix' => substr($apiKey, 0, 6) . '…',
		]);

		$timeout = (int) $timeout;
		if ($timeout < 4) {
			$timeout = 4;
		}
		$curl = \Config\Services::curlrequest();
		try {
			$req = $curl->request('POST', $smsConfig->swiftqomUrl, [
				'headers' => [
					'x-api-key' => $apiKey,
					'Content-Type' => 'application/json',
				],
				'json' => $data,
				'verify' => false,
				'http_errors' => false,
				'timeout' => $timeout,
				'connect_timeout' => min(5, $timeout),
			]);
		} catch (\Throwable $e) {
			$result = ["code" => 500, "content" => $e->getMessage()];
			$this->_comms_debug('SMS', 'swiftqom: HTTP exception', ['error' => $e->getMessage()]);
			return false;
		}

		$httpCode = $req->getStatusCode();
		$res = $req->getBody();
		$this->_comms_debug('SMS', 'swiftqom: provider response', [
			'http_code' => $httpCode,
			'body' => mb_substr((string) $res, 0, 500),
		]);

		$resData = json_decode($res);
		if ($resData === null && json_last_error() !== JSON_ERROR_NONE) {
			$result = ["code" => 500, "content" => 'Sms send failed, please try again later'];
			$this->_comms_debug('SMS', 'swiftqom: invalid JSON response', ['json_error' => json_last_error_msg()]);
			return false;
		}

		$status = isset($resData->status) ? $resData->status : null;
		$messageOk = isset($resData->message) && strtolower((string) $resData->message) === 'success';
		$successFlag = isset($resData->success) && ($resData->success === true || $resData->success === 1 || $resData->success === '1');
		$accepted = ((int) $status === 200 || $messageOk || $successFlag);
		if (!$accepted) {
			$result = ["code" => 400, "content" => $resData->message ?? 'SMS failed'];
			$this->_comms_debug('SMS', 'swiftqom: FAIL', ['result' => $result]);
			return false;
		}

		$messageId = trim((string) ($resData->message_id ?? ''));
		if ($messageId === '') {
			$result = ["code" => 400, "content" => 'SMS provider did not return a message id, so delivery was not confirmed.'];
			$this->_comms_debug('SMS', 'swiftqom: accepted without message id', []);
			return false;
		}

		$delivery = ['state' => 'pending', 'error' => '', 'status' => ''];
		for ($try = 0; $try < 2; $try++) {
			if ($try > 0) {
				usleep(1500000);
			}
			$delivery = $this->swiftqomDeliveryStatus($phone, $messageId);
			if ($delivery['state'] !== 'pending') {
				break;
			}
		}
		$this->_comms_debug('SMS', 'swiftqom: delivery', [
			'message_id' => $messageId,
			'state' => $delivery['state'],
			'status' => $delivery['status'],
			'error' => $delivery['error'],
		]);
		if ($delivery['state'] === 'delivered') {
			$result = ["code" => 200, "content" => 'delivered'];
			return true;
		}
		$why = $delivery['state'] === 'failed'
			? ($delivery['error'] !== '' ? $delivery['error'] : 'SMS was not delivered')
			: 'SMS was accepted but delivery is not confirmed (' . ($delivery['status'] !== '' ? $delivery['status'] : 'pending') . ').';
		$result = ["code" => 400, "content" => $why];
		return false;
	}

	/** @return array{provider:string,username:string,password:string,sender:string} */
	protected function schoolSmsAccount(int $schoolId): array
	{
		$blank = ['provider' => 'swiftqom', 'username' => '', 'password' => '', 'sender' => ''];
		if ($schoolId < 1) {
			return $blank;
		}
		try {
			$model = new \App\Models\IntouchAccount();
			$model->ensureSchema();
			$row = $model->where('school_id', $schoolId)->first();
		} catch (\Throwable $e) {
			return $blank;
		}
		if (!is_array($row)) {
			$masterId = 0;
			try {
				$db = \Config\Database::connect();
				if ($db->fieldExists('master_school_id', 'schools')) {
					$school = $db->table('schools')->select('master_school_id')->where('id', $schoolId)->get()->getRowArray();
					$masterId = (int) ($school['master_school_id'] ?? 0);
				}
			} catch (\Throwable $e) {
				$masterId = 0;
			}
			if ($masterId > 0 && $masterId !== $schoolId) {
				try {
					$row = (new \App\Models\IntouchAccount())->where('school_id', $masterId)->first();
				} catch (\Throwable $e) {
					$row = null;
				}
			}
		}
		if (!is_array($row)) {
			return $blank;
		}
		$provider = strtolower(trim((string) ($row['provider'] ?? 'swiftqom')));
		if ($provider !== 'intouch') {
			$provider = 'swiftqom';
		}
		return [
			'provider' => $provider,
			'username' => trim((string) ($row['username'] ?? '')),
			'password' => (string) ($row['password'] ?? ''),
			'sender' => trim((string) ($row['sender'] ?? '')),
		];
	}

	/** InTouch HTTP API: basic auth, form body, recipients as 07XXXXXXXX. */
	protected function sendIntouchSms(string $phone, string $message, &$result, array $account, int $timeout): bool
	{
		$username = trim((string) ($account['username'] ?? ''));
		$password = (string) ($account['password'] ?? '');
		if ($username === '' || $password === '') {
			$result = ['code' => 400, 'content' => 'Save the InTouch username and passcode in School settings.'];
			return false;
		}
		$normalized = $this->_normalize_rw_phone($phone);
		if ($normalized === '' || strlen($normalized) < 12) {
			$result = ['code' => 400, 'content' => 'Invalid phone number'];
			return false;
		}
		$recipient = '0' . substr($normalized, 3, 9);
		$sender = preg_replace('/[^A-Za-z0-9]/', '', (string) ($account['sender'] ?? ''));
		$sender = substr((string) $sender, 0, 11);
		if ($sender === '') {
			$sender = 'SCHOOL';
		}
		$timeout = $timeout < 4 ? 8 : $timeout;
		$this->_comms_debug('SMS', 'intouch: request prepared', [
			'phone' => $recipient,
			'sender' => $sender,
			'username' => $username,
		]);
		try {
			$req = \Config\Services::curlrequest()->request('POST', 'https://www.intouchsms.co.rw/api/sendsms/.json', [
				'auth' => [$username, $password, 'basic'],
				'form_params' => [
					'sender' => $sender,
					'recipients' => $recipient,
					'message' => $message,
				],
				'verify' => false,
				'http_errors' => false,
				'timeout' => $timeout,
				'connect_timeout' => min(5, $timeout),
			]);
		} catch (\Throwable $e) {
			$result = ['code' => 500, 'content' => $e->getMessage()];
			$this->_comms_debug('SMS', 'intouch: HTTP exception', ['error' => $e->getMessage()]);
			return false;
		}
		$httpCode = $req->getStatusCode();
		$body = (string) $req->getBody();
		$this->_comms_debug('SMS', 'intouch: provider response', [
			'http_code' => $httpCode,
			'body' => mb_substr($body, 0, 500),
		]);
		$resData = json_decode($body);
		$success = is_object($resData) && isset($resData->success) && ($resData->success === true || $resData->success === 1 || $resData->success === '1');
		$detailStatus = '';
		if (is_object($resData) && isset($resData->details[0]->status)) {
			$detailStatus = strtoupper(trim((string) $resData->details[0]->status));
		}
		if ($httpCode === 200 && $success && !in_array($detailStatus, ['E', 'U'], true)) {
			$result = ['code' => 200, 'content' => 'sent'];
			return true;
		}
		$why = 'InTouch did not send the SMS.';
		if (is_object($resData)) {
			$why = (string) ($resData->message ?? $resData->error ?? $resData->summary->message ?? $why);
		}
		$result = ['code' => 400, 'content' => $why !== '' ? $why : 'InTouch did not send the SMS.'];
		return false;
	}

	/**
	 * SwiftQOM's send call only means the message was accepted.
	 * sms_status is the delivery result (FAILED even when send returned success).
	 *
	 * @return array{state:string, error:string, status:string}
	 */
	protected function swiftqomDeliveryStatus(string $phone, string $messageId): array
	{
		$unknown = ['state' => 'pending', 'error' => '', 'status' => ''];
		$smsConfig = config('Sms');
		$endpoint = preg_replace('#/send_sms/?$#', '/sms_status', (string) $smsConfig->swiftqomUrl);
		if (!is_string($endpoint) || $endpoint === $smsConfig->swiftqomUrl) {
			return $unknown;
		}
		$ch = curl_init($endpoint);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 6,
			CURLOPT_CONNECTTIMEOUT => 4,
			CURLOPT_HTTPHEADER => [
				'x-api-key: ' . $smsConfig->swiftqomKey,
				'Content-Type: application/json',
				'Accept: application/json',
			],
			CURLOPT_POSTFIELDS => json_encode([
				'phone' => $phone,
				'message_id' => $messageId,
			]),
			CURLOPT_SSL_VERIFYPEER => false,
		]);
		$raw = curl_exec($ch);
		curl_close($ch);
		if ($raw === false || $raw === '') {
			return ['state' => 'pending', 'error' => '', 'status' => 'UNCONFIRMED'];
		}
		$data = json_decode($raw);
		$row = $data->data ?? null;
		$status = strtoupper(trim((string) ($row->status ?? '')));
		$error = trim((string) ($row->error_message ?? ''));
		if (stripos($error, 'upstream') !== false || stripos($error, 'Web Page Blocked') !== false || stripos($error, 'mtn.co.rw') !== false) {
			$error = 'Valid number. SwiftQOM charged it, but MTN blocked the delivery.';
		} else {
			$error = preg_replace('/Password:\s*\S+/i', 'Password: [hidden]', $error);
			$error = trim(preg_replace('/\s+/', ' ', $error));
		}
		if (in_array($status, ['FAILED', 'UNDELIVERED', 'REJECTED', 'EXPIRED', 'ERROR'], true)) {
			return ['state' => 'failed', 'error' => $error !== '' ? $error : 'SMS was not delivered', 'status' => $status];
		}
		if (in_array($status, ['DELIVERED', 'SUCCESS', 'SENT', 'OK'], true)) {
			return ['state' => 'delivered', 'error' => '', 'status' => $status];
		}
		return ['state' => 'pending', 'error' => '', 'status' => $status !== '' ? $status : 'PENDING'];
	}

	/**
	 * Send email via SMTP settings from .env (SMTP_*).
	 * Used by school creation, staff creation, password reset, etc.
	 */
	public function _send_email($toEmail, $subject, $msgBody, &$error = null)
	{
		$host     = env('SMTP_HOST', '');
		$port     = (int) env('SMTP_PORT', 465);
		$user     = env('SMTP_USERNAME', '');
		$pass     = env('SMTP_PASSWORD', '');
		$from     = env('SMTP_FROM_EMAIL', $user);
		$fromName = env('SMTP_FROM_NAME', 'XanderTech SmartSMS');
		$crypto   = strtolower((string) env('SMTP_ENCRYPTION', ''));
		$error    = null;

		$this->_comms_debug('EMAIL', 'start', [
			'to' => $toEmail,
			'subject' => $subject,
			'host' => $host,
			'port' => $port,
			'username' => $user,
			'from' => $from,
			'from_name' => $fromName,
			'encryption' => $crypto !== '' ? $crypto : '(auto)',
			'body_len' => strlen((string) $msgBody),
		]);

		if ($host === '' || $user === '' || $pass === '' || $from === '') {
			$error = 'SMTP not configured';
			$this->_comms_debug('EMAIL', 'FAIL missing SMTP config', [
				'has_host' => $host !== '',
				'has_user' => $user !== '',
				'has_pass' => $pass !== '',
				'has_from' => $from !== '',
			]);
			log_message('error', 'SMTP not configured: missing SMTP_HOST / SMTP_USERNAME / SMTP_PASSWORD / SMTP_FROM_EMAIL in .env');
			return false;
		}

		if ($crypto === '') {
			$crypto = ($port === 465) ? 'ssl' : 'tls';
		}

		$mail = new PHPMailer(true);

		try {
			$mail->isSMTP();
			$mail->Host       = $host;
			$mail->SMTPAuth   = true;
			$mail->Username   = $user;
			$mail->Password   = $pass;
			$mail->Port       = $port > 0 ? $port : 465;
			$mail->SMTPSecure = ($crypto === 'ssl' || $crypto === 'smtps')
				? PHPMailer::ENCRYPTION_SMTPS
				: PHPMailer::ENCRYPTION_STARTTLS;
			$mail->Timeout    = 8;
			ini_set('default_socket_timeout', '8');
			$mail->CharSet    = 'UTF-8';
			$mail->SMTPDebug  = 0;
			$mail->Debugoutput = function ($str, $level) {
				$this->_comms_debug('EMAIL-SMTP', trim((string) $str), ['level' => $level]);
			};

			// Protocol dump only when DEBUG_COMMS=2 (level 1 already logs start/success/fail).
			if ((string) env('DEBUG_COMMS', '1') === '2') {
				$mail->SMTPDebug = 2;
			}

			$mail->setFrom($from, $fromName);
			$mail->addAddress($toEmail);
			$mail->addReplyTo($from, $fromName);

			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body    = $msgBody;
			$mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $msgBody)));

			$this->_comms_debug('EMAIL', 'sending via PHPMailer…', [
				'secure' => $mail->SMTPSecure,
				'port' => $mail->Port,
			]);
			$mail->send();
			$this->_comms_debug('EMAIL', 'SUCCESS', ['to' => $toEmail, 'subject' => $subject]);
			return true;
		} catch (\Throwable $e) {
			$err = (isset($mail) && $mail instanceof PHPMailer && $mail->ErrorInfo)
				? $mail->ErrorInfo
				: $e->getMessage();
			$error = $err;
			$this->_comms_debug('EMAIL', 'FAIL', ['to' => $toEmail, 'error' => $err]);
			log_message('error', 'Mailer Error to {to}: {err}', [
				'to'  => $toEmail,
				'err' => $err,
			]);
			return false;
		}
	}



//     // ✅ OTHER FUNCTION (example)
//     public function _get_parent_phone($student)
//     {
//         $stMdl = new StudentModel();
//         $st_dt = $stMdl->select("fname,lname,father,ft_phone,mother,mt_phone,guardian,gd_phone")
//                        ->where("id", $student)
//                        ->get()
//                        ->getRow();

//         $phone = "";
//         $name = "";
//         // your logic here...

//         return [$name, $phone];
//     }

// } // ✅ FINAL closing brace for the class



	public function _get_parent_phone($student)
	{
		$stMdl = new StudentModel();
		$st_dt = $stMdl->select("fname,lname,father,ft_phone,mother,mt_phone,guardian,gd_phone")
			->where("id", $student)
			->get()->getRow();
		$phone = "";
		$name = "";
		if (strlen($st_dt->ft_phone)>3){
			$phone = $st_dt->ft_phone;
			$name = $st_dt->father;
		}else if (strlen($st_dt->mt_phone)>3){
			$phone = $st_dt->mt_phone;
			$name = $st_dt->mother;
		}else if (strlen($st_dt->gd_phone)>3){
			$phone = $st_dt->gd_phone;
			$name = $st_dt->guardian;
		}
		return array("parent_name"=>$name,"phone"=>$phone,"name"=>$st_dt->fname.' '.$st_dt->lname);
	}

	protected function getStudentMaterialCheckSmsPayload(int $schoolId, int $studentId, int $classId, int $yearId): ?array
	{
		if ($schoolId < 1 || $studentId < 1 || $classId < 1 || $yearId < 1) {
			return null;
		}
		$parent = $this->_get_parent_phone($studentId);
		$phone = trim((string) ($parent['phone'] ?? ''));
		if (strlen(preg_replace('/\D+/', '', $phone)) < 9) {
			return null;
		}

		$studentName = trim((string) ($parent['name'] ?? ''));
		$classLabel = trim((string) ((new ClassesModel())->get_class_name($classId) ?? ''));
		$matSchema = new \App\Models\StudentMaterialSchemaModel();
		$matSchema->ensureSchema();
		$materials = $matSchema->getStudentChecklist($schoolId, $studentId, $classId, $yearId);
		$summary = $matSchema->summarizeChecklist($materials);
		$total = (int) ($summary['total'] ?? count($materials));
		if ($total < 1) {
			return null;
		}

		$complete = (int) ($summary['complete'] ?? 0);
		$partial = (int) ($summary['partial'] ?? 0);
		$missing = (int) ($summary['missing'] ?? 0);
		$studentLabel = $studentName !== '' ? $studentName : 'your child';
		$classPart = $classLabel !== '' ? " ({$classLabel})" : '';
		$message = "Babyeyi, igenzura ry'ibikoresho bya {$studentLabel}{$classPart} ryarakozwe. "
			. "Byuzuye: {$complete}/{$total}, Igice: {$partial}, Bibura: {$missing}. Murakoze.";

		return [
			'phone' => $phone,
			'student_name' => $studentName,
			'message' => $message,
		];
	}
	public function get_discipline_msg($name, $marks, $reason, $sendRemarks = false, $lang = null, $activeTermId = null, $schoolId = null){
		$lang = strtolower(trim((string) $lang));
		if ($lang !== 'rw' && $lang !== 'en') {
			$lang = \App\Models\DisciplineCodeModel::discLang();
		}
		$name = trim((string) $name);
		$reason = trim((string) $reason);
		$marksToken = is_string($marks) && !is_numeric(trim($marks));
		$marksLabel = $marksToken ? trim((string) $marks) : (string) (int) $marks;
		$marks = (int) $marks;
		$law = $this->disciplineLawTitle($reason, $lang);
		$date = date('d M Y');
		if ($sendRemarks) {
			if ($lang === 'rw') {
				return "ITEGEKWA\n\n"
					. "Mubyeyi,\n"
					. "Ubuyobozi bw'ishuri bwabonye ko umwana wawe {$name} yavunye amabwiriza n'amategeko y'ishuri.\n\n"
					. "Itegeko: {$law}\n\n"
					. "Iyi ni umuburo ukomeye. Agomba guhindura iyo myitwarire ako kanya.\n"
					. "Niba bitagenze neza, amanota y'igihembwe azakuwaho cyangwa ashobora guhagarikwa, kubera: {$law}.\n\n"
					. "Twiteze impinduka yuzuye uyu munsi.\n\n"
					. "Ubuyobozi bw'ishuri\n"
					. "Itariki: {$date}";
			}
			return "WARNING\n\n"
				. "Dear Parent,\n"
				. "The School Administration has noted that your child {$name} has broken the School Rules and Regulations.\n\n"
				. "Law: {$law}\n\n"
				. "This is a serious warning. Your child must change this behaviour immediately.\n"
				. "Failure to comply will lead to deduction of marks from the quarterly conduct marks, or suspension, because of: {$law}.\n\n"
				. "We expect a complete change from today.\n\n"
				. "The School Administration\n"
				. "Date: {$date}";
		}
		if ($marks <= 0 && !$marksToken) {
			if ($lang === 'rw') {
				return "Babyeyi, {$name}: {$law}. Nta manota yakuweho. Iyi nshuro yabitswe. Murakoze.";
			}
			return "Dear parent, {$name}: {$law}. No marks were deducted. This offence is recorded. Thank you.";
		}
		$school = $this->disciplineSchoolId($schoolId);
		$term = $this->disciplineTermCode($activeTermId, $school);
		$place = $this->disciplinePlace($school);
		if ($lang === 'rw') {
			return "IGIHANO\n\n"
				. "Mubyeyi,\n\n"
				. "Ubuyobozi bw'ishuri bwabonye ko umwana wawe {$name} yakomeje kwica amategeko muri iki gihembwe.\n\n"
				. "Itegeko: {$law}\n\n"
				. "Komite y'imyitwarire yafashe icyemezo cyo gukuraho amanota {$marksLabel} ku manota rusange y'igihembwe.\n"
				. "Iki gihano kizagaragara ku ifishi y'amanota ya {$term}.\n\n"
				. "Icyitonderwa: Umunyeshuri araburwa ko iyo yongera kugira iyo myitwarire azahabwa igihano kirenze aka.\n\n"
				. "Byakorewe {$place},\n"
				. "Ku wa {$date}\n\n"
				. "Ubuyobozi bw'ishuri";
		}
		return "DISCIPLINARY SANCTION\n\n"
			. "Dear Parent,\n\n"
			. "The School Administration has noted repeated misconduct by your child {$name} during this term.\n\n"
			. "Law: {$law}\n\n"
			. "The Disciplinary Committee has decided to deduct {$marksLabel} marks from the quarterly general total.\n"
			. "This sanction will be shown on the {$term} report card.\n\n"
			. "Note: The student is warned that any repetition will lead to a heavier punishment.\n\n"
			. "Done at {$place},\n"
			. "On {$date}\n\n"
			. "School Administration";
	}

	private function disciplineLawTitle(string $reason, string $lang): string
	{
		$law = trim($reason);
		if (preg_match('/^(.*?)(?:\s+\(|\s+\x{2014}\s+|\s+—\s+)/u', $law, $cut)) {
			$clean = trim((string) ($cut[1] ?? ''));
			if ($clean !== '') {
				$law = $clean;
			}
		}
		if ($law === '' || $law === '{LAW}') {
			return $law === '{LAW}' ? '{LAW}' : ($lang === 'rw' ? "itegeko ry'ishuri" : 'a school rule');
		}
		return $law;
	}

	private function disciplineSchoolId($schoolId): int
	{
		$id = (int) $schoolId;
		if ($id > 0) {
			return $id;
		}
		if ($this->session) {
			$id = (int) $this->session->get('soma_school_id');
		}
		if ($id <= 0 && isset($this->data['school_id'])) {
			$id = (int) $this->data['school_id'];
		}
		return $id;
	}

	private function disciplineTermCode($activeTermId, int $schoolId): string
	{
		$termNo = 0;
		try {
			$db = \Config\Database::connect();
			if ((int) $activeTermId > 0) {
				$row = $db->table('active_term')->select('term')->where('id', (int) $activeTermId)->get()->getRowArray();
				$termNo = (int) ($row['term'] ?? 0);
			}
			if ($termNo < 1 && $schoolId > 0) {
				$row = $db->table('schools s')
					->select('at.term')
					->join('active_term at', 'at.id = s.active_term', 'left')
					->where('s.id', $schoolId)
					->get()->getRowArray();
				$termNo = (int) ($row['term'] ?? 0);
			}
		} catch (\Throwable $e) {
			$termNo = 0;
		}
		if ($termNo < 1 || $termNo > 3) {
			$termNo = 1;
		}
		return 'T' . $termNo;
	}

	private function disciplinePlace(int $schoolId): string
	{
		$campuses = ['BURERA', 'FUMBWE', 'KABARORE', 'KANZENZE', 'KAYONZA', 'KIRAMURUZI', 'MUSANZE', 'MUYUMBU', 'NGORORERO', 'NYABIHU', 'NYAMASHEKE', 'RUBAVU', 'RUBENGERA', 'RUNDA', 'SUSA'];
		$name = '';
		$address = '';
		if ($schoolId > 0) {
			try {
				$row = (new \App\Models\SchoolModel())->select('name,address')->where('id', $schoolId)->first();
				if (is_array($row)) {
					$name = strtoupper((string) ($row['name'] ?? ''));
					$address = trim((string) ($row['address'] ?? ''));
				}
			} catch (\Throwable $e) {
				$name = '';
			}
		}
		if ($name === '' && $this->session) {
			$name = strtoupper(trim((string) $this->session->get('soma_school')));
		}
		foreach ($campuses as $campus) {
			if ($name !== '' && strpos($name, $campus) !== false) {
				return ucfirst(strtolower($campus));
			}
		}
		if ($address !== '' && strlen($address) <= 40 && strpos($address, ',') === false) {
			return $address;
		}
		return 'Musanze';
	}
	public function get_permisson_msg($name,$destination,$reason){
		$lang = \App\Models\DisciplineCodeModel::discLang();
		$name = trim((string) $name);
		$destination = trim((string) $destination);
		$reason = trim((string) $reason);
		if ($lang === 'rw') {
			return "Babyeyi, {$name} ahawe uruhushya rwo kujya {$destination}. Impamvu: {$reason}. Murakoze.";
		}
		return "Dear parent, {$name} was given permission to go to {$destination}. Reason: {$reason}. Thank you.";
	}
	/**
	 * This function is used to send push notification to user
	 * @param string $token device token to send message
	 * @param array $data array that contains custom data to send
	 * @param array $notification array that contains notification data (title,body,imageUrl,..)
	 * @throws \Exception throw an exception when error occurred
	 */
	public function sendNotificationMessage(string $token,array $data,array $notification){
		if(strlen($token)<10){
			throw(new \Exception("Invalid Token"));
		}
		if(!is_array($data) || count($data)==0){
			throw(new \Exception("Please provide a valid message to send"));
		}
		if(!is_array($notification)){
			throw(new \Exception("Notification must be array and contains title and message"));
		}
		$data = ["to"=>$token,"data"=>$data,"notification"=>$notification];
//		echo json_encode($data);die();
		$this->curl = \Config\Services::curlrequest();

//		$req = $this->curl->request("POST","https://fcm.googleapis.com/fcm/send",[
//			'form_params' => $data,"headers"=>["Authorization"=>"Key=".FCM_SERVER_KEY,"Content-Type"=>"application/json"],'verify' => false,'http_errors' => false
//		]);
		$req = $this->curl->setBody(json_encode($data))->setHeader("Authorization","Key=".FCM_SERVER_KEY)
			->setHeader("Content-Type","application/json")
			->request("POST","https://fcm.googleapis.com/fcm/send",
				['verify' => false,'http_errors' => false]
			);
		echo $req->getBody();
	}

	/**
	 * @param string $tx_id Transaction ID from database and prepend EDU
	 * @param object $input object that contains payment info (token,studentId,phone,amount,..)
	 * @param object $student object that contains student info (id,name,regno,..)
	 * @return string Returns Reference number of the payment from MTN #momo_ref_number
	 * @throws \Exception throw an exception when error occurred
	 */
	public function topUpMOMO(string $tx_id,object $input,object $student):string{
		if(strlen($input->phone)!=12){
			throw(new \Exception("Invalid Phone number"));
		}
		$amount = $input->amount;
		$phone = $input->schoolPhone;
		if($input->type == 4){
			//put all amount to BESOFT account
//			$input->amount += $input->charges;
//			$input->charges = 0;
//			$phone = BESOFT_CHARGES_ACCOUNT;
		}
		$data = [
			"token"=>BESOFT_API_TOKEN,
			"external_transaction_id"=>$tx_id,
			"callback_url"=>base_url('api/updatePaymentStatus'),
			"debit"=>[
				"phone_number"=>$input->phone,
				"amount"=>$input->grandTotal,
				"message"=>ucfirst($student->fname)." Wallet top up"
			],
			"transfers" => [
				[
					"phone_number"=>$phone,
					"amount" => $input->amount,
					"message" => "{$student->regno} Top up"
				],
				[
					"phone_number"=>BESOFT_CHARGES_ACCOUNT,
					"amount" => $input->charges-$input->somanetChargesAmount,
					"message" => "{$student->regno} Top up"
				]
			]
		];
		if($input->type == 4) {
			//school_fees
			$data['transfers'][] = [
				"phone_number" => SOMANET_CHARGES_ACCOUNT,
				"amount" => $input->somanetChargesAmount,
				"message" => "{$student->regno} Registration charges"
			];
		}
//		echo "resdfssdf".json_encode($data);die();
		$this->curl = \Config\Services::curlrequest();
		$req = $this->curl->setBody(json_encode($data))->setHeader("Content-Type","application/json")
			->request("POST",BESOFT_API_URL,
				['verify' => false,'http_errors' => false]
			);
		$res = $req->getBody();
		if (($resData = json_decode($res))===false){
			throw(new \Exception("Invalid API response: {$res}"));
		}else if($resData->status_code>300){
			throw(new \Exception("Error: {$resData->message}"));
		}
		//save credit
		$bMdl = new BankCreditTransactionModel();
		$bMdl->save(['wallet_id'=>$input->walletId, 'amount'=>$amount,'school_id'=>$input->schoolId,'status'=>0]);
		return $resData->momo_ref_number??'';
	}
	public function processPendingBprTransfer(){
		$bMdl = new BankCreditTransactionModel();
		$records = $bMdl->select("bank_credit_transactions.*,s.bank_account,p.txn_id,bank_credit_transactions.retryCount")
			->join("payment_transactions p","p.id = bank_credit_transactions.wallet_id")
			->join("schools s","s.id = bank_credit_transactions.school_id")
			->where("p.status",1)
			->where("bank_credit_transactions.status",0)
			->get()->getResult();
		echo "Pending transactions: ".count($records)."<br />";
		$success = 0;
		foreach ($records as $record) {
			$trans = [
				[
					"drcr"=>"D",
					"account" => BESOFT_BPR_ACCOUNT,
					"amount" => $record->amount,
					"narrative" => "SOMANET FEES TRANSFER"
				],
				[
					"drcr"=>"C",
					"account" => $record->bank_account,
					"amount" => $record->amount,
					"narrative" => "SOMANET FEES TRANSFER"
				]
			];
			try {
				$this->bprPayment($record->id,$record->txn_id . 'I' . $record->id. 'R'.$record->retryCount, $trans);
				$success++;
			} catch (\Exception $e) {
				log_message("critical","BPR BUG: ".$e->getMessage());
				$bMdl->save(['id' => $record->id, 'retryCount'=>($record->retryCount+1),'errorMessage' => $e->getMessage()]);
			}
		}
		echo "Succeeded transactions: ".$success."<br />";
	}

	/**
	 * @throws \Exception
	 */
	public function bprPayment(int $id,string $tx_id, array $trans){
		if(strlen($tx_id)<3){
			throw(new \Exception("Invalid Transaction ID"));
		}
		if(count($trans)<2){
			throw(new \Exception("Invalid Transaction data"));
		}

		$data = [
			"besoftId"=>$tx_id,
			"trans"=>$trans
		];
		$this->curl = \Config\Services::curlrequest();
		$req = $this->curl->setBody(json_encode($data))->setHeader("Content-Type","application/json")
			->request("POST",getenv('custom.bprUrl').'payment',
				['verify' => false,'http_errors' => false]
			);
		$res = $req->getBody();
		if (($resData = json_decode($res))===false){
			throw(new \Exception("Invalid API response: {$res}"));
		}else if($resData->status!=200){
			throw(new \Exception("Error: {$resData->message}"));
		}
		//update credit status
		log_message("critical","BPR RESPONSE: ".$res);
		$bMdl = new BankCreditTransactionModel();
		try {
			$bMdl->save(['id' => $id, 'status' => 1, 'refNo' => $resData->bprRefNo, 'errorMessage' => '']);
		} catch (\ReflectionException $e) {
			throw(new \Exception("Error:  Failed to update bankCredit {$e->getMessage()}"));
		}
	}
	public function verifyBprAccount(string $account,string $key='account'){
		if(strlen($account)<5){
			throw(new \Exception("Invalid Bank account"));
		}

		$data = [
			$key=>$account,
		];
		$this->curl = \Config\Services::curlrequest();
		$req = $this->curl->setBody(json_encode($data))->setHeader("Content-Type","application/json")
			->request("POST",getenv('custom.bprUrl').'customername',
				['verify' => false,'http_errors' => false]
			);
		$res = $req->getBody();
		echo $res;
		if (($resData = json_decode($res))===false){
			throw(new \Exception("Invalid API response: {$res}"));
		}else if($resData->status!=200){
			throw(new \Exception("Error: {$resData->message}"));
		}
		//update credit status
		log_message("critical","BPR RESPONSE: ".$res);

	}
	/**
	 * Initiate registration fee collection via MoPay Gateway V1.
	 * Debits payer for gross amount and splits transfers:
	 * - schoolAmount → school MOMO (Basic Settings)
	 * - chargesAmount (+ platform if any) → REGISTRATION_SERVICE_FEE_MOMO from .env
	 *
	 * @param string $tx_id Preferred MoPay transaction id (may be reminted on duplicate)
	 * @param object $input phone, schoolPhone, grossAmount, schoolAmount, chargesAmount, somanetChargesAmount
	 * @param object $student code / names for messages
	 * @return array{transaction_id:string,momo_ref:string,flow:string,raw:string}
	 * @throws \Exception
	 */
	public function registrationPayment(string $tx_id, object $input, object $student): array
	{
		$gateway = MopayGatewayClient::make();
		if (!$gateway->isConfigured()) {
			throw new \Exception('MoPay is not configured. Set MOPAY_AUTH_KEY and MOPAY_SERVER_BASE_URL in .env');
		}

		$payer = $gateway->normalizeMsisdn((string) ($input->phone ?? ''));
		if (strlen($payer) < 12) {
			throw new \Exception('Invalid MOMO phone number');
		}

		$schoolAmount = (int) ($input->schoolAmount ?? 0);
		$serviceAmount = (int) ($input->chargesAmount ?? 0);
		$platformAmount = (int) ($input->somanetChargesAmount ?? 0);
		$platformOpsAmount = $serviceAmount + $platformAmount;
		$gross = (int) ($input->grossAmount ?? ($schoolAmount + $platformOpsAmount));
		if ($gross < 1 || $schoolAmount < 1) {
			throw new \Exception('Invalid registration payment amount');
		}
		if ($schoolAmount + $platformOpsAmount !== $gross) {
			$gross = $schoolAmount + $platformOpsAmount;
		}

		$schoolReceiver = $gateway->normalizeMsisdn((string) ($input->schoolPhone ?? ''));
		if (strlen($schoolReceiver) < 12) {
			throw new \Exception('School MOMO receive number is not configured in Basic Settings');
		}

		$serviceMomoRaw = trim((string) env('REGISTRATION_SERVICE_FEE_MOMO', ''));
		if ($serviceMomoRaw === '') {
			$serviceMomoRaw = trim((string) env('MOPAY_RECEIVER_ACCOUNT_NO', ''));
		}
		$serviceReceiver = $serviceMomoRaw !== '' ? $gateway->normalizeMsisdn($serviceMomoRaw) : '';
		if ($platformOpsAmount > 0 && strlen($serviceReceiver) < 12) {
			throw new \Exception('Service fee MOMO is not configured. Set REGISTRATION_SERVICE_FEE_MOMO in .env');
		}

		$code = (string) ($student->code ?? 'REG');
		$safeCode = preg_replace('/[^A-Za-z0-9_]/', '', $code) ?: 'REG';
		$txId = $tx_id !== '' ? $tx_id : $gateway->newTransactionId('XSCHREG');

		$transfers = [[
			'transactionId' => $txId . '_T1',
			'account_no' => $schoolReceiver,
			'amount' => $schoolAmount,
			'message' => 'XSCHREG_' . $safeCode . '_SCHOOL',
		]];
		if ($platformOpsAmount > 0) {
			$transfers[] = [
				'transactionId' => $txId . '_T2',
				'account_no' => $serviceReceiver,
				'amount' => $platformOpsAmount,
				'message' => 'XSCHREG_' . $safeCode . '_SERVICE',
			];
		}

		$result = $gateway->initiateCollection([
			'account_no' => $payer,
			'amount' => $gross,
			'transaction_id' => $txId,
			'title' => 'Xander_school_registration',
			'details' => 'Student_registration_payment_' . $safeCode,
			'message' => 'XSCHREG_' . $safeCode,
			'use_transfer' => true,
			'transfers' => $transfers,
		]);

		if (empty($result['ok'])) {
			$msg = (string) ($result['error_message'] ?? 'MoPay payment request failed');
			$http = (int) ($result['http_status'] ?? 0);
			log_message('error', 'MoPay registrationPayment failed http=' . $http . ' msg=' . $msg . ' raw=' . substr((string) ($result['raw'] ?? ''), 0, 500));
			throw new \Exception($msg !== '' ? $msg : ('Mobile Money gateway error (HTTP ' . $http . ')'));
		}

		$momoRef = '';
		if (is_array($result['response'] ?? null)) {
			$momoRef = (string) ($result['response']['momoRef']
				?? $result['response']['momo_ref']
				?? $result['response']['momo_ref_number']
				?? '');
		}

		return [
			'transaction_id' => (string) ($result['transaction_id'] ?? $txId),
			'momo_ref' => $momoRef,
			'flow' => (string) ($result['flow'] ?? ''),
			'raw' => (string) ($result['raw'] ?? ''),
		];
	}

	/**
	 * Global service + platform fees from admin panel.
	 *
	 * @return array{service_fee:int,platform_fee:int}
	 */
	protected function getRegistrationGatewayFees(): array
	{
		try {
			$fees = (new \App\Models\PlatformSettingsModel())->getFees();
			return [
				'service_fee' => max(0, (int) ($fees['service_fee'] ?? 0)),
				'platform_fee' => max(0, (int) ($fees['platform_fee'] ?? 0)),
			];
		} catch (\Throwable $e) {
			log_message('warning', 'getRegistrationGatewayFees: ' . $e->getMessage());
			return ['service_fee' => 0, 'platform_fee' => 0];
		}
	}

	/**
	 * Whether live MoPay registration can run (env configured + not forced bypass).
	 */
	protected function isRegistrationMopayLive(): bool
	{
		$forcedBypass = (string) env('REGISTRATION_PAYMENT_BYPASS', '0') === '1';
		if ($forcedBypass) {
			return false;
		}

		return MopayGatewayClient::make()->isConfigured();
	}
}
