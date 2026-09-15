<?php
/**
 * CLI: reset login and send SMS + email to one staff member.
 *   php /var/www/html/test_share_staff.php [staff_id|email]
 */
define('FCPATH', __DIR__ . '/public/');
require __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();
chdir(FCPATH);
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$lookup = $argv[1] ?? 'ukipi202@gmail.com';

$request = \Config\Services::request();
$response = \Config\Services::response();
$logger = \Config\Services::logger();
$controller = new \App\Controllers\BaseController();
$controller->initController($request, $response, $logger);

$staffMdl = new \App\Models\StaffModel();
if (ctype_digit((string) $lookup)) {
	$staff = $staffMdl->find((int) $lookup);
} else {
	$staff = $staffMdl->where('email', $lookup)->first();
	if (!$staff) {
		$staff = $staffMdl->like('email', $lookup)->first();
	}
}

if (!$staff) {
	echo json_encode(['ok' => false, 'error' => 'Staff not found', 'lookup' => $lookup], JSON_UNESCAPED_SLASHES) . PHP_EOL;
	exit(1);
}

$staffId = (int) $staff['id'];
$fname = (string) ($staff['fname'] ?? '');
$lname = (string) ($staff['lname'] ?? '');
$email = preg_replace('/\s+/', '', trim((string) ($staff['email'] ?? '')));
$phone = trim((string) ($staff['phone'] ?? ''));
$name = trim($fname . ' ' . strtoupper(substr($lname, 0, 1)) . '.');
$loginUser = $email !== '' ? $email : $phone;

$reflect = new ReflectionClass($controller);
$pwMethod = $reflect->getMethod('_smsSafePassword');
$pwMethod->setAccessible(true);
$password = $pwMethod->invoke($controller, 8);

$staffMdl->update($staffId, [
	'password' => password_hash($password, PASSWORD_DEFAULT),
	'reset_exp' => 0,
]);

$smsBody = 'Dear ' . $name . ', SmartSMS login reset. User: ' . $loginUser
	. '. Password: ' . $password . '. Thank you';

$smsResult = null;
$smsOk = $controller->sendSMS($phone, $smsBody, $smsResult);

$html = view('emails/staff_creation', [
	'name' => $name,
	'phone' => $phone,
	'email' => $email,
	'default_password' => $password,
]);
$emailError = null;
$emailOk = false;
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
	$emailOk = $controller->_send_email($email, 'XanderTech SmartSMS login credentials', $html, $emailError);
} else {
	$emailError = 'No valid email on staff record';
}

echo json_encode([
	'ok' => $smsOk && $emailOk,
	'staff_id' => $staffId,
	'name' => trim($fname . ' ' . $lname),
	'phone' => $phone,
	'email' => $email,
	'sms_ok' => $smsOk,
	'sms_result' => $smsResult,
	'email_ok' => $emailOk,
	'email_error' => $emailError,
	'login_user' => $loginUser,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(($smsOk && $emailOk) ? 0 : 1);
