<?php
// Catch fatal errors (missing extensions, etc.) and return JSON instead of empty 500
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => 'PHP fatal: ' . $e['message'], 'file' => basename($e['file']), 'line' => $e['line']]);
    }
});

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

try {

// Auth check for all actions except logout
$user   = session_user();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if (!$user) { json_out(['error' => 'Unauthenticated'], 401); }

$uid = (int)$user['id'];
$body = json_in();

// ─── Route ────────────────────────────────────────────────────
match ($action) {
    'todos'           => get_todos($uid),
    'todo'            => get_todo($uid),
    'create_todo'     => create_todo($uid, $body),
    'update_todo'     => update_todo($uid, $body),
    'delete_todo'     => delete_todo($uid, $body),
    'complete_todo'   => complete_todo($uid, $body),
    'uncomplete_todo' => uncomplete_todo($uid, $body),
    'tags'            => get_tags($uid),
    'create_tag'      => create_tag($uid, $body),
    'update_tag'      => update_tag($uid, $body),
    'delete_tag'      => delete_tag($uid, $body),
    'comments'        => get_comments($uid),
    'add_comment'     => add_comment($uid, $body),
    'shares'          => get_shares($uid),
    'add_share'       => add_share($uid, $body),
    'remove_share'    => remove_share($uid, $body),
    'get_users'       => get_users($uid),
    'find_user'       => find_user($uid),
    'settings'        => get_settings($uid),
    'update_settings' => update_settings($uid, $body),
    'change_password' => change_password($uid, $body),
    'logout'          => logout(),
    'get_files'       => get_files($uid),
    'upload_file'     => upload_file($uid),
    'delete_file'     => delete_file($uid, $body),
    default           => json_out(['error' => 'Unknown action'], 400),
};

} catch (Throwable $e) {
    json_out(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()], 500);
}

// ─── Todos ────────────────────────────────────────────────────
function get_todos(int $uid): never {
    $tag_id = isset($_GET['tag_id']) && $_GET['tag_id'] !== '' ? (int)$_GET['tag_id'] : null;
    $status = $_GET['status'] ?? 'pending';
    $sort   = in_array($_GET['sort'] ?? '', ['active_at','created_at','title','completed_at']) ? $_GET['sort'] : 'active_at';
    $dir    = ($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    $where  = ['(t.user_id = :uid OR EXISTS (SELECT 1 FROM todo_shares ts WHERE ts.todo_id = t.id AND ts.user_id = :uid))'];
    $params = [':uid' => $uid];

    if ($status === 'pending') {
        $where[] = 't.completed_at IS NULL AND (t.active_at IS NULL OR t.active_at > datetime("now", "localtime"))';
    } elseif ($status === 'active') {
        $where[] = 't.completed_at IS NULL AND t.active_at IS NOT NULL AND t.active_at <= datetime("now", "localtime")';
    } elseif ($status === 'completed') {
        $where[] = 't.completed_at IS NOT NULL';
    }

    // Hide completed toggle (used in "all" view)
    if (($_GET['hide_completed'] ?? '0') === '1') {
        $where[] = 't.completed_at IS NULL';
    }

    // Completion date range (used in "completed" view)
    if ($status === 'completed') {
        if (!empty($_GET['completed_from'])) {
            $from = DateTime::createFromFormat('Y-m-d', $_GET['completed_from']);
            if ($from) { $where[] = 't.completed_at >= :completed_from'; $params[':completed_from'] = $from->format('Y-m-d') . ' 00:00:00'; }
        }
        if (!empty($_GET['completed_to'])) {
            $to = DateTime::createFromFormat('Y-m-d', $_GET['completed_to']);
            if ($to) { $where[] = 't.completed_at <= :completed_to'; $params[':completed_to'] = $to->format('Y-m-d') . ' 23:59:59'; }
        }
    }

    if ($tag_id !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM todo_tags tt WHERE tt.todo_id = t.id AND tt.tag_id = :tag_id)';
        $params[':tag_id'] = $tag_id;
    }

    $null_last = '';
    if ($sort === 'active_at')    $null_last = "CASE WHEN t.active_at IS NULL THEN 1 ELSE 0 END, ";
    elseif ($sort === 'completed_at') $null_last = "CASE WHEN t.completed_at IS NULL THEN 1 ELSE 0 END, ";
    $sql = "
        SELECT t.*,
               u.email AS owner_email,
               (t.user_id = :uid) AS is_owner,
               (SELECT COUNT(*) FROM comments c WHERE c.todo_id = t.id) AS comment_count,
               GROUP_CONCAT(tg.id || '|' || tg.name || '|' || tg.color, ';;') AS tags_raw
        FROM todos t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN todo_tags tt ON tt.todo_id = t.id
        LEFT JOIN tags tg ON tg.id = tt.tag_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY t.id
        ORDER BY {$null_last}t.{$sort} {$dir}
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    json_out(array_map('format_todo', $rows));
}

