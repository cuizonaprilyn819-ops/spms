<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Create IPCR') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('ipcrs.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="upcr_id" :value="__('Linked UPCR')" />
                        <select id="upcr_id" name="upcr_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                            <option value="">Select UPCR</option>
                            @foreach ($upcrs as $upcr)
                                <option value="{{ $upcr->id }}" @selected(old('upcr_id') == $upcr->id)>
                                    {{ $upcr->title }} - {{ $upcr->employee->name }} ({{ $upcr->dpcr->opcr->office->name }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('upcr_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="employee_id" :value="__('Assigned Employee')" />
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
                        <x-input-label for="title" :value="__('IPCR Title')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" placeholder="Individual Performance Commitment and Review" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="success_indicators" :value="__('Success Indicators')" />
                        <textarea id="success_indicators" name="success_indicators" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List IPCR success indicators.">{{ old('success_indicators') }}</textarea>
                        <x-input-error :messages="$errors->get('success_indicators')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="targets" :value="__('Targets')" />
                        <textarea id="targets" name="targets" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List individual targets.">{{ old('targets') }}</textarea>
                        <x-input-error :messages="$errors->get('targets')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('ipcrs.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>Create IPCR</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
