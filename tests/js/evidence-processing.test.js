import { test } from 'node:test';
import assert from 'node:assert/strict';
import { installEvidenceProcessing } from '../../resources/js/evidence-processing.js';

function setup(t, count = 1) {
    const saved = Object.fromEntries(['document', 'location', 'fetch', 'DOMParser', 'setInterval'].map(k => [k, globalThis[k]]));
    let tick;
    const nodes = Array.from({ length: count }, (_, i) => ({
        dataset: { processingKey: 'mov:batch', evidenceStatus: `/evidence/mov/${i + 1}/status` }, isConnected: true,
        replaceWith(value) { this.isConnected = false; this.replacement = value; },
    }));
    globalThis.document = { hidden: false, querySelectorAll: () => nodes.filter(n => n.isConnected) };
    globalThis.location = { href: 'https://example.test/movs/1' };
    globalThis.DOMParser = class { parseFromString(html) { return { body: { firstElementChild: { html } } }; } };
    globalThis.setInterval = callback => { tick = callback; return 1; };
    const calls = [];
    globalThis.fetch = async (url, options) => {
        calls.push({ url, options });
        return { ok: true, redirected: false, json: async () => ({ status: 'queued', html: null }) };
    };
    t.after(() => Object.assign(globalThis, saved));
    installEvidenceProcessing();
    return { nodes, calls, tick: () => tick() };
}

test('polls once per pending batch and preserves cards while processing', async t => {
    const { nodes, calls, tick } = setup(t, 3);
    await tick();
    await tick();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].options.credentials, 'same-origin');
    assert.ok(nodes.every(n => n.isConnected));
});

test('updates each completed batch card without replacing other page content', async t => {
    const { nodes, calls, tick } = setup(t, 2);
    globalThis.fetch = async url => {
        calls.push(url);
        return { ok: true, redirected: false, json: async () => ({ status: 'completed', html: `<section>${url}</section>` }) };
    };
    await tick();
    assert.equal(calls.length, 2);
    assert.ok(nodes.every(n => !n.isConnected));
    assert.match(nodes[0].replacement.html, /\/1\/status/);
    assert.match(nodes[1].replacement.html, /\/2\/status/);
});

test('does not poll hidden pages or discard pending cards on network failure', async t => {
    const { nodes, calls, tick } = setup(t);
    document.hidden = true;
    await tick();
    assert.equal(calls.length, 0);
    document.hidden = false;
    globalThis.fetch = async () => { throw new Error('offline'); };
    await tick();
    assert.equal(nodes[0].isConnected, true);
});

test('ignores responses after navigation and does not apply login redirects', async t => {
    const { nodes, tick } = setup(t);
    globalThis.fetch = async () => ({ ok: true, redirected: false, json: async () => {
        location.href = 'https://example.test/reports';
        return { status: 'completed', html: '<section>Result</section>' };
    } });
    await tick();
    assert.equal(nodes[0].isConnected, true);
});
