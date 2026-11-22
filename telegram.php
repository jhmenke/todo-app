<?php
/**
 * Telegram webhook — POST JSON updates from Telegram.
 * Authenticated with the X-Telegram-Bot-Api-Secret-Token header.
 */
require_once __DIR__ . '/app.php';

if (!TELEGRAM_BOT_TOKEN) {
    http_response_code(503);
    exit('Telegram is not configured');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed');
}

$secret = telegram_webhook_secret();
$header = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($secret === '' || !hash_equals($secret, $header)) {
    http_response_code(403);
    exit('Forbidden');
}

$update = json_decode((string) file_get_contents('php://input'), true);
if (is_array($update)) {
    handle_telegram_update($update);
}
http_response_code(200);
echo 'ok';
