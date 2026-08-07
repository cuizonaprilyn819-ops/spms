<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Create UPCR') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('upcrs.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="dpcr_id" :value="__('Linked DPCR')" />
                        <select id="dpcr_id" name="dpcr_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                            <option value="">Select DPCR</option>
                            @foreach ($dpcrs as $dpcr)
                                <option value="{{ $dpcr->id }}" @selected(old('dpcr_id') == $dpcr->id)>
                                    {{ $dpcr->title }} - {{ $dpcr->employee->name }} ({{ $dpcr->opcr->office->name }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('dpcr_id')" class="mt-2" />
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
                        <x-input-label for="title" :value="__('UPCR Title')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" placeholder="Unit Performance Commitment and Review" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="success_indicators" :value="__('Success Indicators')" />
                        <textarea id="success_indicators" name="success_indicators" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List UPCR success indicators.">{{ old('success_indicators') }}</textarea>
                        <x-input-error :messages="$errors->get('success_indicators')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="targets" :value="__('Targets')" />
                        <textarea id="targets" name="targets" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List targets aligned with the DPCR.">{{ old('targets') }}</textarea>
                        <x-input-error :messages="$errors->get('targets')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('upcrs.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>Create UPCR</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
