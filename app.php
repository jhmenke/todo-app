<?php
require_once __DIR__ . '/config.php';

if (!defined('ALLOW_REGISTRATION')) define('ALLOW_REGISTRATION', true);
if (!defined('MAX_UPLOAD_BYTES'))   define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);
if (!defined('CRON_SECRET'))        define('CRON_SECRET', '');
if (!defined('AUTH_LIFETIME'))      define('AUTH_LIFETIME', 90 * 24 * 60 * 60); // 90 days
if (!defined('TELEGRAM_WEBHOOK_SECRET')) define('TELEGRAM_WEBHOOK_SECRET', '');
if (!defined('TELEGRAM_BOT_USERNAME')) define('TELEGRAM_BOT_USERNAME', '');

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
        CREATE TABLE IF NOT EXISTS remember_tokens (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash  TEXT NOT NULL UNIQUE,
            expires_at  DATETIME NOT NULL
        );
        CREATE TABLE IF NOT EXISTS app_meta (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS telegram_link_tokens (
            token_hash TEXT PRIMARY KEY,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            expires_at DATETIME NOT NULL
        );
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            token_hash TEXT PRIMARY KEY,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            expires_at DATETIME NOT NULL
        );
    ");
    // Migrations: add columns if they don't exist yet
    foreach ([
        "ALTER TABLE users ADD COLUMN telegram_chat_id TEXT",
        "ALTER TABLE users ADD COLUMN notify_channel TEXT NOT NULL DEFAULT 'telegram'",
        "ALTER TABLE todos ADD COLUMN priority INTEGER NOT NULL DEFAULT 4",
        "ALTER TABLE todos ADD COLUMN parent_id INTEGER REFERENCES todos(id) ON DELETE CASCADE",
        "ALTER TABLE users ADD COLUMN locale TEXT NOT NULL DEFAULT 'en'",
        "ALTER TABLE users ADD COLUMN display_name TEXT",
    ] as $sql) {
        try { $db->exec($sql); } catch (PDOException) {}
    }
    try {
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS users_display_name_lower ON users (lower(display_name)) WHERE display_name IS NOT NULL AND display_name != ''");
    } catch (PDOException) {}
}

// ─── Telegram ─────────────────────────────────────────────────
function send_telegram(string $chat_id, string $text): bool {
    $res = telegram_api('sendMessage', [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
    return is_array($res) && !empty($res['ok']);
}

function telegram_api(string $method, array $payload = []): ?array {
    if (!TELEGRAM_BOT_TOKEN) return null;
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/' . $method;
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => json_encode($payload),
        'timeout' => 15,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) return null;
    $data = json_decode($result, true);
    return is_array($data) ? $data : null;
}

function ics_escape(string $s): string {
    return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $s);
}

function ics_filename(string $title): string {
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $title) ?? 'task';
    $s = trim($s, '-');
    if ($s === '') $s = 'task';
    if (mb_strlen($s, 'UTF-8') > 40) $s = mb_substr($s, 0, 40, 'UTF-8');
    return $s . '.ics';
}

function todo_ics(array $todo): string {
    $start = new DateTime((string) $todo['active_at']);
    $end = (clone $start)->modify('+30 minutes');
    $host = parse_url(APP_URL, PHP_URL_HOST) ?: 'todo-app';
    $uid = 'todo-' . (int) $todo['id'] . '@' . $host;
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//' . APP_NAME . '//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start->format('Ymd\THis'),
        'DTEND:' . $end->format('Ymd\THis'),
        'SUMMARY:' . ics_escape((string) $todo['title']),
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    return implode("\r\n", $lines) . "\r\n";
}

function telegram_send_document(string $chat_id, string $filename, string $bytes, string $caption = ''): bool {
    if (!TELEGRAM_BOT_TOKEN) return false;
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendDocument';
    $safe_name = str_replace(['"', "\r", "\n"], '', $filename);
    if (function_exists('curl_init') && class_exists('CURLFile')) {
        $tmp = tmpfile();
        if ($tmp === false) return false;
        fwrite($tmp, $bytes);
        fflush($tmp);
        $path = stream_get_meta_data($tmp)['uri'] ?? '';
        if ($path === '') {
            fclose($tmp);
            return false;
        }
        $post = [
            'chat_id' => $chat_id,
            'document' => new CURLFile($path, 'text/calendar', $safe_name),
            'disable_web_page_preview' => 'true',
        ];
        if ($caption !== '') {
            $post['caption'] = $caption;
            $post['parse_mode'] = 'HTML';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        fclose($tmp);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) && !empty($data['ok']);
    }
    $boundary = '----todo' . bin2hex(random_bytes(8));
    $body = '';
    $fields = [
        'chat_id' => $chat_id,
        'disable_web_page_preview' => 'true',
    ];
    if ($caption !== '') {
        $fields['caption'] = $caption;
        $fields['parse_mode'] = 'HTML';
    }
    foreach ($fields as $k => $v) {
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
    }
    $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"document\"; filename=\"{$safe_name}\"\r\n";
    $body .= "Content-Type: text/calendar\r\n\r\n{$bytes}\r\n--{$boundary}--\r\n";
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: multipart/form-data; boundary={$boundary}\r\n",
        'content' => $body,
        'timeout' => 15,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) && !empty($data['ok']);
}

