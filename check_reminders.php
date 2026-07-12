<?php
// Cron endpoint: hit by cron-job.org every 15-30 minutes
// Secured by a simple shared secret token

$CRON_SECRET = 'CHANGE_THIS_TO_A_RANDOM_STRING';

if (isset($_GET['token']) && $_GET['token'] === $CRON_SECRET) {
    // OK
} elseif (isset($_SERVER['HTTP_X_CRON_SECRET']) && $_SERVER['HTTP_X_CRON_SECRET'] === $CRON_SECRET) {
    // OK
} else {
    http_response_code(403);
    die('Forbidden');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = get_db();

// Find due, unsent reminders
$stmt = $db->query("SELECT id, message, remind_at FROM reminders WHERE sent = 0 AND remind_at <= NOW() ORDER BY remind_at ASC");
$due = $stmt->fetchAll();

if (empty($due)) {
    echo json_encode(['status' => 'ok', 'sent' => 0]);
    exit;
}

$sent_count = 0;
foreach ($due as $reminder) {
    $subject = 'Reminder: ' . mb_substr($reminder['message'], 0, 80);
    $body = "Reminder for " . $reminder['remind_at'] . ":\n\n" . $reminder['message'] . "\n\n— " . APP_NAME;

    $headers = [
        'From: ' . APP_NAME . ' <' . OWNER_EMAIL . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $mail_sent = mail(OWNER_EMAIL, $subject, $body, implode("\r\n", $headers));

    if ($mail_sent) {
        $stmt = $db->prepare("UPDATE reminders SET sent = 1 WHERE id = ?");
        $stmt->execute([$reminder['id']]);
        $sent_count++;
    }
}

echo json_encode(['status' => 'ok', 'sent' => $sent_count]);
