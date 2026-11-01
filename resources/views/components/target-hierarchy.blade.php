@props(['target'])
@php
    $viewer = auth()->user();
    $hierarchy = app(\App\Services\TargetHierarchy::class);
    $managed = $hierarchy->managedOfficeIds($viewer);
    $global = $viewer->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director']);
    $visibleOffices = $target->offices->filter(fn ($office) => $global || in_array($office->id, $managed) || (int) $office->id === (int) $viewer->office_id);
@endphp
<section class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-5">
    <h2 class="font-bold">Assignment hierarchy</h2>
    <p class="mt-2 text-sm">Direct individual assignments remain under their office and its reporting heads.</p>
    @foreach($visibleOffices as $office)
        @php
            $path = collect(app(\App\Services\PlanningOrganization::class)->ancestors($office))->reverse()->map(fn ($id) => \App\Models\Office::find($id)?->name)->join(' → ');
            $upcr = $office->pivot->upcr_id ? \App\Models\Upcr::find($office->pivot->upcr_id) : null;
            $canLink = $viewer->hasAnyRole(['Administrator', 'Planning Officer']) || in_array($office->id, $managed);
            $people = $target->assignedEmployees()->where('users.office_id', $office->id)->get();
            $ipcrs = \App\Models\Ipcr::where('office_target_id', $target->id)->whereIn('employee_id', $people->modelKeys())->get()->keyBy('employee_id');
            $candidates = $canLink ? $hierarchy->eligibleUpcrs($target, $office)->get() : collect();
        @endphp
        <div class="mt-4 rounded-lg border bg-white p-4">
            <h3 class="font-semibold">{{ $path }}</h3>
            <p class="mt-1 text-sm">Office head: {{ $office->head?->name ?? ($office->targetHeads->pluck('name')->join(', ') ?: 'Not configured') }}</p>
            <p class="mt-2 text-sm">UPCR:
                @if($upcr)
                    @if($global || in_array($office->id, $managed))<a class="font-semibold underline" href="{{ route('upcrs.show', $upcr) }}">{{ $upcr->title }}</a>@else {{ $upcr->title }} @endif
                    <span class="capitalize">({{ $upcr->status }})</span>
                @else
                    Pending office UPCR link for {{ $target->evaluationCycle?->label }}
                @endif
            </p>
            @forelse($people as $person)
                <p class="mt-2 text-sm">→ {{ $person->name }} · {{ $person->pivot->allocated_target_value }} {{ $target->unit_of_measure }}
                    @if($ipcrs->has($person->id))
                        @if($global || in_array($office->id, $managed) || $person->id === $viewer->id)<a class="underline" href="{{ route('ipcrs.show', $ipcrs[$person->id]) }}">View IPCR</a>@endif
                    @else · IPCR not yet prepared @endif
                </p>
            @empty
                <p class="mt-2 text-sm">Assigned to {{ $office->name }}. Its head can delegate this target to registered personnel.</p>
            @endforelse
            @if($canLink && $target->evaluationCycle?->isOpen())
                @if($candidates->isNotEmpty())
                    <form method="POST" action="{{ route('office-targets.link-upcr', $target) }}" class="mt-3 flex flex-wrap gap-2">@csrf
                        <input type="hidden" name="office_id" value="{{ $office->id }}">
                        <select name="upcr_id" aria-label="UPCR for {{ $office->name }}" required class="rounded-lg border-gray-300 text-sm">
                            <option value="">Select office UPCR</option>
                            @foreach($candidates as $candidate)<option value="{{ $candidate->id }}" @selected($office->pivot->upcr_id === $candidate->id)>{{ $candidate->title }} ({{ $candidate->status }})</option>@endforeach
                        </select>
                        <button class="rounded-lg border px-3 py-2 text-sm font-semibold">Link office UPCR</button>
                    </form>
                @else
                    <p class="mt-2 text-xs">No matching UPCR exists for this office and period. The office assignment and head visibility are retained.</p>
                @endif
            @endif
        </div>
    @endforeach
</section>
