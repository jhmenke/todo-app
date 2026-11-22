<?php
require_once __DIR__ . '/app.php';
$user = require_auth();

function render_schedule_choices(string $dateRef): void {
    ?>
    <div class="schedule-choices" role="group" :aria-label="t('form.activate')">
        <button type="button" class="detail-chip" :aria-pressed="form.active_date === scheduleDate(0)"
            @click="chooseScheduleDate(0)" x-text="t('nav.today')"></button>
        <button type="button" class="detail-chip" :aria-pressed="form.active_date === scheduleDate(1)"
            @click="chooseScheduleDate(1)" x-text="t('nav.tomorrow')"></button>
        <button type="button" class="detail-chip" :aria-expanded="showDateFields"
            @click="showDateFields = !showDateFields; if (showDateFields) $nextTick(() => $refs.<?= h($dateRef) ?>.focus())">
            <svg class="ui-icon" aria-hidden="true"><use href="#icon-calendar"/></svg>
            <span x-text="scheduleChoiceLabel()"></span>
        </button>
    </div>
    <div class="schedule-fields" x-show="showDateFields">
        <div class="inline-schedule">
            <label class="inline-field">
                <span x-text="t('form.date')"></span>
                <input type="date" x-model="form.active_date" x-ref="<?= h($dateRef) ?>">
            </label>
            <label class="inline-field">
                <span x-text="t('form.time')"></span>
                <input type="time" x-model="form.active_time">
            </label>
        </div>
        <button type="button" class="btn-ghost" x-show="form.active_date || form.active_time"
            @click="clearSchedule(); $refs.<?= h($dateRef) ?>.focus()" x-text="t('form.clear_schedule')"></button>
    </div>
    <?php
}

