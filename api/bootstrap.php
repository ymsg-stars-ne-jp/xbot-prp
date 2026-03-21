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
    if (empty($_SESSION['x_access_token'])) {
        jsonResponse(['message' => 'ログインが必要です。'], 401);
    }
}

function xApiGet(string $endpoint, string $accessToken, array $query = []): array {
    $url = 'https://api.x.com/2' . $endpoint;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]
    ]);

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

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        jsonResponse(['message' => 'X APIレスポンスの解析に失敗しました。'], 502);
    }

    return $decoded;
}
