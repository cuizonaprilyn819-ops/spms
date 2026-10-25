<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="mb-3 text-xs font-bold uppercase tracking-[0.16em] text-spms-navy">Employee administration</p>
                <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Employee Directory') }}</h2>
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

            <form method="GET" action="{{ route('employees.index') }}" class="spms-directory-filters">
                <div>
                    <label for="employee-search">Search employees</label>
                    <div class="spms-search-box">
                        <svg class="ml-2 h-5 w-5 shrink-0 text-spms-navy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
                        <input id="employee-search" type="search" name="search" maxlength="200" value="{{ request('search') }}" placeholder="Search by name or email">
                        <button type="submit">Search</button>
                    </div>
                </div>
                <div>
                    <label for="employee-office">Office / department</label>
                    <select id="employee-office" name="office_id">
                        <option value="">All offices</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->id }}" @selected((string) request('office_id') === (string) $office->id)>{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <div class="spms-record-count" role="status">
                <strong>{{ $employees->count() }}</strong> of <strong>{{ $employees->total() }}</strong> employees shown
                @if (request()->filled('search') || request()->filled('office_id'))
                    <a href="{{ route('employees.index') }}" class="ml-3 font-semibold text-spms-navy underline">Clear filters</a>
                @else
                    across all offices
                @endif
            </div>
            <div class="spms-directory-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-spms-surface">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Office</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">CSC Position</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase text-gray-500">Role</th>
                                <th class="px-6 py-3 text-right text-xs font-bold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($employees as $employee)
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3"><span class="spms-account-avatar" aria-hidden="true">{{ mb_substr($employee->name, 0, 1) }}</span><p class="font-semibold text-spms-ink">{{ $employee->name }}</p></div>
                                        <p class="mt-1 text-sm text-gray-500">{{ $employee->email }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $employee->office?->name ?? 'Unassigned' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $employee->positionTitle?->title ?? 'Unassigned' }}</td>
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
                                            @role('Administrator')
                                                @unless ($employee->is(Auth::user()))
                                                <a href="{{ route('admin.password-resets.edit', $employee) }}" class="rounded-lg border border-spms-navy px-3 py-2 text-sm font-semibold text-spms-navy transition hover:bg-blue-50">Reset password</a>
                                                @endunless
                                            @endrole
                                            @unless ($employee->hasRole('Administrator'))
                                            <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Delete this employee account?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-spms-red px-3 py-2 text-sm font-semibold text-spms-red transition hover:bg-spms-red hover:text-white">Delete</button>
                                            </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">{{ request()->filled('search') || request()->filled('office_id') ? 'No employees match your filters.' : 'No employees yet.' }}</td>
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
