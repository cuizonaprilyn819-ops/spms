<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Evaluation Period Management') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Create SPMS cycles and control which semester is open for evaluation.</p>
            </div>
            <a href="{{ route('evaluation-cycles.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                Create Cycle
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
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Cycle</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Year</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Semester</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($cycles as $cycle)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-spms-ink">{{ $cycle->label }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $cycle->year }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $cycle->semester }}</td>
                                    <td class="px-6 py-4">
                                        @if ($cycle->isOpen())
                                            <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">Open</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">Closed</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            @if ($cycle->isOpen())
                                                <form method="POST" action="{{ route('evaluation-cycles.close', $cycle) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg border border-spms-red px-3 py-2 text-sm font-semibold text-spms-red transition hover:bg-spms-red hover:text-white">Close Cycle</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('evaluation-cycles.open', $cycle) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-lg border border-spms-green px-3 py-2 text-sm font-semibold text-spms-green transition hover:bg-spms-green hover:text-white">Open Cycle</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No evaluation cycles yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $cycles->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
