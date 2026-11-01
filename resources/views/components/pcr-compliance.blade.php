@props(['report', 'type'])
@php
    $compliance = app(\App\Services\PcrCompliance::class);
    $cycle = $compliance->cycle($report);
    $submission = $report->submissionRecord;
    $person = $report->employee ?? $report->preparedBy;
@endphp
@if($cycle)
<section class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
    <h3 class="font-bold">PCR submission timeliness</h3>
    <p class="mt-2 text-sm">Submission deadline: {{ ($submission?->due_at ?? $cycle->submissionDueAt())->format('F j, Y H:i T') }}</p>
    @if($submission)
        <p class="mt-1 text-sm">First submitted ({{ $submission->phase === 'commitment' ? 'commitments' : 'performance report' }}): {{ $submission->first_submitted_at->format('F j, Y H:i T') }} · <strong>{{ $submission->isLate() ? 'Late' : 'On time' }}</strong></p>
    @elseif($report->submitted_at || in_array($report->status, ['submitted', 'resubmitted', 'approved']))
        <p class="mt-1 text-sm">Legacy submission: the original first-submission deadline was not recorded.</p>
    @else
        <p class="mt-1 text-sm">{{ now()->gt($cycle->submissionDueAt()) ? 'Overdue — not submitted' : 'Not yet submitted' }}</p>
    @endif
    @if($type === 'ipcr' && $person)
        <p class="mt-3 text-sm font-semibold">Submission before deadline — evidence for IPCR Timeliness (T)</p>
        @foreach($compliance->summary($person, $cycle) as $item)
            <p class="mt-1 text-sm">{{ $item['type'] }}: <strong>{{ $item['status'] }}</strong>@if($item['submitted_at']) · {{ $item['submitted_at']->format('F j, Y H:i T') }}@endif</p>
        @endforeach
        <p class="mt-2 text-xs">The reviewer considers this record when rating Timeliness. Returns and resubmissions remain in the activity history and do not replace the first-submission record.</p>
    @endif
</section>
@endif
