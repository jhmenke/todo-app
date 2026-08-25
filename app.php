<?php
require_once __DIR__ . '/config.php';

if (!defined('ALLOW_REGISTRATION')) define('ALLOW_REGISTRATION', true);
if (!defined('MAX_UPLOAD_BYTES'))   define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);
if (!defined('CRON_SECRET'))        define('CRON_SECRET', '');

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
    // Migrations: add columns if they don't exist yet
    foreach ([
        "ALTER TABLE users ADD COLUMN telegram_chat_id TEXT",
        "ALTER TABLE users ADD COLUMN notify_channel TEXT NOT NULL DEFAULT 'telegram'",
        "ALTER TABLE todos ADD COLUMN priority INTEGER NOT NULL DEFAULT 4",
        "ALTER TABLE todos ADD COLUMN parent_id INTEGER REFERENCES todos(id) ON DELETE CASCADE",
        "ALTER TABLE users ADD COLUMN locale TEXT NOT NULL DEFAULT 'en'",
    ] as $sql) {
        try { $db->exec($sql); } catch (PDOException) {}
    }
}

// ─── Telegram ─────────────────────────────────────────────────
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

// ─── Email ────────────────────────────────────────────────────
function send_email(string $to, string $subject, string $html): bool {
    if (SMTP_HOST !== '') {
        return smtp_send($to, $subject, $html);
    }
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    return mail($to, smtp_header_safe($subject), $html, $headers);
}

function smtp_header_safe(string $s): string {
    return str_replace(["\r", "\n"], '', $s);
}

function smtp_send(string $to, string $subject, string $html): bool {
    $host  = SMTP_HOST;
    $port  = (int) SMTP_PORT;
    $errno = $errstr = null;

    if ($port === 465) {
        $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 10);
    } else {
        $sock = @fsockopen($host, $port, $errno, $errstr, 10);
    }
    if (!$sock) return false;

    stream_set_timeout($sock, 10);

    $read = function () use ($sock): string|false {
        return fgets($sock, 512);
    };
    $send = function (string $cmd) use ($sock): void {
        fwrite($sock, $cmd . "\r\n");
    };
    $expect = function (string $code) use ($read): bool {
        $line = $read();
        return is_string($line) && str_starts_with($line, $code);
    };
    $expect_ehlo = function () use ($read): bool {
        while (($line = $read()) !== false) {
            if (!str_starts_with($line, '250')) return false;
            if (substr($line, 3, 1) !== '-') return true;
        }
        return false;
    };

    $ok = $expect('220');
    if ($ok) { $send('EHLO localhost'); $ok = $expect_ehlo(); }

    if ($ok && $port === 587) {
        $send('STARTTLS');
        $ok = $expect('220');
        if ($ok) {
            $ok = (bool) @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }
        if ($ok) { $send('EHLO localhost'); $ok = $expect_ehlo(); }
    }

    if ($ok) { $send('AUTH LOGIN'); $ok = $expect('334'); }
    if ($ok) { $send(base64_encode(SMTP_USER)); $ok = $expect('334'); }
    if ($ok) { $send(base64_encode(SMTP_PASS)); $ok = $expect('235'); }

    $from = SMTP_FROM;
    if ($ok) { $send("MAIL FROM:<{$from}>"); $ok = $expect('250'); }
    if ($ok) { $send("RCPT TO:<{$to}>"); $ok = $expect('250'); }
    if ($ok) { $send('DATA'); $ok = $expect('354'); }

    if ($ok) {
        $msg  = 'From: ' . smtp_header_safe(SMTP_FROM_NAME) . " <{$from}>\r\n";
        $msg .= 'To: ' . smtp_header_safe($to) . "\r\n";
        $msg .= 'Subject: ' . smtp_header_safe($subject) . "\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $msg .= $html . "\r\n";
        $send($msg . '.');
        $ok = $expect('250');
    }

    $send('QUIT');
    fclose($sock);
    return $ok;
}

// ─── Session / Auth ───────────────────────────────────────────
function session_cookie_path(): string {
    $path = parse_url(APP_URL, PHP_URL_PATH);
    if (!is_string($path) || $path === '') return '/';
    return rtrim($path, '/') ?: '/';
}

