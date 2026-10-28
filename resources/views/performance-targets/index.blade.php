<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Performance Targets') }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @hasanyrole('Administrator|HR|HR Head|Campus Director|Supervisor')
                        Manage targets, success indicators, weights, and deadlines across SPMS forms.
                    @else
                        View targets, success indicators, weights, and deadlines across SPMS forms.
                    @endhasanyrole
                </p>
            </div>
            @hasanyrole('Administrator|Campus Director')
                <a href="{{ route('office-targets.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-spms-navy-hover">Upload Main Campus Document</a>
            @endhasanyrole
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-spms-ink">Assigned Office Targets</h3>
                <p class="mt-1 text-sm text-gray-500">Open a target to view its details, submit MOV, or check progress.</p>
                <div class="mt-4 divide-y divide-gray-200">
                    @forelse($officeTargets as $officeTarget)
                        <div class="py-4">
                            <a href="{{ route('office-targets.show', $officeTarget) }}" class="font-semibold text-spms-navy underline">{{ $officeTarget->title }}</a>
                            <p class="mt-1 text-xs text-gray-500">Created by {{ $officeTarget->createdBy?->name }} · Deadline: {{ $officeTarget->deadline?->format('M d, Y') }} · Weight: {{ $officeTarget->weight }}%</p>
                            <p class="mt-2 text-sm text-gray-600">{{ $officeTarget->success_indicator }}</p>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-gray-500">No office targets assigned yet.</p>
                    @endforelse
                </div>
                {{ $officeTargets->links() }}
            </section>
            @if($targets->isNotEmpty())
            <h3 class="mb-3 text-lg font-bold text-spms-ink">Report Performance Targets</h3>
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-spms-surface">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Target</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Linked Form</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Success Indicator</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Weight</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Deadline</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($targets as $target)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $target->target }}</p>
                                        <p class="mt-1 text-xs text-gray-500">Added by {{ $target->createdBy?->name }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-spms-navy">{{ $typeLabels[$target->targetable_type] ?? 'Form' }}</span>
                                        <p class="mt-2 text-sm text-gray-600">{{ $target->targetable?->title ?? 'Deleted form' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm leading-6 text-gray-600">{{ $target->success_indicator }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-spms-ink">{{ number_format((float) $target->weight, 2) }}%</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $target->deadline->format('M d, Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No performance targets yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $targets->links() }}
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
