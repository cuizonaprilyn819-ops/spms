<x-app-layout>
<x-slot name="header"><h1 class="text-2xl font-bold">Commitment Planning</h1><p class="mt-1 text-sm text-gray-600">Agree on relevant targets, share responsibility, and follow their progress.</p></x-slot>
<div class="planning-page mx-auto max-w-7xl space-y-6 p-6">
@include('planning.messages')
@php
    $dutiesPeople = $people->filter(fn($person) => auth()->user()->hasAnyRole(['Administrator', 'Planning Officer']) || $person->office?->head_id===auth()->id());
    $planOffices = $offices->filter(fn($office) => auth()->user()->hasAnyRole(['Administrator', 'Planning Officer']) || $office->head_id===auth()->id() || $office->id===auth()->user()->office_id);
    $levelByOffice = ['office'=>'opcr','division'=>'dpcr','unit'=>'upcr'];
    $planLevels = auth()->user()->hasAnyRole(['Administrator', 'Planning Officer']) ? collect(['opcr','dpcr','upcr','ipcr']) : $planOffices->where('head_id',auth()->id())->pluck('planning_level')->map(fn($level) => $levelByOffice[$level] ?? null)->filter()->push('ipcr')->unique();
    $defaultLevel = $planLevels->first();
@endphp
@role('Administrator')<p><a class="spms-button spms-button-secondary inline-flex" href="{{ route('planning.campuses') }}">Campuses and planning access</a></p>@endrole
<section class="grid gap-4 md:grid-cols-2" aria-label="Choose a planning direction">
    <div class="planning-card"><h2>Start with Campus OPCR</h2><p>Campus OPCR → DPCR → UPCR → IPCR</p><p class="mt-2 text-sm text-slate-600">Prepare the campus targets, then cascade relevant shares through departments, units and employees.</p><a class="spms-button mt-3 inline-flex" href="#new-plan">Create a commitment plan</a></div>
    <div class="planning-card"><h2>Start with an individual proposal</h2><p>IPCR proposal → UPCR review → DPCR review → Campus OPCR</p><p class="mt-2 text-sm text-slate-600">Propose individual work even before parent plans exist. Each responsible head reviews it upward. Accepted targets enter the OPCR draft before linked commitments are confirmed.</p><a class="spms-action-info mt-3 inline-flex" data-action-icon="send" href="#proposals">Propose a target upward</a></div>
