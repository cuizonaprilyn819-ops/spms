@props(['evidence'])
@php($analysis = $evidence->analysis)
@php($type = $evidence instanceof \App\Models\Mov ? 'mov' : ($evidence instanceof \App\Models\PlanningOutput ? 'planning-output' : 'target-mov'))
@php($owner = (int) ($type === 'mov' ? $evidence->uploaded_by : ($type === 'planning-output' ? $evidence->submitted_by : $evidence->user_id)) === (int) Auth::id())
@php($processing = $analysis?->isProcessing())
<section class="mt-5 rounded-lg border border-blue-200 bg-blue-50/40 p-4" @if($owner && $processing) data-evidence-status="{{ route('evidence.status', [$type, $evidence->id]) }}" data-processing-key="{{ $type }}:{{ $evidence->submission_context['batch_id'] ?? $evidence->id }}" @endif>
    <h3 class="font-bold text-spms-navy">Evidence review</h3>
    @if($evidence->submission_context['questions'] ?? null)
        <details class="mt-3 text-sm"><summary class="cursor-pointer font-semibold">Your submitted answers</summary><dl class="mt-3 space-y-3">@foreach($evidence->submission_context['questions'] as $question)<div><dt class="font-semibold">{{ $question['label'] }}</dt><dd class="mt-1 whitespace-pre-line">{{ $evidence->submission_context['answers'][$question['id']] ?? 'Not provided' }}</dd></div>@endforeach</dl></details>
    @endif
    @if($analysis?->status === 'completed' && !$processing)
        @php($pendingQuestions = ! empty($analysis->findings['followup_questions']))
        @php($sufficient = ! $pendingQuestions && ($analysis->findings['satisfies_target'] ?? false) && ($analysis->findings['supported'] ?? false))
        <div class="mt-3 rounded-lg border p-4 {{ $sufficient ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50' }}">
            <p class="font-semibold {{ $sufficient ? 'text-green-800' : 'text-amber-800' }}">{{ $sufficient ? 'Evidence meets the target' : ($pendingQuestions ? 'A few details are needed' : 'More evidence is needed') }}</p>
            <p class="mt-2 text-sm leading-relaxed text-gray-700">{{ \Illuminate\Support\Str::limit($analysis->summary, 240) }}</p>
            <p class="mt-3 text-sm font-semibold">{{ $sufficient ? 'Next: review your report summary. Your supervisor makes the final decision.' : ($pendingQuestions ? 'Next: answer the questions below.' : 'Next: provide clearer or additional supporting files. Retrying the same file may give the same result.') }}</p>
            @if($owner && ! $sufficient && ! $pendingQuestions && $type !== 'planning-output')
                <a data-action-plain href="{{ route('movs.create', $type === 'mov' ? ['performance_target_id' => $evidence->performance_target_id] : ['office_target_id' => $evidence->office_target_id]) }}" class="mt-3 inline-flex rounded-lg border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-800">Upload revised MOV</a>
                <p class="mt-1 text-xs text-gray-600">Include all supporting files in the new upload.</p>
            @endif
        </div>
        @if($owner && ($analysis->findings['followup_questions'] ?? []))
        <form method="POST" action="{{ route('evidence.answers', [$type, $evidence->id]) }}" class="mt-4 space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4" data-action-plain>
            @csrf
            <input type="hidden" name="analysis_version" value="{{ hash('sha256', json_encode($analysis->findings['followup_questions'])) }}">
            <h4 class="font-bold">A few details are missing from your files</h4>
            <p class="text-sm">Answer only these questions. Your answers are claims; they do not replace missing evidence. AI will recheck the original files before preparing the final report summary.</p>
            @foreach($analysis->findings['followup_questions'] as $question)
            <div><label for="followup-{{ $type }}-{{ $evidence->id }}-{{ $question['id'] }}" class="block text-sm font-semibold">{{ $question['label'] }} {{ $question['required'] ? '*' : '(optional)' }}</label>
            @if($question['type'] === 'text')
                <textarea id="followup-{{ $type }}-{{ $evidence->id }}-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" rows="2" maxlength="2000" @required($question['required']) class="mt-1 block w-full rounded-lg border-gray-300">{{ old('answers.'.$question['id']) }}</textarea>
            @else
                <input id="followup-{{ $type }}-{{ $evidence->id }}-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" type="{{ $question['type'] }}" @if($question['type'] === 'number') min="0" max="9999999999.99" step="0.01" @endif @required($question['required']) value="{{ old('answers.'.$question['id']) }}" class="mt-1 block w-full rounded-lg border-gray-300">
            @endif
            <x-input-error :messages="$errors->get('answers.'.$question['id'])" /></div>
            @endforeach
            <button class="rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white">Save answers and recheck MOV</button>
            <a href="{{ route('movs.create', $type === 'mov' ? ['performance_target_id' => $evidence->performance_target_id] : ['office_target_id' => $evidence->office_target_id]) }}" class="inline-block text-sm font-semibold underline">Upload revised evidence (include all supporting files)</a>
        </form>
        @endif
        <details class="mt-4 rounded-lg border border-gray-200 bg-white p-4">
        <summary class="cursor-pointer text-sm font-semibold text-spms-navy">View full AI findings and references</summary>
        @if($analysis->findings['mov_title'] ?? null)<h4 class="mt-4 text-sm font-bold">MOV title</h4><p class="mt-1 text-sm">{{ $analysis->findings['mov_title'] }}</p>@endif
        @if($analysis->findings['mov_description'] ?? null)<p class="mt-2 whitespace-pre-line text-sm">{{ $analysis->findings['mov_description'] }}</p>@endif
        @if($analysis->findings['user_data_summary'] ?? null)<h4 class="mt-4 text-sm font-bold">User-provided data</h4><p class="mt-1 whitespace-pre-line text-sm">{{ $analysis->findings['user_data_summary'] }}</p>@endif
        @if($analysis->findings['file_findings_summary'] ?? null)<h4 class="mt-4 text-sm font-bold">Findings from uploaded files</h4><p class="mt-1 whitespace-pre-line text-sm">{{ $analysis->findings['file_findings_summary'] }}</p>@endif
        @if($analysis->findings['discrepancies'] ?? null)<h4 class="mt-4 text-sm font-bold text-amber-800">Discrepancies or missing evidence</h4><p class="mt-1 whitespace-pre-line text-sm text-amber-800">{{ $analysis->findings['discrepancies'] }}</p>@endif
        @if($analysis->findings['batch_evidence_ids'] ?? null)<p class="mt-2 text-xs text-gray-500">{{ count($analysis->findings['batch_evidence_ids']) }} uploaded files analyzed together. Repeated copies are not additional accomplishments.</p>@endif
        @if($analysis->findings['source_excerpts'] ?? null)<details class="mt-3 text-sm"><summary class="cursor-pointer font-semibold">Evidence references</summary><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($analysis->findings['source_excerpts'] as $excerpt)<li>{{ $excerpt }}</li>@endforeach</ul></details>@endif
        <p class="mt-3 text-sm font-semibold">Target assessment: {{ $sufficient ? 'Evidence satisfies the target' : 'Not confirmed sufficient' }}</p>
        @if($analysis->findings['target_assessment'] ?? null)<p class="mt-2 text-sm">{{ $analysis->findings['target_assessment'] }}</p>@endif
        <p class="mt-3 whitespace-pre-line text-sm">{{ $analysis->summary }}</p>
        @if($analysis->findings['limitations'] ?? null)<p class="mt-2 text-sm text-amber-800">{{ $analysis->findings['limitations'] }}</p>@endif
        </details>
        @if($analysis->statement && ! ($analysis->findings['followup_questions'] ?? []))
            <p class="mt-3 text-xs font-semibold uppercase">Statement used in reports</p>
            <p class="mt-1 whitespace-pre-line text-sm">{{ $analysis->statement }}</p>
            @if($owner)
                <details class="mt-3"><summary class="cursor-pointer text-sm font-semibold text-spms-navy">Edit report summary</summary>
                <form method="POST" action="{{ route('evidence.statement', [$type, $evidence->id]) }}" class="mt-3 space-y-2" data-action-plain>
                    @csrf @method('PUT')
                    <label for="statement-{{ $type }}-{{ $evidence->id }}" class="text-sm font-semibold">Review or edit report statement</label>
                    <textarea id="statement-{{ $type }}-{{ $evidence->id }}" name="statement" rows="3" maxlength="5000" required class="w-full rounded-lg border-gray-300">{{ $analysis->statement }}</textarea>
                    <button class="rounded-lg bg-spms-navy px-3 py-2 text-sm font-semibold text-white">Save statement</button>
                </form>
                </details>
            @endif
        @elseif($analysis->findings['followup_questions'] ?? [])
            <p class="mt-3 text-sm text-amber-800">The final report statement is waiting for clarification and an evidence recheck.</p>
        @else
            <p class="mt-3 text-sm text-amber-800">No supported report statement could be generated from this file.</p>
        @endif
    @elseif($analysis?->status === 'failed' && !$processing)
        <p class="mt-3 text-sm text-amber-800">{{ $analysis->error }}</p>
    @elseif($processing)
        @php($feedback = $analysis->processingFeedback())
        <p role="status" data-processing-label class="mt-3 text-sm font-semibold">{{ $feedback['label'] }}</p>
        <p data-processing-message class="mt-2 text-sm text-gray-600">{{ $feedback['message'] }}</p>
        <p data-processing-warning @class(['mt-2 text-sm text-amber-800', 'hidden' => !$feedback['warning']])>{{ $feedback['warning'] }}</p>
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
