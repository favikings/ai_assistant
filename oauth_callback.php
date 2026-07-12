<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gmail.php';

if (empty($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

$code = $_GET['code'] ?? null;
if (!$code) {
    echo "No authorization code received.";
    exit;
}

// Exchange code for tokens
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => GMAIL_CLIENT_ID,
        'client_secret' => GMAIL_CLIENT_SECRET,
        'redirect_uri' => GMAIL_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]),
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (isset($response['access_token'])) {
    save_gmail_tokens(
        $response['access_token'],
        $response['refresh_token'] ?? '',
        $response['expires_in'] ?? 3600
    );
    header('Location: index.php?gmail=connected');
    exit;
} else {
    echo "Failed to connect Gmail. " . htmlspecialchars($response['error_description'] ?? 'Unknown error');
}
