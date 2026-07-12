<?php
// === Database ===
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// === Gemini API ===
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
define('GEMINI_MODEL', 'gemini-2.0-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY);

// === Login ===
// Generate with: password_hash('your_password', PASSWORD_BCRYPT)
define('APP_PASSWORD_HASH', 'YOUR_BCRYPT_HASH_HERE');

// === Email (for reminder notifications) ===
define('OWNER_EMAIL', 'your-email@gmail.com');
define('APP_NAME', 'Personal Assistant');

// === Gmail OAuth2 ===
define('GMAIL_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GMAIL_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GMAIL_REDIRECT_URI', 'https://yourdomain.com/oauth_callback.php');
define('GMAIL_SCOPES', 'https://www.googleapis.com/auth/gmail.readonly');

// === Rate Limiting ===
define('RATE_LIMIT_MAX', 20);
define('RATE_LIMIT_WINDOW', 60); // seconds

// === Chat Context ===
define('CHAT_CONTEXT_LIMIT', 50);

// === Brute Force Protection ===
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds
