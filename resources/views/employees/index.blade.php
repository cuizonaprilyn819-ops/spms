<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Employee Management') }}</h2>
                <p class="mt-1 text-sm text-gray-500">Add employees, assign offices, and assign system roles.</p>
            </div>
            <a href="{{ route('employees.create') }}" class="inline-flex items-center justify-center rounded-lg bg-spms-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b4276] focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2">
                Add Employee
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
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Office</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Role</th>
                                <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($employees as $employee)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-spms-ink">{{ $employee->name }}</p>
                                        <p class="mt-1 text-sm text-gray-500">{{ $employee->email }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $employee->office?->name ?? 'Unassigned' }}</td>
                                    <td class="px-6 py-4">
                                        @forelse ($employee->roles as $role)
                                            <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-spms-navy">{{ $role->name }}</span>
                                        @empty
                                            <span class="text-sm text-gray-500">No role</span>
                                        @endforelse
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('employees.edit', $employee) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Edit</a>
                                            <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Delete this employee account?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-spms-red px-3 py-2 text-sm font-semibold text-spms-red transition hover:bg-spms-red hover:text-white">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No employees yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">
                {{ $employees->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
