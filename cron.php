<?php
/**
 * Notification cron — run every minute:
 *   * * * * * php /path/to/todo-app/cron.php >> /var/log/todo-cron.log 2>&1
 */
require_once __DIR__ . '/config.php';

$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

// For each user, find todos that activate within their notify_minutes window
// and haven't been notified yet (for that user — includes shared todos)
$sql = "
    SELECT DISTINCT
        t.id          AS todo_id,
        t.title,
        t.active_at,
        t.recur_type,
        u_recipient.id    AS recipient_id,
        u_recipient.email AS recipient_email,
        u_owner.email     AS owner_email,
        u_recipient.notify_minutes
    FROM todos t
    JOIN users u_owner ON u_owner.id = t.user_id
    JOIN (
        -- owner
        SELECT id, email, notify_minutes, 'owner' AS role FROM users
        UNION ALL
        -- shared users
        SELECT u2.id, u2.email, u2.notify_minutes, 'shared' AS role
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
$log(count($rows) . ' notification(s) to send.');

$insert_sent = db()->prepare('INSERT OR IGNORE INTO notifications_sent (todo_id, user_id) VALUES (?,?)');

foreach ($rows as $row) {
    $active = date('D, M j \a\t g:i A', strtotime($row['active_at']));
    $recur  = $row['recur_type'] ? ' (recurring: ' . $row['recur_type'] . ')' : '';
    $shared = ($row['recipient_email'] !== $row['owner_email']) ? ' — shared by ' . htmlspecialchars($row['owner_email']) : '';

    $subject = '[' . APP_NAME . '] Upcoming: ' . $row['title'];
    $html = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:480px;margin:40px auto;color:#1e293b">
        <h2 style="color:#6366f1;margin-bottom:4px">' . htmlspecialchars($row['title']) . '</h2>
        <p style="color:#64748b;margin-top:0">' . $active . $recur . $shared . '</p>
        <p>This todo becomes active in ' . (int)$row['notify_minutes'] . ' minute(s).</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">
        <p style="color:#94a3b8;font-size:12px">' . APP_NAME . ' — <a href="' . APP_URL . '" style="color:#6366f1">Open app</a></p>
    </body></html>';

    if (send_email($row['recipient_email'], $subject, $html)) {
        $insert_sent->execute([$row['todo_id'], $row['recipient_id']]);
        $log("Sent to {$row['recipient_email']} for todo #{$row['todo_id']}: {$row['title']}");
    } else {
        $log("FAILED to send to {$row['recipient_email']} for todo #{$row['todo_id']}");
    }
}
