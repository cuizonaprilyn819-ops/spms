import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installDashboardRefresh } from '../../resources/js/dashboard-refresh.js';

function harness(t) {
    const keys = ['document', 'location', 'setInterval', 'fetch', 'DOMParser'];
    const originals = new Map(keys.map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    t.after(() => originals.forEach((descriptor, key) => {
        if (descriptor) Object.defineProperty(globalThis, key, descriptor);
        else delete globalThis[key];
    }));
    let tick, calls = 0, replacements = 0;
    const main = { contains: () => false, replaceChildren: () => replacements++ };
    const marker = { closest: () => main, isConnected: true, textContent: 'last loaded' };
    globalThis.document = { hidden: false, activeElement: null, querySelector: () => marker };
    globalThis.location = { href: 'https://spms.test/dashboard' };
    globalThis.setInterval = (callback, delay) => { tick = callback; assert.equal(delay, 15000); };
    globalThis.fetch = async () => { calls++; return { ok: true, redirected: false, text: async () => '<main>Updated</main>' }; };
    globalThis.DOMParser = class { parseFromString() { return { querySelector: () => ({ closest: () => ({ childNodes: ['updated'] }) }) }; } };
    installDashboardRefresh({ mutateDom: callback => callback(), destroyTree() {}, initTree() {} });
    return { tick: () => tick(), marker, main, calls: () => calls, replacements: () => replacements };
}

test('refreshes dashboard content without replacing the page', async t => {
    const h = harness(t);
    await h.tick();
    assert.equal(h.calls(), 1);
    assert.equal(h.replacements(), 1);
});

test('skips hidden pages and focused dashboard controls', async t => {
    const h = harness(t);
    document.hidden = true;
    await h.tick();
    document.hidden = false;
    h.main.contains = () => true;
    await h.tick();
    assert.equal(h.calls(), 0);
});

test('keeps old data with a visible warning on connection failure', async t => {
    const h = harness(t);
    globalThis.fetch = async () => { throw new Error('Offline'); };
    await h.tick();
    assert.equal(h.replacements(), 0);
    assert.match(h.marker.textContent, /last loaded data/);
});

test('discards a response after navigating away', async t => {
    const h = harness(t);
    globalThis.fetch = async () => {
        location.href = 'https://spms.test/notifications';
        return { ok: true, text: async () => 'content' };
    };
    await h.tick();
    assert.equal(h.replacements(), 0);
});
