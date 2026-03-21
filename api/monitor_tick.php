<?php
require_once __DIR__ . '/bootstrap.php';
requireMethod('POST');
rateLimit('monitor_tick', 30, 60);
verifyCsrf();
requireAuth();

$body = json_decode((string)file_get_contents('php://input'), true);
$usernames = $body['usernames'] ?? [];
$settings = $body['settings'] ?? [];

if (!is_array($usernames) || count($usernames) === 0 || count($usernames) > 100) {
    jsonResponse(['message' => '監視対象ユーザー名は1〜100件で指定してください。'], 422);
}

$threshold = max(1, min(100, (int)($settings['riskThreshold'] ?? 75)));
$autoBlock = !empty($settings['autoBlock']);
$autoReport = !empty($settings['autoReport']);
$onlyIfBlocked = !empty($settings['autoReportOnlyWhenBlocked']);

$results = [];
foreach ($usernames as $rawName) {
    $username = ltrim(trim((string)$rawName), '@');
    if (!validUsername($username)) {
        $results[] = ['username' => $username, 'status' => 'invalid'];
        continue;
    }

    $userResp = xApiGet('/users/by/username/' . rawurlencode($username), $_SESSION['x_access_token'], [
        'user.fields' => 'created_at,description,public_metrics,verified,location,url'
    ]);

    if (empty($userResp['data'])) {
        $results[] = ['username' => $username, 'status' => 'not_found'];
        continue;
    }

    $user = $userResp['data'];
    $evaluated = evaluateRisk($user);
    $isRisky = $evaluated['score'] >= $threshold;
    $action = ['blocked' => false, 'reported' => false];

    if ($isRisky && $autoBlock) {
        $action['blocked'] = !empty(autoBlockUser((string)($user['id'] ?? ''))['success']);
    }
    if ($isRisky && $autoReport && (!$onlyIfBlocked || $action['blocked'])) {
        $action['reported'] = !empty(autoReportIllegal([
            'target_user_id' => $user['id'] ?? '',
            'target_username' => $user['username'] ?? $username,
            'risk_score' => $evaluated['score'],
            'reasons' => $evaluated['reasons']
        ])['success']);
    }

    $results[] = [
        'username' => $user['username'] ?? $username,
        'user_id' => $user['id'] ?? '',
        'score' => $evaluated['score'],
        'summary' => $evaluated['summary'],
        'reasons' => $evaluated['reasons'],
        'action' => $action,
        'status' => $isRisky ? 'risky' : 'ok'
    ];
}

appendJsonLog('monitor_events.jsonl', ['timestamp' => gmdate('c'), 'checked_count' => count($results)]);
jsonResponse(['checked_at' => gmdate('c'), 'results' => $results]);
