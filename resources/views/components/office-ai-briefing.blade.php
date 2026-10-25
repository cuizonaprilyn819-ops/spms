@props(['target'])
@php($viewer = auth()->user())
@php($visibleOffices = $viewer->hasRole('Administrator') || $target->created_by === $viewer->id ? $target->offices : $target->offices->where('id', $viewer->office_id))
@foreach($visibleOffices as $briefingOffice)
    @php($briefing = json_decode($briefingOffice->pivot->ai_briefing ?? 'null', true))
    @if($briefing)
        <section class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-5">
            <h3 class="font-bold text-spms-navy">AI office briefing · {{ $briefingOffice->name }}</h3>
            <p class="mt-3 whitespace-pre-line text-sm">{{ $briefing['wording'] }}</p>
            <p class="mt-3 text-sm"><strong>Summary:</strong> {{ $briefing['summary'] }}</p>
            <p class="mt-3 text-sm"><strong>Suggestion:</strong> {{ $briefing['recommendation'] === 'self' ? 'Supervisor handles the target personally.' : 'Consider delegating to employees in your office.' }} {{ $briefing['reason'] }}</p>
            <p class="mt-3 text-xs text-gray-600">Advisory only. Original target details and your office allocation govern the assignment. The supervisor chooses “Assign to myself” or employees below and confirms the allocations.</p>
        </section>
    @endif
@endforeach
