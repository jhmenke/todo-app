<?php
/**
 * Notification cron — run every minute:
 *   * * * * * php /path/to/todo-app/cron.php
 *
 * Logs are written to CRON_LOG_PATH (defined in config.php).
 * Sends at most CRON_DAILY_LIMIT emails per calendar day.
 */
require_once __DIR__ . '/config.php';

$log = function(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(CRON_LOG_PATH, $line, FILE_APPEND | LOCK_EX);
};

// ── Daily send budget ─────────────────────────────────────────
$sentToday = (int) db()->query("
    SELECT COUNT(*) FROM notifications_sent
    WHERE date(sent_at, 'localtime') = date('now', 'localtime')
")->fetchColumn();

$remaining = CRON_DAILY_LIMIT - $sentToday;

if ($remaining <= 0) {
    $log("Daily limit of " . CRON_DAILY_LIMIT . " emails reached ({$sentToday} sent today). Skipping run.");
    exit;
}

// ── Find pending notifications ────────────────────────────────
$sql = "
    SELECT DISTINCT
        t.id          AS todo_id,
        t.title,
        t.active_at,
        t.recur_type,
        u_recipient.id    AS recipient_id,
        u_recipient.email           AS recipient_email,
        u_owner.email               AS owner_email,
        u_recipient.notify_minutes,
        u_recipient.telegram_chat_id,
        u_recipient.notify_channel
    FROM todos t
    JOIN users u_owner ON u_owner.id = t.user_id
    JOIN (
        -- owner
        SELECT id, email, notify_minutes, telegram_chat_id, notify_channel, 'owner' AS role FROM users
        UNION ALL
        -- shared users
        SELECT u2.id, u2.email, u2.notify_minutes, u2.telegram_chat_id, u2.notify_channel, 'shared' AS role
        FROM users u2
        JOIN todo_shares ts ON ts.user_id = u2.id
    ) u_recipient ON (
        u_recipient.id = t.user_id
        OR EXISTS (SELECT 1 FROM todo_shares ts2 WHERE ts2.todo_id = t.id AND ts2.user_id = u_recipient.id)
    )
    WHERE t.completed_at IS NULL
      AND t.active_at IS NOT NULL
      AND t.active_at > datetime('now', 'localtime')
      AND t.active_at <= datetime('now', 'localtime', '+' || u_recipient.notify_minutes || ' minutes')
      AND NOT EXISTS (
          SELECT 1 FROM notifications_sent ns
          WHERE ns.todo_id = t.id AND ns.user_id = u_recipient.id
      )
";

$rows = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) === 0) {
    exit;
}

$insert_sent = db()->prepare('INSERT OR IGNORE INTO notifications_sent (todo_id, user_id) VALUES (?,?)');
$sentEmails = 0;
$sentTelegrams = 0;

foreach ($rows as $row) {
    $channel  = $row['notify_channel'] ?? 'telegram';
    $hasTg    = !empty($row['telegram_chat_id']);
    $active   = date('D, M j \a\t g:i A', strtotime($row['active_at']));
    $recur    = $row['recur_type'] ? ' (recurring: ' . $row['recur_type'] . ')' : '';
    $shared   = ($row['recipient_email'] !== $row['owner_email']) ? ' — shared by ' . $row['owner_email'] : '';
    $notified = false;

    // ── Telegram ──────────────────────────────────────────────
    if ($hasTg && in_array($channel, ['telegram', 'both'])) {
        $text = '<b>' . htmlspecialchars($row['title']) . '</b>' . "\n"
              . $active . $recur . $shared . "\n"
              . 'Active in ' . (int)$row['notify_minutes'] . ' minute(s).';
        if (send_telegram($row['telegram_chat_id'], $text)) {
            $notified = true;
            $sentTelegrams++;
            $log("Telegram sent to {$row['recipient_email']} for todo #{$row['todo_id']}: {$row['title']}");
        } else {
            $log("FAILED Telegram to {$row['recipient_email']} for todo #{$row['todo_id']}");
        }
    }

    // ── Email ──────────────────────────────────────────────────
    // Send if channel is 'email' or 'both', or as fallback when telegram was chosen but not configured
    $wantsEmail = in_array($channel, ['email', 'both']) || ($channel === 'telegram' && !$hasTg);
    if ($wantsEmail) {
        if ($sentEmails >= $remaining) {
            $log("Daily email limit reached mid-run. " . (count($rows) - $sentEmails) . " notification(s) deferred.");
            break;
        }
        $subject = '[' . APP_NAME . '] Upcoming: ' . $row['title'];
        $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:480px;margin:40px auto;color:#1e293b">
            <h2 style="color:#6366f1;margin-bottom:4px">' . htmlspecialchars($row['title']) . '</h2>
            <p style="color:#64748b;margin-top:0">' . $active . $recur . htmlspecialchars($shared) . '</p>
            <p>This todo becomes active in ' . (int)$row['notify_minutes'] . ' minute(s).</p>
            <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
            <p style="color:#94a3b8;font-size:12px">' . APP_NAME . ' — <a href="' . APP_URL . '" style="color:#6366f1">Open app</a></p>
        </body></html>';
        if (send_email($row['recipient_email'], $subject, $html)) {
            $notified = true;
            $sentEmails++;
            $log("Email sent ({$sentEmails}/" . CRON_DAILY_LIMIT . ") to {$row['recipient_email']} for todo #{$row['todo_id']}: {$row['title']}");
        } else {
            $log("FAILED email to {$row['recipient_email']} for todo #{$row['todo_id']}");
        }
    }

    if ($notified) {
        $insert_sent->execute([$row['todo_id'], $row['recipient_id']]);
    }
}

if ($sentEmails > 0 || $sentTelegrams > 0) {
    $log("Run complete. {$sentTelegrams} Telegram(s), {$sentEmails} email(s) sent. Email total today: " . ($sentToday + $sentEmails) . "/" . CRON_DAILY_LIMIT . ".");
}
