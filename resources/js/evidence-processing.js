export function installEvidenceProcessing() {
    const active = new Set();
    const next = new Map();
    setInterval(async () => {
        if (document.hidden) return;
        const groups = new Map();
        document.querySelectorAll('[data-evidence-status]').forEach(node => {
            const key = node.dataset.processingKey;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(node);
        });
        for (const [key, nodes] of groups) {
            if (active.has(key) || (next.get(key) ?? 0) > Date.now()) continue;
            active.add(key);
            next.set(key, Date.now() + 15000);
            const page = location.href;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 12000);
            const load = async node => {
                const response = await fetch(node.dataset.evidenceStatus, {
                    credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok || response.redirected) throw new Error('Processing update unavailable');
                return response.json();
            };
            try {
                const first = await load(nodes[0]);
                if (!['completed', 'failed'].includes(first.status)) {
                    if (location.href !== page || document.hidden) continue;
                    for (const node of nodes) {
                        if (!node.isConnected) continue;
                        for (const [selector, value] of [['[data-processing-label]', first.label], ['[data-processing-message]', first.message], ['[data-processing-warning]', first.warning]]) {
                            const element = node.querySelector?.(selector);
                            if (element && typeof value === 'string') {
                                element.textContent = value;
                                if (selector === '[data-processing-warning]') element.classList.toggle('hidden', !value);
                            }
                        }
                    }
                    continue;
                }
                // One poll per batch; fetch the other cards only once, on completion.
                for (const [index, node] of nodes.entries()) {
                    const data = index ? await load(node) : first;
                    if (location.href !== page || !node.isConnected || document.hidden) break;
                    if (typeof data.html === 'string' && data.html.trim()) {
                        const fragment = new DOMParser().parseFromString(data.html, 'text/html').body.firstElementChild;
                        if (fragment) node.replaceWith(fragment);
                    }
                }
            } catch {
                // Preserve the saved answers and any visible review forms; retry later.
                next.set(key, Date.now() + 30000);
            } finally {
                clearTimeout(timeout);
                active.delete(key);
            }
        }
        for (const key of next.keys()) if (!groups.has(key) && !active.has(key)) next.delete(key);
    }, 5000);
}
