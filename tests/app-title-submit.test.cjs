const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const { runInNewContext } = require('node:vm');

const source = readFileSync(require.resolve('../js/app.js'), 'utf8');

function createApp() {
    const app = runInNewContext(`${source}\ntodoApp();`, { window: {} });
    app.t = key => key;
    app.toast = () => {};
    app.loadTodos = async () => {};
    app.openCreate();
    app.form.title = 'New task';
    return app;
}

function enter(overrides = {}) {
    return {
        key: 'Enter',
        defaultPrevented: false,
        preventDefault() { this.defaultPrevented = true; },
        ...overrides,
    };
}

test('Enter creates the task with its assignments and closes the dialog', async () => {
    const app = createApp();
    app.form.tag_ids = [3];
    app.form.active_date = '2026-10-10';
    app.form.active_time = '14:30';
    app.api = async (method, action, payload) => {
        assert.equal(method, 'POST');
        assert.equal(action, 'create_todo');
        assert.equal(payload.title, 'New task');
        assert.equal(payload.tag_ids[0], 3);
        assert.equal(payload.active_at, '2026-10-10 14:30:00');
        return { id: 1, ...payload };
    };
    const event = enter();
    await app.onTitleKeydown(event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(app.modal, null);
    assert.equal(app.savingForm, false);
});

for (const dropdown of ['tag', 'share']) {
    test(`Enter selects a ${dropdown} suggestion before submitting`, async () => {
        const app = createApp();
        app.api = async () => { assert.fail('Selecting a suggestion must not save'); };
        if (dropdown === 'tag') {
            app.form.title = 'New task #work';
            app.tagDropdown = [{ id: 3, name: 'work' }];
        } else {
            app.form.title = 'New task +Peter';
            app.shareDropdown = [{ id: 2, display_name: 'Peter' }];
        }
        await app.onTitleKeydown(enter());
        assert.equal(app.modal, 'create');
        assert.equal(app.form.title, 'New task');
        if (dropdown === 'tag') assert.equal(app.form.tag_ids[0], 3);
        else assert.equal(app.form.share_emails[0], 'Peter');
    });
}

test('Enter keeps the dialog open for an empty title or a save error', async () => {
    const app = createApp();
    app.form.title = '   ';
    app.api = async () => { assert.fail('An empty title must not be submitted'); };
    await app.onTitleKeydown(enter());
    assert.equal(app.modal, 'create');
    assert.equal(app.formError, 'form.title_required');

    app.form.title = 'New task';
    app.api = async () => ({ error: 'Save failed' });
    await app.onTitleKeydown(enter());
    assert.equal(app.modal, 'create');
    assert.equal(app.formError, 'Save failed');
    assert.equal(app.savingForm, false);
});

test('repeated Enter presses cannot submit the same task twice', async () => {
    const app = createApp();
    let calls = 0;
    let finish;
    app.api = () => {
        calls++;
        return new Promise(resolve => { finish = resolve; });
    };
    const pending = app.onTitleKeydown(enter());
    await app.onTitleKeydown(enter());
    await app.onTitleKeydown(enter({ repeat: true }));
    assert.equal(calls, 1);
    finish({ id: 1, title: 'New task' });
    await pending;
    assert.equal(app.modal, null);
});

test('Enter during text composition does not select a suggestion or save', async () => {
    const app = createApp();
    app.tagDropdown = [{ id: 3, name: 'work' }];
    app.api = async () => { assert.fail('Text composition must not save'); };
    const event = enter({ isComposing: true });
    await app.onTitleKeydown(event);
    assert.equal(event.defaultPrevented, false);
    assert.equal(app.form.tag_ids.length, 0);
    assert.equal(app.modal, 'create');
});
