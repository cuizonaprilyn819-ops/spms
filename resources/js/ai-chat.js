export function aiChat() {
    return {
        open: false,
        message: '',
        messages: [],
        busy: false,
        error: '',
        controller: null,
        toggle() {
            this.open = !this.open;
            this.$nextTick(() => this.open ? this.$refs.input.focus() : this.$refs.launcher.focus());
        },
        scroll() {
            this.$nextTick(() => { this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight; });
        },
        clear() {
            if (this.busy) return;
            this.messages = [];
            this.error = '';
        },
        destroy() {
            this.controller?.abort();
        },
        async send() {
            const message = this.message.trim();
            if (!message || this.busy) return;
            const history = this.messages.slice(-8).map(item => ({ role: item.role, content: item.content.slice(0, 6000) }));
            this.messages.push({ role: 'user', content: message });
            this.message = '';
            this.busy = true;
            this.error = '';
            this.scroll();
            this.controller = new AbortController();
            const timeout = setTimeout(() => this.controller?.abort(), 55000);
            try {
                const response = await fetch(this.$el.dataset.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: this.controller.signal,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ message, history }),
                });
                const payload = await response.json();
                if (!response.ok) {
                    const errors = { 401: 'Please sign in again.', 419: 'Your session expired. Reload the page.', 429: 'Please wait a minute before sending another message.' };
                    throw new Error(errors[response.status] || payload.message || 'The assistant is unavailable. Please try again.');
                }
                if (typeof payload.reply !== 'string' || !payload.reply.trim()) throw new Error('The assistant returned no answer. Please try again.');
                this.messages.push({ role: 'assistant', content: payload.reply });
                this.messages = this.messages.slice(-20);
            } catch (error) {
                this.messages.pop();
                this.message = message;
                this.error = error.name === 'AbortError' ? 'The request timed out. Please try again.' : error.message;
            } finally {
                clearTimeout(timeout);
                this.busy = false;
                this.controller = null;
                this.scroll();
            }
        },
    };
}
