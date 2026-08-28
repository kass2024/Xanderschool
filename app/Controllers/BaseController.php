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
				$stMdl =  new StudentModel();
				$crMdl =  new ClassRecordModel();
				$id = $this->request->getPost("data");
				$record_id = $this->request->getPost("record_id");
				if (strlen($id)==0 || strlen($record_id)==0){
					return $this->response->setJSON(array("error"=>"Error occurred: please provide all required data $id | $record_id "));
				}
				try {
					$stMdl->save(array("id" => $id, "status" => $value));
					$crMdl->save(array("id" => $record_id, "status" => $value));
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
		$intro = $isReset
			? lang('app.dear') . ' ' . $name . ', SmartSMS login reset.'
			: lang('app.dear') . ' ' . $name . lang('app.accountIsCreated');
		$body = trim(preg_replace('/\s+/', ' ', $intro))
			. ' User: ' . $loginUser
			. '. ' . lang('app.password') . ': ' . $password
			. '. ' . lang('app.thankyou');
		$logBody = trim(preg_replace('/\s+/', ' ', $intro))
			. ' User: ' . $loginUser
			. '. ' . lang('app.password') . ': **********'
			. '. ' . lang('app.thankyou');
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
			return $phone;
		}
		if ($phone[0] === '0') {
			return '250' . substr($phone, 1);
		}
		if (strlen($phone) === 9) {
			return '250' . $phone;
		}
		return '25' . $phone;
	}

	function _send_sms($phone, $message, &$result, $remaining_sms, $school_acronym = "SOMANET", $school_id = null)
	{
		$this->_comms_debug('SMS', '_send_sms start (SwiftQOM)', [
			'phone_raw' => $phone,
			'remaining_sms' => $remaining_sms,
			'sender' => $school_acronym,
			'school_id' => $school_id,
			'msg_len' => strlen((string) $message),
		]);

		if ($remaining_sms <= 0) {
			$result = ["code" => 200, "content" => "SMS limit reached, contact SOMANET admin"];
			$this->_comms_debug('SMS', 'blocked — SMS limit reached', ['remaining_sms' => $remaining_sms]);
			return false;
		}

		return $this->sendSMS($phone, $message, $result, $school_acronym);
	}

	public function sendSMS($phone, $message, &$result, $sender = null): bool
	{
		$smsConfig = config('Sms');
		$smsType = $smsConfig->type;
		$sender = $sender ?: $smsConfig->swiftqomSender;

		$this->_comms_debug('SMS', 'sendSMS start', [
			'sms.type' => $smsType,
			'phone_raw' => $phone,
			'sender' => $sender,
			'msg_len' => strlen((string) $message),
			'endpoint' => $smsConfig->swiftqomUrl,
			'has_swiftqom_key' => $smsConfig->swiftqomKey !== '',
		]);

		$phone = $this->_normalize_rw_phone($phone);
		if ($phone === '') {
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

		if (isset($resData->status) && (int) $resData->status === 200) {
			$this->_comms_debug('SMS', 'swiftqom: SUCCESS', ['phone' => $phone]);
			return true;
		}

		$result = ["code" => 400, "content" => $resData->message ?? 'SMS failed'];
		$this->_comms_debug('SMS', 'swiftqom: FAIL', ['result' => $result]);
		return false;
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
			$mail->Timeout    = 20;
			$mail->CharSet    = 'UTF-8';
			$mail->SMTPDebug  = 0;
			$mail->Debugoutput = function ($str, $level) {
				$this->_comms_debug('EMAIL-SMTP', trim((string) $str), ['level' => $level]);
			};

			// Enable protocol-level debug when DEBUG_COMMS is on
			if ((string) env('DEBUG_COMMS', '1') !== '0') {
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
	public function get_discipline_msg($name,$marks,$reason){
		return "Babyeyi dufatanyije kurera, umwana wanyu {$name} akuweho amanota {$marks} y'imyitwarire kubera {$reason}.\nMurakoze";

	}
	public function get_permisson_msg($name,$destination,$reason){
		return "babyeyi dufatanyije kurera umwana wanyu {$name} ahawe uruhushya rwo Kujya {$destination} Kubera {$reason}.\nMurakoze";

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
