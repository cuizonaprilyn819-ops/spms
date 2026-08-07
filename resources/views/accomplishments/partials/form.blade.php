<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-input-label for="performance_target_id" :value="__('Performance Target')" />
        <select id="performance_target_id" name="performance_target_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" required>
            <option value="">Select target</option>
            @foreach ($targets as $target)
                <option value="{{ $target->id }}" @selected((string) old('performance_target_id', $accomplishment?->performance_target_id) === (string) $target->id)>
                    {{ $target->target }} - {{ $target->targetable?->title ?? 'Performance form' }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('performance_target_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="details" :value="__('Accomplishment Details')" />
        <textarea id="details" name="details" rows="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky" placeholder="Describe what has been accomplished." required>{{ old('details', $accomplishment?->details) }}</textarea>
        <x-input-error :messages="$errors->get('details')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="completion_percentage" :value="__('Completion Percentage')" />
        <x-text-input id="completion_percentage" name="completion_percentage" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full" :value="old('completion_percentage', $accomplishment?->completion_percentage)" required />
        <x-input-error :messages="$errors->get('completion_percentage')" class="mt-2" />
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('accomplishments.index') }}" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-spms-ink transition hover:bg-gray-50">Cancel</a>
        <x-primary-button>{{ $submit }}</x-primary-button>
    </div>
</form>
