@props(['evidence'])
@php($analysis = $evidence->analysis)
@php($type = $evidence instanceof \App\Models\Mov ? 'mov' : ($evidence instanceof \App\Models\PlanningOutput ? 'planning-output' : 'target-mov'))
@php($owner = (int) ($type === 'mov' ? $evidence->uploaded_by : ($type === 'planning-output' ? $evidence->submitted_by : $evidence->user_id)) === (int) Auth::id())
<section class="mt-5 rounded-lg border border-blue-200 bg-blue-50/40 p-4">
    <h3 class="font-bold text-spms-navy">MOV analysis and report statement</h3>
    <p class="mt-1 text-xs text-gray-600">Formal, concise English; past tense for evidence-supported completed work. AI statements do not change completion or ratings.</p>
    @if($analysis?->status === 'completed')
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
    @elseif($analysis?->status === 'failed')
        <p class="mt-3 text-sm text-amber-800">{{ $analysis->error }}</p>
    @elseif($analysis?->status === 'analyzing')
        <p class="mt-3 text-sm">Analysis in progress. Refresh to see the result.</p>
    @else
        <p class="mt-3 text-sm text-gray-600">This MOV has not been analyzed yet.</p>
    @endif
    @if($owner)
        <form method="POST" action="{{ route('evidence.analyze', [$type, $evidence->id]) }}" class="mt-3">@csrf
            <button class="rounded-lg border border-blue-300 px-3 py-2 text-sm font-semibold text-spms-navy">{{ $analysis ? 'Retry MOV analysis' : 'Analyze MOV and draft report statement' }}</button>
        </form>
        <x-input-error :messages="$errors->get('statement')" class="mt-2" />
    @endif
</section>
