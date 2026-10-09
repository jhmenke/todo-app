# Changelog

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
