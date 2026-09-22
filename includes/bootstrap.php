<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    die('Missing config.php. Copy config.sample.php to config.php and fill in your details.');
}
$config = require $configFile;

session_name('wf_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/http_fetch.php';
require __DIR__ . '/links.php';
require __DIR__ . '/collections.php';
require __DIR__ . '/netscape.php';

$pdo = wf_db($config);
