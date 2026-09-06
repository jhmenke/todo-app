const COLORS = ['#5b4dff','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#14b8a6','#f97316','#64748b'];

function i18n(key, vars = {}) {
    let cur = window.I18N || {};
    for (const p of key.split('.')) {
        if (cur == null || typeof cur !== 'object' || !(p in cur)) return key;
        cur = cur[p];
    }
    if (typeof cur !== 'string') return key;
    return cur.replace(/\{(\w+)\}/g, (_, k) => (vars[k] != null ? String(vars[k]) : ''));
}

function pad2(n) { return String(n).padStart(2, '0'); }
function formatFixedDate(d, withTime = true) {
    const day = `${d.getFullYear()}.${pad2(d.getMonth() + 1)}.${pad2(d.getDate())}`;
    if (!withTime) return day;
    if (window.LOCALE === 'de') {
        return `${day} ${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    }
    let h = d.getHours();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${day} ${h}:${pad2(d.getMinutes())} ${ampm}`;
}

// ── Datetime tag parser ─────────────────────────────────────────
// Parses the content inside <...> into { date: 'YYYY-MM-DD', time: 'HH:MM' }
// Returns null if nothing recognisable is found.
function parseDateTag(raw) {
    let s = raw.trim().toLowerCase();
    s = s.replace(/\bum\b/g, ' ').replace(/\s+/g, ' ').trim(); // strip German "at"

    const pad     = n => String(n).padStart(2, '0');
    const today   = new Date(); today.setHours(0, 0, 0, 0);
    const addDays = n => { const d = new Date(today); d.setDate(d.getDate() + n); return d; };
    const fmt     = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

    let h = null, m = 0, match;

    // ── 1. Named time words ──────────────────────────────────────
    const namedTimes = [
        { words: ['noon', 'mittag'],             h: 12 },
        { words: ['midnight', 'mitternacht'],    h: 0  },
        { words: ['morning', 'morgens', 'früh'], h: 8  },
        { words: ['evening', 'abend', 'abends'], h: 19 },
        { words: ['night', 'nacht', 'nachts'],   h: 22 },
    ];
    for (const nt of namedTimes) {
        const w = nt.words.find(w => s.includes(w));
        if (w !== undefined) {
            h = nt.h;
            s = s.replace(w, ' ').replace(/\s+/g, ' ').trim();
            break;
        }
    }

    // ── 2. Clock time (HH:MM | HH am/pm | HH Uhr) ───────────────
    if (h === null) {
        if (match = s.match(/\b(\d{1,2}):(\d{2})\b/)) {
            h = parseInt(match[1]); m = parseInt(match[2]);
            s = s.replace(match[0], ' ').replace(/\s+/g, ' ').trim();
        } else if (match = s.match(/\b(\d{1,2})\s*(am|pm)\b/)) {
            h = parseInt(match[1]);
            if (match[2] === 'pm' && h < 12) h += 12;
            if (match[2] === 'am' && h === 12) h = 0;
            s = s.replace(match[0], ' ').replace(/\s+/g, ' ').trim();
        } else if (match = s.match(/\b(\d{1,2})\s*uhr\b/)) {
            h = parseInt(match[1]);
            s = s.replace(match[0], ' ').replace(/\s+/g, ' ').trim();
        }
    }

    // ── 3. Day / date (most specific first) ─────────────────────
    let dateStr = '';
    const DOW = {
        su: 0, sunday: 0, sonntag: 0,
        mo: 1, monday: 1, montag: 1,
        di: 2, tue: 2, tuesday: 2, dienstag: 2,
        mi: 3, wed: 3, wednesday: 3, mittwoch: 3,
        do: 4, thu: 4, thursday: 4, donnerstag: 4,
        fr: 5, friday: 5, freitag: 5,
        sa: 6, saturday: 6, samstag: 6,
    };

    if (s.includes('übermorgen') || s.includes('uebermorgen') || /\bday after tomorrow\b/.test(s)) {
        dateStr = fmt(addDays(2));
        s = s.replace(/übermorgen|uebermorgen|\bday after tomorrow\b/, ' ');
    } else if (/\b(tomorrow|morgen)\b/.test(s)) {
        dateStr = fmt(addDays(1));
        s = s.replace(/\b(tomorrow|morgen)\b/, ' ');
    } else if (/\b(today|heute)\b/.test(s)) {
        dateStr = fmt(today);
        s = s.replace(/\b(today|heute)\b/, ' ');
    } else if (/\bnext week\b/.test(s) || s.includes('nächste woche')) {
        dateStr = fmt(addDays((1 - today.getDay() + 7) % 7 || 7)); // next Monday
        s = s.replace(/\bnext week\b/, ' ').replace('nächste woche', ' ');
    } else if (match = s.match(/(?:^|(?<=\s))(su|sunday|sonntag|mo|monday|montag|di|tue|tuesday|dienstag|mi|wed|wednesday|mittwoch|do|thu|thursday|donnerstag|fr|friday|freitag|sa|saturday|samstag)(?=\s|$)/)) {
        dateStr = fmt(addDays((DOW[match[1]] - today.getDay() + 7) % 7 || 7));
        s = s.replace(match[0], ' ');
    } else if (match = s.match(/(\d{1,2})\.(\d{1,2})\.(\d{4})?/)) {
        const y = match[3] ? parseInt(match[3]) : today.getFullYear();
        dateStr = fmt(new Date(y, parseInt(match[2]) - 1, parseInt(match[1])));
        s = s.replace(match[0], ' ');
    } else if (match = s.match(/(\d{4})-(\d{2})-(\d{2})/)) {
        dateStr = match[0];
        s = s.replace(match[0], ' ');
    }

    // ── 4. Bare hour only if what's left after date words is just a number
    s = s.replace(/\s+/g, ' ').trim();
    if (h === null && dateStr && /^\d{1,2}$/.test(s)) {
        const n = parseInt(s, 10);
        if (n >= 0 && n <= 23) h = n;
    }

    if (h === null && !dateStr) return null;
    if (h !== null && (h > 23 || m > 59)) return null;

    return { date: dateStr, time: h !== null ? `${pad(h)}:${pad(m)}` : '' };
}

function todoApp() {
    return {
        // ── Data ──────────────────────────────────────────────
        todos:        [],
        tags:         [],
        allUsers:     [],   // all other registered users
        loading:      false,

        // ── Filters / Sort ────────────────────────────────────
        filterTagId:    null,
        filterStatus:   'today',
        sortBy:         'priority',
        sortDir:        'asc',
        hideCompleted:  true,
        sidebarOpen:    false,
        searchQuery:    '',
        searchTimer:    null,
        completedFrom:  '',
        completedTo:    '',

        // ── Drawer (todo detail) ───────────────────────────────
        drawer:       null,   // full todo object
        drawerTab:    'comments', // 'comments' | 'files' | 'shares'
        comments:     [],
        shares:       [],
        files:        [],
        newComment:   '',
        shareEmail:   '',
        shareError:   '',
        uploading:    false,
        uploadError:  '',
        drawerLoading: false,

        // ── Edit-in-drawer ────────────────────────────────────
        editing: false,

        // ── Create/Edit Modal ─────────────────────────────────
        modal:         null,   // null | 'create' | todo (for edit)
        form:          { title: '', active_date: '', active_time: '', tag_ids: [], recur_type: '', recur_interval: 1, recur_days: [], recur_ends_at: '', share_emails: [], priority: 4 },
        newSubtask:    '',
        formError:     '',
        savingForm:    false,
        newTagName:    '',
        newTagColor:   '#5b4dff',
        showTagForm:   false,
        shareDropdown: [],      // filtered users shown while typing <+
        shareDropdownIndex: -1, // keyboard-highlighted index in shareDropdown
        tagDropdown: [],        // filtered tags shown while typing #
        tagDropdownIndex: -1,   // keyboard-highlighted index in tagDropdown

        // ── Settings modal ────────────────────────────────────
        showSettings:     false,
        settingsMinutes:  5,
        settingsChannel:  'telegram',
        settingsLocale:   (typeof window !== 'undefined' && window.LOCALE) ? window.LOCALE : 'en',
        settingsError:    '',
        telegramLinked:      false,
        telegramConfigured:  false,
        telegramLinkPending: false,
        telegramLinkUrl:     '',
        telegramError:       '',
        telegramLinkTimer:   null,
        pwCurrent:   '',
        pwNew:       '',
        pwNew2:      '',
        pwError:     '',
        pwOk:        false,

        // ── Toast / undo ──────────────────────────────────────
        toastMsg:    '',
        toastTimer:  null,
        undoId:      null,
        undoNextId:  null,
        leavingId:   null,
        syncTimer:   null,

        t(key, vars = {}) { return i18n(key, vars); },

        // ── Init ──────────────────────────────────────────────
        async init() {
            await Promise.all([this.loadTags(), this.loadTodos()]);
            const [s, users] = await Promise.all([
                this.api('GET', 'settings'),
                this.api('GET', 'get_users'),
            ]);
            if (s.notify_minutes)     this.settingsMinutes     = s.notify_minutes;
            if (s.notify_channel)     this.settingsChannel     = s.notify_channel;
            if (s.locale)             this.settingsLocale      = s.locale;
            this.telegramLinked     = !!s.telegram_linked;
            this.telegramConfigured = !!s.telegram_configured;
            this.$watch('showSettings', open => {
                if (open) {
                    this.refreshTelegramStatus().then(() => {
                        if (!this.telegramLinked && this.telegramConfigured) this.prepareTelegramLink();
                    });
                } else this.stopTelegramPoll();
            });
            this.allUsers = Array.isArray(users) ? users : [];
            const openId = parseInt(new URLSearchParams(location.search).get('todo') || '0', 10);
            if (openId) {
                const todo = await this.api('GET', 'todo', null, { id: openId });
                if (!todo.error) await this.openDrawer(todo);
                history.replaceState({}, '', location.pathname);
            }
            this.startLiveSync();
        },

        startLiveSync() {
            const tick = () => {
                if (document.visibilityState === 'visible') this.loadTodos(true);
            };
            document.addEventListener('visibilitychange', tick);
            window.addEventListener('focus', tick);
            this.syncTimer = setInterval(tick, 8000);
        },

        // ── API helper ────────────────────────────────────────
        async api(method, action, body = null, params = {}) {
            try {
                const qs = new URLSearchParams({ action, ...params }).toString();
                const opts = { method, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.CSRF_TOKEN || '' } };
                if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
                const res = await fetch(`api.php?${qs}`, opts);
                if (res.status === 401) { location.href = 'auth.php'; return { error: 'Unauthenticated' }; }
                return await res.json();
            } catch (e) {
                return { error: 'Request failed: ' + e.message };
            }
        },

        // ── Load ──────────────────────────────────────────────
        async loadTodos(silent = false) {
            if (silent && (this.loading || this.leavingId)) return;
            if (!silent) this.loading = true;
            try {
                const params = { status: this.filterStatus, sort: this.sortBy, dir: this.sortDir };
                if (this.searchQuery.trim()) params.q = this.searchQuery.trim();
                if (this.filterTagId) params.tag_id = this.filterTagId;
                if (this.filterStatus === 'all' && this.hideCompleted) params.hide_completed = '1';
                if (this.filterStatus === 'completed') {
                    if (this.completedFrom) params.completed_from = this.completedFrom;
                    if (this.completedTo)   params.completed_to   = this.completedTo;
                }
                const data = await this.api('GET', 'todos', null, params);
                const list = Array.isArray(data) ? data : [];
                if (silent && JSON.stringify(list) === JSON.stringify(this.todos)) return;
                this.todos = list;
                if (silent && this.drawer) await this.refreshOpenDrawer();
            } finally {
                if (!silent) this.loading = false;
            }
        },

        async refreshOpenDrawer() {
            if (!this.drawer) return;
            const full = await this.api('GET', 'todo', null, { id: this.drawer.id });
            if (full.error) this.closeDrawer();
            else this.drawer = full;
        },

        async loadTags() {
            const data = await this.api('GET', 'tags');
            this.tags = Array.isArray(data) ? data : [];
        },

        // ── Filters ───────────────────────────────────────────
        setStatus(s) {
            this.filterStatus = s;
            this.sidebarOpen = false;
            this.loadTodos();
        },
        setTag(id) {
            this.filterTagId = (this.filterTagId === id) ? null : id;
            this.sidebarOpen = false;
            this.loadTodos();
        },
        filterTitle() {
            if (this.filterTagId) {
                const t = this.tags.find(x => x.id === this.filterTagId);
                return t ? t.name : 'Tag';
            }
            return ({
                all: this.t('nav.all_tasks'),
                today: this.t('nav.today'),
                pending: this.t('nav.pending'),
                active: this.t('nav.active'),
                completed: this.t('nav.completed'),
            })[this.filterStatus] || this.t('nav.tasks');
        },
        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.loadTodos(), 200);
        },
        clearSearch() {
            this.searchQuery = '';
            this.loadTodos();
        },
        setSort(field) {
            if (this.sortBy === field) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy  = field;
                this.sortDir = (field === 'title' || field === 'priority') ? 'asc' : 'desc';
            }
            this.loadTodos();
        },
        sortLabel(field) {
            if (this.sortBy !== field) return '';
            return this.sortDir === 'asc' ? ' ↑' : ' ↓';
        },

        // ── Create / Edit Modal ───────────────────────────────
        openCreate() {
            this.form = {
                title: '', active_date: '', active_time: '', tag_ids: [],
                recur_type: '', recur_interval: 1, recur_days: [], recur_ends_at: '',
                share_emails: [], priority: 4, parent_id: null,
            };
            this.formError     = '';
            this.showTagForm   = false;
            this.shareDropdown = [];
            this.modal         = 'create';
        },
        openEdit(todo) {
            const at = todo.active_at ? todo.active_at.replace(' ', 'T') : null;
            this.form = {
                id:             todo.id,
                title:          todo.title,
                active_date:    at ? at.slice(0, 10) : '',
                active_time:    at ? at.slice(11, 16) : '',
                tag_ids:        todo.tags.map(t => t.id),
                recur_type:     todo.recur_type || '',
                recur_interval: todo.recur_interval || 1,
                recur_days:     todo.recur_days ? JSON.parse(todo.recur_days) : [],
                recur_ends_at:  todo.recur_ends_at ? todo.recur_ends_at.slice(0,16) : '',
                share_emails:   [],
                priority:       todo.priority || 4,
                parent_id:      todo.parent_id || null,
            };
            this.formError     = '';
            this.showTagForm   = false;
            this.shareDropdown = [];
            this.modal         = todo;
        },
        closeModal() { this.modal = null; },

        toggleFormTag(id) {
            const i = this.form.tag_ids.indexOf(id);
            if (i === -1) this.form.tag_ids.push(id);
            else this.form.tag_ids.splice(i, 1);
        },
        toggleFormDay(d) {
            const i = this.form.recur_days.indexOf(d);
            if (i === -1) this.form.recur_days.push(d);
            else this.form.recur_days.splice(i, 1);
        },

        async saveForm() {
            if (!this.form.title.trim()) { this.formError = this.t('form.title_required'); return; }
            this.savingForm = true;
            this.formError  = '';
            const isCreate  = this.modal === 'create';
            try {
                const payload = { ...this.form };
                payload.active_at = this.resolveActiveAt(payload.active_date, payload.active_time);
                delete payload.active_date;
                delete payload.active_time;
                delete payload.share_emails;
                if (!payload.recur_type) { payload.recur_type = null; payload.recur_days = []; payload.recur_ends_at = ''; }
                const action = isCreate ? 'create_todo' : 'update_todo';
                const result = await this.api('POST', action, payload);
                if (result.error) { this.formError = result.error; return; }

                // Apply pending shares
                const shareErrors = [];
                for (const email of this.form.share_emails) {
                    const r = await this.api('POST', 'add_share', { todo_id: result.id, email });
                    if (r.error) shareErrors.push(email);
                }

                this.modal = null;

                // If the saved todo would be hidden in the current filter, switch to show it
                const at  = result.active_at ? new Date(result.active_at.replace(' ', 'T')) : null;
                const now = new Date();
                const sharedNote = this.form.share_emails.length && !shareErrors.length
                    ? this.t('toast.shared_n', { n: this.form.share_emails.length }) : '';
                const shareFailNote = shareErrors.length
                    ? this.t('toast.share_fail', { emails: shareErrors.join(', ') }) : '';

                if (at && at <= now && !result.completed_at && this.filterStatus === 'pending') {
                    this.filterStatus = 'active';
                    this.toast((isCreate ? this.t('toast.created_active') : this.t('toast.saved_active')) + sharedNote + shareFailNote);
                } else if (result.completed_at && this.filterStatus !== 'completed') {
                    this.filterStatus = 'completed';
                    this.toast(this.t('toast.saved_completed') + sharedNote + shareFailNote);
                } else {
                    this.toast((isCreate ? this.t('toast.created') : this.t('toast.saved')) + sharedNote + shareFailNote);
                }

                await this.loadTodos();
                if (this.drawer && this.drawer.id === result.id) this.drawer = result;
            } finally {
                this.savingForm = false;
            }
        },

        async createInlineTag() {
            if (!this.newTagName.trim()) return;
            const tag = await this.api('POST', 'create_tag', { name: this.newTagName.trim(), color: this.newTagColor });
            if (!tag.error) {
                this.tags.push(tag);
                this.form.tag_ids.push(tag.id);
                this.newTagName  = '';
                this.newTagColor = '#5b4dff';
                this.showTagForm = false;
            }
        },

        // ── Drawer ────────────────────────────────────────────
        async openDrawer(todo) {
            this.drawer      = todo;
            this.drawerTab   = 'comments';
            this.newComment  = '';
            this.newSubtask  = '';
            this.shareEmail  = '';
            this.shareError  = '';
            this.uploadError = '';
            this.files       = [];
            this.editing     = false;
            const full = await this.api('GET', 'todo', null, { id: todo.id });
            if (!full.error) this.drawer = full;
            await this.loadDrawerData(todo.id);
        },
        closeDrawer() { this.drawer = null; },

        async loadDrawerData(id) {
            this.drawerLoading = true;
            const [comments, shares, files] = await Promise.all([
                this.api('GET', 'comments', null, { todo_id: id }),
                this.drawer?.is_owner ? this.api('GET', 'shares', null, { todo_id: id }) : Promise.resolve([]),
                this.api('GET', 'get_files', null, { todo_id: id }),
            ]);
            this.comments      = Array.isArray(comments) ? comments : [];
            this.shares        = Array.isArray(shares) ? shares : [];
            this.files         = Array.isArray(files) ? files : [];
            this.drawerLoading = false;
        },

        async uploadFile(event) {
            const input = event.target;
            if (!input.files.length) return;
            this.uploading   = true;
            this.uploadError = '';
            const fd = new FormData();
            fd.append('file', input.files[0]);
            fd.append('todo_id', this.drawer.id);
            try {
                const res = await fetch('api.php?action=upload_file', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
                    body: fd,
                });
                const result = await res.json();
                if (result.error) { this.uploadError = result.error; return; }
                result.uploader_email = result.uploader_email || '';
                this.files.push(result);
                input.value = '';
            } catch (e) {
                this.uploadError = 'Upload failed: ' + e.message;
            } finally {
                this.uploading = false;
            }
        },

        async deleteFile(id) {
            if (!confirm(this.t('files.delete_confirm'))) return;
            const r = await this.api('POST', 'delete_file', { id });
            if (!r.error) this.files = this.files.filter(f => f.id !== id);
        },

        fileUrl(file) { return `download.php?f=${encodeURIComponent(file.stored_as)}`; },

        formatBytes(bytes) {
            bytes = parseInt(bytes);
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        fileTypeLabel(mime) {
            if (!mime) return 'FILE';
            if (mime.startsWith('image/'))           return 'IMG';
            if (mime === 'application/pdf')           return 'PDF';
            if (mime.includes('word'))                return 'DOC';
            if (mime.includes('excel') || mime.includes('spreadsheet')) return 'XLS';
            if (mime === 'text/csv')                  return 'CSV';
            if (mime === 'text/plain')                return 'TXT';
            if (mime.includes('zip'))                 return 'ZIP';
            return 'FILE';
        },

        async addComment() {
            const body = this.newComment.trim();
            if (!body) return;
            const c = await this.api('POST', 'add_comment', { todo_id: this.drawer.id, body });
            if (!c.error) { this.comments.push(c); this.newComment = ''; this.updateCommentCount(1); }
        },

        async addShare() {
            this.shareError = '';
            const email = this.shareEmail.trim();
            if (!email) return;
            const result = await this.api('POST', 'add_share', { todo_id: this.drawer.id, email });
            if (result.error) { this.shareError = result.error; return; }
            this.shares.push(result);
            this.shareEmail = '';
        },

        async removeShare(userId) {
            await this.api('POST', 'remove_share', { todo_id: this.drawer.id, user_id: userId });
            this.shares = this.shares.filter(s => s.user_id !== userId);
        },

        updateCommentCount(delta) {
            if (this.drawer) this.drawer.comment_count = (this.drawer.comment_count || 0) + delta;
            const t = this.todos.find(t => t.id === this.drawer?.id);
            if (t) t.comment_count = (t.comment_count || 0) + delta;
        },

        // ── Complete / Delete ─────────────────────────────────
        async addSubtask() {
            const title = (this.newSubtask || '').trim();
            if (!title || !this.drawer) return;
            const r = await this.api('POST', 'create_todo', {
                title,
                parent_id: this.drawer.id,
                priority: this.drawer.priority || 4,
            });
            if (r.error) { this.toast(r.error); return; }
            this.newSubtask = '';
            const full = await this.api('GET', 'todo', null, { id: this.drawer.id });
            if (!full.error) this.drawer = full;
            await this.loadTodos();
        },

        async toggleComplete(todo, e) {
            e.preventDefault();
            e.stopPropagation();
            if (!todo.is_owner) return;
            if (todo.completed_at) {
                const result = await this.api('POST', 'uncomplete_todo', { id: todo.id });
                if (result.error) { this.toast(result.error); return; }
                this.clearUndo();
                this.toast(this.t('toast.incomplete'));
            } else {
                this.leavingId = todo.id;
                const prev = todo.completed_at;
                todo.completed_at = new Date().toISOString().slice(0, 19).replace('T', ' ');
                const [result] = await Promise.all([
                    this.api('POST', 'complete_todo', { id: todo.id }),
                    this.sleep(280),
                ]);
                if (result.error) {
                    todo.completed_at = prev;
                    this.leavingId = null;
                    this.toast(result.error);
                    return;
                }
                this.offerUndo(todo.id, result.next_todo);
                this.toast(result.next_todo ? this.t('toast.next') : this.t('toast.completed'), 10000, true);
            }
            if (this.drawer?.id === todo.id) this.closeDrawer();
            await this.loadTodos();
            this.leavingId = null;
            if (this.drawer) {
                const full = await this.api('GET', 'todo', null, { id: this.drawer.id });
                if (!full.error) this.drawer = full;
            }
        },

        offerUndo(id, nextTodo) {
            this.undoId = id;
            this.undoNextId = nextTodo && nextTodo.id ? nextTodo.id : null;
        },
        clearUndo() {
            this.undoId = null;
            this.undoNextId = null;
        },
        async undoComplete() {
            const id = this.undoId;
            const nextId = this.undoNextId;
            if (!id) return;
            this.clearUndo();
            this.toastMsg = '';
            clearTimeout(this.toastTimer);
            if (nextId) await this.api('POST', 'delete_todo', { id: nextId });
            const result = await this.api('POST', 'uncomplete_todo', { id });
            if (result.error) { this.toast(result.error); return; }
            this.toast(this.t('toast.restored'));
            await this.loadTodos();
            if (this.drawer) {
                const full = await this.api('GET', 'todo', null, { id: this.drawer.id });
                if (!full.error) this.drawer = full;
            }
        },
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },

        async deleteTodo(id) {
            if (!confirm(this.t('confirm.delete_todo'))) return;
            await this.api('POST', 'delete_todo', { id });
            this.todos = this.todos.filter(t => t.id !== id);
            if (this.drawer?.id === id) this.closeDrawer();
            this.toast(this.t('toast.deleted'));
        },

        // ── Tags management ───────────────────────────────────
        async deleteTag(id) {
            if (!confirm(this.t('confirm.delete_tag'))) return;
            await this.api('POST', 'delete_tag', { id });
            this.tags = this.tags.filter(t => t.id !== id);
            if (this.filterTagId === id) { this.filterTagId = null; }
            await this.loadTodos();
        },

        // ── Settings ──────────────────────────────────────────
        applyTelegramStatus(s) {
            if (!s || s.error) return;
            this.telegramLinked     = !!s.telegram_linked;
            this.telegramConfigured = !!s.telegram_configured;
        },
        async refreshTelegramStatus() {
            const s = await this.api('GET', 'settings');
            this.applyTelegramStatus(s);
            if (this.telegramLinked) {
                this.telegramLinkPending = false;
                this.telegramLinkUrl = '';
                this.stopTelegramPoll();
            }
        },
        async prepareTelegramLink() {
            this.telegramError = '';
            const r = await this.api('POST', 'telegram_link');
            if (r.error) { this.telegramError = r.error; this.telegramLinkUrl = ''; return; }
            this.telegramLinkUrl = r.url || '';
        },
        onTelegramLinkClick() {
            this.telegramLinkPending = true;
            this.startTelegramPoll();
        },
        startTelegramPoll() {
            this.stopTelegramPoll();
            const started = Date.now();
            this.telegramLinkTimer = setInterval(() => {
                if (!this.showSettings || Date.now() - started > 120000) {
                    this.stopTelegramPoll();
                    this.telegramLinkPending = false;
                    return;
                }
                this.refreshTelegramStatus();
            }, 2000);
        },
        stopTelegramPoll() {
            if (this.telegramLinkTimer) {
                clearInterval(this.telegramLinkTimer);
                this.telegramLinkTimer = null;
            }
        },
        async unlinkTelegram() {
            this.telegramError = '';
            const r = await this.api('POST', 'telegram_unlink');
            if (r.error) { this.telegramError = r.error; return; }
            this.telegramLinked = false;
            this.telegramLinkPending = false;
            this.stopTelegramPoll();
            if (this.telegramConfigured) await this.prepareTelegramLink();
        },

        async saveSettings() {
            this.settingsError = '';
            const r = await this.api('POST', 'update_settings', {
                notify_minutes:    parseInt(this.settingsMinutes),
                notify_channel:    this.settingsChannel,
                locale:            this.settingsLocale,
            });
            if (r.error) { this.settingsError = r.error; return; }
            if (this.settingsLocale && this.settingsLocale !== window.LOCALE) {
                location.reload();
                return;
            }
            this.toast(this.t('settings.saved'));
        },

        async changePassword() {
            this.pwError = ''; this.pwOk = false;
            if (this.pwNew !== this.pwNew2) { this.pwError = this.t('auth.password_mismatch'); return; }
            const r = await this.api('POST', 'change_password', { current: this.pwCurrent, new: this.pwNew });
            if (r.error) { this.pwError = r.error; return; }
            this.pwCurrent = this.pwNew = this.pwNew2 = '';
            this.pwOk = true;
        },

        async logout() {
            await this.api('POST', 'logout');
            location.href = 'auth.php';
        },

        // ── Helpers ───────────────────────────────────────────
        toast(msg, ms = 3000, keepUndo = false) {
            this.toastMsg = msg;
            if (!keepUndo) this.clearUndo();
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.toastMsg = '';
                this.clearUndo();
            }, ms);
        },

        formatDate(dt) {
            if (!dt) return '';
            const d = new Date(dt.replace(' ', 'T'));
            const now = new Date();
            const diff = d - now;
            if (diff < 0 && diff > -86400000) return this.t('date.active_now');
            return formatFixedDate(d, true);
        },

        formatDateShort(dt) {
            if (!dt) return '—';
            return formatFixedDate(new Date(dt.replace(' ', 'T')), true);
        },

        recurLabel(todo) {
            if (!todo.recur_type) return '';
            if (todo.recur_type === 'custom') return this.t('recur.custom', { n: todo.recur_interval });
            return this.t('recur.' + todo.recur_type) || '';
        },

        // Opt-in tokens: <datetime> or "datetime", <+email>, #tag, p1–p4. Never rewrite free text.
        parseTagsInTitle() {
            const re = /<(\+?)([^>]+)>|["“„](\+?)([^"“”„]+)["“”]/g;
            let newTitle = this.form.title;
            let match;
            while ((match = re.exec(this.form.title)) !== null) {
                const plus = match[0].startsWith('<') ? match[1] : (match[3] || '');
                const inner = match[0].startsWith('<') ? match[2] : (match[4] || '');
                if (plus === '+') {
                    const email = inner.trim();
                    if (email && !this.form.share_emails.includes(email)) {
                        this.form.share_emails.push(email);
                    }
                    newTitle = newTitle.replace(match[0], '').replace(/  +/g, ' ').trim();
                } else {
                    const parsed = parseDateTag(inner);
                    if (parsed) {
                        if (parsed.date) this.form.active_date = parsed.date;
                        if (parsed.time) this.form.active_time = parsed.time;
                        newTitle = newTitle.replace(match[0], '').replace(/  +/g, ' ').trim();
                    }
                }
            }

            const pri = newTitle.match(/(?:^|\s)p([1-4])(?=\s|$)/i);
            if (pri) {
                this.form.priority = parseInt(pri[1], 10);
                newTitle = newTitle.replace(/(?:^|\s)p[1-4](?=\s|$)/gi, ' ').replace(/\s+/g, ' ').trim();
            }

            if (newTitle !== this.form.title) this.form.title = newTitle;
            this.updateShareDropdown();
            this.updateTagDropdown();
        },

        // Show user dropdown when title contains an incomplete <+... (no closing >)
        updateShareDropdown() {
            const m = this.form.title.match(/<\+([^>]*)$/);
            if (m !== null) {
                const q = m[1].toLowerCase();
                this.shareDropdown = this.allUsers.filter(u =>
                    u.email.toLowerCase().includes(q) && !this.form.share_emails.includes(u.email)
                );
            } else {
                this.shareDropdown = [];
            }
            this.shareDropdownIndex = this.shareDropdown.length ? 0 : -1;
        },

        shareDropdownNav(e) {
            const sd = this.shareDropdown.length > 0;
            const td = this.tagDropdown.length > 0;
            if (!sd && !td) return;
            if (e.key === 'ArrowDown' || e.key === 'Tab') {
                e.preventDefault();
                if (sd) this.shareDropdownIndex = (this.shareDropdownIndex + 1) % this.shareDropdown.length;
                if (td) this.tagDropdownIndex = (this.tagDropdownIndex + 1) % this.tagDropdown.length;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (sd) this.shareDropdownIndex = (this.shareDropdownIndex - 1 + this.shareDropdown.length) % this.shareDropdown.length;
                if (td) this.tagDropdownIndex = (this.tagDropdownIndex - 1 + this.tagDropdown.length) % this.tagDropdown.length;
            } else if (e.key === 'Enter') {
                if (sd) {
                    e.preventDefault();
                    const i = this.shareDropdownIndex >= 0 ? this.shareDropdownIndex : 0;
                    this.selectShareUser(this.shareDropdown[i].email);
                } else if (td) {
                    e.preventDefault();
                    const i = this.tagDropdownIndex >= 0 ? this.tagDropdownIndex : 0;
                    this.selectTag(this.tagDropdown[i].id);
                }
            }
        },

        // Show tag dropdown when title contains an incomplete #tagname (no space after)
        updateTagDropdown() {
            const m = this.form.title.match(/#([^\s#]*)$/);
            if (m !== null) {
                const q = m[1].toLowerCase();
                this.tagDropdown = this.tags.filter(t =>
                    t.name.toLowerCase().includes(q)
                );
            } else {
                this.tagDropdown = [];
            }
            this.tagDropdownIndex = this.tagDropdown.length ? 0 : -1;
        },

        selectTag(tagId) {
            this.form.title = this.form.title.replace(/#[^\s#]*$/, '').replace(/\s+/g, ' ').trim();
            if (!this.form.tag_ids.includes(tagId)) {
                this.form.tag_ids.push(tagId);
            }
            this.tagDropdown = [];
            this.tagDropdownIndex = -1;
        },

        // Called when user clicks a name in the share dropdown
        selectShareUser(email) {
            // Remove the incomplete <+... from the title
            this.form.title = this.form.title.replace(/<\+[^>]*$/, '').replace(/  +/g, ' ').trim();
            if (!this.form.share_emails.includes(email)) {
                this.form.share_emails.push(email);
            }
            this.shareDropdown = [];
        },

        resolveActiveAt(date, time) {
            if (!date && !time) return '';
            if (date && time)  return `${date} ${time}:00`;
            if (date && !time) return `${date} 09:00:00`;
            // time only — use today if time is still ahead, otherwise tomorrow
            const [h, m] = time.split(':').map(Number);
            const now = new Date();
            const candidate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, 0);
            if (candidate <= now) candidate.setDate(candidate.getDate() + 1);
            const p = n => String(n).padStart(2, '0');
            return `${candidate.getFullYear()}-${p(candidate.getMonth()+1)}-${p(candidate.getDate())} ${p(h)}:${p(m)}:00`;
        },

        dayLabel(n) { return this.t('day.' + n); },
        colors() { return COLORS; },
        days() { return [0,1,2,3,4,5,6]; },

        statusCounts() {
            // Not computed server-side; just used for label
            return { all: '', pending: '', active: '', completed: '' };
        },
    };
}
