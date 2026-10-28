export function installDashboardRefresh(Alpine) {
    let busy = false;
    setInterval(async () => {
        const marker = document.querySelector('[data-dashboard-refresh]');
        const main = marker?.closest('main');
        // Route navigation focuses the main region; only a focused control pauses updates.
        const interacting = () => document.activeElement !== main && main.contains(document.activeElement);
        if (!main || busy || document.hidden || interacting()) return;
        const url = location.href;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 12000);
        busy = true;
        try {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal, headers: { Accept: 'text/html' } });
            if (!response.ok || response.redirected) throw new Error('Refresh unavailable');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = page.querySelector('[data-dashboard-refresh]')?.closest('main');
            if (!fresh) throw new Error('Please sign in again');
            if (location.href !== url || !marker.isConnected || document.hidden || interacting()) return;
            Alpine.mutateDom(() => {
                Alpine.destroyTree(main);
                main.replaceChildren(...fresh.childNodes);
                Alpine.initTree(main);
            });
        } catch {
            if (marker.isConnected) marker.textContent = 'Live update unavailable. Showing the last loaded data; retrying automatically.';
        } finally {
            clearTimeout(timeout);
            busy = false;
        }
    }, 15000);
}
