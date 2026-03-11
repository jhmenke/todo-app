<?php
/**
 * Mail + SMTP diagnostic — DELETE or restrict this file after testing.
 * Access: https://jhmenke.de/todo-app/test_mail.php
 */
require_once __DIR__ . '/config.php';
$user = require_auth();  // must be logged in

$result  = null;
$to      = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to     = trim($_POST['to'] ?? $user['email']);
    $result = run_test($to);
}

function run_test(string $to): array {
    $steps = [];

    // 1. OpenSSL available?
    $steps[] = ['label' => 'OpenSSL extension', 'ok' => extension_loaded('openssl'), 'detail' => extension_loaded('openssl') ? phpversion('openssl') : 'NOT loaded — required for port 465/587'];

    // 2. Can we reach the SMTP host?
    $errno = $errstr = null;
    if (SMTP_HOST === '') {
        $steps[] = ['label' => 'SMTP mode', 'ok' => true, 'detail' => 'Using PHP mail() — skipping connection test'];
    } else {
        $prefix = (SMTP_PORT === 465) ? 'ssl://' : '';
        $sock   = @fsockopen($prefix . SMTP_HOST, SMTP_PORT, $errno, $errstr, 5);
        $steps[] = [
            'label'  => 'TCP connection to ' . SMTP_HOST . ':' . SMTP_PORT,
            'ok'     => (bool)$sock,
            'detail' => $sock ? 'Connected OK' : "Failed: [{$errno}] {$errstr}",
        ];
        if ($sock) fclose($sock);
    }

    // 3. Try sending
    $subject = '[' . APP_NAME . '] Test email ' . date('H:i:s');
    $html    = '<p>This is a test email from <strong>' . h(APP_NAME) . '</strong> sent at ' . date('Y-m-d H:i:s') . '.</p>'
             . '<p>SMTP: ' . h(SMTP_HOST) . ':' . SMTP_PORT . '<br>From: ' . h(SMTP_FROM) . '</p>';

    $ok      = send_email($to, $subject, $html);
    $steps[] = ['label' => 'send_email() call', 'ok' => $ok, 'detail' => $ok ? 'Returned true — check your inbox (also spam folder)' : 'Returned false — see SMTP connection result above'];

    return $steps;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mail test — <?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-2xl shadow border border-gray-200 w-full max-w-lg p-6 space-y-5">
    <div>
        <h1 class="font-bold text-lg text-slate-800">Mail diagnostic</h1>
        <p class="text-sm text-gray-500 mt-0.5">Tests SMTP connection and sends a real email.</p>
    </div>

    <div class="bg-gray-50 rounded-lg p-4 text-sm space-y-1">
        <p><span class="text-gray-500">SMTP host:</span> <strong><?= h(SMTP_HOST ?: '(using mail())') ?></strong></p>
        <p><span class="text-gray-500">Port:</span> <strong><?= SMTP_PORT ?> <?= SMTP_PORT === 465 ? '(implicit SSL)' : (SMTP_PORT === 587 ? '(STARTTLS)' : '') ?></strong></p>
        <p><span class="text-gray-500">From:</span> <strong><?= h(SMTP_FROM) ?></strong></p>
    </div>

    <form method="POST" class="flex gap-2">
        <input type="email" name="to" value="<?= h($to) ?>"
            class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm">
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            Send test
        </button>
    </form>

    <?php if ($result): ?>
    <div class="space-y-2">
        <?php foreach ($result as $step): ?>
        <div class="flex items-start gap-3 p-3 rounded-lg <?= $step['ok'] ? 'bg-green-50' : 'bg-red-50' ?>">
            <span class="mt-0.5 text-lg"><?= $step['ok'] ? '✓' : '✗' ?></span>
            <div>
                <p class="text-sm font-medium <?= $step['ok'] ? 'text-green-800' : 'text-red-800' ?>"><?= h($step['label']) ?></p>
                <p class="text-xs <?= $step['ok'] ? 'text-green-600' : 'text-red-600' ?> mt-0.5"><?= h($step['detail']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="border-t border-gray-100 pt-4 space-y-2 text-sm text-gray-600">
        <p class="font-medium text-gray-700">Check cron manually</p>
        <p>Run this in Plesk → <strong>Geplante Aufgaben → Jetzt ausführen</strong>, or via SSH:</p>
        <pre class="bg-gray-100 rounded p-2 text-xs overflow-x-auto">php /var/www/vhosts/jhmenke.de/httpdocs/todo-app/cron.php</pre>
        <p>Output goes to <code class="bg-gray-100 px-1 rounded">/var/log/todo-cron.log</code> — or click "Jetzt ausführen" and watch the Plesk output.</p>
        <p class="font-medium text-gray-700 pt-1">Cron only fires for todos where:</p>
        <ul class="list-disc list-inside text-xs text-gray-500 space-y-0.5">
            <li>Activate time is set (not blank)</li>
            <li>Activate time is in the future but within your notify window</li>
            <li>Not already completed</li>
            <li>Not already notified (each todo notifies exactly once)</li>
        </ul>
    </div>

    <a href="/" class="block text-center text-sm text-indigo-600 hover:underline">← Back to app</a>
</div>
</body>
</html>
