<?php
/**
 * Auth-gated file downloads. Files are never served directly.
 */
require_once __DIR__ . '/app.php';
$user = require_auth();
$uid  = (int)$user['id'];

$stored_as = basename($_GET['f'] ?? '');
if ($stored_as === '') { http_response_code(400); exit('Missing file parameter'); }

$stmt = db()->prepare('SELECT * FROM files WHERE stored_as=?');
$stmt->execute([$stored_as]);
$file = $stmt->fetch();

if (!$file) { http_response_code(404); exit('Not found'); }

if (!can_access_todo($uid, (int)$file['todo_id'])) {
    http_response_code(403); exit('Forbidden');
}

$path = __DIR__ . '/uploads/' . $file['uploaded_by'] . '/' . $file['todo_id'] . '/' . $stored_as;
if (!is_file($path)) { http_response_code(404); exit('File missing'); }

$mime = $file['mime_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . content_disposition_attachment((string)$file['filename']));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
