const paths = {
    add: 'M12 5v14M5 12h14',
    edit: 'm16 3 5 5M4 20l4-1L21 6l-5-5L3 14l-1 8z',
    delete: 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
    reject: 'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0ZM8 8l8 8M8 16l8-8',
    save: 'M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12l4 4v12a2 2 0 0 1-2 2ZM7 3v6h10V3M7 21v-8h10v8',
    send: 'm22 2-7 20-4-9-9-4 20-7ZM22 2 11 13',
    upload: 'M12 16V3M7 8l5-5 5 5M3 16v5h18v-5',
    download: 'M12 3v13M7 11l5 5 5-5M3 16v5h18v-5',
    check: 'm5 12 4 4L19 6',
    back: 'm10 5-7 7 7 7M3 12h18',
    return: 'm8 3-5 5 5 5M3 8h11a7 7 0 0 1 0 14',
    close: 'm6 6 12 12M6 18 18 6',
    search: 'M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
    filter: 'M3 5h18M6 12h12M10 19h4',
    key: 'M20 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM13 10 3 20H1v-3l10-10M17 7h.01',
    view: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    document: 'M14 2H5v20h14V7l-5-5ZM14 2v6h5M8 12h8M8 16h6',
    spreadsheet: 'M14 2H5v20h14V7l-5-5ZM14 2v6h5M8 12h8v7H8v-7ZM12 12v7M8 15.5h8',
    open: 'M14 3h7v7M21 3 10 14M10 3H3v18h18v-7',
    suggest: 'M3 3h7v7H3V3ZM14 14h7v7h-7v-7ZM10 6h7v8M6 10v7h8',
    refresh: 'M20 7a9 9 0 0 0-15-2L2 8M2 2v6h6M4 17a9 9 0 0 0 15 2l3-3M22 22v-6h-6',
};

export function actionForLabel(text) {
    const label = text.replace(/\s+/g, ' ').trim().toLowerCase();
    if (/^(delete|remove)\b/.test(label)) return 'delete';
    if (/^reject\b/.test(label)) return 'reject';
    if (/^(reset password|request password|change password|reset your password)\b/.test(label)) return 'key';
    if (/^(cancel|back|all plans)\b/.test(label)) return 'back';
    if (/^(return|confirm return)\b/.test(label)) return 'return';
    if (/^(close|clear)\b/.test(label)) return 'close';
    if (/^(upload|attach|submit mov)\b/.test(label)) return 'upload';
    if (/^(download|export|get relevant)\b/.test(label)) return 'download';
    if (/^(pdf)\b/.test(label)) return 'document';
    if (/^(excel)\b/.test(label)) return 'spreadsheet';
    if (/^(edit|amend|propose an amendment)\b/.test(label)) return 'edit';
    if (/^(approve|confirm|validate|verify|mark|enable)\b/.test(label) || /^record my agreement\b/.test(label)) return 'check';
    if (/^(save|update|record|finish)\b/.test(label)) return 'save';
    if (/^(add|new|create|open a new)\b/.test(label)) return 'add';
    if (/^(send|submit|assign|resubmit|forward)\b/.test(label)) return 'send';
    if (/^(search)\b/.test(label)) return 'search';
    if (/^(filter|apply(?: filters)?)\b/.test(label)) return 'filter';
    if (/^(view|preview|show|hide)\b/.test(label)) return 'view';
    if (/^(suggest|analy[sz]e|propose a target)\b/.test(label)) return 'suggest';
    if (/^(refresh|reload|retry|reset)\b/.test(label)) return 'refresh';
    if (/^(open)\b/.test(label)) return 'open';
    return null;
}

export function tooltipPosition(rect, width, height, viewport) {
    const margin = 12;
    const gap = 9;
    const above = rect.top >= height + gap + margin;
    return {
        left: Math.max(margin, Math.min(rect.left + rect.width / 2 - width / 2, viewport.width - width - margin)),
        top: Math.max(margin, Math.min(above ? rect.top - height - gap : rect.bottom + gap, viewport.height - height - margin)),
        placement: above ? 'above' : 'below',
    };
}

