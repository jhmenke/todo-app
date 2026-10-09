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
    const foreground = app.loadTodos();
    const background = app.loadTodos(true);
    requests[1].resolve({ error: 'Request failed' });
    await background;
    requests[0].resolve([]);
    await foreground;

    assert.equal(app.loading, false);
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
