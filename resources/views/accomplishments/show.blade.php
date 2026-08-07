<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('View Accomplishment') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $accomplishment->performanceTarget->target }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('accomplishments.edit', $accomplishment) }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Edit</a>
                <a href="{{ route('accomplishments.index') }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="border-b border-gray-200 pb-6">
                    <p class="text-sm font-semibold uppercase text-spms-sky">Accomplishment Submission</p>
                    <h1 class="mt-2 text-2xl font-bold text-spms-ink">{{ $accomplishment->performanceTarget->target }}</h1>
                    <p class="mt-2 text-sm text-gray-500">Submitted by {{ $accomplishment->submittedBy?->name }}</p>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-xs font-bold uppercase text-gray-500">Linked Form</p>
                        <p class="mt-2 text-sm font-semibold text-spms-ink">{{ $accomplishment->performanceTarget->targetable?->title ?? 'Performance form' }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-xs font-bold uppercase text-gray-500">Deadline</p>
                        <p class="mt-2 text-sm font-semibold text-spms-ink">{{ $accomplishment->performanceTarget->deadline->format('M d, Y') }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-xs font-bold uppercase text-gray-500">Completion</p>
                        <p class="mt-2 text-sm font-semibold text-spms-ink">{{ number_format((float) $accomplishment->completion_percentage, 2) }}%</p>
                    </div>
                </div>

                <div class="mt-8 space-y-6">
                    <section>
                        <h3 class="text-lg font-bold text-spms-ink">Submission Details</h3>
                        <div class="mt-3 whitespace-pre-line rounded-lg border border-gray-200 bg-spms-surface p-4 text-sm leading-6 text-gray-700">{{ $accomplishment->details }}</div>
                    </section>

                    <section>
                        <h3 class="text-lg font-bold text-spms-ink">Success Indicator</h3>
                        <div class="mt-3 whitespace-pre-line rounded-lg border border-gray-200 bg-spms-surface p-4 text-sm leading-6 text-gray-700">{{ $accomplishment->performanceTarget->success_indicator }}</div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
