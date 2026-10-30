<x-app-layout>
<x-slot name="header"><div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide text-spms-navy">Historical {{ strtoupper($pcr->type) }}</p><h2 class="mt-1 text-xl font-bold text-spms-ink">{{ $pcr->owner_name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $period }} · {{ $snapshot['owner_position'] }}</p></div><span class="rounded-full bg-spms-tint px-3 py-2 text-xs font-semibold text-spms-navy">Revision {{ $revision->number }}</span></div></x-slot>
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    @if(session('status'))<p role="status" class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-900">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="flex flex-wrap gap-2">
        <a class="inline-flex rounded-lg border bg-white px-4 py-2 text-sm font-semibold text-spms-navy" href="{{ route('historical-pcrs.index') }}">Back to historical PCRs</a>
        <a class="inline-flex rounded-lg bg-spms-navy px-4 py-2 text-sm font-semibold text-white" href="{{ route('historical-pcrs.original', $pcr) }}" data-no-spa>Download original PDF</a>
        @foreach(['docx' => 'Word DOCX', 'xlsx' => 'Excel XLSX', 'pdf' => 'PDF'] as $format => $label)<a data-no-spa data-action-icon="{{ $format === 'xlsx' ? 'spreadsheet' : 'document' }}" class="inline-flex rounded-lg border bg-white px-4 py-2 text-sm font-semibold text-spms-navy" href="{{ route('historical-pcrs.export', [$pcr, $format, 'revision' => $revision->id]) }}">Export {{ $label }}</a>@endforeach
        @can('update', $pcr)<a class="inline-flex rounded-lg border bg-white px-4 py-2 text-sm font-semibold text-spms-navy" href="{{ route('historical-pcrs.edit', $pcr) }}">Edit transcription</a>@endcan
    </div>
    <section class="rounded-xl border {{ $revision->verified_at ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-5">
        <h3 class="font-semibold text-spms-ink">{{ $status }}</h3>
        <p class="mt-1 text-sm text-slate-700">{{ $snapshot['office'] }}. Original signed source retained; historical approval is separate from transcription review. Parent OPCR was not supplied.</p>
        @if($revision->verified_at)<p class="mt-2 text-xs text-slate-600">Transcription verified by {{ $revision->verifier?->name }} on {{ $revision->verified_at->format('M j, Y H:i') }}.</p>@endif
        @foreach($snapshot['notes'] as $note)<p class="mt-2 text-sm text-amber-950">{{ $note }}</p>@endforeach
        <p class="mt-3 text-sm font-semibold text-spms-ink">Reported final average: {{ $snapshot['reported_overall'] ?: 'Not recorded' }} · {{ $snapshot['adjectival_rating'] ?: 'Adjectival rating not recorded' }}</p>
    </section>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b p-5"><h3 class="font-semibold text-spms-ink">Source transcription</h3><p class="mt-1 text-xs text-slate-500">Q: Quality · E: Efficiency · T: Timeliness · A: Average. Blank or unclear source values remain unfilled.</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-xs"><thead class="bg-spms-tint"><tr>@foreach($columns as $column)<th class="p-3 font-semibold" style="width:{{ $column['width'] }}%">{{ $column['label'] }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
        @php($last = null)
        @foreach($rows as $row)
            @if($row['section'] !== $last)<tr class="bg-slate-50"><th colspan="{{ count($columns) }}" class="p-3 text-sm font-semibold text-spms-navy">{{ $row['section'] }}</th></tr>@php($last = $row['section'])@endif
            <tr>@foreach($columns as $key => $column)<td class="whitespace-pre-line p-3 align-top text-slate-700">{{ $row[$key] ?? '' }}</td>@endforeach</tr>
        @endforeach
        @if(! count($rows))<tr><td colspan="{{ count($columns) }}" class="p-5 text-slate-500">Transcription has not been entered yet. The original is available above.</td></tr>@endif
        </tbody></table></div>
    </section>
    <section class="rounded-xl border bg-white p-5"><h3 class="font-semibold text-spms-ink">Named personnel on the source</h3><dl class="mt-3 grid gap-3 sm:grid-cols-2">@foreach($snapshot['signatories'] as $signatory)<div><dt class="text-xs text-slate-500">{{ $signatory['role'] }} · source p. {{ $signatory['page'] }}</dt><dd class="mt-1 text-sm font-medium text-spms-ink">{{ $signatory['name'] }}</dd><dd class="text-xs text-slate-500">Date: {{ $signatory['date'] ?: 'Not recorded' }}</dd></div>@endforeach</dl></section>
    <section class="rounded-xl border bg-white p-5" x-data="{ page: 1 }">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold text-spms-ink">Original pages and evidence</h3><p class="mt-1 text-xs text-slate-500">{{ $pcr->source_pages }} pages · Evidence begins at page {{ $pcr->form_pages + 1 }} · {{ $pcr->source_name }}</p></div><label class="text-sm">Source page <select x-model="page" class="ml-2 rounded-lg border-slate-300 text-sm">@foreach(range(1, $pcr->source_pages) as $page)<option value="{{ $page }}">{{ $page }}{{ $page > $pcr->form_pages ? ' — evidence' : ' — form' }}</option>@endforeach</select></label></div>
        <img class="mt-4 max-h-[850px] w-full rounded-lg border object-contain" src="{{ route('historical-pcrs.page', [$pcr, 1]) }}" :src="{{ \Illuminate\Support\Js::from(route('historical-pcrs.page', [$pcr, 1])) }}.replace(/\/1$/, '/' + page)" alt="Selected original source page">
    </section>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border bg-white p-5"><h3 class="font-semibold text-spms-ink">Revision history</h3><div class="mt-3 space-y-3">@foreach($pcr->revisions()->with('author')->orderByDesc('number')->get() as $version)<div class="border-b pb-3 text-sm"><a class="font-semibold text-spms-navy underline" href="{{ route('historical-pcrs.show', [$pcr, 'revision' => $version->id]) }}">Revision {{ $version->number }}</a> · {{ $version->verified_at ? 'Verified' : 'Draft' }}<p class="mt-1 text-slate-600">{{ $version->reason }}</p><p class="mt-1 text-xs text-slate-500">{{ $version->author?->name }} · {{ $version->created_at->format('M j, Y H:i') }}</p></div>@endforeach</div></section>
        <section class="rounded-xl border bg-white p-5"><h3 class="font-semibold text-spms-ink">Request a correction</h3><p class="mt-1 text-sm text-slate-500">Identify the source page and the wording or value to review. The original and earlier revisions are retained.</p><form method="post" action="{{ route('historical-pcrs.correction', $pcr) }}" class="mt-4 space-y-3">@csrf<input type="hidden" name="revision_id" value="{{ $revision->id }}"><label class="block text-sm font-medium">Correction details<textarea name="message" required maxlength="4000" rows="4" class="mt-2 w-full rounded-lg border-slate-300">{{ old('message') }}</textarea></label><x-primary-button>Send correction request</x-primary-button></form>
        @foreach($pcr->corrections()->with('requester')->latest()->get() as $correction)<div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm"><p class="font-semibold">{{ $correction->requester?->name }} · {{ $correction->resolved_revision_id ? 'Addressed in a later revision' : 'Awaiting review' }}</p><p class="mt-1 whitespace-pre-line text-slate-600">{{ $correction->message }}</p></div>@endforeach</section>
    </div>
    @can('update', $pcr)
        @if(! $revision->verified_at && $revision->id === $pcr->current_revision_id)<form method="post" action="{{ route('historical-pcrs.verify', $pcr) }}" class="rounded-xl border bg-white p-5">@csrf<input type="hidden" name="revision_id" value="{{ $revision->id }}"><h3 class="font-semibold text-spms-ink">Verify transcription</h3><label class="mt-3 flex gap-3 text-sm text-slate-600"><input class="mt-1 rounded border-slate-300" type="checkbox" name="acknowledge" value="1" required>I checked the transcription against the source, including its unresolved notes. This verifies transcription accuracy and does not grant new institutional approval.</label><x-primary-button class="mt-4">Verify and lock transcription</x-primary-button></form>@endif
    @endcan
</div>
</x-app-layout>
