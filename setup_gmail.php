<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/gmail.php';

if (gmail_token_exists()) {
    header('Location: index.php');
    exit;
}

$scope = urlencode(GMAIL_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID' ? GMAIL_SCOPES : 'https://www.googleapis.com/auth/gmail.readonly');
$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth'
    . '?client_id=' . urlencode(GMAIL_CLIENT_ID)
    . '&redirect_uri=' . urlencode(GMAIL_REDIRECT_URI)
    . '&response_type=code'
    . '&scope=' . $scope
    . '&access_type=offline'
    . '&prompt=consent';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Gmail — Personal Assistant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-icon">G</div>
            <h1>Connect Gmail</h1>
            <p style="color: var(--text-secondary); margin-bottom: 24px; font-size: 14px; line-height: 1.6;">
                This will give the assistant read-only access to your Gmail inbox. It can check and search emails but cannot send, reply, or modify anything.
            </p>
            <?php if (GMAIL_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID'): ?>
                <div style="background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 12px; padding: 16px; font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                    <strong style="color: var(--text-primary);">Setup required:</strong><br>
                    Before connecting Gmail, you need to configure OAuth credentials in <code>config.php</code>:
                    <ol style="margin: 8px 0 0 16px;">
                        <li>Create a Google Cloud project</li>
                        <li>Enable the Gmail API</li>
                        <li>Create OAuth 2.0 credentials</li>
                        <li>Add your domain to authorized redirect URIs</li>
                        <li>Update <code>GMAIL_CLIENT_ID</code>, <code>GMAIL_CLIENT_SECRET</code>, and <code>GMAIL_REDIRECT_URI</code> in config.php</li>
                    </ol>
                </div>
            <?php else: ?>
                <a href="<?= htmlspecialchars($auth_url) ?>" style="display: inline-block; padding: 14px 24px; background: var(--accent); color: white; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 16px;">
                    Sign in with Google
                </a>
            <?php endif; ?>
            <br><br>
            <a href="index.php" style="color: var(--text-secondary); font-size: 14px;">← Back to chat</a>
        </div>
    </div>
</body>
</html>
