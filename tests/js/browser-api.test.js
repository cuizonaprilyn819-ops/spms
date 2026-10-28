import { test } from 'node:test';
import assert from 'node:assert/strict';
import { apiTarget, installBrowserApi } from '../../resources/js/browser-api.js';

test('maps navigation, filters and API pagination to canonical page URLs', () => {
    const result = apiTarget('https://spms.test/api/ipcrs?page=2', 'https://spms.test');
    assert.equal(result.api.href, 'https://spms.test/api/ipcrs?page=2');
    assert.equal(result.page.href, 'https://spms.test/ipcrs?page=2');
});

test('preserves subdirectory deployment paths', () => {
    const result = apiTarget('https://spms.test/spms/offices', 'https://spms.test/spms');
    assert.equal(result.api.href, 'https://spms.test/spms/api/offices');
});

test('leaves authentication, external links, files and unrelated paths to the browser', () => {
    for (const path of ['/login', '/logout', '/password', '/target-movs/1/preview', '/movs/1/download', '/reports/ipcr/pdf', '/reports/ipcr/excel', 'https://external.test/offices']) {
        assert.equal(apiTarget(new URL(path, 'https://spms.test').href, 'https://spms.test'), null);
    }
    assert.equal(apiTarget('https://spms.test/other/offices', 'https://spms.test/spms'), null);
});

function formHarness(t, response) {
    const original = new Map(['document', 'window', 'HTMLFormElement', 'FormData', 'fetch'].map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    t.after(() => {
        for (const [key, descriptor] of original) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        }
    });
    const listeners = {};
    const calls = [];
    const button = { disabled: false };
    class Form {
        action = 'https://spms.test/movs';
        method = 'post';
        target = '';
        isConnected = true;
        querySelectorAll() { return [button]; }
        querySelector() { return null; }
        prepend(box) { this.error = box.textContent; }
    }
    const form = new Form();
    globalThis.HTMLFormElement = Form;
    const NativeFormData = globalThis.FormData;
    globalThis.FormData = class extends NativeFormData { constructor() { super(); this.append('title', 'Test evidence'); } };
    globalThis.document = {
        querySelector(selector) {
            return { content: selector.includes('spms-base-url') ? 'https://spms.test' : 'csrf-test' };
        },
        addEventListener(name, handler) { listeners[name] = handler; },
        createElement() { return { dataset: {}, setAttribute() {}, focus() {} }; },
    };
    const destinations = [];
    const windowListeners = {};
    let reloads = 0;
    globalThis.window = {
        location: { pathname: '/movs', search: '', assign(url) { destinations.push(url); }, reload() { reloads++; } },
        addEventListener(name, handler) { windowListeners[name] = handler; },
    };
    globalThis.fetch = async (url, options) => { calls.push({ url: String(url), options }); return response; };
    installBrowserApi({});
    const event = {
        target: form, defaultPrevented: false,
        submitter: { formAction: 'https://spms.test/movs/create', hasAttribute() { return false; } },
        preventDefault() { this.defaultPrevented = true; },
    };
    return { submit: () => listeners.submit(event), popstate: () => windowListeners.popstate(), reloads: () => reloads, calls, destinations, form, button, event };
}

test('submits to the form action with session cookies and CSRF protection', async t => {
    const harness = formHarness(t, { ok: true, json: async () => ({ redirect: '/login' }) });
    await harness.submit();
    assert.equal(harness.event.defaultPrevented, true);
    assert.equal(harness.calls[0].url, 'https://spms.test/api/movs');
    assert.equal(harness.calls[0].options.method, 'POST');
    assert.equal(harness.calls[0].options.credentials, 'same-origin');
    assert.equal(harness.calls[0].options.headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.equal(harness.calls[0].options.body.get('title'), 'Test evidence');
    assert.deepEqual(harness.destinations, ['/login']);
    assert.equal(harness.button.disabled, false);
});

test('shows validation errors without replacing the form or retrying the write', async t => {
    const harness = formHarness(t, { ok: false, status: 422, json: async () => ({ errors: { file: ['Unsupported file type.'] } }) });
    await harness.submit();
    assert.equal(harness.form.error, 'Unsupported file type.');
    assert.equal(harness.form.isConnected, true);
    assert.equal(harness.calls.length, 1);
    assert.equal(harness.button.disabled, false);
    assert.deepEqual(harness.destinations, []);
});

test('section history keeps the page open while browser back to another page reloads it', t => {
    const harness = formHarness(t, {});
    window.location.hash = '#new-plan';
    harness.popstate();
    assert.equal(harness.reloads(), 0);
    window.location.pathname = '/planning';
    harness.popstate();
    assert.equal(harness.reloads(), 1);
});
