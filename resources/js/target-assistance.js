export function formValues(form) {
    const values = {};
    for (const [name, value] of new FormData(form)) {
        if (value instanceof File || name.startsWith('_') || name.startsWith('ai_assistance')) continue;
        const keys = name.replace(/\]/g, '').split('[');
        if (keys.some(k => ['__proto__', 'constructor', 'prototype'].includes(k))) continue;
        let node = values;
        keys.forEach((key, index) => {
            const last = index === keys.length - 1;
            if (last) {
                if (key === '' && Array.isArray(node)) node.push(value);
                else node[key] = value;
            } else node = node[key] ??= keys[index + 1] === '' ? [] : {};
        });
    }
    return values;
}

export function assistanceFields(path) {
    path = path.replace(/^\/api\//, '/').replace(/\/$/, '');
    if (/^\/historical-pcrs\/\d+\/corrections$/.test(path)) return ['message'];
    if (/^\/historical-pcrs\/\d+$/.test(path)) return ['snapshot.notes'];
    if (/^\/planning\/targets\/\d+\/outputs$/.test(path)) return ['details'];
    if (/^\/planning\/targets\/\d+\/update$/.test(path)) return ['target', 'success_indicator', 'local_target'];
    if (/^\/planning\/\d+\/(scope|targets)$/.test(path) || /^\/planning\/proposals(?:\/\d+\/resubmit)?$/.test(path)) return ['target', 'success_indicator', 'local_target'];
    if (/^\/(office-targets(?:\/\d+)?|target-drafts\/\d+\/assign)$/.test(path)) return ['title', 'success_indicator'];
    if (/^\/(opcrs|dpcrs|upcrs|ipcrs)$/.test(path) || /^\/report-workflow\/(opcr|dpcr|upcr|ipcr)\/\d+$/.test(path)) return ['title', 'strategic_program', 'program_outcome', 'success_indicators', 'targets'];
    if (/^\/accomplishments(?:\/\d+)?$/.test(path)) return ['details'];
    if (/^\/evidence\/(mov|target-mov|planning-output)\/\d+\/statement$/.test(path)) return ['statement'];
    return [];
}

export function installTargetAssistance() {
    let opener, active, original, protectedSnapshot;
    const config = () => document.querySelector('[data-target-assistance-config]');
    const dialog = () => document.querySelector('[data-target-assistance-dialog]');
    function close() { dialog()?.close(); opener?.focus(); }
    function clearMarker(field) { field.form?.querySelectorAll('[data-assistance-marker]').forEach(m => { if (m.dataset.field === field.name) m.remove(); }); }
    function scan(root = document) {
        if (!config()) return;
        root.querySelectorAll('form').forEach(form => {
            if ((form.method || 'get').toLowerCase() === 'get') return;
            const fields = assistanceFields(new URL(form.action).pathname);
            form.querySelectorAll('input[name], textarea[name]').forEach(field => {
                const name = field.name.replace(/\]/g, '').replace(/\[/g, '.');
                if (!fields.includes(name) && !(fields.includes('snapshot.notes') && /^snapshot\.notes\.\d+$/.test(name))) return;
                if (field.type === 'hidden' || field.readOnly || field.disabled || field.dataset.rephraseReady) return;
                field.dataset.rephraseReady = '1';
                const b = document.createElement('button'); b.type = 'button'; b.textContent = 'Rephrase with AI'; b.dataset.actionIcon = 'suggest'; b.dataset.rephraseButton = ''; b.className = 'is-secondary spms-rephrase-button';
                b.addEventListener('click', () => { opener = b; active = field; original = field.value; protectedSnapshot = JSON.stringify(formValues(form)); dialog().showModal(); preview(); });
                field.insertAdjacentElement('afterend', b);
                field.addEventListener('input', () => clearMarker(field));
            });
        });
    }
    async function preview() {
        const content = dialog().querySelector('[data-assistance-content]');
        content.textContent = 'Preparing a wording suggestion…'; dialog().setAttribute('aria-busy', 'true');
        try {
            if (!original.trim()) throw new Error('Enter the wording first.');
            const response = await fetch(config().dataset.preview, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ action: active.form.action, values: formValues(active.form), field: active.name.replace(/\]/g, '').replace(/\[/g, '.') }) });
            const type = response.headers.get('content-type') || '';
            const payload = type.includes('json') ? await response.json() : { html: await response.text() };
            if (payload.html?.includes('data-assistance-result')) content.innerHTML = payload.html;
            else throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'AI assistance is unavailable. Continue with manual entry.');
        } catch (error) { content.textContent = error.message; }
        finally { dialog()?.removeAttribute('aria-busy'); }
    }
    document.addEventListener('click', event => {
        if (event.target.closest('[data-assistance-close],[data-assistance-keep]')) close();
        if (event.target.closest('[data-assistance-retry]')) { original = active.value; protectedSnapshot = JSON.stringify(formValues(active.form)); preview(); }
        if (event.target.closest('[data-assistance-use]')) {
            if (active.value !== original || JSON.stringify(formValues(active.form)) !== protectedSnapshot) { dialog().querySelector('[data-assistance-content]').textContent = 'The form changed. Close this preview and request a fresh suggestion.'; return; }
            const result = dialog().querySelector('[data-assistance-result]');
            const suggestion = result.querySelector('[data-suggestion]').textContent;
            clearMarker(active); active.value = suggestion; active.dispatchEvent(new Event('input', { bubbles: true })); active.dispatchEvent(new Event('change', { bubbles: true }));
            const marker = document.createElement('input'); marker.type = 'hidden'; marker.name = 'ai_assistance[]'; marker.value = result.dataset.assistanceId; marker.dataset.assistanceMarker = ''; marker.dataset.field = active.name; active.form.append(marker);
            close();
        }
    });
    document.addEventListener('input', event => {
        if (!event.target.form || event.target.dataset.rephraseReady) return;
        // Changing any protected input makes an accepted preview stale; retain the text as a manual edit.
        event.target.form.querySelectorAll('[data-assistance-marker]').forEach(m => m.remove());
    });
    document.addEventListener('change', event => {
        if (!event.target.form || event.target.dataset.rephraseReady) return;
        event.target.form.querySelectorAll('[data-assistance-marker]').forEach(m => m.remove());
    });
    scan();
    let scheduled = false;
    new MutationObserver(records => {
        if (!records.some(r => [...r.addedNodes].some(n => n.nodeType === 1 && (n.matches?.('form, input[name], textarea[name], [data-target-assistance-config]') || n.querySelector?.('form'))))) return;
        if (!scheduled) { scheduled = true; queueMicrotask(() => { scheduled = false; scan(); }); }
    }).observe(document.body, { childList: true, subtree: true });
}