function start_session(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    $lifetime = 30 * 24 * 60 * 60; // 30 days
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => session_cookie_path(),
        'secure'   => str_starts_with(APP_URL, 'https://'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function session_user(): ?array {
    start_session();
    return $_SESSION['user'] ?? null;
}

function todo_url(int $todo_id): string {
    return rtrim(APP_URL, '/') . '/?todo=' . $todo_id;
}

function safe_next(?string $next): string {
    $home = rtrim(APP_URL, '/') . '/';
    if (!is_string($next) || $next === '') return $home;
    if (preg_match('#^https?://#i', $next)) {
        return str_starts_with($next, $home) || rtrim($next, '/') === rtrim(APP_URL, '/') ? $next : $home;
    }
    if ($next[0] !== '/' || str_contains($next, "\n") || str_contains($next, "\r")) return $home;
    $app = parse_url(APP_URL, PHP_URL_PATH);
    $app = is_string($app) ? rtrim($app, '/') : '';
    $path = parse_url($next, PHP_URL_PATH) ?? '';
    if ($app !== '' && $path !== '' && $path !== $app && !str_starts_with($path, $app . '/')) {
        return $home;
    }
    return $next;
}

function require_auth(): array {
    $user = session_user();
    if (!$user) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthenticated']);
            exit;
        }
        $next = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . rtrim(APP_URL, '/') . '/auth.php?next=' . rawurlencode($next));
        exit;
    }
    return $user;
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token = null): bool {
    $token ??= $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

function registration_open(): bool {
    if (ALLOW_REGISTRATION) return true;
    try {
        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    } catch (Throwable) {
        return true;
    }
}

function login_user(array $user): void {
    session_regenerate_id(true);
    unset($_SESSION['_csrf']);
    $_SESSION['user'] = [
        'id'             => $user['id'],
        'email'          => $user['email'],
        'notify_minutes' => $user['notify_minutes'] ?? 5,
        'locale'         => normalize_locale($user['locale'] ?? current_locale()),
    ];
    set_locale($_SESSION['user']['locale']);
}

function normalize_locale(?string $s): string {
    $s = strtolower(substr((string) $s, 0, 2));
    return $s === 'de' ? 'de' : 'en';
}

function detect_browser_locale(): string {
    $h = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    return preg_match('/\bde\b/i', $h) ? 'de' : 'en';
}

function current_locale(): string {
    if (PHP_SAPI !== 'cli') {
        start_session();
        if (!empty($_SESSION['user']['locale'])) {
            return normalize_locale($_SESSION['user']['locale']);
        }
    }
    if (!empty($_COOKIE['locale'])) {
        return normalize_locale($_COOKIE['locale']);
    }
    return detect_browser_locale();
}

function set_locale(string $locale): string {
    $locale = normalize_locale($locale);
    if (PHP_SAPI !== 'cli') {
        setcookie('locale', $locale, [
            'expires'  => time() + 365 * 24 * 3600,
            'path'     => session_cookie_path(),
            'secure'   => str_starts_with(APP_URL, 'https://'),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['locale'] = $locale;
    }
    start_session();
    if (!empty($_SESSION['user'])) {
        $_SESSION['user']['locale'] = $locale;
    }
    return $locale;
}

function i18n_dict(?string $locale = null): array {
    static $dicts = [];
    $locale = $locale ? normalize_locale($locale) : current_locale();
    if (!isset($dicts[$locale])) {
        $path = __DIR__ . '/lang/' . $locale . '.json';
        $dicts[$locale] = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }
    return $dicts[$locale];
}

function t(string $key, array $vars = [], ?string $locale = null): string {
    $locale = $locale ? normalize_locale($locale) : current_locale();
    $cur = i18n_dict($locale);
    foreach (explode('.', $key) as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) {
            return $locale !== 'en' ? t($key, $vars, 'en') : $key;
        }
        $cur = $cur[$p];
    }
    if (!is_string($cur)) return $key;
    foreach ($vars as $k => $v) {
        $cur = str_replace('{' . $k . '}', (string) $v, $cur);
    }
    return $cur;
}

function format_notify_when(string $active_at, string $locale = 'en'): string {
    $ts = strtotime($active_at) ?: time();
    if (normalize_locale($locale) === 'de') {
        return date('Y.m.d H:i', $ts);
    }
    return date('Y.m.d g:i A', $ts);
}

// ─── Access / files ───────────────────────────────────────────
function can_access_todo(int $uid, int $todo_id): bool {
    $stmt = db()->prepare('SELECT 1 FROM todos WHERE id=? AND (user_id=? OR EXISTS (SELECT 1 FROM todo_shares ts WHERE ts.todo_id=todos.id AND ts.user_id=?))');
    $stmt->execute([$todo_id, $uid, $uid]);
    return (bool) $stmt->fetch();
}

function allowed_upload_mimes(): array {
    return [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt'],
        'text/csv'   => ['csv'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/zip' => ['zip'],
        'application/x-zip-compressed' => ['zip'],
    ];
}

function delete_todo_uploads(int $todo_id): void {
    $stmt = db()->prepare('SELECT uploaded_by, stored_as FROM files WHERE todo_id = ?');
    $stmt->execute([$todo_id]);
    $dirs = [];
    foreach ($stmt as $row) {
        $dir  = __DIR__ . '/uploads/' . (int) $row['uploaded_by'] . '/' . $todo_id;
        $path = $dir . '/' . basename((string) $row['stored_as']);
        if (is_file($path)) @unlink($path);
        $dirs[$dir] = true;
    }
    foreach (array_keys($dirs) as $dir) {
        if (is_dir($dir)) @rmdir($dir);
    }
}

function content_disposition_attachment(string $filename): string {
    $ascii = preg_replace('/[\r\n"\\\\]/', '_', $filename) ?? 'file';
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $ascii) ?? 'file';
    if ($ascii === '') $ascii = 'file';
    return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
}

// ─── Utilities ────────────────────────────────────────────────
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
