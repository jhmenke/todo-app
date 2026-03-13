<?php
require_once __DIR__ . '/config.php';
$user = require_auth();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="bg-gray-50 text-slate-800 h-screen flex flex-col overflow-hidden" x-data="todoApp()">

<!-- ── Top bar ─────────────────────────────────────────────── -->
<header class="bg-white border-b border-gray-200 px-5 py-3 flex items-center justify-between flex-shrink-0 z-10">
    <span class="font-bold text-indigo-600 text-lg tracking-tight"><?= h(APP_NAME) ?></span>
    <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500 hidden sm:block"><?= h($user['email']) ?></span>
        <button @click="showSettings=true" class="text-sm text-gray-500 hover:text-indigo-600 px-2 py-1 rounded transition-colors">Settings</button>
        <button @click="logout()" class="text-sm text-gray-400 hover:text-gray-700 px-2 py-1 rounded transition-colors">Sign out</button>
        <button @click="openCreate()"
            class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            New todo
        </button>
    </div>
</header>

<!-- ── Body (sidebar + content) ──────────────────────────────────── -->
<div class="flex flex-1 overflow-hidden">

    <!-- ── Sidebar ─────────────────────────────────────────── -->
    <aside class="w-52 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col overflow-y-auto">
        <div class="p-3 space-y-0.5">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-2 mb-1 mt-2">Status</p>
            <div class="sidebar-item" :class="filterStatus==='all'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('all')">
                <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                All
            </div>
            <div class="sidebar-item" :class="filterStatus==='pending'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('pending')">
                <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                Pending
            </div>
            <div class="sidebar-item" :class="filterStatus==='active'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('active')">
                <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                Active now
            </div>
            <div class="sidebar-item" :class="filterStatus==='completed'&&!filterTagId?'active':''" @click="filterTagId=null;setStatus('completed')">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Completed
            </div>

            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-2 mb-1 mt-4">Tags</p>
            <template x-for="tag in tags" :key="tag.id">
                <div class="sidebar-item group" :class="filterTagId===tag.id?'active':''" @click="setTag(tag.id)">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${tag.color}`"></span>
                    <span class="flex-1 truncate" x-text="tag.name"></span>
                    <button @click.stop="deleteTag(tag.id)" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition-opacity">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
            <div x-show="tags.length===0" class="px-2 py-1 text-xs text-gray-400">No tags yet</div>
        </div>
    </aside>

    <!-- ── Main content ─────────────────────────────────────── -->
    <main class="flex-1 overflow-y-auto">
        <div class="max-w-2xl mx-auto px-4 py-6">

            <!-- Sort bar -->
            <div class="flex items-center gap-2 mb-3 flex-wrap">
                <span class="text-xs text-gray-400 font-medium mr-1">Sort by</span>
                <button class="sort-btn" :class="sortBy==='active_at'?'active':''" @click="setSort('active_at')">
                    Due date<span x-text="sortLabel('active_at')"></span>
                </button>
                <button class="sort-btn" :class="sortBy==='created_at'?'active':''" @click="setSort('created_at')">
                    Created<span x-text="sortLabel('created_at')"></span>
                </button>
                <button class="sort-btn" :class="sortBy==='title'?'active':''" @click="setSort('title')">
                    Title<span x-text="sortLabel('title')"></span>
                </button>
                <button x-show="filterStatus==='completed'" class="sort-btn" :class="sortBy==='completed_at'?'active':''" @click="setSort('completed_at')">
                    Completed on<span x-text="sortLabel('completed_at')"></span>
                </button>
            </div>

            <!-- All tab: hide-completed toggle -->
            <div x-show="filterStatus==='all'" class="flex items-center gap-2 mb-4">
                <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-gray-600">
                    <input type="checkbox" x-model="hideCompleted" @change="loadTodos()"
                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 accent-indigo-600">
                    Hide completed
                </label>
            </div>

            <!-- Completed tab: date range filter -->
            <div x-show="filterStatus==='completed'" class="flex items-center gap-2 mb-4 flex-wrap">
                <span class="text-xs text-gray-400 font-medium">Completed</span>
                <input type="date" x-model="completedFrom" @change="loadTodos()"
                    class="px-2 py-1 border border-gray-200 rounded-lg text-xs text-gray-600 bg-white">
                <span class="text-xs text-gray-400">—</span>
                <input type="date" x-model="completedTo" @change="loadTodos()"
                    class="px-2 py-1 border border-gray-200 rounded-lg text-xs text-gray-600 bg-white">
                <button x-show="completedFrom || completedTo"
                    @click="completedFrom=''; completedTo=''; loadTodos()"
                    class="text-xs text-gray-400 hover:text-gray-600 transition-colors">Clear</button>
            </div>

            <!-- Loading -->
            <div x-show="loading" class="text-center py-16 text-gray-400 text-sm">Loading…</div>

            <!-- Empty state -->
            <div x-show="!loading && todos.length===0" class="text-center py-20">
                <svg class="w-12 h-12 mx-auto text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <p class="text-gray-400 text-sm">No todos here.</p>
                <button @click="openCreate()" class="mt-3 text-indigo-600 text-sm hover:underline">Create one</button>
            </div>

            <!-- Todo list -->
            <div class="space-y-2" x-show="!loading">
                <template x-for="todo in todos" :key="todo.id">
                    <div class="todo-card bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-start gap-3 cursor-pointer"
                         :class="todo.completed_at ? 'todo-completed opacity-75' : ''"
                         @click="openDrawer(todo)">

                        <!-- Checkbox -->
                        <input type="checkbox" class="todo-check mt-0.5"
                            :checked="!!todo.completed_at"
                            @click.stop="completeTodo(todo, $event)">

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="todo-title font-medium text-sm" x-text="todo.title"></span>
                                <span x-show="todo.recur_type" class="recur-badge" title="Recurring">↻</span>
                                <span x-show="!todo.is_owner" class="text-xs text-gray-400 italic">shared</span>
                            </div>

                            <!-- Tags -->
                            <div class="flex flex-wrap gap-1 mt-1.5" x-show="todo.tags.length">
                                <template x-for="tag in todo.tags" :key="tag.id">
                                    <span class="tag-pill" :style="`background:${tag.color}`" x-text="tag.name"></span>
                                </template>
                            </div>

                            <!-- Meta -->
                            <div class="flex items-center gap-3 mt-1.5 text-xs text-gray-400 flex-wrap">
                                <span x-show="todo.active_at"
                                      :class="!todo.completed_at && todo.active_at && new Date(todo.active_at.replace(' ','T')) <= new Date() ? 'text-amber-600 font-medium' : ''"
                                      x-text="formatDate(todo.active_at)"></span>
                                <span x-show="!todo.active_at && !todo.completed_at" class="text-gray-300">No due date</span>
                                <span x-show="todo.recur_type" class="text-emerald-600" x-text="recurLabel(todo)"></span>
                                <span x-show="todo.comment_count > 0" class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    <span x-text="todo.comment_count"></span>
                                </span>
                                <span x-show="todo.completed_at" class="text-emerald-600">
                                    Done <span x-text="formatDateShort(todo.completed_at)"></span>
                                </span>
                            </div>
                        </div>

                        <!-- Owner actions -->
                        <div x-show="todo.is_owner" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 flex-shrink-0" @click.stop>
                            <button @click.stop="openEdit(todo)" class="p-1.5 text-gray-400 hover:text-indigo-600 rounded transition-colors"
                                title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button @click.stop="deleteTodo(todo.id)" class="p-1.5 text-gray-400 hover:text-red-500 rounded transition-colors"
                                title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </main>
</div>

<!-- ── Drawer overlay ─────────────────────────────────────────── -->
<div x-show="drawer" x-transition:enter="opacity-0" x-transition:enter-end="opacity-100"
     class="fixed inset-0 bg-black/30 z-30 backdrop-blur-sm transition-opacity duration-200"
     @click="closeDrawer()" style="display:none"></div>

<!-- ── Drawer panel ────────────────────────────────────────────── -->
<div x-show="drawer"
     x-transition:enter="translate-x-full" x-transition:enter-end="translate-x-0"
     class="fixed top-0 right-0 h-full w-full sm:w-[480px] bg-white shadow-2xl z-40 flex flex-col transition-transform duration-250"
     style="display:none" @keydown.escape.window="closeDrawer()">

    <template x-if="drawer">
        <div class="flex flex-col h-full">
            <!-- Drawer header -->
            <div class="flex items-start gap-3 px-5 py-4 border-b border-gray-100">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="font-semibold text-slate-800 text-base leading-snug" x-text="drawer.title"></h2>
                        <span x-show="drawer.recur_type" class="recur-badge" x-text="recurLabel(drawer)"></span>
                        <span x-show="!drawer.is_owner" class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">
                            shared by <span x-text="drawer.owner_email"></span>
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-2" x-show="drawer.tags.length">
                        <template x-for="tag in drawer.tags" :key="tag.id">
                            <span class="tag-pill" :style="`background:${tag.color}`" x-text="tag.name"></span>
                        </template>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5" x-show="drawer.active_at && !drawer.completed_at">
                        Activates: <span x-text="formatDateShort(drawer.active_at)" class="font-medium text-gray-600"></span>
                    </p>
                    <p class="text-xs text-emerald-600 mt-1.5" x-show="drawer.completed_at">
                        Completed: <span x-text="formatDateShort(drawer.completed_at)" class="font-medium"></span>
                    </p>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <button x-show="drawer.is_owner" @click="openEdit(drawer)" class="p-1.5 text-gray-400 hover:text-indigo-600 rounded transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button @click="closeDrawer()" class="p-1.5 text-gray-400 hover:text-gray-700 rounded transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Drawer tabs -->
            <div class="flex border-b border-gray-100 px-5">
                <button @click="drawerTab='comments'" class="py-2.5 px-3 text-sm font-medium border-b-2 transition-colors mr-2"
                    :class="drawerTab==='comments' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    Comments <span x-show="comments.length" class="ml-1 text-xs bg-gray-100 text-gray-500 rounded-full px-1.5" x-text="comments.length"></span>
                </button>
                <button @click="drawerTab='files'" class="py-2.5 px-3 text-sm font-medium border-b-2 transition-colors mr-2"
                    :class="drawerTab==='files' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    Files <span x-show="files.length" class="ml-1 text-xs bg-gray-100 text-gray-500 rounded-full px-1.5" x-text="files.length"></span>
                </button>
                <button x-show="drawer.is_owner" @click="drawerTab='shares'" class="py-2.5 px-3 text-sm font-medium border-b-2 transition-colors"
                    :class="drawerTab==='shares' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    Sharing <span x-show="shares.length" class="ml-1 text-xs bg-gray-100 text-gray-500 rounded-full px-1.5" x-text="shares.length"></span>
                </button>
            </div>

            <!-- Comments tab -->
            <div x-show="drawerTab==='comments'" class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4" x-show="!drawerLoading">
                    <div x-show="comments.length===0" class="text-center py-10 text-sm text-gray-400">No comments yet. Be the first.</div>
                    <template x-for="c in comments" :key="c.id">
                        <div class="flex gap-3">
                            <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center flex-shrink-0 mt-0.5"
                                x-text="c.email.charAt(0).toUpperCase()"></div>
                            <div class="flex-1">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xs font-medium text-gray-700" x-text="c.email"></span>
                                    <span class="text-xs text-gray-400" x-text="formatDateShort(c.created_at)"></span>
                                </div>
                                <p class="text-sm text-gray-700 mt-0.5 whitespace-pre-wrap" x-text="c.body"></p>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="drawerLoading" class="flex-1 flex items-center justify-center text-sm text-gray-400">Loading…</div>

                <!-- Comment input -->
                <div class="px-5 py-3 border-t border-gray-100">
                    <div class="flex gap-2">
                        <textarea x-model="newComment" rows="2" placeholder="Add a comment…"
                            class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none"
                            @keydown.ctrl.enter="addComment()" @keydown.meta.enter="addComment()"></textarea>
                        <button @click="addComment()" :disabled="!newComment.trim()"
                            class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium self-end disabled:opacity-40 hover:bg-indigo-700 transition-colors">
                            Post
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Ctrl+Enter to submit</p>
                </div>
            </div>

            <!-- Files tab -->
            <div x-show="drawerTab==='files'" class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2">
                    <div x-show="files.length===0 && !drawerLoading" class="text-center py-10 text-sm text-gray-400">No files attached yet.</div>
                    <template x-for="f in files" :key="f.id">
                        <div class="flex items-center gap-3 py-2 px-3 bg-gray-50 rounded-lg group">
                            <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center flex-shrink-0"
                                x-text="fileTypeLabel(f.mime_type)"></div>
                            <div class="flex-1 min-w-0">
                                <a :href="fileUrl(f)" class="text-sm font-medium text-indigo-600 hover:underline truncate block" x-text="f.filename"></a>
                                <p class="text-xs text-gray-400" x-text="formatBytes(f.size_bytes) + ' · ' + (f.uploader_email || 'you')"></p>
                            </div>
                            <button @click="deleteFile(f.id)"
                                class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition-opacity p-1 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
                <div class="px-5 py-3 border-t border-gray-100">
                    <p x-show="uploadError" class="text-xs text-red-500 mb-2" x-text="uploadError"></p>
                    <label class="inline-flex items-center gap-3 cursor-pointer">
                        <span class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                            :class="uploading ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 text-white'">
                            <span x-text="uploading ? 'Uploading…' : 'Attach file'"></span>
                        </span>
                        <input type="file" class="hidden" :disabled="uploading" @change="uploadFile($event)">
                        <span class="text-xs text-gray-400">Max 20 MB · Images, PDF, Office, ZIP, TXT</span>
                    </label>
                </div>
            </div>

            <!-- Shares tab -->
            <div x-show="drawer.is_owner && drawerTab==='shares'" class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2">
                    <div class="flex items-center justify-between py-2">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center"
                                x-text="drawer.owner_email?.charAt(0).toUpperCase()"></div>
                            <span class="text-sm text-gray-700" x-text="drawer.owner_email"></span>
                        </div>
                        <span class="text-xs text-indigo-600 font-medium">Owner</span>
                    </div>
                    <template x-for="s in shares" :key="s.user_id">
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 text-xs font-bold flex items-center justify-center"
                                    x-text="s.email.charAt(0).toUpperCase()"></div>
                                <span class="text-sm text-gray-700" x-text="s.email"></span>
                            </div>
                            <button @click="removeShare(s.user_id)" class="text-xs text-red-400 hover:text-red-600 transition-colors">Remove</button>
                        </div>
                    </template>
                    <div x-show="shares.length===0" class="text-sm text-gray-400 py-2">Not shared with anyone.</div>
                </div>
                <div class="px-5 py-3 border-t border-gray-100">
                    <p x-show="shareError" class="text-xs text-red-500 mb-2" x-text="shareError"></p>
                    <div class="flex gap-2">
                        <input type="email" x-model="shareEmail" placeholder="user@email.com"
                            class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2"
                            @keydown.enter="addShare()">
                        <button @click="addShare()" class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            Share
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Must be a registered user's email</p>
                </div>
            </div>
        </div>
    </template>
</div>

<!-- ── Create / Edit Modal ─────────────────────────────────────── -->
<template x-if="modal !== null">
<div class="modal-bg" @keydown.escape.window="closeModal()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 pt-5 pb-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800" x-text="modal==='create' ? 'New todo' : 'Edit todo'"></h3>
            <button @click="closeModal()" class="text-gray-400 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-5">
            <p x-show="formError" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2" x-text="formError"></p>

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <div class="relative">
                    <input type="text" x-model="form.title" placeholder="What needs doing?" autofocus
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
                        @input="parseTagsInTitle()"
                        @keydown.escape="shareDropdown = []"
                        @keydown="shareDropdownNav($event)">

                    <!-- User share dropdown (appears when typing <+) -->
                    <div x-show="shareDropdown.length > 0"
                         class="absolute top-full left-0 right-0 z-20 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                        <template x-for="(u, i) in shareDropdown" :key="u.id">
                            <button type="button" @click="selectShareUser(u.email)"
                                :class="i === shareDropdownIndex ? 'bg-indigo-50' : 'hover:bg-indigo-50'"
                                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm transition-colors text-left">
                                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold flex items-center justify-center flex-shrink-0"
                                    x-text="u.email.charAt(0).toUpperCase()"></span>
                                <span x-text="u.email" class="text-gray-700"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Pending share chips -->
                <div x-show="form.share_emails.length > 0" class="flex flex-wrap gap-1.5 mt-2">
                    <template x-for="email in form.share_emails" :key="email">
                        <span class="inline-flex items-center gap-1 bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs px-2 py-1 rounded-full">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span x-text="email"></span>
                            <button type="button" @click="form.share_emails = form.share_emails.filter(e => e !== email)"
                                class="ml-0.5 text-indigo-400 hover:text-red-500 transition-colors leading-none">×</button>
                        </span>
                    </template>
                </div>

                <p class="text-xs text-gray-400 mt-1.5">
                    <code class="bg-gray-100 px-1 rounded">&lt;morgen 9 Uhr&gt;</code>
                    <code class="bg-gray-100 px-1 rounded">&lt;friday 18:00&gt;</code>
                    <code class="bg-gray-100 px-1 rounded">&lt;+email&gt;</code> to share
                </p>
            </div>

            <!-- Activate at -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Activate at <span class="text-gray-400 font-normal">(optional)</span></label>
                <div class="flex gap-2">
                    <input type="date" x-model="form.active_date"
                        class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm min-w-0">
                    <input type="time" x-model="form.active_time"
                        class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm min-w-0">
                </div>
                <p class="text-xs text-gray-400 mt-1.5">
                    <span x-show="!form.active_date && form.active_time">Time only — will use today or tomorrow automatically.</span>
                    <span x-show="form.active_date && !form.active_time">Date only — will activate at 09:00 on that day.</span>
                    <span x-show="!form.active_date && !form.active_time">Leave blank for no scheduled activation.</span>
                </p>
            </div>

            <!-- Tags -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                <div class="flex flex-wrap gap-2 mb-2">
                    <template x-for="tag in tags" :key="tag.id">
                        <button type="button" @click="toggleFormTag(tag.id)"
                            class="tag-pill cursor-pointer ring-2 ring-offset-1 transition-all"
                            :style="`background:${tag.color}`"
                            :class="form.tag_ids.includes(tag.id) ? 'ring-indigo-500' : 'ring-transparent opacity-60'"
                            x-text="tag.name"></button>
                    </template>
                    <button type="button" @click="showTagForm=!showTagForm"
                        class="text-xs text-indigo-600 border border-indigo-200 rounded-full px-3 py-1 hover:bg-indigo-50 transition-colors">
                        + New tag
                    </button>
                </div>
                <!-- Inline tag creation -->
                <div x-show="showTagForm" class="mt-2 p-3 bg-gray-50 rounded-lg space-y-2">
                    <input type="text" x-model="newTagName" placeholder="Tag name"
                        class="w-full text-sm border border-gray-200 rounded px-2 py-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex gap-1 flex-wrap">
                            <template x-for="c in colors()" :key="c">
                                <div class="color-swatch" :style="`background:${c}`"
                                    :class="newTagColor===c?'selected':''"
                                    @click="newTagColor=c"></div>
                            </template>
                        </div>
                        <button @click="createInlineTag()" class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded font-medium hover:bg-indigo-700 transition-colors flex-shrink-0">Add</button>
                    </div>
                </div>
            </div>

            <!-- Recurrence -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recurrence</label>
                <select x-model="form.recur_type" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white">
                    <option value="">None</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="custom">Custom interval</option>
                </select>

                <!-- Weekly day picker -->
                <div x-show="form.recur_type==='weekly'" class="mt-3">
                    <p class="text-xs text-gray-500 mb-2">Repeat on</p>
                    <div class="flex gap-1.5 flex-wrap">
                        <template x-for="d in days()" :key="d">
                            <button type="button" @click="toggleFormDay(d)"
                                class="w-9 h-9 rounded-full text-xs font-medium transition-colors"
                                :class="form.recur_days.includes(d) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                x-text="dayLabel(d)"></button>
                        </template>
                    </div>
                </div>

                <!-- Custom interval -->
                <div x-show="form.recur_type==='custom'" class="mt-3 flex items-center gap-2">
                    <span class="text-sm text-gray-600">Every</span>
                    <input type="number" x-model="form.recur_interval" min="1" max="365" class="w-20 px-2 py-1.5 border border-gray-200 rounded text-sm text-center">
                    <span class="text-sm text-gray-600">days</span>
                </div>

                <!-- Ends at -->
                <div x-show="form.recur_type" class="mt-3">
                    <label class="block text-xs text-gray-500 mb-1">Ends at <span class="text-gray-400">(optional)</span></label>
                    <input type="datetime-local" x-model="form.recur_ends_at" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
            </div>
        </div>

        <div class="px-6 pb-5 flex justify-end gap-2">
            <button @click="closeModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors">Cancel</button>
            <button @click="saveForm()" :disabled="savingForm"
                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                x-text="savingForm ? 'Saving…' : 'Save'"></button>
        </div>
    </div>
</div>
</template>

<!-- ── Settings Modal ──────────────────────────────────────────── -->
<div x-show="showSettings" class="modal-bg" @click.self="showSettings=false" style="display:none">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
        <div class="px-6 pt-5 pb-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Settings</h3>
            <button @click="showSettings=false" class="text-gray-400 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-6">
            <!-- Notify channel -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Notification channel</label>
                <div class="flex flex-col gap-1.5 text-sm text-gray-700">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="telegram" class="accent-indigo-600"> Telegram
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="email" class="accent-indigo-600"> Email
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" x-model="settingsChannel" value="both" class="accent-indigo-600"> Both
                    </label>
                </div>
            </div>

            <!-- Telegram chat ID -->
            <div x-show="settingsChannel === 'telegram' || settingsChannel === 'both'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Telegram Chat ID</label>
                <input type="text" x-model="settingsTelegram" placeholder="e.g. 123456789"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                <p class="text-xs text-gray-400 mt-1.5 leading-relaxed">
                    To find your Chat ID:
                    1. Open Telegram and search for <strong>@userinfobot</strong>.<br>
                    2. Start the bot and send it any message.<br>
                    3. It will reply with your numeric Chat ID — paste it above.
                </p>
            </div>

            <!-- Notify lead time -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notification lead time</label>
                <div class="flex items-center gap-2">
                    <input type="number" x-model="settingsMinutes" min="1" max="1440" class="w-24 px-3 py-2 border border-gray-200 rounded-lg text-sm text-center">
                    <span class="text-sm text-gray-600">minutes before active</span>
                </div>
                <p x-show="settingsError" class="text-xs text-red-500 mt-1" x-text="settingsError"></p>
                <button @click="saveSettings()" class="mt-3 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">Save</button>
            </div>

            <hr class="border-gray-100">

            <!-- Change password -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Change password</label>
                <div class="space-y-2">
                    <input type="password" x-model="pwCurrent" placeholder="Current password" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <input type="password" x-model="pwNew"     placeholder="New password (min 8)" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <input type="password" x-model="pwNew2"    placeholder="Confirm new password" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
                <p x-show="pwError" class="text-xs text-red-500 mt-2" x-text="pwError"></p>
                <p x-show="pwOk" class="text-xs text-emerald-600 mt-2">Password changed successfully.</p>
                <button @click="changePassword()" class="mt-3 px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors">Update password</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast ────────────────────────────────────────────────────── -->
<div x-show="toastMsg" x-transition class="toast" x-text="toastMsg" style="display:none"></div>

<script src="js/app.js"></script>
</body>
</html>
