const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const { runInNewContext } = require('node:vm');

const source = readFileSync(require.resolve('../js/app.js'), 'utf8');

async function createApp(overrides = {}) {
    const app = runInNewContext(`${source}\ntodoApp();`, { window: {}, URLSearchParams });
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
