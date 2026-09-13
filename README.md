# Todo App

A self-hosted task manager written in PHP 8.1+, SQLite, Alpine.js, and Tailwind CSS (loaded from a CDN). Copy the files onto a PHP host, edit `config.php`, and schedule the minute cron job.

## Features

- Create, edit, complete, and delete tasks, including subtasks and priorities
- Color-coded tags for grouping work
- Recurring tasks: daily, weekly, monthly, or a custom interval. Completing a recurring task schedules the next future occurrence.
- Share a task with other registered users. Each person can set a short name in Settings (Peter, Lisa); mention them as `+Peter` in Telegram or in the title field. `<+email>` still works.
- Comments and file attachments on a task
- Email and Telegram notifications before a task’s activation time (SMTP, PHP `mail()`, or a Telegram bot). Due messages include a link to open and complete the task.
- One Telegram bot from `config.php`. Users tap **Link Telegram** in Settings (no chat ID to copy). **Enable instant replies** in Settings turns on a Telegram webhook (HTTPS; no SSH). Keep the minute cron for due notifications.
- Create tasks by messaging the bot. Dates can sit at the start or end (`16 Uhr bügeln`, `tomorrow 9am`) or in quotes (`"morgen 9 Uhr"`); `#tag`, `p1`, `+Peter`, and `<+email>` still work. Timed creates attach a `.ics` calendar file on the confirmation.
- Ask the bot `today?` / `heute?` or `tomorrow?` / `morgen?` for a due-list (overdue is included in today).
- English and German UI. Dates use `YYYY.MM.DD`. German uses 24-hour time; English uses 12-hour AM/PM.
- User accounts: registration (can be disabled after the first user), login with a 90-day remember-me cookie, password change in Settings, and **Forgot password?** on the login page (email plus Telegram if the account is linked)
- Opt-in datetime tags in titles, for example `"friday 18:00"`, `<morgen 9 Uhr>`, or (Telegram) a date at the start or end of the message

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
