<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_GET['state'], $_GET['code']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    jsonResponse(['message' => 'OAuth state が不正です。'], 400);
}

$clientId = envOrFail('X_CLIENT_ID');
$clientSecret = envOrFail('X_CLIENT_SECRET');
$redirectUri = envOrFail('X_REDIRECT_URI');
$codeVerifier = $_SESSION['oauth_code_verifier'] ?? '';
if ($codeVerifier === '') {
    jsonResponse(['message' => 'code_verifier が見つかりません。'], 400);
}

$payload = http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $_GET['code'],
    'redirect_uri' => $redirectUri,
    'client_id' => $clientId,
    'code_verifier' => $codeVerifier
]);

$ch = curl_init('https://api.x.com/2/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
    ]
]);

$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false || $status >= 400) {
    jsonResponse([
        'message' => 'トークン取得に失敗しました。',
        'status' => $status,
        'raw' => $res
    ], 502);
}

$tokenData = json_decode($res, true);
if (!is_array($tokenData) || empty($tokenData['access_token'])) {
    jsonResponse(['message' => 'トークンレスポンスが不正です。'], 502);
}

$accessToken = $tokenData['access_token'];
$userResp = xApiGet('/users/me', $accessToken, [
    'user.fields' => 'created_at,description,public_metrics,verified,profile_image_url'
]);

if (isset($userResp['_http_status'])) {
    jsonResponse([
        'message' => 'ログインユーザー情報の取得に失敗しました。',
        'status' => (int)$userResp['_http_status'],
        'error' => $userResp['_error'] ?? null
    ], (int)$userResp['_http_status']);
}

if (empty($userResp['data'])) {
    jsonResponse(['message' => 'ログインユーザー情報の取得に失敗しました。'], 502);
}

$_SESSION['x_access_token'] = $accessToken;
$_SESSION['x_refresh_token'] = $tokenData['refresh_token'] ?? null;
$_SESSION['x_user'] = $userResp['data'];
unset($_SESSION['oauth_state'], $_SESSION['oauth_code_verifier']);

header('Location: ../public/index.html');
exit;
