<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gmail.php';
$gmail_connected = gmail_token_exists();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Personal Assistant</title>
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Assistant">
    <link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%231a1a2e' width='100' height='100' rx='20'/><text x='50' y='62' text-anchor='middle' fill='white' font-size='36' font-family='sans-serif'>PA</text></svg>">
</head>
<body>
    <div id="app">
        <header id="header">
            <div class="header-left">
                <h1>Assistant</h1>
            </div>
            <div class="header-right">
                <button id="gmail-btn" class="header-btn <?= $gmail_connected ? 'connected' : '' ?>" title="<?= $gmail_connected ? 'Gmail connected' : 'Connect Gmail' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </button>
                <a href="logout.php" class="header-btn" title="Log out">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </header>

        <main id="chat-container">
            <div id="chat-messages">
                <div class="message assistant-message">
                    <div class="message-content">Hey! I'm your personal assistant. I can help you with tasks, goals, reminders, checking your Gmail, or anything else you need.</div>
                </div>
            </div>
            <div id="typing-indicator" class="hidden">
                <div class="message assistant-message">
                    <div class="message-content typing">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        </main>

        <footer id="input-area">
            <form id="chat-form">
                <textarea id="chat-input" placeholder="Type a message..." rows="1" maxlength="2000"></textarea>
                <button type="submit" id="send-btn" disabled>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </form>
        </footer>
    </div>

    <script>
        window.APP_CONFIG = {
            gmailConnected: <?= $gmail_connected ? 'true' : 'false' ?>
        };
    </script>
    <script src="app.js"></script>
</body>
</html>
