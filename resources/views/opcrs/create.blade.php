<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Create OPCR') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('opcrs.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="evaluation_cycle_id" :value="__('Open Period')" />
                            <select id="evaluation_cycle_id" name="evaluation_cycle_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                                <option value="">Select open period</option>
                                @foreach ($cycles as $cycle)
                                    <option value="{{ $cycle->id }}" @selected(old('evaluation_cycle_id') == $cycle->id)>{{ $cycle->label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('evaluation_cycle_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="office_id" :value="__('Office')" />
                            <select id="office_id" name="office_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                                <option value="">Select office</option>
                                @foreach ($offices as $office)
                                    <option value="{{ $office->id }}" @selected(old('office_id') == $office->id)>{{ $office->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('office_id')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="title" :value="__('OPCR Title')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" placeholder="Office Performance Commitment and Review" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="success_indicators" :value="__('Success Indicators')" />
                        <textarea id="success_indicators" name="success_indicators" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List success indicators for the office.">{{ old('success_indicators') }}</textarea>
                        <x-input-error :messages="$errors->get('success_indicators')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="targets" :value="__('Targets')" />
                        <textarea id="targets" name="targets" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List target outputs, timelines, and expected results.">{{ old('targets') }}</textarea>
                        <x-input-error :messages="$errors->get('targets')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('opcrs.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>Create OPCR</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
