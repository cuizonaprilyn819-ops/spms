<x-app-layout>
    @php
        $canManageOffices = auth()->user()?->hasAnyRole(['Administrator', 'HR']);
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Office Management') }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($canManageOffices)
                        Add, edit, and remove offices used for employee assignment.
                    @else
                        View offices used for employee assignment.
                    @endif
                </p>
            </div>
            @if ($canManageOffices)
                <a href="{{ route('offices.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                    Add Office
                </a>
            @endif
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
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Office</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Employees</th>
                                @if ($canManageOffices)
                                    <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($offices as $office)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $office->name }}</p>
                                        @if ($office->description)
                                            <p class="mt-1 max-w-xl text-sm text-gray-500">{{ $office->description }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $office->code ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $office->employees_count }}</td>
                                    @if ($canManageOffices)
                                        <td class="px-6 py-4">
                                            <div class="flex justify-end gap-2">
                                                <a href="{{ route('offices.edit', $office) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Edit</a>
                                                <form method="POST" action="{{ route('offices.destroy', $office) }}" onsubmit="return confirm('Delete this office? Employees assigned to it will become unassigned.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg border border-spms-red px-3 py-2 text-sm font-semibold text-spms-red transition hover:bg-spms-red hover:text-white">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canManageOffices ? 4 : 3 }}" class="px-6 py-12 text-center text-sm text-gray-500">No offices yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $offices->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
