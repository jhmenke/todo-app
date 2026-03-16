<?php
// ─── App ──────────────────────────────────────────────────────
define('APP_NAME', 'MyTasks');
define('APP_URL',  'http://localhost/todo-app');  // no trailing slash

// ─── Database ─────────────────────────────────────────────────
define('DB_PATH', __DIR__ . '/db/todos.db');

// ─── Cron ─────────────────────────────────────────────────────
define('CRON_LOG_PATH',    __DIR__ . '/cron.log');
define('CRON_DAILY_LIMIT', 100);  // max notifications per day across all users

// ─── Telegram ─────────────────────────────────────────────────
define('TELEGRAM_BOT_TOKEN', '');  // set to your bot token from @BotFather

// ─── Email ────────────────────────────────────────────────────
// Set SMTP_HOST to '' to use PHP mail() instead
define('SMTP_HOST',      '');
define('SMTP_PORT',      587);
define('SMTP_USER',      'you@example.com');
define('SMTP_PASS',      'your-smtp-password');
define('SMTP_FROM',      'you@example.com');
define('SMTP_FROM_NAME', APP_NAME);
