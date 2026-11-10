@props(['plan'])
@php
    $nodes=[]; $seen=[]; $cursor=$plan;
    while($cursor && !isset($seen[$cursor->id])) {
        $seen[$cursor->id]=true;
        array_unshift($nodes,$cursor);
        $cursor=$cursor->parent;
    }
@endphp
<nav class="spms-report-lineage" aria-label="Commitment reporting lineage">
@foreach($nodes as $node)
@unless($loop->first)<span class="spms-lineage-arrow" aria-hidden="true">›</span>@endunless
@php($canOpen=app(\App\Services\PlanningOrganization::class)->canView(auth()->user(),$node))
@if(!$loop->last && $canOpen)<a href="{{ route('planning.show',$node) }}">@else<span class="spms-lineage-node" @if($loop->last) aria-current="page" @endif>@endif
<strong>{{ $node->level_label }}</strong><span>{{ $node->employee?->name ?? $node->office->name }}</span>
@if(!$loop->last && $canOpen)</a>@else</span>@endif
@endforeach
</nav>
