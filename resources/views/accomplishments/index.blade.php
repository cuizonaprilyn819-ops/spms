<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Accomplishment Tracking') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Submit accomplishments, update progress, and monitor completion percentage.</p>
            </div>
            <a href="{{ route('accomplishments.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                Submit Accomplishment
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-spms-surface">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Target</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Accomplishment</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Completion</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Submitted By</th>
                                <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($accomplishments as $accomplishment)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $accomplishment->performanceTarget->target }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $accomplishment->performanceTarget->targetable?->title ?? 'Performance form' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm leading-6 text-gray-600">{{ $accomplishment->details }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex min-w-40 items-center gap-3">
                                            <div class="h-2 flex-1 rounded-full bg-gray-200">
                                                <div class="h-2 rounded-full bg-spms-green" style="width: {{ min(100, (float) $accomplishment->completion_percentage) }}%"></div>
                                            </div>
                                            <span class="text-sm font-semibold text-spms-ink">{{ number_format((float) $accomplishment->completion_percentage, 2) }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $accomplishment->submittedBy?->name }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('accomplishments.show', $accomplishment) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">View</a>
                                            <a href="{{ route('accomplishments.edit', $accomplishment) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No accomplishments submitted yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $accomplishments->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