function telegram_configured(): bool {
    return TELEGRAM_BOT_TOKEN !== '';
}

function telegram_webhook_secret(): string {
    return TELEGRAM_WEBHOOK_SECRET !== ''
        ? TELEGRAM_WEBHOOK_SECRET
        : substr(hash('sha256', 'wh:' . TELEGRAM_BOT_TOKEN), 0, 32);
}

function telegram_webhook_url(): string {
    $base = rtrim(APP_URL, '/');
    if (str_starts_with($base, 'http://')) {
        $base = 'https://' . substr($base, strlen('http://'));
    }
    return $base . '/telegram.php?key=' . rawurlencode(telegram_webhook_secret());
}

function telegram_webhook_status(): array {
    $info = telegram_api('getWebhookInfo');
    $url = (is_array($info) && !empty($info['ok'])) ? (string) ($info['result']['url'] ?? '') : '';
    $err = (is_array($info) && !empty($info['ok'])) ? (string) ($info['result']['last_error_message'] ?? '') : '';
    $on = $url !== '';
    meta_set('telegram_webhook', $on ? '1' : '0');
    return ['on' => $on, 'error' => $err];
}

function telegram_set_webhook(bool $enable): array {
    if ($enable) {
        $res = telegram_api('setWebhook', [
            'url' => telegram_webhook_url(),
            'secret_token' => telegram_webhook_secret(),
            'allowed_updates' => ['message'],
        ]);
    } else {
        $res = telegram_api('deleteWebhook', ['drop_pending_updates' => false]);
    }
    if (!$res || empty($res['ok'])) {
        $desc = is_array($res) ? (string) ($res['description'] ?? '') : '';
        return ['ok' => false, 'error' => $desc !== '' ? $desc : t('error.telegram_bot')];
    }
    meta_set('telegram_webhook', $enable ? '1' : '0');
    return ['ok' => true, 'on' => $enable];
}

function telegram_bot_username(): ?string {
    if (TELEGRAM_BOT_USERNAME !== '') {
        return ltrim(TELEGRAM_BOT_USERNAME, '@');
    }
    $fp = substr(hash('sha256', TELEGRAM_BOT_TOKEN), 0, 12);
    if (meta_get('telegram_bot_token_fp') === $fp) {
        $cached = meta_get('telegram_bot_username');
        if ($cached !== '') return $cached;
    }
    $res = telegram_api('getMe');
    $name = is_array($res) ? (string) ($res['result']['username'] ?? '') : '';
    if ($name === '') return null;
    meta_set('telegram_bot_token_fp', $fp);
    meta_set('telegram_bot_username', $name);
    return $name;
}

function telegram_create_link(int $uid): ?array {
    if (!telegram_configured()) return null;
    $username = telegram_bot_username();
    if (!$username) return null;
    db()->prepare('DELETE FROM telegram_link_tokens WHERE user_id=? OR expires_at <= datetime("now","localtime")')
        ->execute([$uid]);
    $token = bin2hex(random_bytes(16));
    db()->prepare('INSERT INTO telegram_link_tokens (token_hash, user_id, expires_at) VALUES (?,?,datetime("now","localtime","+30 minutes"))')
        ->execute([hash('sha256', $token), $uid]);
    return [
        'url'      => 'https://t.me/' . $username . '?start=' . $token,
        'username' => $username,
    ];
}

function telegram_link_chat(string $token, string $chat_id): ?array {
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT user_id FROM telegram_link_tokens WHERE token_hash=? AND expires_at > datetime("now","localtime")');
    $stmt->execute([$hash]);
    $uid = $stmt->fetchColumn();
    if (!$uid) return null;
    $uid = (int) $uid;
    db()->prepare('UPDATE users SET telegram_chat_id=NULL WHERE telegram_chat_id=? AND id!=?')->execute([$chat_id, $uid]);
    db()->prepare('UPDATE users SET telegram_chat_id=? WHERE id=?')->execute([$chat_id, $uid]);
    db()->prepare('DELETE FROM telegram_link_tokens WHERE token_hash=? OR user_id=?')->execute([$hash, $uid]);
    $u = db()->prepare('SELECT * FROM users WHERE id=?');
    $u->execute([$uid]);
    return $u->fetch() ?: null;
}

