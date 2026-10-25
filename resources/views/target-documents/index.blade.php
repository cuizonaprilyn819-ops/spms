<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">Target Planning Documents</h2></x-slot>
    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-spms-navy">1. Upload → 2. Analyze → 3. Review → 4. Assign</p>
            <h1 class="mt-3 text-2xl font-bold">Upload a target planning document</h1>
            <p class="mt-2 text-sm text-gray-600">AI will identify targets and suggest offices. You choose what to assign. CD-only targets remain visible for your review and cannot be assigned to offices.</p>
            @if($errors->any())<div role="alert" class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <form method="POST" action="{{ route('target-documents.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <div><label for="document-cycle" class="block text-sm font-semibold">Evaluation period</label>
                    <select id="document-cycle" name="evaluation_cycle_id" required class="mt-1 w-full rounded-lg border-gray-300">
                        <option value="">Select an open period</option>
                        @foreach($cycles as $cycle)<option value="{{ $cycle->id }}" @selected(old('evaluation_cycle_id') == $cycle->id)>{{ $cycle->label }}</option>@endforeach
                    </select>
                </div>
                @if($cycles->isEmpty())<p class="text-sm text-amber-800">Ask the administrator to open an evaluation period before uploading.</p>@endif
                <div><label for="main-campus-file" class="block text-sm font-semibold">Target planning file</label><input id="main-campus-file" name="file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required class="mt-2 block w-full rounded-lg border border-gray-300 p-3 text-sm"/></div>
                <p class="text-xs text-gray-500">PDF, Word (.doc/.docx), or Excel (.xls/.xlsx), up to 20 MB, subject to the server upload limit. During analysis, the document and office catalogue are sent to OpenAI. Review extracted details against the source. For Excel, split sheets larger than 1,000 rows before uploading.</p>
                <button type="submit" :disabled="busy" @disabled($cycles->isEmpty()) class="rounded-lg bg-spms-navy px-5 py-3 text-sm font-semibold text-white disabled:opacity-50" x-text="busy ? 'Uploading…' : 'Upload document'">Upload document</button>
            </form>
        </div>
        <section class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-bold">Your documents</h2>
            @forelse($documents as $document)
                <a href="{{ route('target-documents.show', $document) }}" class="mt-3 flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-4 hover:bg-blue-50"><span class="break-all font-semibold">{{ $document->original_name }}</span><span class="text-xs uppercase text-gray-500">{{ $document->status }}</span></a>
            @empty<p class="mt-3 text-sm text-gray-500">No documents uploaded yet.</p>@endforelse
            <div class="mt-4">{{ $documents->links() }}</div>
        </section>
    </div>
</x-app-layout>