function get_todo(int $uid): never {
    $id = (int)($_GET['id'] ?? 0);
    $todo = fetch_todo($uid, $id);
    if (!$todo) json_out(['error' => 'Not found'], 404);
    json_out($todo);
}

function create_todo(int $uid, array $b): never {
    $title = trim($b['title'] ?? '');
    if ($title === '') json_out(['error' => 'Title required'], 422);

    $active_at      = parse_datetime($b['active_at'] ?? '');
    $recur_type     = in_array($b['recur_type'] ?? '', ['daily','weekly','monthly','custom']) ? $b['recur_type'] : null;
    $recur_interval = max(1, (int)($b['recur_interval'] ?? 1));
    $recur_days     = ($recur_type === 'weekly' && !empty($b['recur_days'])) ? json_encode($b['recur_days']) : null;
    $recur_ends_at  = parse_datetime($b['recur_ends_at'] ?? '');
    $parent_id      = isset($b['recur_parent_id']) ? (int)$b['recur_parent_id'] : null;

    $stmt = db()->prepare('INSERT INTO todos (user_id,title,active_at,recur_type,recur_interval,recur_days,recur_ends_at,recur_parent_id) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$uid, $title, $active_at, $recur_type, $recur_interval, $recur_days, $recur_ends_at, $parent_id]);
    $todo_id = (int)db()->lastInsertId();

    sync_tags($todo_id, $uid, $b['tag_ids'] ?? []);
    json_out(fetch_todo($uid, $todo_id));
}

function update_todo(int $uid, array $b): never {
    $id   = (int)($b['id'] ?? 0);
    $todo = fetch_todo_raw($uid, $id);
    if (!$todo || !$todo['is_owner']) json_out(['error' => 'Not found or not owner'], 403);

    $title          = trim($b['title'] ?? $todo['title']);
    $active_at      = array_key_exists('active_at', $b) ? parse_datetime($b['active_at']) : $todo['active_at'];
    $recur_type     = in_array($b['recur_type'] ?? $todo['recur_type'], ['daily','weekly','monthly','custom']) ? ($b['recur_type'] ?? $todo['recur_type']) : null;
    $recur_interval = max(1, (int)($b['recur_interval'] ?? $todo['recur_interval']));
    $recur_days     = ($recur_type === 'weekly') ? json_encode($b['recur_days'] ?? json_decode($todo['recur_days'] ?? '[]', true)) : null;
    $recur_ends_at  = array_key_exists('recur_ends_at', $b) ? parse_datetime($b['recur_ends_at']) : $todo['recur_ends_at'];

    db()->prepare('UPDATE todos SET title=?,active_at=?,recur_type=?,recur_interval=?,recur_days=?,recur_ends_at=? WHERE id=?')
        ->execute([$title, $active_at, $recur_type, $recur_interval, $recur_days, $recur_ends_at, $id]);

    sync_tags($id, $uid, $b['tag_ids'] ?? null);
    json_out(fetch_todo($uid, $id));
}

function delete_todo(int $uid, array $b): never {
    $id   = (int)($b['id'] ?? 0);
    $todo = fetch_todo_raw($uid, $id);
    if (!$todo || !$todo['is_owner']) json_out(['error' => 'Not found or not owner'], 403);
    db()->prepare('DELETE FROM todos WHERE id=?')->execute([$id]);
    json_out(['ok' => true]);
}

function complete_todo(int $uid, array $b): never {
    $id   = (int)($b['id'] ?? 0);
    $todo = fetch_todo_raw($uid, $id);
    if (!$todo || !$todo['is_owner']) json_out(['error' => 'Not found or not owner'], 403);

    db()->prepare('UPDATE todos SET completed_at=datetime("now","localtime") WHERE id=?')->execute([$id]);

    // Spawn next recurrence
    $next = null;
    if ($todo['recur_type']) {
        $next = spawn_recurrence($uid, $todo);
    }

    json_out(['completed' => true, 'next_todo' => $next]);
}

function uncomplete_todo(int $uid, array $b): never {
    $id   = (int)($b['id'] ?? 0);
    $todo = fetch_todo_raw($uid, $id);
    if (!$todo || !$todo['is_owner']) json_out(['error' => 'Not found or not owner'], 403);
    db()->prepare('UPDATE todos SET completed_at=NULL WHERE id=?')->execute([$id]);
    json_out(fetch_todo($uid, $id));
}

// ─── Tags ─────────────────────────────────────────────────────
function get_tags(int $uid): never {
    $rows = db()->prepare('SELECT * FROM tags WHERE user_id=? ORDER BY name ASC');
    $rows->execute([$uid]);
    json_out($rows->fetchAll());
}

function create_tag(int $uid, array $b): never {
    $name  = trim($b['name'] ?? '');
    $color = $b['color'] ?? '#6366f1';
    if ($name === '') json_out(['error' => 'Name required'], 422);
    $stmt = db()->prepare('INSERT INTO tags (user_id,name,color) VALUES (?,?,?)');
    $stmt->execute([$uid, $name, $color]);
    $id = db()->lastInsertId();
    json_out(['id' => (int)$id, 'user_id' => $uid, 'name' => $name, 'color' => $color]);
}

function update_tag(int $uid, array $b): never {
    $id    = (int)($b['id'] ?? 0);
    $name  = trim($b['name'] ?? '');
    $color = $b['color'] ?? '#6366f1';
    if ($name === '') json_out(['error' => 'Name required'], 422);
    $stmt = db()->prepare('UPDATE tags SET name=?, color=? WHERE id=? AND user_id=?');
    $stmt->execute([$name, $color, $id, $uid]);
    json_out(['id' => $id, 'user_id' => $uid, 'name' => $name, 'color' => $color]);
}

function delete_tag(int $uid, array $b): never {
    $id = (int)($b['id'] ?? 0);
    db()->prepare('DELETE FROM tags WHERE id=? AND user_id=?')->execute([$id, $uid]);
    json_out(['ok' => true]);
}

// ─── Comments ─────────────────────────────────────────────────
function get_comments(int $uid): never {
    $todo_id = (int)($_GET['todo_id'] ?? 0);
    if (!can_access_todo($uid, $todo_id)) json_out(['error' => 'Not found'], 404);
    $stmt = db()->prepare('SELECT c.*, u.email FROM comments c JOIN users u ON u.id=c.user_id WHERE c.todo_id=? ORDER BY c.created_at ASC');
    $stmt->execute([$todo_id]);
    json_out($stmt->fetchAll());
}

function add_comment(int $uid, array $b): never {
    $todo_id = (int)($b['todo_id'] ?? 0);
    $body    = trim($b['body'] ?? '');
    if (!can_access_todo($uid, $todo_id)) json_out(['error' => 'Not found'], 404);
    if ($body === '') json_out(['error' => 'Comment cannot be empty'], 422);
    $stmt = db()->prepare('INSERT INTO comments (todo_id,user_id,body) VALUES (?,?,?)');
    $stmt->execute([$todo_id, $uid, $body]);
    $id = db()->lastInsertId();
    $stmt2 = db()->prepare('SELECT c.*, u.email FROM comments c JOIN users u ON u.id=c.user_id WHERE c.id=?');
    $stmt2->execute([$id]);
    json_out($stmt2->fetch());
}

// ─── Shares ───────────────────────────────────────────────────
function get_shares(int $uid): never {
    $todo_id = (int)($_GET['todo_id'] ?? 0);
    $todo    = fetch_todo_raw($uid, $todo_id);
    if (!$todo || !$todo['is_owner']) json_out(['error' => 'Not found'], 404);
    $stmt = db()->prepare('SELECT ts.user_id, u.email, ts.shared_at FROM todo_shares ts JOIN users u ON u.id=ts.user_id WHERE ts.todo_id=?');
    $stmt->execute([$todo_id]);
    json_out($stmt->fetchAll());
}

function add_share(int $uid, array $b): never {
    $todo_id = (int)($b['todo_id'] ?? 0);
    $email   = trim($b['email'] ?? '');
    $todo    = fetch_todo_raw($uid, $todo_id);
    if (!$todo || !$todo['is_owner']) json_out(['error' => 'Not found or not owner'], 403);

    $stmt = db()->prepare('SELECT id, email FROM users WHERE email=?');
    $stmt->execute([$email]);
    $target = $stmt->fetch();
    if (!$target) json_out(['error' => 'No account found for that email'], 404);
    if ($target['id'] === $uid) json_out(['error' => 'Cannot share with yourself'], 422);

    try {
        db()->prepare('INSERT INTO todo_shares (todo_id, user_id) VALUES (?,?)')->execute([$todo_id, $target['id']]);
    } catch (PDOException) {
        json_out(['error' => 'Already shared with this user'], 422);
    }
    json_out(['user_id' => $target['id'], 'email' => $target['email'], 'shared_at' => date('Y-m-d H:i:s')]);
}

function remove_share(int $uid, array $b): never {
    $todo_id    = (int)($b['todo_id'] ?? 0);
    $share_uid  = (int)($b['user_id'] ?? 0);
    $todo       = fetch_todo_raw($uid, $todo_id);
    if (!$todo || !$todo['is_owner']) json_out(['error' => 'Not found or not owner'], 403);
    db()->prepare('DELETE FROM todo_shares WHERE todo_id=? AND user_id=?')->execute([$todo_id, $share_uid]);
    json_out(['ok' => true]);
}

function get_users(int $uid): never {
    $stmt = db()->prepare('SELECT id, email FROM users WHERE id != ? ORDER BY email ASC');
    $stmt->execute([$uid]);
    json_out($stmt->fetchAll());
}

function find_user(int $uid): never {
    $email = trim($_GET['email'] ?? '');
    $stmt  = db()->prepare('SELECT id, email FROM users WHERE email=? AND id != ?');
    $stmt->execute([$email, $uid]);
    $u = $stmt->fetch();
    if (!$u) json_out(['error' => 'Not found'], 404);
    json_out($u);
}

// ─── Settings ─────────────────────────────────────────────────
function get_settings(int $uid): never {
    $stmt = db()->prepare('SELECT id, email, notify_minutes, telegram_chat_id, notify_channel FROM users WHERE id=?');
    $stmt->execute([$uid]);
    json_out($stmt->fetch());
}

function update_settings(int $uid, array $b): never {
    $minutes  = max(1, min(1440, (int)($b['notify_minutes'] ?? 5)));
    $tg       = trim($b['telegram_chat_id'] ?? '');
    $channel  = in_array($b['notify_channel'] ?? '', ['telegram','email','both']) ? $b['notify_channel'] : 'telegram';
    db()->prepare('UPDATE users SET notify_minutes=?, telegram_chat_id=?, notify_channel=? WHERE id=?')
        ->execute([$minutes, $tg ?: null, $channel, $uid]);
    $_SESSION['user']['notify_minutes'] = $minutes;
    json_out(['ok' => true, 'notify_minutes' => $minutes]);
}

function change_password(int $uid, array $b): never {
    $current = $b['current'] ?? '';
    $new     = $b['new'] ?? '';
    if (strlen($new) < 8) json_out(['error' => 'Password must be at least 8 characters'], 422);

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id=?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        json_out(['error' => 'Current password is incorrect'], 403);
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $uid]);
    json_out(['ok' => true]);
}

