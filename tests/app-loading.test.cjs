const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const { runInNewContext } = require('node:vm');

const source = readFileSync(require.resolve('../js/app.js'), 'utf8');

function createApp() {
    const app = runInNewContext(`${source}\ntodoApp();`, { window: {} });
    const requests = [];
    const task = { id: 1, title: 'Keep this task visible', is_owner: true, tags: [] };
    app.todos = [task];
    app.api = async (method, action) => {
        if (action === 'todos') {
            return new Promise((resolve, reject) => requests.push({ resolve, reject }));
        }
        if (action === 'todo') return task;
        return [];
    };
    return { app, requests, task };
}

for (const order of ['foreground first', 'background first']) {
    test(`closing a task keeps the list visible when overlapping loads finish ${order}`, async () => {
        const { app, requests, task } = createApp();
        const foreground = app.loadTodos();
        await app.openDrawer(task);
        const background = app.loadTodos(true);
        app.closeDrawer();

        if (order === 'foreground first') {
            requests[0].resolve([{ id: 2, title: 'Stale response' }]);
            await foreground;
            assert.equal(app.loading, true);
            requests[1].resolve([task]);
            await background;
        } else {
            requests[1].resolve([task]);
            await background;
            requests[0].resolve([{ id: 2, title: 'Stale response' }]);
            await foreground;
        }

        assert.equal(app.drawer, null);
        assert.equal(app.loading, false);
        assert.equal(app.todos.length, 1);
        assert.equal(app.todos[0].id, task.id);
    });
}

test('a failed background refresh releases loading and preserves the existing list', async () => {
    const { app, requests, task } = createApp();
    const initial = app.loadTodos();
    requests.shift().resolve([task]);
    await initial;
    const foreground = app.loadTodos();
    const background = app.loadTodos(true);
    requests[1].resolve({ error: 'Request failed' });
    await background;
    requests[0].resolve([]);
    await foreground;

    assert.equal(app.loading, false);
    assert.equal(app.listError, true);
    assert.equal(app.showsLoadedList(), true);
    assert.equal(app.todos[0].id, task.id);
});

test('an older request cannot release loading for a newer foreground request', async () => {
    const { app, requests, task } = createApp();
    const older = app.loadTodos(true);
    const newer = app.loadTodos();
    requests[0].resolve([]);
    await older;
    assert.equal(app.loading, true);
    assert.equal(app.todos[0].id, task.id);

    requests[1].resolve([]);
    await newer;
    assert.equal(app.loading, false);
    assert.equal(app.todos.length, 0);
});

test('the schedule uses the server date and groups subtasks by their own dates', async () => {
    const { app } = createApp();
    app.filterStatus = 'scheduled';
    const task = (id, fields = {}) => ({ id, title: 'Task ' + id, ...fields });
    app.api = async (method, action, body, params) => {
        assert.equal(action, 'todos');
        assert.equal(params.status, 'scheduled');
        assert.equal(params.include_summary, '1');
        return {
            summary: { local_date: '2026-11-21', scheduled: 4, scheduled_today: 2, overdue: 1, completed: 1 },
            todos: [
                task(1, {
                    active_at: '2026-11-21 08:00:00',
                    children: [
                        task(2, { active_at: '2026-11-21 09:00:00' }),
                        task(3, { active_at: '2026-11-22 09:00:00' }),
                    ],
                }),
                task(4, { active_at: '2026-11-20 09:00:00' }),
                task(5, { completed_at: '2026-11-21 10:00:00' }),
            ],
        };
    };
    await app.loadTodos();

    const groups = app.taskGroups();
    assert.equal(app.summary.scheduled_today, 2);
    assert.deepEqual(Array.from(groups, group => group.key), ['overdue', '2026-11-21', '2026-11-22', 'completed']);
    assert.deepEqual(Array.from(groups[1].tasks, item => item.task.id), [1, 2]);
    assert.equal(groups[1].tasks[1].nested, true);
    assert.equal(groups[2].label, 'nav.tomorrow');
    assert.equal(groups[2].tasks[0].parentTitle, 'Task 1');
    assert.equal(groups[2].tasks[0].nested, false);
});

test('overview has separate overdue and today sections even with an empty list', async () => {
    const { app } = createApp();
    app.api = async (method, action, body, params) => {
        assert.equal(params.status, 'today');
        return { todos: [], summary: { local_date: '2026-11-21' } };
    };
    await app.loadTodos();
    assert.deepEqual(Array.from(app.taskGroups(), group => group.key), ['overdue', '2026-11-21']);
    assert.ok(app.taskGroups().every(group => group.tasks.length === 0));
});

test('search ignores the active view and tag, and clearing it restores both', async () => {
    const { app } = createApp();
    const scopes = [];
    app.filterStatus = 'all';
    app.filterTagId = 4;
    app.searchQuery = ' Tickets ';
    app.api = async (method, action, body, params) => {
        scopes.push(params);
        return [];
    };
    await app.loadTodos();
    assert.equal(scopes[0].status, 'all');
    assert.equal(scopes[0].q, 'Tickets');
    assert.equal(scopes[0].tag_id, undefined);
    assert.equal(scopes[0].hide_completed, undefined);
    assert.equal(app.hideCompletedInView(), false);

    await app.clearSearch();
    assert.equal(scopes[1].tag_id, 4);
    assert.equal(scopes[1].hide_completed, '1');
    assert.equal(scopes[1].q, undefined);
});

test('failed search does not show results from another view and retry clears the error', async () => {
    const { app, task, requests } = createApp();
    const initial = app.loadTodos();
    requests.shift().resolve([task]);
    await initial;
    app.searchQuery = 'tickets';
    const search = app.loadTodos();
    requests.shift().resolve({ error: 'Offline' });
    await search;
    assert.equal(app.listError, true);
    assert.equal(app.showsLoadedList(), false);

    const retry = app.loadTodos();
    requests.shift().resolve([]);
    await retry;
    assert.equal(app.listError, false);
    assert.equal(app.showsLoadedList(), true);
    assert.equal(app.todos.length, 0);
});
