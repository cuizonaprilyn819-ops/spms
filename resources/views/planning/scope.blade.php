<x-app-layout>
<x-slot name="header"><h1 class="text-2xl font-bold">Set an OPCR target</h1><p class="text-sm text-slate-600">{{ $plan->office->name }} · {{ $plan->cycle->label }} · Revision {{ $plan->revision }}</p></x-slot>
<div class="planning-page mx-auto max-w-5xl space-y-6 p-6">
@include('planning.messages')
<nav class="spms-plan-path" aria-label="Target steps"><span @if(!$draft) aria-current="step" @endif>1. Target and responsibility</span><span @if($draft) aria-current="step" @endif>2. Review calculated total</span><span>3. Confirm draft</span></nav>
@if($preview)
<section class="planning-card"><h2>Review before confirming</h2>
<p class="spms-alert" data-tone="info">This is a private planning draft. Confirmation adds a target to the OPCR draft; commitment approval remains a separate step.</p>
<h3 class="mt-4 font-semibold">{{ $preview['target'] }}</h3><p>{{ $preview['success_indicator'] }}</p>
<dl class="spms-scope-summary">
<div><dt>Coverage</dt><dd>{{ ucfirst($preview['scope_metadata']['coverage']) }}</dd></div>
<div><dt>Quantity entered</dt><dd>{{ $preview['scope_metadata']['entered_quantity'] }} {{ $preview['unit'] }} · {{ $preview['scope_metadata']['quantity_basis']==='total' ? 'combined total' : 'per department' }}</dd></div>
<div><dt>Calculated commitment</dt><dd class="text-xl font-semibold">{{ $preview['quantity'] }} {{ $preview['unit'] }}</dd></div>
<div><dt>Responsible departments</dt><dd>{{ $offices->whereIn('id',$preview['scope_metadata']['recipient_ids'])->pluck('name')->join('; ') }}</dd></div>
<div><dt>Participating campuses</dt><dd>{{ $campuses->whereIn('id',$preview['scope_metadata']['campus_ids'])->pluck('name')->join('; ') ?: 'Existing campus workflow' }}</dd></div>
<div><dt>Matching duties</dt><dd>{{ $preview['scope_metadata']['matching_reason'] }}</dd></div>
<div><dt>Standards / deadline</dt><dd>{{ $preview['standards'] }} · {{ $preview['deadline'] }}</dd></div>
<div><dt>Source</dt><dd>{{ $preview['source_evidence'] }}</dd></div></dl>
@if($draft->ai_advice)<div class="spms-alert" data-tone="primary"><strong>AI advice — human review required</strong><p class="whitespace-pre-line">{{ $draft->ai_advice }}</p></div>@endif
@if(!$draft->performance_target_id)
<form class="planning-form" method="post" action="{{ route('planning.scope-suggest',$draft) }}">@csrf<button class="is-secondary">Ask AI to review relevance</button></form>
<form class="planning-form" method="post" action="{{ route('planning.scope-confirm',$draft) }}">@csrf<label class="spms-choice"><input name="confirmed" value="1" type="checkbox" required> I confirm the recipients, quantity meaning, standards and source.</label><button>Confirm OPCR target draft</button></form>
@else<p class="spms-alert" data-tone="success">Confirmed as target #{{ $draft->performance_target_id }}.</p>@endif
</section>
@endif
@if(!$draft?->performance_target_id)
<section class="planning-card"><h2>{{ $draft ? 'Revise this draft' : 'Describe the target' }}</h2>
<p class="mb-4 text-sm text-slate-600">Example: “2 exchange students deployed abroad”. Confirm where it applies and which departments are responsible.</p>
<form class="planning-form" method="post" action="{{ route('planning.scope-store',$plan) }}" data-guided-target>
@csrf
@if($document)<input type="hidden" name="document_draft_id" value="{{ $document->id }}">@endif
@if($proposal)<input type="hidden" name="proposal_id" value="{{ $proposal->id }}"><p class="spms-alert" data-tone="info">Reviewing upward proposal #{{ $proposal->id }}. It remains pending until this scope is confirmed.</p>@endif
@include('planning.target-fields',['prefill'=>$document?->proposal ?? [],'scopePayload'=>$draft?->payload ?? $proposal?->proposal ?? []])
@include('planning.scope-fields',['scope'=>$draft?->payload ?? []])
<button>Save private draft and review total</button></form></section>
@endif
<a class="text-blue-700 underline" href="{{ route('planning.show',$plan) }}">Return to OPCR</a>
</div></x-app-layout>
