<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Add Target') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('performance-targets.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="targetable" :value="__('Linked Performance Form')" />
                        <select id="targetable" name="targetable" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required onchange="const [type,id] = this.value.split(':'); document.getElementById('targetable_type').value = type || ''; document.getElementById('targetable_id').value = id || '';">
                            <option value="">Select OPCR, DPCR, UPCR, or IPCR</option>
                            @foreach ($targetables as $group => $records)
                                <optgroup label="{{ $group }}">
                                    @foreach ($records as $record)
                                        @php
                                            $type = strtolower($group);
                                            $value = $type.':'.$record->id;
                                        @endphp
                                        <option value="{{ $value }}" @selected(old('targetable') === $value)>{{ $record->title }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <input type="hidden" id="targetable_type" name="targetable_type" value="{{ old('targetable_type') }}">
                        <input type="hidden" id="targetable_id" name="targetable_id" value="{{ old('targetable_id') }}">
                        <x-input-error :messages="$errors->get('targetable_type')" class="mt-2" />
                        <x-input-error :messages="$errors->get('targetable_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="target" :value="__('Target')" />
                        <x-text-input id="target" name="target" type="text" class="mt-1 block w-full" :value="old('target')" placeholder="Example: Submit quarterly accomplishment report" required />
                        <x-input-error :messages="$errors->get('target')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="success_indicator" :value="__('Success Indicator')" />
                        <textarea id="success_indicator" name="success_indicator" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="Describe how this target will be measured." required>{{ old('success_indicator') }}</textarea>
                        <x-input-error :messages="$errors->get('success_indicator')" class="mt-2" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="weight" :value="__('Weight (%)')" />
                            <x-text-input id="weight" name="weight" type="number" min="0.01" max="100" step="0.01" class="mt-1 block w-full" :value="old('weight')" required />
                            <x-input-error :messages="$errors->get('weight')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="deadline" :value="__('Deadline')" />
                            <x-text-input id="deadline" name="deadline" type="date" class="mt-1 block w-full" :value="old('deadline')" required />
                            <x-input-error :messages="$errors->get('deadline')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('performance-targets.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>Add Target</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
