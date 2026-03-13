# Deployment

## Requirements

- PHP 8.1+ with extensions: `pdo_sqlite`, `openssl` (for SMTP TLS)
- Apache or nginx
- Cron access on the server

## Steps

### 1. Upload files

Copy the `todo-app/` directory into your webroot, e.g. `/var/www/html/todo-app/`.

### 2. Configure the app

Edit `config.php` and set:

```php
define('APP_URL', 'https://yourdomain.com/todo-app'); // no trailing slash

define('SMTP_HOST', 'smtp.yourprovider.com');
define('SMTP_PORT', 587);  // 587 = STARTTLS, 465 = implicit SSL (SMTPS)
define('SMTP_USER', 'noreply@yourdomain.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_FROM', 'noreply@yourdomain.com');
```

To use PHP's `mail()` instead of SMTP (requires server-side sendmail), set:

```php
define('SMTP_HOST', '');
```

Both port 587 (STARTTLS) and port 465 (implicit SSL/SMTPS) are supported automatically.

### 3. Make the database directory writable

```bash
chmod 775 /var/www/html/todo-app/db
chown www-data:www-data /var/www/html/todo-app/db
```

The SQLite database file (`db/todos.db`) is created automatically on first request.

### 4. Set up the cron job

```bash
crontab -e
```

Add:

```
* * * * * php /var/www/html/todo-app/cron.php
```

This runs every minute and sends notification emails for todos that activate within each user's configured lead time. Logs are written to `cron.log` in the app directory. A hard limit of 20 emails per day (configurable via `CRON_DAILY_LIMIT` in `config.php`) protects against runaway sending.

### 5. nginx config (if not using Apache)

Apache reads `.htaccess` automatically. For nginx, add this to your server block:

```nginx
location /todo-app/uploads/ {
    deny all;
}

location ~ /todo-app/(config|cron)\.php$ {
    deny all;
}

location /todo-app/ {
    try_files $uri $uri/ /todo-app/index.php?$query_string;
}
```

### 6. First run

Visit `https://yourdomain.com/todo-app/` and register your first account. The database schema is created automatically.

## File permissions summary

| Path | Permission | Notes |
|---|---|---|
| `db/` | `775`, owned by web user | Must be writable for SQLite |
| `uploads/` | `775`, owned by web user | Required when file uploads are enabled |
| `config.php` | `640` | Contains credentials — not web-accessible (blocked by `.htaccess`) |
| `cron.php` | `640` | Not web-accessible (blocked by `.htaccess`) |

## Enabling file uploads (future)

When you're ready to add file upload support:

1. Ensure `uploads/` is writable (same as `db/` above)
2. The `uploads/.htaccess` already blocks direct access
3. `download.php` is already in place and auth-gated
4. Add an upload endpoint in `api.php` and wire the UI — no architecture changes needed

Uploaded files are stored at `uploads/{user_id}/{todo_id}/{uuid_filename}` and served only through `download.php`.
