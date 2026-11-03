@if(config('spms.target_assistance.enabled'))
@php
    $assistanceSubject=null;
    $subjectAdapters=app(\App\Services\AssistanceSubject::class);
    foreach(['plan','target','officeTarget','historicalPcr','scopeDraft','draft','proposal','opcr','dpcr','upcr','ipcr','accomplishment'] as $key){
        $candidate=request()->route($key);
        if($candidate instanceof \Illuminate\Database\Eloquent\Model && in_array(get_class($candidate),$subjectAdapters::TYPES,true)){$assistanceSubject=$subjectAdapters->canonical($candidate);break;}
    }
    if(!$assistanceSubject && request()->route('type') && request()->route('report')){
        try{$assistanceSubject=$subjectAdapters->canonical($subjectAdapters->resolve(request()->route('type'),(int)request()->route('report')));}catch(\Throwable){}
    }
    $assistanceCanView=$assistanceSubject && $subjectAdapters->canView(auth()->user(),$assistanceSubject);
@endphp
@if($assistanceCanView)
<section class="planning-card mx-auto my-6 max-w-7xl" aria-label="Target assistance">
    <div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="font-semibold">Target conflict review</h2><p class="mt-1 text-sm text-slate-600">Check saved targets. Draft results stay private; submitted findings notify the responsible parties.</p></div>
    <div class="flex flex-wrap gap-3"><form method="post" action="{{ route('target-conflicts.scan') }}">@csrf<input type="hidden" name="kind" value="{{ $subjectAdapters->kind($assistanceSubject) }}"><input type="hidden" name="id" value="{{ $assistanceSubject->id }}"><button class="is-secondary" data-action-icon="search">Check target conflicts</button></form><a class="text-violet-700 underline" href="{{ route('target-conflicts.index') }}">Target conflicts</a></div></div>
</section>
@endif
<div data-target-assistance-config data-preview="{{ route('target-assistance.preview') }}" data-scan="{{ route('target-conflicts.scan') }}"></div>
<dialog data-target-assistance-dialog class="spms-assistance-dialog" aria-labelledby="target-assistance-title">
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-4"><h2 id="target-assistance-title" class="text-xl font-semibold">Rephrase with AI</h2><button type="button" data-assistance-close aria-label="Close rephrasing preview">Close</button></div>
    <div data-assistance-content class="mt-4" role="status" aria-live="polite"></div>
</dialog>
@endif
