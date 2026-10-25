<div class="ml-auto flex items-center gap-2">
    <a href="{{ route('notifications.index') }}" class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-spms-ink hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-spms-sky" aria-label="Notifications{{ $unreadCount ? ', '.$unreadCount.' unread' : ', no unread notifications' }}" title="Notifications">
        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
        </svg>
        @if ($unreadCount)
            <span class="absolute right-0 top-0 rounded-full bg-spms-red px-1.5 text-xs font-bold text-white" aria-hidden="true">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </a>
        <div class="relative" x-data="{ accountOpen: false }" @click.outside="accountOpen = false" @keydown.escape.prevent="accountOpen = false; $refs.accountButton.focus()">
            <button x-ref="accountButton" type="button" @click="accountOpen = !accountOpen" :aria-expanded="accountOpen" aria-controls="account-menu" class="flex items-center gap-3 rounded-lg px-3 py-2 text-right hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-spms-sky">
                <span class="spms-account-avatar" aria-hidden="true">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
                <span>
                    <span class="block text-sm font-semibold text-spms-ink">{{ Auth::user()->name }}</span>
                    <span class="block text-xs text-gray-500">{{ Auth::user()->email }}</span>
                </span>
                <svg class="h-4 w-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
            </button>
            <div id="account-menu" x-cloak x-show="accountOpen" x-transition class="absolute right-0 z-50 mt-2 w-48 rounded-lg border border-gray-200 bg-white p-2 shadow-lg">
                <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-spms-ink hover:bg-gray-50">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-spms-red hover:bg-gray-50">Log Out</button>
                </form>
            </div>
        </div>
</div>
