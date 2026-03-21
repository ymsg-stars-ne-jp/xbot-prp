<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['x_user'])) {
    jsonResponse(['authenticated' => false]);
}

jsonResponse([
    'authenticated' => true,
    'id' => $_SESSION['x_user']['id'] ?? '',
    'name' => $_SESSION['x_user']['name'] ?? '',
    'username' => $_SESSION['x_user']['username'] ?? ''
]);
