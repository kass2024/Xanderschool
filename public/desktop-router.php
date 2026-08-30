<?php
/**
 * PHP built-in server router for the Xander School desktop app.
 * Forces SCRIPT_NAME to /index.php so CodeIgniter sees /login, /dashboard, etc.
 */
$uri = urldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$doc = __DIR__;
$path = $doc . str_replace('/', DIRECTORY_SEPARATOR, $uri);

if (preg_match('#^/assets/images/profile/([A-Za-z0-9._-]+)$#', $uri, $match)) {
	$profileDir = rtrim((string) getenv('XANDER_PROFILE_DIR'), '/\\');
	$profilePath = $profileDir . DIRECTORY_SEPARATOR . $match[1];
	if ($profileDir !== '' && is_file($profilePath)) {
		$mime = function_exists('mime_content_type') ? mime_content_type($profilePath) : 'application/octet-stream';
		header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
		header('Content-Length: ' . (string) filesize($profilePath));
		readfile($profilePath);
		exit;
	}
}

if ($uri !== '/' && is_file($path)) {
	return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';

require $doc . DIRECTORY_SEPARATOR . 'index.php';
