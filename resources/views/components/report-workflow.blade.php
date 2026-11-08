@props(['report', 'type'])
@php($reportContext = app(\App\Services\ReportTargetContext::class)->display($report))
@if(($reportContext['strategic_program'] ?? null) || ($reportContext['program_outcome'] ?? null))
<section class="mb-4 space-y-3 rounded-lg border bg-white p-4 text-sm">
<div><h3 class="font-bold">Strategic Program</h3><p class="mt-1 whitespace-pre-line">{{ $reportContext['strategic_program'] ?? 'Not specified in source' }}</p></div>
<div><h3 class="font-bold">Program Outcome</h3><p class="mt-1 whitespace-pre-line">{{ $reportContext['program_outcome'] ?? 'Not specified in source' }}</p></div>
@if($reportContext['paraphrased_at'] ?? null)<p class="text-xs text-gray-500">AI wording prepared for review. Compare with the source before submission.</p>@endif
</section>
@endif
@php($pcrCycle = app(\App\Services\PcrCompliance::class)->cycle($report))
@if($report->commitmentPlan)
    <div class="spms-alert rounded border border-blue-200 bg-blue-50 p-4" data-tone="info"><a class="font-semibold underline" href="{{ route('planning.show',$report->commitmentPlan) }}">Open commitment planning and its revision history</a><p>Commitments {{ $report->commitmentPlan->state }} · Final performance report {{ $report->status }}</p></div>