?><!DOCTYPE html>
<html lang="<?= h(current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?></title>
    <script>
        window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
        window.LOCALE = <?= json_encode(current_locale()) ?>;
        window.I18N = <?= json_encode(i18n_dict(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&amp;family=Source+Sans+3:wght@400..700&amp;display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (window.tailwind) {
            tailwind.config = {
                theme: { extend: { colors: { accent: {
                    DEFAULT: 'var(--accent)', soft: 'var(--accent-soft)', dark: 'var(--accent-ink)'
                } } } }
            };
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="css/app.css?v=<?= h(substr(hash_file('sha256', __DIR__ . '/css/app.css'), 0, 12)) ?>">
</head>
<body class="app-shell" x-data="todoApp()" :data-accent="accentTheme"
    @keydown.escape.window="dismissWorkspaceOverlay()">

<svg class="icon-library" xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true">
    <symbol id="icon-brand" viewBox="0 0 24 24"><path d="m3 12 4 4 9-9m-5 8 2 2 8-8"/></symbol>
    <symbol id="icon-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></symbol>
    <symbol id="icon-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="icon-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
    <symbol id="icon-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18m-13 5h2m4 0h2"/></symbol>
    <symbol id="icon-overview" viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></symbol>
    <symbol id="icon-list" viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></symbol>
    <symbol id="icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5h4"/></symbol>
    <symbol id="icon-done" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5 5-5"/></symbol>
    <symbol id="icon-sort" viewBox="0 0 24 24"><path d="M4 6h4m4 0h8M4 12h9m4 0h3M4 18h3m4 0h9M8 4v4m9 2v4m-6 2v4"/></symbol>
    <symbol id="icon-comment" viewBox="0 0 24 24"><path d="M20 11.5a8 8 0 0 1-8 8 8.5 8.5 0 0 1-3.6-.8L4 20l1.2-3.9a8 8 0 1 1 14.8-4.6Z"/></symbol>
    <symbol id="icon-flag" viewBox="0 0 24 24"><path d="M5 21V4m0 1c5-5 9 4 14-1v10c-5 5-9-4-14 1"/></symbol>
    <symbol id="icon-tag" viewBox="0 0 24 24"><path d="M3 4h8l9 9-7 7-9-9V4Z"/><path d="M8 8h.01"/></symbol>
    <symbol id="icon-repeat" viewBox="0 0 24 24"><path d="M3 12V9a4 4 0 0 1 4-4h13m-4-3 4 3-4 3m5 4v3a4 4 0 0 1-4 4H4m4-3-4 3 4 3"/></symbol>
    <symbol id="icon-more" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></symbol>
    <symbol id="icon-back" viewBox="0 0 24 24"><path d="M20 12H4m7-7-7 7 7 7"/></symbol>
</svg>

<div class="planner-window" x-ref="plannerWindow" :class="{ 'detail-open': drawer }" x-cloak>
    <header class="app-header">
        <div class="brand">
            <span class="brand-mark"><svg class="ui-icon" aria-hidden="true"><use href="#icon-brand"/></svg></span>
            <span class="brand-name"><?= h(APP_NAME) ?></span>
        </div>
        <form id="task-search" role="search" class="search-wrap" autocomplete="off" @submit.prevent>
            <svg class="ui-icon search-icon" aria-hidden="true"><use href="#icon-search"/></svg>
            <input type="search" id="task-search-query" name="todo_filter_q" x-model="searchQuery"
                @input="onSearchInput()" @keydown.escape.stop="clearSearch()"
                autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                data-1p-ignore data-lpignore="true"
                :placeholder="t('list.search')" class="search-input" :aria-label="t('list.search')">
            <button type="button" class="search-clear" x-show="searchQuery" @click="clearSearch()"
                :aria-label="t('list.clear_search')"><svg class="ui-icon" aria-hidden="true"><use href="#icon-close"/></svg></button>
        </form>
        <div class="header-actions">
            <button type="button" class="btn-primary header-add" @click="openCreate()"
                :aria-label="t('nav.add_task')" :aria-expanded="quickCreate">
                <svg class="ui-icon" aria-hidden="true"><use href="#icon-plus"/></svg>
                <span class="add-label" x-text="t('nav.add_task')"></span>
                <span class="add-short" x-text="t('nav.new')"></span>
            </button>
            <details class="popover account-menu" @click.outside="$el.open=false"
                @keydown.escape.stop="$el.open=false; $el.querySelector('summary').focus()">
                <summary class="avatar account-trigger" :aria-label="t('nav.account')"><?= h(strtoupper(substr($user['email'], 0, 1))) ?></summary>
                <div class="popover-panel">
                    <p class="account-email"><?= h($user['email']) ?></p>
                    <button type="button" class="menu-option" @click="$el.closest('details').open=false; showSettings=true" x-text="t('nav.settings')"></button>
                    <button type="button" class="menu-option" @click="logout()" x-text="t('nav.sign_out')"></button>
                </div>
            </details>
        </div>
    </header>

    <aside class="sidebar" :aria-label="t('nav.tasks')">
        <nav class="sidebar-nav" :aria-label="t('nav.status')">
            <button type="button" class="sidebar-item" :aria-current="isOverview() ? 'page' : null" @click="setStatus('today')">
                <svg class="ui-icon" aria-hidden="true"><use href="#icon-overview"/></svg>
                <span x-text="t('nav.overview')"></span>
            </button>
            <button type="button" class="sidebar-item" :aria-current="filterStatus==='upcoming' && !searchQuery.trim() ? 'page' : null" @click="setStatus('upcoming')">
                <svg class="ui-icon" aria-hidden="true"><use href="#icon-calendar"/></svg>
                <span x-text="t('nav.upcoming')"></span>
            </button>
            <button type="button" class="sidebar-item" :aria-current="filterStatus==='all' && !filterTagId && !searchQuery.trim() ? 'page' : null" @click="setStatus('all')">
                <svg class="ui-icon" aria-hidden="true"><use href="#icon-list"/></svg>
                <span x-text="t('nav.all_tasks')"></span>
            </button>
        </nav>
        <nav class="sidebar-tags" :aria-label="t('nav.tags')" x-show="tags.length">
            <p class="sidebar-section" x-text="t('nav.tags')"></p>
            <template x-for="tag in tags" :key="tag.id">
                <div class="sidebar-tag">
                    <button type="button" class="sidebar-item" :aria-current="filterTagId===tag.id && !searchQuery.trim() ? 'page' : null" @click="setTag(tag.id)">
                        <span class="tag-dot" :style="{ backgroundColor: tag.color }" aria-hidden="true"></span>
                        <span class="truncate" x-text="tag.name"></span>
                    </button>
                    <button type="button" class="icon-btn tag-remove" @click="deleteTag(tag.id)" :aria-label="t('nav.delete_tag', {tag: tag.name})">
                        <svg class="ui-icon" aria-hidden="true"><use href="#icon-close"/></svg>
                    </button>
                </div>
            </template>
        </nav>
    </aside>

    <main class="workspace">
        <header class="workspace-heading">
            <h1 class="page-title" x-ref="workspaceTitle" tabindex="-1" x-text="filterTitle()"></h1>
            <div class="workspace-tools">
                <p class="workspace-date" x-show="!searchQuery.trim()"
                    x-text="formatWorkspaceDate(workspaceDate(), {weekday:'long', day:'numeric', month:'long'})"></p>
                <button type="button" class="btn-ghost completed-link"
                    :aria-pressed="filterStatus==='completed' && !searchQuery.trim()" @click="setStatus('completed')">
                    <svg class="ui-icon" aria-hidden="true"><use href="#icon-done"/></svg>
                    <span x-text="t('nav.completed')"></span>
                    <span class="completed-count" x-show="summary" x-text="summary?.completed"></span>
                </button>
            </div>
        </header>

        <template x-if="modal !== null">
        <div class="task-editor" :class="quickCreate ? 'quick-editor' : 'modal-bg'"
            :role="quickCreate ? null : 'dialog'" :aria-modal="quickCreate ? null : 'true'"
            :aria-label="modal==='create' ? t('form.new') : t('form.edit')"
            @click.self="if (!quickCreate) closeModal()" @keydown.ctrl.s.window.prevent="saveForm()">
            <div class="task-editor-panel" :class="{ 'modal-panel task-modal-panel': !quickCreate }">
                <div class="modal-heading" x-show="!quickCreate">
                    <h3 class="font-semibold tracking-tight" x-text="modal==='create' ? t('form.new') : t('form.edit')"></h3>
                    <button type="button" @click="closeModal()" class="icon-btn" :aria-label="t('task.close')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="task-editor-content">
                    <p role="alert" x-show="formError" class="danger-text text-sm" x-text="formError"></p>

                    <!-- Title -->
                    <div>
                        <label for="task-form-title" class="block text-sm font-medium mb-1" :class="{ 'sr-only': quickCreate }" x-text="t('form.title')"></label>
                        <div class="relative">
                            <input type="text" id="task-form-title" x-model="form.title" :placeholder="t('form.title_placeholder')"
                                x-init="$nextTick(() => $el.focus())"
                                class="w-full"
                                @input="parseTagsInTitle()"
                                @keydown.escape="clearShareDropdown(); tagDropdown = []"
                                @keydown="onTitleKeydown($event)">

                            <!-- User share dropdown (appears when typing <+) -->
                            <div x-show="shareDropdown.length > 0"
                                 class="absolute top-full left-0 right-0 z-20 mt-1 bg-[var(--surface)] border rounded-xl shadow-lg overflow-hidden" style="border-color:var(--line)">
                                <template x-for="(u, i) in shareDropdown" :key="u.id">
                                    <button type="button" @click="selectShareUser(userLabel(u))"
                                        :class="i === shareDropdownIndex ? 'bg-accent-soft' : 'hover:bg-accent-soft'"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-sm transition-colors text-left">
                                        <span class="avatar" x-text="userLabel(u).charAt(0).toUpperCase()"></span>
                                        <span x-text="userLabel(u)"></span>
                                    </button>
                                </template>
                            </div>

                            <!-- Tag dropdown (appears when typing #) -->
                            <div x-show="tagDropdown.length > 0"
                                 class="absolute top-full left-0 right-0 z-20 mt-1 bg-[var(--surface)] border rounded-xl shadow-lg overflow-hidden" style="border-color:var(--line)">
                                <template x-for="(tag, i) in tagDropdown" :key="tag.id">
                                    <button type="button" @click="selectTag(tag.id)"
                                        :class="i === tagDropdownIndex ? 'bg-accent-soft' : 'hover:bg-accent-soft'"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-sm transition-colors text-left">
                                        <span class="w-3 h-3 rounded-full flex-shrink-0" :style="`background:${tag.color}`"></span>
                                        <span x-text="tag.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Pending share chips -->
                        <div x-show="form.share_emails.length > 0" class="flex flex-wrap gap-1.5 mt-2">
                            <template x-for="email in form.share_emails" :key="email">
                                <span class="inline-flex items-center gap-1 bg-accent-soft text-accent-dark text-xs px-2 py-1 rounded-full font-medium">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <span x-text="email"></span>
                                    <button type="button" @click="form.share_emails = form.share_emails.filter(e => e !== email)"
                                        class="ml-0.5 text-accent hover:danger-text transition-colors leading-none">×</button>
                                </span>
                            </template>
                        </div>

                        <p class="text-xs muted-text mt-1.5" x-show="!quickCreate">
                            <code>&lt;morgen 9 Uhr&gt;</code>
                            <code>&lt;friday 18:00&gt;</code>
                            <code>+Peter</code> <span x-text="t('form.hint_share')"></span>
                            · <code>#Tag</code>
                            · <code>p1</code>
                        </p>

                    </div>

                    <div class="schedule-editor" x-show="quickCreate">
                        <?php render_schedule_choices('quickDate'); ?>
                    </div>

                    <div x-show="!quickCreate" class="space-y-5">
                        <!-- Priority -->
                        <div>
                            <label class="block text-sm font-medium mb-2" x-text="t('form.priority')"></label>
                            <div class="flex gap-1.5">
                                <template x-for="p in [1,2,3,4]" :key="p">
                                    <button type="button" class="prio-btn" :class="form.priority===p ? 'is-on p'+p : 'p'+p"
                                        @click="form.priority=p"
                                        x-text="p===4 ? t('form.none') : 'P'+p"></button>
                                </template>
                            </div>
                        </div>

                        <!-- Activate at -->
                        <div>
                            <label class="block text-sm font-medium mb-1"><span x-text="t('form.activate')"></span> <span class="muted-text font-normal" x-text="t('form.optional')"></span></label>
                            <div class="flex gap-2">
                                <input type="date" x-model="form.active_date"
                                    class="flex-1 min-w-0">
                                <input type="time" x-model="form.active_time"
                                    class="flex-1 min-w-0">
                            </div>
                            <p class="text-xs muted-text mt-1.5">
                                <span x-show="!form.active_date && form.active_time" x-text="t('form.time_only')"></span>
                                <span x-show="form.active_date && !form.active_time" x-text="t('form.date_only')"></span>
                                <span x-show="!form.active_date && !form.active_time" x-text="t('form.leave_blank')"></span>
                            </p>
                        </div>

                        <!-- Tags -->
                        <div>
                            <label class="block text-sm font-medium mb-2" x-text="t('form.tags')"></label>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <template x-for="tag in tags" :key="tag.id">
                                    <button type="button" @click="toggleFormTag(tag.id)"
                                        class="detail-chip tag-choice" :aria-pressed="form.tag_ids.includes(tag.id)">
                                        <span class="tag-dot" :style="{ backgroundColor: tag.color }"></span>
                                        <span x-text="tag.name"></span>
                                    </button>
                                </template>
                                <button type="button" class="btn-ghost" @click="showTagForm=!showTagForm">
                                    <span x-text="t('form.new_tag')"></span>
                                </button>
                            </div>
                            <!-- Inline tag creation -->
                            <div x-show="showTagForm" class="mt-2 p-3 rounded-xl space-y-2" style="background:var(--field)">
                                <input type="text" x-model="newTagName" :placeholder="t('form.tag_name')"
                                    class="w-full text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex gap-1 flex-wrap">
                                        <template x-for="c in colors()" :key="c">
                                            <button type="button" class="color-swatch" :style="{ backgroundColor: c }"
                                                :aria-label="c" :aria-pressed="newTagColor===c" @click="newTagColor=c"></button>
                                        </template>
                                    </div>
                                    <button @click="createInlineTag()" class="btn-primary flex-shrink-0" x-text="t('task.add')"></button>
                                </div>
                            </div>
                        </div>

                        <!-- Recurrence -->
                        <div x-show="!form.parent_id">
                            <label class="block text-sm font-medium mb-1" x-text="t('form.recurrence')"></label>
                            <select x-model="form.recur_type" class="w-full">
                                <option value="" x-text="t('form.none')"></option>
                                <option value="daily" x-text="t('form.daily')"></option>
                                <option value="weekly" x-text="t('form.weekly')"></option>
                                <option value="monthly" x-text="t('form.monthly')"></option>
                                <option value="custom" x-text="t('form.custom')"></option>
                            </select>

                            <!-- Weekly day picker -->
                            <div x-show="form.recur_type==='weekly'" class="mt-3">
                                <p class="text-xs muted-text mb-2" x-text="t('form.repeat_on')"></p>
                                <div class="flex gap-1.5 flex-wrap">
                                    <template x-for="d in days()" :key="d">
                                        <button type="button" @click="toggleFormDay(d)"
                                            class="detail-chip tag-choice day-choice"
                                            :aria-pressed="form.recur_days.includes(d)"
                                            x-text="dayLabel(d)"></button>
                                    </template>
                                </div>
                            </div>

                            <!-- Custom interval -->
                            <div x-show="form.recur_type==='custom'" class="mt-3 flex items-center gap-2">
                                <span class="text-sm muted-text" x-text="t('form.every')"></span>
                                <input type="number" x-model="form.recur_interval" min="1" max="365" class="w-20 text-center">
                                <span class="text-sm muted-text" x-text="t('form.days')"></span>
                            </div>

                            <!-- Ends at -->
                            <div x-show="form.recur_type" class="mt-3">
                                <label class="block text-xs muted-text mb-1"><span x-text="t('form.ends_at')"></span> <span class="muted-text" x-text="t('form.optional')"></span></label>
                                <input type="datetime-local" x-model="form.recur_ends_at" class="w-full">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="task-editor-actions">
                    <button type="button" class="btn-ghost" x-show="quickCreate" @click="quickCreate=false"
                        x-text="t('form.more_options')"></button>
                    <button type="button" @click="closeModal()" class="btn-ghost" x-text="t('form.cancel')"></button>
                    <button type="button" @click="saveForm()" :disabled="savingForm"
                        class="btn-primary"
                        x-text="savingForm ? t('form.saving') : (quickCreate ? t('task.add') : t('form.save'))"></button>
                </div>
            </div>
        </div>
        </template>

        <div class="planner-work" :class="{ 'planner-work-tall': filterStatus === 'upcoming' && !searchQuery.trim() }">
            <section class="planner-list" aria-labelledby="schedule-heading" :aria-busy="loading">
                <header class="list-header">
                    <h2 id="schedule-heading" :class="{ 'sr-only': searchQuery.trim() }"
                        x-text="filterTagId || searchQuery.trim() || ['all','completed','pending','active'].includes(filterStatus) ? t('nav.tasks') : t('nav.schedule')"></h2>
                    <details class="popover sort-menu" @click.outside="$el.open=false"
                        @keydown.escape.stop="$el.open=false; $el.querySelector('summary').focus()">
                        <summary class="btn-ghost">
                            <svg class="ui-icon" aria-hidden="true"><use href="#icon-sort"/></svg>
                            <span x-text="t('sort.label')"></span>
                        </summary>
                        <div class="popover-panel">
                            <template x-for="option in [
                                {field:'active_at', label:'sort.due'},
                                {field:'priority', label:'sort.priority'},
                                {field:'created_at', label:'sort.created'},
                                {field:'title', label:'sort.title'},
                                ...(filterStatus==='completed' || searchQuery.trim() ? [{field:'completed_at', label:'sort.completed_on'}] : [])
                            ]" :key="option.field">
                                <button type="button" class="menu-option" :aria-pressed="sortBy===option.field"
                                    @click="setSort(option.field)"
                                    x-text="t(option.label) + sortLabel(option.field)"></button>
                            </template>
                            <div class="popover-divider" role="group" :aria-label="t('nav.status')" x-show="!searchQuery.trim()">
                                <p class="menu-caption" x-text="t('nav.status')"></p>
                                <button type="button" class="menu-option" :aria-pressed="filterStatus==='pending'"
                                    @click="setStatus('pending'); $el.closest('details').open=false" x-text="t('nav.pending')"></button>
                                <button type="button" class="menu-option" :aria-pressed="filterStatus==='active'"
                                    @click="setStatus('active'); $el.closest('details').open=false" x-text="t('nav.active')"></button>
                            </div>
                            <label x-show="filterStatus==='all' && !searchQuery.trim()" class="option-check">
                                <input type="checkbox" x-model="hideCompleted" @change="loadTodos()">
                                <span x-text="t('list.hide_completed')"></span>
                            </label>
                            <div x-show="filterStatus==='completed' && !searchQuery.trim()" class="date-filters">
                                <label><span x-text="t('list.completed_from')"></span><input type="date" name="todo_completed_from" x-model="completedFrom" @change="loadTodos()" autocomplete="off"></label>
                                <label><span x-text="t('list.completed_to')"></span><input type="date" name="todo_completed_to" x-model="completedTo" @change="loadTodos()" autocomplete="off"></label>
                                <button type="button" x-show="completedFrom || completedTo"
                                    @click="completedFrom=''; completedTo=''; loadTodos()"
                                    class="btn-ghost" x-text="t('list.clear')"></button>
                            </div>
                        </div>
                    </details>
                </header>

                <p x-show="loading" class="list-message" role="status" x-text="t('list.loading')"></p>
                <div x-show="listError" class="load-notice">
                    <p role="status" x-text="t(showsLoadedList() && todos.length ? 'list.refresh_failed' : 'list.load_failed')"></p>
                    <button type="button" class="btn-ghost" @click="loadTodos()" x-text="t('list.retry')"></button>
                </div>

                <div x-show="showsLoadedList() && !listError && todos.length===0 && !isOverview()" class="list-message">
                    <p class="font-semibold" x-text="searchQuery.trim() ? t('list.no_matches') : t('list.empty_title')"></p>
                    <p x-text="searchQuery.trim() ? t('list.no_matches_body') : t('list.empty_body')"></p>
                </div>

                <div x-show="showsLoadedList() && (!listError || todos.length)">
                    <template x-for="group in taskGroups()" :key="group.key">
                        <section class="date-group">
                            <header class="date-heading" :class="{ 'late': group.key==='overdue' }">
                                <h3 x-text="group.label"></h3>
                                <span x-show="group.date" x-text="group.date"></span>
                            </header>
                            <p class="group-empty" x-show="!group.tasks.length && !listError"
                                x-text="t(group.key==='overdue' ? 'list.empty_overdue' : 'list.empty_today')"></p>
                            <template x-for="entry in group.tasks" :key="entry.task.id">
                                <div class="task-row" :class="{
                                    'is-open': drawer?.id===entry.task.id,
                                    'todo-completed': entry.task.completed_at,
                                    'is-leaving': leavingId===entry.task.id,
                                    'task-child': entry.nested
                                }">
                                    <label class="check-target">
                                        <input type="checkbox" class="todo-check"
                                            :checked="!!entry.task.completed_at" :disabled="!entry.task.is_owner"
                                            :aria-label="t(entry.task.completed_at ? 'task.mark_incomplete' : 'task.mark_complete') + ' — ' + entry.task.title"
                                            @click="toggleComplete(entry.task, $event)">
                                    </label>
                                    <button type="button" class="task-target" :data-task-id="entry.task.id"
                                        :aria-expanded="drawer?.id===entry.task.id"
                                        @click="openDrawer(entry.task)">
                                        <span class="todo-title" x-text="entry.task.title"></span>
                                        <span class="task-meta">
                                            <span x-show="entry.task.active_at && !entry.task.completed_at" :class="{ 'late': group.key==='overdue' }">
                                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-clock"/></svg>
                                                <span x-text="taskSchedule(entry.task, group.key)"></span>
                                            </span>
                                            <span x-show="entry.task.completed_at">
                                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-done"/></svg>
                                                <span x-text="formatDateShort(entry.task.completed_at)"></span>
                                            </span>
                                            <template x-for="tag in entry.task.tags || []" :key="tag.id">
                                                <span><i class="tag-dot" :style="{ backgroundColor: tag.color }" aria-hidden="true"></i><span x-text="tag.name"></span></span>
                                            </template>
                                            <span x-show="entry.task.priority < 4" class="task-priority" :class="'p' + entry.task.priority">
                                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-flag"/></svg>
                                                <span class="sr-only" x-text="t('form.priority') + ' ' + entry.task.priority"></span>
                                            </span>
                                            <span x-show="entry.task.recur_type">
                                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-repeat"/></svg>
                                                <span x-text="recurLabel(entry.task)"></span>
                                            </span>
                                            <span x-show="!entry.task.is_owner" x-text="t('list.shared')"></span>
                                            <span x-show="entry.parentTitle && !entry.nested" x-text="t('list.under') + ' ' + entry.parentTitle"></span>
                                            <span x-show="entry.task.comment_count > 0">
                                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-comment"/></svg>
                                                <span x-text="entry.task.comment_count"></span>
                                                <span class="sr-only" x-text="t('tabs.comments')"></span>
                                            </span>
                                        </span>
                                    </button>
                                </div>
                            </template>
                        </section>
                    </template>
                </div>
            </section>
            <template x-if="drawer">
                <section class="drawer-panel" :aria-label="t('task.detail')">
                    <div class="task-detail">
                        <header class="detail-chrome">
                            <span class="detail-context" x-text="filterTitle()"></span>
                            <button type="button" class="btn-ghost detail-back" data-close-task @click="closeDrawer()">
                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-back"/></svg>
                                <span x-text="filterTitle()"></span>
                            </button>
                            <div class="detail-actions">
                                <details class="popover" @click.outside="$el.open=false"
                                    @keydown.escape.stop="$el.open=false; $el.querySelector('summary').focus()">
                                    <summary class="icon-btn" :aria-label="t('task.actions')">
                                        <svg class="ui-icon" aria-hidden="true"><use href="#icon-more"/></svg>
                                    </summary>
                                    <div class="popover-panel">
                                        <button type="button" class="menu-option" x-show="drawer.is_owner"
                                            @click="$el.closest('details').open=false; openEdit(drawer)" x-text="t('list.edit')"></button>
                                        <button type="button" class="menu-option"
                                            @click="$el.closest('details').open=false; drawerTab='files'" x-text="t('tabs.files')"></button>
                                        <button type="button" class="menu-option" x-show="drawer.is_owner"
                                            @click="$el.closest('details').open=false; drawerTab='shares'" x-text="t('tabs.sharing')"></button>
                                        <button type="button" class="menu-option danger-text" x-show="drawer.is_owner" :disabled="deletingTodo"
                                            @click="$el.closest('details').open=false; deleteTodo(drawer.id)" x-text="t('list.delete')"></button>
                                    </div>
                                </details>
                                <button type="button" class="icon-btn detail-close" data-close-task
                                    @click="closeDrawer()" :aria-label="t('task.close')">
                                    <svg class="ui-icon" aria-hidden="true"><use href="#icon-close"/></svg>
                                </button>
                            </div>
                        </header>

                        <div class="load-notice" x-show="drawerRefreshError">
                            <p role="status" x-text="t('task.refresh_failed')"></p>
                            <button type="button" class="btn-ghost" @click="refreshOpenDrawer()" x-text="t('list.retry')"></button>
                        </div>

                        <div class="detail-heading" :class="{ 'todo-completed': drawer.completed_at }">
                            <label class="check-target">
                                <input type="checkbox" class="todo-check"
                                    :checked="!!drawer.completed_at" :disabled="!drawer.is_owner || completingDrawer"
                                    :aria-label="t(drawer.completed_at ? 'task.mark_incomplete' : 'task.mark_complete')"
                                    @click.prevent="toggleDrawerComplete($event)">
                            </label>
                            <h2 class="todo-title">
                                <button type="button" x-show="drawer.is_owner" class="detail-title-button" x-ref="inlineEditTrigger"
                                    @click="beginInlineEdit('title')" x-text="drawer.title"></button>
                                <span x-show="!drawer.is_owner" x-text="drawer.title"></span>
                            </h2>
                        </div>
                        <div class="detail-metadata">
                            <button type="button" class="detail-chip" x-show="drawer.active_at || drawer.is_owner" :disabled="!drawer.is_owner"
                                @click="beginInlineEdit('schedule')">
                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-calendar"/></svg>
                                <span x-text="drawer.active_at ? formatDateShort(drawer.active_at) : t('form.choose_date')"></span>
                            </button>
                            <button type="button" class="detail-chip" x-show="drawer.tags?.length" :disabled="!drawer.is_owner"
                                @click="beginInlineEdit('tags')">
                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-tag"/></svg>
                                <span x-text="(drawer.tags || []).map(tag => tag.name).join(', ')"></span>
                            </button>
                            <button type="button" class="detail-chip" x-show="drawer.priority < 4" :disabled="!drawer.is_owner"
                                @click="beginInlineEdit('priority')">
                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-flag"/></svg>
                                <span x-text="t('form.priority') + ' ' + drawer.priority"></span>
                            </button>
                            <span class="detail-chip" x-show="drawer.recur_type">
                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-repeat"/></svg>
                                <span x-text="recurLabel(drawer)"></span>
                            </span>
                            <span class="detail-chip" x-show="drawer.completed_at" x-text="t('task.completed') + ' ' + formatDateShort(drawer.completed_at)"></span>
                        </div>
                        <p class="detail-note" x-show="drawer.parent_title" x-text="t('task.subtask_of') + ' ' + drawer.parent_title"></p>
                        <p class="detail-note" x-show="!drawer.is_owner" x-text="t('task.shared_by') + ' ' + (drawer.owner_display_name || drawer.owner_email)"></p>

                        <form class="inline-form" x-show="editing" x-ref="inlineEdit" @submit.prevent="saveInlineEdit()">
                            <p role="alert" class="danger-text text-sm" x-show="formError" x-text="formError"></p>
                            <label class="inline-field" data-edit-field="title" x-show="editing==='title'">
                                <span x-text="t('form.title')"></span>
                                <input type="text" x-model="form.title">
                            </label>
                            <div class="schedule-editor" data-edit-field="schedule" x-show="editing==='schedule'">
                                <?php render_schedule_choices('inlineDate'); ?>
                            </div>
                            <div data-edit-field="tags" x-show="editing==='tags'">
                                <p class="detail-note" x-text="t('form.tags')"></p>
                                <div class="detail-metadata">
                                    <template x-for="tag in tags" :key="tag.id">
                                        <button type="button" class="detail-chip tag-choice" :aria-pressed="form.tag_ids.includes(tag.id)"
                                            @click="toggleFormTag(tag.id)">
                                            <span class="tag-dot" :style="{ backgroundColor: tag.color }"></span><span x-text="tag.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <label class="inline-field" data-edit-field="priority" x-show="editing==='priority'">
                                <span x-text="t('form.priority')"></span>
                                <select x-model.number="form.priority">
                                    <option value="4" x-text="t('form.none')"></option>
                                    <option value="1">P1</option><option value="2">P2</option><option value="3">P3</option>
                                </select>
                            </label>
                            <div class="inline-actions">
                                <button type="button" class="btn-ghost" @click="cancelInlineEdit(); $nextTick(() => $refs.inlineEditTrigger?.focus())" x-text="t('form.cancel')"></button>
                                <button type="submit" class="btn-primary" :disabled="savingForm" x-text="savingForm ? t('form.saving') : t('form.save')"></button>
                            </div>
                        </form>

                        <section class="detail-block" x-show="drawerTab==='comments' && !drawer.parent_id && drawer.is_owner">
                            <div class="block-heading">
                                <h3 x-show="drawer.children?.length || subtaskOpen" x-text="t('task.subtasks')"></h3>
                                <button type="button" class="btn-ghost" x-show="!subtaskOpen" x-ref="subtaskTrigger"
                                    @click="subtaskOpen=true">
                                    <svg class="ui-icon" aria-hidden="true"><use href="#icon-plus"/></svg>
                                    <span x-text="t('task.add_step')"></span>
                                </button>
                            </div>
                            <template x-for="child in (drawer.children || [])" :key="child.id">
                                <div class="step-row" :class="{ 'todo-completed': child.completed_at }">
                                    <label class="check-target">
                                        <input type="checkbox" class="todo-check" :checked="!!child.completed_at" :disabled="!child.is_owner"
                                            :aria-label="t(child.completed_at ? 'task.mark_incomplete' : 'task.mark_complete') + ' — ' + child.title"
                                            @click="toggleComplete(child, $event)">
                                    </label>
                                    <button type="button" class="todo-title" @click="openDrawer(child)" x-text="child.title"></button>
                                </div>
                            </template>
                            <template x-if="subtaskOpen">
                                <form class="inline-form" @submit.prevent="addSubtask()">
                                    <input type="text" class="w-full" x-model="newSubtask" x-init="$nextTick(() => $el.focus())"
                                        :aria-label="t('task.add_step')" :placeholder="t('task.add_subtask')">
                                    <div class="inline-actions">
                                        <button type="button" class="btn-ghost"
                                            @click="subtaskOpen=false; newSubtask=''; $nextTick(() => $refs.subtaskTrigger.focus())" x-text="t('form.cancel')"></button>
                                        <button type="submit" class="btn-primary" :disabled="!newSubtask.trim()" x-text="t('task.add')"></button>
                                    </div>
                                </form>
                            </template>
                        </section>

                        <section class="detail-block" x-show="drawerTab==='comments'">
                            <h3 x-text="t('tabs.comments')"></h3>
                            <p x-show="drawerLoading" class="detail-note" role="status" x-text="t('list.loading')"></p>
                            <template x-for="comment in comments" :key="comment.id">
                                <article class="comment-row">
                                    <span class="avatar" aria-hidden="true" x-text="comment.email.charAt(0).toUpperCase()"></span>
                                    <div class="min-w-0">
                                        <div class="comment-header">
                                            <strong x-text="comment.email"></strong>
                                            <span x-text="formatDateShort(comment.created_at)"></span>
                                        </div>
                                        <p class="comment-body" x-text="comment.body"></p>
                                    </div>
                                </article>
                            </template>
                            <button type="button" class="btn-ghost" x-show="!commentOpen && !drawerLoading" x-ref="commentTrigger"
                                @click="commentOpen=true">
                                <svg class="ui-icon" aria-hidden="true"><use href="#icon-comment"/></svg>
                                <span x-text="t('comments.add')"></span>
                            </button>
                            <template x-if="commentOpen">
                                <form class="inline-form comment-composer" @submit.prevent="addComment()">
                                    <p role="alert" class="danger-text text-sm" x-show="commentError" x-text="commentError"></p>
                                    <label class="inline-field">
                                        <span x-text="t('comments.label')"></span>
                                        <textarea x-model="newComment" x-init="$nextTick(() => $el.focus())" rows="3" :readonly="commentPosting"
                                            @keydown.ctrl.enter.prevent="addComment()" @keydown.meta.enter.prevent="addComment()"></textarea>
                                    </label>
                                    <div class="inline-actions">
                                        <button type="button" class="btn-ghost" :disabled="commentPosting" @click="closeComment()" x-text="t('form.cancel')"></button>
                                        <button type="submit" class="btn-primary" :disabled="!newComment.trim() || commentPosting" x-text="t('comments.post')"></button>
                                    </div>
                                </form>
                            </template>
                        </section>

                        <section class="detail-block" x-show="drawerTab==='files'">
                            <div class="block-heading">
                                <h3 x-text="t('tabs.files')"></h3>
                                <button type="button" class="btn-ghost" @click="drawerTab='comments'" x-text="t('tabs.comments')"></button>
                            </div>
                            <p x-show="drawerLoading" class="detail-note" role="status" x-text="t('list.loading')"></p>
                            <p class="detail-note" x-show="!drawerLoading && !files.length" x-text="t('files.empty')"></p>
                            <template x-for="file in files" :key="file.id">
                                <div class="file-row">
                                    <span class="file-type" x-text="fileTypeLabel(file.mime_type)"></span>
                                    <div class="flex-1 min-w-0">
                                        <a class="file-link" :href="fileUrl(file)" x-text="file.filename"></a>
                                        <p class="detail-note" x-text="formatBytes(file.size_bytes) + ' · ' + (file.uploader_email || t('files.you'))"></p>
                                    </div>
                                    <button type="button" class="icon-btn danger-text"
                                        x-show="drawer.is_owner || Number(file.uploaded_by) === <?= (int)$user['id'] ?>"
                                        @click="deleteFile(file.id)" :aria-label="t('list.delete') + ' ' + file.filename">
                                        <svg class="ui-icon" aria-hidden="true"><use href="#icon-close"/></svg>
                                    </button>
                                </div>
                            </template>
                            <p class="danger-text text-sm" x-show="uploadError" role="alert" x-text="uploadError"></p>
                            <button type="button" class="btn-primary" :disabled="uploading"
                                @click="$refs.attachmentInput.click()" x-text="uploading ? t('files.uploading') : t('files.attach')"></button>
                            <input type="file" class="hidden" x-ref="attachmentInput" :disabled="uploading" @change="uploadFile($event)">
                            <p class="detail-note" x-text="t('files.limits')"></p>
                        </section>

                        <section class="detail-block" x-show="drawer.is_owner && drawerTab==='shares'">
                            <div class="block-heading">
                                <h3 x-text="t('tabs.sharing')"></h3>
                                <button type="button" class="btn-ghost" @click="drawerTab='comments'" x-text="t('tabs.comments')"></button>
                            </div>
                            <p x-show="drawerLoading" class="detail-note" role="status" x-text="t('list.loading')"></p>
                            <div class="share-row">
                                <strong x-text="drawer.owner_display_name || drawer.owner_email"></strong>
                                <span class="detail-note" x-text="t('share.owner')"></span>
                            </div>
                            <template x-for="share in shares" :key="share.user_id">
                                <div class="share-row">
                                    <span x-text="share.display_name || share.email"></span>
                                    <button type="button" class="btn-ghost danger-text" @click="removeShare(share.user_id)" x-text="t('share.remove')"></button>
                                </div>
                            </template>
                            <form class="inline-form" @submit.prevent="addShare()">
                                <p class="danger-text text-sm" x-show="shareError" role="alert" x-text="shareError"></p>
                                <label class="inline-field">
                                    <span x-text="t('share.hint')"></span>
                                    <input type="text" x-model="shareEmail" :placeholder="t('share.placeholder')" autocomplete="off">
                                </label>
                                <div class="inline-actions">
                                    <button type="submit" class="btn-primary" :disabled="!shareEmail.trim()" x-text="t('share.button')"></button>
                                </div>
                            </form>
                        </section>
                    </div>
                </section>
            </template>
        </div>
    </main>
</div>

<!-- ── Settings Modal ──────────────────────────────────────────── -->
<div x-show="showSettings" x-cloak class="modal-bg" @click.self="showSettings=false" role="dialog" aria-modal="true" :aria-label="t('settings.title')">
    <div class="modal-panel max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 pt-5 pb-3 flex items-center justify-between" style="border-bottom:1px solid var(--line)">
            <h3 class="font-semibold tracking-tight" x-text="t('settings.title')"></h3>
            <button type="button" @click="showSettings=false" class="icon-btn" :aria-label="t('task.close')">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-6">
            <div>
                <label class="block text-sm font-medium mb-1" x-text="t('settings.display_name')"></label>
                <input type="text" x-model="settingsDisplayName" maxlength="32" autocomplete="off"
                    :placeholder="t('settings.display_name_placeholder')" class="w-full">
                <p class="text-xs muted-text mt-1.5" x-text="t('settings.display_name_help')"></p>
            </div>

            <div>
                <p class="text-sm font-medium"><?= h($user['email']) ?></p>
                <p x-show="emailVerified" class="text-xs success-text mt-1" x-text="t('settings.email_verified')"></p>
                <div x-show="!emailVerified">
                    <p class="text-xs muted-text mt-1" x-text="t('settings.email_verification_help')"></p>
                    <button type="button" class="btn-ghost mt-2" @click="sendEmailVerification()"
                        :disabled="emailVerificationBusy" x-text="t('settings.verify_email')"></button>
                </div>
                <p x-show="emailVerificationMsg" class="text-xs muted-text mt-1" role="status" x-text="emailVerificationMsg"></p>
            </div>

            <div>
                <label class="inline-field">
                    <span x-text="t('settings.appearance')"></span>
                    <select x-model="accentTheme" @change="setAccent($event.target.value)">
                        <option value="evergreen" x-text="t('settings.evergreen')"></option>
                        <option value="blue" x-text="t('settings.ink_blue')"></option>
                    </select>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2" x-text="t('settings.language')"></label>
                <div class="flex gap-2">
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" x-model="settingsLocale" value="en">
                        <span x-text="t('settings.english')"></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" x-model="settingsLocale" value="de">
                        <span x-text="t('settings.german')"></span>
                    </label>
                </div>
            </div>

            <!-- Notify channel -->
            <div>
                <label class="block text-sm font-medium mb-2" x-text="t('settings.channel')"></label>
                <div class="flex flex-col gap-1.5 text-sm">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="telegram">
                        <span x-text="t('settings.telegram')"></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="email">
                        <span x-text="t('settings.email')"></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="both">
                        <span x-text="t('settings.both')"></span>
                    </label>
                </div>
            </div>

            <!-- Telegram link -->
            <div x-show="settingsChannel === 'telegram' || settingsChannel === 'both'">
                <label class="block text-sm font-medium mb-1" x-text="t('settings.telegram')"></label>
                <p x-show="!telegramConfigured" class="text-xs muted-text leading-relaxed" x-text="t('settings.telegram_off')"></p>
                <div x-show="telegramConfigured" class="space-y-2">
                    <div x-show="telegramLinked" class="flex items-center gap-2">
                        <span class="text-sm font-medium success-text" x-text="t('settings.telegram_linked')"></span>
                        <button type="button" @click="unlinkTelegram()" class="btn-ghost" x-text="t('settings.telegram_unlink')"></button>
                    </div>
                    <div x-show="!telegramLinked" class="flex flex-wrap items-center gap-2">
                        <a x-show="telegramLinkUrl" :href="telegramLinkUrl" target="_blank" rel="noopener"
                            class="btn-primary no-underline" @click="onTelegramLinkClick()"
                            x-text="t('settings.telegram_link')"></a>
                        <span x-show="telegramLinkPending" class="text-xs muted-text" x-text="t('settings.telegram_waiting')"></span>
                    </div>
                    <p class="text-xs muted-text leading-relaxed" x-text="t('settings.telegram_help')"></p>
                </div>
                <p x-show="telegramError" class="text-xs danger-text mt-1" x-text="telegramError"></p>
            </div>

            <!-- Notify lead time -->
            <div>
                <label class="block text-sm font-medium mb-1" x-text="t('settings.lead_time')"></label>
                <div class="flex items-center gap-2">
                    <input type="number" x-model="settingsMinutes" min="1" max="1440" class="w-24 text-center">
                    <span class="text-sm muted-text" x-text="t('settings.minutes_before')"></span>
                </div>
                <p x-show="settingsError" class="text-xs danger-text mt-1" x-text="settingsError"></p>
                <button @click="saveSettings()" class="btn-primary mt-3" x-text="t('form.save')"></button>
            </div>

            <hr style="border-color:var(--line)">

            <!-- Change password -->
            <form id="change-password" method="post" @submit.prevent="changePassword()">
                <label class="block text-sm font-medium mb-3" x-text="t('settings.change_password')"></label>
                <div class="space-y-2">
                    <input type="password" name="current_password" autocomplete="current-password" x-model="pwCurrent" :placeholder="t('settings.current_password')" class="w-full">
                    <input type="password" name="new_password" autocomplete="new-password" x-model="pwNew"     :placeholder="t('settings.new_password')" class="w-full">
                    <input type="password" name="confirm_password" autocomplete="new-password" x-model="pwNew2"    :placeholder="t('settings.confirm_new')" class="w-full">
                </div>
                <p x-show="pwError" class="text-xs danger-text mt-2" x-text="pwError"></p>
                <p x-show="pwOk" class="text-xs success-text mt-2" x-text="t('settings.password_ok')"></p>
                <button type="submit" class="btn-primary mt-3" style="background:var(--ink);box-shadow:none" x-text="t('settings.update_password')"></button>
            </form>
        </div>
    </div>
</div>

<!-- ── Toast ────────────────────────────────────────────────────── -->
<div x-show="toastMsg" x-cloak class="toast" :class="undoId ? 'has-undo' : ''">
    <div class="toast-row">
        <span role="status" x-text="toastMsg"></span>
        <button type="button" class="toast-action" x-show="undoId" @click="undoComplete()" x-text="t('toast.undo')"></button>
        <button type="button" class="toast-action" x-show="toastTodo" @click="openToastTodo()" x-text="t('toast.open_task')"></button>
    </div>
    <template x-if="undoId"><div class="toast-bar"></div></template>
</div>

<script src="js/app.js?v=<?= h(substr(hash_file('sha256', __DIR__ . '/js/app.js'), 0, 12)) ?>"></script>
</body>
</html>
