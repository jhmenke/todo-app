const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const { runInNewContext } = require('node:vm');

const source = readFileSync(require.resolve('../js/app.js'), 'utf8');

async function createApp(overrides = {}) {
    const app = runInNewContext(`${source}\ntodoApp();`, { window: {}, URLSearchParams, confirm: () => true });
    const task = { id: 7, title: 'Task from Telegram', tags: [], is_owner: true, ...overrides };
    const calls = [];
    app.t = key => key;
    app.toast = message => { app.lastToast = message; };
    app.prefersReducedMotion = () => true;
    app.api = async (method, action, body) => {
        calls.push({ method, action, body });
        if (action === 'todo') return task;
        if (action === 'complete_todo') return { completed: true, next_todo: { id: 8 } };
        if (action === 'uncomplete_todo') return { ...task, completed_at: null };
        return [];
    };
    await app.openDrawer(task);
    return { app, task, calls };
}

const click = () => ({ preventDefault() {}, stopPropagation() {} });

test('a task opened directly can be completed without appearing in the current list', async () => {
    const { app, task, calls } = await createApp();
    assert.equal(app.todos.length, 0);
    await app.toggleDrawerComplete(click());
    const completion = calls.find(call => call.action === 'complete_todo');
    assert.equal(completion.method, 'POST');
    assert.equal(completion.body.id, task.id);
    assert.equal(app.drawer, null);
    assert.equal(app.undoId, task.id);
    assert.equal(app.undoNextId, 8);
    assert.equal(app.loading, false);
    assert.equal(app.completingDrawer, false);
});

test('a completed task can be marked incomplete from its detail view', async () => {
    const { app, task, calls } = await createApp({ completed_at: '2026-10-09 12:00:00' });
    await app.toggleDrawerComplete(click());
    assert.equal(calls.find(call => call.action === 'uncomplete_todo').body.id, task.id);
    assert.equal(app.lastToast, 'toast.incomplete');
    assert.equal(app.drawer, null);
    assert.equal(app.completingDrawer, false);
});

test('repeated clicks submit only once and a failed completion keeps the task open', async () => {
    const { app, task } = await createApp();
    let calls = 0;
    let finish;
    app.api = () => {
        calls++;
        return new Promise(resolve => { finish = resolve; });
    };
    const pending = app.toggleDrawerComplete(click());
    await app.toggleDrawerComplete(click());
    assert.equal(calls, 1);
    assert.equal(app.completingDrawer, true);
    finish({ error: 'Completion failed' });
    await pending;
    assert.equal(app.drawer.id, task.id);
    assert.equal(app.drawer.completed_at, undefined);
    assert.equal(app.lastToast, 'Completion failed');
    assert.equal(app.completingDrawer, false);
});

test('shared tasks cannot be completed by a non-owner', async () => {
    const { app } = await createApp({ is_owner: false });
    app.api = async () => { assert.fail('Only the owner may complete the task'); };
    await app.toggleDrawerComplete(click());
    assert.equal(app.completingDrawer, false);
    assert.ok(app.drawer);
});

test('late comments from the previous selection do not appear on the open task', async () => {
    const { app, task } = await createApp();
    const next = { ...task, id: 9, title: 'Another task' };
    let notifyStarted;
    let finish;
    const started = new Promise(resolve => { notifyStarted = resolve; });
    app.api = async (method, action, body, params) => {
        if (action === 'todo') return params.id === task.id ? task : next;
        if (action === 'comments' && params.todo_id === task.id) {
            return new Promise(resolve => { finish = resolve; notifyStarted(); });
        }
        if (action === 'comments') return [{ id: 2, body: 'Comment on the open task' }];
        return [];
    };
    const pending = app.openDrawer(task);
    await started;
    await app.openDrawer(next);
    finish([{ id: 1, body: 'Comment on the previous task' }]);
    await pending;

    assert.equal(app.drawer.id, next.id);
    assert.equal(app.comments[0].id, 2);
    assert.equal(app.drawerLoading, false);
});

test('a temporary refresh failure keeps the open task and its draft', async () => {
    const { app, task } = await createApp();
    app.newComment = 'Unsaved comment';
    app.api = async () => ({ status: 503, error: 'Unavailable' });
    await app.refreshOpenDrawer();
    assert.equal(app.drawer.id, task.id);
    assert.equal(app.drawerRefreshError, true);
    assert.equal(app.newComment, 'Unsaved comment');

    app.api = async () => task;
    await app.refreshOpenDrawer();
    assert.equal(app.drawerRefreshError, false);

    app.api = async () => ({ status: 404, error: 'Not found' });
    await app.refreshOpenDrawer();
    assert.equal(app.drawer, null);
});

test('deletion failure keeps the task in the list and in the drawer', async () => {
    const { app, task } = await createApp();
    app.todos = [task];
    app.api = async () => ({ status: 500, error: 'Delete failed' });
    await app.deleteTodo(task.id);
    assert.equal(app.lastToast, 'Delete failed');
    assert.equal(app.todos[0].id, task.id);
    assert.equal(app.drawer.id, task.id);
    assert.equal(app.deletingTodo, false);
});
