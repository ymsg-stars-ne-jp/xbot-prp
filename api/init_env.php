<?php
require_once __DIR__ . '/bootstrap.php';
rateLimit('init_env', 10, 60);

$envPath = dirname(__DIR__) . '/.env';
$examplePath = dirname(__DIR__) . '/.env.example';

if (is_file($envPath)) {
    jsonResponse(['ok' => true, 'message' => '.env は既に存在します。'], 200);
}

if (!is_file($examplePath)) {
    jsonResponse(['ok' => false, 'message' => '.env.example が見つかりません。'], 500);
}

copy($examplePath, $envPath);
@chmod($envPath, 0600);
jsonResponse(['ok' => true, 'message' => '.env を自動作成しました。値を設定してください。'], 201);
