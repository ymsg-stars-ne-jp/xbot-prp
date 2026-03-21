<?php
require_once __DIR__ . '/bootstrap.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['message' => 'Method Not Allowed'], 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$usernames = $body['usernames'] ?? [];
$settings = $body['settings'] ?? [];

if (!is_array($usernames) || count($usernames) === 0) {
    jsonResponse(['message' => '監視対象ユーザー名が空です。'], 422);
}

$threshold = (int)($settings['riskThreshold'] ?? 75);
$autoBlock = !empty($settings['autoBlock']);
$autoReport = !empty($settings['autoReport']);
$autoReportOnlyWhenBlocked = !empty($settings['autoReportOnlyWhenBlocked']);

$accessToken = $_SESSION['x_access_token'];
$results = [];

foreach ($usernames as $rawName) {
    $username = ltrim(trim((string)$rawName), '@');
    if ($username === '') {
        continue;
    }

    $userResp = xApiGet('/users/by/username/' . rawurlencode($username), $accessToken, [
        'user.fields' => 'created_at,description,public_metrics,verified,location,url'
    ]);

    if (empty($userResp['data'])) {
        $results[] = [
            'username' => $username,
            'status' => 'not_found'
        ];
        continue;
    }

    $user = $userResp['data'];
    $evaluated = evaluateRisk($user);
    $action = [
        'blocked' => false,
        'reported' => false
    ];

    $isRisky = $evaluated['score'] >= $threshold;
    if ($isRisky && $autoBlock) {
        $blockedRes = autoBlockUser($user['id']);
        $action['blocked'] = !empty($blockedRes['success']);
    }

    if ($isRisky && $autoReport && (!$autoReportOnlyWhenBlocked || $action['blocked'])) {
        $reportRes = autoReportIllegal([
            'target_user_id' => $user['id'] ?? '',
            'target_username' => $user['username'] ?? $username,
            'target_name' => $user['name'] ?? '',
            'risk_score' => $evaluated['score'],
            'reasons' => $evaluated['reasons'],
            'summary' => $evaluated['summary']
        ]);
        $action['reported'] = !empty($reportRes['success']);
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

appendJsonLog('monitor_events.jsonl', [
    'timestamp' => gmdate('c'),
    'settings' => [
        'riskThreshold' => $threshold,
        'autoBlock' => $autoBlock,
        'autoReport' => $autoReport,
        'autoReportOnlyWhenBlocked' => $autoReportOnlyWhenBlocked
    ],
    'checked_count' => count($results)
]);

jsonResponse([
    'checked_at' => gmdate('c'),
    'results' => $results
]);