@endif
@php($reviewable = in_array($report->status, [$report::STATUS_SUBMITTED, $report::STATUS_RESUBMITTED], true))
<section class="mt-6 rounded-lg border border-gray-200 bg-white p-5">
    @php($canReview = $reviewable && Auth::user()->hasAnyRole(['Administrator', 'Planning Officer', 'HR Head', 'Campus Director', 'Supervisor', 'Division Head', 'Unit Head']) && (int) $report->prepared_by !== (int) Auth::id() && (int) $report->employee_id !== (int) Auth::id())
    @if($report->isEditableBy(Auth::id()))
        @php($evidenceIssues = app(\App\Services\ReportEvidence::class)->issues($report))
        @if($evidenceIssues)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><h3 class="font-semibold">MOV required before submission</h3><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($evidenceIssues as $issue)<li>{{ $issue }}</li>@endforeach</ul><p class="mt-2">Select each target, answer its questions, and upload the related MOV files together for AI analysis. The combined evidence must demonstrate the full assigned target.</p></div>
        @endif
    @endif
    @if($errors->has('mov'))<p role="alert" class="mb-4 text-sm text-red-700">{{ $errors->first('mov') }}</p>@endif
    <div class="flex flex-col gap-4 border-b border-gray-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Review status</p>
            <p class="mt-1 text-lg font-bold capitalize text-spms-ink">{{ str_replace('_', ' ', $report->status) }}</p>
            <p class="mt-2 text-xs leading-5 text-gray-500">Last updated {{ $report->updated_at->format('M d, Y h:i A') }}<span class="mx-1">·</span>Resubmissions: {{ $report->resubmission_count }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 self-start">
            @if($report->isEditableBy(Auth::id()))
                @unless($report->commitmentPlan)<a href="{{ route('report-workflow.edit', [$type, $report]) }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold">Edit report</a>@endunless
                <form method="POST" action="{{ route('report-workflow.submit', [$type, $report]) }}">@csrf @method('PATCH')
                    <button @disabled(!empty($evidenceIssues)) class="inline-flex items-center justify-center rounded-md bg-spms-navy px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ $report->status === $report::STATUS_RETURNED ? 'Resubmit' : 'Submit for review' }}</button>
                </form>
            @endif

        </div>
    </div>
    @if($canReview)
    <details id="return-corrections-{{ $type }}-{{ $report->id }}" class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4" @if($errors->has('remarks')) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-amber-800">Return for correction</summary>
        <form method="POST" action="{{ route('report-workflow.return', [$type, $report]) }}" class="mt-4 space-y-3">@csrf @method('PATCH')
            <x-input-label for="remarks-{{ $type }}-{{ $report->id }}" value="Required corrections"/>
            <textarea id="remarks-{{ $type }}-{{ $report->id }}" name="remarks" required rows="3" maxlength="5000" class="block w-full rounded-md border-gray-300" placeholder="Explain the missing or incorrect information.">{{ old('remarks') }}</textarea>
            <x-input-error :messages="$errors->get('remarks')" />
            <button type="submit" class="inline-flex rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Confirm return</button>
        </form>
    </details>
    @endif
    @if($canReview)
        <form method="POST" action="{{ route('report-workflow.approve', [$type, $report]) }}" class="mt-5 space-y-4">@csrf @method('PATCH')
            @if($type === 'ipcr' && ($report->office_target_id || $pcrCycle?->pcr_submission_due_at))
                <fieldset class="space-y-4">
                    <legend class="text-base font-bold text-spms-ink">Reviewer ratings <span class="text-sm font-normal text-gray-500">(0–5)</span></legend>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        @foreach(['quality_rating' => 'Quality (Q)', 'efficiency_rating' => 'Efficiency (E)', 'timeliness_rating' => 'Timeliness (T)'] as $field => $label)
                            <div>
                                <label for="{{ $field }}-{{ $type }}-{{ $report->id }}" class="block text-sm font-semibold text-gray-700">{{ $label }}</label>
                                <input id="{{ $field }}-{{ $type }}-{{ $report->id }}" name="{{ $field }}" type="number" min="0" max="5" step="0.01" required value="{{ old($field) }}" class="mt-2 block w-full rounded-md border-gray-300">
                                <x-input-error :messages="$errors->get($field)" class="mt-1" />
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs leading-5 text-gray-500">Average is calculated as (Q + E + T) ÷ 3 when approved.</p>
                    @if($pcrCycle?->pcr_submission_due_at)<p class="text-xs text-blue-800">Consider the PCR submission record below when assessing Timeliness (T).</p>@endif
                    <div>
                        <label for="rating-remarks-{{ $type }}-{{ $report->id }}" class="block text-sm font-semibold text-gray-700">Rating remarks</label>
                        <textarea id="rating-remarks-{{ $type }}-{{ $report->id }}" name="rating_remarks" rows="3" maxlength="5000" class="mt-2 block w-full rounded-md border-gray-300">{{ old('rating_remarks') }}</textarea>
                        <x-input-error :messages="$errors->get('rating_remarks')" class="mt-1" />
                    </div>
                </fieldset>
            @endif
            <div class="flex justify-end border-t border-gray-100 pt-4"><button class="inline-flex items-center justify-center rounded-md bg-spms-green px-5 py-2.5 text-sm font-semibold text-white">Approve</button></div>
        </form>
    @endif
    <x-pcr-compliance :report="$report" :type="$type" />
    @if($type === 'ipcr' && $report->average_rating !== null)
        <p class="mt-4 text-sm font-semibold">Reviewer ratings: Q {{ $report->quality_rating }} · E {{ $report->efficiency_rating }} · T {{ $report->timeliness_rating }} · Average {{ number_format($report->average_rating, 2) }}</p>
    @endif
    @if($report->statusHistories->isNotEmpty())
        <div class="mt-5 border-t pt-4"><h3 class="font-bold text-spms-ink">Activity history</h3>
            <ol class="mt-3 space-y-3">@foreach($report->statusHistories as $history)
                <li class="text-sm"><span class="font-semibold capitalize">{{ str_replace('_', ' ', $history->to_status) }}</span> by {{ $history->actor?->name ?? 'Deleted user' }}
                    <span class="text-gray-500">· {{ $history->created_at->format('M d, Y h:i:s A') }}</span>
                    @if($history->remarks)<p class="spms-alert mt-1 rounded bg-amber-50 p-2 text-gray-700" data-tone="warning">{{ $history->remarks }}</p>@endif
                </li>
            @endforeach</ol>
        </div>
    @endif
</section>
