<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'];

    // Check brute force
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lockout_until'] = 0;
    }

    if (time() < $_SESSION['login_lockout_until']) {
        $remaining = $_SESSION['login_lockout_until'] - time();
        $error = "Too many attempts. Try again in " . ceil($remaining / 60) . " minute(s).";
    } elseif (password_verify($password, APP_PASSWORD_HASH)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['login_attempts'] = 0;
        session_regenerate_id(true);
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $_SESSION['login_lockout_until'] = time() + LOGIN_LOCKOUT_TIME;
            $error = "Too many failed attempts. Locked out for 15 minutes.";
        } else {
            $remaining = MAX_LOGIN_ATTEMPTS - $_SESSION['login_attempts'];
            $error = "Wrong password. $remaining attempt(s) remaining.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Login — Personal Assistant</title>
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-icon">PA</div>
            <h1>Personal Assistant</h1>
            <form method="POST" action="login.php">
                <input type="password" name="password" placeholder="Enter password" autofocus required autocomplete="current-password">
                <?php if (!empty($error)): ?>
                    <div class="login-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <button type="submit">Log In</button>
            </form>
        </div>
    </div>
</body>
</html>
