<?php
/**
 * File download stub — wire up when file uploads are added.
 * Files are never served directly; all downloads go through here
 * so auth is enforced before any file is sent.
 */
require_once __DIR__ . '/config.php';
$user = require_auth();
$uid  = (int)$user['id'];

$stored_as = basename($_GET['f'] ?? '');
if ($stored_as === '') { http_response_code(400); exit('Missing file parameter'); }

$stmt = db()->prepare('SELECT * FROM files WHERE stored_as=?');
$stmt->execute([$stored_as]);
$file = $stmt->fetch();

if (!$file) { http_response_code(404); exit('Not found'); }

// Check access: must be able to see the todo
if (!can_access_todo($uid, (int)$file['todo_id'])) {
    http_response_code(403); exit('Forbidden');
}

$path = __DIR__ . '/uploads/' . $file['uploaded_by'] . '/' . $file['todo_id'] . '/' . $stored_as;
if (!file_exists($path)) { http_response_code(404); exit('File missing'); }

$mime = $file['mime_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($file['filename']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);

function can_access_todo(int $uid, int $todo_id): bool {
    $stmt = db()->prepare('SELECT 1 FROM todos WHERE id=? AND (user_id=? OR EXISTS (SELECT 1 FROM todo_shares ts WHERE ts.todo_id=todos.id AND ts.user_id=?))');
    $stmt->execute([$todo_id, $uid, $uid]);
    return (bool)$stmt->fetch();
}
