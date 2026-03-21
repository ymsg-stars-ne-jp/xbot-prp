<?php
require_once __DIR__ . '/bootstrap.php';
requireMethod('POST');
rateLimit('analyze_user', 50, 60);
verifyCsrf();
requireAuth();

$body = json_decode((string)file_get_contents('php://input'), true);
$username = ltrim(trim((string)($body['username'] ?? '')), '@');
if ($username === '' || !validUsername($username)) {
    jsonResponse(['message' => 'ユーザー名の形式が不正です。'], 422);
}

$userResp = xApiGet('/users/by/username/' . rawurlencode($username), $_SESSION['x_access_token'], [
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
