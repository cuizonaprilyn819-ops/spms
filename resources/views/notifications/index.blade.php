<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Notifications') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Requests, approval updates, reminders, and evaluation notices.</p>
            </div>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">
                    Mark All Read
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="space-y-3">
                @forelse ($notifications as $notification)
                    <div class="rounded-lg border {{ $notification->read_at ? 'border-gray-200 bg-white' : 'border-blue-200 bg-blue-50' }} p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-spms-ink">{{ $notification->data['title'] ?? 'SPMS Notification' }}</h3>
                                    @unless ($notification->read_at)
                                        <span class="rounded-full bg-spms-sky px-2 py-0.5 text-xs font-bold text-white">New</span>
                                    @endunless
                                </div>
                                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $notification->data['message'] ?? 'You have a new SPMS notification.' }}</p>
                                <p class="mt-2 text-xs text-gray-500">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>

                            <div class="flex shrink-0 gap-2">
                                @if (! $notification->read_at)
                                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-spms-navy-hover">
                                            Open
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ \App\Support\NotificationUrl::resolve($notification->data['url'] ?? null) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">
                                        Open
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center text-sm text-gray-500 shadow-sm">
                        No notifications yet.
                    </div>
                @endforelse
            </div>

            <div class="mt-5">
                {{ $notifications->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
