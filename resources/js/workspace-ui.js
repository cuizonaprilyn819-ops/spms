// Keep disclosure links useful after the API replaces the page content.
export function installWorkspaceUi() {
    function reveal(link) {
        const section = document.getElementById(link.hash.slice(1));
        if (section?.tagName === 'DETAILS') section.open = true;
        link.closest('nav').querySelectorAll('a').forEach(item => item.removeAttribute('aria-current'));
        link.setAttribute('aria-current', 'location');
    }
    function restoreSection() {
        const link = [...document.querySelectorAll('.spms-section-nav a')].find(item => item.hash === window.location.hash);
        if (link) reveal(link);
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('.spms-section-nav a[href^="#"]');
        if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        reveal(link);
    });
    window.addEventListener('hashchange', restoreSection);
    restoreSection();
}
