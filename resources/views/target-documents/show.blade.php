<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">Analyze and Review Targets</h2></x-slot>
    <div class="mx-auto max-w-5xl space-y-5 px-4 py-8" data-action-plain>
        <a href="{{ route('target-documents.index') }}" class="text-sm font-semibold text-spms-navy">← Your documents</a>
        @if(session('status'))<p role="status" class="spms-alert rounded-lg bg-blue-50 p-4 text-sm text-blue-900" data-tone="info">{{ session('status') }}</p>@endif
        <section class="rounded-xl border border-gray-200 bg-white p-6">
            <h1 class="break-words text-xl font-bold">{{ $document->original_name }}</h1>
            <div class="mt-4 flex flex-wrap items-center gap-3 border-b border-gray-100 pb-5">
            <a href="{{ route('target-documents.download', $document) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-spms-navy transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5" /></svg>
                Download original
            </a>
            <form method="POST" action="{{ route('target-documents.destroy', $document) }}" x-data @submit="if (!window.confirm('Delete this document and its extracted drafts? Previously assigned targets will be kept.')) $event.preventDefault()">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7" /></svg>
                    Delete document
                </button>
            </form>
            </div>
            @if($errors->any())<div role="alert" class="mt-3 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @if($document->error)<p role="alert" class="spms-alert mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" data-tone="danger">{{ $document->error }}</p>@endif
            @if($document->status !== 'review')
                <form method="POST" action="{{ route('target-documents.analyze', $document) }}" class="mt-5" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <p class="mb-3 text-sm text-gray-600">Analyze the document to prepare target drafts. Nothing is assigned until you review and confirm each target.</p>
                    @if($document->status === 'analyzing')<p class="mb-3 text-sm text-amber-800">Analysis is in progress. Refresh this page to check results. If interrupted, you can retry after ten minutes.</p>@endif
                    <button type="submit" :disabled="busy" :aria-busy="busy" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-spms-navy px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700 disabled:cursor-wait disabled:opacity-60">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z" /></svg>
                        <span x-text="busy ? 'Analyzing document…' : 'Analyze document with AI'">Analyze document with AI</span>
                    </button>
                    <p x-cloak x-show="busy" role="status" class="mt-3 text-sm text-gray-600">Reading the source table may take several minutes. Please keep this page open.</p>
                </form>
            @else
                @if($document->drafts->isNotEmpty())
                <form method="POST" action="{{ route('target-documents.weights', $document) }}" class="mt-5" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <button type="submit" :disabled="busy" class="rounded-lg border border-blue-300 px-4 py-2 text-sm font-semibold text-spms-navy disabled:opacity-50" x-text="busy ? 'Reading source budgets and dividing weights…' : 'AI divide target weights'">AI divide target weights</button>
                    <p class="mt-2 text-xs text-gray-600">Before any assignment/import, divide the 100% report budget among targets using section, objective and program budgets. Review recommendations and source conflicts before confirming each assignment. Quantities and units are preserved.</p>
                </form>
                <form method="POST" action="{{ route('target-documents.hierarchy', $document) }}" class="mt-5" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <button type="submit" :disabled="busy" class="rounded-lg border border-blue-300 px-4 py-2 text-sm font-semibold text-spms-navy disabled:opacity-50" x-text="busy ? 'Re-reading source table…' : 'Re-read programs and outcomes'">Re-read programs and outcomes</button>
                    <p class="mt-2 text-xs text-gray-600">Rechecks the source table and merged cells. Existing targets and assignments are kept. Blank or unreadable source cells will be flagged.</p>
                </form>
                @endif
                <h2 class="mt-5 text-sm font-semibold">Initial extraction notes</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $document->summary }}</p>
                <p class="mt-4 text-xs text-gray-500">AI suggestions require review. Check the original document for missing targets and incorrect details. Supply missing quantities and weights before assignment. Target duration follows the evaluation period automatically.</p>
            @endif
        </section>
        @if($document->drafts->isNotEmpty())<x-target-weight-summary :document="$document" />@endif
        @foreach($document->drafts->groupBy(fn ($d) => $d->proposal['program'] ?? 'Program not identified — check source') as $program => $programDrafts)
        <details class="rounded-xl border bg-white p-6"><summary class="cursor-pointer text-lg font-bold">{{ $program }}</summary>
            @foreach($programDrafts->groupBy(fn ($d) => $d->proposal['program_outcome'] ?? 'Outcome not identified — check source') as $outcome => $outcomeDrafts)
            <details class="mt-4 rounded-lg border p-4"><summary class="cursor-pointer font-semibold">{{ $outcome }}</summary>
                @foreach($outcomeDrafts as $draft)
                <article class="mt-4 border-t pt-4"><h3 class="font-bold">{{ $draft->proposal['title'] }}</h3>
                    <p class="mt-2 text-sm">{{ $draft->proposal['success_indicator'] }}</p>
                    <p class="mt-2 text-sm font-semibold">Weight: {{ isset($draft->proposal['weight']) ? number_format($draft->proposal['weight'], 2).'%' : 'Needs review' }} · Measure: {{ $draft->proposal['target_value'] ?? 'Quantity needs review' }} {{ $draft->proposal['unit_of_measure'] ?? 'Unit needs review' }}</p>
                    @if($draft->proposal['weight_reason'] ?? null)<p class="mt-1 text-xs text-gray-600">Weight basis: {{ $draft->proposal['weight_reason'] }}</p>@endif
                    <blockquote class="mt-3 border-l-4 pl-4 text-sm">{{ $draft->proposal['source_excerpt'] }}</blockquote>
                    <a href="{{ route('target-drafts.review', $draft) }}" class="mt-3 inline-block rounded-lg border px-4 py-2 font-semibold">{{ $draft->office_target_id ? 'View assignment' : 'Assign' }}</a>
                </article>
                @endforeach
            </details>
            @endforeach
        </details>
        @endforeach
        <x-workflow-audit :subject="$document" />
        @if($document->status === 'review' && $document->drafts->isEmpty())<p class="spms-alert rounded-lg bg-amber-50 p-5 text-sm" data-tone="warning">No targets were extracted. Check the analysis notes and upload a clearer or smaller document.</p>@endif
    </div>
</x-app-layout>
