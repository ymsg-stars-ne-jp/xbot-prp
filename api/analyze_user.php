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

if (($userResp['_http_status'] ?? 200) === 404) {
    jsonResponse(['message' => '対象ユーザーが見つかりません。'], 404);
}

if (($userResp['_http_status'] ?? 200) >= 400) {
    jsonResponse([
        'message' => 'X API呼び出しに失敗しました。',
        'status' => $userResp['_http_status'],
        'error' => $userResp
    ], $userResp['_http_status']);
}

if (empty($userResp['data'])) {
    jsonResponse(['message' => '対象ユーザーが見つかりません。'], 404);
}

$user = $userResp['data'];
$metrics = $user['public_metrics'] ?? [];
$followers = (int)($metrics['followers_count'] ?? 0);
$following = (int)($metrics['following_count'] ?? 0);
$tweetCount = (int)($metrics['tweet_count'] ?? 0);

$score = 0;
$reasons = [];

$createdAt = isset($user['created_at']) ? strtotime($user['created_at']) : false;
$ageDays = $createdAt ? floor((time() - $createdAt) / 86400) : null;
if ($ageDays !== null && $ageDays < 30) {
    $score += 30;
    $reasons[] = '作成から30日未満の新規アカウントです。';
}

if ($following > 0 && $followers > 0) {
    $ratio = $following / max($followers, 1);
    if ($ratio >= 8) {
        $score += 25;
        $reasons[] = 'フォロー数に対してフォロワー数が少なく、比率が不自然です。';
    }
} elseif ($following >= 200 && $followers === 0) {
    $score += 30;
    $reasons[] = '大量フォローでフォロワーが0です。';
}

if ($tweetCount < 5) {
    $score += 15;
    $reasons[] = '投稿数が極端に少ないです。';
}

$desc = trim((string)($user['description'] ?? ''));
if ($desc === '') {
    $score += 10;
    $reasons[] = '自己紹介が空です。';
}

if (!empty($user['verified'])) {
    $score = max(0, $score - 20);
    $reasons[] = '認証済みアカウントのためリスクを減点しました。';
}

$score = max(0, min(100, $score));
if ($score >= 70) {
    $summary = '業者・ボット挙動の可能性が高めです。手動で投稿内容や相互関係を確認してください。';
} elseif ($score >= 40) {
    $summary = 'やや不自然な傾向があります。短期間での行動変化を確認してください。';
} else {
    $summary = '公開指標上は大きな不審点は少なめです。';
}

jsonResponse([
    'user' => [
        'id' => $user['id'] ?? '',
        'name' => $user['name'] ?? '',
        'username' => $user['username'] ?? '',
        'created_at' => $user['created_at'] ?? null,
        'public_metrics' => $metrics
    ],
    'score' => $score,
    'reasons' => $reasons,
    'summary' => $summary
]);
