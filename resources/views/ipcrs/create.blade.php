<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Prepare My IPCR') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('ipcrs.store') }}" class="space-y-6">
                    @csrf

                    @if($sourceTarget)
                        <input type="hidden" name="office_target_id" value="{{ $sourceTarget->id }}">
                        <p class="rounded-lg bg-blue-50 p-4 text-sm">Preparing an IPCR from your completed target: {{ $sourceTarget->title }}</p>
                    @else
                    @if($upcrs->isEmpty())
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">No approved UPCR is available for your office. Your Supervisor must submit and obtain approval for the UPCR first.</div>
                    @endif

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
                    @endif

                    <div>
                        <x-input-label for="employee_id" value="Responsible Employee" />
                        <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-lg border-gray-300" required>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('employee_id', Auth::id()) == $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('employee_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="title" :value="__('IPCR Title')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $sourceTarget?->title)" placeholder="Individual Performance Commitment and Review" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="success_indicators" :value="__('Success Indicators')" />
                        <textarea id="success_indicators" name="success_indicators" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List IPCR success indicators.">{{ old('success_indicators', $sourceTarget?->success_indicator) }}</textarea>
                        <x-input-error :messages="$errors->get('success_indicators')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="targets" :value="__('Targets')" />
                        <textarea id="targets" name="targets" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="List individual targets.">{{ old('targets', $sourceTarget?->success_indicator) }}</textarea>
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
