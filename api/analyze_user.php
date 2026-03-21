<?php
require_once __DIR__ . '/bootstrap.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['message' => 'Method Not Allowed'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$username = trim((string)($body['username'] ?? ''));
$username = ltrim($username, '@');

if ($username === '') {
    jsonResponse(['message' => 'ユーザー名を入力してください。'], 422);
}

$accessToken = $_SESSION['x_access_token'];
$userResp = xApiGet('/users/by/username/' . rawurlencode($username), $accessToken, [
    'user.fields' => 'created_at,description,public_metrics,verified,location,url'
]);

if (empty($userResp['data'])) {
    jsonResponse(['message' => '対象ユーザーが見つかりません。'], 404);
}

$user = $userResp['data'];
$evaluated = evaluateRisk($user);

jsonResponse([
    'user' => [
        'id' => $user['id'] ?? '',
        'name' => $user['name'] ?? '',
        'username' => $user['username'] ?? '',
        'created_at' => $user['created_at'] ?? null,
        'public_metrics' => $evaluated['metrics']
    ],
    'score' => $evaluated['score'],
    'reasons' => $evaluated['reasons'],
    'summary' => $evaluated['summary']
]);
