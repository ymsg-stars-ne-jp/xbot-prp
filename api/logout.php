<?php
require_once __DIR__ . '/bootstrap.php';
requireMethod('POST');
rateLimit('logout', 30, 60);
verifyCsrf();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
jsonResponse(['ok' => true]);
