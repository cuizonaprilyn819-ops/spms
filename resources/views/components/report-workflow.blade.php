@props(['report', 'type'])
@php($reviewable = in_array($report->status, [$report::STATUS_SUBMITTED, $report::STATUS_RESUBMITTED], true))
<section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" x-data="{ returning: false }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase text-gray-500">Review status</p>
            <p class="mt-1 text-lg font-bold capitalize text-spms-ink">{{ str_replace('_', ' ', $report->status) }}</p>
            <p class="text-xs text-gray-500">Last updated {{ $report->updated_at->format('M d, Y h:i A') }} · Resubmissions: {{ $report->resubmission_count }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($report->isEditableBy(Auth::id()))
                <a href="{{ route('report-workflow.edit', [$type, $report]) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold">Edit report</a>
                <form method="POST" action="{{ route('report-workflow.submit', [$type, $report]) }}">@csrf @method('PATCH')
                    <button class="rounded-md bg-spms-navy px-4 py-2 text-sm font-semibold text-white">{{ $report->status === $report::STATUS_RETURNED ? 'Resubmit' : 'Submit for review' }}</button>
                </form>
            @endif
            @hasanyrole('Administrator|HR Head|Campus Director|Supervisor')
                @if($reviewable)
                    <button type="button" @click="returning = !returning" class="rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white">Return for correction</button>
                    <form method="POST" action="{{ route('report-workflow.approve', [$type, $report]) }}">@csrf @method('PATCH')
                        @if($type === 'ipcr' && $report->office_target_id)
                            <div class="mb-3 space-y-2">
                                <p class="text-sm font-semibold">Reviewer ratings (1–5)</p>
                                @foreach(['quality_rating' => 'Quality (Q)', 'efficiency_rating' => 'Efficiency (E)', 'timeliness_rating' => 'Timeliness (T)'] as $field => $label)
                                    <label class="block text-sm">{{ $label }}
                                        <input name="{{ $field }}" type="number" min="1" max="5" step="0.01" required value="{{ old($field) }}" class="block w-full rounded-md border-gray-300">
                                    </label>
                                    <x-input-error :messages="$errors->get($field)" />
                                @endforeach
                                <p class="text-xs text-gray-500">Average is calculated as (Q + E + T) ÷ 3 when approved.</p>
                                <label class="block text-sm">Rating remarks<textarea name="rating_remarks" maxlength="5000" class="block w-full rounded-md border-gray-300">{{ old('rating_remarks') }}</textarea></label>
                                <x-input-error :messages="$errors->get('rating_remarks')" />
                            </div>
                        @endif
                        <button class="rounded-md bg-spms-green px-4 py-2 text-sm font-semibold text-white">Approve</button>
                    </form>
                @endif
            @endhasanyrole
        </div>
    </div>
    @if($type === 'ipcr' && $report->average_rating !== null)
        <p class="mt-4 text-sm font-semibold">Reviewer ratings: Q {{ $report->quality_rating }} · E {{ $report->efficiency_rating }} · T {{ $report->timeliness_rating }} · Average {{ number_format($report->average_rating, 2) }}</p>
    @endif
    <form x-show="returning" x-cloak method="POST" action="{{ route('report-workflow.return', [$type, $report]) }}" class="mt-4 rounded-md bg-amber-50 p-4">@csrf @method('PATCH')
        <x-input-label for="remarks-{{ $type }}" value="Required corrections"/>
        <textarea id="remarks-{{ $type }}" name="remarks" required rows="3" class="mt-1 block w-full rounded-md border-gray-300" placeholder="Explain the error or missing information."></textarea>
        <button class="mt-3 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Confirm return</button>
    </form>
    @if($report->statusHistories->isNotEmpty())
        <div class="mt-5 border-t pt-4"><h3 class="font-bold text-spms-ink">Activity history</h3>
            <ol class="mt-3 space-y-3">@foreach($report->statusHistories as $history)
                <li class="text-sm"><span class="font-semibold capitalize">{{ str_replace('_', ' ', $history->to_status) }}</span> by {{ $history->actor?->name ?? 'Deleted user' }}
                    <span class="text-gray-500">· {{ $history->created_at->format('M d, Y h:i:s A') }}</span>
                    @if($history->remarks)<p class="mt-1 rounded bg-amber-50 p-2 text-gray-700">{{ $history->remarks }}</p>@endif
                </li>
            @endforeach</ol>
        </div>
    @endif
</section>
