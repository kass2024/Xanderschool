<?php
/**
 * PHP built-in server router for the Xander School desktop app.
 * Forces SCRIPT_NAME to /index.php so CodeIgniter sees /login, /dashboard, etc.
 */
$uri = urldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
$doc = __DIR__;
$path = $doc . str_replace('/', DIRECTORY_SEPARATOR, $uri);

if ($uri !== '/' && is_file($path)) {
	return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';

require $doc . DIRECTORY_SEPARATOR . 'index.php';
