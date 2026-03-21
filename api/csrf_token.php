<?php
require_once __DIR__ . '/bootstrap.php';
rateLimit('csrf_token', 60, 60);
jsonResponse(['csrf_token' => getCsrfToken()]);
