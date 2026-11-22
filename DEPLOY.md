# Deployment

## Requirements

- PHP 8.1+ with extensions: `pdo_sqlite`, `openssl` (for SMTP TLS and Telegram HTTPS)
- Apache or nginx
- Cron access on the server (CLI `php`)

## Steps

### 1. Upload files

Copy the application files into your webroot, e.g. `/var/www/html/todo-app/`. Deploy from a clean export and include the public access rules in `.htaccess` if using Apache.

Include **`lang/`** (`en.json`, `de.json`). Without it the UI shows translation keys instead of labels.

Exclude `.git/`, `debugging/`, `tests/`, private storage, and personal config overlays.

When updating, deploy the PHP files, `js/app.js`, `css/app.css`, both language files, and the access rules together. Merge the new settings into your existing server `config.php` to preserve credentials. Back up the database and uploaded attachments before updating.

### 2. Configure the app

Edit `config.php` and set:

```php
define('APP_NAME', 'MyTasks');
define('APP_URL',  'https://yourdomain.com/todo-app'); // no trailing slash — must be the public URL

define('ALLOW_REGISTRATION', false); // allow only the first account on an empty database

define('SMTP_HOST', 'smtp.yourprovider.com');
define('SMTP_PORT', 587);  // 587 = STARTTLS, 465 = implicit SSL (SMTPS)
define('SMTP_USER', 'noreply@yourdomain.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_FROM', 'noreply@yourdomain.com');

define('TELEGRAM_BOT_TOKEN', ''); // optional; from @BotFather
define('TELEGRAM_BOT_USERNAME', ''); // optional; auto from getMe if empty
```

`APP_URL` is used for login redirects, session cookie path, and **Telegram/email links** to open a specific todo (`/?todo=ID`). If it is wrong (or still `http://127.0.0.1:8080`), login and notification links break.

To use PHP's `mail()` instead of SMTP (requires server-side sendmail), set:

```php
define('SMTP_HOST', '');
```

Both port 587 (STARTTLS) and port 465 (implicit SSL/SMTPS) are supported automatically.

Users pick Telegram, email, or both in Settings. Telegram is one bot from `TELEGRAM_BOT_TOKEN`; each user taps **Link Telegram** in Settings (no chat ID to copy). Language (English / Deutsch) is in Settings; guests can switch EN/DE on the login page. Notification text follows the **recipient's** language.

### 3. Put private storage outside the webroot

Set private paths in the deployed `config.php`, for example if `/srv/todo-app-data` is outside your document root.

```php
$privateData = '/srv/todo-app-data';
define('DB_PATH', $privateData . '/db/todos.db');
define('UPLOADS_PATH', $privateData . '/uploads');
define('CRON_LOG_PATH', $privateData . '/db/cron.log');
```

Use equivalent absolute private paths for other layouts. Create the database and upload directories and give the site's PHP and scheduled-task account access. Use `0700` for directories when those processes share one account. PHP creates the SQLite database and the `sessions/` directory under the database directory. Keep the same database path for both HTTP requests and cron.

If PHP uses `open_basedir`, append the absolute private storage directory to the site's existing allowed paths in the hosting PHP settings. In Plesk, this setting is under the domain's **PHP Settings**. On Linux, separate entries with `:`. Keep the private storage directory outside the document root.

If you have already moved the database outside the webroot, point `DB_PATH` at the existing `todos.db`. Pause requests and cron, back up existing attachments, then move the contents of the old public `uploads/` directory to the private `UPLOADS_PATH` while preserving its uploader/task subdirectories. Resume service after checking the paths and ownership. Check that no old database, session, upload, or log files remain in the public tree.

### 4. Set up the cron job

```bash
crontab -e
```

Add:

```
* * * * * php /var/www/html/todo-app/cron.php
```

This runs every minute to poll Telegram when using polling mode and send due notifications. Due notifications link to `APP_URL/?todo=ID`. Set `CRON_LOG_PATH` to a private path.

Use PHP CLI for scheduled tasks. If your scheduler supports custom headers and requires HTTPS, set a fresh `CRON_HEADER_SECRET`, allow that endpoint in the vhost, and have the scheduler authenticate with the `X-Cron-Secret` header.

### 5. nginx config (if not using Apache)

On Apache, enable the shipped `.htaccess` rules or apply equivalent rules in the vhost. For nginx, put these rules ahead of any general PHP regex location.

```nginx
location ^~ /todo-app/db/ {
    deny all;
}

location ^~ /todo-app/uploads/ {
    deny all;
}

location ^~ /todo-app/debugging/ {
    deny all;
}

location ^~ /todo-app/tests/ {
    deny all;
}

location ~ ^/todo-app/(?:.*/)?\. {
    deny all;
}

location ~ /todo-app/(config.*|cron)\.php$ {
    deny all;
}

location ~ /todo-app/.*\.(db|db-wal|db-shm|log)$ {
    deny all;
}

location /todo-app/ {
    try_files $uri $uri/ /todo-app/index.php?$query_string;
}
```

Verify on the deployed host that direct requests for private paths, configuration, and Git metadata return 403 or 404.
If a reverse proxy sits in front of PHP, configure the web server to set the real client address from that trusted proxy so login throttling uses the correct IP.

### 6. First run

