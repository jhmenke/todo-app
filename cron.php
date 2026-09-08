<?php
/**
 * Notification cron — run every minute:
 *   * * * * * php /path/to/todo-app/cron.php
 *
 * Logs are written to CRON_LOG_PATH (defined in config.php).
 * Sends at most CRON_DAILY_LIMIT notifications per calendar day.
 */
require_once __DIR__ . '/app.php';

if (PHP_SAPI !== 'cli') {
    $secret = CRON_SECRET;
    $given  = (string) ($_GET['key'] ?? '');
    if ($secret === '' || !hash_equals($secret, $given)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

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

telegram_poll_updates();

if ($remaining <= 0) {
    $log("Daily limit of " . CRON_DAILY_LIMIT . " notifications reached ({$sentToday} sent today). Skipping run.");
    exit;
}

// ── Find pending notifications ────────────────────────────────
$sql = "
    SELECT DISTINCT
        t.id          AS todo_id,
        t.title,
        t.active_at,
        t.recur_type,
        u.id          AS recipient_id,
        u.email       AS recipient_email,
        u_owner.email AS owner_email,
        u.notify_minutes,
        u.telegram_chat_id,
        u.notify_channel,
        u.locale
    FROM todos t
    JOIN users u_owner ON u_owner.id = t.user_id
    JOIN users u ON (
        u.id = t.user_id
        OR EXISTS (SELECT 1 FROM todo_shares ts WHERE ts.todo_id = t.id AND ts.user_id = u.id)
    )
    WHERE t.completed_at IS NULL
      AND t.active_at IS NOT NULL
      AND t.active_at > datetime('now', 'localtime')
      AND t.active_at <= datetime('now', 'localtime', '+' || u.notify_minutes || ' minutes')
      AND NOT EXISTS (
          SELECT 1 FROM notifications_sent ns
          WHERE ns.todo_id = t.id AND ns.user_id = u.id
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
    $loc      = normalize_locale($row['locale'] ?? 'en');
    $mins     = (int) $row['notify_minutes'];
    $active   = format_notify_when($row['active_at'], $loc);
    $recurKey = $row['recur_type'] ? 'recur.' . $row['recur_type'] : '';
    $recur    = $row['recur_type'] ? ' (' . t('notify.recurring', ['type' => t($recurKey, [], $loc)], $loc) . ')' : '';
    $shared   = ($row['recipient_email'] !== $row['owner_email'])
        ? ' — ' . t('notify.shared_by', ['email' => $row['owner_email']], $loc) : '';
    $notified = false;
    $link     = todo_url((int) $row['todo_id']);
    $linkLabel = t('notify.open_task', [], $loc);

    // ── Telegram ──────────────────────────────────────────────
    if ($hasTg && in_array($channel, ['telegram', 'both'])) {
        $text = '<b>' . htmlspecialchars($row['title']) . '</b>' . "\n"
              . $active . $recur . $shared . "\n"
              . t('notify.active_in', ['n' => $mins], $loc) . "\n"
              . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($linkLabel) . '</a>';
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
        $subject = '[' . APP_NAME . '] ' . t('notify.email_subject', ['title' => $row['title']], $loc);
        $html = '<!DOCTYPE html><html lang="' . h($loc) . '"><body style="font-family:sans-serif;max-width:480px;margin:40px auto;color:#1e293b">
            <h2 style="color:#5b4dff;margin-bottom:4px">' . htmlspecialchars($row['title']) . '</h2>
            <p style="color:#64748b;margin-top:0">' . htmlspecialchars($active . $recur . $shared) . '</p>
            <p>' . htmlspecialchars(t('notify.email_body', ['n' => $mins], $loc)) . '</p>
            <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
            <p style="color:#94a3b8;font-size:12px">' . APP_NAME . ' — <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="color:#5b4dff">' . htmlspecialchars($linkLabel) . '</a></p>
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
