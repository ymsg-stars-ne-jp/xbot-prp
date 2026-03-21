<?php
function hardenSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

function applySecurityHeaders(): void {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; connect-src 'self' https://api.x.com https://x.com; img-src 'self' data: https:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://x.com;");
}

function ensureEnvFile(): void {
    $root = dirname(__DIR__);
    $envPath = $root . '/.env';
    $examplePath = $root . '/.env.example';
    if (!is_file($envPath) && is_file($examplePath)) {
        copy($examplePath, $envPath);
        @chmod($envPath, 0600);
    }
}

hardenSession();
applySecurityHeaders();
ensureEnvFile();

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
        echo json_encode(['message' => "環境変数 {$key} が未設定です。"], JSON_UNESCAPED_UNICODE);
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

function requireMethod(string $method): void {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        jsonResponse(['message' => 'Method Not Allowed'], 405);
    }
}

function clientKey(): string {
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . session_id());
}

function rateLimit(string $bucket, int $max, int $windowSec): void {
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/ratelimit_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $bucket) . '.json';
    $now = time();
    $entries = [];

    if (is_file($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $entries = $decoded;
        }
    }

    $key = clientKey();
    $list = $entries[$key] ?? [];
    $list = array_values(array_filter($list, fn($ts) => is_int($ts) && ($now - $ts) < $windowSec));

    if (count($list) >= $max) {
        jsonResponse(['message' => 'アクセス頻度が高すぎます。しばらく待って再試行してください。'], 429);
    }

    $list[] = $now;
    $entries[$key] = $list;
    file_put_contents($file, json_encode($entries), LOCK_EX);
}

function requireAuth(): void {
    if (empty($_SESSION['x_access_token']) || empty($_SESSION['x_user']['id'])) {
        jsonResponse(['message' => 'ログインが必要です。'], 401);
    }
}

function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($headerToken) || !is_string($sessionToken) || $headerToken === '' || !hash_equals($sessionToken, $headerToken)) {
        jsonResponse(['message' => 'CSRFトークンが不正です。'], 403);
    }
}

function validUsername(string $username): bool {
    return (bool)preg_match('/^[A-Za-z0-9_]{1,15}$/', $username);
}

function xApiRequest(string $method, string $endpoint, string $accessToken, array $query = [], ?array $jsonBody = null): array {
    $url = 'https://api.x.com/2' . $endpoint;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_TIMEOUT => 15
    ];

    if ($jsonBody !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        jsonResponse(['message' => '外部API処理に失敗しました。時間をおいて再実行してください。'], 502);
    }

    if ($response === '' || $response === null) {
        return ['ok' => true];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['ok' => true];
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

    if ($following > 0 && $followers > 0 && ($following / max($followers, 1)) >= 8) {
        $score += 25;
        $reasons[] = 'フォロー数に対してフォロワー数が少なく、比率が不自然です。';
    } elseif ($following >= 200 && $followers === 0) {
        $score += 30;
        $reasons[] = '大量フォローでフォロワーが0です。';
    }

    if ($tweetCount < 5) {
        $score += 15;
        $reasons[] = '投稿数が極端に少ないです。';
    }

    if (trim((string)($user['description'] ?? '')) === '') {
        $score += 10;
        $reasons[] = '自己紹介が空です。';
    }

    if (!empty($user['verified'])) {
        $score = max(0, $score - 20);
        $reasons[] = '認証済みアカウントのためリスクを減点しました。';
    }

    $score = max(0, min(100, $score));
    $summary = $score >= 70
        ? '業者・ボット挙動の可能性が高めです。'
        : ($score >= 40 ? 'やや不自然な傾向があります。' : '公開指標上は大きな不審点は少なめです。');

    return ['score' => $score, 'reasons' => $reasons, 'summary' => $summary, 'metrics' => $metrics];
}

function appendJsonLog(string $filename, array $payload): void {
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($dir . '/' . $filename, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function autoBlockUser(string $targetUserId): array {
    requireAuth();
    $res = xApiPost('/users/' . rawurlencode($_SESSION['x_user']['id']) . '/blocking', $_SESSION['x_access_token'], ['target_user_id' => $targetUserId]);
    return ['success' => true, 'response' => $res];
}

function autoReportIllegal(array $reportPayload): array {
    $payload = ['timestamp' => gmdate('c'), 'actor_user' => $_SESSION['x_user']['username'] ?? '', 'data' => $reportPayload];
    appendJsonLog('illegal_reports.jsonl', $payload);

    $webhook = getenv('REPORT_WEBHOOK_URL');
    if (is_string($webhook) && str_starts_with($webhook, 'https://')) {
        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['success' => $response !== false && $status < 400, 'webhook_called' => true, 'status' => $status];
    }

    return ['success' => true, 'webhook_called' => false];
}