1. Visit `https://yourdomain.com/todo-app/` and register your first account. The database schema is created automatically (or upgraded — see below).
2. Open **Settings** to choose the language and notifications. Send a verification email if others should be able to share tasks with you by email. If you use Telegram, tap **Link Telegram** and Start the bot.
3. Lost password: login page → **Forgot password?** A 30-minute link is emailed and, if Telegram is linked, sent in chat.
4. Keep `ALLOW_REGISTRATION` set to `false` on private installs. Restrict access during first-account setup on an empty database.

## Reusing an existing SQLite database

Use the existing `todos.db` at the private path configured in step 3. Before copying a live SQLite database, stop writers and take a consistent backup that includes any uncheckpointed WAL data.

On first request the app adds missing columns and leaves existing data in place:

| Column | Default for old rows |
|---|---|
| `users.telegram_chat_id` | empty |
| `users.notify_channel` | `telegram` (email is still used if Telegram is not configured) |
| `users.locale` | `en` |
| `users.display_name` | empty (set a short name in Settings to use `+Peter` when sharing) |
| `todos.priority` | `4` (none) |
| `todos.parent_id` | empty (no sub-tasks) |
| `users.email_verified` | false until verified in Settings |
| `users.auth_version` | 1 |

A `telegram_link_tokens` table is created if missing (used by **Link Telegram** in Settings). Existing `telegram_chat_id` values stay valid; users do not need to re-link. `password_reset_tokens` is created for **Forgot password?** links.

Existing shares keep their recipients. New shares by email require the recipient to verify their address in Settings. Sharing by display name still works. Sign in again if prompted after deploying.

## File permissions summary

| Path | Permission | Notes |
|---|---|---|
| Database directory | writable by the site's PHP and cron user | Private SQLite files, PHP sessions, and cron log |
| Upload directory | writable by the site's PHP user | Private attachments served through `download.php` |
| `lang/` | readable by web user | `en.json` / `de.json` — required for the UI |
| `config.php` | `640` | Contains credentials — not web-accessible |
| `cron.php` | `640` | CLI-only by default — not web-accessible |

## File attachments

Uploads are enabled. Limits:

- Max size: 20 MB (`MAX_UPLOAD_BYTES` in `config.php`; also `upload_max_filesize` / `post_max_size` in PHP)
- Types: images, PDF, plain text/CSV, Word, Excel, ZIP
- Storage: `{UPLOADS_PATH}/{uploader_id}/{todo_id}/{uuid}.ext`
- Download: `download.php?f=…` after an access check (owner or sharee)

Set `UPLOADS_PATH` outside the document root. The access rules also block requests to the legacy `uploads/` location.

## Notifications

| Channel | What you need |
|---|---|
| Email | `SMTP_*` in `config.php`, or empty `SMTP_HOST` to use `mail()` |
| Telegram | `TELEGRAM_BOT_TOKEN`; each user taps Link Telegram in Settings |
| Both | Both of the above; users pick the channel in Settings |

The cron job sends each scheduled reminder once per recipient within their `notify_minutes` window. Recipients include the todo owner and anyone the todo is shared with. Rescheduling a task gives it a fresh reminder and keeps earlier sends in the daily count. Messages are in the recipient's language and include a link to that task (`/?todo=ID`). If you are not logged in, login continues to that same URL.

Owners can complete the task using the circular checkbox beside its title in the detail view opened by the notification link. Completion closes the detail view and offers Undo. Shared recipients can view the task, but completion remains an owner action.

### Create tasks from Telegram

After linking in Settings, message the bot a title. Dates can be at the start or end (`16 Uhr bügeln`, `tomorrow 9am`, `morgen 9 Uhr`) or quoted (`"friday 18:00"`). `#Tag`, `p1`, and `<+email>` still work. If the new task has a time, the confirmation includes a `.ics` file (30-minute event) to add to a calendar; there is no “open & complete” link on that ack.

Send `?` for today's and tomorrow's overviews together, or `today?` / `heute?` or `tomorrow?` / `morgen?` for one day. Today's overview includes overdue incomplete tasks. The day words also work without a question mark; full-width question marks and extra whitespace are accepted. `/start` or `/help` shows examples. `/unlink` disconnects the chat.

Incoming messages use minute polling by default. For instant replies, the operator sets `TELEGRAM_WEBHOOK_HEADER_SECRET` to a new random value, for example one generated by `openssl rand -hex 32`, and runs `php /path/to/todo-app/cron.php telegram-webhook enable`. Set an HTTPS `APP_URL` and allow access to `telegram.php`. Telegram supplies the secret in the webhook header.

If upgrading an existing webhook, use a new secret and re-register it with the enable command during deployment. To switch to minute polling, run `php /path/to/todo-app/cron.php telegram-webhook disable`. Keep the scheduled CLI job for due notifications in either mode.

## Optional config

```php
define('ALLOW_REGISTRATION', false);  // first user still allowed
define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);
define('CRON_LOG_PATH',    dirname(DB_PATH) . '/cron.log');
define('CRON_DAILY_LIMIT', 100);      // notifications per calendar day
define('CRON_HEADER_SECRET', '');     // leave empty for CLI only
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_BOT_USERNAME', ''); // optional; auto from getMe if empty
define('TELEGRAM_WEBHOOK_HEADER_SECRET', '');
```
