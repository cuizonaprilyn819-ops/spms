<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Create DPCR') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('dpcrs.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="opcr_id" :value="__('Linked OPCR')" />
                        <select id="opcr_id" name="opcr_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                            <option value="">Select OPCR</option>
                            @foreach ($opcrs as $opcr)
                                <option value="{{ $opcr->id }}" @selected(old('opcr_id') == $opcr->id)>
                                    {{ $opcr->title }} - {{ $opcr->office->name }} ({{ $opcr->evaluationCycle->label }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('opcr_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="employee_id" :value="__('Responsible Employee')" />
                        <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                            <option value="">Select employee</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                                    {{ $employee->name }} - {{ $employee->office?->name ?? 'Unassigned Office' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('employee_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="title" :value="__('DPCR Title')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" placeholder="Division Performance Commitment and Review" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="success_indicators" :value="__('Success Indicators')" />
                        <textarea id="success_indicators" name="success_indicators" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List DPCR success indicators.">{{ old('success_indicators') }}</textarea>
                        <x-input-error :messages="$errors->get('success_indicators')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="targets" :value="__('Targets')" />
                        <textarea id="targets" name="targets" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List targets aligned with the OPCR.">{{ old('targets') }}</textarea>
                        <x-input-error :messages="$errors->get('targets')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('dpcrs.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>Create DPCR</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
