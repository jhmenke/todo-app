<?php
// ─── App ──────────────────────────────────────────────────────
define('APP_NAME', 'MyTasks');
define('APP_URL',  'http://localhost/todo-app');  // no trailing slash — set to your public URL

// After creating your first account, set this to false to lock registration.
// The first user can always register even when this is false.
define('ALLOW_REGISTRATION', true);

// ─── Database ─────────────────────────────────────────────────
define('DB_PATH', __DIR__ . '/db/todos.db');

// ─── Uploads ──────────────────────────────────────────────────
define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);  // 20 MB

// ─── Cron ─────────────────────────────────────────────────────
define('CRON_LOG_PATH',    __DIR__ . '/cron.log');
define('CRON_DAILY_LIMIT', 100);  // max notifications per day across all users
// HTTP trigger: cron.php?key=SECRET — leave empty for CLI only (recommended)
define('CRON_SECRET', '');

// Stay signed in this long (cookie + server session). Shared hosts often wipe
// default /tmp sessions overnight; we also store a remember token in SQLite.
define('AUTH_LIFETIME', 90 * 24 * 60 * 60); // 90 days

// ─── Telegram ─────────────────────────────────────────────────
define('TELEGRAM_BOT_TOKEN', '');  // set to your bot token from @BotFather
// Optional public @username. Empty = fetched via getMe and cached.
define('TELEGRAM_BOT_USERNAME', '');
// Optional webhook secret. Empty = derived from the bot token.
// Set webhook: see DEPLOY.md
define('TELEGRAM_WEBHOOK_SECRET', '');

// ─── Email ────────────────────────────────────────────────────
// Set SMTP_HOST to '' to use PHP mail() instead
define('SMTP_HOST',      '');
define('SMTP_PORT',      587);
define('SMTP_USER',      'you@example.com');
define('SMTP_PASS',      'your-smtp-password');
define('SMTP_FROM',      'you@example.com');
define('SMTP_FROM_NAME', APP_NAME);
