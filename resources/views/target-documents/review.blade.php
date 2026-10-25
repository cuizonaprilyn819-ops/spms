<x-app-layout>
    @php($proposal = $draft->proposal)
    @php($suggestions = collect($proposal['suggested_offices'])->whereIn('office_id', $offices->modelKeys())->keyBy('office_id'))
    @php($selected = array_map('intval', old('office_ids', $suggestions->keys()->all())))
    <x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">Review Target and Assign Offices</h2></x-slot>
    <div class="mx-auto max-w-5xl space-y-5 px-4 py-8">
        <a href="{{ route('target-documents.show', $draft->document) }}" class="text-sm font-semibold text-spms-navy">← All extracted targets</a>
        <section class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-bold">{{ $proposal['title'] }}</h2>
            <p class="mt-2 text-sm text-gray-600">{{ $proposal['classification_reason'] }}</p>
            <blockquote class="mt-4 whitespace-pre-line border-l-4 border-blue-200 pl-4 text-sm">{{ $proposal['source_excerpt'] }}</blockquote>
        </section>
        @if($draft->office_target_id)
            <p class="rounded-xl bg-green-50 p-6">This target is already assigned. <a class="font-bold underline" href="{{ route('office-targets.show', $draft->office_target_id) }}">View assignment</a></p>
        @elseif($proposal['cd_only'])
            <p class="rounded-xl bg-amber-50 p-6">CD-only: this target is retained for the campus director and excluded from office assignment.</p>
        @else
            <form method="POST" action="{{ route('target-drafts.assign', $draft) }}" novalidate class="space-y-5 rounded-xl border border-gray-200 bg-white p-6" x-data="{
                busy: false, search: '', feedback: '',
                submitAssignment(event) {
                    if (this.busy) { event.preventDefault(); return; }
                    const form = event.target;
                    this.feedback = '';
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        this.search = '';
                        this.feedback = 'Complete the required target details, enter a quantity for each selected office, and check the confirmation box.';
                        this.$nextTick(() => form.reportValidity());
                        return;
                    }
                    const offices = Array.from(form.querySelectorAll('input[name=&quot;office_ids[]&quot;]:checked'));
                    const total = offices.reduce((sum, office) => sum + Math.round(Number(form.elements.namedItem('allocations[' + office.value + ']').value) * 100), 0);
                    if (!offices.length || total !== Math.round(Number(form.elements.namedItem('target_value').value) * 100)) {
                        event.preventDefault();
                        this.feedback = offices.length ? 'Office quantities must add up to the total quantity.' : 'Select at least one office to assign this target.';
                        return;
                    }
                    this.busy = true;
                }
            }" @submit="submitAssignment($event)">
                @csrf
                @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                <p class="text-sm text-gray-600">Correct the extracted details, select one or more offices, and distribute the total quantity. Suggested offices are preselected; you can remove them or add others.</p>
                <div><label for="review-title" class="text-sm font-semibold">Target title</label><input id="review-title" name="title" maxlength="255" required value="{{ old('title', $proposal['title']) }}" class="mt-1 w-full rounded-lg border-gray-300"/></div>
                <div><label for="review-indicator" class="text-sm font-semibold">Success indicator</label><textarea id="review-indicator" name="success_indicator" rows="3" required maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300">{{ old('success_indicator', $proposal['success_indicator']) }}</textarea></div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label for="review-value" class="text-sm font-semibold">Total quantity</label><input id="review-value" name="target_value" type="number" min="0.01" step="0.01" required value="{{ old('target_value', $proposal['target_value']) }}" class="mt-1 w-full rounded-lg border-gray-300"/></div>
                    <div><label for="review-unit" class="text-sm font-semibold">Unit of measure</label><input id="review-unit" name="unit_of_measure" maxlength="100" required value="{{ old('unit_of_measure', $proposal['unit_of_measure']) }}" class="mt-1 w-full rounded-lg border-gray-300"/></div>
                    <div><label for="review-weight" class="text-sm font-semibold">Weight (%)</label><input id="review-weight" name="weight" type="number" min="0.01" max="100" step="0.01" required value="{{ old('weight', $proposal['weight']) }}" class="mt-1 w-full rounded-lg border-gray-300"/></div>
                    <div><label for="review-deadline" class="text-sm font-semibold">Deadline</label><input id="review-deadline" name="deadline" type="date" required value="{{ old('deadline', $proposal['deadline']) }}" class="mt-1 w-full rounded-lg border-gray-300"/></div>
                    <div><label for="review-priority" class="text-sm font-semibold">Priority</label><select id="review-priority" name="priority" class="mt-1 w-full rounded-lg border-gray-300">@foreach(App\Models\OfficeTarget::PRIORITIES as $priority)<option @selected(old('priority', 'medium') === $priority) value="{{ $priority }}">{{ ucfirst($priority) }}</option>@endforeach</select></div>
                </div>
                <fieldset><legend class="font-bold">Assigned offices and allocations</legend>
                    <label for="review-search" class="sr-only">Search offices</label><input id="review-search" type="search" x-model="search" @keydown.enter.prevent placeholder="Search office or campus…" class="my-3 w-full rounded-lg border-gray-300"/>
                    <div class="max-h-96 space-y-3 overflow-y-auto">
                        @foreach($offices->sortByDesc(fn ($office) => $suggestions->has($office->id)) as $office)
                            <div x-data="{ selected: {{ in_array($office->id, $selected) ? 'true' : 'false' }} }" data-office="{{ $office->assignment_label }}" x-show="selected || $el.dataset.office.toLowerCase().includes(search.toLowerCase())" class="rounded-lg border border-gray-200 p-4">
                                <label class="flex gap-2 text-sm font-semibold"><input type="checkbox" name="office_ids[]" value="{{ $office->id }}" x-model="selected" class="rounded border-gray-300"/>{{ $office->assignment_label }}</label>
                                @if($suggestions->has($office->id))<p class="mt-2 text-xs text-blue-800">AI suggestion: {{ $suggestions[$office->id]['reason'] }}</p>@endif
                                <p class="mt-2 text-xs text-gray-500">{{ $office->targetHeads->isEmpty() ? 'No supervisor configured: assignment will be available when an office head is added.' : 'Will notify: '.$office->targetHeads->pluck('name')->join(', ') }}</p>
                                <div x-show="selected" class="mt-3"><label for="office-allocation-{{ $office->id }}" class="text-xs font-semibold">Office quantity</label><input id="office-allocation-{{ $office->id }}" name="allocations[{{ $office->id }}]" type="number" min="0.01" step="0.01" :disabled="!selected" :required="selected" value="{{ old('allocations.'.$office->id, count($selected) === 1 && in_array($office->id, $selected) ? $proposal['target_value'] : '') }}" class="ml-2 w-32 rounded-lg border-gray-300"/></div>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
                <div><label for="review-instructions" class="text-sm font-semibold">Additional instructions</label><textarea id="review-instructions" name="instructions" maxlength="5000" rows="3" class="mt-1 w-full rounded-lg border-gray-300">{{ old('instructions') }}</textarea></div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirmed" value="1" required class="mt-1 rounded border-gray-300"/>I reviewed the source, target details, and office allocations. Assign this target and send office-specific AI briefings to the office heads.</label>
                <p x-show="feedback" x-text="feedback" role="alert" aria-live="polite" class="text-sm font-semibold text-red-700"></p>
                <button type="submit" :disabled="busy" class="rounded-lg bg-spms-navy px-5 py-3 text-sm font-semibold text-white disabled:opacity-50" x-text="busy ? 'Preparing office briefings and assigning…' : 'Confirm and assign to offices'">Confirm and assign to offices</button>
                <p class="text-xs text-gray-500">AI will summarize the work and suggest self-assignment or delegation. The supervisor makes the final decision.</p>
            </form>
        @endif
    </div>
</x-app-layout>
