// Presentation only: the server remains responsible for permissions, totals and pagination.
export function simpleTableHeaders(table) {
    const rows = [...(table.tHead?.rows || [])];
    if (rows.length !== 1) return null;
    const cells = [...rows[0].cells];
    if (!cells.length || cells.some(cell => cell.colSpan !== 1 || cell.rowSpan !== 1)) return null;
    return cells.map(cell => cell.textContent.replace(/\s+/g, ' ').trim());
}

export function installWorkspacePages() {
    function tables(scope) {
        const candidates = scope.matches?.('table') ? [scope] : [...scope.querySelectorAll('table')];
        for (const table of candidates) {
            if (!table.closest('#main-content') || table.closest('[data-table-matrix]')) continue;
            const headers = simpleTableHeaders(table);
            if (!headers || table.querySelector('tbody [rowspan], tbody th[colspan]')) continue;
            table.classList.add('spms-responsive-table');
            for (const row of table.tBodies[0]?.rows || []) {
                if (row.cells.length !== headers.length || [...row.cells].some(cell => cell.colSpan !== 1)) continue;
                [...row.cells].forEach((cell, i) => {
                    if (!cell.dataset.label) cell.dataset.label = headers[i];
                });
            }
            if (!table.hasAttribute('data-workspace-table') || table.dataset.workspaceEnhanced) continue;
            table.dataset.workspaceEnhanced = 'true';
            const rows = [...(table.tBodies[0]?.rows || [])].filter(row => row.cells.length === headers.length && [...row.cells].every(cell => cell.colSpan === 1));
            if (!rows.length) continue;
            const toolbar = document.createElement('div');
            toolbar.className = 'spms-table-toolbar';
            const label = document.createElement('label');
            label.textContent = 'Search visible rows';
            const search = document.createElement('input');
            search.type = 'search';
            search.placeholder = 'Type to filter this page…';
            search.autocomplete = 'off';
            label.append(search);
            const count = document.createElement('span');
            count.className = 'spms-table-filter-count';
            count.setAttribute('role', 'status');
            const filter = () => {
                const query = search.value.trim().toLocaleLowerCase();
                let shown = 0;
                for (const row of rows) {
                    row.hidden = !row.textContent.toLocaleLowerCase().includes(query);
                    if (!row.hidden) shown++;
                }
                count.textContent = `${shown} of ${rows.length} visible rows`;
                empty.hidden = shown !== 0;
            };
            const empty = document.createElement('p');
            empty.className = 'spms-table-empty';
            empty.textContent = 'No visible rows match your search. Clear the search to show this page again.';
            empty.hidden = true;
            search.addEventListener('input', filter);
            toolbar.append(label, count);
            const wrapper = table.parentElement;
            wrapper.before(toolbar);
            wrapper.after(empty);
            filter();
        }
    }

    function activate(nav, hash, focus = false) {
        const links = [...nav.querySelectorAll('a[href^="#"]')];
        const chosen = links.find(link => link.hash === hash) || links[0];
        if (!chosen) return;
        for (const link of links) {
            const panel = document.getElementById(link.hash.slice(1));
            if (!panel) continue;
            const selected = link === chosen;
            link.setAttribute('role', 'tab');
            link.id ||= `tab-${panel.id}`;
            link.setAttribute('aria-controls', panel.id);
            link.setAttribute('aria-selected', String(selected));
            link.tabIndex = selected ? 0 : -1;
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', link.id);
            panel.hidden = !selected;
        }
        if (focus) chosen.focus();
    }

    function enhance(scope) {
        tables(scope);
        const navs = scope.matches?.('[data-workspace-tabs]') ? [scope] : scope.querySelectorAll('[data-workspace-tabs]');
        for (const nav of navs) {
            if (nav.dataset.workspaceEnhanced) continue;
            nav.dataset.workspaceEnhanced = 'true';
            nav.setAttribute('role', 'tablist');
            const errorLink = [...nav.querySelectorAll('a[href^="#"]')].find(link =>
                document.getElementById(link.hash.slice(1))?.querySelector('ul.text-red-600, [aria-invalid="true"], [role="alert"]'));
            activate(nav, errorLink?.hash || window.location.hash || nav.dataset.workspaceDefault);
        }
    }
    enhance(document);
    new MutationObserver(records => {
        for (const record of records) for (const node of record.addedNodes) {
            if (node.nodeType === Node.ELEMENT_NODE && !node.closest('.spms-action, .spms-table-toolbar')) enhance(node);
        }
    }).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-workspace-tabs] a[href^="#"]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        history.replaceState(history.state, '', link.hash);
        activate(link.closest('[data-workspace-tabs]'), link.hash);
    });
    document.addEventListener('keydown', event => {
        const link = event.target.closest('[data-workspace-tabs] a');
        if (!link || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const nav = link.closest('[data-workspace-tabs]');
        const links = [...nav.querySelectorAll('a')];
        const index = links.indexOf(link);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + links.length) % links.length;
        history.replaceState(history.state, '', links[next].hash);
        activate(nav, links[next].hash, true);
    });
    window.addEventListener('hashchange', () => document.querySelectorAll('[data-workspace-tabs]').forEach(nav => activate(nav, window.location.hash)));
}
