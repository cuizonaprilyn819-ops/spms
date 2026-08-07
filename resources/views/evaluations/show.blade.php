<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Evaluation Result') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $evaluation->employee?->name }} rated by {{ $evaluation->ratedBy?->name }}.</p>
            </div>
            <a href="{{ route('evaluations.index') }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Back to Evaluations</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <dl class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">Employee</dt>
                        <dd class="mt-1 text-sm font-semibold text-spms-ink">{{ $evaluation->employee?->name }}</dd>
                        <dd class="mt-1 text-xs text-gray-500">{{ $evaluation->employee?->office?->name ?? 'No office assigned' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">Evaluator</dt>
                        <dd class="mt-1 text-sm font-semibold text-spms-ink">{{ $evaluation->ratedBy?->name }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-bold uppercase text-gray-500">Target</dt>
                        <dd class="mt-1 text-sm font-semibold text-spms-ink">{{ $evaluation->performanceTarget->target }}</dd>
                        <dd class="mt-1 text-sm text-gray-600">{{ $evaluation->performanceTarget->success_indicator }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">Quality (Q)</dt>
                        <dd class="mt-1 text-3xl font-bold text-spms-navy">{{ number_format((float) ($evaluation->quality_rating ?? $evaluation->rating), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">Efficiency (E)</dt>
                        <dd class="mt-1 text-3xl font-bold text-spms-navy">{{ number_format((float) ($evaluation->efficiency_rating ?? $evaluation->rating), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">Timeliness (T)</dt>
                        <dd class="mt-1 text-3xl font-bold text-spms-navy">{{ number_format((float) ($evaluation->timeliness_rating ?? $evaluation->rating), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">Average Rating (A)</dt>
                        <dd class="mt-1 text-3xl font-bold text-spms-navy">{{ number_format((float) $evaluation->rating, 2) }}</dd>
                        <dd class="mt-1 text-xs font-semibold text-gray-500">{{ $evaluation->adjectival_rating ?? 'Not rated' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase text-gray-500">Weighted Rating</dt>
                        <dd class="mt-1 text-3xl font-bold text-spms-green">{{ number_format((float) $evaluation->computed_rating, 2) }}</dd>
                        <dd class="mt-1 text-xs text-gray-500">Weight: {{ number_format((float) $evaluation->performanceTarget->weight, 2) }}%</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-bold uppercase text-gray-500">Remarks</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-gray-600">{{ $evaluation->remarks ?: 'No remarks provided.' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
