@props(['report', 'type'])
@php
    $lineage = [];
    $cursor = $report;
    $level = $type;
    $seen = [];
    while ($cursor) {
        $key = $cursor::class.':'.$cursor->getKey();
        if (isset($seen[$key])) break;
        $seen[$key] = true;
        array_unshift($lineage, [
            'type' => $level, 'report' => $cursor,
            'label' => $level === 'ipcr' ? ($cursor->employee?->name ?? 'Personnel not configured')
                : ($cursor->office?->name ?? $cursor->employee?->office?->name ?? 'Office not configured'),
        ]);
        $parentPlan = $cursor->commitmentPlan?->parent;
        if ($parentPlan) {
            $cursor = $parentPlan->report;
            $level = $parentPlan->level;
        } else {
            $cursor = match ($level) { 'ipcr' => $cursor->upcr, 'upcr' => $cursor->dpcr, 'dpcr' => $cursor->opcr, default => null };
            $level = match ($level) { 'ipcr' => 'upcr', 'upcr' => 'dpcr', 'dpcr' => 'opcr', default => '' };
        }
    }
    $canOpenParents = auth()->user()->hasAnyRole(['Administrator', 'Planning Officer', 'Campus Director', 'HR', 'HR Head', 'HRM Office II'])
        && ! auth()->user()->hasRole('Supervisor');
@endphp
<nav class="spms-report-lineage" aria-label="Report lineage">
    @foreach($lineage as $node)
        @unless($loop->first)<span class="spms-lineage-arrow" aria-hidden="true">›</span>@endunless
        @if(! $loop->last && $canOpenParents)
            <a href="{{ route($node['type'].'s.show', $node['report']) }}"><strong>{{ strtoupper($node['type']) }}</strong><span>{{ $node['label'] }}</span></a>
        @else
            <span class="spms-lineage-node" @if($loop->last) aria-current="page" @endif><strong>{{ strtoupper($node['type']) }}</strong><span>{{ $node['label'] }}</span></span>
        @endif
    @endforeach
</nav>