</section>
<nav class="spms-section-nav" aria-label="Planning sections"><a href="#plans" aria-current="location">Plans</a><a href="#new-plan">Create a plan</a><a href="#proposals">Upward proposals</a>@if($dutiesPeople->isNotEmpty() || auth()->user()->hasAnyRole(['Administrator', 'Planning Officer']))<a href="#office-setup">Duties &amp; office setup</a>@endif</nav>
<section id="plans" class="planning-card"><div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 class="!mb-1">Plans by reporting level</h2><p class="text-sm text-slate-500">Open a plan to review its targets, allocation, and supporting work.</p></div><span class="text-xs text-slate-500">{{ $plans->count() }} {{ Str::plural('plan', $plans->count()) }} on this page</span></div><div class="overflow-x-auto"><table data-workspace-table class="planning-table planning-plan-list"><thead><tr><th>Plan</th><th>Office / person</th><th>Period</th><th>Commitments</th><th>Revision</th></tr></thead><tbody>
@forelse($plans as $p)<tr><td data-label="Plan"><a class="font-semibold text-blue-800 underline decoration-blue-200 underline-offset-4 hover:decoration-blue-700" href="{{ route('planning.show',$p) }}">{{ strtoupper($p->level) }} #{{ $p->id }}</a></td><td data-label="Office / person"><span class="font-medium">{{ $p->office->name }}</span>@if($p->employee)<span class="block text-xs text-slate-500">{{ $p->employee->name }}</span>@endif</td><td data-label="Period">{{ $p->cycle->label }}</td><td data-label="Commitments"><x-planning-status :state="$p->state" /></td><td data-label="Revision">{{ $p->revision }}</td></tr>
@empty<tr><td colspan="5" class="!py-8"><p class="font-medium">No plans are available to you yet.</p><p class="mt-1 text-slate-500">Create a draft below when your evaluation period and reporting structure are ready.</p></td></tr>
@endforelse
</tbody></table></div></section>
<details id="new-plan" class="planning-card"><summary>Create a commitment plan</summary><p class="mt-2 text-sm text-slate-500">Campus-based periods start with Campus OPCR, then DPCR, UPCR and IPCR. University-based periods retain their University OPCR above the campus. Each child selects its immediate parent. Use an upward proposal below to start before parent plans exist.</p><form class="planning-form" method="post" action="{{ route('planning.store') }}">
@csrf
<div class="grid gap-3 sm:grid-cols-3"><label>Period<select name="evaluation_cycle_id" required>
@foreach($cycles->where('planning_enabled',true)->where('status','open') as $c)<option value="{{ $c->id }}">{{ $c->label }} · {{ $c->usesUniversityOpcr() ? 'University-based' : 'Campus-based' }}</option>
@endforeach</select></label>
<label>Level<select name="level">
@foreach($planLevels as $l)<option value="{{ $l }}">{{ strtoupper($l) }}</option>
@endforeach</select></label><label>Responsible office<select name="office_id">
@foreach($planOffices as $o)<option value="{{ $o->id }}" @selected($o->id===auth()->user()->office_id)>{{ $o->name }} — {{ $o->planning_level ?: 'Not configured' }}</option>
@endforeach</select></label></div>
<label>Parent plan (blank only for the root OPCR)<select name="parent_id"><option value="">No parent</option>
@foreach($parentPlans as $p)<option value="{{ $p->id }}">#{{ $p->id }} {{ strtoupper($p->level) }} · {{ $p->office->name }} · {{ $p->state }}</option>
@endforeach</select></label>
<label>Employee (IPCR only)<select name="employee_id"><option value="">Office commitment</option>
@foreach($people as $person)<option value="{{ $person->id }}" @selected($defaultLevel==='ipcr' && $person->id===auth()->id())>{{ $person->name }} — {{ $person->office?->name }}</option>
@endforeach</select></label>
<label>Head’s actual-level IPCR explanation, when applicable<textarea name="head_exception_reason"></textarea></label><button>Create draft</button></form></details>
<details id="proposals" class="planning-card"><summary>Propose a target upward</summary><p class="mt-2 text-sm text-gray-600">Start from your planned IPCR work. Your unit head reviews it for UPCR, the department head reviews it for DPCR, and the campus head reviews it for Campus OPCR. You can propose before any plan exists. A campus-based period stops at Campus OPCR; university-based periods continue to the University OPCR. Approved commitments change through reviewed amendments.</p>
<form class="planning-form" method="post" action="{{ route('planning.proposal') }}">
@csrf<label>Period<select name="evaluation_cycle_id">
@foreach($cycles->where('planning_enabled',true)->where('status','open') as $c)<option value="{{ $c->id }}">{{ $c->label }}</option>
@endforeach</select></label>
<label>Existing OPCR target ID to amend (optional)<input name="amends_target_id" type="number" min="1"></label>
@include('planning.target-fields')<button>Send proposal</button></form></details>
@foreach($proposals as $proposal)<section class="planning-card"><h2>Proposal #{{ $proposal->id }} · {{ $proposal->proposal['target'] }}</h2><p><x-planning-status :state="$proposal->state" /> <span class="ml-2 text-sm text-slate-500">{{ $proposal->office->name }}</span></p><p class="mt-2 text-sm text-slate-600">Current review office: {{ $proposal->reviewOffice->name }} · {{ $proposal->cycle->label }}</p><p>{{ $proposal->review_notes }}</p>
<p class="mt-2 text-sm text-slate-600">Proposed by {{ $proposal->author->name }}</p>
<dl class="mt-3 grid gap-3 sm:grid-cols-3">
    <div><dt class="text-sm text-slate-600">Proposed quantity</dt><dd class="font-semibold">{{ $proposal->proposal['quantity'] }} {{ $proposal->proposal['unit'] }}</dd></div>
    <div><dt class="text-sm text-slate-600">Measurement</dt><dd>{{ $proposal->proposal['measurement'] }}</dd></div>
    <div><dt class="text-sm text-slate-600">Proposed deadline</dt><dd>{{ $proposal->proposal['deadline'] }}</dd></div>
