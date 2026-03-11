const COLORS = ['#6366f1','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#8b5cf6','#14b8a6','#f97316','#64748b'];
const DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

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
        sunday: 0, sonntag: 0, monday: 1, montag: 1,
        tuesday: 2, dienstag: 2, wednesday: 3, mittwoch: 3,
        thursday: 4, donnerstag: 4, friday: 5, freitag: 5,
        saturday: 6, samstag: 6,
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
    } else if (match = s.match(/\b(sunday|sonntag|monday|montag|tuesday|dienstag|wednesday|mittwoch|thursday|donnerstag|friday|freitag|saturday|samstag)\b/)) {
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

    // ── 4. Bare number → treat as hour ───────────────────────────
    s = s.replace(/\s+/g, ' ').trim();
    if (h === null && (match = s.match(/\b(\d{1,2})\b/))) {
        const n = parseInt(match[1]);
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
        filterStatus:   'pending',
        sortBy:         'active_at',
        sortDir:        'asc',
        hideCompleted:  false,
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
        form:          { title: '', active_date: '', active_time: '', tag_ids: [], recur_type: '', recur_interval: 1, recur_days: [], recur_ends_at: '', share_emails: [] },
        formError:     '',
        savingForm:    false,
        newTagName:    '',
        newTagColor:   '#6366f1',
        showTagForm:   false,
        shareDropdown: [],      // filtered users shown while typing <+
        shareDropdownIndex: -1, // keyboard-highlighted index in shareDropdown

        // ── Settings modal ────────────────────────────────────
        showSettings:    false,
        settingsMinutes: 5,
        settingsError:   '',
        pwCurrent:   '',
        pwNew:       '',
        pwNew2:      '',
        pwError:     '',
        pwOk:        false,

        // ── Toast ─────────────────────────────────────────────
        toastMsg: '',
        toastTimer: null,

        // ── Init ──────────────────────────────────────────────
        async init() {
            await Promise.all([this.loadTags(), this.loadTodos()]);
            const [s, users] = await Promise.all([
                this.api('GET', 'settings'),
                this.api('GET', 'get_users'),
            ]);
            if (s.notify_minutes) this.settingsMinutes = s.notify_minutes;
            this.allUsers = Array.isArray(users) ? users : [];
        },

        // ── API helper ────────────────────────────────────────
        async api(method, action, body = null, params = {}) {
            try {
                const qs = new URLSearchParams({ action, ...params }).toString();
                const opts = { method, headers: { 'X-Requested-With': 'XMLHttpRequest' } };
                if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
                const res = await fetch(`api.php?${qs}`, opts);
                if (res.status === 401) { location.href = 'auth.php'; return { error: 'Unauthenticated' }; }
                return await res.json();
            } catch (e) {
                return { error: 'Request failed: ' + e.message };
            }
        },

        // ── Load ──────────────────────────────────────────────
        async loadTodos() {
            this.loading = true;
            try {
                const params = { status: this.filterStatus, sort: this.sortBy, dir: this.sortDir };
                if (this.filterTagId) params.tag_id = this.filterTagId;
                if (this.filterStatus === 'all' && this.hideCompleted) params.hide_completed = '1';
                if (this.filterStatus === 'completed') {
                    if (this.completedFrom) params.completed_from = this.completedFrom;
                    if (this.completedTo)   params.completed_to   = this.completedTo;
                }
                const data = await this.api('GET', 'todos', null, params);
                this.todos = Array.isArray(data) ? data : [];
            } finally {
                this.loading = false;
            }
        },

        async loadTags() {
            const data = await this.api('GET', 'tags');
            this.tags = Array.isArray(data) ? data : [];
        },

        // ── Filters ───────────────────────────────────────────
        setStatus(s) {
            this.filterStatus = s;
            this.loadTodos();
        },
        setTag(id) {
            this.filterTagId = (this.filterTagId === id) ? null : id;
            this.loadTodos();
        },
        setSort(field) {
            if (this.sortBy === field) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy  = field;
                this.sortDir = field === 'title' ? 'asc' : 'asc';
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
                share_emails: [],
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
            if (!this.form.title.trim()) { this.formError = 'Title is required.'; return; }
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
                    ? ` Shared with ${this.form.share_emails.length}.` : '';
                const shareFailNote = shareErrors.length
                    ? ` Share failed for: ${shareErrors.join(', ')}.` : '';

                if (at && at <= now && !result.completed_at && this.filterStatus === 'pending') {
                    this.filterStatus = 'active';
                    this.toast((isCreate ? 'Created' : 'Saved') + ' — visible in Active now.' + sharedNote + shareFailNote);
                } else if (result.completed_at && this.filterStatus !== 'completed') {
                    this.filterStatus = 'completed';
                    this.toast('Saved — visible in Completed.' + sharedNote + shareFailNote);
                } else {
                    this.toast((isCreate ? 'Created.' : 'Saved.') + sharedNote + shareFailNote);
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
                this.newTagColor = '#6366f1';
                this.showTagForm = false;
            }
        },

        // ── Drawer ────────────────────────────────────────────
        async openDrawer(todo) {
            this.drawer      = todo;
            this.drawerTab   = 'comments';
            this.newComment  = '';
            this.shareEmail  = '';
            this.shareError  = '';
            this.uploadError = '';
            this.files       = [];
            this.editing     = false;
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
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
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
            if (!confirm('Delete this file?')) return;
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
        async completeTodo(todo, e) {
            e.stopPropagation();
            const result = await this.api('POST', 'complete_todo', { id: todo.id });
            if (result.error) return;
            if (result.next_todo) {
                this.todos = this.todos.map(t => t.id === todo.id ? result.next_todo : t);
                this.toast('Done — next occurrence scheduled.');
            } else {
                this.todos = this.todos.filter(t => t.id !== todo.id);
                this.toast('Completed.');
            }
            if (this.drawer?.id === todo.id) this.closeDrawer();
        },

        async deleteTodo(id) {
            if (!confirm('Delete this todo?')) return;
            await this.api('POST', 'delete_todo', { id });
            this.todos = this.todos.filter(t => t.id !== id);
            if (this.drawer?.id === id) this.closeDrawer();
            this.toast('Deleted.');
        },

        // ── Tags management ───────────────────────────────────
        async deleteTag(id) {
            if (!confirm('Delete this tag? It will be removed from all todos.')) return;
            await this.api('POST', 'delete_tag', { id });
            this.tags = this.tags.filter(t => t.id !== id);
            if (this.filterTagId === id) { this.filterTagId = null; }
            await this.loadTodos();
        },

        // ── Settings ──────────────────────────────────────────
        async saveSettings() {
            this.settingsError = '';
            const r = await this.api('POST', 'update_settings', { notify_minutes: parseInt(this.settingsMinutes) });
            if (r.error) { this.settingsError = r.error; return; }
            this.toast('Settings saved.');
        },

        async changePassword() {
            this.pwError = ''; this.pwOk = false;
            if (this.pwNew !== this.pwNew2) { this.pwError = 'Passwords do not match.'; return; }
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
        toast(msg) {
            this.toastMsg = msg;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toastMsg = ''; }, 3000);
        },

        formatDate(dt) {
            if (!dt) return '';
            const d = new Date(dt.replace(' ', 'T'));
            const now = new Date();
            const diff = d - now;
            if (diff < 0 && diff > -86400000) return 'Active now';
            const opts = { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            if (d.getFullYear() !== now.getFullYear()) opts.year = 'numeric';
            return d.toLocaleDateString('en-US', opts);
        },

        formatDateShort(dt) {
            if (!dt) return '—';
            return new Date(dt.replace(' ', 'T')).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        recurLabel(todo) {
            if (!todo.recur_type) return '';
            const map = { daily: 'Daily', weekly: 'Weekly', monthly: 'Monthly', custom: `Every ${todo.recur_interval}d` };
            return map[todo.recur_type] || '';
        },

        // Called on every title input — strips <datetime> and <+email> tags, fills fields
        parseTagsInTitle() {
            const re = /<(\+?)([^>]+)>/g;
            let newTitle = this.form.title;
            let match;
            while ((match = re.exec(this.form.title)) !== null) {
                if (match[1] === '+') {
                    // Share tag: <+email>
                    const email = match[2].trim();
                    if (email && !this.form.share_emails.includes(email)) {
                        this.form.share_emails.push(email);
                    }
                    newTitle = newTitle.replace(match[0], '').replace(/  +/g, ' ').trim();
                } else {
                    // Datetime tag
                    const parsed = parseDateTag(match[2]);
                    if (parsed) {
                        if (parsed.date) this.form.active_date = parsed.date;
                        if (parsed.time) this.form.active_time = parsed.time;
                        newTitle = newTitle.replace(match[0], '').replace(/  +/g, ' ').trim();
                    }
                }
            }
            if (newTitle !== this.form.title) this.form.title = newTitle;
            this.updateShareDropdown();
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
            this.shareDropdownIndex = -1;
        },

        shareDropdownNav(e) {
            if (this.shareDropdown.length === 0) return;
            if (e.key === 'ArrowDown' || e.key === 'Tab') {
                e.preventDefault();
                this.shareDropdownIndex = (this.shareDropdownIndex + 1) % this.shareDropdown.length;
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.shareDropdownIndex = (this.shareDropdownIndex - 1 + this.shareDropdown.length) % this.shareDropdown.length;
            } else if (e.key === 'Enter' && this.shareDropdownIndex >= 0) {
                e.preventDefault();
                this.selectShareUser(this.shareDropdown[this.shareDropdownIndex].email);
            }
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

        dayLabel(n) { return DAYS[n]; },
        colors() { return COLORS; },
        days() { return [0,1,2,3,4,5,6]; },

        statusCounts() {
            // Not computed server-side; just used for label
            return { all: '', pending: '', active: '', completed: '' };
        },
    };
}
