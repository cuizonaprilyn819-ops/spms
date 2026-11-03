<x-app-layout>
<x-slot name="header"><h1 class="text-2xl font-bold">Target conflicts</h1><p class="text-sm text-slate-600">Application checks and AI suggestions with recorded reviewer responses.</p></x-slot>
<div class="planning-page mx-auto max-w-7xl space-y-5 p-6">
@if(session('status'))<x-semantic-alert tone="success">{{ session('status') }}</x-semantic-alert>@endif
<form method="get" class="planning-card planning-form grid gap-3 sm:grid-cols-4">
<label>Period<select name="period"><option value="">All permitted periods</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected((string)request('period')===(string)$period->id)>{{ $period->label }}</option>@endforeach</select></label>
<label>Office<select name="office"><option value="">All permitted offices</option>@foreach($offices as $office)<option value="{{ $office->id }}" @selected((string)request('office')===(string)$office->id)>{{ $office->name }}</option>@endforeach</select></label>
<label>Finding source<select name="source"><option value="">Application and AI</option><option value="rule" @selected(request('source')==='rule')>Application rule</option><option value="ai" @selected(request('source')==='ai')>AI suspicion</option></select></label>
<label>Status<select name="status"><option value="">All statuses</option>@foreach(['open','addressed','dismissed','correction-required','pending','completed','failed','outdated','rules-only'] as $state)<option value="{{ $state }}" @selected(request('status')===$state)>{{ ucfirst(str_replace('-',' ',$state)) }}</option>@endforeach</select></label><button>Filter results</button>
</form>
<x-semantic-alert tone="info">Similar wording alone does not prove a conflict. Linked cascades and shared contributors are expected. Historical source documents remain unchanged.</x-semantic-alert>
@forelse($scans as $scan)<article class="planning-card flex flex-wrap items-center justify-between gap-4"><div><h2 class="font-semibold">{{ ucfirst(str_replace('-',' ',$scan->subject_kind)) }} #{{ $scan->subject_id }}</h2><p class="text-sm text-slate-600">{{ $scan->created_at->format('M d, Y H:i') }} · {{ $scan->published ? 'Shared for review' : 'Private draft check' }} · {{ ucfirst($scan->status) }}</p></div><a class="font-semibold text-violet-700 underline" href="{{ route('target-conflicts.show',$scan) }}">View findings and decisions</a></article>@empty<p class="planning-card">No matching checks are available to your account. Open a target or plan and choose Check target conflicts.</p>@endforelse
</div></x-app-layout>