</dl>
<div class="mt-3 space-y-2 text-sm"><p><strong>Success indicator:</strong> {{ $proposal->proposal['success_indicator'] }}</p><p><strong>Quality and timeliness standards:</strong> {{ $proposal->proposal['standards'] }}</p><p class="whitespace-pre-wrap"><strong>Source:</strong> {{ $proposal->proposal['source_evidence'] }}</p></div>
@if($proposal->state==='returned' && $proposal->submitted_by === auth()->id())<form class="planning-form" method="post" action="{{ route('planning.proposal-resubmit',$proposal) }}">
@csrf
@include('planning.target-fields',['prefill'=>$proposal->proposal])<button>Resubmit revised proposal</button></form>
@endif
@if($proposal->state==='pending' && $proposal->submitted_by !== auth()->id())<form class="planning-form" method="post" action="{{ route('planning.proposal-review',$proposal) }}">
@csrf
@php($atProposalRoot = in_array($proposal->review_office_id,app(\App\Services\PlanningOrganization::class)->rootOfficeIds($proposal->cycle),true))
<label>Decision<select name="decision">@if(!$atProposalRoot)<option value="forward">Forward to next reporting level</option>@else<option value="materialize">Review for OPCR draft / amendment</option>@endif<option value="return">Return for revision</option><option value="reject">Reject</option></select></label>
<label>OPCR plan, when adding to commitments<select name="root_plan_id"><option value="">Select if applicable</option>
@foreach($plans->where('level','opcr')->whereNull('parent_id')->where('office_id',$proposal->review_office_id)->where('evaluation_cycle_id',$proposal->evaluation_cycle_id) as $p)<option value="{{ $p->id }}">#{{ $p->id }} {{ $p->level_label }} · {{ $p->office->name }}</option>
@endforeach</select></label><label>Review notes<textarea name="review_notes" required></textarea></label><button>Record decision</button></form>
@endif</section>
@endforeach
@if($dutiesPeople->isNotEmpty() || auth()->user()->hasAnyRole(['Administrator', 'Planning Officer']))<div id="office-setup" class="pt-3"><p class="spms-section-label">Office setup</p><h2 class="text-lg font-semibold">Duties and reporting relationships</h2><p class="mt-1 text-sm text-slate-500">Verified duties help the right targets reach the right people.</p></div>@endif
@if($dutiesPeople->isNotEmpty())<details class="planning-card"><summary>Personnel duties</summary>
@foreach($people as $person)
@if(auth()->user()->hasAnyRole(['Administrator', 'Planning Officer']) || $person->office?->head_id===auth()->id())<form class="planning-form border-b pb-4" method="post" action="{{ route('planning.duties',$person) }}">
@csrf<label>{{ $person->name }} — {{ $person->office?->name }}<textarea name="planning_duties" required>{{ $person->planning_duties }}</textarea></label><button>Save verified duties</button></form>
@endif
@endforeach</details>@endif
@hasanyrole('Administrator|Planning Officer')
<details class="planning-card"><summary>Reporting structure</summary><p class="text-sm text-gray-600">Verify Office → Division → Unit. A head belongs to their office; a reviewer is a separate person. Existing plan structures are preserved.</p>
@foreach($offices as $o)<details class="mt-4 border-t pt-3"><summary>{{ $o->name }}</summary><form class="planning-form" method="post" action="{{ route('planning.organization',$o) }}">
@csrf
<label>Level<select name="planning_level">
@foreach(['office','division','unit'] as $l)<option
@selected($o->planning_level===$l)>{{ $l }}</option>
@endforeach</select></label>
<label>Parent<select name="parent_office_id"><option value="">None (office level)</option>
@foreach($offices->where('id','!=',$o->id) as $parent)<option value="{{ $parent->id }}"
@selected($o->parent_office_id===$parent->id)>{{ $parent->name }}</option>
@endforeach</select></label>
<div class="grid gap-3 sm:grid-cols-2">
@foreach(['head_id'=>'Responsible head','planning_reviewer_id'=>'Commitment reviewer'] as $field=>$label)<label>{{ $label }}<select name="{{ $field }}" required><option value="">Select a person</option>
@foreach($people as $person)<option value="{{ $person->id }}"
@selected($o->$field===$person->id)>{{ $person->name }} — {{ $person->office?->name }}</option>
@endforeach</select></label>
@endforeach</div>
<label>Verified mandate<textarea name="mandate" required>{{ $o->mandate }}</textarea></label><label><input name="verified" type="checkbox" value="1" required> I verified these reporting relationships and duties.</label><button>Verify structure</button></form></details>
@endforeach</details>
<details class="planning-card"><summary>Enable planning for a new period</summary><p>Existing reports and assignments remain unchanged. Activation is permanent for the selected period.</p>
@foreach($cycles->where('planning_enabled',false)->where('status','open') as $c)<form class="planning-form" method="post" action="{{ route('planning.activate',$c) }}">
@csrf<h3>{{ $c->label }}</h3><label>Readiness record: policy checked, reporting structure, reviewers and allocation inputs<textarea name="planning_readiness" required minlength="20"></textarea></label><label><input type="checkbox" name="confirmed" value="1" required> I verified readiness and will review missing data before allocations.</label><button>Enable for this period</button></form>
@endforeach</details>
@endhasanyrole
</div></x-app-layout>
