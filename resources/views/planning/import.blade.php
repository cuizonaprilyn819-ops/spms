<x-app-layout><x-slot name="header"><h1 class="text-2xl font-bold">Review extracted OPCR target</h1></x-slot><div class="planning-page mx-auto max-w-4xl space-y-5 p-6">
@include('planning.messages')<section class="planning-card"><h2>{{ $draft->proposal['title'] }}</h2><blockquote>{{ $draft->proposal['source_excerpt'] }}</blockquote><p class="mt-3">{{ $draft->proposal['classification_reason'] }}. This creates a draft commitment; no work is assigned yet.</p>
@if($draft->performance_target_id)<p>Already imported as target #{{ $draft->performance_target_id }}.</p>
@else
@forelse($plans->where('state','draft') as $p)<details class="mt-4"><summary>Import into OPCR #{{ $p->id }} — {{ $p->office->name }}</summary><form class="planning-form" method="post" action="{{ route('planning.import',[$p,$draft]) }}">
@csrf
@include('planning.target-fields',['prefill'=>$draft->proposal])<button>Save reviewed OPCR draft</button></form></details>
@empty<p class="mt-4">Create an OPCR draft for this period in <a class="text-blue-700 underline" href="{{ route('planning.index') }}">Commitment Planning</a> first.</p>
@endforelse
@endif</section></div></x-app-layout>
