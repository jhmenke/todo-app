<?php
// ─── App config ───────────────────────────────────────────────
define('APP_NAME', 'MyTasks');
define('APP_URL',  'http://localhost/todo-app');  // no trailing slash

// ─── Database ─────────────────────────────────────────────────
define('DB_PATH', __DIR__ . '/db/todos.db');

// ─── Cron ──────────────────────────────────────────────────────
define('CRON_LOG_PATH',    __DIR__ . '/cron.log');
define('CRON_DAILY_LIMIT', 100);  // max emails sent per day across all users

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

// ─── Database connection (singleton) ──────────────────────────
function db(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA journal_mode = WAL');
        db_init($db);
    }
    return $db;
}

function db_init(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            email           TEXT UNIQUE NOT NULL,
            password_hash   TEXT NOT NULL,
            notify_minutes  INTEGER DEFAULT 5,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS tags (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name     TEXT NOT NULL,
            color    TEXT DEFAULT '#6366f1'
        );
        CREATE TABLE IF NOT EXISTS todos (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id          INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title            TEXT NOT NULL,
            active_at        DATETIME,
            completed_at     DATETIME,
            recur_type       TEXT DEFAULT NULL,
            recur_interval   INTEGER DEFAULT 1,
            recur_days       TEXT DEFAULT NULL,
            recur_ends_at    DATETIME DEFAULT NULL,
            recur_parent_id  INTEGER REFERENCES todos(id),
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS todo_tags (
            todo_id  INTEGER NOT NULL REFERENCES todos(id) ON DELETE CASCADE,
            tag_id   INTEGER NOT NULL REFERENCES tags(id)  ON DELETE CASCADE,
            PRIMARY KEY (todo_id, tag_id)
        );
        CREATE TABLE IF NOT EXISTS comments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            todo_id    INTEGER NOT NULL REFERENCES todos(id) ON DELETE CASCADE,
            user_id    INTEGER NOT NULL REFERENCES users(id),
            body       TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS todo_shares (
            todo_id   INTEGER NOT NULL REFERENCES todos(id) ON DELETE CASCADE,
            user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            shared_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (todo_id, user_id)
        );
        CREATE TABLE IF NOT EXISTS notifications_sent (
            todo_id  INTEGER NOT NULL REFERENCES todos(id) ON DELETE CASCADE,
            user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            sent_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (todo_id, user_id)
        );
        CREATE TABLE IF NOT EXISTS files (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            todo_id     INTEGER NOT NULL REFERENCES todos(id) ON DELETE CASCADE,
            uploaded_by INTEGER NOT NULL REFERENCES users(id),
            filename    TEXT NOT NULL,
            stored_as   TEXT NOT NULL,
            mime_type   TEXT,
            size_bytes  INTEGER,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
    // Migrations: add columns if they don't exist yet (SQLite ignores duplicates via try/catch)
    foreach ([
        "ALTER TABLE users ADD COLUMN telegram_chat_id TEXT",
        "ALTER TABLE users ADD COLUMN notify_channel TEXT NOT NULL DEFAULT 'telegram'",
    ] as $sql) {
        try { $db->exec($sql); } catch (PDOException) {}
    }
}

// ─── Telegram sending ──────────────────────────────────────────
function send_telegram(string $chat_id, string $text): bool {
    if (!TELEGRAM_BOT_TOKEN) return false;
    $url     = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $payload = json_encode(['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML']);
    $ctx     = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 10,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    return $result !== false && str_contains($result, '"ok":true');
}

// ─── Email sending ────────────────────────────────────────────
function send_email(string $to, string $subject, string $html): bool {
    if (SMTP_HOST !== '') {
        return smtp_send($to, $subject, $html);
    }
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    return mail($to, $subject, $html, $headers);
}

function smtp_send(string $to, string $subject, string $html): bool {
    $host  = SMTP_HOST;
    $port  = SMTP_PORT;
    $errno = $errstr = null;

    // Port 465 = implicit SSL (SMTPS); port 587 = plaintext then STARTTLS
    if ($port === 465) {
        $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 10);
    } else {
        $sock = @fsockopen($host, $port, $errno, $errstr, 10);
    }
    if (!$sock) return false;

    $read = fn() => fgets($sock, 512);
    $send = fn(string $cmd) => fputs($sock, $cmd . "\r\n");

    $read(); // 220 greeting
    $send("EHLO localhost");
    while (($line = $read()) && substr($line, 3, 1) === '-');

    if ($port === 587) {
        $send("STARTTLS");
        $read();
        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $send("EHLO localhost");
        while (($line = $read()) && substr($line, 3, 1) === '-');
    }

    $send("AUTH LOGIN");
    $read();
    $send(base64_encode(SMTP_USER));
    $read();
    $send(base64_encode(SMTP_PASS));
    $read();

    $from = SMTP_FROM;
    $send("MAIL FROM:<{$from}>");
    $read();
    $send("RCPT TO:<{$to}>");
    $read();
    $send("DATA");
    $read();

    $msg  = "From: " . SMTP_FROM_NAME . " <{$from}>\r\n";
    $msg .= "To: {$to}\r\n";
    $msg .= "Subject: {$subject}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $msg .= $html . "\r\n";
    $send($msg . ".");
    $read();
    $send("QUIT");
    fclose($sock);
    return true;
}

// ─── Auth helpers ─────────────────────────────────────────────
function session_user(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user'] ?? null;
}

function require_auth(): array {
    $user = session_user();
    if (!$user) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }
        header('Location: ' . APP_URL . '/auth.php');
        exit;
    }
    return $user;
}

function json_out(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_in(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
