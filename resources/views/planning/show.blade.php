<x-app-layout>
<x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-2xl font-bold">{{ strtoupper($plan->level) }} · {{ $plan->office->name }}</h1><p class="text-sm text-gray-600">@if($plan->employee){{ $plan->employee->name }} · @endif{{ $plan->cycle->label }} · Revision {{ $plan->revision }}</p></div><a class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" href="{{ route('planning.index') }}">All plans</a></div></x-slot>
<div class="planning-page mx-auto max-w-7xl space-y-6 p-6">
@include('planning.messages')
<div class="flex flex-wrap items-center justify-between gap-3"><nav class="spms-plan-path" aria-label="Target reporting levels">@foreach(['opcr','dpcr','upcr','ipcr'] as $level)<span @if($plan->level===$level) aria-current="step" @endif>{{ strtoupper($level) }}</span>@unless($loop->last)<span aria-hidden="true">→</span>@endunless @endforeach</nav><x-planning-status :state="$plan->state" /></div>
<nav class="spms-section-nav" aria-label="Plan sections"><a href="#responsibility" aria-current="location">Commitments</a><a href="#plan-targets">Targets</a>@if($plan->state==='approved')<a href="#performance-review">Final review</a>@endif<a href="#planning-history">History</a></nav>
<section id="responsibility" class="planning-card"><div class="flex flex-wrap justify-between gap-3"><div><h2>Commitments and responsibility</h2><p class="text-sm text-slate-600">Reviewer: {{ $plan->reviewer->name }}. Planning approval is separate from final performance review.</p>
@if($plan->parent)<a href="{{ route('planning.show',$plan->parent) }}" class="text-blue-700 underline">Parent: {{ strtoupper($plan->parent->level) }} #{{ $plan->parent_id }}</a>
@endif
@if($plan->head_exception_reason)<p>Head’s reporting path: {{ $plan->head_exception_reason }}</p>
@endif</div><div class="space-x-3"><a class="text-blue-700 underline" href="{{ route('planning.export',[$plan,'pdf']) }}">PDF</a><a class="text-blue-700 underline" href="{{ route('planning.export',[$plan,'excel']) }}">Excel</a></div></div>
@if($canManage && $plan->state==='draft')<form class="planning-form" method="post" action="{{ route('planning.transition',[$plan,'submit']) }}">
@csrf<button>Submit commitments for review</button></form>
@endif
@if($plan->employee_id===auth()->id() && in_array($plan->state,['draft','submitted']))<form class="planning-form" method="post" action="{{ route('planning.transition',[$plan,'agree']) }}">
@csrf<label><input type="checkbox" name="confirmed" value="1" required> I discussed and agree with these commitments.</label><button>Record my agreement</button></form>
@endif
@if($plan->state==='endorsed')
<p class="mt-4 rounded bg-blue-50 p-3">Reviewed and awaiting parent approval. This revision is not yet confirmed; previous approved shares remain reserved.</p>
@if(auth()->id()===$plan->reviewer_id)
<form class="planning-form" method="post" action="{{ route('planning.transition',[$plan,'return']) }}">@csrf<label>Correction needed<textarea name="reason" required></textarea></label><button class="is-secondary">Return the reviewed draft</button></form>
@endif
@endif
@if($plan->agreed_at)<p class="mt-3">Employee agreement recorded {{ $plan->agreed_at->format('M d, Y H:i') }}.</p>
@endif
@if(auth()->id()===$plan->reviewer_id && $plan->state==='submitted')<form class="planning-form" method="post" enctype="multipart/form-data" action="{{ route('planning.transition',[$plan,'approve']) }}">
@csrf
@if($plan->level==='opcr')<h3>External review and approval</h3><label>PMT reviewer<input name="pmt_reviewer" required></label><label>University approver<input name="university_approver" required></label><label>Approval date<input type="date" name="approved_on" required max="{{ today()->toDateString() }}"></label><label>Approval evidence (PDF or image)<input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png" required></label>
@endif<button>Approve commitments</button></form>
<form class="planning-form" method="post" action="{{ route('planning.transition',[$plan,'return']) }}">
@csrf<label>Correction needed<textarea name="reason" required></textarea></label><button class="is-secondary">Return to preparer</button></form>
@endif
@if($canManage && $plan->state==='approved')<details class="mt-4"><summary>Propose an amendment</summary><form class="planning-form" method="post" action="{{ route('planning.transition',[$plan,'amend']) }}">
@csrf<label>Reason<textarea name="reason" required></textarea></label><button class="is-secondary">Open a new revision for review</button></form></details>
@endif
</section>
@if($plan->parent && $canManage && $plan->state==='draft')<section class="planning-card"><h2>Get relevant targets</h2><p>Drafts do not reserve quantities. Approval checks the remaining allocation again.</p><form method="post" action="{{ route('planning.suggest',$plan) }}" class="planning-form">
@csrf<button>Suggest relevance from verified duties</button></form>
@forelse($sources as $source)
@php($t=$source['target'])<div class="my-4 rounded border p-4"><h3>#{{ $t->id }} · {{ $t->target }}</h3><p>{{ $t->success_indicator }}</p><p>{{ $t->quantity }} {{ $t->unit }} · {{ $t->standards }}</p><p>Unallocated: {{ $source['summary']['unallocated'] ?? 'Not a divisible count' }}</p>
@if($source['eligible'])<form class="planning-form" method="post" action="{{ route('planning.target',$plan) }}">
@csrf<input type="hidden" name="parent_target_id" value="{{ $t->id }}"><label>Local target title (optional)<input name="local_target" maxlength="255" placeholder="Use wording appropriate to your office"></label><label>Your committed quantity<input type="number" name="quantity" min="0.01" step="0.01" value="{{ $t->measurement==='count' ? '' : $t->quantity }}" required></label>
@foreach($source['suggestion']['drivers'] as $id=>$driver)
@if($driver['office_id']===$plan->office_id && ($driver['employee_id'] ?? null)===$plan->employee_id)<p>Suggested share: {{ $source['suggestion']['shares'][$id] ?? 'Manual entry needed' }} · {{ $driver['name'] }}: {{ $driver['value'] }} · Source: {{ $driver['source'] }}</p>
@endif
@endforeach
<label>Rating weight (%)<input name="weight" type="number" min="0" max="100" step="0.01" value="0" required></label>
@if($t->measurement==='shared')<label><input type="checkbox" name="contributes_only" value="1"> Contributor only; another office owns the output</label>
@endif<label>Allocation explanation / override reason<textarea name="override_reason" required></textarea></label><button>Add linked target to draft</button></form>
@else<p class="mt-2 text-amber-800">Not available: the parent head must confirm its relevance to your office or duties.</p>
@endif</div>
@empty<p>No parent targets are available yet.</p>
@endforelse</section>
@endif
@if($canManage && $plan->state==='draft' && $plan->level==='opcr')<details class="planning-card"><summary>Add an OPCR target</summary><form class="planning-form" method="post" action="{{ route('planning.target',$plan) }}">
@csrf
@include('planning.target-fields')<button>Save target draft</button></form><p class="mt-4"><a class="text-blue-700 underline" href="{{ route('target-documents.index') }}">Or extract drafts from a planning document</a></p></details>
@endif
<div id="plan-targets" class="pt-1"><h2 class="text-lg font-semibold">Targets and supporting work</h2><p class="mt-1 text-sm text-slate-500">Review each commitment, its allocation, and the evidence recorded against it.</p></div>
@forelse($lines as $lineRow)
@php($t=$lineRow['target'])
@php($s=$lineRow['summary'])<section class="planning-card"><h2>#{{ $t->id }} · {{ $t->target }}</h2><p>{{ $t->success_indicator }}</p><p class="mt-2 text-sm text-gray-600">Root OPCR target #{{ $t->root_target_id }}
@if($t->parent_target_id) · Parent target #{{ $t->parent_target_id }} · Source revision {{ $t->source_revision }}
@endif</p>
@if($t->needs_reconciliation)<p class="mt-3 rounded bg-amber-50 p-3 text-amber-900">The parent changed. Open an amendment and reconcile this line against the new source before approval or reporting.</p>
@endif
<div class="spms-plan-metrics my-4 grid gap-px sm:grid-cols-4">
@foreach(['Committed'=>$t->quantity.' '.$t->unit,'Reported'=>$s['reported'] ?? 'Unavailable','Validated'=>$s['validated'] ?? 'Unavailable','Unallocated'=>$s['unallocated'] ?? 'Not applicable'] as $label=>$value)<div class="rounded bg-gray-50 p-3"><p class="text-sm text-gray-600">{{ $label }}</p><p class="text-xl font-semibold">{{ $value }}</p></div>
@endforeach</div><p>Quality / timeliness: {{ $t->standards }} · Deadline {{ $t->deadline->format('M d, Y') }} · Rating weight {{ $t->weight }}%</p><p>Source: {{ $t->source_evidence }}</p>
@if($s['extra']>0)<p class="mt-2 text-amber-800">Additional output: {{ $s['extra'] }}. Original commitment unchanged; validate the supporting work separately.</p>
@endif
@if($canManage && $plan->state==='draft')<details class="mt-4"><summary>Edit / reconcile draft target</summary><form class="planning-form" method="post" action="{{ route('planning.target-update',$t) }}">
@csrf
@if($plan->level==='opcr')
@include('planning.target-fields',['line'=>$t])
@else
<label>Current parent target<select name="source_target_id">
@foreach($sources->where('eligible',true) as $src)<option value="{{ $src['target']->id }}"
@selected($t->parent_target_id===$src['target']->id)>#{{ $src['target']->id }} {{ $src['target']->target }}</option>
@endforeach</select></label><label>Quantity<input name="quantity" type="number" min="0.01" step="0.01" value="{{ $t->quantity }}" required></label><label>Rating weight (%)<input name="weight" type="number" min="0" max="100" step="0.01" value="{{ $t->weight }}" required></label><label>Reason<textarea name="override_reason" required></textarea></label>
@endif<button>Save draft changes</button></form></details>
@endif
@if($canManage && $plan->level!=='ipcr')<details class="mt-4"><summary>Confirm eligible offices or personnel</summary><form class="planning-form" method="post" action="{{ route('planning.eligibility',$t) }}">
@csrf
@include('planning.recipient')<label>Supporting mandate or duty<textarea name="reason" required></textarea></label><button>Confirm relevance</button></form>
@foreach($eligibilities->where('performance_target_id',$t->id) as $e)<p class="mt-2 text-sm">Office #{{ $e->office_id }}
@if($e->employee_id) · Person #{{ $e->employee_id }}
@endif — {{ $e->reason }}</p>
@endforeach</details>
@if($t->measurement==='count')<details class="mt-4"><summary>Record workload / capacity for suggested shares</summary><form class="planning-form" method="post" action="{{ route('planning.driver',$t) }}">
@csrf
@include('planning.recipient')<label>Common driver name and unit<input name="name" placeholder="Expected applications (cases)" required></label><div class="grid gap-3 sm:grid-cols-2"><label>Recorded driver value<input name="value" type="number" min="0.01" step="0.01" required></label><label>Maximum output capacity (optional)<input name="capacity" type="number" min="0" step="0.01"></label></div><label>Data source and period<textarea name="source" required></textarea></label><label>Valid until<input name="valid_until" type="date" min="{{ today()->toDateString() }}" required></label><button>Confirm allocation input</button></form></details>
@endif
@endif
@if($plan->state==='approved' && ($plan->employee_id===auth()->id() || (!$plan->employee_id && $plan->office->head_id===auth()->id())))<details class="mt-4"><summary>Record supporting output</summary><p class="text-sm text-gray-600">Use a unique business reference for each output or non-overlapping batch. Choose new output or a cumulative total. Keep the same method for this assignment; repeated totals are not added together.</p><form class="planning-form" method="post" enctype="multipart/form-data" action="{{ route('planning.output',$t) }}">
@csrf<label>Reporting method<select name="submission_mode"><option value="incremental">New output only</option><option value="cumulative">Cumulative total for this assignment</option></select></label><label>Unique output / batch reference<input name="reference" required></label><label>Details<textarea name="details" required></textarea></label><label>Quantity for the selected reporting method<input name="quantity" type="number" step="0.01" min="0.01" value="{{ $t->measurement==='rate' ? 1 : '' }}" required></label>
@if($t->measurement==='rate')<div class="grid gap-3 sm:grid-cols-2"><label>Cases meeting the standard<input name="numerator" type="number" step="0.01" min="0" required></label><label>Total cases<input name="denominator" type="number" step="0.01" min="0.01" required></label></div>
@endif<label>Supporting evidence (PDF or image)<input type="file" name="evidence" required accept=".pdf,.png,.jpg,.jpeg"></label><button>Submit reported output</button></form></details>
@endif
@if($canManage && $t->measurement==='shared')<details class="mt-4"><summary>Record a contribution without counting another output</summary><form class="planning-form" method="post" action="{{ route('planning.contribution', $s['outputs']->first()?->id ?? 0) }}">
@csrf<input type="hidden" name="performance_target_id" value="{{ $t->id }}"><label>Contribution details<textarea name="details" required></textarea></label><p>Use the accountable owner’s output record. If none is listed below, its owner must report it first.</p><button
@disabled($s['outputs']->isEmpty())>Record contribution</button></form></details>
@endif
@foreach($s['outputs'] as $out)<div class="mt-3 border-t pt-3"><p><strong>{{ $out->reference }}</strong> · {{ $out->validated_at ? 'Validated' : 'Reported, pending validation' }} · {{ $out->details }}</p><a class="text-blue-700 underline" href="{{ route('planning.output-download',$out) }}">Supporting evidence</a>
@foreach($out->evidence['attachments'] ?? [] as $index=>$file)
<a class="ml-3 text-blue-700 underline" href="{{ route('planning.output-download',$out) }}?attachment={{ $index }}">{{ $file['name'] }}</a>
@endforeach
@if($out->submitted_by===auth()->id() && $plan->report->status!=='approved')
<details class="mt-3"><summary>Add supporting file without changing quantity</summary><form class="planning-form" method="post" enctype="multipart/form-data" action="{{ route('planning.output-evidence',$out) }}">@csrf<label>Additional evidence<input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png" required></label><button>Add supporting file</button></form></details>
@endif

