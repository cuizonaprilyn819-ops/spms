<x-app-layout>
<x-slot name="header"><h2 class="text-xl font-bold text-spms-ink">Historical PCRs</h2><p class="mt-1 text-sm text-slate-500">Original forms, transcriptions, and evidence from previous reporting periods.</p></x-slot>
<div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    @if(session('status'))<p role="status" class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</p>@endif
    <div class="flex flex-wrap gap-3"><a class="inline-flex rounded-lg border bg-white px-4 py-2 text-sm font-semibold text-spms-navy" href="{{ route('reports.index') }}">Back to My Reports</a>@can('create', \App\Models\HistoricalPcr::class)<a class="inline-flex rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white" href="{{ route('historical-pcrs.create') }}">Upload historical PCR</a>@endcan</div>
    <section><h3 class="text-lg font-bold text-spms-ink">My historical PCRs</h3>@include('historical-pcrs.cards', ['items' => $records->where('owner_id', auth()->id())])</section>
    <section><h3 class="text-lg font-bold text-spms-ink">{{ auth()->user()->hasRole('Administrator') ? 'Manage historical PCRs' : 'Related PCRs' }}</h3><p class="mt-1 text-sm text-slate-500">{{ auth()->user()->hasRole('Administrator') ? 'Review source transcriptions and record corrections.' : 'Forms on which you are a named reviewer, assessor, or approving official.' }}</p>@include('historical-pcrs.cards', ['items' => $records->where('owner_id', '!=', auth()->id())])</section>
</div>
</x-app-layout>
