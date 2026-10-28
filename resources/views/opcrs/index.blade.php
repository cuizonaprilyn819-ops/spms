<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('OPCR Management') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Create, view, and approve Office Performance Commitment and Review records.</p>
            </div>
            @role('Campus Director')<a href="{{ route('opcrs.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-spms-navy-hover focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                Create OPCR
            </a>@endrole
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
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">OPCR</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Office</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($opcrs as $opcr)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $opcr->title }}</p>
                                        <p class="mt-1 text-xs text-gray-500">Prepared by {{ $opcr->preparedBy?->name }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $opcr->office->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $opcr->evaluationCycle->label }}</td>
                                    <td class="px-6 py-4">
                                        @if ($opcr->isApproved())
                                            <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">Approved</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Draft</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('opcrs.show', $opcr) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No OPCR records yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $opcrs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
