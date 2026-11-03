<x-app-layout>
<x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">{{ $proposal ? 'Revise office target' : 'Add office target' }}</h2></x-slot>
<div class="mx-auto max-w-3xl space-y-5 px-4 py-8">
    <a href="{{ route('office-target-proposals.index') }}" class="text-sm font-semibold text-spms-navy">← Office target proposals</a>
    <p class="text-sm text-gray-600">Add a target for <strong>{{ auth()->user()->office->name }}</strong>. The Planning Officer validates it before it becomes active. Duration follows the selected evaluation period.</p>
    <p class="text-sm">For a period using commitment planning, use <a class="font-semibold underline" href="{{ route('planning.index') }}#proposals">Commitment Planning → Propose a target upward</a>.</p>
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    @if($proposal?->review_notes)<p class="rounded-lg bg-amber-50 p-4 text-sm">Required corrections: {{ $proposal->review_notes }}</p>@endif
    <form method="POST" action="{{ route('office-target-proposals.store') }}" class="space-y-5 rounded-xl border bg-white p-6">
        @csrf
        @if($proposal)<input type="hidden" name="proposal_id" value="{{ $proposal->id }}">@endif
        <label class="block text-sm font-semibold">Evaluation period<select name="evaluation_cycle_id" required class="mt-2 block w-full rounded-lg border-gray-300"><option value="">Select open period</option>@foreach($cycles as $cycle)<option value="{{ $cycle->id }}" @selected(old('evaluation_cycle_id', $proposal?->evaluation_cycle_id) == $cycle->id)>{{ $cycle->label }}</option>@endforeach</select></label>
        @foreach(['title' => ['Target title',255], 'program' => ['Strategic Program',5000], 'program_outcome' => ['Program Outcome',5000], 'success_indicator' => ['Success Indicator (Targets + Measures)',5000]] as $field => [$label,$length])
        <label class="block text-sm font-semibold">{{ $label }}<textarea name="{{ $field }}" required maxlength="{{ $length }}" rows="{{ $field === 'title' ? 1 : 3 }}" class="mt-2 block w-full rounded-lg border-gray-300">{{ old($field, $proposal?->proposal[$field] ?? '') }}</textarea></label>
        @endforeach
        <div class="grid gap-4 sm:grid-cols-3">
            <label class="text-sm font-semibold">Quantity<input name="target_value" type="number" min="0.01" max="9999999999.99" step="0.01" required value="{{ old('target_value', $proposal?->proposal['target_value'] ?? '') }}" class="mt-2 block w-full rounded-lg border-gray-300"></label>
            <label class="text-sm font-semibold">Unit of measure<input name="unit_of_measure" maxlength="100" required value="{{ old('unit_of_measure', $proposal?->proposal['unit_of_measure'] ?? '') }}" class="mt-2 block w-full rounded-lg border-gray-300"></label>
            <label class="text-sm font-semibold">Weight (%)<input name="weight" type="number" min="0.01" max="100" step="0.01" required value="{{ old('weight', $proposal?->proposal['weight'] ?? '') }}" class="mt-2 block w-full rounded-lg border-gray-300"></label>
        </div>
        <label class="block text-sm font-semibold">Priority<select name="priority" class="mt-2 block w-full rounded-lg border-gray-300">@foreach(App\Models\OfficeTarget::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(old('priority', $proposal?->proposal['priority'] ?? 'medium') === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
        <label class="block text-sm font-semibold">Why this target is needed<textarea name="justification" required rows="3" maxlength="5000" class="mt-2 block w-full rounded-lg border-gray-300">{{ old('justification', $proposal?->proposal['justification'] ?? '') }}</textarea></label>
        <button @disabled($cycles->isEmpty()) class="rounded-lg bg-spms-navy px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">Submit for validation</button>
    </form>
</div>
</x-app-layout>
