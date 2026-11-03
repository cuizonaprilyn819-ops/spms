<div class="mt-4 grid gap-4 lg:grid-cols-2">
@forelse($items as $record)
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div><span class="text-xs font-bold uppercase tracking-wide text-spms-navy">{{ strtoupper($record->type) }}</span><h4 class="mt-1 font-semibold text-spms-ink">{{ $record->owner_name }}</h4></div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $record->revision->verified_at ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">{{ $record->revision->verified_at ? 'Verified transcription' : 'Draft transcription' }}</span>
        </div>
        <p class="mt-2 text-sm text-slate-600">{{ $record->cycle->label }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ $record->form_pages }} form pages · {{ $record->source_pages - $record->form_pages }} evidence pages · Original retained</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a class="inline-flex rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white" href="{{ route('historical-pcrs.show', $record) }}">View PCR</a>
            <a class="inline-flex rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-spms-navy" href="{{ route('historical-pcrs.original', $record) }}" data-no-spa>Download original PDF</a>
        </div>
    </article>
@empty
    <p class="rounded-xl border border-dashed border-slate-200 p-5 text-sm text-slate-500">No historical PCRs in this section.</p>
@endforelse
</div>
