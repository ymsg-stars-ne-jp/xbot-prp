<?php
require_once __DIR__ . '/bootstrap.php';
rateLimit('oauth_start', 30, 60);

$clientId = envOrFail('X_CLIENT_ID');
$redirectUri = envOrFail('X_REDIRECT_URI');

$state = bin2hex(random_bytes(16));
$codeVerifier = bin2hex(random_bytes(32));
$codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_code_verifier'] = $codeVerifier;

$params = [
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'scope' => 'tweet.read users.read follows.read block.write offline.access',
    'state' => $state,
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => 'S256'
];

header('Location: https://x.com/i/oauth2/authorize?' . http_build_query($params));
exit;