export function installActionButtons() {
    const exclude = '.spms-guest, .spms-sidebar, .spms-topbar, .spms-section-nav, .spms-plan-path, #account-menu, [x-data="aiChat"], [role="listbox"], [role="tablist"], [data-action-plain]';
    let tooltip;
    let active;
    let hideTimer;

    function labelOf(control) {
        return (control.querySelector('.spms-action-label') || control).textContent.replace(/\s+/g, ' ').trim();
    }

    function enhance(control) {
        if (!control.matches('button, a[href]') || control.closest(exclude) || control.closest('label') || control.closest('[x-data^="searchableSelect"]')) return;
        // Existing icon controls, menu triggers, and option selectors keep their own semantics.
        if (!control.hasAttribute('data-action-icon') && (control.hasAttribute('aria-label') || control.hasAttribute('aria-controls') || control.hasAttribute('aria-expanded') || control.hasAttribute(':aria-expanded'))) return;
        const label = labelOf(control);
        const icon = actionForLabel(label) || control.dataset.actionIcon;
        if (!icon || label.length > 160) return;
        if (control.tagName === 'A' && !/(^|\s)(inline-flex|inline-block|px-|py-)/.test(control.className) && !/^(edit|view|preview|pdf|excel|cancel|back|download(?: (pdf|excel))?|export(?: (pdf|excel))?)$/i.test(label)) return;

        control.setAttribute('aria-label', label);
        control.dataset.actionIcon = icon;
        control.classList.add('spms-action');
        const tableScroll = control.closest('table')?.parentElement;
        if (tableScroll?.classList.contains('overflow-x-auto')) tableScroll.classList.add('spms-action-scroll');
        if (icon === 'delete' || icon === 'reject') control.classList.add('spms-action-danger');
        else if (/(^|\s)bg-(spms-green|green-|emerald-)/.test(control.className)) control.classList.add('spms-action-success');
        else if (/(^|\s)bg-amber-/.test(control.className)) control.classList.add('spms-action-warning');
        else if (/(^|\s)bg-(spms-navy|blue-[6-9]00)/.test(control.className)) control.classList.add('spms-action-primary');
        if (control.querySelector(':scope > .spms-action-label')) {
            control.querySelector('[data-action-generated] path')?.setAttribute('d', paths[icon]);
            if (active === control) show(control);
            return;
        }

        const nodes = [...control.childNodes];
        const iconBox = document.createElement('span');
        iconBox.className = 'spms-action-icon';
        iconBox.setAttribute('aria-hidden', 'true');
        let svg = control.querySelector('svg');
        if (!svg) {
            svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.dataset.actionGenerated = '';
            for (const [key, value] of Object.entries({ viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.75', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })) svg.setAttribute(key, value);
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', paths[icon]);
            svg.append(path);
        }
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        iconBox.append(svg);
        const text = document.createElement('span');
        text.className = 'spms-action-label';
        for (const node of nodes) if (node !== svg) text.append(node);
        control.append(iconBox, text);
    }

    function scan(root) {
        if (root.nodeType !== Node.ELEMENT_NODE) return;
        enhance(root);
        root.querySelectorAll('button, a[href]').forEach(enhance);
    }

    function hide() {
        clearTimeout(hideTimer);
        if (tooltip) tooltip.removeAttribute('data-visible');
        active = null;
    }

    function makeTooltip() {
        if (tooltip?.isConnected) return;
        tooltip = document.createElement('div');
        tooltip.className = 'spms-action-tooltip';
        // The control already has the same accessible name; this is its visual label.
        tooltip.setAttribute('aria-hidden', 'true');
        tooltip.addEventListener('pointerenter', () => clearTimeout(hideTimer));
        tooltip.addEventListener('pointerleave', scheduleHide);
        document.body.append(tooltip);
    }

    function show(control) {
        if (window.matchMedia('(max-width: 767px), (hover: none), (pointer: coarse)').matches) return;
        clearTimeout(hideTimer);
        makeTooltip();
        active = control;
        tooltip.textContent = labelOf(control);
        const rect = control.getBoundingClientRect();
        const box = tooltip.getBoundingClientRect();
        const position = tooltipPosition(rect, box.width, box.height, { width: window.innerWidth, height: window.innerHeight });
        tooltip.style.left = `${position.left}px`;
        tooltip.style.top = `${position.top}px`;
        tooltip.dataset.placement = position.placement;
        tooltip.dataset.visible = '';
    }

    function scheduleHide() {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            if (active?.isConnected && (active.matches(':hover') || active === document.activeElement || tooltip?.matches(':hover'))) return;
            hide();
        }, 100);
    }

    scan(document.body);
    // Covers API navigation, dashboard refreshes, and Alpine's changing busy labels.
    new MutationObserver(records => {
        const pending = new Set();
        for (const record of records) {
            const owner = (record.target.nodeType === Node.ELEMENT_NODE ? record.target : record.target.parentElement)?.closest('.spms-action');
            if (owner) pending.add(owner);
            for (const node of record.addedNodes) if (node.nodeType === Node.ELEMENT_NODE && !node.closest('.spms-action-icon, .spms-action-label, .spms-action-tooltip')) pending.add(node);
        }
        pending.forEach(scan);
        if (active && !active.isConnected) hide();
    }).observe(document.body, { childList: true, characterData: true, subtree: true });

    document.addEventListener('pointerover', event => {
        const control = event.target.closest('.spms-action');
        if (control && !control.contains(event.relatedTarget)) show(control);
    });
    document.addEventListener('pointerout', event => {
        if (event.target.closest('.spms-action')) scheduleHide();
    });
    document.addEventListener('focusin', event => {
        const control = event.target.closest('.spms-action');
        if (control) show(control);
    });
    document.addEventListener('focusout', event => {
        if (event.target.closest('.spms-action')) scheduleHide();
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') hide(); });
    document.addEventListener('click', event => { if (event.target.closest('.spms-action')) hide(); });
    window.addEventListener('resize', hide);
    window.addEventListener('scroll', hide, true);
}
