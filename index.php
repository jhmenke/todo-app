<?php
require_once __DIR__ . '/app.php';
$user = require_auth();
?><!DOCTYPE html>
<html lang="<?= h(current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?></title>
    <script>
        window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
        window.LOCALE = <?= json_encode(current_locale()) ?>;
        window.I18N = <?= json_encode(i18n_dict(), JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: { accent: { DEFAULT: '#5b4dff', soft: '#eeebff', dark: '#4a3cf0' } }
                }
            }
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="css/app.css?v=5">
</head>
<body class="app-shell h-screen flex flex-col overflow-hidden font-sans" x-data="todoApp()">

<!-- ── Top bar ─────────────────────────────────────────────── -->
<header class="app-header">
    <div class="flex items-center gap-2 min-w-0">
        <button type="button" class="icon-btn md:hidden" @click="sidebarOpen=true" :aria-label="t('nav.open_menu')">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.6" d="M5 12.5l5 5L20 7"/></svg>
            </span>
            <span class="brand-name"><?= h(APP_NAME) ?></span>
        </div>
    </div>
    <div class="flex items-center gap-1.5">
        <div class="user-chip" title="<?= h($user['email']) ?>">
            <span class="avatar"><?= h(strtoupper(substr($user['email'], 0, 1))) ?></span>
            <span><?= h($user['email']) ?></span>
        </div>
        <button type="button" class="icon-btn" @click="showSettings=true" :title="t('nav.settings')" :aria-label="t('nav.settings')">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button type="button" class="icon-btn" @click="logout()" :title="t('nav.sign_out')" :aria-label="t('nav.sign_out')">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M18 12H9m9 0l-3-3m3 3l-3 3"/></svg>
        </button>
        <button type="button" @click="openCreate()" class="btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            <span class="hidden sm:inline" x-text="t('nav.new_todo')"></span>
            <span class="sm:hidden" x-text="t('nav.new')"></span>
        </button>
    </div>
</header>

