<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function gmail_token_exists() {
    $db = get_db();
    $stmt = $db->query("SELECT id FROM gmail_tokens LIMIT 1");
    return $stmt->fetch() !== false;
}

function get_gmail_tokens() {
    $db = get_db();
    $stmt = $db->query("SELECT * FROM gmail_tokens ORDER BY id DESC LIMIT 1");
    return $stmt->fetch();
}

function save_gmail_tokens($access_token, $refresh_token, $expires_in) {
    $db = get_db();
    $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
    $stmt = $db->prepare("DELETE FROM gmail_tokens");
    $stmt->execute();
    $stmt = $db->prepare("INSERT INTO gmail_tokens (access_token, refresh_token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$access_token, $refresh_token, $expires_at]);
}

function refresh_gmail_token() {
    $tokens = get_gmail_tokens();
    if (!$tokens || empty($tokens['refresh_token'])) return false;

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => GMAIL_CLIENT_ID,
            'client_secret' => GMAIL_CLIENT_SECRET,
            'refresh_token' => $tokens['refresh_token'],
            'grant_type' => 'refresh_token',
        ]),
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($response['access_token'])) {
        save_gmail_tokens($response['access_token'], $tokens['refresh_token'], $response['expires_in']);
        return $response['access_token'];
    }
    return false;
}

function get_valid_access_token() {
    $tokens = get_gmail_tokens();
    if (!$tokens) return false;
    if (strtotime($tokens['expires_at']) <= time() + 60) {
        return refresh_gmail_token();
    }
    return $tokens['access_token'];
}

function gmail_api_get($endpoint, $params = []) {
    $token = get_valid_access_token();
    if (!$token) return ['error' => 'Gmail not connected'];

    $url = 'https://www.googleapis.com/gmail/v1/users/me/' . $endpoint;
    if ($params) $url .= '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response;
}

function gmail_check_inbox() {
    $response = gmail_api_get('messages', ['q' => 'is:unread', 'maxResults' => 10]);
    if (isset($response['error'])) return $response['error'];
    if (empty($response['messages'])) return 'No unread emails.';

    $results = [];
    foreach ($response['messages'] as $msg) {
        $detail = gmail_api_get('messages/' . $msg['id'], ['format' => 'metadata', 'metadataHeaders' => 'From,Subject,Date']);
        if (isset($detail['payload']['headers'])) {
            $headers = [];
            foreach ($detail['payload']['headers'] as $h) {
                $headers[strtolower($h['name'])] = $h['value'];
            }
            $results[] = [
                'id' => $msg['id'],
                'from' => $headers['from'] ?? 'Unknown',
                'subject' => $headers['subject'] ?? '(no subject)',
                'date' => $headers['date'] ?? '',
                'snippet' => $detail['snippet'] ?? '',
            ];
        }
    }

    if (empty($results)) return 'No unread emails.';

    $output = "Recent unread emails:\n";
    foreach ($results as $i => $r) {
        $output .= sprintf("%d. From: %s\n   Subject: %s\n   Snippet: %s\n\n", $i + 1, $r['from'], $r['subject'], mb_substr($r['snippet'], 0, 120));
    }
    return trim($output);
}

function gmail_search_emails($query) {
    $response = gmail_api_get('messages', ['q' => $query, 'maxResults' => 5]);
    if (isset($response['error'])) return $response['error'];
    if (empty($response['messages'])) return 'No emails found matching that search.';

    $output = "Search results:\n";
    $i = 1;
    foreach ($response['messages'] as $msg) {
        $detail = gmail_api_get('messages/' . $msg['id'], ['format' => 'metadata', 'metadataHeaders' => 'From,Subject,Date']);
        if (isset($detail['payload']['headers'])) {
            $headers = [];
            foreach ($detail['payload']['headers'] as $h) {
                $headers[strtolower($h['name'])] = $h['value'];
            }
            $output .= sprintf("%d. From: %s\n   Subject: %s\n   Snippet: %s\n\n", $i++, $headers['from'] ?? 'Unknown', $headers['subject'] ?? '(no subject)', mb_substr($detail['snippet'] ?? '', 0, 120));
        }
    }
    return trim($output);
}

function gmail_summarize_email($email_id) {
    $detail = gmail_api_get('messages/' . $email_id, ['format' => 'full']);
    if (isset($detail['error'])) return $detail['error'];

    $headers = [];
    if (isset($detail['payload']['headers'])) {
        foreach ($detail['payload']['headers'] as $h) {
            $headers[strtolower($h['name'])] = $h['value'];
        }
    }

    $body = extract_email_body($detail['payload'] ?? []);

    $output = "Email details:\n";
    $output .= "From: " . ($headers['from'] ?? 'Unknown') . "\n";
    $output .= "Subject: " . ($headers['subject'] ?? '(no subject)') . "\n";
    $output .= "Date: " . ($headers['date'] ?? 'Unknown') . "\n";
    $output .= "Body:\n" . mb_substr($body, 0, 2000);
    return $output;
}

function extract_email_body($payload) {
    $body = '';
    if (!empty($payload['body']['data'])) {
        $body = base64_decode(strtr($payload['body']['data'], '-_', '+/'));
    }
    if (empty($body) && !empty($payload['parts'])) {
        foreach ($payload['parts'] as $part) {
            if ($part['mimeType'] === 'text/plain' && !empty($part['body']['data'])) {
                $body = base64_decode(strtr($part['body']['data'], '-_', '+/'));
                break;
            }
            if (!empty($part['parts'])) {
                $body = extract_email_body($part);
                if ($body) break;
            }
        }
    }
    return $body;
}
