@props(['target'])
@hasanyrole('Supervisor|HR Head')
@php
    $head = Auth::user();
    $office = $target->offices()->whereKey($head->office_id)->first();
    $employees = \App\Models\User::where('office_id', $head->office_id)->whereKeyNot($head->id)->whereHas('roles', fn ($roles) => $roles->where('name', 'Employee'))->orderBy('name')->get();
    $people = collect([$head])->concat($employees);
    $assignments = $target->assignedEmployees()->where('users.office_id', $head->office_id)->get()->keyBy('id');
@endphp
@if($office)
<section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-bold text-spms-ink">Assign to Myself or Employees</h2>
    <p class="mt-2 text-sm text-gray-600">Office allocation: <strong>{{ $office->pivot->allocated_target_value ?? 'Not set' }} {{ $target->unit_of_measure }}</strong>.</p>
    <p class="mt-2 text-sm text-gray-500">Select yourself, employees, or both. Enter each person's share. Combined assignments must not exceed the office allocation. Unassigned amounts can be allocated later.</p>
    @if(session('status'))<p class="mt-3 text-sm text-green-700" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="mt-3 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="POST" action="{{ route('office-targets.assign-employees', $target) }}" class="mt-4 space-y-4">
        @csrf
        @foreach($people as $person)
            @php($assigned = $assignments->get($person->id))
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 p-3" x-data="{ selected: {{ in_array($person->id, old('employee_ids', $assignments->keys()->all())) ? 'true' : 'false' }} }">
                <label class="flex flex-1 items-center gap-2 text-sm">
                    <input type="checkbox" name="employee_ids[]" value="{{ $person->id }}" x-model="selected" class="rounded border-gray-300">
                    {{ $person->id === $head->id ? 'Assign to myself: '.$person->name : $person->name }}
                    @if($assigned)<span class="text-gray-500">(Assigned)</span>@endif
                </label>
                <div>
                    <label for="allocation-{{ $person->id }}" class="block text-xs text-gray-600">Allocation ({{ $target->unit_of_measure }})</label>
                    <input id="allocation-{{ $person->id }}" type="number" name="allocations[{{ $person->id }}]" min="0.01" step="0.01" max="{{ $office->pivot->allocated_target_value }}" :disabled="!selected" :required="selected" value="{{ old('allocations.'.$person->id, $assigned?->pivot->allocated_target_value) }}" class="mt-1 w-32 rounded-lg border-gray-300">
                </div>
            </div>
        @endforeach
        <p class="text-xs text-gray-500">Existing assignments remain if unchecked. Your own new assignment is accepted automatically.</p>
        <x-primary-button>Save Assignments</x-primary-button>
    </form>
</section>
@endif
@endhasanyrole
