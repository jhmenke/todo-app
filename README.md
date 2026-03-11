# Todo App

A lightweight, self-hosted todo/task management web app built with PHP, SQLite, and Alpine.js. No frameworks, no build step — just drop it on a PHP server and go.

## Features

- **Task management** — create, edit, complete, and delete todos
- **Tags** — color-coded labels to organize tasks
- **Recurring tasks** — daily, weekly, monthly, and custom recurrence rules
- **Task sharing** — share individual todos with other registered users
- **Comments** — leave notes on tasks
- **File attachments** — upload and download files per task
- **Email notifications** — get notified before a task activates, via SMTP or PHP `mail()`
- **User accounts** — registration, login, password change
- **Inline date parsing** — type `<friday 18:00>` in a title to set the activation time naturally
- **Inline sharing** — type `<+email>` in a title to share a task while creating it

## Tech Stack

- **Backend:** PHP 8.1+, SQLite (via PDO)
- **Frontend:** Alpine.js, Tailwind CSS (CDN)
- **Email:** Custom SMTP client (supports STARTTLS / implicit SSL) or PHP `mail()`
- **Storage:** Single SQLite file — no database server required

## Setup

See [DEPLOY.md](DEPLOY.md) for full deployment instructions. Quick start:

1. Copy the project to your webroot
2. Copy `config.php` and fill in your `APP_URL` and SMTP credentials
3. Make `db/` and `uploads/` writable by the web server
4. Set up a cron job: `* * * * * php /path/to/todo-app/cron.php`
5. Visit the URL and register your first account

## Project Structure

```
todo-app/
├── index.php        # Main UI
├── api.php          # JSON API (all data operations)
├── auth.php         # Login / registration
├── cron.php         # Notification cron job
├── download.php     # Auth-gated file downloads
├── config.php       # App configuration (copy & edit this)
├── db/              # SQLite database (auto-created)
├── uploads/         # File attachments (auto-created per user/task)
├── css/app.css      # Custom styles
└── js/app.js        # Alpine.js app logic
```

## A note on authorship

This project was written almost entirely through conversations with **Claude** (Anthropic's AI assistant), using [Claude Code](https://claude.com/product/claude-code). The architecture, code, and features were developed iteratively via natural language — specifying what was needed and having Claude implement it. It's an experiment in AI-assisted solo development, and a testament to how far that workflow has come.

## License

See [LICENSE](LICENSE).
