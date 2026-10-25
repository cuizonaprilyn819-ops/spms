<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Create Period') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('evaluation-cycles.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="year" :value="__('Year')" />
                        <x-text-input id="year" name="year" type="number" min="2000" max="2100" class="mt-1 block w-full" :value="old('year', now()->year)" required autofocus />
                        <x-input-error :messages="$errors->get('year')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="semester" :value="__('Semester')" />
                        <select id="semester" name="semester" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                            <option value="">Select semester</option>
                            @foreach ($semesters as $semester)
                                <option value="{{ $semester }}" @selected(old('semester') === $semester)>{{ App\Models\EvaluationCycle::semesterLabel($semester) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('semester')" class="mt-2" />
                    </div>

                    <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-spms-navy">
                        The new period will open automatically. Any currently open period will be closed.
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('evaluation-cycles.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>Create and Open Period</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
