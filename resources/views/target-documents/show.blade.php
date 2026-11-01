<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">Analyze and Review Targets</h2></x-slot>
    <div class="mx-auto max-w-5xl space-y-5 px-4 py-8">
        <a href="{{ route('target-documents.index') }}" class="text-sm font-semibold text-spms-navy">← Your documents</a>
        @if(session('status'))<p role="status" class="spms-alert rounded-lg bg-blue-50 p-4 text-sm text-blue-900" data-tone="info">{{ session('status') }}</p>@endif
        <section class="rounded-xl border border-gray-200 bg-white p-6">
            <h1 class="break-all text-xl font-bold">{{ $document->original_name }}</h1>
            <form method="POST" action="{{ route('target-documents.destroy', $document) }}" class="mt-3" x-data @submit="if (!window.confirm('Delete this document and its extracted drafts? Previously assigned targets will be kept.')) $event.preventDefault()">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete document</button>
            </form>
            <a href="{{ route('target-documents.download', $document) }}" class="mt-2 inline-block text-sm font-semibold text-spms-navy">Download original document</a>
            @if($errors->any())<div role="alert" class="mt-3 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @if($document->error)<p role="alert" class="spms-alert mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" data-tone="danger">{{ $document->error }}</p>@endif
            @if($document->status !== 'review')
                <form method="POST" action="{{ route('target-documents.analyze', $document) }}" class="mt-5" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <p class="mb-3 text-sm text-gray-600">Analyze the document to prepare target drafts. Nothing is assigned until you review and confirm each target.</p>
                    @if($document->status === 'analyzing')<p class="mb-3 text-sm text-amber-800">Analysis is in progress. Refresh this page to check results. If interrupted, you can retry after ten minutes.</p>@endif
                    <button type="submit" :disabled="busy" class="rounded-lg bg-spms-navy px-5 py-3 text-sm font-semibold text-white disabled:opacity-50" x-text="busy ? 'Analyzing document… reading the source table may take several minutes' : 'Analyze document with AI'">Analyze document with AI</button>
                </form>
            @else
                @if($document->drafts->isNotEmpty())
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
        @foreach($document->drafts->groupBy(fn ($d) => $d->proposal['program'] ?? 'Program not identified — check source') as $program => $programDrafts)
        <details class="rounded-xl border bg-white p-6"><summary class="cursor-pointer text-lg font-bold">{{ $program }}</summary>
            @foreach($programDrafts->groupBy(fn ($d) => $d->proposal['program_outcome'] ?? 'Outcome not identified — check source') as $outcome => $outcomeDrafts)
            <details class="mt-4 rounded-lg border p-4"><summary class="cursor-pointer font-semibold">{{ $outcome }}</summary>
                @foreach($outcomeDrafts as $draft)
                <article class="mt-4 border-t pt-4"><h3 class="font-bold">{{ $draft->proposal['title'] }}</h3>
                    <p class="mt-2 text-sm">{{ $draft->proposal['success_indicator'] }}</p>
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
