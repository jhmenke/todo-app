# Changelog

## 2026-11-22

### Added

- Schedule new or existing tasks with Today, Tomorrow, or a custom date. Saving a new task keeps the current view and offers an Open task action.
- Search titles across all accessible tasks, including shared and completed tasks. Open results appear first, and clearing search restores the previous view and tag.
- Choose a green or blue accent in Settings.

### Changed

- Redesigned the task workspace with date-grouped lists and a side panel for task details on desktop. Task details open in a full-width view on smaller screens.
- Overview shows overdue and today's tasks in separate sections. Upcoming keeps the schedule panel tall even with few tasks, and completed tasks are available through a compact history link.

### Fixed

- Failed loads show a retry action. Temporary refresh failures keep the last loaded list or open task visible.
- Failed deletions preserve tasks and attachments and show an error.
- Rescheduling a task gives it a fresh reminder while preserving earlier sends in the daily count.

### Security

- Documented private storage for the database, sessions, attachments, and cron logs. Server access rules block direct requests to sensitive app files.
- Closed public registration by default after the first account, throttled login attempts, and revoked existing sessions and remembered logins after password changes or resets.
- Required verified addresses for new task shares by email. Existing shares and sharing by display name continue to work.
- Restricted HTTP cron runs and Telegram webhooks to header-based authentication. Review the update steps for existing integrations in [DEPLOY.md](DEPLOY.md).

## 2026-10-09

### Added

- Edit a task's title, activation date/time, and tag assignments directly in its detail view by clicking the title.
- Complete or reopen an owned task with the same circular checkbox used in the task list. This also works for tasks opened from Telegram/email notification links and retains completion Undo.
- Save from the create/edit title field with Enter. Autocomplete suggestions take priority over submission, and text composition does not trigger saving.
- Telegram `?` command for today's and tomorrow's task overviews together. Day commands accept optional and full-width question marks and normalized whitespace.
- JavaScript regression checks for list loading, title submission, and task detail completion.

### Changed

- New tasks preselect the active sidebar tag.
- Keep Save at the bottom of the create/edit dialog, beside Cancel.
- Close create/edit dialogs by clicking the surrounding backdrop, as well as with Escape.
- Allow the Settings dialog to scroll on smaller screens.

### Fixed

- Overlapping background and foreground list requests could leave the loading flag set and hide the task list after a detail view closed. The latest request now clears that flag, including failed refreshes.
- Browsers could retain an old script without the task detail completion handler. JavaScript URLs now use a hash of the script contents to invalidate cached versions after updates.
- Cancel the completion checkbox's native click toggle before handling the request.
- Prevent duplicate task submissions and duplicate completion requests from the detail view while a request is pending.
