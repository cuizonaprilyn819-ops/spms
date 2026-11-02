<section data-assistance-result data-assistance-id="{{ $assistance->id }}" class="space-y-4">
    @if($failure)<x-semantic-alert tone="warning">{{ $failure }} Manual entry remains available.</x-semantic-alert>@endif
    <p class="text-sm text-slate-600">Review the meaning before applying. This preview does not save or approve anything.</p>
    <div class="grid gap-4 md:grid-cols-2">
        <div><h3 class="font-semibold">Original</h3><p class="mt-2 whitespace-pre-line rounded border border-slate-200 p-3">{{ $assistance->original }}</p></div>
        <div><h3 class="font-semibold">Suggested wording</h3><p data-suggestion class="mt-2 whitespace-pre-line rounded border border-violet-200 bg-violet-50 p-3">{{ $assistance->suggestion ?? 'No suggestion available.' }}</p></div>
    </div>
    <details><summary class="cursor-pointer font-semibold">Protected details</summary><dl class="mt-2 space-y-2 text-sm">
    @forelse($assistance->protected_values as $name=>$value)<div><dt class="font-semibold">{{ ucfirst(str_replace('_',' ',$name)) }}</dt><dd class="break-words">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</dd></div>@empty<p>No structured details were entered. Keep quantities and meaning faithful to the original.</p>@endforelse
    </dl></details>
    <div class="flex flex-wrap gap-3">
        @if($assistance->status==='completed')<button type="button" data-assistance-use class="spms-button-primary">Use suggestion</button>@endif
        <button type="button" data-assistance-retry class="is-secondary">Try again</button>
        <button type="button" data-assistance-keep class="is-secondary">Keep original</button>
    </div>
</section>