@if($out->target->plan->reviewer_id===auth()->id() && !$out->validated_at)<form class="planning-form" method="post" action="{{ route('planning.output-validate',$out) }}">
@csrf<button>Validate this output</button></form>
@endif</div>
@endforeach
@if(auth()->id()===$plan->reviewer_id && $plan->state==='approved')<details class="mt-4"><summary>Record reviewer ratings</summary><form class="planning-form" method="post" action="{{ route('planning.rate',$t) }}">
@csrf<div class="grid gap-3 sm:grid-cols-3">
@foreach(['quality_rating'=>'Quality','efficiency_rating'=>'Efficiency','timeliness_rating'=>'Timeliness'] as $field=>$label)<label>{{ $label }} (1–5)<input name="{{ $field }}" type="number" min="1" max="5" step="0.01" required></label>
@endforeach</div><label>Rating remarks<textarea name="remarks"></textarea></label><button>Save reviewer ratings</button></form></details>
@endif
</section>
@empty
<p class="rounded-lg border border-dashed border-slate-300 p-6 text-sm text-slate-500">This plan has no target lines yet. Add an OPCR target or get relevant targets from the parent plan when available.</p>
@endforelse
@if($plan->state==='approved')
<section id="performance-review" class="planning-card"><h2>Final performance review</h2><p>Submit this report after execution. The reviewer validates evidence and records ratings separately.</p><x-report-workflow :report="$plan->report" :type="$plan->level" /></section>
@endif
<section id="planning-history" class="planning-card"><h2>Planning history</h2>
@foreach($events as $event)<div class="border-b py-3"><p>{{ $event->created_at->format('M d, Y H:i') }} · {{ $event->actor?->name }} · {{ $event->action }}</p>
@foreach(collect($event->data)->except('evidence') as $label=>$value)
    @if(is_scalar($value))<p class="whitespace-pre-line text-sm text-gray-600">{{ ucfirst(str_replace('_',' ',$label)) }}: {{ $value }}</p>
    @elseif(is_array($value))<p class="text-sm text-gray-600">{{ ucfirst(str_replace('_',' ',$label)) }}: {{ collect($value)->filter(fn($v)=>is_scalar($v))->map(fn($v,$k)=>ucfirst(str_replace('_',' ',$k)).': '.$v)->join('; ') }}</p>
    @endif
@endforeach
@if(!empty($event->data['evidence']))<a class="text-blue-700 underline" href="{{ route('planning.approval-download',[$plan,$event]) }}">External approval evidence</a>
@endif</div>
@endforeach {{ $events->links() }}</section>
</div></x-app-layout>
