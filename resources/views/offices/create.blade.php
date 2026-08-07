<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Add Office') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @include('offices.partials.form', [
                    'office' => null,
                    'action' => route('offices.store'),
                    'method' => 'POST',
                    'submit' => 'Save Office',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