function logout(): never {
    session_destroy();
    json_out(['ok' => true]);
}

// ─── Helpers ──────────────────────────────────────────────────
function parse_datetime(string $s): ?string {
    if ($s === '') return null;
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $s)
       ?: DateTime::createFromFormat('Y-m-d H:i:s', $s)
       ?: DateTime::createFromFormat('Y-m-d H:i', $s);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

function format_todo(array $row): array {
    $row['is_owner']      = (bool)$row['is_owner'];
    $row['comment_count'] = (int)$row['comment_count'];
    $row['tags']          = [];
    if (!empty($row['tags_raw'])) {
        foreach (explode(';;', $row['tags_raw']) as $part) {
            [$id, $name, $color] = explode('|', $part, 3);
            $row['tags'][] = ['id' => (int)$id, 'name' => $name, 'color' => $color];
        }
    }
    unset($row['tags_raw']);
    return $row;
}

function fetch_todo(int $uid, int $id): ?array {
    $stmt = db()->prepare("
        SELECT t.*, u.email AS owner_email, (t.user_id = :uid) AS is_owner,
               (SELECT COUNT(*) FROM comments c WHERE c.todo_id = t.id) AS comment_count,
               GROUP_CONCAT(tg.id || '|' || tg.name || '|' || tg.color, ';;') AS tags_raw
        FROM todos t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN todo_tags tt ON tt.todo_id = t.id
        LEFT JOIN tags tg ON tg.id = tt.tag_id
        WHERE t.id = :id
          AND (t.user_id = :uid OR EXISTS (SELECT 1 FROM todo_shares ts WHERE ts.todo_id = t.id AND ts.user_id = :uid))
        GROUP BY t.id
    ");
    $stmt->execute([':uid' => $uid, ':id' => $id]);
    $row = $stmt->fetch();
    return $row ? format_todo($row) : null;
}

function fetch_todo_raw(int $uid, int $id): ?array {
    $stmt = db()->prepare("
        SELECT *, (user_id = ?) AS is_owner FROM todos WHERE id = ?
        AND (user_id = ? OR EXISTS (SELECT 1 FROM todo_shares ts WHERE ts.todo_id = todos.id AND ts.user_id = ?))
    ");
    $stmt->execute([$uid, $id, $uid, $uid]);
    return $stmt->fetch() ?: null;
}

function can_access_todo(int $uid, int $todo_id): bool {
    $stmt = db()->prepare('SELECT 1 FROM todos WHERE id=? AND (user_id=? OR EXISTS (SELECT 1 FROM todo_shares ts WHERE ts.todo_id=todos.id AND ts.user_id=?))');
    $stmt->execute([$todo_id, $uid, $uid]);
    return (bool)$stmt->fetch();
}

function sync_tags(int $todo_id, int $uid, ?array $tag_ids): void {
    if ($tag_ids === null) return;
    db()->prepare('DELETE FROM todo_tags WHERE todo_id=?')->execute([$todo_id]);
    if (empty($tag_ids)) return;
    $stmt = db()->prepare('INSERT OR IGNORE INTO todo_tags (todo_id, tag_id) SELECT ?, id FROM tags WHERE id=? AND user_id=?');
    foreach ($tag_ids as $tid) {
        $stmt->execute([$todo_id, (int)$tid, $uid]);
    }
}

function spawn_recurrence(int $uid, array $todo): ?array {
    $active_at = new DateTime($todo['active_at'] ?? 'now');
    $recur_type = $todo['recur_type'];

    if ($recur_type === 'daily') {
        $active_at->modify('+1 day');
    } elseif ($recur_type === 'monthly') {
        $active_at->modify('+1 month');
    } elseif ($recur_type === 'custom') {
        $active_at->modify('+' . max(1, (int)$todo['recur_interval']) . ' days');
    } elseif ($recur_type === 'weekly') {
        $days = json_decode($todo['recur_days'] ?? '[]', true); // [0=Sun..6=Sat]
        if (empty($days)) { $active_at->modify('+7 days'); }
        else {
            sort($days);
            $current_dow = (int)$active_at->format('w');
            $next_dow = null;
            foreach ($days as $d) { if ($d > $current_dow) { $next_dow = $d; break; } }
            if ($next_dow === null) $next_dow = $days[0];
            $diff = ($next_dow - $current_dow + 7) % 7 ?: 7;
            $active_at->modify("+{$diff} days");
        }
    }

    $next_active = $active_at->format('Y-m-d H:i:s');
    if ($todo['recur_ends_at'] && $next_active > $todo['recur_ends_at']) return null;

    $parent_id = $todo['recur_parent_id'] ?? $todo['id'];
    $stmt = db()->prepare('INSERT INTO todos (user_id,title,active_at,recur_type,recur_interval,recur_days,recur_ends_at,recur_parent_id) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$uid, $todo['title'], $next_active, $recur_type, $todo['recur_interval'], $todo['recur_days'], $todo['recur_ends_at'], $parent_id]);
    $new_id = (int)db()->lastInsertId();

    // Copy tags
    $stmt2 = db()->prepare('INSERT INTO todo_tags (todo_id, tag_id) SELECT ?, tag_id FROM todo_tags WHERE todo_id=?');
    $stmt2->execute([$new_id, $todo['id']]);

    // Copy shares
    $stmt3 = db()->prepare('INSERT INTO todo_shares (todo_id, user_id) SELECT ?, user_id FROM todo_shares WHERE todo_id=?');
    $stmt3->execute([$new_id, $todo['id']]);

    return fetch_todo($uid, $new_id);
}

// ─── Files ────────────────────────────────────────────────────
function get_files(int $uid): never {
    $todo_id = (int)($_GET['todo_id'] ?? 0);
    if (!can_access_todo($uid, $todo_id)) json_out(['error' => 'Not found'], 404);
    $stmt = db()->prepare('SELECT f.*, u.email AS uploader_email FROM files f JOIN users u ON u.id = f.uploaded_by WHERE f.todo_id = ? ORDER BY f.created_at ASC');
    $stmt->execute([$todo_id]);
    json_out($stmt->fetchAll());
}

function upload_file(int $uid): never {
    $todo_id = (int)($_POST['todo_id'] ?? 0);
    if (!can_access_todo($uid, $todo_id)) json_out(['error' => 'Not found'], 404);

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $codes = [1=>'File too large',2=>'File too large',3=>'Partial upload',4=>'No file sent',6=>'No temp dir',7=>'Cannot write',8=>'Extension blocked'];
        $msg = $codes[$_FILES['file']['error'] ?? 4] ?? 'Upload error';
        json_out(['error' => $msg], 422);
    }

    $allowed_mime = [
        'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
        'application/pdf',
        'text/plain','text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip','application/x-zip-compressed',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['file']['tmp_name']);
    if (!in_array($mime, $allowed_mime)) json_out(['error' => 'File type not allowed: ' . $mime], 422);

    $original  = basename($_FILES['file']['name']);
    $ext       = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $stored_as = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
    $dir       = __DIR__ . '/uploads/' . $uid . '/' . $todo_id;

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) json_out(['error' => 'Cannot create upload directory'], 500);
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $stored_as)) json_out(['error' => 'Could not save file'], 500);

    $stmt = db()->prepare('INSERT INTO files (todo_id, uploaded_by, filename, stored_as, mime_type, size_bytes) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$todo_id, $uid, $original, $stored_as, $mime, (int)$_FILES['file']['size']]);
    $id = (int)db()->lastInsertId();

    json_out(['id' => $id, 'todo_id' => $todo_id, 'filename' => $original, 'stored_as' => $stored_as,
              'mime_type' => $mime, 'size_bytes' => (int)$_FILES['file']['size'],
              'created_at' => date('Y-m-d H:i:s'), 'uploaded_by' => $uid, 'uploader_email' => '']);
}

function delete_file(int $uid, array $b): never {
    $id   = (int)($b['id'] ?? 0);
    $stmt = db()->prepare('SELECT f.*, t.user_id AS todo_owner FROM files f JOIN todos t ON t.id = f.todo_id WHERE f.id = ?');
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) json_out(['error' => 'Not found'], 404);
    if ($file['uploaded_by'] != $uid && $file['todo_owner'] != $uid) json_out(['error' => 'Forbidden'], 403);

    $path = __DIR__ . '/uploads/' . $file['uploaded_by'] . '/' . $file['todo_id'] . '/' . $file['stored_as'];
    if (file_exists($path)) unlink($path);
    db()->prepare('DELETE FROM files WHERE id = ?')->execute([$id]);
    json_out(['ok' => true]);
}
