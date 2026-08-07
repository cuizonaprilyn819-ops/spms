<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Edit Submission') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @include('accomplishments.partials.form', [
                    'accomplishment' => $accomplishment,
                    'targets' => $targets,
                    'action' => route('accomplishments.update', $accomplishment),
                    'method' => 'PATCH',
                    'submit' => 'Update Submission',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
