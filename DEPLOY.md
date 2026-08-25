# Deployment

## Requirements

- PHP 8.1+ with extensions: `pdo_sqlite`, `openssl` (for SMTP TLS and Telegram HTTPS)
- Apache or nginx
- Cron access on the server (CLI `php`, not a URL fetch)

## Steps

### 1. Upload files

Copy the `todo-app/` directory into your webroot, e.g. `/var/www/html/todo-app/`.

Include **`lang/`** (`en.json`, `de.json`). Without it the UI shows translation keys instead of labels.

Do **not** deploy `debugging/` or any `*_jhmenke.php` / personal config overlays.

### 2. Configure the app

Edit `config.php` and set:

```php
define('APP_NAME', 'MyTasks');
define('APP_URL',  'https://yourdomain.com/todo-app'); // no trailing slash — must be the public URL

define('ALLOW_REGISTRATION', true); // set false after you create your account

define('SMTP_HOST', 'smtp.yourprovider.com');
define('SMTP_PORT', 587);  // 587 = STARTTLS, 465 = implicit SSL (SMTPS)
define('SMTP_USER', 'noreply@yourdomain.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_FROM', 'noreply@yourdomain.com');

define('TELEGRAM_BOT_TOKEN', ''); // optional; from @BotFather
```

`APP_URL` is used for login redirects, session cookie path, and **Telegram/email links** to open a specific todo (`/?todo=ID`). If it is wrong (or still `http://127.0.0.1:8080`), login and notification links break.

To use PHP's `mail()` instead of SMTP (requires server-side sendmail), set:

```php
define('SMTP_HOST', '');
```

Both port 587 (STARTTLS) and port 465 (implicit SSL/SMTPS) are supported automatically.

Users pick Telegram, email, or both in Settings. Telegram also needs each user's chat ID (Settings explains how to get it from `@userinfobot`). Language (English / Deutsch) is in Settings; guests can switch EN/DE on the login page. Notification text follows the **recipient's** language.

### 3. Make the database and uploads directories writable

```bash
chmod 775 /var/www/html/todo-app/db /var/www/html/todo-app/uploads
chown www-data:www-data /var/www/html/todo-app/db /var/www/html/todo-app/uploads
```

The SQLite database (`db/todos.db`) is created automatically on first request. Attachments are stored under `uploads/{user_id}/{todo_id}/` and are never served directly.

### 4. Set up the cron job

```bash
crontab -e
```

Add:

```
* * * * * php /var/www/html/todo-app/cron.php
```

This runs every minute and sends Telegram and/or email notifications for todos that activate within each user's lead time. Telegram (and email) include a link to `APP_URL/?todo=ID` so you can open that task in the browser and complete it. Logs go to `cron.log` in the app directory (path override: `CRON_LOG_PATH`). A hard limit of 100 notifications per day (configurable via `CRON_DAILY_LIMIT`) protects against runaway sending.

`cron.php` is CLI-only by default and is blocked from the web. If your host can only trigger HTTP cron, set `CRON_SECRET` in `config.php`, allow web access to `cron.php`, and call `cron.php?key=YOUR_SECRET`.

### 5. nginx config (if not using Apache)

Apache reads `.htaccess` automatically (denies `config.php`, `cron.php`, `db/`, `uploads/`, and `*.db` / `*.log`). If `AllowOverride` is `None`, those rules never apply — deny `db/` and `uploads/` in the vhost instead. For nginx, add this to your server block:

```nginx
location /todo-app/db/ {
    deny all;
}

location /todo-app/uploads/ {
    deny all;
}

location /todo-app/debugging/ {
    deny all;
}

location ~ /todo-app/(config.*|cron)\.php$ {
    deny all;
}

location ~ /todo-app/.*\.(db|log)$ {
    deny all;
}

location /todo-app/ {
    try_files $uri $uri/ /todo-app/index.php?$query_string;
}
```

### 6. First run

1. Visit `https://yourdomain.com/todo-app/` and register your first account. The database schema is created automatically (or upgraded — see below).
2. Open **Settings**: language, notification channel, lead time, Telegram chat ID if you use Telegram.
3. Set `ALLOW_REGISTRATION` to `false` in `config.php` so strangers cannot create accounts. (The very first user can still register even when this is false, in case the database is empty.)

## Reusing an existing SQLite database

You can keep a `todos.db` from an older version of this app. Copy it into `db/` (and copy `uploads/` if you had attachments). Back it up first, including any `todos.db-wal` / `todos.db-shm` files.

On first request the app adds missing columns and leaves existing data in place:

| Column | Default for old rows |
|---|---|
| `users.telegram_chat_id` | empty |
| `users.notify_channel` | `telegram` (email is still used if Telegram is not configured) |
| `users.locale` | `en` |
| `todos.priority` | `4` (none) |
| `todos.parent_id` | empty (no sub-tasks) |

Passwords, todos, tags, shares, comments, and files stay as they are. Existing users can switch to Deutsch in Settings. Log in once after deploy (cookie path follows `APP_URL`).

## File permissions summary

| Path | Permission | Notes |
|---|---|---|
| `db/` | `775`, owned by web user | Must be writable for SQLite (`todos.db`, WAL files). Blocked from HTTP. |
| `uploads/` | `775`, owned by web user | File attachments. Blocked from HTTP; downloads go through `download.php`. |
| `lang/` | readable by web user | `en.json` / `de.json` — required for the UI |
| `config.php` | `640` | Contains credentials — not web-accessible |
| `cron.php` | `640` | CLI-only by default — not web-accessible |

## File attachments

Uploads are enabled. Limits:

- Max size: 20 MB (`MAX_UPLOAD_BYTES` in `config.php`; also `upload_max_filesize` / `post_max_size` in PHP)
- Types: images, PDF, plain text/CSV, Word, Excel, ZIP
- Storage: `uploads/{uploader_id}/{todo_id}/{uuid}.ext`
- Download: `download.php?f=…` after an access check (owner or sharee)

`uploads/.htaccess` and the nginx rules above block direct URL access, so a guessed filename cannot be fetched without a login.

## Notifications

| Channel | What you need |
|---|---|
| Email | `SMTP_*` in `config.php`, or empty `SMTP_HOST` to use `mail()` |
| Telegram | `TELEGRAM_BOT_TOKEN`, plus each user's chat ID in Settings |
| Both | Both of the above; users pick the channel in Settings |

The cron job notifies the todo owner and anyone the todo is shared with, once per (todo, user), when `active_at` falls inside that user's `notify_minutes` window. Messages are in the recipient's language and include a link to that task (`/?todo=ID`). If you are not logged in, login continues to that same URL.

## Optional config

```php
define('ALLOW_REGISTRATION', true);   // false = login only (first user still allowed)
define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);
define('CRON_LOG_PATH',    __DIR__ . '/cron.log');
define('CRON_DAILY_LIMIT', 100);      // notifications per calendar day
define('CRON_SECRET',      '');       // HTTP cron key; leave empty for CLI only
define('TELEGRAM_BOT_TOKEN', '');
```
