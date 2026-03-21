<?php
session_start();

function loadEnv(string $path): void {
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');

function envOrFail(string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'message' => "環境変数 {$key} が未設定です。"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $value;
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuth(): void {
    if (empty($_SESSION['x_access_token']) || empty($_SESSION['x_user']['id'])) {
        jsonResponse(['message' => 'ログインが必要です。'], 401);
    }
}

function xApiRequest(string $method, string $endpoint, string $accessToken, array $query = [], ?array $jsonBody = null): array {
    $url = 'https://api.x.com/2' . $endpoint;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method)
    ];

    if ($jsonBody !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        jsonResponse([
            'message' => 'X API呼び出しに失敗しました。',
            'status' => $status,
            'raw' => $response
        ], 502);
    }

    if ($response === '' || $response === null) {
        return ['ok' => true];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['ok' => true, 'raw' => $response];
    }

    return $decoded;
}

function xApiGet(string $endpoint, string $accessToken, array $query = []): array {
    return xApiRequest('GET', $endpoint, $accessToken, $query);
}

function xApiPost(string $endpoint, string $accessToken, array $body = []): array {
    return xApiRequest('POST', $endpoint, $accessToken, [], $body);
}

function evaluateRisk(array $user): array {
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

    return [
        'score' => $score,
        'reasons' => $reasons,
        'summary' => $summary,
        'metrics' => $metrics
    ];
}

function appendJsonLog(string $filename, array $payload): void {
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $line = json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($dir . '/' . $filename, $line, FILE_APPEND | LOCK_EX);
}

function autoBlockUser(string $targetUserId): array {
    requireAuth();
    $accessToken = $_SESSION['x_access_token'];
    $authUserId = $_SESSION['x_user']['id'];

    $res = xApiPost('/users/' . rawurlencode($authUserId) . '/blocking', $accessToken, [
        'target_user_id' => $targetUserId
    ]);

    return [
        'success' => true,
        'response' => $res
    ];
}

function autoReportIllegal(array $reportPayload): array {
    $payload = [
        'timestamp' => gmdate('c'),
        'actor_user' => $_SESSION['x_user']['username'] ?? '',
        'data' => $reportPayload
    ];

    appendJsonLog('illegal_reports.jsonl', $payload);

    $webhook = getenv('REPORT_WEBHOOK_URL');
    if ($webhook !== false && $webhook !== '') {
        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $response !== false && $status < 400,
            'status' => $status,
            'webhook_called' => true
        ];
    }

    return [
        'success' => true,
        'webhook_called' => false
    ];
}
