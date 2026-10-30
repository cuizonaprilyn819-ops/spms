const resources = new Set([
    'dashboard', 'profile', 'offices', 'employees', 'evaluation-cycles',
    'strategic-goals', 'strategic-objectives', 'office-targets', 'target-movs',
    'opcrs', 'dpcrs', 'upcrs', 'ipcrs', 'evaluations', 'performance-targets',
    'accomplishments', 'movs', 'notifications', 'reports', 'report-workflow',
    'report-attachments',
    'planning', 'historical-pcrs',
]);

export function apiTarget(href, baseHref) {
    const base = new URL(baseHref);
    const url = new URL(href, base);
    const prefix = base.pathname.replace(/\/$/, '');
    if (url.origin !== base.origin || !url.pathname.startsWith(`${prefix}/`)) return null;
    const path = url.pathname.slice(prefix.length).replace(/^\/api\//, '/');
    if (!resources.has(path.split('/')[1]) || /\/(download|preview|pdf|excel|original)\/?$/.test(path) || /\/historical-pcrs\/\d+\/(pages|export)\//.test(path)) return null;
    const page = new URL(`${prefix}${path}${url.search}${url.hash}`, base);
    const api = new URL(`${prefix}/api${path}${url.search}`, base);
    return { page, api };
}

export function installBrowserApi(Alpine) {
    const base = document.querySelector('meta[name="spms-base-url"]')?.content;
    if (!base) return;
    let busy = false;
    let currentPage = window.location.pathname + window.location.search;

    function feedback(active, form = null) {
        document.documentElement?.toggleAttribute('data-spms-busy', active);
        document.querySelector('main')?.setAttribute?.('aria-busy', String(active));
        form?.toggleAttribute?.('data-submitting', active);
        form?.setAttribute?.('aria-busy', String(active));
        const status = document.querySelector('[data-navigation-status]');
        if (status) status.textContent = active ? (form ? 'Saving. Please wait.' : 'Loading page.') : '';
    }

    function showError(message, container = document.querySelector('main') || document.body) {
        container.querySelector('[data-api-error]')?.remove();
        const box = document.createElement('div');
        box.dataset.apiError = '';
        box.className = 'm-4 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-700';
        box.setAttribute('role', 'alert');
        box.tabIndex = -1;
        box.textContent = message;
        container.prepend(box);
        box.focus();
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                ...options.headers,
            },
        });
        const payload = await response.json();
        if (!response.ok) {
            const message = response.status === 419
                ? 'Your session expired. Reload this page before trying again.'
                : response.status === 401
                    ? 'Please sign in again to continue.'
                    : response.status === 403
                        ? 'You do not have permission to make this change. Check the responsible office and person, or contact your office head.'
                        : Object.values(payload.errors || {}).flat().join('\n') || payload.message || 'Unable to save. Please try again.';
            throw new Error(message);
        }
        return payload;
    }

    function render(html, page) {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        if (!parsed.querySelector('meta[name="spms-base-url"]')) throw new Error('Unable to display this page. Please reload.');
        // Register page-local helpers before Alpine initializes the new content.
        const scripts = [...parsed.body.querySelectorAll('script')];
        scripts.forEach(script => script.remove());
        Alpine.mutateDom(() => {
            Alpine.destroyTree(document.body);
            document.body.replaceChildren(...parsed.body.childNodes);
            for (const script of scripts) {
                if (script.src || (script.type && script.type !== 'text/javascript')) continue;
                const live = document.createElement('script');
                live.textContent = script.textContent;
                document.body.append(live);
            }
            document.title = parsed.title;
            document.querySelector('meta[name="csrf-token"]').content = parsed.querySelector('meta[name="csrf-token"]').content;
            history.pushState({}, '', page);
            currentPage = page.pathname + page.search;
            Alpine.initTree(document.body);
        });
        window.scrollTo(0, 0);
        const main = document.getElementById('main-content');
        main?.focus({ preventScroll: true });
        if (page.hash) {
            const section = document.getElementById(decodeURIComponent(page.hash.slice(1)));
            if (section?.tagName === 'DETAILS') section.open = true;
            section?.scrollIntoView();
        }
    }

    async function navigate(target) {
        const payload = await request(target.api);
        if (payload.redirect) {
            window.location.assign(payload.redirect);
            return;
        }
        if (typeof payload.html !== 'string') throw new Error('Unexpected response. Please reload this page.');
        render(payload.html, target.page);
    }

    document.addEventListener('click', async event => {
        const link = event.target.closest('a[href]');
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const target = apiTarget(link.href, base);
        if (!target || (target.page.pathname === location.pathname && target.page.search === location.search && target.page.hash)) return;
        event.preventDefault();
        if (busy) return;
        busy = true;
        feedback(true);
        try { await navigate(target); } catch (error) { showError(error.message); } finally { busy = false; feedback(false); }
    });

    document.addEventListener('submit', async event => {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || (form.target && form.target !== '_self')) return;
        const target = apiTarget(event.submitter?.hasAttribute('formaction') ? event.submitter.formAction : form.action, base);
        if (!target) return;
        event.preventDefault();
        if (busy) return;
        busy = true;
        const data = new FormData(form, event.submitter);
        const buttons = [...form.querySelectorAll('button, input[type="submit"]')].filter(button => !button.disabled);
        buttons.forEach(button => { button.disabled = true; });
        feedback(true, form);
        try {
            if (form.method.toLowerCase() === 'get') {
                target.page.search = new URLSearchParams(data).toString();
                target.api.search = target.page.search;
                await navigate(target);
            } else {
                // POST plus Laravel's _method field also supports multipart PUT/PATCH uploads.
                const payload = await request(target.api, { method: 'POST', body: data });
                if (payload.redirect) {
                    const next = apiTarget(payload.redirect, base);
                    // Load the next screen via the API, preserving flashed success messages.
                    if (next) await navigate(next);
                    else window.location.assign(payload.redirect);
                } else if (payload.html) render(payload.html, target.page);
            }
        } catch (error) {
            showError(error.message, form.isConnected ? form : undefined);
        } finally {
            buttons.forEach(button => { button.disabled = false; });
            busy = false;
            feedback(false, form);
        }
    });

    window.addEventListener('popstate', () => {
        if (window.location.pathname + window.location.search !== currentPage) window.location.reload();
    });
}
