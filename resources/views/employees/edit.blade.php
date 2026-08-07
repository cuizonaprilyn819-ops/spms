<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Edit Employee') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @include('employees.partials.form', [
                    'employee' => $employee,
                    'offices' => $offices,
                    'roles' => $roles,
                    'action' => route('employees.update', $employee),
                    'method' => 'PATCH',
                    'submit' => 'Update Employee',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
