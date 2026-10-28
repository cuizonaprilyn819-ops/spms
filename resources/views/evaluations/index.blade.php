<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Employee Evaluations') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Rate employees using Q/E/T/A, add remarks, and review automatically computed weighted ratings.</p>
            </div>
            <a href="{{ route('evaluations.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-spms-navy-hover focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                Rate Employee
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
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Target</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Remarks</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Q/E/T/A</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Adjectival</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Weighted</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Rated By</th>
                                <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($evaluations as $evaluation)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $evaluation->employee?->name }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $evaluation->employee?->office?->name ?? 'No office assigned' }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $evaluation->performanceTarget->target }}</p>
                                        <p class="mt-1 text-xs text-gray-500">Weight: {{ number_format((float) $evaluation->performanceTarget->weight, 2) }}%</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm leading-6 text-gray-600">{{ $evaluation->remarks ?: 'No remarks provided.' }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-spms-ink">
                                        {{ number_format((float) ($evaluation->quality_rating ?? $evaluation->rating), 2) }} /
                                        {{ number_format((float) ($evaluation->efficiency_rating ?? $evaluation->rating), 2) }} /
                                        {{ number_format((float) ($evaluation->timeliness_rating ?? $evaluation->rating), 2) }} /
                                        {{ number_format((float) $evaluation->rating, 2) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-semibold text-spms-navy">{{ $evaluation->adjectival_rating ?? 'Not rated' }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-spms-green">{{ number_format((float) $evaluation->computed_rating, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $evaluation->ratedBy?->name }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('evaluations.show', $evaluation) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">No employee evaluations submitted yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $evaluations->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
