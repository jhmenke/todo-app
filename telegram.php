<?php
/**
 * Telegram webhook — POST JSON updates from Telegram.
 * Optional: ?key=SECRET  or header X-Telegram-Bot-Api-Secret-Token
 */
require_once __DIR__ . '/app.php';

if (!TELEGRAM_BOT_TOKEN) {
    http_response_code(503);
    exit('Telegram is not configured');
}

$secret = TELEGRAM_WEBHOOK_SECRET !== ''
    ? TELEGRAM_WEBHOOK_SECRET
    : substr(hash('sha256', 'wh:' . TELEGRAM_BOT_TOKEN), 0, 32);
$header = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
$query  = (string) ($_GET['key'] ?? '');
if ($secret !== '' && !hash_equals($secret, $header) && !hash_equals($secret, $query)) {
    http_response_code(403);
    exit('Forbidden');
}

$update = json_decode((string) file_get_contents('php://input'), true);
if (is_array($update)) {
    handle_telegram_update($update);
}
http_response_code(200);
echo 'ok';
