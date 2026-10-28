import './bootstrap';

import Alpine from 'alpinejs';
import { installBrowserApi } from './browser-api';
import { installDashboardRefresh } from './dashboard-refresh';
import { aiChat } from './ai-chat';
import { installWorkspaceUi } from './workspace-ui';
import { installActionButtons } from './action-buttons';

installWorkspaceUi();

window.Alpine = Alpine;
Alpine.data('aiChat', aiChat);

Alpine.data('searchableSelect', (options, initialValue = '') => ({
    options,
    value: String(initialValue ?? ''),
    search: '',
    open: false,
    init() { this.restoreLabel(); },
    get filteredOptions() {
        const query = this.search.trim().toLocaleLowerCase();
        const matches = query && !this.value
            ? this.options.filter(option => option.label.toLocaleLowerCase().includes(query))
            : this.options;
        return matches;
    },
    onInput() {
        const selected = this.options.find(option => String(option.value) === this.value);
        if (!selected || this.search !== selected.label) this.value = '';
        this.open = true;
    },
    select(option) {
        this.value = String(option.value);
        this.search = option.label;
        this.open = false;
    },
    clear() {
        this.value = '';
        this.search = '';
        this.open = false;
    },
    restoreLabel() {
        const selected = this.options.find(option => String(option.value) === this.value);
        this.search = selected?.label ?? '';
    },
}));

Alpine.start();
installBrowserApi(Alpine);
installDashboardRefresh(Alpine);
installActionButtons();
