const COLORS = ['#39745c','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#14b8a6','#f97316','#64748b'];

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
function localDateKey(date) {
    return [date.getFullYear(), pad2(date.getMonth() + 1), pad2(date.getDate())].join('-');
}
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
        loading:      false,
        listError:    false,
        loadedListScope: null,
        summary:      null,

        // ── Filters / Sort ────────────────────────────────────
        filterTagId:    null,
        filterStatus:   'today',
        sortBy:         'active_at',
        sortDir:        'asc',
        hideCompleted:  true,
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
        commentOpen:  false,
        commentPosting: false,
        commentError: '',
        subtaskOpen:  false,
        shareEmail:   '',
        shareError:   '',
        uploading:    false,
        uploadError:  '',
        drawerLoading: false,
        drawerReq: 0,
        drawerRefreshReq: 0,
        drawerRefreshError: false,
        completingDrawer: false,
        deletingTodo: false,

        // ── Edit-in-drawer ────────────────────────────────────
        editing: null,

        // ── Create/Edit Modal ─────────────────────────────────
        modal:         null,   // null | 'create' | todo (for edit)
        quickCreate:   false,
        showDateFields: false,
        form:          { title: '', active_date: '', active_time: '', tag_ids: [], recur_type: '', recur_interval: 1, recur_days: [], recur_ends_at: '', share_emails: [], priority: 4 },
        newSubtask:    '',
        formError:     '',
        savingForm:    false,
        newTagName:    '',
        newTagColor:   COLORS[0],
        showTagForm:   false,
        shareDropdown: [],      // exact sharing match for the current mention
        shareDropdownIndex: -1, // keyboard-highlighted index in shareDropdown
        shareLookupTimer: null,
        shareLookupId: 0,
        tagDropdown: [],        // filtered tags shown while typing #
        tagDropdownIndex: -1,   // keyboard-highlighted index in tagDropdown

        // ── Settings modal ────────────────────────────────────
        showSettings:     false,
        settingsMinutes:  5,
        settingsChannel:  'telegram',
        settingsLocale:   (typeof window !== 'undefined' && window.LOCALE) ? window.LOCALE : 'en',
        settingsError:    '',
        settingsDisplayName: '',
        accentTheme: 'evergreen',
        emailVerified:        false,
        emailVerificationMsg: '',
        emailVerificationBusy: false,
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
        toastTodo:   null,
        undoId:      null,
        undoNextId:  null,
        todosReq:    0,
        leavingId:   null,
        syncTimer:   null,

        t(key, vars = {}) { return i18n(key, vars); },

        // ── Init ──────────────────────────────────────────────
        async init() {
            try {
                if (localStorage.getItem('mytasks-accent') === 'blue') this.accentTheme = 'blue';
            } catch { /* The workspace can run without local storage. */ }
            await Promise.all([this.loadTags(), this.loadTodos()]);
            const s = await this.api('GET', 'settings');
            if (s.notify_minutes)     this.settingsMinutes     = s.notify_minutes;
            if (s.notify_channel)     this.settingsChannel     = s.notify_channel;
            if (s.locale)             this.settingsLocale      = s.locale;
            if (s.display_name != null) this.settingsDisplayName = s.display_name;
            this.applyAccountStatus(s);
            this.$watch('showSettings', open => {
                if (open) {
                    this.refreshTelegramStatus().then(() => {
                        if (!this.telegramLinked && this.telegramConfigured) this.prepareTelegramLink();
                    });
                } else this.stopTelegramPoll();
            });
            const query = new URLSearchParams(location.search);
            const verification = query.get('email_verification');
            if (verification) {
                this.showSettings = true;
                if (verification !== 'success' || !this.emailVerified) {
                    this.emailVerificationMsg = this.t('settings.email_verification_invalid');
                }
                history.replaceState({}, '', location.pathname);
            }
            const openId = parseInt(query.get('todo') || '0', 10);
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
                const data = await res.json().catch(() => null);
                if (!res.ok) return { error: data?.error || this.t('error.request_failed'), status: res.status };
                return data ?? { error: this.t('error.request_failed') };
            } catch {
                return { error: this.t('error.request_failed') };
            }
        },

        // ── Load ──────────────────────────────────────────────
        listScope() {
            const q = this.searchQuery.trim();
            if (q) return { status: 'all', q };

            const scope = { status: this.filterStatus };
            if (this.filterTagId) scope.tag_id = this.filterTagId;
            if (scope.status === 'all' && this.hideCompleted) scope.hide_completed = '1';
            if (scope.status === 'completed') {
                if (this.completedFrom) scope.completed_from = this.completedFrom;
                if (this.completedTo) scope.completed_to = this.completedTo;
            }
            return scope;
        },

        async loadTodos(silent = false) {
            if (this.searchTimer !== null) {
                if (silent) return;
                clearTimeout(this.searchTimer);
                this.searchTimer = null;
            }
            const req = ++this.todosReq;
            const scope = this.listScope();
            const params = { ...scope, sort: this.sortBy, dir: this.sortDir, include_summary: '1' };
            if (!silent) {
                this.loading = true;
                this.listError = false;
            }
            const data = await this.api('GET', 'todos', null, params);
            if (req !== this.todosReq) return;

            const todos = Array.isArray(data) ? data : data?.todos;
            this.listError = !Array.isArray(todos);
            if (!this.listError) {
                this.todos = todos;
                this.loadedListScope = JSON.stringify(scope);
                this.summary = data.summary ?? this.summary;
            }
            this.loading = false;
            if (silent && this.drawer && !this.savingForm && !this.completingDrawer) await this.refreshOpenDrawer();
        },

        async refreshOpenDrawer() {
            if (!this.drawer) return;
            const req = this.drawerReq;
            const refresh = ++this.drawerRefreshReq;
            const full = await this.api('GET', 'todo', null, { id: this.drawer.id });
            if (req !== this.drawerReq || refresh !== this.drawerRefreshReq) return;
            if (full.status === 404) {
                this.closeDrawer();
                this.toast(full.error, 6000);
                return;
            }
            this.drawerRefreshError = !!full.error;
            if (!this.drawerRefreshError) this.drawer = full;
        },

        async loadTags() {
            const data = await this.api('GET', 'tags');
            this.tags = Array.isArray(data) ? data : [];
        },

        // ── Filters ───────────────────────────────────────────
        setStatus(s) {
            if (this.drawer) this.closeDrawer(false);
            this.searchQuery = '';
            this.filterStatus = s;
            this.filterTagId = null;
            if (s !== 'completed' && this.sortBy === 'completed_at') {
                this.sortBy = 'active_at';
                this.sortDir = 'asc';
            }
            this.loadTodos();
        },
        setTag(id) {
            if (this.drawer) this.closeDrawer(false);
            this.searchQuery = '';
            this.filterTagId = (this.filterTagId === id) ? null : id;
            this.filterStatus = 'all';
            if (this.sortBy === 'completed_at') {
                this.sortBy = 'active_at';
                this.sortDir = 'asc';
            }
            this.loadTodos();
        },
        filterTitle() {
            if (this.searchQuery.trim()) return this.t('list.search_results');
            if (this.filterTagId) {
                const t = this.tags.find(x => x.id === this.filterTagId);
                return t ? t.name : 'Tag';
            }
            return ({
                all: this.t('nav.all_tasks'),
                today: this.t('nav.overview'),
                scheduled: this.t('nav.scheduled'),
                scheduled_today: this.t('nav.today'),
                overdue: this.t('nav.overdue'),
                upcoming: this.t('nav.upcoming'),
                pending: this.t('nav.pending'),
                active: this.t('nav.active'),
                completed: this.t('nav.completed'),
            })[this.filterStatus] || this.t('nav.tasks');
        },
        onSearchInput() {
            if (this.drawer) this.closeDrawer(false);
            if (this.searchTimer !== null) clearTimeout(this.searchTimer);
            this.todosReq++;
            this.loading = true;
            this.listError = false;
            this.searchTimer = setTimeout(() => {
                this.searchTimer = null;
                this.loadTodos();
            }, 200);
        },
        clearSearch() {
            if (!this.searchQuery) return;
            if (this.drawer) this.closeDrawer(false);
            this.searchQuery = '';
            return this.loadTodos();
        },
        showsLoadedList() {
            return !this.loading && this.loadedListScope === JSON.stringify(this.listScope());
        },
        isOverview() {
            return this.filterStatus === 'today' && !this.searchQuery.trim();
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

        workspaceDate() {
            return this.summary?.local_date || localDateKey(new Date());
        },
        formatWorkspaceDate(day, options) {
            const locale = (typeof window !== 'undefined' && window.LOCALE === 'de') ? 'de-DE' : 'en-GB';
            return new Intl.DateTimeFormat(locale, options).format(new Date(day + 'T00:00:00'));
        },
        taskGroups() {
            const today = this.workspaceDate();
            const next = new Date(today + 'T00:00:00');
            next.setDate(next.getDate() + 1);
            const tomorrow = localDateKey(next);
            const grouped = new Map();
            if (this.isOverview()) {
                grouped.set('overdue', []);
                grouped.set(today, []);
            }
            const getKey = task => {
                if (task.completed_at && this.leavingId !== task.id) return 'completed';
                const day = task.active_at?.slice(0, 10);
                if (!day) return 'unscheduled';
                return day < today ? 'overdue' : day;
            };
            const push = (task, key, parent = null) => {
                if (!grouped.has(key)) grouped.set(key, []);
                grouped.get(key).push({
                    task,
                    nested: parent !== null && key === getKey(parent),
                    parentTitle: parent?.title || task.parent_title,
                });
            };
            for (const todo of this.todos) {
                push(todo, getKey(todo));
                for (const child of todo.children || []) push(child, getKey(child), todo);
            }
            const special = { overdue: 0, unscheduled: 2, completed: 3 };
            const keys = [...grouped.keys()].sort((a, b) =>
                (special[a] ?? 1) - (special[b] ?? 1) || a.localeCompare(b));
            return keys.map(key => ({
                key,
                tasks: grouped.get(key),
                date: key in special ? '' : this.formatWorkspaceDate(key, { day: 'numeric', month: 'short' }),
                label: key in special ? this.t('nav.' + key)
                    : key === today ? this.t('nav.today')
                    : key === tomorrow ? this.t('nav.tomorrow')
                    : this.formatWorkspaceDate(key, { weekday: 'long' }),
            }));
        },
        taskSchedule(task, groupKey) {
            if (!task.active_at) return '';
            const date = new Date(task.active_at.replace(' ', 'T'));
            if (groupKey !== task.active_at.slice(0, 10)) return formatFixedDate(date, true);
            return new Intl.DateTimeFormat(window.LOCALE === 'de' ? 'de-DE' : 'en-US', {
                hour: 'numeric', minute: '2-digit',
            }).format(date);
        },

        // ── Create / Edit Modal ───────────────────────────────
        openCreate() {
            if (this.savingForm) return;
            this.cancelInlineEdit();
            this.form = {
                title: '', active_date: '', active_time: '', tag_ids: this.filterTagId ? [this.filterTagId] : [],
                recur_type: '', recur_interval: 1, recur_days: [], recur_ends_at: '',
                share_emails: [], priority: 4, parent_id: null,
            };
            this.formError     = '';
            this.showTagForm   = false;
            this.clearShareDropdown();
            this.showDateFields = false;
            this.quickCreate   = true;
            this.modal         = 'create';
        },
        openEdit(todo) {
            if (this.savingForm) return;
            this.cancelInlineEdit();
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
            this.clearShareDropdown();
            this.quickCreate   = false;
            this.modal         = todo;
        },
        closeModal() {
            this.clearShareDropdown();
            this.modal = null;
            this.quickCreate = false;
        },

        scheduleDate(daysAhead) {
            const day = new Date(this.workspaceDate() + 'T00:00:00');
            day.setDate(day.getDate() + daysAhead);
            return localDateKey(day);
        },

        chooseScheduleDate(daysAhead) {
            this.form.active_date = this.scheduleDate(daysAhead);
        },

        clearSchedule() {
            this.form.active_date = '';
            this.form.active_time = '';
        },

        scheduleChoiceLabel() {
            const { active_date: date, active_time: time } = this.form;
            const label = date && ![this.scheduleDate(0), this.scheduleDate(1)].includes(date)
                ? this.formatWorkspaceDate(date, { day: 'numeric', month: 'short', year: 'numeric' })
                : this.t('form.choose_date');
            return time ? label + ' · ' + time : label;
        },

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
            if (this.savingForm) return;
            if (this.modal === 'create' || this.modal?.title !== this.form.title) this.parseTagsInTitle(true);
            if (!this.form.title.trim()) { this.formError = this.t('form.title_required'); return; }
            this.savingForm = true;
            this.formError  = '';
            const isCreate  = this.modal === 'create';
            const recipients = [...this.form.share_emails];
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
                for (const email of recipients) {
                    const r = await this.api('POST', 'add_share', { todo_id: result.id, email });
                    if (r.error) shareErrors.push(email);
                }

                this.closeModal();

                const sharedNote = recipients.length && !shareErrors.length
                    ? this.t('toast.shared_n', { n: recipients.length }) : '';
                const shareFailNote = shareErrors.length
                    ? this.t('toast.share_fail', { emails: shareErrors.join(', ') }) : '';

                const savedMessage = this.t(isCreate ? 'toast.created' : 'toast.saved') + sharedNote + shareFailNote;
                this.toast(savedMessage, isCreate ? 10000 : 3000, false, isCreate ? result : null);

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
                this.newTagColor = COLORS[0];
                this.showTagForm = false;
            }
        },

        // ── Drawer ────────────────────────────────────────────
        async openDrawer(todo) {
            const req = ++this.drawerReq;
            this.drawer      = todo;
            this.drawerTab   = 'comments';
            this.comments    = [];
            this.shares      = [];
            this.newComment  = '';
            this.commentOpen = false;
            this.commentPosting = false;
            this.commentError = '';
            this.newSubtask  = '';
            this.subtaskOpen = false;
            this.shareEmail  = '';
            this.shareError  = '';
            this.uploadError = '';
            this.uploading   = false;
            this.files       = [];
            this.editing     = null;
            this.drawerLoading = true;
            this.drawerRefreshError = false;
            this.$nextTick?.(() => {
                if (req !== this.drawerReq) return;
                const close = [...this.$refs.plannerWindow.querySelectorAll('[data-close-task]')]
                    .find(button => button.getClientRects().length);
                close?.focus({ preventScroll: !close.classList.contains('detail-back') });
            });
            const full = await this.api('GET', 'todo', null, { id: todo.id });
            if (req !== this.drawerReq) return;
            if (full.error) {
                this.closeDrawer();
                this.toast(full.error);
                return;
            }
            this.drawer = full;
            await this.loadDrawerData(todo.id);
        },
        closeDrawer(restoreFocus = true) {
            const id = this.drawer?.id;
            const planner = this.$refs?.plannerWindow;
            const heading = this.$refs?.workspaceTitle;
            this.drawerReq++;
            this.drawer = null;
            this.editing = null;
            this.drawerLoading = false;
            if (!restoreFocus || !id || !planner) return;
            this.$nextTick?.(() => requestAnimationFrame(() => {
                if (this.drawer) return;
                const opener = [...planner.querySelectorAll('[data-task-id]')]
                    .find(button => Number(button.dataset.taskId) === Number(id));
                (opener || heading)?.focus({ preventScroll: true });
            }));
        },

        beginInlineEdit(field = 'title') {
            const todo = this.drawer;
            if (!todo || !todo.is_owner || this.savingForm) return;
            if (this.modal) this.closeModal();
            const at = todo.active_at ? todo.active_at.replace(' ', 'T') : null;
            this.form = {
                id: todo.id,
                title: todo.title,
                active_date: at ? at.slice(0, 10) : '',
                active_time: at ? at.slice(11, 16) : '',
                tag_ids: (todo.tags || []).map(tag => tag.id),
                priority: todo.priority || 4,
            };
            this.formError = '';
            this.editing = field;
            this.showDateFields = false;
            this.$nextTick?.(() => {
                const input = this.$refs.inlineEdit?.querySelector('[data-edit-field="' + field + '"]');
                const control = [...(input?.querySelectorAll('input, select, button') || [])]
                    .find(element => element.getClientRects().length);
                control?.focus();
            });
        },

        cancelInlineEdit() {
            this.editing = null;
            this.formError = '';
        },

        async saveInlineEdit() {
            if (this.savingForm || !this.editing) return;
            const field = this.editing;
            const payload = { id: this.form.id };
            if (field === 'title') {
                if (!this.form.title.trim()) { this.formError = this.t('form.title_required'); return; }
                payload.title = this.form.title;
            } else if (field === 'schedule') {
                payload.active_at = this.resolveActiveAt(this.form.active_date, this.form.active_time);
            } else if (field === 'tags') {
                payload.tag_ids = [...this.form.tag_ids];
            } else if (field === 'priority') {
                payload.priority = Number(this.form.priority);
            }
            const req = this.drawerReq;
            this.savingForm = true;
            this.formError = '';
            try {
                const result = await this.api('POST', 'update_todo', payload);
                if (req !== this.drawerReq) {
                    if (!result.error) await this.loadTodos(true);
                    return;
                }
                if (result.error) { this.formError = result.error; return; }
                this.drawerRefreshReq++;
                this.drawerRefreshError = false;
                this.drawer = result;
                this.editing = null;
                this.$nextTick?.(() => this.$refs.inlineEditTrigger?.focus());
                await this.loadTodos();
                this.toast(this.t('toast.saved'));
            } finally {
                this.savingForm = false;
            }
        },

        async loadDrawerData(id) {
            const req = this.drawerReq;
            this.drawerLoading = true;
            const [comments, shares, files] = await Promise.all([
                this.api('GET', 'comments', null, { todo_id: id }),
                this.drawer?.is_owner ? this.api('GET', 'shares', null, { todo_id: id }) : Promise.resolve([]),
                this.api('GET', 'get_files', null, { todo_id: id }),
            ]);
            if (req !== this.drawerReq) return;
            this.comments      = Array.isArray(comments) ? comments : [];
            this.shares        = Array.isArray(shares) ? shares : [];
            this.files         = Array.isArray(files) ? files : [];
            this.drawerLoading = false;
        },

        async uploadFile(event) {
            const input = event.target;
            if (!input.files.length || !this.drawer) return;
            const req = this.drawerReq;
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
                if (req !== this.drawerReq) return;
                if (result.error) { this.uploadError = result.error; return; }
                result.uploader_email = result.uploader_email || '';
                this.files.push(result);
                input.value = '';
            } catch (e) {
                if (req === this.drawerReq) this.uploadError = 'Upload failed: ' + e.message;
            } finally {
                if (req === this.drawerReq) this.uploading = false;
            }
        },

        async deleteFile(id) {
            const req = this.drawerReq;
            if (!confirm(this.t('files.delete_confirm'))) return;
            const r = await this.api('POST', 'delete_file', { id });
            if (!r.error && req === this.drawerReq) this.files = this.files.filter(f => f.id !== id);
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
            if (!body || !this.drawer || this.commentPosting) return;
            const id = this.drawer.id;
            const req = this.drawerReq;
            this.commentPosting = true;
            this.commentError = '';
            try {
                const comment = await this.api('POST', 'add_comment', { todo_id: id, body });
                if (!comment.error) this.updateCommentCount(id);
                if (req !== this.drawerReq) return;
                if (comment.error) { this.commentError = comment.error; return; }
                this.comments.push(comment);
                this.closeComment();
            } finally {
                if (req === this.drawerReq) this.commentPosting = false;
            }
        },
        closeComment() {
            this.newComment = '';
            this.commentOpen = false;
            this.commentError = '';
            this.$nextTick?.(() => this.$refs.commentTrigger?.focus());
        },

        async addShare() {
            this.shareError = '';
            const email = this.shareEmail.trim();
            if (!email || !this.drawer) return;
            const req = this.drawerReq;
            const result = await this.api('POST', 'add_share', { todo_id: this.drawer.id, email });
            if (req !== this.drawerReq) return;
            if (result.error) { this.shareError = result.error; return; }
            this.shares.push(result);
            this.shareEmail = '';
        },

        async removeShare(userId) {
            if (!this.drawer) return;
            const req = this.drawerReq;
            const result = await this.api('POST', 'remove_share', { todo_id: this.drawer.id, user_id: userId });
            if (!result.error && req === this.drawerReq) this.shares = this.shares.filter(s => s.user_id !== userId);
        },

        updateCommentCount(id) {
            const task = this.todos.flatMap(todo => [todo, ...(todo.children || [])]).find(todo => todo.id === id);
            if (task) task.comment_count = (task.comment_count || 0) + 1;
            if (this.drawer?.id === id && this.drawer !== task) {
                this.drawer.comment_count = (this.drawer.comment_count || 0) + 1;
            }
        },

        dismissWorkspaceOverlay() {
            if (this.showSettings) this.showSettings = false;
            else if (this.modal) this.closeModal();
            else if (this.editing) this.cancelInlineEdit();
            else if (this.commentOpen && !this.commentPosting) this.closeComment();
            else if (this.subtaskOpen) { this.subtaskOpen = false; this.newSubtask = ''; }
            else if (this.drawer) this.closeDrawer();
        },

        // ── Complete / Delete ─────────────────────────────────
        async addSubtask() {
            const title = (this.newSubtask || '').trim();
            if (!title || !this.drawer) return;
            const id = this.drawer.id;
            const req = this.drawerReq;
            const r = await this.api('POST', 'create_todo', {
                title,
                parent_id: id,
                priority: this.drawer.priority || 4,
            });
            if (r.error) { this.toast(r.error); return; }
            if (req === this.drawerReq) {
                this.newSubtask = '';
                this.subtaskOpen = false;
                await this.refreshOpenDrawer();
                this.$nextTick?.(() => this.$refs.subtaskTrigger?.focus());
            }
            await this.loadTodos();
        },

        hideCompletedInView() {
            if (this.searchQuery.trim()) return false;
            return this.filterStatus !== 'completed' && !(this.filterStatus === 'all' && !this.hideCompleted);
        },
        removeTodoFromList(id) {
            const nid = Number(id);
            this.todos = this.todos.filter(t => Number(t.id) !== nid);
            this.todos.forEach(t => {
                if (t.children?.length) {
                    t.children = t.children.filter(c => Number(c.id) !== nid);
                }
            });
        },
        prefersReducedMotion() {
            return typeof window !== 'undefined'
                && window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },
        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
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
                if (this.drawer?.id === todo.id) this.closeDrawer();
                await this.loadTodos(true);
                return;
            }
            const result = await this.api('POST', 'complete_todo', { id: todo.id });
            if (result.error) {
                this.toast(result.error);
                return;
            }
            this.offerUndo(todo.id, result.next_todo);
            this.toast(result.next_todo ? this.t('toast.next') : this.t('toast.completed'), 10000, true);
            todo.completed_at = todo.completed_at || new Date().toISOString().slice(0, 19).replace('T', ' ');
            const hide = this.hideCompletedInView();
            if (hide) {
                this.leavingId = todo.id;
                if (!this.prefersReducedMotion()) await this.sleep(400);
                this.removeTodoFromList(todo.id);
                this.leavingId = null;
            }
            if (this.drawer?.id === todo.id) this.closeDrawer();
            await this.loadTodos(true);
        },

        async toggleDrawerComplete(e) {
            if (!this.drawer || !this.drawer.is_owner || this.completingDrawer) return;
            this.completingDrawer = true;
            try {
                await this.toggleComplete(this.drawer, e);
            } finally {
                this.completingDrawer = false;
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
            if (this.drawer) await this.refreshOpenDrawer();
        },

        async deleteTodo(id) {
            if (this.deletingTodo || !confirm(this.t('confirm.delete_todo'))) return;
            this.deletingTodo = true;
            try {
                const result = await this.api('POST', 'delete_todo', { id });
                if (!result.ok) {
                    this.toast(result.error || this.t('error.request_failed'), 6000);
                    return;
                }
                this.removeTodoFromList(id);
                if (this.drawer?.id === id) this.closeDrawer();
                this.toast(this.t('toast.deleted'));
                await this.loadTodos(true);
            } finally {
                this.deletingTodo = false;
            }
        },

        // ── Tags management ───────────────────────────────────
        async deleteTag(id) {
            if (!confirm(this.t('confirm.delete_tag'))) return;
            const result = await this.api('POST', 'delete_tag', { id });
            if (!result.ok) {
                this.toast(result.error || this.t('error.request_failed'), 6000);
                return;
            }
            this.tags = this.tags.filter(t => t.id !== id);
            if (this.filterTagId === id) { this.filterTagId = null; }
            await this.loadTodos();
        },

        // ── Settings ──────────────────────────────────────────
        setAccent(accent) {
            this.accentTheme = accent === 'blue' ? 'blue' : 'evergreen';
            try { localStorage.setItem('mytasks-accent', this.accentTheme); } catch { /* Optional persistence. */ }
        },

        applyAccountStatus(s) {
            if (!s || s.error) return;
            this.telegramLinked     = !!s.telegram_linked;
            this.telegramConfigured = !!s.telegram_configured;
            this.emailVerified      = !!s.email_verified;
        },
        async refreshTelegramStatus() {
            const s = await this.api('GET', 'settings');
            this.applyAccountStatus(s);
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
                display_name:      this.settingsDisplayName.trim(),
            });
            if (r.error) { this.settingsError = r.error; return; }
            if (this.settingsLocale && this.settingsLocale !== window.LOCALE) {
                location.reload();
                return;
            }
            this.toast(this.t('settings.saved'));
        },

        async sendEmailVerification() {
            if (this.emailVerificationBusy) return;
            this.emailVerificationBusy = true;
            const result = await this.api('POST', 'verify_email');
            this.emailVerificationBusy = false;
            this.emailVerificationMsg = result.error || this.t('settings.email_verification_sent');
        },

        async changePassword() {
            this.pwError = ''; this.pwOk = false;
            if (this.pwNew !== this.pwNew2) { this.pwError = this.t('auth.password_mismatch'); return; }
            const r = await this.api('POST', 'change_password', { current: this.pwCurrent, new: this.pwNew });
            if (r.error) { this.pwError = r.error; return; }
            window.CSRF_TOKEN = r.csrf_token;
            this.pwCurrent = this.pwNew = this.pwNew2 = '';
            this.pwOk = true;
        },

        async logout() {
            await this.api('POST', 'logout');
            location.href = 'auth.php';
        },

        // ── Helpers ───────────────────────────────────────────
        toast(msg, ms = 3000, keepUndo = false, todo = null) {
            this.toastMsg = msg;
            this.toastTodo = todo;
            if (!keepUndo) this.clearUndo();
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.toastMsg = '';
                this.toastTodo = null;
                this.clearUndo();
            }, ms);
        },

        openToastTodo() {
            const todo = this.toastTodo;
            if (!todo) return;
            clearTimeout(this.toastTimer);
            this.toastMsg = '';
            this.toastTodo = null;
            return this.openDrawer(todo);
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

        userLabel(u) {
            const n = (u && u.display_name) ? String(u.display_name).trim() : '';
            return (u && u.label) || n || (u && u.email) || '';
        },

        // Opt-in tokens: <datetime> or "datetime", <+email>, +Name, #tag, p1–p4.
        parseTagsInTitle(commit = false) {
            const re = /<(\+?)([^>]+)>|["“„](\+?)([^"“”„]+)["“”]/g;
            let newTitle = this.form.title;
            let match;
            while ((match = re.exec(this.form.title)) !== null) {
                const plus = match[0].startsWith('<') ? match[1] : (match[3] || '');
                const inner = match[0].startsWith('<') ? match[2] : (match[4] || '');
                if (plus === '+') {
                    const who = inner.trim();
                    if (who && !this.form.share_emails.includes(who)) {
                        this.form.share_emails.push(who);
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

            const plus = commit ? /(?:^|\s)\+([^\s+]+)(?=\s|$)/g : /(?:^|\s)\+([^\s+]+)(?=\s)/g;
            newTitle = newTitle.replace(plus, (_, who) => {
                if (!this.form.share_emails.includes(who)) this.form.share_emails.push(who);
                return ' ';
            });
            if (newTitle !== this.form.title && this.form.share_emails.length) {
                newTitle = newTitle.replace(/  +/g, ' ').trim();
            }

            if (newTitle !== this.form.title) this.form.title = newTitle;
            if (!commit) this.updateShareDropdown();
            else this.clearShareDropdown();
            this.updateTagDropdown();
        },

        clearShareDropdown() {
            if (this.shareLookupTimer !== null) clearTimeout(this.shareLookupTimer);
            this.shareLookupTimer = null;
            this.shareLookupId++;
            this.shareDropdown = [];
            this.shareDropdownIndex = -1;
        },

        updateShareDropdown() {
            this.clearShareDropdown();
            const title = this.form.title;
            const match = title.match(/<\+([^>]*)$/) || title.match(/(?:^|\s)\+([^\s+]*)$/);
            const query = match?.[1].trim();
            if (!query) return;
            const lookup = this.shareLookupId;
            this.shareLookupTimer = setTimeout(async () => {
                this.shareLookupTimer = null;
                const user = await this.api('GET', 'find_user', null, { q: query });
                if (lookup !== this.shareLookupId || this.form.title !== title || !user.label) return;
                if (this.form.share_emails.includes(user.label)) return;
                this.shareDropdown = [user];
                this.shareDropdownIndex = 0;
            }, 200);
        },

        onTitleKeydown(e) {
            if (e.isComposing) return;
            this.shareDropdownNav(e);
            if (e.key === 'Enter' && !e.defaultPrevented) {
                e.preventDefault();
                if (!e.repeat) return this.saveForm();
            }
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
                    this.selectShareUser(this.userLabel(this.shareDropdown[i]));
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
        selectShareUser(who) {
            this.form.title = this.form.title
                .replace(/<\+[^>]*$/, '')
                .replace(/(?:^|\s)\+[^\s+]*$/, ' ')
                .replace(/\s+/g, ' ').trim();
            if (who && !this.form.share_emails.includes(who)) {
                this.form.share_emails.push(who);
            }
            this.clearShareDropdown();
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
