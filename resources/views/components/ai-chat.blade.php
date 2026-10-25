<div x-data="aiChat" data-endpoint="{{ route('assistant.chat') }}" class="fixed bottom-5 right-5 z-50" @keydown.escape.window="if (open) toggle()">
    <section x-show="open" style="display: none" id="spms-ai-chat" aria-labelledby="spms-ai-title" class="mb-3 flex w-[calc(100vw-2.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl" role="region">
        <header class="flex items-center justify-between bg-spms-navy px-4 py-3 text-white">
            <div><h2 id="spms-ai-title" class="font-bold">SPMS Assistant</h2><p class="text-xs text-blue-100">Help with targets and reports</p></div>
            <button type="button" @click="toggle()" class="rounded p-2 hover:bg-white/10" aria-label="Close assistant">✕</button>
        </header>
        <div x-ref="messages" role="log" aria-live="polite" aria-label="Chat messages" class="h-72 max-h-[45vh] space-y-3 overflow-y-auto p-4 text-sm">
            <p x-show="messages.length === 0" class="text-gray-600">Hello! Ask me about SPMS, draft a target, or paste text you want summarized. I cannot access your records or files.</p>
            <template x-for="(item, index) in messages" :key="index">
                <div class="rounded-xl p-3" :class="item.role === 'user' ? 'ml-6 bg-blue-50 text-spms-navy' : 'mr-3 bg-gray-100 text-gray-800'">
                    <p class="mb-1 text-xs font-bold" x-text="item.role === 'user' ? 'You' : 'Assistant'"></p>
                    <p class="whitespace-pre-wrap break-words" x-text="item.content"></p>
                </div>
            </template>
            <p x-show="busy" class="text-gray-500" role="status">Thinking…</p>
        </div>
        <div class="border-t border-gray-100 p-3">
            <p x-show="error" x-text="error" class="mb-2 text-sm text-red-700" role="alert"></p>
            <form @submit.prevent.stop="send()" data-no-api>
                <label for="ai-chat-message" class="sr-only">Message the assistant</label>
                <textarea id="ai-chat-message" x-ref="input" x-model="message" :disabled="busy" maxlength="4000" rows="2" placeholder="Ask about your work…" class="w-full resize-none rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                <div class="mt-2 flex items-center justify-between">
                    <button type="button" @click="clear()" :disabled="busy" class="text-xs text-gray-500 disabled:opacity-50">Clear chat</button>
                    <button type="submit" :disabled="busy || !message.trim()" class="rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Send</button>
                </div>
            </form>
            <p class="mt-2 text-[11px] text-gray-500">Messages are sent to OpenAI. Review AI suggestions before using them. Chat clears when you navigate away.</p>
        </div>
    </section>
    <button type="button" x-ref="launcher" @click="toggle()" :aria-expanded="open" aria-controls="spms-ai-chat" aria-label="Open SPMS AI assistant" class="ml-auto flex h-14 w-14 items-center justify-center rounded-full bg-spms-navy text-white shadow-lg ring-4 ring-white hover:bg-blue-800 focus:outline-none focus:ring-blue-300">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-7 w-7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2V11.5a9.5 9.5 0 0 1 19 0Z"/></svg>
    </button>
</div>