<!-- ── Body (sidebar + content) ──────────────────────────────────── -->
<div class="flex flex-1 overflow-hidden">

    <div class="sidebar-overlay" x-show="sidebarOpen" x-cloak @click="sidebarOpen=false" style="display:none"></div>

    <!-- ── Sidebar ─────────────────────────────────────────── -->
    <aside class="sidebar" :class="sidebarOpen ? 'open' : ''">
        <div class="p-3 pb-8">
            <p class="sidebar-section" x-text="t('nav.status')"></p>
            <div class="sidebar-item" :class="filterStatus==='today'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('today')">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span x-text="t('nav.today')"></span>
            </div>
            <div class="sidebar-item" :class="filterStatus==='all'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('all')">
                <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span x-text="t('nav.all')"></span>
            </div>
            <div class="sidebar-item" :class="filterStatus==='pending'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('pending')">
                <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                <span x-text="t('nav.pending')"></span>
            </div>
            <div class="sidebar-item" :class="filterStatus==='active'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('active')">
                <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                <span x-text="t('nav.active')"></span>
            </div>
            <div class="sidebar-item" :class="filterStatus==='completed'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('completed')">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="t('nav.completed')"></span>
            </div>

            <p class="sidebar-section" x-text="t('nav.tags')"></p>
            <template x-for="tag in tags" :key="tag.id">
                <div class="sidebar-item group" :class="filterTagId===tag.id?'active':''" @click="setTag(tag.id)">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${tag.color}`"></span>
                    <span class="flex-1 truncate" x-text="tag.name"></span>
                    <button @click.stop="deleteTag(tag.id)" class="opacity-0 group-hover:opacity-100 text-stone-400 hover:text-red-500 transition-opacity">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
            <div x-show="tags.length===0" class="px-4 py-1 text-xs text-stone-400" x-text="t('nav.no_tags')"></div>
        </div>
    </aside>

    <!-- ── Main content ─────────────────────────────────────── -->
    <main class="flex-1 overflow-y-auto">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 py-7">

            <div class="mb-5 flex items-end justify-between gap-3 flex-wrap">
                <div>
                    <p class="page-kicker" x-text="t('nav.your_list')"></p>
                    <h1 class="page-title mt-1" x-text="filterTitle()"></h1>
                </div>
                <div class="search-wrap">
                    <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                    <input type="search" x-model="searchQuery" @input="onSearchInput()" @keydown.escape="clearSearch()"
                        :placeholder="t('list.search')" class="search-input" :aria-label="t('list.search')">
                    <button type="button" class="search-clear" x-show="searchQuery" @click="clearSearch()" :aria-label="t('list.clear_search')">×</button>
                </div>
            </div>

            <!-- Sort bar -->
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <div class="sort-bar">
                    <button class="sort-btn" :class="sortBy==='priority'?'active':''" @click="setSort('priority')">
                        <span x-text="t('sort.priority')"></span><span x-text="sortLabel('priority')"></span>
                    </button>
                    <button class="sort-btn" :class="sortBy==='active_at'?'active':''" @click="setSort('active_at')">
                        <span x-text="t('sort.due')"></span><span x-text="sortLabel('active_at')"></span>
                    </button>
                    <button class="sort-btn" :class="sortBy==='created_at'?'active':''" @click="setSort('created_at')">
                        <span x-text="t('sort.created')"></span><span x-text="sortLabel('created_at')"></span>
                    </button>
                    <button class="sort-btn" :class="sortBy==='title'?'active':''" @click="setSort('title')">
                        <span x-text="t('sort.title')"></span><span x-text="sortLabel('title')"></span>
                    </button>
                    <button x-show="filterStatus==='completed'" class="sort-btn" :class="sortBy==='completed_at'?'active':''" @click="setSort('completed_at')">
                        <span x-text="t('sort.completed_on')"></span><span x-text="sortLabel('completed_at')"></span>
                    </button>
                </div>
            </div>

            <!-- All tab: hide-completed toggle -->
            <div x-show="filterStatus==='all'" class="flex items-center gap-2 mb-4">
                <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-stone-600">
                    <input type="checkbox" x-model="hideCompleted" @change="loadTodos()"
                        class="w-4 h-4 rounded border-stone-300 accent-[#5b4dff]">
                    <span x-text="t('list.hide_completed')"></span>
                </label>
            </div>

            <!-- Completed tab: date range filter -->
            <div x-show="filterStatus==='completed'" class="flex items-center gap-2 mb-4 flex-wrap">
                <span class="text-xs text-stone-400 font-medium" x-text="t('nav.completed')"></span>
                <input type="date" x-model="completedFrom" @change="loadTodos()"
                    class="px-2 py-1 text-xs">
                <span class="text-xs text-stone-400">—</span>
                <input type="date" x-model="completedTo" @change="loadTodos()"
                    class="px-2 py-1 text-xs">
                <button x-show="completedFrom || completedTo"
                    @click="completedFrom=''; completedTo=''; loadTodos()"
                    class="btn-ghost text-xs" x-text="t('list.clear')"></button>
            </div>

            <!-- Loading -->
            <div x-show="loading" class="text-center py-16 text-stone-400 text-sm" x-text="t('list.loading')"></div>

            <!-- Empty state -->
            <div x-show="!loading && todos.length===0" class="empty-state">
                <div class="empty-glyph">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <p class="text-stone-700 font-semibold" x-text="searchQuery ? t('list.no_matches') : t('list.empty_title')"></p>
                <p class="text-stone-400 text-sm mt-1" x-text="searchQuery ? t('list.no_matches_body') : t('list.empty_body')"></p>
                <button @click="openCreate()" class="btn-primary mt-5" x-show="!searchQuery" x-text="t('list.create')"></button>
            </div>

            <!-- Todo list -->
            <div class="space-y-2.5" x-show="!loading">
                <template x-for="todo in todos" :key="todo.id">
                    <div class="todo-stack">
                    <div class="todo-card group"
                         :class="{ 'todo-completed': todo.completed_at, leaving: leavingId === todo.id }"
                         @click="openDrawer(todo)">

                        <input type="checkbox" class="todo-check mt-0.5"
                            :checked="!!todo.completed_at"
                            :disabled="!todo.is_owner"
                            :title="todo.is_owner ? '' : t('error.not_owner')"
                            @click.stop="toggleComplete(todo, $event)">

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="prio-flag" x-show="todo.priority < 4" :class="'p'+todo.priority" :title="'P'+todo.priority"></span>
                                <span class="todo-title" x-text="todo.title"></span>
                                <span x-show="todo.recur_type" class="recur-badge" title="Recurring">↻</span>
                                <span x-show="!todo.is_owner" class="share-pill" x-text="t('list.shared')"></span>
                            </div>
                            <p x-show="todo.parent_title" class="text-xs text-stone-400 mt-0.5">
                                <span x-text="t('list.under')"></span> <span x-text="todo.parent_title"></span>
                            </p>
                            <div class="flex flex-wrap gap-1 mt-1.5" x-show="todo.tags.length">
                                <template x-for="tag in todo.tags" :key="tag.id">
                                    <span class="tag-pill" :style="`background:${tag.color}`" x-text="tag.name"></span>
                                </template>
                            </div>
                            <div class="flex items-center gap-3 mt-1.5 text-xs text-stone-400 flex-wrap">
                                <span x-show="todo.active_at"
                                      :class="!todo.completed_at && todo.active_at && new Date(todo.active_at.replace(' ','T')) <= new Date() ? 'text-amber-600 font-semibold' : ''"
                                      x-text="formatDate(todo.active_at)"></span>
                                <span x-show="!todo.active_at && !todo.completed_at" class="text-stone-300" x-text="t('list.no_due')"></span>
                                <span x-show="todo.recur_type" class="text-emerald-600 font-medium" x-text="recurLabel(todo)"></span>
                                <span x-show="todo.children && todo.children.length" class="text-stone-400"
                                    x-text="todo.children.filter(c => c.completed_at).length + '/' + todo.children.length + ' ' + t('list.sub')"></span>
                                <span x-show="todo.comment_count > 0" class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    <span x-text="todo.comment_count"></span>
                                </span>
                                <span x-show="todo.completed_at" class="text-emerald-600 font-medium">
                                    <span x-text="t('list.done')"></span> <span x-text="formatDateShort(todo.completed_at)"></span>
                                </span>
                            </div>
                        </div>

                        <div x-show="todo.is_owner" class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 flex-shrink-0" @click.stop>
                            <button @click.stop="openEdit(todo)" class="icon-btn" :title="t('list.edit')">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button @click.stop="deleteTodo(todo.id)" class="icon-btn hover:!text-red-500" :title="t('list.delete')">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>

                    <template x-for="child in (todo.children || [])" :key="child.id">
                        <div class="todo-card todo-child group"
                             :class="{ 'todo-completed': child.completed_at, leaving: leavingId === child.id }"
                             @click="openDrawer(child)">
                            <input type="checkbox" class="todo-check mt-0.5"
                                :checked="!!child.completed_at"
                                :disabled="!child.is_owner"
                                @click.stop="toggleComplete(child, $event)">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="prio-flag" x-show="child.priority < 4" :class="'p'+child.priority"></span>
                                    <span class="todo-title" x-text="child.title"></span>
                                </div>
                                <div class="flex items-center gap-3 mt-1 text-xs text-stone-400" x-show="child.active_at">
                                    <span :class="!child.completed_at && child.active_at && new Date(child.active_at.replace(' ','T')) <= new Date() ? 'text-amber-600 font-semibold' : ''"
                                          x-text="formatDate(child.active_at)"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                    </div>
                </template>
            </div>
        </div>
    </main>
</div>

<!-- ── Drawer overlay ─────────────────────────────────────────── -->
<div x-show="drawer" x-transition:enter="opacity-0" x-transition:enter-end="opacity-100"
     class="fixed inset-0 bg-stone-900/40 z-30 backdrop-blur-sm transition-opacity duration-200"
     @click="closeDrawer()" style="display:none"></div>

<!-- ── Drawer panel ────────────────────────────────────────────── -->
<div x-show="drawer"
     x-transition:enter="translate-x-full" x-transition:enter-end="translate-x-0"
     class="drawer-panel fixed top-0 right-0 h-full w-full sm:w-[480px] z-40 flex flex-col transition-transform duration-250"
     style="display:none" @keydown.escape.window="closeDrawer()">

    <template x-if="drawer">
        <div class="flex flex-col h-full">
            <!-- Drawer header -->
            <div class="flex items-start gap-3 px-5 py-5 border-b" style="border-color:var(--line)">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="font-semibold text-base leading-snug tracking-tight" x-text="drawer.title"></h2>
                        <span class="prio-flag" x-show="drawer.priority < 4" :class="'p'+drawer.priority"></span>
                        <span x-show="drawer.recur_type" class="recur-badge" x-text="recurLabel(drawer)"></span>
                        <span x-show="!drawer.is_owner" class="share-pill">
                            <span x-text="t('task.shared_by')"></span> <span x-text="drawer.owner_email"></span>
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-2" x-show="drawer.tags.length">
                        <template x-for="tag in drawer.tags" :key="tag.id">
                            <span class="tag-pill" :style="`background:${tag.color}`" x-text="tag.name"></span>
                        </template>
                    </div>
                    <p class="text-xs text-stone-400 mt-2" x-show="drawer.active_at && !drawer.completed_at">
                        <span x-text="t('task.activates')"></span> <span x-text="formatDateShort(drawer.active_at)" class="font-medium text-stone-600"></span>
                    </p>
                    <p class="text-xs text-emerald-600 mt-2" x-show="drawer.completed_at">
                        <span x-text="t('task.completed')"></span> <span x-text="formatDateShort(drawer.completed_at)" class="font-medium"></span>
                    </p>
                    <p class="text-xs text-stone-400 mt-2" x-show="drawer.parent_title">
                        <span x-text="t('task.subtask_of')"></span> <span class="font-medium text-stone-600" x-text="drawer.parent_title"></span>
                    </p>
                </div>
                <div class="flex items-center gap-0.5 flex-shrink-0">
                    <button x-show="drawer.is_owner" @click="openEdit(drawer)" class="icon-btn" :title="t('list.edit')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button @click="closeDrawer()" class="icon-btn" :title="t('task.close')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="px-5 py-3" style="border-bottom:1px solid var(--line)" x-show="!drawer.parent_id && drawer.is_owner">
                <p class="text-xs font-semibold uppercase tracking-wider text-stone-400 mb-2" x-text="t('task.subtasks')"></p>
                <div class="space-y-1.5 mb-2">
                    <template x-for="child in (drawer.children || [])" :key="child.id">
                        <div class="flex items-center gap-2 py-1">
                            <input type="checkbox" class="todo-check"
                                :checked="!!child.completed_at"
                                @click.stop="toggleComplete(child, $event)">
                            <button type="button" class="text-sm text-left flex-1 min-w-0 truncate"
                                :class="child.completed_at ? 'line-through text-stone-400' : ''"
                                x-text="child.title"
                                @click="openDrawer(child)"></button>
                        </div>
                    </template>
                    <div x-show="!(drawer.children && drawer.children.length)" class="text-xs text-stone-400" x-text="t('task.none_yet')"></div>
                </div>
                <div class="flex gap-2">
                    <input type="text" x-model="newSubtask" :placeholder="t('task.add_subtask')"
                        class="flex-1 text-sm"
                        @keydown.enter.prevent="addSubtask()">
                    <button type="button" class="btn-primary" :disabled="!newSubtask.trim()" @click="addSubtask()" x-text="t('task.add')"></button>
                </div>
            </div>

            <!-- Drawer tabs -->
            <div class="flex px-5" style="border-bottom:1px solid var(--line)">
                <button @click="drawerTab='comments'" class="drawer-tab"
                    :class="drawerTab==='comments' ? 'active' : ''">
                    <span x-text="t('tabs.comments')"></span> <span x-show="comments.length" class="count-chip" x-text="comments.length"></span>
                </button>
                <button @click="drawerTab='files'" class="drawer-tab"
                    :class="drawerTab==='files' ? 'active' : ''">
                    <span x-text="t('tabs.files')"></span> <span x-show="files.length" class="count-chip" x-text="files.length"></span>
                </button>
                <button x-show="drawer.is_owner" @click="drawerTab='shares'" class="drawer-tab"
                    :class="drawerTab==='shares' ? 'active' : ''">
                    <span x-text="t('tabs.sharing')"></span> <span x-show="shares.length" class="count-chip" x-text="shares.length"></span>
                </button>
            </div>

            <!-- Comments tab -->
            <div x-show="drawerTab==='comments'" class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4" x-show="!drawerLoading">
                    <div x-show="comments.length===0" class="text-center py-10 text-sm text-stone-400" x-text="t('comments.empty')"></div>
                    <template x-for="c in comments" :key="c.id">
                        <div class="flex gap-3">
                            <div class="avatar avatar-lg mt-0.5"
                                x-text="c.email.charAt(0).toUpperCase()"></div>
                            <div class="flex-1">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xs font-semibold" x-text="c.email"></span>
                                    <span class="text-xs text-stone-400" x-text="formatDateShort(c.created_at)"></span>
                                </div>
                                <p class="text-sm text-stone-700 mt-0.5 whitespace-pre-wrap" x-text="c.body"></p>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="drawerLoading" class="flex-1 flex items-center justify-center text-sm text-stone-400" x-text="t('list.loading')"></div>

                <!-- Comment input -->
                <div class="px-5 py-3" style="border-top:1px solid var(--line)">
                    <div class="flex gap-2">
                        <textarea x-model="newComment" rows="2" :placeholder="t('comments.placeholder')"
                            class="flex-1 text-sm resize-none"
                            @keydown.ctrl.enter="addComment()" @keydown.meta.enter="addComment()"></textarea>
                        <button @click="addComment()" :disabled="!newComment.trim()"
                            class="btn-primary self-end">
                            <span x-text="t('comments.post')"></span>
                        </button>
                    </div>
                    <p class="text-xs text-stone-400 mt-1" x-text="t('comments.hint')"></p>
                </div>
            </div>

            <!-- Files tab -->
            <div x-show="drawerTab==='files'" class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2">
                    <div x-show="files.length===0 && !drawerLoading" class="text-center py-10 text-sm text-stone-400" x-text="t('files.empty')"></div>
                    <template x-for="f in files" :key="f.id">
                        <div class="file-row group">
                            <div class="file-type" x-text="fileTypeLabel(f.mime_type)"></div>
                            <div class="flex-1 min-w-0">
                                <a :href="fileUrl(f)" class="text-sm font-semibold text-accent hover:underline truncate block" x-text="f.filename"></a>
                                <p class="text-xs text-stone-400" x-text="formatBytes(f.size_bytes) + ' · ' + (f.uploader_email || t('files.you'))"></p>
                            </div>
                            <button @click="deleteFile(f.id)"
                                class="opacity-0 group-hover:opacity-100 icon-btn hover:!text-red-500 flex-shrink-0">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <div class="px-5 py-3" style="border-top:1px solid var(--line)">
                    <p x-show="uploadError" class="text-xs text-red-500 mb-2" x-text="uploadError"></p>
                    <label class="inline-flex items-center gap-3 cursor-pointer">
                        <span class="btn-primary" :class="uploading ? 'opacity-50 pointer-events-none' : ''">
                            <span x-text="uploading ? t('files.uploading') : t('files.attach')"></span>
                        </span>
                        <input type="file" class="hidden" :disabled="uploading" @change="uploadFile($event)">
                        <span class="text-xs text-stone-400" x-text="t('files.limits')"></span>
                    </label>
                </div>
            </div>

            <!-- Shares tab -->
            <div x-show="drawer.is_owner && drawerTab==='shares'" class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2">
                    <div class="flex items-center justify-between py-2">
                        <div class="flex items-center gap-2">
                            <div class="avatar avatar-owner"
                                x-text="drawer.owner_email?.charAt(0).toUpperCase()"></div>
                            <span class="text-sm" x-text="drawer.owner_email"></span>
                        </div>
                        <span class="text-xs font-semibold text-accent" x-text="t('share.owner')"></span>
                    </div>
                    <template x-for="s in shares" :key="s.user_id">
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-2">
                                <div class="avatar" x-text="s.email.charAt(0).toUpperCase()"></div>
                                <span class="text-sm" x-text="s.email"></span>
                            </div>
                            <button @click="removeShare(s.user_id)" class="text-xs text-red-400 hover:text-red-600 transition-colors" x-text="t('share.remove')"></button>
                        </div>
                    </template>
                    <div x-show="shares.length===0" class="text-sm text-stone-400 py-2" x-text="t('share.empty')"></div>
                </div>
                <div class="px-5 py-3" style="border-top:1px solid var(--line)">
                    <p x-show="shareError" class="text-xs text-red-500 mb-2" x-text="shareError"></p>
                    <div class="flex gap-2">
                        <input type="email" x-model="shareEmail" :placeholder="t('share.placeholder')"
                            class="flex-1 text-sm"
                            @keydown.enter="addShare()">
                        <button @click="addShare()" class="btn-primary" x-text="t('share.button')"></button>
                    </div>
                    <p class="text-xs text-stone-400 mt-1" x-text="t('share.hint')"></p>
                </div>
            </div>
        </div>
    </template>
</div>

<!-- ── Create / Edit Modal ─────────────────────────────────────── -->
<template x-if="modal !== null">
<div class="modal-bg" @keydown.escape.window="closeModal()" @keydown.ctrl.s.window.prevent="saveForm()">
    <div class="modal-panel max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 pt-5 pb-3 flex items-center justify-between" style="border-bottom:1px solid var(--line)">
            <h3 class="font-semibold tracking-tight" x-text="modal==='create' ? t('form.new') : t('form.edit')"></h3>
            <button @click="closeModal()" class="icon-btn">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-5">
            <p x-show="formError" class="text-sm text-red-600 bg-red-50 rounded-xl px-3 py-2" x-text="formError"></p>

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium mb-1" x-text="t('form.title')"></label>
                <div class="relative">
                    <input type="text" x-model="form.title" :placeholder="t('form.title_placeholder')" autofocus
                        class="w-full"
                        @input="parseTagsInTitle()"
                        @keydown.escape="shareDropdown = []; tagDropdown = []"
                        @keydown="shareDropdownNav($event)">

                    <!-- User share dropdown (appears when typing <+) -->
                    <div x-show="shareDropdown.length > 0"
                         class="absolute top-full left-0 right-0 z-20 mt-1 bg-[var(--surface)] border rounded-xl shadow-lg overflow-hidden" style="border-color:var(--line)">
                        <template x-for="(u, i) in shareDropdown" :key="u.id">
                            <button type="button" @click="selectShareUser(u.email)"
                                :class="i === shareDropdownIndex ? 'bg-accent-soft' : 'hover:bg-accent-soft'"
                                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm transition-colors text-left">
                                <span class="avatar" x-text="u.email.charAt(0).toUpperCase()"></span>
                                <span x-text="u.email"></span>
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
                                class="ml-0.5 text-accent hover:text-red-500 transition-colors leading-none">×</button>
                        </span>
                    </template>
                </div>

                <p class="text-xs text-stone-400 mt-1.5">
                    <code>&lt;morgen 9 Uhr&gt;</code>
                    <code>&lt;friday 18:00&gt;</code>
                    <code>&lt;+email&gt;</code> <span x-text="t('form.hint_share')"></span>
                    · <code>#Tag</code>
                    · <code>p1</code>
                </p>
            </div>

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
                <label class="block text-sm font-medium mb-1"><span x-text="t('form.activate')"></span> <span class="text-stone-400 font-normal" x-text="t('form.optional')"></span></label>
                <div class="flex gap-2">
                    <input type="date" x-model="form.active_date"
                        class="flex-1 min-w-0">
                    <input type="time" x-model="form.active_time"
                        class="flex-1 min-w-0">
                </div>
                <p class="text-xs text-stone-400 mt-1.5">
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
                            class="tag-pill cursor-pointer ring-2 ring-offset-1 transition-all"
                            :style="`background:${tag.color}`"
                            :class="form.tag_ids.includes(tag.id) ? 'ring-accent' : 'ring-transparent opacity-60'"
                            x-text="tag.name"></button>
                    </template>
                    <button type="button" @click="showTagForm=!showTagForm"
                        class="text-xs text-accent border border-accent/20 rounded-full px-3 py-1 hover:bg-accent-soft transition-colors font-semibold">
                        <span x-text="t('form.new_tag')"></span>
                    </button>
                </div>
                <!-- Inline tag creation -->
                <div x-show="showTagForm" class="mt-2 p-3 rounded-xl space-y-2" style="background:rgba(28,25,23,0.04)">
                    <input type="text" x-model="newTagName" :placeholder="t('form.tag_name')"
                        class="w-full text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex gap-1 flex-wrap">
                            <template x-for="c in colors()" :key="c">
                                <div class="color-swatch" :style="`background:${c}`"
                                    :class="newTagColor===c?'selected':''"
                                    @click="newTagColor=c"></div>
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
                    <p class="text-xs text-stone-500 mb-2" x-text="t('form.repeat_on')"></p>
                    <div class="flex gap-1.5 flex-wrap">
                        <template x-for="d in days()" :key="d">
                            <button type="button" @click="toggleFormDay(d)"
                                class="w-9 h-9 rounded-full text-xs font-semibold transition-colors"
                                :class="form.recur_days.includes(d) ? 'bg-accent text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200'"
                                x-text="dayLabel(d)"></button>
                        </template>
                    </div>
                </div>

                <!-- Custom interval -->
                <div x-show="form.recur_type==='custom'" class="mt-3 flex items-center gap-2">
                    <span class="text-sm text-stone-600" x-text="t('form.every')"></span>
                    <input type="number" x-model="form.recur_interval" min="1" max="365" class="w-20 text-center">
                    <span class="text-sm text-stone-600" x-text="t('form.days')"></span>
                </div>

                <!-- Ends at -->
                <div x-show="form.recur_type" class="mt-3">
                    <label class="block text-xs text-stone-500 mb-1"><span x-text="t('form.ends_at')"></span> <span class="text-stone-400" x-text="t('form.optional')"></span></label>
                    <input type="datetime-local" x-model="form.recur_ends_at" class="w-full">
                </div>
            </div>
        </div>

        <div class="px-6 pb-5 flex justify-end gap-2">
            <button @click="closeModal()" class="btn-ghost" x-text="t('form.cancel')"></button>
            <button @click="saveForm()" :disabled="savingForm"
                class="btn-primary"
                x-text="savingForm ? t('form.saving') : t('form.save')"></button>
        </div>
    </div>
</div>
</template>

<!-- ── Settings Modal ──────────────────────────────────────────── -->
<div x-show="showSettings" class="modal-bg" @click.self="showSettings=false" style="display:none">
    <div class="modal-panel max-w-md mx-4">
        <div class="px-6 pt-5 pb-3 flex items-center justify-between" style="border-bottom:1px solid var(--line)">
            <h3 class="font-semibold tracking-tight" x-text="t('settings.title')"></h3>
            <button @click="showSettings=false" class="icon-btn">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-6">
            <div>
                <label class="block text-sm font-medium mb-2" x-text="t('settings.language')"></label>
                <div class="flex gap-2">
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" x-model="settingsLocale" value="en" class="accent-[#5b4dff]">
                        <span x-text="t('settings.english')"></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                        <input type="radio" x-model="settingsLocale" value="de" class="accent-[#5b4dff]">
                        <span x-text="t('settings.german')"></span>
                    </label>
                </div>
            </div>

            <!-- Notify channel -->
            <div>
                <label class="block text-sm font-medium mb-2" x-text="t('settings.channel')"></label>
                <div class="flex flex-col gap-1.5 text-sm">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="telegram" class="accent-[#5b4dff]">
                        <span x-text="t('settings.telegram')"></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="email" class="accent-[#5b4dff]">
                        <span x-text="t('settings.email')"></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="both" class="accent-[#5b4dff]">
                        <span x-text="t('settings.both')"></span>
                    </label>
                </div>
            </div>

            <!-- Telegram chat ID -->
            <div x-show="settingsChannel === 'telegram' || settingsChannel === 'both'">
                <label class="block text-sm font-medium mb-1" x-text="t('settings.chat_id')"></label>
                <input type="text" x-model="settingsTelegram" placeholder="e.g. 123456789"
                    class="w-full">
                <p class="text-xs text-stone-400 mt-1.5 leading-relaxed" x-text="t('settings.chat_help')"></p>
            </div>

            <!-- Notify lead time -->
            <div>
                <label class="block text-sm font-medium mb-1" x-text="t('settings.lead_time')"></label>
                <div class="flex items-center gap-2">
                    <input type="number" x-model="settingsMinutes" min="1" max="1440" class="w-24 text-center">
                    <span class="text-sm text-stone-600" x-text="t('settings.minutes_before')"></span>
                </div>
                <p x-show="settingsError" class="text-xs text-red-500 mt-1" x-text="settingsError"></p>
                <button @click="saveSettings()" class="btn-primary mt-3" x-text="t('form.save')"></button>
            </div>

            <hr style="border-color:var(--line)">

            <!-- Change password -->
            <div>
                <label class="block text-sm font-medium mb-3" x-text="t('settings.change_password')"></label>
                <div class="space-y-2">
                    <input type="password" x-model="pwCurrent" :placeholder="t('settings.current_password')" class="w-full">
                    <input type="password" x-model="pwNew"     :placeholder="t('settings.new_password')" class="w-full">
                    <input type="password" x-model="pwNew2"    :placeholder="t('settings.confirm_new')" class="w-full">
                </div>
                <p x-show="pwError" class="text-xs text-red-500 mt-2" x-text="pwError"></p>
                <p x-show="pwOk" class="text-xs text-emerald-600 mt-2" x-text="t('settings.password_ok')"></p>
                <button @click="changePassword()" class="btn-primary mt-3" style="background:var(--ink);box-shadow:none" x-text="t('settings.update_password')"></button>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast ────────────────────────────────────────────────────── -->
<div x-show="toastMsg" x-cloak x-transition.duration.200ms class="toast" :class="undoId ? 'has-undo' : ''">
    <div class="toast-row">
        <span x-text="toastMsg"></span>
        <button type="button" class="toast-undo" x-show="undoId" @click="undoComplete()" x-text="t('toast.undo')"></button>
    </div>
    <template x-if="undoId"><div class="toast-bar"></div></template>
</div>

<script src="js/app.js?v=9"></script>
</body>
</html>
