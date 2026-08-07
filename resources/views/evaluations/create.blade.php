<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-spms-ink">{{ __('Rate Employee') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('evaluations.store') }}" class="space-y-6" x-data="{ quality: '{{ old('quality_rating', '') }}', efficiency: '{{ old('efficiency_rating', '') }}', timeliness: '{{ old('timeliness_rating', '') }}', weight: '', average() { const q = parseFloat(this.quality || 0); const e = parseFloat(this.efficiency || 0); const t = parseFloat(this.timeliness || 0); return ((q + e + t) / 3).toFixed(2); }, computed() { const targetWeight = parseFloat(this.weight || 0); return (parseFloat(this.average()) * (targetWeight / 100)).toFixed(2); } }">
                    @csrf

                    <div>
                        <x-input-label for="employee_id" :value="__('Employee')" />
                        <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                            <option value="">Select employee</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                                    {{ $employee->name }}{{ $employee->office ? ' - '.$employee->office->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('employee_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="performance_target_id" :value="__('Performance Target')" />
                        <select id="performance_target_id" name="performance_target_id" x-on:change="weight = $event.target.selectedOptions[0]?.dataset.weight || ''" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
                            <option value="" data-weight="">Select target</option>
                            @foreach ($targets as $target)
                                <option value="{{ $target->id }}" data-weight="{{ $target->weight }}" @selected(old('performance_target_id') == $target->id)>
                                    {{ $target->target }} - {{ number_format((float) $target->weight, 2) }}%
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('performance_target_id')" class="mt-2" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-3">
                        <div>
                            <x-input-label for="quality_rating" :value="__('Quality (Q)')" />
                            <x-text-input id="quality_rating" name="quality_rating" type="number" min="1" max="5" step="0.01" class="mt-1 block w-full" x-model="quality" :value="old('quality_rating')" placeholder="1.00 to 5.00" required />
                            <x-input-error :messages="$errors->get('quality_rating')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="efficiency_rating" :value="__('Efficiency (E)')" />
                            <x-text-input id="efficiency_rating" name="efficiency_rating" type="number" min="1" max="5" step="0.01" class="mt-1 block w-full" x-model="efficiency" :value="old('efficiency_rating')" placeholder="1.00 to 5.00" required />
                            <x-input-error :messages="$errors->get('efficiency_rating')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="timeliness_rating" :value="__('Timeliness (T)')" />
                            <x-text-input id="timeliness_rating" name="timeliness_rating" type="number" min="1" max="5" step="0.01" class="mt-1 block w-full" x-model="timeliness" :value="old('timeliness_rating')" placeholder="1.00 to 5.00" required />
                            <x-input-error :messages="$errors->get('timeliness_rating')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Average Rating (A)</p>
                            <p class="mt-2 text-3xl font-bold text-spms-navy" x-text="average()">0.00</p>
                            <p class="mt-1 text-xs text-gray-500">Formula: (Q + E + T) / 3</p>
                        </div>

                        <div class="rounded-lg border border-green-100 bg-green-50 p-4">
                            <p class="text-xs font-bold uppercase text-gray-500">Weighted Rating</p>
                            <p class="mt-2 text-3xl font-bold text-spms-green" x-text="computed()">0.00</p>
                            <p class="mt-1 text-xs text-gray-500">Formula: A x target weight / 100</p>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="remarks" :value="__('Remarks')" />
                        <textarea id="remarks" name="remarks" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="Add evaluator remarks or observations.">{{ old('remarks') }}</textarea>
                        <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('evaluations.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
                        <x-primary-button>Save Evaluation</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