function telegram_unlink_chat(string $chat_id): void {
    db()->prepare('UPDATE users SET telegram_chat_id=NULL WHERE telegram_chat_id=?')->execute([$chat_id]);
}

function telegram_unlink_user(int $uid): void {
    db()->prepare('UPDATE users SET telegram_chat_id=NULL WHERE id=?')->execute([$uid]);
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
    $lifetime = AUTH_LIFETIME;
    $save = __DIR__ . '/db/sessions';
    if (!is_dir($save)) {
        @mkdir($save, 0775, true);
    }
    if (is_dir($save) && is_writable($save)) {
        session_save_path($save);
    }
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.cookie_lifetime', (string) $lifetime);
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
    if (!empty($_SESSION['user']['id'])) {
        return $_SESSION['user'];
    }
    if (PHP_SAPI === 'cli') return null;
    $user = user_from_remember_cookie();
    if (!$user) return null;
    login_user($user, true);
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

function login_user(array $user, bool $issue_remember = true): void {
    start_session();
    session_regenerate_id(true);
    unset($_SESSION['_csrf']);
    $_SESSION['user'] = [
        'id'             => $user['id'],
        'email'          => $user['email'],
        'notify_minutes' => $user['notify_minutes'] ?? 5,
        'locale'         => normalize_locale($user['locale'] ?? current_locale()),
    ];
    set_locale($_SESSION['user']['locale']);
    if ($issue_remember) {
        issue_remember_token((int) $user['id']);
    }
}

function auth_cookie_opts(int $expires): array {
    return [
        'expires'  => $expires,
        'path'     => session_cookie_path(),
        'secure'   => str_starts_with(APP_URL, 'https://'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function issue_remember_token(int $uid): void {
    db()->prepare('DELETE FROM remember_tokens WHERE user_id=? AND expires_at <= datetime("now","localtime")')->execute([$uid]);
    $stmt = db()->prepare('SELECT COUNT(*) FROM remember_tokens WHERE user_id=?');
    $stmt->execute([$uid]);
    $extra = (int) $stmt->fetchColumn() - 7;
    while ($extra-- > 0) {
        db()->prepare('DELETE FROM remember_tokens WHERE id = (SELECT id FROM remember_tokens WHERE user_id=? ORDER BY expires_at ASC LIMIT 1)')
            ->execute([$uid]);
    }
    $token = bin2hex(random_bytes(32));
    $exp   = date('Y-m-d H:i:s', time() + AUTH_LIFETIME);
    db()->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)')
        ->execute([$uid, hash('sha256', $token), $exp]);
    setcookie('remember', $uid . ':' . $token, auth_cookie_opts(time() + AUTH_LIFETIME));
}

function user_from_remember_cookie(): ?array {
    $raw = (string) ($_COOKIE['remember'] ?? '');
    if (!preg_match('/^(\d+):([a-f0-9]{64})$/', $raw, $m)) return null;
    $uid  = (int) $m[1];
    $hash = hash('sha256', $m[2]);
    $stmt = db()->prepare('SELECT id FROM remember_tokens WHERE user_id=? AND token_hash=? AND expires_at > datetime("now","localtime")');
    $stmt->execute([$uid, $hash]);
    $row = $stmt->fetch();
    if (!$row) return null;
    db()->prepare('DELETE FROM remember_tokens WHERE id=?')->execute([$row['id']]);
    $u = db()->prepare('SELECT * FROM users WHERE id=?');
    $u->execute([$uid]);
    return $u->fetch() ?: null;
}

function clear_remember(int $uid): void {
    db()->prepare('DELETE FROM remember_tokens WHERE user_id=?')->execute([$uid]);
    setcookie('remember', '', auth_cookie_opts(time() - 3600));
}

function password_reset_url(string $token): string {
    return rtrim(APP_URL, '/') . '/auth.php?reset=' . rawurlencode($token);
}

function password_reset_user(string $token): ?array {
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $stmt = db()->prepare(
        'SELECT u.* FROM password_reset_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash=? AND t.expires_at > datetime("now","localtime")'
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

function password_reset_request(string $email): void {
    $email = trim($email);
    $stmt = db()->prepare('SELECT * FROM users WHERE email=?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) return;

    $uid = (int) $user['id'];
    $open = db()->prepare('SELECT expires_at FROM password_reset_tokens WHERE user_id=? AND expires_at > datetime("now","localtime")');
    $open->execute([$uid]);
    $exp = $open->fetchColumn();
    if ($exp && (strtotime((string) $exp) - time()) > 28 * 60) {
        return;
    }

    db()->prepare('DELETE FROM password_reset_tokens WHERE user_id=? OR expires_at <= datetime("now","localtime")')->execute([$uid]);
    $token = bin2hex(random_bytes(16));
    db()->prepare('INSERT INTO password_reset_tokens (token_hash, user_id, expires_at) VALUES (?,?,datetime("now","localtime","+30 minutes"))')
        ->execute([hash('sha256', $token), $uid]);

    $loc = normalize_locale($user['locale'] ?? 'en');
    $link = password_reset_url($token);
    $subject = '[' . APP_NAME . '] ' . t('auth.reset_email_subject', [], $loc);
    $html = '<!DOCTYPE html><html lang="' . h($loc) . '"><body style="font-family:sans-serif;max-width:480px;margin:40px auto;color:#1e293b">'
        . '<p>' . htmlspecialchars(t('auth.reset_email_body', [], $loc), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="color:#4f46e5">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a></p>'
        . '<p style="color:#94a3b8;font-size:12px">' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</body></html>';
    send_email($user['email'], $subject, $html);
    if (!empty($user['telegram_chat_id'])) {
        send_telegram(
            (string) $user['telegram_chat_id'],
            t('auth.reset_telegram', [], $loc) . "\n<a href=\"" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(t('auth.reset_open', [], $loc), ENT_QUOTES, 'UTF-8') . '</a>'
        );
    }
}

function password_reset_complete(int $uid, string $token, string $new): bool {
    $row = password_reset_user($token);
    if (!$row || (int) $row['id'] !== $uid) return false;
    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
    db()->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([$uid]);
    clear_remember($uid);
    return true;
}

function logout_user(): void {
    start_session();
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    if ($uid) clear_remember($uid);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', auth_cookie_opts(time() - 3600));
    }
    session_destroy();
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

function format_overview_time(string $active_at, string $locale = 'en'): string {
    $ts = strtotime($active_at) ?: time();
    return normalize_locale($locale) === 'de' ? date('H:i', $ts) : date('g:i A', $ts);
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

function meta_get(string $key, string $default = ''): string {
    $stmt = db()->prepare('SELECT value FROM app_meta WHERE key=?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function meta_set(string $key, string $value): void {
    db()->prepare('INSERT INTO app_meta (key, value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')
        ->execute([$key, $value]);
}

function parse_date_tag(string $raw): ?array {
    $s = mb_strtolower(trim($raw), 'UTF-8');
    $s = preg_replace('/\bum\b/u', ' ', $s) ?? $s;
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $s = trim($s);

    $pad = fn(int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $today = new DateTime('today');
    $addDays = function (int $n) use ($today): DateTime {
        $d = clone $today;
        $d->modify(($n >= 0 ? '+' : '') . $n . ' days');
        return $d;
    };
    $fmt = fn(DateTime $d): string => $d->format('Y-m-d');

    $h = null;
    $m = 0;

    $named = [
        12 => ['noon', 'mittag'],
        0  => ['midnight', 'mitternacht'],
        8  => ['morning', 'morgens', 'früh'],
        19 => ['evening', 'abend', 'abends'],
        22 => ['night', 'nacht', 'nachts'],
    ];
    foreach ($named as $hour => $words) {
        foreach ($words as $w) {
            if (mb_strpos($s, $w) !== false) {
                $h = $hour;
                $s = trim(preg_replace('/' . preg_quote($w, '/') . '/u', ' ', $s, 1) ?? $s);
                $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
                break 2;
            }
        }
    }

    if ($h === null) {
        if (preg_match('/\b(\d{1,2}):(\d{2})\b/', $s, $match)) {
            $h = (int) $match[1];
            $m = (int) $match[2];
            $s = trim(str_replace($match[0], ' ', $s));
        } elseif (preg_match('/\b(\d{1,2})\s*(am|pm)\b/', $s, $match)) {
            $h = (int) $match[1];
            if ($match[2] === 'pm' && $h < 12) $h += 12;
            if ($match[2] === 'am' && $h === 12) $h = 0;
            $s = trim(str_replace($match[0], ' ', $s));
        } elseif (preg_match('/\b(\d{1,2})\s*uhr\b/u', $s, $match)) {
            $h = (int) $match[1];
            $s = trim(str_replace($match[0], ' ', $s));
        }
    }

    $dateStr = '';
    $dow = [
        'su' => 0, 'sunday' => 0, 'sonntag' => 0,
        'mo' => 1, 'monday' => 1, 'montag' => 1,
        'di' => 2, 'tue' => 2, 'tuesday' => 2, 'dienstag' => 2,
        'mi' => 3, 'wed' => 3, 'wednesday' => 3, 'mittwoch' => 3,
        'do' => 4, 'thu' => 4, 'thursday' => 4, 'donnerstag' => 4,
        'fr' => 5, 'friday' => 5, 'freitag' => 5,
        'sa' => 6, 'saturday' => 6, 'samstag' => 6,
    ];

    if (str_contains($s, 'übermorgen') || str_contains($s, 'uebermorgen') || preg_match('/\bday after tomorrow\b/', $s)) {
        $dateStr = $fmt($addDays(2));
        $s = trim(preg_replace('/übermorgen|uebermorgen|\bday after tomorrow\b/u', ' ', $s) ?? $s);
    } elseif (preg_match('/\b(tomorrow|morgen)\b/u', $s)) {
        $dateStr = $fmt($addDays(1));
        $s = trim(preg_replace('/\b(tomorrow|morgen)\b/u', ' ', $s) ?? $s);
    } elseif (preg_match('/\b(today|heute)\b/u', $s)) {
        $dateStr = $fmt($today);
        $s = trim(preg_replace('/\b(today|heute)\b/u', ' ', $s) ?? $s);
    } elseif (preg_match('/\bnext week\b/', $s) || str_contains($s, 'nächste woche')) {
        $toMon = (1 - (int) $today->format('w') + 7) % 7 ?: 7;
        $dateStr = $fmt($addDays($toMon));
        $s = trim(str_replace(['next week', 'nächste woche'], ' ', $s));
    } elseif (preg_match('/(?:^|(?<=\s))(su|sunday|sonntag|mo|monday|montag|di|tue|tuesday|dienstag|mi|wed|wednesday|mittwoch|do|thu|thursday|donnerstag|fr|friday|freitag|sa|saturday|samstag)(?=\s|$)/u', $s, $match)) {
        $want = $dow[$match[1]];
        $diff = ($want - (int) $today->format('w') + 7) % 7 ?: 7;
        $dateStr = $fmt($addDays($diff));
        $s = trim(str_replace($match[0], ' ', $s));
    } elseif (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})?/', $s, $match)) {
        $y = !empty($match[3]) ? (int) $match[3] : (int) $today->format('Y');
        $dateStr = sprintf('%04d-%02d-%02d', $y, (int) $match[2], (int) $match[1]);
        $s = trim(str_replace($match[0], ' ', $s));
    } elseif (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $s, $match)) {
        $dateStr = $match[0];
        $s = trim(str_replace($match[0], ' ', $s));
    }

    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    if ($h === null && $dateStr !== '' && preg_match('/^\d{1,2}$/', $s)) {
        $n = (int) $s;
        if ($n >= 0 && $n <= 23) {
            $h = $n;
            $s = '';
        }
    }

    if ($h === null && $dateStr === '') return null;
    if ($h !== null && ($h > 23 || $m > 59)) return null;

    return [
        'date' => $dateStr,
        'time' => $h !== null ? $pad($h) . ':' . $pad($m) : '',
        'rest' => $s,
    ];
}

function resolve_active_at(string $date, string $time): ?string {
    if ($date === '' && $time === '') return null;
    if ($date !== '' && $time !== '') return $date . ' ' . $time . ':00';
    if ($date !== '') return $date . ' 09:00:00';
    [$hh, $mm] = array_map('intval', explode(':', $time . ':0'));
    $now = new DateTime('now');
    $candidate = new DateTime($now->format('Y-m-d') . sprintf(' %02d:%02d:00', $hh, $mm));
    if ($candidate <= $now) $candidate->modify('+1 day');
    return $candidate->format('Y-m-d H:i:s');
}

function parse_todo_text(string $text): array {
    $title = $text;
    $date = $time = '';
    $priority = 4;
    $emails = [];
    $tags = [];

    if (preg_match_all('/<(\+?)([^>]+)>|["“„](\+?)([^"“”„]+)["“”]/u', $text, $all, PREG_SET_ORDER)) {
        foreach ($all as $match) {
            $plus  = str_starts_with($match[0], '<') ? $match[1] : ($match[3] ?? '');
            $inner = str_starts_with($match[0], '<') ? $match[2] : ($match[4] ?? '');
            if ($plus === '+') {
                $email = trim($inner);
                if ($email !== '' && !in_array($email, $emails, true)) $emails[] = $email;
                $title = str_replace($match[0], ' ', $title);
            } else {
                $parsed = parse_date_tag($inner);
                if ($parsed) {
                    if ($parsed['date'] !== '') $date = $parsed['date'];
                    if ($parsed['time'] !== '') $time = $parsed['time'];
                    $title = str_replace($match[0], ' ', $title);
                }
            }
        }
    }

    if (preg_match_all('/(?:^|\s)p([1-4])(?=\s|$)/i', $title, $pm)) {
        $priority = (int) $pm[1][count($pm[1]) - 1];
        $title = preg_replace('/(?:^|\s)p[1-4](?=\s|$)/i', ' ', $title) ?? $title;
    }

    if (preg_match_all('/(?:^|(?<=\s))#([^\s#]+)/u', $title, $tm)) {
        foreach ($tm[1] as $name) {
            $name = trim($name);
            if ($name !== '' && !in_array($name, $tags, true)) $tags[] = $name;
        }
        $title = preg_replace('/(?:^|(?<=\s))#[^\s#]+/u', ' ', $title) ?? $title;
    }

    if (preg_match_all('/(?:^|(?<=\s))\+([^\s+]+)/u', $title, $sm)) {
        foreach ($sm[1] as $name) {
            $name = trim($name);
            if ($name !== '' && !in_array($name, $emails, true)) $emails[] = $name;
        }
        $title = preg_replace('/(?:^|(?<=\s))\+[^\s+]+/u', ' ', $title) ?? $title;
    }

    $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

    if ($date === '' && $time === '') {
        $affix = extract_affix_date($title);
        $title = $affix['title'];
        $date  = $affix['date'];
        $time  = $affix['time'];
    }

    return compact('title', 'date', 'time', 'priority', 'emails', 'tags');
}

function extract_affix_date(string $title): array {
    $trail = extract_end_date($title, true);
    if ($trail['date'] !== '' || $trail['time'] !== '') return $trail;
    return extract_end_date($title, false);
}

function extract_end_date(string $title, bool $trailing): array {
    $empty = ['title' => $title, 'date' => '', 'time' => ''];
    $words = preg_split('/\s+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) return $empty;
    $max = min(4, count($words));
    for ($n = $max; $n >= 1; $n--) {
        $chunk = $trailing
            ? implode(' ', array_slice($words, -$n))
            : implode(' ', array_slice($words, 0, $n));
        $parsed = parse_date_tag($chunk);
        if (!$parsed) continue;
        if (trim((string) ($parsed['rest'] ?? '')) !== '') continue;
        if (is_bare_weekday_phrase($chunk, $parsed)) continue;
        $rest = $trailing
            ? array_slice($words, 0, -$n)
            : array_slice($words, $n);
        return [
            'title' => trim(implode(' ', $rest)),
            'date'  => $parsed['date'],
            'time'  => $parsed['time'],
        ];
    }
    return $empty;
}

function is_bare_weekday_phrase(string $raw, array $parsed): bool {
    if (($parsed['time'] ?? '') !== '') return false;
    $s = mb_strtolower(trim($raw), 'UTF-8');
    return (bool) preg_match('/^(su|sunday|sonntag|mo|monday|montag|di|tue|tuesday|dienstag|mi|wed|wednesday|mittwoch|do|thu|thursday|donnerstag|fr|friday|freitag|sa|saturday|samstag)$/u', $s);
}

function normalize_display_name(string $s): string|false|null {
    $s = trim($s);
    if ($s === '') return null;
    if (str_contains($s, '@') || !preg_match('/^[\p{L}\p{N}_-]{1,32}$/u', $s)) return false;
    return $s;
}

function share_label(array $user): string {
    $name = trim((string) ($user['display_name'] ?? ''));
    return $name !== '' ? $name : (string) ($user['email'] ?? '');
}

function find_share_user(int $uid, string $who): ?array {
    $who = trim($who);
    if ($who === '') return null;
    if (str_contains($who, '@')) {
        $stmt = db()->prepare('SELECT id, email, display_name FROM users WHERE email=? AND id!=?');
        $stmt->execute([$who, $uid]);
    } else {
        $stmt = db()->prepare('SELECT id, email, display_name FROM users WHERE lower(display_name)=lower(?) AND display_name != \'\' AND id!=?');
        $stmt->execute([$who, $uid]);
    }
    return $stmt->fetch() ?: null;
}

function find_or_create_tag(int $uid, string $name): int {
    $stmt = db()->prepare('SELECT id FROM tags WHERE user_id=? AND lower(name)=lower(?)');
    $stmt->execute([$uid, $name]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;
    $colors = ['#5b4dff', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#14b8a6'];
    db()->prepare('INSERT INTO tags (user_id,name,color) VALUES (?,?,?)')
        ->execute([$uid, $name, $colors[array_rand($colors)]]);
    return (int) db()->lastInsertId();
}

function create_todo_from_text(int $uid, string $text): ?array {
    $p = parse_todo_text($text);
    if ($p['title'] === '') return null;
    $active = resolve_active_at($p['date'], $p['time']);
    $stmt = db()->prepare('INSERT INTO todos (user_id,title,active_at,priority) VALUES (?,?,?,?)');
    $priority = max(1, min(4, (int) $p['priority'] ?: 4));
    $stmt->execute([$uid, $p['title'], $active, $priority]);
    $todo_id = (int) db()->lastInsertId();
    $tag_ids = [];
    foreach ($p['tags'] as $name) {
        $tag_ids[] = find_or_create_tag($uid, $name);
    }
    if ($tag_ids) {
        $ins = db()->prepare('INSERT OR IGNORE INTO todo_tags (todo_id, tag_id) VALUES (?,?)');
        foreach ($tag_ids as $tid) $ins->execute([$todo_id, $tid]);
    }
    $share_ok = $share_fail = [];
    foreach ($p['emails'] as $who) {
        $target = find_share_user($uid, $who);
        if (!$target) { $share_fail[] = $who; continue; }
        try {
            db()->prepare('INSERT INTO todo_shares (todo_id, user_id) VALUES (?,?)')->execute([$todo_id, (int) $target['id']]);
            $share_ok[] = share_label($target);
        } catch (PDOException) {
            $share_fail[] = $who;
        }
    }
    return [
        'id'         => $todo_id,
        'title'      => $p['title'],
        'active_at'  => $active,
        'priority'   => $priority,
        'share_ok'   => $share_ok,
        'share_fail' => $share_fail,
    ];
}

function telegram_day_overview(int $uid, string $which, string $locale): string {
    $locale = normalize_locale($locale);
    $today = (new DateTime('today'))->format('Y-m-d');
    $tomorrow = (new DateTime('today'))->modify('+1 day')->format('Y-m-d');
    $day = $which === 'tomorrow' ? $tomorrow : $today;

    $stmt = db()->prepare("
        SELECT t.title, t.active_at, t.priority
        FROM todos t
        WHERE t.completed_at IS NULL
          AND t.active_at IS NOT NULL
          AND date(t.active_at) " . ($which === 'tomorrow' ? '=' : '<=') . " ?
          AND (t.user_id = ? OR EXISTS (SELECT 1 FROM todo_shares ts WHERE ts.todo_id = t.id AND ts.user_id = ?))
        ORDER BY t.active_at ASC, t.priority ASC, t.title COLLATE NOCASE ASC
    ");
    $stmt->execute([$day, $uid, $uid]);
    $rows = $stmt->fetchAll();

    $overdue = [];
    $on_day = [];
    foreach ($rows as $row) {
        $date = substr((string) $row['active_at'], 0, 10);
        if ($which === 'today' && $date < $today) $overdue[] = $row;
        else $on_day[] = $row;
    }

    $line = function (array $row, bool $with_date) use ($locale): string {
        $when = $with_date
            ? format_notify_when((string) $row['active_at'], $locale)
            : format_overview_time((string) $row['active_at'], $locale);
        $title = htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8');
        $prio = ((int) $row['priority'] < 4) ? ' P' . (int) $row['priority'] : '';
        return '• ' . $when . ' ' . $title . $prio;
    };

    $blocks = [];
    if ($overdue) {
        $blocks[] = '<b>' . htmlspecialchars(t('tg.overview_overdue', [], $locale), ENT_QUOTES, 'UTF-8') . '</b>'
            . "\n" . implode("\n", array_map(fn($r) => $line($r, true), $overdue));
    }
    $heading = $which === 'tomorrow' ? t('tg.overview_tomorrow', [], $locale) : t('tg.overview_today', [], $locale);
    if ($on_day) {
        $blocks[] = '<b>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</b>'
            . "\n" . implode("\n", array_map(fn($r) => $line($r, false), $on_day));
    }
    if (!$blocks) {
        return t($which === 'tomorrow' ? 'tg.overview_empty_tomorrow' : 'tg.overview_empty_today', [], $locale);
    }

    $text = implode("\n\n", $blocks);
    if (strlen($text) > 3900) {
        $text = substr($text, 0, 3900);
        $cut = strrpos($text, "\n");
        if ($cut !== false) $text = substr($text, 0, $cut);
        $text .= "\n" . t('tg.overview_more', [], $locale);
    }
    return $text;
}

function telegram_user_by_chat(string $chat_id): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE telegram_chat_id=?');
    $stmt->execute([$chat_id]);
    return $stmt->fetch() ?: null;
}

function handle_telegram_update(array $update): void {
    $msg = $update['message'] ?? $update['edited_message'] ?? null;
    if (!$msg || empty($msg['text'])) return;
    $chat_id = (string) ($msg['chat']['id'] ?? '');
    if ($chat_id === '') return;
    $text = trim((string) $msg['text']);
    $user = telegram_user_by_chat($chat_id);
    $lang = $user['locale'] ?? ($msg['from']['language_code'] ?? 'en');

    if (preg_match('/^\/(start|help|unlink)(?:@\S+)?(?:\s+(\S+))?$/i', $text, $cmd)) {
        $name = strtolower($cmd[1]);
        $payload = $cmd[2] ?? '';
        if ($name === 'unlink') {
            if ($user) {
                telegram_unlink_chat($chat_id);
                send_telegram($chat_id, t('tg.unlinked', [], $user['locale'] ?? $lang));
            } else {
                send_telegram($chat_id, t('tg.unknown', [], $lang));
            }
            return;
        }
        if ($name === 'start' && $payload !== '') {
            $linked = telegram_link_chat($payload, $chat_id);
            if ($linked) {
                $lang = $linked['locale'] ?? $lang;
                send_telegram($chat_id, t('tg.linked', ['email' => $linked['email']], $lang) . "\n\n" . t('tg.help', [], $lang));
            } else {
                send_telegram($chat_id, t('tg.link_expired', [], $lang));
            }
            return;
        }
        if ($user) {
            send_telegram($chat_id, t('tg.linked', ['email' => $user['email']], $lang) . "\n\n" . t('tg.help', [], $lang));
        } else {
            send_telegram($chat_id, t('tg.unknown', [], $lang) . "\n\n" . t('tg.help', [], $lang));
        }
        return;
    }
    if (str_starts_with($text, '/')) return;
    if (!$user) {
        send_telegram($chat_id, t('tg.unknown', [], $lang));
        return;
    }
    if (preg_match('/^(today|tomorrow|heute|morgen)\s*\?$/iu', $text, $q)) {
        $which = in_array(mb_strtolower($q[1], 'UTF-8'), ['tomorrow', 'morgen'], true) ? 'tomorrow' : 'today';
        send_telegram($chat_id, telegram_day_overview((int) $user['id'], $which, $user['locale'] ?? $lang));
        return;
    }
    $todo = create_todo_from_text((int) $user['id'], $text);
    if (!$todo) {
        send_telegram($chat_id, t('tg.empty', [], $lang));
        return;
    }
    $loc = $user['locale'] ?? $lang;
    $reply = t('tg.created', ['title' => htmlspecialchars($todo['title'], ENT_QUOTES, 'UTF-8')], $loc);
    if ($todo['active_at']) {
        $reply .= "\n" . t('tg.due', ['when' => format_notify_when($todo['active_at'], $loc)], $loc);
    }
    if ($todo['priority'] < 4) {
        $reply .= "\nP" . $todo['priority'];
    }
    if (!empty($todo['share_ok'])) {
        $reply .= "\n" . t('tg.shared', ['names' => htmlspecialchars(implode(', ', $todo['share_ok']), ENT_QUOTES, 'UTF-8')], $loc);
    }
    if ($todo['active_at']
        && telegram_send_document($chat_id, ics_filename($todo['title']), todo_ics($todo), $reply)) {
        return;
    }
    send_telegram($chat_id, $reply);
}

function telegram_poll_updates(): void {
    if (!TELEGRAM_BOT_TOKEN) return;
    $offset = (int) meta_get('telegram_offset', '0');
    $res = telegram_api('getUpdates', [
        'offset'  => $offset,
        'timeout' => 0,
        'allowed_updates' => ['message'],
    ]);
    if (!$res || empty($res['ok']) || empty($res['result'])) return;
    foreach ($res['result'] as $update) {
        handle_telegram_update($update);
        if (isset($update['update_id'])) {
            $offset = (int) $update['update_id'] + 1;
        }
    }
    meta_set('telegram_offset', (string) $offset);
}
