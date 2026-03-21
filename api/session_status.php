<?php
require_once __DIR__ . '/bootstrap.php';
rateLimit('session_status', 120, 60);

if (empty($_SESSION['x_user'])) {
    jsonResponse(['authenticated' => false, 'csrf_token' => getCsrfToken()]);
}

jsonResponse([
    'authenticated' => true,
    'id' => $_SESSION['x_user']['id'] ?? '',
    'name' => $_SESSION['x_user']['name'] ?? '',
    'username' => $_SESSION['x_user']['username'] ?? '',
    'csrf_token' => getCsrfToken()
]);
