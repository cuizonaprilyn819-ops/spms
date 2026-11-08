@props(['target'])
@php
    $user = Auth::user();
    $assignment = $target->offices()->whereKey($user->office_id)->exists()
        ? $target->assignedEmployees()->whereKey($user->id)->first()
        : null;
@endphp
@if($assignment?->pivot?->accepted_at)
<section aria-labelledby="target-report-heading" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm" data-action-plain>
    <h2 id="target-report-heading" class="text-lg font-bold text-spms-ink">IPCR Report</h2>
    <p class="mt-2 text-sm text-gray-600">Prepare your report for this target, including zero or partial accomplishment, for supervisor review.</p>
    <a href="{{ route('ipcrs.create', ['office_target_id' => $target->id]) }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-spms-navy hover:bg-gray-50">Prepare My IPCR Report</a>
</section>
@endif
