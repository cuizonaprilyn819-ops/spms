<!doctype html><html><head><meta charset="utf-8"><title>Commitment plan</title><style>body{font:11px DejaVu Sans,sans-serif;color:#132238}table{width:100%;border-collapse:collapse}td,th{border:1px solid #aaa;padding:7px;text-align:left;vertical-align:top}th{background:#eef2f6}p{line-height:1.5}</style></head><body>
<h1>{{ $plan->level_label }} — {{ $plan->office->name }}</h1><p>{{ $plan->employee?->name }} · {{ $plan->cycle->label }} · Revision {{ $plan->revision }} · Commitments {{ $plan->state }}<br>Planning approval is separate from final performance review. Generated {{ now()->format('M d, Y H:i') }}.</p>
@php
    $reportingPath=[]; $seenPlans=[]; $cursor=$plan;
    while($cursor && !isset($seenPlans[$cursor->id])) {
        $seenPlans[$cursor->id]=true;
        $levelLabel=$cursor->level_label;
        array_unshift($reportingPath,$levelLabel.' — '.($cursor->employee?->name ?? $cursor->office->name));
        $cursor=$cursor->parent;
    }
@endphp
<p>Reporting path: {{ implode(' → ',$reportingPath) }}</p>
<table><thead><tr><th>Target / source</th><th>Measure and standards</th><th>Committed</th><th>Reported</th><th>Validated</th><th>Unallocated</th><th>Accomplishment statements from MOV</th></tr></thead><tbody>
@foreach($lines as $row)
@php($t=$row['target'])
@php($s=$row['summary'])<tr><td>#{{ $t->id }} {{ $t->target }}<br>Root #{{ $t->root_target_id }} · Parent #{{ $t->parent_target_id }}<br>{{ $t->source_evidence }}@if($t->scope_metadata)<br>Coverage: {{ $t->scope_metadata['coverage'] }} · {{ $t->scope_metadata['quantity_basis'] }} · Entered {{ $t->scope_metadata['entered_quantity'] }} {{ $t->unit }}<br>Responsible departments: {{ \App\Models\Office::whereIn('id',$t->scope_metadata['recipient_ids'])->pluck('name')->join('; ') }}@endif</td><td>{{ $t->measurement }} · {{ $t->success_indicator }}<br>{{ $t->standards }}<br>Due {{ $t->deadline->format('M d, Y') }}</td><td>{{ $t->quantity }} {{ $t->unit }}</td><td>{{ $s['reported'] ?? 'Unavailable' }}</td><td>{{ $s['validated'] ?? 'Unavailable' }}</td><td>{{ $s['unallocated'] ?? 'Not applicable' }}</td><td>@foreach($s['outputs'] as $output)@if($output->analysis?->status === 'completed' && $output->analysis->statement)<p>{{ $output->analysis->statement }}</p>@endif @endforeach</td></tr>
@endforeach</tbody></table>
</body></html>
