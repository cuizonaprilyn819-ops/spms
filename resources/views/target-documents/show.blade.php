<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">Analyze and Review Targets</h2></x-slot>
    <div class="mx-auto max-w-5xl space-y-5 px-4 py-8">
        <a href="{{ route('target-documents.index') }}" class="text-sm font-semibold text-spms-navy">← Your documents</a>
        <section class="rounded-xl border border-gray-200 bg-white p-6">
            <h1 class="break-all text-xl font-bold">{{ $document->original_name }}</h1>
            <a href="{{ route('target-documents.download', $document) }}" class="mt-2 inline-block text-sm font-semibold text-spms-navy">Download original document</a>
            @if($errors->any())<div role="alert" class="mt-3 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @if($document->error)<p role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $document->error }}</p>@endif
            @if($document->status !== 'review')
                <form method="POST" action="{{ route('target-documents.analyze', $document) }}" class="mt-5" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <p class="mb-3 text-sm text-gray-600">Analyze the document to prepare target drafts. Nothing is assigned until you review and confirm each target.</p>
                    @if($document->status === 'analyzing')<p class="mb-3 text-sm text-amber-800">Analysis is in progress. Refresh this page to check results. If interrupted, you can retry after five minutes.</p>@endif
                    <button type="submit" :disabled="busy" class="rounded-lg bg-spms-navy px-5 py-3 text-sm font-semibold text-white disabled:opacity-50" x-text="busy ? 'Analyzing document… this can take up to two minutes' : 'Analyze document with AI'">Analyze document with AI</button>
                </form>
            @else
                <p class="mt-5 whitespace-pre-line text-sm text-gray-700">{{ $document->summary }}</p>
                <p class="mt-4 text-xs text-gray-500">AI suggestions require review. Check the original document for missing targets and incorrect details. Missing quantities, deadlines, and weights must be supplied before assignment.</p>
            @endif
        </section>
        @foreach($document->drafts as $draft)
            <article class="rounded-xl border {{ $draft->proposal['cd_only'] ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-white' }} p-6">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-bold">{{ $draft->proposal['title'] }}</h2><span class="text-xs font-bold uppercase">{{ $draft->office_target_id ? 'Assigned' : ($draft->proposal['cd_only'] ? 'CD-only · Not assignable' : 'Ready for review') }}</span></div>
                <p class="mt-2 text-sm text-gray-700">{{ $draft->proposal['classification_reason'] }}</p>
                <blockquote class="mt-4 whitespace-pre-line border-l-4 border-blue-200 pl-4 text-sm text-gray-600">{{ $draft->proposal['source_excerpt'] }}</blockquote>
                <a href="{{ route('target-drafts.review', $draft) }}" class="mt-4 inline-block rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold">{{ $draft->office_target_id ? 'View assignment' : ($draft->proposal['cd_only'] ? 'Review CD-only target' : 'Review target and offices') }}</a>
            </article>
        @endforeach
        @if($document->status === 'review' && $document->drafts->isEmpty())<p class="rounded-lg bg-amber-50 p-5 text-sm">No targets were extracted. Check the analysis notes and upload a clearer or smaller document.</p>@endif
    </div>
</x-app-layout>
