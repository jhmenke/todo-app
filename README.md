# Todo App

A self-hosted task manager written in PHP 8.1+, SQLite, Alpine.js, and Tailwind CSS (loaded from a CDN). Copy the files onto a PHP host, edit `config.php`, and schedule the minute cron job.

## Features

- Create, edit, complete, and delete tasks, including subtasks and priorities
- Color-coded tags for grouping work
- Recurring tasks: daily, weekly, monthly, or a custom interval. Completing a recurring task schedules the next future occurrence.
- Share a task with other registered users, including inline `<+email>` while composing
- Comments and file attachments on a task
- Email and Telegram notifications before a task’s activation time (SMTP, PHP `mail()`, or a Telegram bot)
- Create tasks by messaging the Telegram bot, using the same `<datetime>`, `#tag`, and `p1` tokens as the web form
- English and German UI. Dates use `YYYY.MM.DD`. German uses 24-hour time; English uses 12-hour AM/PM.
- User accounts: registration (can be disabled after the first user), login with a 90-day remember-me cookie, and password change
- Opt-in datetime tags in titles, for example `<friday 18:00>` or `<morgen 9 Uhr>`

## Tech stack

- **Backend:** PHP 8.1+, SQLite via PDO
- **Frontend:** Alpine.js, Tailwind CSS (CDN)
- **Email:** SMTP with STARTTLS or implicit SSL, or PHP `mail()`
- **Telegram:** Bot API (notifications and inbound task create; webhook or cron `getUpdates`)
- **Storage:** one SQLite file under `db/`

## Setup

See [DEPLOY.md](DEPLOY.md) for deployment details. In short:

1. Copy the project into the webroot.
2. Edit `config.php` (`APP_URL`, SMTP, optional Telegram bot token).
3. Make `db/` and `uploads/` writable by the web server.
4. Schedule cron: `* * * * * php /path/to/todo-app/cron.php`
5. Open the URL, register the first account, then set `ALLOW_REGISTRATION` to `false`.

## Project structure

```
todo-app/
├── index.php        # Main UI
├── api.php          # JSON API
├── app.php          # Shared kernel (database, mail, session, CSRF, Telegram)
├── auth.php         # Login and registration
├── cron.php         # Notifications and Telegram polling
├── telegram.php     # Optional Telegram webhook
├── download.php     # Authenticated file downloads
├── config.php       # App configuration
├── lang/            # English and German strings
├── db/              # SQLite database (created on first request; not web-accessible)
├── uploads/         # Attachments (created per user and task)
├── css/app.css      # Styles
└── js/app.js        # Alpine.js application
```

## Authorship

This repository was written with AI coding assistants. The original application was implemented with **Claude** (Anthropic) through [Claude Code](https://claude.com/product/claude-code). Later features and maintenance were implemented with **Grok** (xAI).

## License

BSD 3-Clause. See [LICENSE](LICENSE). Copyright (c) 2026 Jan-Hendrik Menke.
