@props(['evidence'])
@php($analysis = $evidence->analysis)
@php($type = $evidence instanceof \App\Models\Mov ? 'mov' : ($evidence instanceof \App\Models\PlanningOutput ? 'planning-output' : 'target-mov'))
@php($owner = (int) ($type === 'mov' ? $evidence->uploaded_by : ($type === 'planning-output' ? $evidence->submitted_by : $evidence->user_id)) === (int) Auth::id())
@php($processing = $analysis?->isProcessing())
<section class="mt-5 rounded-lg border border-blue-200 bg-blue-50/40 p-4" @if($owner && $processing) data-evidence-status="{{ route('evidence.status', [$type, $evidence->id]) }}" data-processing-key="{{ $type }}:{{ $evidence->submission_context['batch_id'] ?? $evidence->id }}" @endif>
    <h3 class="font-bold text-spms-navy">MOV analysis and report statement</h3>
    <p class="mt-1 text-xs text-gray-600">Formal, concise English; past tense for evidence-supported completed work. AI statements do not change completion or ratings.</p>
    @if($evidence->submission_context['questions'] ?? null)
        <details class="mt-3 text-sm"><summary class="cursor-pointer font-semibold">Your submitted answers</summary><dl class="mt-3 space-y-3">@foreach($evidence->submission_context['questions'] as $question)<div><dt class="font-semibold">{{ $question['label'] }}</dt><dd class="mt-1 whitespace-pre-line">{{ $evidence->submission_context['answers'][$question['id']] ?? 'Not provided' }}</dd></div>@endforeach</dl></details>
    @endif
    @if($analysis?->status === 'completed' && !$processing)
        @if($analysis->findings['user_data_summary'] ?? null)<h4 class="mt-4 text-sm font-bold">User-provided data</h4><p class="mt-1 whitespace-pre-line text-sm">{{ $analysis->findings['user_data_summary'] }}</p>@endif
        @if($analysis->findings['file_findings_summary'] ?? null)<h4 class="mt-4 text-sm font-bold">Findings from uploaded files</h4><p class="mt-1 whitespace-pre-line text-sm">{{ $analysis->findings['file_findings_summary'] }}</p>@endif
        @if($analysis->findings['discrepancies'] ?? null)<h4 class="mt-4 text-sm font-bold text-amber-800">Discrepancies or missing evidence</h4><p class="mt-1 whitespace-pre-line text-sm text-amber-800">{{ $analysis->findings['discrepancies'] }}</p>@endif
        @if($analysis->findings['batch_evidence_ids'] ?? null)<p class="mt-2 text-xs text-gray-500">{{ count($analysis->findings['batch_evidence_ids']) }} uploaded files analyzed together. Repeated copies are not additional accomplishments.</p>@endif
        @if($analysis->findings['source_excerpts'] ?? null)<details class="mt-3 text-sm"><summary class="cursor-pointer font-semibold">Evidence references</summary><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($analysis->findings['source_excerpts'] as $excerpt)<li>{{ $excerpt }}</li>@endforeach</ul></details>@endif
        <p class="mt-3 text-sm font-semibold">Target assessment: {{ ($analysis->findings['satisfies_target'] ?? false) ? 'Evidence satisfies the target' : 'Not confirmed sufficient — review or retry analysis' }}</p>
        @if($analysis->findings['target_assessment'] ?? null)<p class="mt-2 text-sm">{{ $analysis->findings['target_assessment'] }}</p>@endif
        <p class="mt-3 whitespace-pre-line text-sm">{{ $analysis->summary }}</p>
        @if($analysis->findings['limitations'] ?? null)<p class="mt-2 text-sm text-amber-800">{{ $analysis->findings['limitations'] }}</p>@endif
        @if($analysis->statement)
            <p class="mt-3 text-xs font-semibold uppercase">Statement used in reports</p>
            <p class="mt-1 whitespace-pre-line text-sm">{{ $analysis->statement }}</p>
            @if($owner)
                <form method="POST" action="{{ route('evidence.statement', [$type, $evidence->id]) }}" class="mt-3 space-y-2">
                    @csrf @method('PUT')
                    <label for="statement-{{ $type }}-{{ $evidence->id }}" class="text-sm font-semibold">Review or edit report statement</label>
                    <textarea id="statement-{{ $type }}-{{ $evidence->id }}" name="statement" rows="3" maxlength="5000" required class="w-full rounded-lg border-gray-300">{{ $analysis->statement }}</textarea>
                    <button class="rounded-lg bg-spms-navy px-3 py-2 text-sm font-semibold text-white">Save statement</button>
                </form>
            @endif
        @else
            <p class="mt-3 text-sm text-amber-800">No supported report statement could be generated from this file.</p>
        @endif
        <details class="mt-3 text-sm"><summary class="cursor-pointer font-semibold">Evidence references</summary><ul class="mt-2 list-disc pl-5">@foreach($analysis->findings['source_excerpts'] ?? [] as $excerpt)<li>{{ $excerpt }}</li>@endforeach</ul></details>
    @elseif($analysis?->status === 'failed' && !$processing)
        <p class="mt-3 text-sm text-amber-800">{{ $analysis->error }}</p>
    @elseif($processing)
        <p role="status" class="mt-3 text-sm">Processing MOV… You can leave this page. Your original files are saved; results will update here automatically.</p>
        @if($analysis->updated_at->lt(now()->subMinutes(15)))<p class="mt-2 text-sm text-amber-800">Processing is taking longer than expected. You can retry or ask your administrator to check processing.</p>@endif
    @else
        <p class="mt-3 text-sm text-gray-600">This MOV has not been analyzed yet.</p>
    @endif
    @if($owner)
        <form method="POST" action="{{ route('evidence.analyze', [$type, $evidence->id]) }}" class="mt-3">@csrf
            <button @disabled($processing && $analysis->updated_at->gt(now()->subMinutes(15))) class="rounded-lg border border-blue-300 px-3 py-2 text-sm font-semibold text-spms-navy disabled:opacity-50">{{ $processing ? 'Processing MOV' : ($analysis ? 'Retry MOV analysis' : 'Analyze MOV and draft report statement') }}</button>
        </form>
        <x-input-error :messages="$errors->get('statement')" class="mt-2" />
    @endif
</section>
